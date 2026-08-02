<?php
/**
 * Explicit administrator recovery for installations where File 21 was hidden.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Performs bounded writes only after an authenticated nonce-protected action. */
final class PublicSurfaceRecovery {
	const VERSION = '1.0.3';
	const VERSION_OPTION = 'sabri_hnf_public_surface_recovery_version';
	const REPORT_OPTION = 'sabri_hnf_public_surface_recovery_report';
	const NORMALIZATION_BATCH_SIZE = 200;

	/** Register only explicit administrator recovery and notices. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_post_sabri_hnf_recover_public_surface', array( __CLASS__, 'recover_from_admin' ) );
			add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		}
	}

	/** Read-only compatibility method retained for diagnostics. */
	public static function maybe_recover() {
		return self::report(
			false,
			'explicit_admin_action_required',
			array(
				'diagnostics'           => CorrectivePublicMount::diagnostics(),
				'recovery_complete'     => false,
				'news_gates_changed'    => false,
				'publication_changed'   => false,
				'legacy_migration_run'  => false,
				'public_request_writes' => false,
			)
		);
	}

	/** Administrator-requested recovery. */
	public static function recover_from_admin() {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user_id <= 0 || ! CanonicalIdentityAdapter::current_action_ready( $user_id ) || ! function_exists( 'current_user_can' ) || ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'sabri_feed_manage_settings' ) ) ) {
			if ( function_exists( 'wp_die' ) ) { wp_die( esc_html__( 'You do not have permission to recover File 21 public surfaces.', 'sabri-complete-home-news-feed' ) ); }
			return;
		}
		if ( function_exists( 'check_admin_referer' ) ) { check_admin_referer( 'sabri_hnf_recover_public_surface' ); }

		$before                 = CorrectivePublicSettings::stored();
		$diagnostics            = CorrectivePublicMount::diagnostics();
		$after                  = CorrectivePublicSettings::patch( self::safe_read_surface_patch( $diagnostics ) );
		$normalized             = self::normalize_published_privileged_posts();
		$normalization_complete = ! empty( $normalized['complete'] );
		$report                 = self::report(
			true,
			$normalization_complete ? 'administrator_recovery_completed' : 'administrator_recovery_continues',
			array(
				'previous'              => $before,
				'current'               => $after,
				'diagnostics'           => $diagnostics,
				'normalized_posts'      => $normalized,
				'recovery_complete'     => $normalization_complete,
				'news_gates_changed'    => false,
				'publication_changed'   => false,
				'legacy_migration_run'  => false,
				'public_request_writes' => false,
			)
		);
		if ( function_exists( 'update_option' ) ) {
			if ( $normalization_complete ) { update_option( self::VERSION_OPTION, self::VERSION, false ); }
			update_option( self::REPORT_OPTION, $report, false );
		}
		if ( class_exists( __NAMESPACE__ . '\\FeedQuery' ) ) { FeedQuery::invalidate_cache(); }
		if ( class_exists( __NAMESPACE__ . '\\AuditLog' ) ) { AuditLog::record( 'public_surface_visibility_recovery_manual', $report ); }

		$url = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=' . CorrectiveAdmin::PAGE_SLUG ) : '';
		if ( function_exists( 'add_query_arg' ) ) {
			$url = add_query_arg( array( 'public_surface_recovered' => 1, 'recovery_complete' => $normalization_complete ? 1 : 0 ), $url );
		}
		if ( function_exists( 'wp_safe_redirect' ) && '' !== $url ) { wp_safe_redirect( $url ); exit; }
	}

	/** Show an administrator notice while explicit normalization is pending. */
	public static function admin_notice() {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user_id <= 0 || ! CanonicalIdentityAdapter::current_action_ready( $user_id ) || ! function_exists( 'current_user_can' ) || ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'sabri_feed_manage_settings' ) ) ) { return; }
		$completed_version = function_exists( 'get_option' ) ? (string) get_option( self::VERSION_OPTION, '' ) : '';
		if ( '' !== $completed_version && version_compare( $completed_version, self::VERSION, '>=' ) ) { return; }
		$url = function_exists( 'admin_url' ) ? admin_url( 'admin-post.php?action=sabri_hnf_recover_public_surface' ) : '';
		if ( function_exists( 'wp_nonce_url' ) && '' !== $url ) { $url = wp_nonce_url( $url, 'sabri_hnf_recover_public_surface' ); }
		$message = __( 'File 21 read-only surfaces can render without database writes. Run the explicit recovery to normalize only already-published Founder, Administrator, and trusted-publisher posts with missing File 21 metadata.', 'sabri-complete-home-news-feed' );
		echo '<div class="notice notice-warning"><p>' . esc_html( $message );
		if ( '' !== $url ) { echo ' <a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Run Explicit File 21 Recovery', 'sabri-complete-home-news-feed' ) . '</a>'; }
		echo '</p></div>';
	}

	/** Safe patch: read-only visibility on; Editorial News/write/migration gates untouched. */
	private static function safe_read_surface_patch( array $diagnostics ) {
		return array(
			'home_surface_enabled'          => 1,
			'profile_timeline_enabled'      => 1,
			'distinct_surface_marker'       => 1,
			'duplicate_feed_guard'          => 1,
			'replace_existing_feed_surface' => ! empty( $diagnostics['feed_conflict'] ) ? 1 : CorrectivePublicSettings::enabled( 'replace_existing_feed_surface' ),
			'duplicate_navigation_guard'    => 1,
			'read_only_surface_recovered'   => 1,
		);
	}

	/** Normalize one bounded batch of already-published privileged posts. */
	private static function normalize_published_privileged_posts() {
		$result = array( 'scanned' => 0, 'updated' => array(), 'bounded' => true, 'batch_size' => self::NORMALIZATION_BATCH_SIZE, 'more_possible' => false, 'complete' => false );
		if ( ! class_exists( 'WP_Query' ) || ! function_exists( 'update_post_meta' ) ) { return $result; }

		$author_ids = class_exists( __NAMESPACE__ . '\\LegacyFounderPostMigration' ) ? LegacyFounderPostMigration::privileged_author_ids() : array();
		if ( class_exists( __NAMESPACE__ . '\\CanonicalIdentityAdapter' ) ) {
			foreach ( CanonicalIdentityAdapter::verified_doctor_ids( 500 ) as $doctor_id ) {
				if ( CanonicalIdentityAdapter::can_publish_immediately( $doctor_id ) ) { $author_ids[] = $doctor_id; }
			}
		}
		$author_ids = array_values( array_unique( array_filter( array_map( 'absint', is_array( $author_ids ) ? $author_ids : array() ) ) ) );
		if ( empty( $author_ids ) ) { $result['complete'] = true; return $result; }

		$query = new \WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'author__in'             => $author_ids,
				'posts_per_page'         => self::NORMALIZATION_BATCH_SIZE,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'has_password'           => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					'relation' => 'OR',
					array( 'key' => PostMetadata::META_REVIEW_STATE, 'compare' => 'NOT EXISTS' ),
					array( 'key' => PostMetadata::META_REVIEW_STATE, 'value' => '', 'compare' => '=' ),
				),
			)
		);
		$post_ids = is_array( $query->posts ) ? array_map( 'absint', $query->posts ) : array();
		$result['scanned']       = count( $post_ids );
		$result['more_possible'] = self::NORMALIZATION_BATCH_SIZE === count( $post_ids );
		foreach ( $post_ids as $post_id ) {
			if ( $post_id <= 0 || 'publish' !== get_post_status( $post_id ) ) { continue; }
			update_post_meta( $post_id, PostMetadata::META_REVIEW_STATE, 'approved' );
			$has_visibility = function_exists( 'metadata_exists' ) ? metadata_exists( 'post', $post_id, PostMetadata::META_VISIBILITY ) : '' !== (string) get_post_meta( $post_id, PostMetadata::META_VISIBILITY, true );
			$has_type       = function_exists( 'metadata_exists' ) ? metadata_exists( 'post', $post_id, PostMetadata::META_TYPE ) : '' !== (string) get_post_meta( $post_id, PostMetadata::META_TYPE, true );
			if ( ! $has_visibility ) { update_post_meta( $post_id, PostMetadata::META_VISIBILITY, 'public' ); }
			if ( ! $has_type ) { update_post_meta( $post_id, PostMetadata::META_TYPE, 'standard-post' ); }
			$result['updated'][] = $post_id;
		}
		$result['complete'] = ! $result['more_possible'];
		return $result;
	}

	/** Build a privacy-safe report. */
	private static function report( $changed, $reason, array $context ) {
		return array(
			'version'       => self::VERSION,
			'changed'       => (bool) $changed,
			'reason'        => function_exists( 'sanitize_key' ) ? sanitize_key( $reason ) : preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $reason ) ),
			'context'       => $context,
			'completed_utc' => gmdate( 'Y-m-d H:i:s' ),
		);
	}
}
