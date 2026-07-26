<?php
/**
 * File 21 harmonization diagnostics and release-readiness contract.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Provides truthful operator diagnostics without claiming live acceptance. */
final class HarmonizationDiagnostics {
	/** Register shared System Check and readiness filters. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'sabri_shell_system_check_report', array( __CLASS__, 'append_rows' ), 30 );
			add_filter( 'sabri_hnf_harmonization_readiness', array( __CLASS__, 'filter_readiness' ) );
		}
	}

	/** Read-only System Check rows for the comprehensive completion line. */
	public static function rows() {
		$version_ok = defined( 'SABRI_HNF_VERSION' ) && defined( 'SABRI_HNF_SCHEMA_VERSION' )
			&& '1.0.1' === SABRI_HNF_VERSION
			&& '1.0.0' === SABRI_HNF_SCHEMA_VERSION;
		$bootstrap_ok = function_exists( 'sabri_hnf_php_supported' )
			&& function_exists( 'sabri_hnf_wp_supported' )
			&& function_exists( 'sabri_hnf_activate' )
			&& function_exists( 'sabri_hnf_deactivate' )
			&& function_exists( 'sabri_hnf_bootstrap' );
		$home_ok = class_exists( __NAMESPACE__ . '\\HomeCompositionRegistry' )
			&& class_exists( __NAMESPACE__ . '\\CompanionHomeRowAdapters' )
			&& defined( 'SABRI_HNF_PATH' )
			&& is_readable( SABRI_HNF_PATH . 'assets/css/home-composition.css' );
		$search_ok = class_exists( __NAMESPACE__ . '\\SearchProviderRegistry' )
			&& count( SearchProviderRegistry::providers() ) >= 2;
		$migration_ok = class_exists( __NAMESPACE__ . '\\LegacyPublicationMigration' )
			&& class_exists( __NAMESPACE__ . '\\LegacyPublicationRollback' )
			&& class_exists( __NAMESPACE__ . '\\LegacyInteractionMigrationAdapter' );
		$providers = $migration_ok ? LegacyInteractionMigrationAdapter::providers() : array();
		$shell = class_exists( __NAMESPACE__ . '\\CompanionIntegrationRegistry' ) ? CompanionIntegrationRegistry::service( 'shell' ) : array( 'status' => 'Missing' );
		$required_slots = class_exists( __NAMESPACE__ . '\\Integrations' ) ? array_keys( Integrations::required_shell_slots() ) : array();
		$available_slots = self::available_shell_slots();
		$missing_slots = array_values( array_diff( $required_slots, $available_slots ) );
		$shell_status = isset( $shell['status'] ) ? (string) $shell['status'] : 'Missing';
		$slot_status = empty( $missing_slots )
			? 'Connected'
			: ( 'Missing' === $shell_status ? 'External dependency missing' : 'Available but not configured' );

		return array(
			self::row( 'File 21 release identity', $version_ok ? 'Connected' : 'Blocked', $version_ok ? 'Plugin 1.0.1; schema 1.0.0.' : 'Expected plugin 1.0.1 and schema 1.0.0.' ),
			self::row( 'Plugin bootstrap completeness', $bootstrap_ok ? 'Connected' : 'Blocked', $bootstrap_ok ? 'Compatibility guards, activation, deactivation and runtime bootstrap are loaded.' : 'One or more bootstrap boundaries are unavailable.' ),
			self::row( 'Home composition runtime', $home_ok ? 'Connected' : 'Missing', $home_ok ? 'Control bar, cross-module rows and responsive stylesheet are present.' : 'Home composition class or stylesheet is missing.' ),
			self::row( 'Global Search providers', $search_ok ? 'Connected' : 'Missing', $search_ok ? 'Authorized Posts and approved Editorial News providers are registered.' : 'Expected File 21 Search providers are unavailable.' ),
			self::row( 'File 04 migration and rollback', $migration_ok ? 'Connected' : 'Missing', $migration_ok ? 'Selected publication migration, provenance mapping, interaction adapter and non-destructive rollback are present.' : 'One or more migration runtime classes are missing.' ),
			self::row( 'File 04 interaction schema provider', $providers ? 'Connected' : 'Available but not configured', $providers ? implode( ', ', array_keys( $providers ) ) : 'No verified source-schema provider is connected; interaction counts remain explicitly unavailable rather than inferred.' ),
			self::row( 'File 20 rendering slots', $slot_status, empty( $missing_slots ) ? 'All required Home/News slots are advertised by the Unified Shell.' : 'Missing advertised slots: ' . implode( ', ', $missing_slots ) ),
		);
	}

	/** Machine-readable readiness report. */
	public static function readiness() {
		$rows = self::rows();
		$blocking = array();
		$external = array();
		foreach ( $rows as $row ) {
			if ( in_array( $row['status'], array( 'Blocked', 'Missing' ), true ) ) {
				$blocking[] = sanitize_key( $row['label'] );
			} elseif ( in_array( $row['status'], array( 'External dependency missing', 'Available but not configured' ), true ) ) {
				$external[] = sanitize_key( $row['label'] );
			}
		}
		return array(
			'code_ready_for_exact_head_qa' => empty( $blocking ),
			'live_release_ready' => false,
			'blocking_checks' => array_values( array_unique( $blocking ) ),
			'external_acceptance_checks' => array_values( array_unique( $external ) ),
			'rows' => $rows,
			'automatic_merge' => false,
			'automatic_deployment' => false,
			'generated_at_utc' => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	/** Append rows to a shared Shell report. */
	public static function append_rows( $rows ) {
		$rows = is_array( $rows ) ? $rows : array();
		return array_merge( $rows, self::rows() );
	}

	/** Shared readiness filter. */
	public static function filter_readiness( $report ) {
		$report = is_array( $report ) ? $report : array();
		return array_merge( $report, self::readiness() );
	}

	/** Accepted File 20 slot advertisement contract. */
	private static function available_shell_slots() {
		$slots = function_exists( 'apply_filters' ) ? apply_filters( 'sabri_shell_rendering_slots', array() ) : array();
		if ( ! is_array( $slots ) ) {
			return array();
		}
		if ( array_is_list( $slots ) ) {
			return array_values( array_unique( array_filter( array_map( 'sanitize_key', $slots ) ) ) );
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', array_keys( $slots ) ) ) ) );
	}

	/** Stable report row. */
	private static function row( $label, $status, $detail ) {
		return array(
			'label' => (string) $label,
			'status' => (string) $status,
			'detail' => (string) $detail,
		);
	}
}
