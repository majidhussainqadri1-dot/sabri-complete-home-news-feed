<?php
/**
 * Safe public-surface recovery for installations where File 21 remained invisible.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activates only read-only public surfaces while leaving publication, News, and
 * legacy migrations fail-closed. The recovery runs once per release and never
 * overrides an administrator-completed wizard decision.
 */
final class PublicSurfaceRecovery {
	const VERSION = '1.0.2';
	const VERSION_OPTION = 'sabri_hnf_public_surface_recovery_version';
	const REPORT_OPTION = 'sabri_hnf_public_surface_recovery_report';

	/** Register the one-time migration and an explicit administrator recovery action. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', array( __CLASS__, 'maybe_recover' ), 1 );
			add_action( 'admin_post_sabri_hnf_recover_public_surface', array( __CLASS__, 'recover_from_admin' ) );
			add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
		}
	}

	/** Run a bounded, non-destructive recovery once for this patch release. */
	public static function maybe_recover() {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return self::report( false, 'wordpress_options_unavailable', array() );
		}

		$completed_version = (string) get_option( self::VERSION_OPTION, '' );
		if ( '' !== $completed_version && version_compare( $completed_version, self::VERSION, '>=' ) ) {
			$stored_report = get_option( self::REPORT_OPTION, array() );
			return is_array( $stored_report ) ? $stored_report : self::report( false, 'already_completed', array() );
		}

		$raw_components = get_option( CorrectivePublicSettings::OPTION_NAME, null );
		$current        = CorrectivePublicSettings::get();
		$wizard_done    = ! empty( $current['wizard_completed'] );
		$explicit       = is_array( $raw_components ) && $wizard_done;
		$diagnostics    = CorrectivePublicMount::diagnostics();
		$patch          = array();
		$normalized     = array();

		/* A completed wizard is an explicit administrator decision. */
		if ( ! $explicit ) {
			$patch      = self::safe_read_surface_patch( $diagnostics );
			$updated    = CorrectivePublicSettings::patch( $patch );
			$normalized = self::normalize_published_privileged_posts();
			$changed    = $updated !== $current || ! empty( $normalized['updated'] );
			$reason     = $changed ? 'safe_read_surfaces_recovered' : 'safe_read_surfaces_already_ready';
		} else {
			$updated = $current;
			$changed = false;
			$reason  = 'administrator_wizard_decision_preserved';
		}

		$report = self::report(
			$changed,
			$reason,
			array(
				'previous'             => $current,
				'current'              => $updated,
				'diagnostics'          => $diagnostics,
				'normalized_posts'     => $normalized,
				'news_gates_changed'   => false,
				'publication_changed'  => false,
				'legacy_migration_run' => false,
			)
		);

