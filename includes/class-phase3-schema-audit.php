<?php
/**
 * Read-only Phase 3 schema audit.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verifies that the accepted Phase 2 social tables can support Phase 3 runtime.
 */
final class Phase3SchemaAudit {
	/**
	 * Run a non-destructive schema and contract audit.
	 *
	 * @return array<string,mixed>
	 */
	public static function audit() {
		$verification             = Database::verify_schema();
		$schema                   = Database::schema();
		$columns                  = InteractionRepository::writable_columns();
		$missing_column_contracts = array();
		$missing_unique_contracts = array();

		foreach ( $columns as $slug => $required_columns ) {
			$sql = isset( $schema[ $slug ] ) ? (string) $schema[ $slug ] : '';
			foreach ( $required_columns as $column ) {
				if ( ! preg_match( '/\n\s*' . preg_quote( $column, '/' ) . '\s+/i', $sql ) ) {
					$missing_column_contracts[] = $slug . '.' . $column;
				}
			}
		}

		foreach ( Database::expected_unique_indexes() as $slug => $indexes ) {
			$sql = isset( $schema[ $slug ] ) ? (string) $schema[ $slug ] : '';
			foreach ( $indexes as $index ) {
				if ( 'PRIMARY' === $index ) {
					$found = false !== stripos( $sql, 'PRIMARY KEY' );
				} else {
					$found = false !== stripos( $sql, 'UNIQUE KEY ' . $index );
				}
				if ( ! $found ) {
					$missing_unique_contracts[] = $slug . '.' . $index;
				}
			}
		}

		$schema_change_required = ! empty( $missing_column_contracts ) || ! empty( $missing_unique_contracts );
		$runtime_repair_required = empty( $verification['verified'] );

		return array(
			'ok'                       => ! $schema_change_required && ! $runtime_repair_required,
			'phase3_target_version'    => Phase3Contracts::TARGET_VERSION,
			'current_schema_version'   => function_exists( 'get_option' ) ? (string) get_option( Migrations::SCHEMA_OPTION_NAME, '' ) : '',
			'target_schema_version'    => SABRI_HNF_SCHEMA_VERSION,
			'schema_change_required'   => $schema_change_required,
			'runtime_repair_required'  => $runtime_repair_required,
			'missing_tables'           => $verification['missing_tables'],
			'missing_indexes'          => $verification['missing_indexes'],
			'missing_columns_contract' => $missing_column_contracts,
			'missing_unique_contract'  => $missing_unique_contracts,
			'destructive_cleanup'      => false,
			'content_preserved'        => true,
		);
	}

	/**
	 * Whether the existing accepted schema definition needs a versioned change.
	 *
	 * Missing runtime tables require repair/installation, not an automatic schema
	 * version bump. This method considers only the frozen SQL contract.
	 *
	 * @return bool
	 */
	public static function schema_change_required() {
		$audit = self::audit();
		return ! empty( $audit['schema_change_required'] );
	}
}
