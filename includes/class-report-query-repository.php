<?php
/**
 * Prepared report queries for Phase 3E.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides narrowly scoped confidential report reads.
 */
final class ReportQueryRepository {
	/**
	 * Find one report by its duplicate-control identity.
	 *
	 * @param int    $reporter_user_id Reporter user ID.
	 * @param string $object_type Object type.
	 * @param int    $object_id Object ID.
	 * @param string $duplicate_hash Duplicate hash.
	 * @return array<string,mixed>|null
	 */
	public static function duplicate_record( $reporter_user_id, $object_type, $object_id, $duplicate_hash ) {
		global $wpdb;
		if ( ! self::database_ready( array( 'get_row', 'prepare' ) ) ) {
			return null;
		}

		$table = InteractionRepository::table_name( 'reports' );
		$sql   = $wpdb->prepare(
			"SELECT id, reporter_user_id, object_type, object_id, reason, status, duplicate_hash, notes, created_at, updated_at FROM `{$table}` WHERE reporter_user_id = %d AND object_type = %s AND object_id = %d AND duplicate_hash = %s ORDER BY id DESC LIMIT 1",
			self::positive_id( $reporter_user_id ),
			self::clean_key( $object_type ),
			self::positive_id( $object_id ),
			self::hash_value( $duplicate_hash )
		);
		$row = $wpdb->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Find one report by primary ID.
	 *
	 * @param int $report_id Report ID.
	 * @return array<string,mixed>|null
	 */
	public static function report( $report_id ) {
		global $wpdb;
		if ( ! self::database_ready( array( 'get_row', 'prepare' ) ) ) {
			return null;
		}

		$table = InteractionRepository::table_name( 'reports' );
		$sql   = $wpdb->prepare(
			"SELECT id, reporter_user_id, object_type, object_id, reason, status, duplicate_hash, notes, created_at, updated_at FROM `{$table}` WHERE id = %d LIMIT 1",
			self::positive_id( $report_id )
		);
		$row = $wpdb->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Return a confidential, filtered moderator queue.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array<string,mixed>
	 */
	public static function queue( array $filters = array() ) {
		global $wpdb;
		if ( ! self::database_ready( array( 'get_results', 'get_var', 'prepare' ) ) ) {
			return array( 'items' => array(), 'total' => 0, 'page' => 1, 'per_page' => 25, 'max_pages' => 0 );
		}

		$status      = isset( $filters['status'] ) && ReportPolicy::state_allowed( $filters['status'] ) ? self::clean_key( $filters['status'] ) : '';
		$reason      = isset( $filters['reason'] ) && ReportPolicy::reason_allowed( $filters['reason'] ) ? self::clean_key( $filters['reason'] ) : '';
		$object_type = isset( $filters['object_type'] ) && ReportPolicy::object_type_allowed( $filters['object_type'] ) ? self::clean_key( $filters['object_type'] ) : '';
		$page        = isset( $filters['page'] ) ? max( 1, (int) $filters['page'] ) : 1;
		$per_page    = isset( $filters['per_page'] ) ? min( 100, max( 1, (int) $filters['per_page'] ) ) : 25;
		$offset      = ( $page - 1 ) * $per_page;
		$table       = InteractionRepository::table_name( 'reports' );
		$where       = array( '1=1' );
		$args        = array();

		if ( '' !== $status ) {
			$where[] = 'status = %s';
			$args[]  = $status;
		}
		if ( '' !== $reason ) {
			$where[] = 'reason = %s';
			$args[]  = $reason;
		}
		if ( '' !== $object_type ) {
			$where[] = 'object_type = %s';
			$args[]  = $object_type;
		}

		$where_sql = implode( ' AND ', $where );
		$count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}";
		$list_sql  = "SELECT id, reporter_user_id, object_type, object_id, reason, status, notes, created_at, updated_at FROM `{$table}` WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		$count     = empty( $args ) ? (int) $wpdb->get_var( $count_sql ) : (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) );
		$list_args = $args;
		$list_args[] = $per_page;
		$list_args[] = $offset;
		$rows      = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_args ), ARRAY_A );

		return array(
			'items'     => is_array( $rows ) ? array_values( $rows ) : array(),
			'total'     => max( 0, $count ),
			'page'      => $page,
			'per_page'  => $per_page,
			'max_pages' => $count > 0 ? (int) ceil( $count / $per_page ) : 0,
		);
	}

	/**
	 * Check required database methods and table ownership.
	 *
	 * @param array<int,string> $methods Methods.
	 * @return bool
	 */
	private static function database_ready( array $methods ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || '' === InteractionRepository::table_name( 'reports' ) ) {
			return false;
		}
		foreach ( $methods as $method ) {
			if ( ! method_exists( $wpdb, $method ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Strict positive ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private static function positive_id( $value ) {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/', $value ) ) ) {
			return 0;
		}
		$value = (int) $value;
		return $value > 0 ? $value : 0;
	}

	/**
	 * Sanitize key.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}

	/**
	 * Validate a fixed SHA-256 hash.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function hash_value( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : str_repeat( '0', 64 );
	}
}
