<?php
/**
 * Durable File 21 storage and recovery primitives for File 22 workflows.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns short-lived preview tokens, native idempotency markers, bounded
 * retention, automatic reconciliation, and an explicit administrator repair.
 */
final class UniversalComposerWorkflowStore {
	private const OPTION_PREFIX        = 'sabri_hnf_file22_idem_';
	private const LAST_REPORT_OPTION   = 'sabri_hnf_file22_recovery_last_report';
	private const CRON_HOOK            = 'sabri_hnf_file22_idempotency_cleanup';
	private const ADMIN_ACTION         = 'sabri_hnf_file22_reconcile_idempotency';
	private const MARKER_META_KEY      = '_sabri_hnf_file22_idempotency_hash';
	private const FINGERPRINT_META_KEY = '_sabri_hnf_file22_payload_fingerprint';
	private const PREVIEW_MARKER       = 'sabri_file22_preview';
	private const PREVIEW_EXPIRES      = 'sabri_file22_expires';
	private const PREVIEW_SIGNATURE    = 'sabri_file22_signature';
	private const PREVIEW_TTL          = 600;
	private const PROCESSING_TTL       = 900;
	private const COMPLETED_TTL        = 2592000;
	private const RECOVERABLE_TTL      = 604800;
	private const CLEANUP_BATCH        = 100;

	/** Attach request-time preview enforcement, cron, and administrator repair. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'enforce_preview_token' ), -100 );
			add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_reconciliation' ) );
			add_action( 'admin_init', array( __CLASS__, 'maybe_schedule_reconciliation' ) );
			add_action( 'admin_menu', array( __CLASS__, 'register_recovery_page' ) );
			add_action( 'admin_post_' . self::ADMIN_ACTION, array( __CLASS__, 'handle_manual_reconciliation' ) );
		}
	}

	/** Produce a signed preview URL whose expiry is enforced when opened. */
	public static function issue_preview_url( int $post_id, int $user_id ): array {
		if ( $post_id <= 0 || $user_id <= 0 || ! function_exists( 'get_preview_post_link' ) || ! self::user_can_preview_draft( $post_id, $user_id ) ) {
			return array( 'url' => '', 'expires_at' => 0 );
		}

		$url = (string) get_preview_post_link( $post_id );
		if ( '' === $url || ! function_exists( 'add_query_arg' ) ) {
			return array( 'url' => '', 'expires_at' => 0 );
		}

		$expires   = time() + self::PREVIEW_TTL;
		$signature = self::preview_signature( $post_id, $user_id, $expires );
		if ( '' === $signature ) {
			return array( 'url' => '', 'expires_at' => 0 );
		}

		$url = add_query_arg(
			array(
				self::PREVIEW_MARKER    => '1',
				self::PREVIEW_EXPIRES   => (string) $expires,
				self::PREVIEW_SIGNATURE => $signature,
			),
			$url
		);

		return array( 'url' => (string) $url, 'expires_at' => $expires );
	}

