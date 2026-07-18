<?php
/**
 * Safe database repository boundary for Phase 3 interactions.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restricts social-data writes to plugin-owned tables and allow-listed columns.
 */
final class InteractionRepository {
	/**
	 * Writable columns by table slug.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function writable_columns() {
		return array(
			'reactions'  => array( 'post_id', 'user_id', 'reaction_type', 'status', 'created_at', 'updated_at' ),
			'follows'    => array( 'follower_user_id', 'target_user_id', 'target_type', 'status', 'created_at', 'updated_at' ),
			'saves'      => array( 'user_id', 'post_id', 'collection_key', 'status', 'created_at', 'updated_at' ),
			'reports'    => array( 'reporter_user_id', 'object_type', 'object_id', 'reason', 'status', 'duplicate_hash', 'notes', 'created_at', 'updated_at' ),
			'views'      => array( 'post_id', 'user_id', 'anonymous_hash', 'view_date', 'view_count', 'status', 'created_at', 'updated_at' ),
			'poll_votes' => array( 'poll_post_id', 'option_key', 'user_id', 'anonymous_hash', 'vote_group_key', 'status', 'created_at', 'updated_at' ),
			'audit_log'  => array( 'actor_user_id', 'action', 'object_type', 'object_id', 'context', 'ip_hash', 'created_at' ),
		);
	}

	/**
	 * Return a validated full table name.
	 *
	 * @param string $slug Table slug.
	 * @return string
	 */
	public static function table_name( $slug ) {
		$slug   = self::clean_key( $slug );
		$tables = Database::table_names();
		return isset( $tables[ $slug ] ) ? $tables[ $slug ] : '';
	}

