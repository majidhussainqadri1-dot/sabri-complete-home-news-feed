<?php
/**
 * Database schema foundation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines and installs versioned social data tables.
 */
final class Database {
	const INSTALL_RESULT_OPTION = 'sabri_feed_last_schema_install_result';

	/**
	 * Table slugs.
	 *
	 * @return array<int,string>
	 */
	public static function table_slugs() {
		return array(
			'reactions',
			'follows',
			'saves',
			'reports',
			'views',
			'poll_votes',
			'audit_log',
		);
	}

	/**
	 * Full table names with dynamic WordPress prefix.
	 *
	 * @param string|null $prefix Optional prefix.
	 * @return array<string,string>
	 */
	public static function table_names( $prefix = null ) {
		global $wpdb;

		if ( null === $prefix ) {
			$prefix = ( isset( $wpdb ) && ! empty( $wpdb->prefix ) ) ? $wpdb->prefix : 'wp_';
		}

		return array(
			'reactions'  => $prefix . 'sabri_feed_reactions',
			'follows'    => $prefix . 'sabri_feed_follows',
			'saves'      => $prefix . 'sabri_feed_saves',
			'reports'    => $prefix . 'sabri_feed_reports',
			'views'      => $prefix . 'sabri_feed_views',
			'poll_votes' => $prefix . 'sabri_feed_poll_votes',
			'audit_log'  => $prefix . 'sabri_feed_audit_log',
		);
	}

