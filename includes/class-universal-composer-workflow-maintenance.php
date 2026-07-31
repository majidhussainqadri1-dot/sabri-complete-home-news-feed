<?php
/**
 * Corrected retention and reconciliation controls for File 22 workflows.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces the first-pass maintenance callback so completed and recoverable
 * records expire instead of being renewed forever.
 */
final class UniversalComposerWorkflowMaintenance {
	private const OPTION_PREFIX      = 'sabri_hnf_file22_idem_';
	private const LAST_REPORT_OPTION = 'sabri_hnf_file22_recovery_last_report';
	private const CRON_HOOK          = 'sabri_hnf_file22_idempotency_cleanup';
	private const ADMIN_ACTION       = 'sabri_hnf_file22_reconcile_idempotency';
	private const RECOVERABLE_TTL    = 604800;
	private const BATCH_LIMIT        = 100;

	/** Replace first-pass callbacks with corrected bounded maintenance. */
	public static function register() {
		if ( function_exists( 'remove_action' ) ) {
			remove_action( self::CRON_HOOK, array( UniversalComposerWorkflowStore::class, 'run_scheduled_reconciliation' ) );
			remove_action( 'admin_menu', array( UniversalComposerWorkflowStore::class, 'register_recovery_page' ) );
			remove_action( 'admin_post_' . self::ADMIN_ACTION, array( UniversalComposerWorkflowStore::class, 'handle_manual_reconciliation' ) );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( self::CRON_HOOK, array( __CLASS__, 'run_scheduled_reconciliation' ) );
			add_action( 'admin_menu', array( __CLASS__, 'register_recovery_page' ) );
			add_action( 'admin_post_' . self::ADMIN_ACTION, array( __CLASS__, 'handle_manual_reconciliation' ) );
		}
	}

	/** Run one bounded scheduled batch. */
	public static function run_scheduled_reconciliation() {
		self::reconcile_and_cleanup( self::BATCH_LIMIT );
	}

	/** Register aggregate-only recovery controls. */
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

	/** Render no record identifiers, post identifiers, keys, or content. */
	public static function render_recovery_page() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$report = function_exists( 'get_option' ) ? get_option( self::LAST_REPORT_OPTION, array() ) : array();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'File 22 Workflow Recovery', 'sabri-complete-home-news-feed' ); ?></h1>
			<p><?php echo esc_html__( 'Reconcile stale workflow records without displaying content, patient data, raw keys, or native references.', 'sabri-complete-home-news-feed' ); ?></p>
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

	/** Handle explicit administrator reconciliation. */
	public static function handle_manual_reconciliation() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not authorized to run this repair.', 'sabri-complete-home-news-feed' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ADMIN_ACTION );
		self::reconcile_and_cleanup( self::BATCH_LIMIT );
		wp_safe_redirect( admin_url( 'tools.php?page=sabri-file22-workflow-recovery' ) );
		exit;
	}

	/**
	 * Apply one-way retention: completed and already-recoverable expired records
	 * are deleted, while an expired processing record receives at most one
	 * recoverable-draft interval.
	 *
	 * @return array<string,int>
	 */
	public static function reconcile_and_cleanup( int $limit = self::BATCH_LIMIT ): array {
		$report = array( 'run_at' => time(), 'scanned' => 0, 'reconciled' => 0, 'recoverable' => 0, 'deleted' => 0, 'invalid' => 0 );
		foreach ( self::option_rows( max( 1, min( self::BATCH_LIMIT, $limit ) ) ) as $row ) {
			++$report['scanned'];
			$option_key = isset( $row['option_name'] ) ? (string) $row['option_name'] : '';
			$record     = isset( $row['option_value'] ) ? maybe_unserialize( $row['option_value'] ) : null;
			if ( '' === $option_key || ! is_array( $record ) || empty( $record['fingerprint'] ) || empty( $record['key_hash'] ) || empty( $record['user_id'] ) ) {
				if ( '' !== $option_key ) {
					UniversalComposerWorkflowStore::delete_record( $option_key );
				}
				++$report['invalid'];
				continue;
			}
			if ( (int) ( $record['expires_at'] ?? 0 ) > time() ) {
				continue;
			}

			$post_id = UniversalComposerWorkflowStore::post_id_from_reference( (string) ( $record['native_reference'] ?? '' ) );
			if ( $post_id <= 0 ) {
				$post_id = (int) UniversalComposerWorkflowStore::find_native_post( (int) $record['user_id'], (string) $record['key_hash'], (string) $record['fingerprint'] );
			}
			$status = $post_id > 0 && function_exists( 'get_post_status' ) ? (string) get_post_status( $post_id ) : '';
			$state  = sanitize_key( (string) ( $record['state'] ?? '' ) );

			if ( in_array( $state, array( 'completed', 'recoverable' ), true ) ) {
				UniversalComposerWorkflowStore::remove_native_marker( $post_id, (string) $record['key_hash'], (string) $record['fingerprint'] );
				UniversalComposerWorkflowStore::delete_record( $option_key );
				++$report['deleted'];
				continue;
			}

			if ( in_array( $status, array( 'pending', 'future', 'publish', 'trash' ), true ) ) {
				if ( UniversalComposerWorkflowStore::complete_record( $option_key, $record, $post_id, UniversalComposerWorkflowStore::normalize_status( $status ) ) ) {
					++$report['reconciled'];
				}
				continue;
			}
			if ( 'draft' === $status && 'processing' === $state ) {
				$record['state']            = 'recoverable';
				$record['native_reference'] = UniversalComposerWorkflowStore::native_reference( $post_id );
				$record['updated_at']       = time();
				$record['expires_at']       = time() + self::RECOVERABLE_TTL;
				if ( function_exists( 'update_option' ) ) {
					update_option( $option_key, $record, false );
				}
				++$report['recoverable'];
				continue;
			}

			UniversalComposerWorkflowStore::remove_native_marker( $post_id, (string) $record['key_hash'], (string) $record['fingerprint'] );
			UniversalComposerWorkflowStore::delete_record( $option_key );
			++$report['deleted'];
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::LAST_REPORT_OPTION, $report, false );
		}
		return $report;
	}

	/** Fetch a bounded oldest-first batch using prepared values. */
	private static function option_rows( int $limit ): array {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'esc_like' ) ) {
			return array();
		}
		$like = $wpdb->esc_like( self::OPTION_PREFIX ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d", $like, $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
}
