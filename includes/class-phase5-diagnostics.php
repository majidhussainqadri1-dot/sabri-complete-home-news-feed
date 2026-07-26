<?php
/**
 * Final release-readiness diagnostics.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Returns privacy-minimized operator health and blockers. */
final class Phase5Diagnostics {
	public static function register() {}
	public static function report() {
		$schema = Phase5Database::verify();
		$migration = Phase5Migrations::state();
		$audit = Phase5AuditIntegrity::verify_chain();
		$performance = Phase5Performance::audit();
		$gates = Phase5FeatureSettings::get();
		$blockers = array();
		if ( $schema['missing_tables'] ) { $blockers[] = 'missing_tables'; }
		if ( $schema['missing_indexes'] ) { $blockers[] = 'missing_indexes'; }
		if ( empty( $migration['completed'] ) ) { $blockers[] = 'migration_incomplete'; }
		if ( empty( $audit['success'] ) ) { $blockers[] = 'audit_integrity'; }
		if ( empty( $performance['success'] ) ) { $blockers[] = 'performance_schema'; }
		if ( defined( 'SABRI_HNF_VERSION' ) && '1.0.1' !== SABRI_HNF_VERSION ) { $blockers[] = 'plugin_version_mismatch'; }
		if ( defined( 'SABRI_HNF_SCHEMA_VERSION' ) && '1.0.0' !== SABRI_HNF_SCHEMA_VERSION ) { $blockers[] = 'schema_version_mismatch'; }
		if ( class_exists( __NAMESPACE__ . '\\Phase4Contracts' ) && '4A' !== Phase4Contracts::CHECKPOINT ) { $blockers[] = 'checkpoint_changed'; }
		if ( array_sum( $gates ) > 0 ) { $blockers[] = 'public_gate_enabled'; }
		return array(
			'ready' => empty( $blockers ),
			'blockers' => $blockers,
			'plugin_version' => defined( 'SABRI_HNF_VERSION' ) ? SABRI_HNF_VERSION : '',
			'schema_version' => defined( 'SABRI_HNF_SCHEMA_VERSION' ) ? SABRI_HNF_SCHEMA_VERSION : '',
			'schema' => $schema,
			'migration' => $migration,
			'audit' => $audit,
			'performance' => $performance,
			'gates' => $gates,
			'breaking_active' => BreakingNewsService::active_count(),
			'submissions_pending' => Phase5Repository::count( 'submissions', array( 'status' => 'submitted' ) ),
			'generated_at_utc' => gmdate( 'Y-m-d H:i:s' ),
		);
	}
}