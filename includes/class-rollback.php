<?php
/**
 * Rollback foundation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restores plugin-owned settings and capabilities only.
 */
final class Rollback {
	/** Data boundaries that rollback must preserve. */
	public static function preserved_boundaries() {
		return array(
			'posts',
			'pages',
			'users',
			'comments',
			'media',
			'messages',
			'appointments',
			'doctors',
			'clinics',
			'marketplace data',
			'companion-plugin data',
			'Editorial News content and metadata',
		);
	}

	/** Rollback preview. */
	public static function preview() {
		$snapshot = Snapshot::latest();
		return array(
			'available'           => ! empty( $snapshot ),
			'snapshot_created_at' => isset( $snapshot['created_at'] ) ? $snapshot['created_at'] : '',
			'will_restore'        => array( 'plugin settings', 'Phase 4 feature settings', 'schema version option', 'plugin capability assignments', 'rewrite refresh flag' ),
			'will_not_delete'     => self::preserved_boundaries(),
			'destructive'         => false,
		);
	}

	/** Execute rollback from the activation snapshot. */
	public static function execute() {
		$snapshot = Snapshot::latest();
		$report   = array(
			'available'       => ! empty( $snapshot ),
			'destructive'     => false,
			'deleted_content' => false,
			'preserved'       => self::preserved_boundaries(),
			'steps'           => array(),
		);

		if ( empty( $snapshot ) ) {
			$report['steps'][] = 'No snapshot available.';
			return $report;
		}

		if ( function_exists( 'update_option' ) && array_key_exists( 'settings', $snapshot ) ) {
			update_option( Settings::OPTION_NAME, is_array( $snapshot['settings'] ) ? $snapshot['settings'] : array(), false );
			$report['steps'][] = 'Restored plugin settings.';
		}

		if ( function_exists( 'update_option' ) && array_key_exists( 'phase4_settings', $snapshot ) ) {
			$phase4_settings = is_array( $snapshot['phase4_settings'] ) ? NewsFeatureSettings::sanitize( $snapshot['phase4_settings'] ) : NewsFeatureSettings::defaults();
			update_option( NewsFeatureSettings::OPTION_NAME, $phase4_settings, false );
			$report['steps'][] = 'Restored Phase 4 feature settings.';
		}

		if ( function_exists( 'update_option' ) && isset( $snapshot['schema_version'] ) ) {
			update_option( Migrations::SCHEMA_OPTION_NAME, $snapshot['schema_version'], false );
			$report['steps'][] = 'Restored schema version option.';
		}

		$report['capabilities']        = Capabilities::restore_from_snapshot( $snapshot );
		$report['phase4_capabilities'] = NewsCapabilities::restore_from_snapshot( $snapshot );
		$report['steps'][]             = 'Restored Phase 2, Phase 3, and Phase 4 capability assignments from snapshot.';

		if ( function_exists( 'update_option' ) ) {
			update_option( 'sabri_feed_flush_rewrite_rules', 1, false );
			$report['steps'][] = 'Scheduled rewrite refresh.';
		}

		AuditLog::record( 'rollback_execute', $report );
		return $report;
	}
}
