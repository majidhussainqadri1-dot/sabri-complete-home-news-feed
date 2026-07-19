<?php
/**
 * Phase 3H release-readiness gate.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Prevents automated or accidental Phase 3 release promotion. */
final class ReleaseReadiness {
	const ACCEPTANCE_OPTION = 'sabri_hnf_phase3_staging_acceptance';

	/** Register read-only system-check integration. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'sabri_shell_system_check_report', array( __CLASS__, 'append_system_check' ) );
		}
	}

	/** Immutable acceptance checklist keys. */
	public static function checklist_items() {
		return array(
			'clean_install',
			'upgrade_install',
			'rollback_restore',
			'phase2_regression',
			'role_access_matrix',
			'followers_visibility',
			'reactions_saves',
			'comments_replies',
			'follows',
			'reports_moderation',
			'polls',
			'notification_bridge',
			'view_logging',
			'keyboard_navigation',
			'screen_reader_labels',
			'mobile_responsive',
			'privacy_export_erasure',
			'cache_invalidation',
			'safe_mode_emergency_disable',
			'backup_verified',
		);
	}

	/** Stable hash for the exact acceptance checklist contract. */
	public static function checklist_hash() {
		return hash( 'sha256', wp_json_encode( array( Phase3Contracts::TARGET_VERSION, self::checklist_items() ) ) );
	}

	/** Return a read-only readiness report. */
	public static function report() {
		$schema      = Phase3SchemaAudit::audit();
		$acceptance  = self::acceptance_record();
		$blocked     = array();
		$code_ready  = ! empty( $schema['ok'] ) && self::required_classes_available();

		if ( ! $code_ready ) {
			$blocked[] = 'code_or_schema_audit_failed';
		}
		if ( empty( $acceptance['valid'] ) ) {
			$blocked[] = 'staging_acceptance_missing_or_invalid';
		}
		if ( SABRI_HNF_VERSION !== Phase3Contracts::TARGET_VERSION ) {
			$blocked[] = 'plugin_version_not_promoted';
		}
		if ( SafeMode::public_features_disabled() ) {
			$blocked[] = 'safe_mode_or_emergency_disable_active';
		}

		return array(
			'code_ready_for_staging' => $code_ready,
			'release_ready'          => empty( $blocked ),
			'target_version'         => Phase3Contracts::TARGET_VERSION,
			'plugin_version'         => SABRI_HNF_VERSION,
			'schema_version'         => SABRI_HNF_SCHEMA_VERSION,
			'checklist_hash'         => self::checklist_hash(),
			'acceptance'             => $acceptance,
			'blocked_reasons'        => array_values( array_unique( $blocked ) ),
			'schema'                 => $schema,
			'automatic_promotion'     => false,
			'automatic_merge'         => false,
			'automatic_deployment'    => false,
		);
	}

	/** Append a concise Shell system-check row. */
	public static function append_system_check( $rows ) {
		if ( ! is_array( $rows ) ) {
			return $rows;
		}
		$report = self::report();
		$rows[] = array(
			'label'  => __( 'Phase 3 release readiness', 'sabri-complete-home-news-feed' ),
			'status' => ! empty( $report['release_ready'] ) ? 'Ready' : ( ! empty( $report['code_ready_for_staging'] ) ? 'Staging Required' : 'Blocked' ),
			'detail' => ! empty( $report['release_ready'] )
				? __( 'The tested staging acceptance record matches the frozen checklist.', 'sabri-complete-home-news-feed' )
				: __( 'Release promotion is blocked until the frozen staging checklist, rollback verification, and explicit acceptance are complete.', 'sabri-complete-home-news-feed' ),
		);
		return $rows;
	}

	/** Parse and validate the manually recorded staging acceptance. */
	private static function acceptance_record() {
		$record = function_exists( 'get_option' ) ? get_option( self::ACCEPTANCE_OPTION, array() ) : array();
		$record = is_array( $record ) ? $record : array();
		$accepted_at = isset( $record['accepted_at'] ) ? trim( (string) $record['accepted_at'] ) : '';
		$tested_sha  = isset( $record['tested_head_sha'] ) ? strtolower( trim( (string) $record['tested_head_sha'] ) ) : '';
		$hash        = isset( $record['checklist_hash'] ) ? strtolower( trim( (string) $record['checklist_hash'] ) ) : '';
		$completed   = isset( $record['completed_items'] ) && is_array( $record['completed_items'] ) ? array_values( array_unique( array_map( 'sanitize_key', $record['completed_items'] ) ) ) : array();
		$required    = self::checklist_items();
		$valid_date  = '' !== $accepted_at && false !== strtotime( $accepted_at . ' UTC' );
		$all_items   = empty( array_diff( $required, $completed ) );
		$valid       = ! empty( $record['accepted'] )
			&& ! empty( $record['accepted_by'] )
			&& $valid_date
			&& preg_match( '/^[a-f0-9]{40}$/', $tested_sha )
			&& hash_equals( self::checklist_hash(), $hash )
			&& $all_items
			&& ! empty( $record['rollback_verified'] )
			&& ! empty( $record['backup_verified'] );

		return array(
			'valid'             => (bool) $valid,
			'accepted'          => ! empty( $record['accepted'] ),
			'accepted_by'       => isset( $record['accepted_by'] ) ? absint( $record['accepted_by'] ) : 0,
			'accepted_at'       => $valid_date ? $accepted_at : '',
			'tested_head_sha'   => preg_match( '/^[a-f0-9]{40}$/', $tested_sha ) ? $tested_sha : '',
			'checklist_matches' => 64 === strlen( $hash ) && hash_equals( self::checklist_hash(), $hash ),
			'completed_count'   => count( array_intersect( $required, $completed ) ),
			'required_count'    => count( $required ),
			'rollback_verified' => ! empty( $record['rollback_verified'] ),
			'backup_verified'   => ! empty( $record['backup_verified'] ),
		);
	}

	/** Required runtime classes for the completed Phase 3 surface. */
	private static function required_classes_available() {
		$classes = array(
			ReactionService::class,
			SaveService::class,
			CommentService::class,
			FollowService::class,
			FollowersVisibility::class,
			ReportService::class,
			PollService::class,
			NotificationBridge::class,
			ViewService::class,
		);
		foreach ( $classes as $class ) {
			if ( ! class_exists( $class ) ) {
				return false;
			}
		}
		return true;
	}
}
