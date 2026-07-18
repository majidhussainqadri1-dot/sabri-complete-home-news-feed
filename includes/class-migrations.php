<?php
/**
 * Schema migration foundation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides idempotent migration preview and execution.
 */
final class Migrations {
	const SCHEMA_OPTION_NAME = 'sabri_feed_schema_version';

	/**
	 * Current stored schema version.
	 *
	 * @return string
	 */
	public static function current_version() {
		if ( function_exists( 'get_option' ) ) {
			return (string) get_option( self::SCHEMA_OPTION_NAME, '' );
		}

		return '';
	}

	/**
	 * Preview migration actions.
	 *
	 * @return array<string,mixed>
	 */
	public static function preview() {
		$current = self::current_version();

		return array(
			'current_version' => $current,
			'target_version'  => SABRI_HNF_SCHEMA_VERSION,
			'action'          => SABRI_HNF_SCHEMA_VERSION === $current ? 'none' : 'install_or_upgrade',
			'tables'          => Database::table_status(),
			'indexes'         => Database::expected_indexes(),
			'destructive'     => false,
		);
	}

	/**
	 * Execute migration.
	 *
	 * @param bool $capture_snapshot Whether to capture a pre-migration snapshot.
	 * @return array<string,mixed>
	 */
	public static function migrate( $capture_snapshot = true ) {
		if ( $capture_snapshot ) {
			Snapshot::capture_before_mutation( 'migration' );
		}

		$report = Database::install();

		return array(
			'preview' => self::preview(),
			'result'  => $report,
		);
	}
}