	/** Reject expired, forged, cross-user, non-draft, or unauthorized preview URLs. */
	public static function enforce_preview_token() {
		if ( empty( $_GET[ self::PREVIEW_MARKER ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$post_id = 0;
		if ( isset( $_GET['preview_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = absint( wp_unslash( $_GET['preview_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_GET['p'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_id = absint( wp_unslash( $_GET['p'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$user_id   = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$expires   = isset( $_GET[ self::PREVIEW_EXPIRES ] ) ? absint( wp_unslash( $_GET[ self::PREVIEW_EXPIRES ] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$signature = isset( $_GET[ self::PREVIEW_SIGNATURE ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::PREVIEW_SIGNATURE ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( self::preview_token_is_valid( $post_id, $user_id, $expires, $signature ) ) {
			if ( function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
			return;
		}

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		if ( function_exists( 'status_header' ) ) {
			status_header( 403 );
		}
		if ( function_exists( 'wp_die' ) ) {
			wp_die(
				esc_html__( 'This private preview link is invalid or has expired.', 'sabri-complete-home-news-feed' ),
				esc_html__( 'Preview unavailable', 'sabri-complete-home-news-feed' ),
				array( 'response' => 403 )
			);
		}
	}

	/** Side-effect-free validation used by runtime enforcement and tests. */
	public static function preview_token_is_valid( int $post_id, int $user_id, int $expires, string $signature ): bool {
		$now = time();
		if ( $post_id <= 0 || $user_id <= 0 || $expires <= $now || $expires > $now + self::PREVIEW_TTL || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $signature ) ) {
			return false;
		}
		if ( ! self::user_can_preview_draft( $post_id, $user_id ) ) {
			return false;
		}
		$expected = self::preview_signature( $post_id, $user_id, $expires );
		return '' !== $expected && hash_equals( $expected, $signature );
	}

	/** Return the opaque option key; the raw File 22 key is never persisted. */
	public static function option_key( int $user_id, string $key_hash ): string {
		return self::OPTION_PREFIX . $user_id . '_' . $key_hash;
	}

	/** Return a one-way submission-key hash. */
	public static function key_hash( string $idempotency_key ): string {
		return hash( 'sha256', $idempotency_key );
	}

	/** Load one controlled reconciliation record. */
	public static function load_record( string $option_key ) {
		return function_exists( 'get_option' ) ? get_option( $option_key, null ) : null;
	}

	/** Atomically acquire a new processing record. */
	public static function acquire_record( string $option_key, int $user_id, string $key_hash, string $fingerprint ): bool {
		if ( ! function_exists( 'add_option' ) ) {
			return false;
		}
		$now = time();
		return (bool) add_option(
			$option_key,
			array(
				'state'       => 'processing',
				'user_id'     => $user_id,
				'key_hash'    => $key_hash,
				'fingerprint' => $fingerprint,
				'created_at'  => $now,
				'updated_at'  => $now,
				'expires_at'  => $now + self::PROCESSING_TTL,
			),
			'',
			false
		);
	}

	/** Persist the native reference before reporting completion. */
	public static function persist_processing_reference( string $option_key, array $record, int $post_id ): bool {
		if ( ! function_exists( 'update_option' ) || ! function_exists( 'get_option' ) ) {
			return false;
		}
		$record['state']            = 'processing';
		$record['native_reference'] = self::native_reference( $post_id );
		$record['updated_at']       = time();
		$record['expires_at']       = time() + self::PROCESSING_TTL;
		update_option( $option_key, $record, false );
		return $record === get_option( $option_key, null );
	}

	/** Persist a completed record with bounded retention. */
	public static function complete_record( string $option_key, array $record, int $post_id, string $status ): bool {
		if ( ! function_exists( 'update_option' ) || ! function_exists( 'get_option' ) ) {
			return false;
		}
		$now                        = time();
		$record['state']            = 'completed';
		$record['native_reference'] = self::native_reference( $post_id );
		$record['status']           = $status;
		$record['completed_at']     = $now;
		$record['updated_at']       = $now;
		$record['expires_at']       = $now + self::COMPLETED_TTL;
		update_option( $option_key, $record, false );
		return $record === get_option( $option_key, null );
	}

	/** Remove one option record. */
	public static function delete_record( string $option_key ): bool {
		return function_exists( 'delete_option' ) ? (bool) delete_option( $option_key ) : false;
	}

	/** Attach recoverable native markers before or immediately after mutation. */
	public static function attach_native_marker( int $post_id, int $user_id, string $key_hash, string $fingerprint ): bool {
		if ( $post_id <= 0 || $user_id <= 0 || ! self::user_can_manage_post( $post_id, $user_id ) || ! function_exists( 'get_post_meta' ) || ! function_exists( 'update_post_meta' ) ) {
			return false;
		}
		$existing_key         = (string) get_post_meta( $post_id, self::MARKER_META_KEY, true );
		$existing_fingerprint = (string) get_post_meta( $post_id, self::FINGERPRINT_META_KEY, true );
		if ( ( '' !== $existing_key && ! hash_equals( $existing_key, $key_hash ) ) || ( '' !== $existing_fingerprint && ! hash_equals( $existing_fingerprint, $fingerprint ) ) ) {
			return false;
		}
		update_post_meta( $post_id, self::MARKER_META_KEY, $key_hash );
		update_post_meta( $post_id, self::FINGERPRINT_META_KEY, $fingerprint );
		return hash_equals( $key_hash, (string) get_post_meta( $post_id, self::MARKER_META_KEY, true ) )
			&& hash_equals( $fingerprint, (string) get_post_meta( $post_id, self::FINGERPRINT_META_KEY, true ) );
	}

	/** Remove native markers only when they match the expected submission. */
	public static function remove_native_marker( int $post_id, string $key_hash, string $fingerprint ): void {
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) || ! function_exists( 'delete_post_meta' ) ) {
			return;
		}
		if ( hash_equals( $key_hash, (string) get_post_meta( $post_id, self::MARKER_META_KEY, true ) ) ) {
			delete_post_meta( $post_id, self::MARKER_META_KEY, $key_hash );
		}
		if ( hash_equals( $fingerprint, (string) get_post_meta( $post_id, self::FINGERPRINT_META_KEY, true ) ) ) {
			delete_post_meta( $post_id, self::FINGERPRINT_META_KEY, $fingerprint );
		}
	}

	/** Find one previously-created native post by hashed idempotency material. */
	public static function find_native_post( int $user_id, string $key_hash, string $fingerprint ) {
		if ( $user_id <= 0 || ! function_exists( 'get_posts' ) ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'              => 'post',
				'post_status'            => 'any',
				'author'                 => $user_id,
				'fields'                 => 'ids',
				'posts_per_page'         => 2,
				'no_found_rows'          => true,
				'suppress_filters'       => false,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					'relation' => 'AND',
					array( 'key' => self::MARKER_META_KEY, 'value' => $key_hash, 'compare' => '=' ),
					array( 'key' => self::FINGERPRINT_META_KEY, 'value' => $fingerprint, 'compare' => '=' ),
				),
			)
		);
		if ( ! is_array( $ids ) || 1 !== count( $ids ) ) {
			return 0;
		}
		return absint( reset( $ids ) );
	}

	/** Schedule daily bounded reconciliation from an administrator request only. */
	public static function maybe_schedule_reconciliation() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) || ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_event' ) ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'daily', self::CRON_HOOK );
		}
	}

	/** Cron callback. */
	public static function run_scheduled_reconciliation() {
		self::reconcile_and_cleanup( self::CLEANUP_BATCH );
	}

	/** Register a narrowly-scoped administrator recovery page. */
	public static function register_recovery_page() {
		if ( function_exists( 'add_management_page' ) ) {
			add_management_page(
				__( 'File 22 Workflow Recovery', 'sabri-complete-home-news-feed' ),
				__( 'File 22 Workflow Recovery', 'sabri-complete-home-news-feed' ),
				'manage_options',
				'sabri-file22-workflow-recovery',
				array( __CLASS__, 'render_recovery_page' )
			);
		}
	}

	/** Render privacy-safe repair controls and aggregate-only evidence. */
	public static function render_recovery_page() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$report = function_exists( 'get_option' ) ? get_option( self::LAST_REPORT_OPTION, array() ) : array();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'File 22 Workflow Recovery', 'sabri-complete-home-news-feed' ); ?></h1>
			<p><?php echo esc_html__( 'Reconcile stale File 21 idempotency records without displaying post content, patient data, raw keys, or native references.', 'sabri-complete-home-news-feed' ); ?></p>
			<?php if ( is_array( $report ) && ! empty( $report['run_at'] ) ) : ?>
				<p><strong><?php echo esc_html__( 'Last run:', 'sabri-complete-home-news-feed' ); ?></strong> <?php echo esc_html( gmdate( 'Y-m-d H:i:s', (int) $report['run_at'] ) . ' UTC' ); ?></p>
				<ul>
					<li><?php echo esc_html( sprintf( __( 'Reconciled: %d', 'sabri-complete-home-news-feed' ), (int) ( $report['reconciled'] ?? 0 ) ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Recoverable drafts: %d', 'sabri-complete-home-news-feed' ), (int) ( $report['recoverable'] ?? 0 ) ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Expired records removed: %d', 'sabri-complete-home-news-feed' ), (int) ( $report['deleted'] ?? 0 ) ) ); ?></li>
					<li><?php echo esc_html( sprintf( __( 'Invalid records removed: %d', 'sabri-complete-home-news-feed' ), (int) ( $report['invalid'] ?? 0 ) ) ); ?></li>
				</ul>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ADMIN_ACTION ); ?>">
				<?php wp_nonce_field( self::ADMIN_ACTION ); ?>
				<?php submit_button( __( 'Run bounded reconciliation', 'sabri-complete-home-news-feed' ) ); ?>
			</form>
		</div>
		<?php
	}

	/** Handle an explicit capability- and nonce-protected repair request. */
	public static function handle_manual_reconciliation() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not authorized to run this repair.', 'sabri-complete-home-news-feed' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ADMIN_ACTION );
		self::reconcile_and_cleanup( self::CLEANUP_BATCH );
		wp_safe_redirect( admin_url( 'tools.php?page=sabri-file22-workflow-recovery' ) );
		exit;
	}

	/**
	 * Reconcile completed native writes and delete expired bounded records.
	 *
	 * @return array<string,int>
	 */
	public static function reconcile_and_cleanup( int $limit = self::CLEANUP_BATCH ): array {
		$report = array( 'run_at' => time(), 'scanned' => 0, 'reconciled' => 0, 'recoverable' => 0, 'deleted' => 0, 'invalid' => 0 );
		$rows   = self::option_rows( max( 1, min( self::CLEANUP_BATCH, $limit ) ) );
		foreach ( $rows as $row ) {
			++$report['scanned'];
			$option_key = isset( $row['option_name'] ) ? (string) $row['option_name'] : '';
			$record     = isset( $row['option_value'] ) ? maybe_unserialize( $row['option_value'] ) : null;
			if ( '' === $option_key || ! is_array( $record ) || empty( $record['fingerprint'] ) || empty( $record['key_hash'] ) || empty( $record['user_id'] ) ) {
				self::delete_record( $option_key );
				++$report['invalid'];
				continue;
			}
			if ( (int) ( $record['expires_at'] ?? 0 ) > time() ) {
				continue;
			}

			$post_id = self::post_id_from_reference( (string) ( $record['native_reference'] ?? '' ) );
			if ( $post_id <= 0 ) {
				$post_id = (int) self::find_native_post( (int) $record['user_id'], (string) $record['key_hash'], (string) $record['fingerprint'] );
			}
			$status = $post_id > 0 && function_exists( 'get_post_status' ) ? (string) get_post_status( $post_id ) : '';

			if ( in_array( $status, array( 'pending', 'future', 'publish', 'trash' ), true ) ) {
				self::complete_record( $option_key, $record, $post_id, self::normalize_status( $status ) );
				++$report['reconciled'];
				continue;
			}
			if ( 'draft' === $status ) {
				$record['state']            = 'recoverable';
				$record['native_reference'] = self::native_reference( $post_id );
				$record['updated_at']       = time();
				$record['expires_at']       = time() + self::RECOVERABLE_TTL;
				if ( function_exists( 'update_option' ) ) {
					update_option( $option_key, $record, false );
				}
				++$report['recoverable'];
				continue;
			}

			self::remove_native_marker( $post_id, (string) $record['key_hash'], (string) $record['fingerprint'] );
			self::delete_record( $option_key );
			++$report['deleted'];
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::LAST_REPORT_OPTION, $report, false );
		}
		return $report;
	}

	/** Whether a record has exceeded its processing lease. */
	public static function record_is_expired( array $record ): bool {
		return (int) ( $record['expires_at'] ?? 0 ) <= time();
	}

	/** Convert a positive post ID to the native opaque reference. */
	public static function native_reference( int $post_id ): string {
		return 'post-' . $post_id;
	}

	/** Convert the controlled native reference to a post ID. */
	public static function post_id_from_reference( string $reference ): int {
		if ( 1 !== preg_match( '/^post-([1-9][0-9]*)$/D', trim( $reference ), $matches ) ) {
			return 0;
		}
		return (int) $matches[1];
	}

	/** Normalize the native WordPress status into the File 22 vocabulary. */
	public static function normalize_status( string $status ): string {
		$map = array( 'draft' => 'draft', 'pending' => 'pending_review', 'future' => 'scheduled', 'publish' => 'published', 'trash' => 'rejected' );
		$status = function_exists( 'sanitize_key' ) ? sanitize_key( $status ) : strtolower( $status );
		return $map[ $status ] ?? '';
	}

	/** Return a bounded prepared option query. */
	private static function option_rows( int $limit ): array {
		global $wpdb;
		/** @var \wpdb $wpdb */
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'esc_like' ) ) {
			return array();
		}
		$like = $wpdb->esc_like( self::OPTION_PREFIX ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d", $like, $limit ), 'ARRAY_A' );
		return is_array( $rows ) ? $rows : array();
	}

	/** Only the signed-in author or native moderator may preview a draft. */
	private static function user_can_preview_draft( int $post_id, int $user_id ): bool {
		return function_exists( 'get_post_status' )
			&& 'draft' === (string) get_post_status( $post_id )
			&& self::user_can_manage_post( $post_id, $user_id );
	}

	/** Reuse File 21's native edit authority. */
	private static function user_can_manage_post( int $post_id, int $user_id ): bool {
		return class_exists( __NAMESPACE__ . '\\ComposerPermissions' )
			&& ComposerPermissions::user_can_edit_post( $post_id, $user_id );
	}

	/** HMAC the post, authenticated subject, and absolute expiry. */
	private static function preview_signature( int $post_id, int $user_id, int $expires ): string {
		$salt = function_exists( 'wp_salt' ) ? (string) wp_salt( 'auth' ) : ( defined( 'AUTH_SALT' ) ? (string) AUTH_SALT : '' );
		return '' !== $salt ? hash_hmac( 'sha256', implode( '|', array( $post_id, $user_id, $expires, self::PREVIEW_MARKER ) ), $salt ) : '';
	}
}
