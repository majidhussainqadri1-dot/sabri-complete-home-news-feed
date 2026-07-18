<?php
/**
 * Read and delete queries for Phase 3 interactions.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides narrowly scoped prepared queries for reactions and saves.
 */
final class InteractionQueryRepository {
	/**
	 * Find the active reaction for a user and post.
	 *
	 * @param int $user_id User ID.
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>|null
	 */
	public static function active_reaction( $user_id, $post_id ) {
		global $wpdb;
		if ( ! self::database_ready( array( 'get_row', 'prepare' ) ) ) {
			return null;
		}

		$table = InteractionRepository::table_name( 'reactions' );
		$sql   = $wpdb->prepare(
			"SELECT id, post_id, user_id, reaction_type, status FROM `{$table}` WHERE user_id = %d AND post_id = %d AND status = %s ORDER BY id DESC LIMIT 1",
			self::positive_id( $user_id ),
			self::positive_id( $post_id ),
			'active'
		);
		$row = $wpdb->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Delete the active reaction for a user and post.
	 *
	 * @param int $user_id User ID.
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function delete_active_reaction( $user_id, $post_id ) {
		global $wpdb;
		if ( ! self::database_ready( array( 'delete' ) ) ) {
			return false;
		}

		$table  = InteractionRepository::table_name( 'reactions' );
		$result = $wpdb->delete(
			$table,
			array(
				'user_id' => self::positive_id( $user_id ),
				'post_id' => self::positive_id( $post_id ),
				'status'  => 'active',
			),
			array( '%d', '%d', '%s' )
		);
		return false !== $result;
	}

	/**
	 * Return active reaction counts grouped by type.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,int>
	 */
	public static function reaction_counts( $post_id ) {
		global $wpdb;
		$counts = array( 'like' => 0, 'dislike' => 0 );
		if ( ! self::database_ready( array( 'get_results', 'prepare' ) ) ) {
			return $counts;
		}

		$table = InteractionRepository::table_name( 'reactions' );
		$sql   = $wpdb->prepare(
			"SELECT reaction_type, COUNT(*) AS total FROM `{$table}` WHERE post_id = %d AND status = %s GROUP BY reaction_type",
			self::positive_id( $post_id ),
			'active'
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$type = isset( $row['reaction_type'] ) ? sanitize_key( $row['reaction_type'] ) : '';
			if ( array_key_exists( $type, $counts ) ) {
				$counts[ $type ] = max( 0, (int) $row['total'] );
			}
		}
		return $counts;
	}

	/**
	 * Find one save record regardless of active/removed status.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $post_id Post ID.
	 * @param string $collection Collection key.
	 * @return array<string,mixed>|null
	 */
	public static function save_record( $user_id, $post_id, $collection = 'default' ) {
		global $wpdb;
		if ( ! self::database_ready( array( 'get_row', 'prepare' ) ) ) {
			return null;
		}

		$table = InteractionRepository::table_name( 'saves' );
		$sql   = $wpdb->prepare(
			"SELECT id, user_id, post_id, collection_key, status FROM `{$table}` WHERE user_id = %d AND post_id = %d AND collection_key = %s ORDER BY id DESC LIMIT 1",
			self::positive_id( $user_id ),
			self::positive_id( $post_id ),
			self::collection_key( $collection )
		);
		$row = $wpdb->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Return active saved post IDs for the current user.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit Limit.
	 * @return array<int,int>
	 */
	public static function saved_post_ids( $user_id, $limit = 100 ) {
		global $wpdb;
		if ( ! self::database_ready( array( 'get_col', 'prepare' ) ) ) {
			return array();
		}

		$limit = min( 200, max( 1, (int) $limit ) );
		$table = InteractionRepository::table_name( 'saves' );
		$sql   = $wpdb->prepare(
			"SELECT post_id FROM `{$table}` WHERE user_id = %d AND status = %s ORDER BY updated_at DESC, id DESC LIMIT %d",
			self::positive_id( $user_id ),
			'active',
			$limit
		);
		$ids = $wpdb->get_col( $sql );
		return array_values( array_unique( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : array() ) ) ) );
	}

	/**
	 * Check database adapter methods.
	 *
	 * @param array<int,string> $methods Required methods.
	 * @return bool
	 */
	private static function database_ready( array $methods ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return false;
		}
		foreach ( $methods as $method ) {
			if ( ! method_exists( $wpdb, $method ) ) {
				return false;
			}
		}
		return '' !== InteractionRepository::table_name( 'reactions' ) && '' !== InteractionRepository::table_name( 'saves' );
	}

	/**
	 * Positive integer ID.
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
	 * Safe collection key.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function collection_key( $value ) {
		$value = function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
		return '' !== $value ? substr( $value, 0, 64 ) : 'default';
	}
}