		update_option( self::VERSION_OPTION, self::VERSION, false );
		update_option( self::REPORT_OPTION, $report, false );
		if ( $changed && class_exists( __NAMESPACE__ . '\\FeedQuery' ) ) {
			FeedQuery::invalidate_cache();
		}
		if ( class_exists( __NAMESPACE__ . '\\AuditLog' ) ) {
			AuditLog::record( 'public_surface_visibility_recovery', $report );
		}
		return $report;
	}

	/** Administrator-requested recovery. */
	public static function recover_from_admin() {
		if ( ! function_exists( 'current_user_can' ) || ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'sabri_feed_manage_settings' ) ) ) {
			if ( function_exists( 'wp_die' ) ) {
				wp_die( esc_html__( 'You do not have permission to recover File 21 public surfaces.', 'sabri-complete-home-news-feed' ) );
			}
			return;
		}
		if ( function_exists( 'check_admin_referer' ) ) {
			check_admin_referer( 'sabri_hnf_recover_public_surface' );
		}

		$before      = CorrectivePublicSettings::get();
		$diagnostics = CorrectivePublicMount::diagnostics();
		$after       = CorrectivePublicSettings::patch( self::safe_read_surface_patch( $diagnostics ) );
		$normalized  = self::normalize_published_privileged_posts();
		$report      = self::report(
			true,
			'administrator_recovery_completed',
			array(
				'previous'             => $before,
				'current'              => $after,
				'diagnostics'          => $diagnostics,
				'normalized_posts'     => $normalized,
				'news_gates_changed'   => false,
				'publication_changed'  => false,
				'legacy_migration_run' => false,
			)
		);
		if ( function_exists( 'update_option' ) ) {
			update_option( self::VERSION_OPTION, self::VERSION, false );
			update_option( self::REPORT_OPTION, $report, false );
		}
		if ( class_exists( __NAMESPACE__ . '\\FeedQuery' ) ) {
			FeedQuery::invalidate_cache();
		}
		if ( class_exists( __NAMESPACE__ . '\\AuditLog' ) ) {
			AuditLog::record( 'public_surface_visibility_recovery_manual', $report );
		}

		$url = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=' . CorrectiveAdmin::PAGE_SLUG ) : '';
		if ( function_exists( 'add_query_arg' ) ) {
			$url = add_query_arg( array( 'public_surface_recovered' => 1 ), $url );
		}
		if ( function_exists( 'wp_safe_redirect' ) && '' !== $url ) {
			wp_safe_redirect( $url );
			exit;
		}
	}

	/** Show a precise notice only while the public read surface is unavailable. */
	public static function admin_notice() {
		if ( ! function_exists( 'current_user_can' ) || ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'sabri_feed_manage_settings' ) ) ) {
			return;
		}
		$diagnostics = CorrectivePublicMount::diagnostics();
		if ( ! empty( $diagnostics['effective_home_surface'] ) ) {
			return;
		}
		$url = function_exists( 'admin_url' ) ? admin_url( 'admin-post.php?action=sabri_hnf_recover_public_surface' ) : '';
		if ( function_exists( 'wp_nonce_url' ) && '' !== $url ) {
			$url = wp_nonce_url( $url, 'sabri_hnf_recover_public_surface' );
		}
		$message = __( 'File 21 is active, but its public Home surface is not currently observable. Recover the read-only Home and Profile Timeline without enabling News gates, automatic publication, or legacy migration.', 'sabri-complete-home-news-feed' );
		echo '<div class="notice notice-error"><p>' . esc_html( $message );
		if ( '' !== $url ) {
			echo ' <a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Recover File 21 Public Surface', 'sabri-complete-home-news-feed' ) . '</a>';
		}
		echo '</p></div>';
	}

	/** Safe patch: read-only visibility on; editorial/write/migration gates untouched. */
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

	/** Normalize only already-published privileged posts with blank File 21 metadata. */
	private static function normalize_published_privileged_posts() {
		$result = array( 'scanned' => 0, 'updated' => array(), 'bounded' => true );
		if ( ! class_exists( 'WP_Query' ) || ! function_exists( 'update_post_meta' ) ) {
			return $result;
		}

		$author_ids = class_exists( __NAMESPACE__ . '\\LegacyFounderPostMigration' ) ? LegacyFounderPostMigration::privileged_author_ids() : array();
		if ( class_exists( __NAMESPACE__ . '\\CanonicalIdentityAdapter' ) ) {
			foreach ( CanonicalIdentityAdapter::verified_doctor_ids( 500 ) as $doctor_id ) {
				if ( CanonicalIdentityAdapter::can_publish_immediately( $doctor_id ) ) {
					$author_ids[] = $doctor_id;
				}
			}
		}
		$author_ids = array_values( array_unique( array_filter( array_map( 'absint', is_array( $author_ids ) ? $author_ids : array() ) ) ) );
		if ( empty( $author_ids ) ) {
			return $result;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'author__in'             => $author_ids,
				'posts_per_page'         => 200,
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
		$result['scanned'] = count( $post_ids );
		foreach ( $post_ids as $post_id ) {
			if ( $post_id <= 0 || 'publish' !== get_post_status( $post_id ) ) {
				continue;
			}
			update_post_meta( $post_id, PostMetadata::META_REVIEW_STATE, 'approved' );
			$has_visibility = function_exists( 'metadata_exists' ) ? metadata_exists( 'post', $post_id, PostMetadata::META_VISIBILITY ) : '' !== (string) get_post_meta( $post_id, PostMetadata::META_VISIBILITY, true );
			$has_type       = function_exists( 'metadata_exists' ) ? metadata_exists( 'post', $post_id, PostMetadata::META_TYPE ) : '' !== (string) get_post_meta( $post_id, PostMetadata::META_TYPE, true );
			if ( ! $has_visibility ) {
				update_post_meta( $post_id, PostMetadata::META_VISIBILITY, 'public' );
			}
			if ( ! $has_type ) {
				update_post_meta( $post_id, PostMetadata::META_TYPE, 'standard-post' );
			}
			$result['updated'][] = $post_id;
		}
		return $result;
	}

	/** Build a privacy-safe report. */
	private static function report( $changed, $reason, array $context ) {
		return array(
			'version'       => self::VERSION,
			'changed'       => (bool) $changed,
			'reason'        => sanitize_key( $reason ),
			'context'       => $context,
			'completed_utc' => gmdate( 'Y-m-d H:i:s' ),
		);
	}
}
