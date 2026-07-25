<?php
/**
 * Phase 5 migration coordinator.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Additive, idempotent, locked, resumable final-phase migrations. */
final class Phase5Migrations {
	const STATE_OPTION = 'sabri_feed_phase5_migration_state';
	const LOCK_OPTION  = 'sabri_feed_phase5_migration_lock';
	const REPORT_OPTION = 'sabri_feed_phase5_migration_report';
	const LOCK_TTL = 900;

	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_init', array( __CLASS__, 'maybe_migrate' ), 3 );
		}
	}

	public static function maybe_migrate() {
		$state = self::state();
		if ( ! empty( $state['completed'] ) && Phase5Contracts::INTERNAL_SCHEMA_TARGET === (string) $state['target'] ) {
			return $state;
		}
		return self::migrate( false );
	}

	public static function migrate( $force = false ) {
		$owner = self::lock_owner();
		if ( ! self::acquire_lock( $owner, $force ) ) {
			return array( 'success' => false, 'status' => 'locked', 'code' => 'phase5_migration_failed' );
		}
		$started = microtime( true );
		try {
			$before = Phase5Database::verify();
			$install = Phase5Database::install();
			$after = Phase5Database::verify();
			$success = ! empty( $install['success'] ) && empty( $after['missing_tables'] ) && empty( $after['missing_indexes'] );
			$state = array(
				'target' => Phase5Contracts::INTERNAL_SCHEMA_TARGET,
				'completed' => $success ? 1 : 0,
				'status' => $success ? 'verified' : 'failed',
				'last_step' => $success ? 'verify' : 'install',
				'updated_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			);
			$report = array(
				'success' => $success,
				'status' => $state['status'],
				'before' => $before,
				'install' => $install,
				'after' => $after,
				'duration_ms' => (int) round( ( microtime( true ) - $started ) * 1000 ),
				'plugin_version' => defined( 'SABRI_HNF_VERSION' ) ? SABRI_HNF_VERSION : '',
				'schema_constant' => defined( 'SABRI_HNF_SCHEMA_VERSION' ) ? SABRI_HNF_SCHEMA_VERSION : '',
				'checkpoint' => class_exists( __NAMESPACE__ . '\\Phase4Contracts' ) ? Phase4Contracts::CHECKPOINT : '',
			);
			if ( function_exists( 'update_option' ) ) {
				update_option( self::STATE_OPTION, $state, false );
				update_option( self::REPORT_OPTION, $report, false );
			}
			if ( class_exists( __NAMESPACE__ . '\\Phase5AuditIntegrity' ) ) {
				Phase5AuditIntegrity::record( 'migration_' . $state['status'], 'schema', 0, array( 'target' => $state['target'], 'duration_ms' => $report['duration_ms'] ) );
			}
			return $report;
		} finally {
			self::release_lock( $owner );
		}
	}

	public static function state() {
		$state = function_exists( 'get_option' ) ? get_option( self::STATE_OPTION, array() ) : array();
		return is_array( $state ) ? $state : array();
	}

	public static function report() {
		$report = function_exists( 'get_option' ) ? get_option( self::REPORT_OPTION, array() ) : array();
		return is_array( $report ) ? $report : array();
	}

	private static function lock_owner() {
		$entropy = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : bin2hex( random_bytes( 16 ) );
		return hash( 'sha256', $entropy . '|' . microtime( true ) );
	}

	private static function acquire_lock( $owner, $force ) {
		$now = time();
		$current = function_exists( 'get_option' ) ? get_option( self::LOCK_OPTION, array() ) : array();
		if ( is_array( $current ) && ! empty( $current['expires'] ) && (int) $current['expires'] > $now && ! $force ) {
			return false;
		}
		$lock = array( 'owner' => $owner, 'expires' => $now + self::LOCK_TTL );
		if ( function_exists( 'update_option' ) ) {
			update_option( self::LOCK_OPTION, $lock, false );
			$stored = get_option( self::LOCK_OPTION, array() );
			return is_array( $stored ) && isset( $stored['owner'] ) && hash_equals( $owner, (string) $stored['owner'] );
		}
		return true;
	}

	private static function release_lock( $owner ) {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'delete_option' ) ) {
			return;
		}
		$current = get_option( self::LOCK_OPTION, array() );
		if ( is_array( $current ) && isset( $current['owner'] ) && hash_equals( $owner, (string) $current['owner'] ) ) {
			delete_option( self::LOCK_OPTION );
		}
	}
}
