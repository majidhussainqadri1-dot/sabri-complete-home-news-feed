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

/** Restores plugin-owned settings, option identities, and capabilities only. */
final class Rollback {
	/** Data boundaries that rollback must preserve. */
	public static function preserved_boundaries() {
		return array(
			'posts', 'pages', 'users', 'comments', 'media', 'messages', 'appointments',
			'doctors', 'clinics', 'marketplace data', 'companion-plugin data',
			'Editorial News content and metadata',
		);
	}

	/** Rollback preview. */
	public static function preview() {
		$snapshot = Snapshot::latest();
		return array(
			'available'           => ! empty( $snapshot ),
			'snapshot_created_at' => isset( $snapshot['created_at'] ) ? $snapshot['created_at'] : '',
			'will_restore'        => array(
				'plugin settings', 'Phase 4 feature settings and option existence', 'schema version option',
				'Phase 4 contract and term version options', 'Phase 4 capability mutation record',
				'plugin capability assignments', 'rewrite refresh flag',
			),
			'will_not_delete'     => self::preserved_boundaries(),
			'destructive'         => false,
		);
	}

	/** Execute rollback from the immutable activation snapshot. */
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

		self::restore_option(
			NewsFeatureSettings::OPTION_NAME,
			'phase4_settings',
			isset( $snapshot['phase4_settings'] ) && is_array( $snapshot['phase4_settings'] ) ? NewsFeatureSettings::sanitize( $snapshot['phase4_settings'] ) : NewsFeatureSettings::defaults(),
			$snapshot,
			$report,
			'Phase 4 feature settings'
		);

		if ( function_exists( 'update_option' ) && array_key_exists( 'schema_version', $snapshot ) ) {
			update_option( Migrations::SCHEMA_OPTION_NAME, $snapshot['schema_version'], false );
			$report['steps'][] = 'Restored schema version option.';
		}

		self::restore_option(
			'sabri_feed_phase4_contract_version',
			'phase4_contract_version',
			isset( $snapshot['phase4_contract_version'] ) ? (string) $snapshot['phase4_contract_version'] : '',
			$snapshot,
			$report,
			'Phase 4 contract version option'
		);

		self::restore_option(
			NewsTaxonomies::TERM_VERSION_OPTION,
			'phase4_terms_version',
			isset( $snapshot['phase4_terms_version'] ) ? (string) $snapshot['phase4_terms_version'] : '',
			$snapshot,
			$report,
			'Phase 4 terms version option'
		);

		self::restore_option(
			NewsCapabilities::MUTATION_OPTION,
			'phase4_capability_mutations',
			isset( $snapshot['phase4_capability_mutations'] ) && is_array( $snapshot['phase4_capability_mutations'] ) ? $snapshot['phase4_capability_mutations'] : array(),
			$snapshot,
			$report,
			'Phase 4 capability mutation record'
		);

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

	/** Restore an option value or delete it when it did not exist at baseline. */
	private static function restore_option( $option_name, $snapshot_key, $value, array $snapshot, array &$report, $label ) {
		$exists_map = isset( $snapshot['option_exists'] ) && is_array( $snapshot['option_exists'] ) ? $snapshot['option_exists'] : array();
		$has_exact_existence = array_key_exists( $snapshot_key, $exists_map );
		$should_exist = $has_exact_existence ? ! empty( $exists_map[ $snapshot_key ] ) : array_key_exists( $snapshot_key, $snapshot );

		if ( ! $should_exist && function_exists( 'delete_option' ) ) {
			delete_option( $option_name );
			$report['steps'][] = 'Removed ' . $label . ' because it did not exist at baseline.';
			return;
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( $option_name, $value, false );
			$report['steps'][] = 'Restored ' . $label . '.';
		}
	}
}