	/**
	 * Allowed row statuses by table.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function allowed_statuses() {
		return array(
			'reactions'  => array( 'active', 'removed' ),
			'follows'    => array( 'active', 'blocked', 'removed' ),
			'saves'      => array( 'active', 'removed' ),
			'reports'    => array( 'open', 'triaged', 'resolved', 'dismissed', 'duplicate' ),
			'views'      => array( 'counted', 'ignored' ),
			'poll_votes' => array( 'active', 'replaced', 'removed' ),
			'audit_log'  => array( 'recorded' ),
		);
	}

	/**
	 * Expected index names by table.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function expected_indexes() {
		return array(
			'reactions'  => array( 'PRIMARY', 'user_post_status', 'post_status', 'user_status' ),
			'follows'    => array( 'PRIMARY', 'follower_target', 'target_status', 'follower_status' ),
			'saves'      => array( 'PRIMARY', 'user_post_collection', 'post_status', 'user_status' ),
			'reports'    => array( 'PRIMARY', 'duplicate_control', 'object_status', 'reporter_status' ),
			'views'      => array( 'PRIMARY', 'view_identity', 'post_date', 'user_date' ),
			'poll_votes' => array( 'PRIMARY', 'vote_identity', 'poll_status', 'user_status' ),
			'audit_log'  => array( 'PRIMARY', 'action_created', 'actor_created', 'object_lookup' ),
		);
	}

	/**
	 * Expected unique index names by table.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function expected_unique_indexes() {
		return array(
			'reactions'  => array( 'PRIMARY', 'user_post_status' ),
			'follows'    => array( 'PRIMARY', 'follower_target' ),
			'saves'      => array( 'PRIMARY', 'user_post_collection' ),
			'reports'    => array( 'PRIMARY', 'duplicate_control' ),
			'views'      => array( 'PRIMARY', 'view_identity' ),
			'poll_votes' => array( 'PRIMARY', 'vote_identity' ),
			'audit_log'  => array( 'PRIMARY' ),
		);
	}

	/**
	 * dbDelta-compatible SQL.
	 *
	 * @param string|null $prefix Optional prefix.
	 * @return array<string,string>
	 */
	public static function schema( $prefix = null ) {
		global $wpdb;

		$tables  = self::table_names( $prefix );
		$charset = '';
		if ( isset( $wpdb ) && method_exists( $wpdb, 'get_charset_collate' ) ) {
			$charset = $wpdb->get_charset_collate();
		}

		return array(
			'reactions'  => "CREATE TABLE {$tables['reactions']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	post_id bigint(20) unsigned NOT NULL,
	user_id bigint(20) unsigned NOT NULL,
	reaction_type varchar(32) NOT NULL DEFAULT 'like',
	status varchar(20) NOT NULL DEFAULT 'active',
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY user_post_status (user_id,post_id,status),
	KEY post_status (post_id,status),
	KEY user_status (user_id,status)
) {$charset};",
			'follows'    => "CREATE TABLE {$tables['follows']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	follower_user_id bigint(20) unsigned NOT NULL,
	target_user_id bigint(20) unsigned NOT NULL,
	target_type varchar(32) NOT NULL DEFAULT 'user',
	status varchar(20) NOT NULL DEFAULT 'active',
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY follower_target (follower_user_id,target_user_id,target_type),
	KEY target_status (target_user_id,target_type,status),
	KEY follower_status (follower_user_id,status)
) {$charset};",
			'saves'      => "CREATE TABLE {$tables['saves']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	user_id bigint(20) unsigned NOT NULL,
	post_id bigint(20) unsigned NOT NULL,
	collection_key varchar(64) NOT NULL DEFAULT 'default',
	status varchar(20) NOT NULL DEFAULT 'active',
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY user_post_collection (user_id,post_id,collection_key),
	KEY post_status (post_id,status),
	KEY user_status (user_id,status)
) {$charset};",
			'reports'    => "CREATE TABLE {$tables['reports']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	reporter_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	object_type varchar(32) NOT NULL,
	object_id bigint(20) unsigned NOT NULL,
	reason varchar(64) NOT NULL,
	status varchar(20) NOT NULL DEFAULT 'open',
	duplicate_hash char(64) NOT NULL,
	notes text NULL,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY duplicate_control (reporter_user_id,object_type,object_id,duplicate_hash),
	KEY object_status (object_type,object_id,status),
	KEY reporter_status (reporter_user_id,status)
) {$charset};",
			'views'      => "CREATE TABLE {$tables['views']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	post_id bigint(20) unsigned NOT NULL,
	user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	anonymous_hash char(64) NOT NULL DEFAULT '',
	view_date date NOT NULL,
	view_count int(10) unsigned NOT NULL DEFAULT 1,
	status varchar(20) NOT NULL DEFAULT 'counted',
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY view_identity (post_id,user_id,anonymous_hash,view_date),
	KEY post_date (post_id,view_date),
	KEY user_date (user_id,view_date)
) {$charset};",
			'poll_votes' => "CREATE TABLE {$tables['poll_votes']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	poll_post_id bigint(20) unsigned NOT NULL,
	option_key varchar(64) NOT NULL,
	user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	anonymous_hash char(64) NOT NULL DEFAULT '',
	vote_group_key varchar(64) NOT NULL DEFAULT 'default',
	status varchar(20) NOT NULL DEFAULT 'active',
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY vote_identity (poll_post_id,user_id,anonymous_hash,vote_group_key),
	KEY poll_status (poll_post_id,status),
	KEY user_status (user_id,status)
) {$charset};",
			'audit_log'  => "CREATE TABLE {$tables['audit_log']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	action varchar(64) NOT NULL,
	object_type varchar(32) NOT NULL DEFAULT '',
	object_id bigint(20) unsigned NOT NULL DEFAULT 0,
	context longtext NULL,
	ip_hash char(64) NOT NULL DEFAULT '',
	created_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY action_created (action,created_at),
	KEY actor_created (actor_user_id,created_at),
	KEY object_lookup (object_type,object_id)
) {$charset};",
		);
	}

	/**
	 * Install or repair schema idempotently.
	 *
	 * @return array<string,mixed>
	 */
	public static function install() {
		$report = array(
			'success'                 => false,
			'status'                  => 'failed',
			'previous_schema_version' => function_exists( 'get_option' ) ? (string) get_option( Migrations::SCHEMA_OPTION_NAME, '' ) : '',
			'target_schema_version'   => SABRI_HNF_SCHEMA_VERSION,
			'dbdelta_available'       => false,
			'dbdelta_results'         => array(),
			'missing_tables'          => array(),
			'missing_indexes'         => array(),
			'table_status'            => array(),
			'index_status'            => array(),
			'message'                 => '',
		);

		if ( ! function_exists( 'dbDelta' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$report['dbdelta_available'] = self::dbdelta_available();
		if ( ! $report['dbdelta_available'] ) {
			$report['message'] = 'dbDelta is unavailable; schema installation was not attempted.';
			return self::record_install_failure( $report );
		}

		foreach ( self::schema() as $slug => $sql ) {
			$result = dbDelta( $sql );
			$report['dbdelta_results'][ $slug ] = is_array( $result ) ? $result : array();
		}

		$verification = self::verify_schema();
		$report['table_status']    = $verification['table_status'];
		$report['index_status']    = $verification['index_status'];
		$report['missing_tables']  = $verification['missing_tables'];
		$report['missing_indexes'] = $verification['missing_indexes'];

		if ( ! $verification['verified'] ) {
			$report['message'] = 'Schema installation completed but structure verification failed.';
			return self::record_install_failure( $report );
		}

		$report['success'] = true;
		$report['status']  = 'verified';
		$report['message'] = 'Schema installed and verified.';

		if ( function_exists( 'update_option' ) ) {
			update_option( Migrations::SCHEMA_OPTION_NAME, SABRI_HNF_SCHEMA_VERSION, false );
			update_option( self::INSTALL_RESULT_OPTION, $report, false );
		}

		return $report;
	}

	/**
	 * Verify expected tables, indexes, and unique constraints.
	 *
	 * @return array<string,mixed>
	 */
	public static function verify_schema() {
		$table_status    = self::table_status();
		$index_status    = self::index_status();
		$missing_tables  = array();
		$missing_indexes = array();

		foreach ( $table_status as $slug => $status ) {
			if ( 'Connected' !== $status ) {
				$missing_tables[] = $slug;
			}
		}

		foreach ( $index_status as $slug => $indexes ) {
			foreach ( $indexes as $index => $status ) {
				if ( 'Connected' !== $status ) {
					$missing_indexes[] = $slug . '.' . $index . ':' . $status;
				}
			}
		}

		return array(
			'verified'        => empty( $missing_tables ) && empty( $missing_indexes ),
			'table_status'    => $table_status,
			'index_status'    => $index_status,
			'missing_tables'  => $missing_tables,
			'missing_indexes' => $missing_indexes,
		);
	}

	/**
	 * Return table status.
	 *
	 * @return array<string,string>
	 */
	public static function table_status() {
		global $wpdb;

		$status = array();
		foreach ( self::table_names() as $slug => $table ) {
			$status[ $slug ] = 'Missing';
			if ( isset( $wpdb ) && method_exists( $wpdb, 'get_var' ) && method_exists( $wpdb, 'prepare' ) ) {
				$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
				$status[ $slug ] = $found === $table ? 'Connected' : 'Missing';
			}
		}

		return $status;
	}

	/**
	 * Return index status.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function index_status() {
		global $wpdb;

		$status = array();
		foreach ( self::table_names() as $slug => $table ) {
			$status[ $slug ] = array();
			foreach ( self::expected_indexes()[ $slug ] as $index ) {
				$status[ $slug ][ $index ] = 'Missing';
			}

			if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) ) {
				continue;
			}

			$found = $wpdb->get_results( 'SHOW INDEX FROM `' . str_replace( '`', '', $table ) . '`', ARRAY_A );
			if ( ! is_array( $found ) ) {
				continue;
			}

			foreach ( $found as $row ) {
				if ( empty( $row['Key_name'] ) || ! isset( $status[ $slug ][ $row['Key_name'] ] ) ) {
					continue;
				}

				if ( in_array( $row['Key_name'], self::expected_unique_indexes()[ $slug ], true ) ) {
					$status[ $slug ][ $row['Key_name'] ] = isset( $row['Non_unique'] ) && 0 === (int) $row['Non_unique'] ? 'Connected' : 'Not unique';
				} else {
					$status[ $slug ][ $row['Key_name'] ] = 'Connected';
				}
			}
		}

		return $status;
	}

	/**
	 * Check dbDelta availability, with a filterable seam for host diagnostics and tests.
	 *
	 * @return bool
	 */
	private static function dbdelta_available() {
		$available = function_exists( 'dbDelta' );
		if ( function_exists( 'apply_filters' ) ) {
			$available = apply_filters( 'sabri_feed_dbdelta_available', $available );
		}

		return (bool) $available;
	}

	/**
	 * Store a failed install report without advancing the schema version.
	 *
	 * @param array<string,mixed> $report Report.
	 * @return array<string,mixed>
	 */
	private static function record_install_failure( array $report ) {
		if ( function_exists( 'update_option' ) ) {
			update_option( self::INSTALL_RESULT_OPTION, $report, false );
		}

		if ( class_exists( __NAMESPACE__ . '\\AuditLog' ) ) {
			AuditLog::record( 'schema_install_failed', $report );
		}

		return $report;
	}
}