	/**
	 * Whether the WordPress database adapter exposes required safe methods.
	 *
	 * @return bool
	 */
	public static function database_ready() {
		global $wpdb;
		return isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'insert' ) && method_exists( $wpdb, 'update' ) && method_exists( $wpdb, 'prepare' );
	}

	/**
	 * Whether a status belongs to the table allow-list.
	 *
	 * @param string $slug Table slug.
	 * @param string $status Status.
	 * @return bool
	 */
	public static function status_allowed( $slug, $status ) {
		$slug     = self::clean_key( $slug );
		$status   = self::clean_key( $status );
		$statuses = Database::allowed_statuses();
		return isset( $statuses[ $slug ] ) && in_array( $status, $statuses[ $slug ], true );
	}

	/**
	 * Insert an allow-listed row.
	 *
	 * @param string              $slug Table slug.
	 * @param array<string,mixed> $data Row data.
	 * @return array<string,mixed>
	 */
	public static function insert_row( $slug, array $data ) {
		global $wpdb;

		$slug  = self::clean_key( $slug );
		$table = self::table_name( $slug );
		if ( '' === $table ) {
			return InteractionResult::error( 'invalid_repository', 'The requested data store is unavailable.', array(), 400 );
		}

		if ( ! self::database_ready() ) {
			return InteractionResult::error( 'database_unavailable', 'The data store is temporarily unavailable.', array(), 503 );
		}

		$normalized = self::normalize_data( $slug, $data, false );
		if ( empty( $normalized['ok'] ) ) {
			return $normalized;
		}

		$row = $normalized['data']['row'];
		if ( empty( $row ) ) {
			return InteractionResult::error( 'empty_insert', 'A bounded data row is required.', array(), 400 );
		}

		if ( in_array( 'created_at', self::writable_columns()[ $slug ], true ) && empty( $row['created_at'] ) ) {
			$row['created_at'] = self::now_utc();
		}
		if ( in_array( 'updated_at', self::writable_columns()[ $slug ], true ) && empty( $row['updated_at'] ) ) {
			$row['updated_at'] = self::now_utc();
		}

		$result = $wpdb->insert( $table, $row, self::formats_for( $row ) );
		if ( false === $result ) {
			return InteractionResult::error( 'database_write_failed', 'The action could not be saved.', array(), 500 );
		}

		return InteractionResult::success(
			'row_inserted',
			array(
				'repository' => $slug,
				'affected'   => (int) $result,
			),
			'Saved.',
			201
		);
	}

	/**
	 * Update allow-listed columns using a bounded identity condition.
	 *
	 * @param string              $slug Table slug.
	 * @param array<string,mixed> $data Updated data.
	 * @param array<string,mixed> $where Conditions.
	 * @return array<string,mixed>
	 */
	public static function update_rows( $slug, array $data, array $where ) {
		global $wpdb;

		$slug  = self::clean_key( $slug );
		$table = self::table_name( $slug );
		if ( '' === $table ) {
			return InteractionResult::error( 'invalid_repository', 'The requested data store is unavailable.', array(), 400 );
		}

		if ( 'audit_log' === $slug ) {
			return InteractionResult::error( 'append_only_repository', 'Audit records are append-only.', array(), 405 );
		}

		if ( ! self::database_ready() ) {
			return InteractionResult::error( 'database_unavailable', 'The data store is temporarily unavailable.', array(), 503 );
		}

		if ( empty( $where ) ) {
			return InteractionResult::error( 'missing_update_condition', 'A bounded update condition is required.', array(), 400 );
		}

		$normalized_data  = self::normalize_data( $slug, $data, false );
		$normalized_where = self::normalize_data( $slug, $where, true );
		if ( empty( $normalized_data['ok'] ) ) {
			return $normalized_data;
		}
		if ( empty( $normalized_where['ok'] ) ) {
			return $normalized_where;
		}

		$row       = $normalized_data['data']['row'];
		$where_row = $normalized_where['data']['row'];
		if ( empty( $row ) || empty( $where_row ) ) {
			return InteractionResult::error( 'empty_update', 'A bounded update is required.', array(), 400 );
		}

		if ( ! self::where_is_bounded( $slug, $where_row ) ) {
			return InteractionResult::error( 'unbounded_update_condition', 'A complete identity condition is required.', array(), 400 );
		}

		if ( in_array( 'updated_at', self::writable_columns()[ $slug ], true ) && ! array_key_exists( 'updated_at', $row ) ) {
			$row['updated_at'] = self::now_utc();
		}

		$result = $wpdb->update( $table, $row, $where_row, self::formats_for( $row ), self::formats_for( $where_row ) );
		if ( false === $result ) {
			return InteractionResult::error( 'database_update_failed', 'The action could not be updated.', array(), 500 );
		}

		return InteractionResult::success(
			'row_updated',
			array(
				'repository' => $slug,
				'affected'   => (int) $result,
			),
			'Updated.',
			200
		);
	}

	/**
	 * Normalize a row and reject every unknown column.
	 *
	 * @param string              $slug Table slug.
	 * @param array<string,mixed> $data Raw data.
	 * @param bool                $allow_id Whether the primary ID may appear in a WHERE clause.
	 * @return array<string,mixed>
	 */
	private static function normalize_data( $slug, array $data, $allow_id ) {
		$columns = self::writable_columns();
		if ( ! isset( $columns[ $slug ] ) ) {
			return InteractionResult::error( 'invalid_repository', 'The requested data store is unavailable.', array(), 400 );
		}

		$allowed = $columns[ $slug ];
		if ( $allow_id ) {
			$allowed[] = 'id';
		}

		$row = array();
		foreach ( $data as $column => $value ) {
			$column = self::clean_key( $column );
			if ( '' === $column || ! in_array( $column, $allowed, true ) ) {
				return InteractionResult::error( 'invalid_repository_column', 'The requested data field is unavailable.', array(), 400 );
			}

			$clean = self::sanitize_value( $column, $value );
			if ( is_wp_error( $clean ) ) {
				return InteractionResult::error( $clean->get_error_code(), $clean->get_error_message(), array(), 400 );
			}

			if ( 'status' === $column && ! self::status_allowed( $slug, $clean ) ) {
				return InteractionResult::error( 'invalid_repository_status', 'The requested data state is unavailable.', array(), 400 );
			}

			$row[ $column ] = $clean;
		}

		return InteractionResult::success( 'row_valid', array( 'row' => $row ), 'Valid.', 200 );
	}

	/**
	 * Require a primary ID or the full natural identity for the selected table.
	 *
	 * @param string              $slug Table slug.
	 * @param array<string,mixed> $where Normalized conditions.
	 * @return bool
	 */
	private static function where_is_bounded( $slug, array $where ) {
		if ( isset( $where['id'] ) && (int) $where['id'] > 0 ) {
			return true;
		}

		switch ( $slug ) {
			case 'reactions':
				return ! empty( $where['user_id'] ) && ! empty( $where['post_id'] );
			case 'follows':
				return ! empty( $where['follower_user_id'] ) && ! empty( $where['target_user_id'] ) && ! empty( $where['target_type'] );
			case 'saves':
				return ! empty( $where['user_id'] ) && ! empty( $where['post_id'] ) && ! empty( $where['collection_key'] );
			case 'reports':
				return ! empty( $where['reporter_user_id'] ) && ! empty( $where['object_type'] ) && ! empty( $where['object_id'] ) && ! empty( $where['duplicate_hash'] );
			case 'views':
				return ! empty( $where['post_id'] ) && ! empty( $where['view_date'] ) && ( ! empty( $where['user_id'] ) || ! empty( $where['anonymous_hash'] ) );
			case 'poll_votes':
				return ! empty( $where['poll_post_id'] ) && ! empty( $where['vote_group_key'] ) && ( ! empty( $where['user_id'] ) || ! empty( $where['anonymous_hash'] ) );
			default:
				return false;
		}
	}

	/**
	 * Sanitize a value according to its frozen schema type.
	 *
	 * @param string $column Column.
	 * @param mixed  $value Value.
	 * @return mixed|\WP_Error
	 */
	private static function sanitize_value( $column, $value ) {
		$integer_columns = array( 'id', 'post_id', 'user_id', 'follower_user_id', 'target_user_id', 'reporter_user_id', 'object_id', 'view_count', 'poll_post_id', 'actor_user_id' );
		if ( in_array( $column, $integer_columns, true ) ) {
			if ( ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/', $value ) ) ) || (int) $value < 0 ) {
				return new \WP_Error( 'invalid_repository_integer', 'The requested numeric identifier is invalid.' );
			}
			return (int) $value;
		}

		if ( in_array( $column, array( 'anonymous_hash', 'duplicate_hash', 'ip_hash' ), true ) ) {
			$value = strtolower( trim( (string) $value ) );
			if ( '' !== $value && ! preg_match( '/^[a-f0-9]{64}$/', $value ) ) {
				return new \WP_Error( 'invalid_repository_hash', 'The requested data identifier is invalid.' );
			}
			return $value;
		}

		if ( in_array( $column, array( 'notes', 'context' ), true ) ) {
			return function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $value ) : trim( strip_tags( (string) $value ) );
		}

		if ( in_array( $column, array( 'created_at', 'updated_at' ), true ) ) {
			$value = self::clean_text( $value );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
				return new \WP_Error( 'invalid_repository_datetime', 'The requested timestamp is invalid.' );
			}
			return $value;
		}

		if ( 'view_date' === $column ) {
			$value = self::clean_text( $value );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				return new \WP_Error( 'invalid_repository_date', 'The requested date is invalid.' );
			}
			return $value;
		}

		return self::clean_key( $value );
	}

	/**
	 * Build wpdb formats from normalized row values.
	 *
	 * @param array<string,mixed> $row Row.
	 * @return array<int,string>
	 */
	private static function formats_for( array $row ) {
		$formats = array();
		foreach ( $row as $value ) {
			$formats[] = is_int( $value ) ? '%d' : '%s';
		}
		return $formats;
	}

	/**
	 * UTC timestamp.
	 *
	 * @return string
	 */
	private static function now_utc() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Sanitize key.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_key( $value ) {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}

	/**
	 * Sanitize text.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_text( $value ) {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}
		return trim( strip_tags( (string) $value ) );
	}
}
