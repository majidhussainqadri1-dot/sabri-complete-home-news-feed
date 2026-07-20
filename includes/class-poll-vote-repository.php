<?php
/**
 * Phase 3F poll vote queries.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides narrowly scoped prepared reads for authenticated poll votes.
 */
final class PollVoteRepository {
	/**
	 * Return one natural-key vote row regardless of state.
	 *
	 * @param int    $poll_post_id Poll post ID.
	 * @param int    $user_id User ID.
	 * @param string $vote_group_key Vote group.
	 * @return array<string,mixed>|null
	 */
	public static function vote_record( $poll_post_id, $user_id, $vote_group_key = PollPolicy::VOTE_GROUP ) {
		global $wpdb;
		if ( ! self::database_ready( array( 'get_row', 'prepare' ) ) ) {
			return null;
		}

		$table = InteractionRepository::table_name( 'poll_votes' );
		$sql   = $wpdb->prepare(
			"SELECT id, poll_post_id, option_key, user_id, anonymous_hash, vote_group_key, status FROM `{$table}` WHERE poll_post_id = %d AND user_id = %d AND anonymous_hash = %s AND vote_group_key = %s ORDER BY id DESC LIMIT 1",
			self::positive_id( $poll_post_id ),
			self::positive_id( $user_id ),
			'',
			self::group_key( $vote_group_key )
		);
		$row = $wpdb->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Return active vote counts grouped by option key.
	 *
	 * @param int    $poll_post_id Poll post ID.
	 * @param string $vote_group_key Vote group.
	 * @return array<string,int>
	 */
	public static function aggregate_counts( $poll_post_id, $vote_group_key = PollPolicy::VOTE_GROUP ) {
		global $wpdb;
		if ( ! self::database_ready( array( 'get_results', 'prepare' ) ) ) {
			return array();
		}

		$table = InteractionRepository::table_name( 'poll_votes' );
		$sql   = $wpdb->prepare(
			"SELECT option_key, COUNT(*) AS total FROM `{$table}` WHERE poll_post_id = %d AND vote_group_key = %s AND status = %s GROUP BY option_key",
			self::positive_id( $poll_post_id ),
			self::group_key( $vote_group_key ),
			'active'
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$out  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$key = isset( $row['option_key'] ) ? PollPolicy::option_key( $row['option_key'] ) : '';
			if ( '' !== $key ) {
				$out[ $key ] = max( 0, (int) $row['total'] );
			}
		}
		return $out;
	}

	/**
	 * Check required database methods and table availability.
	 *
	 * @param array<int,string> $methods Methods.
	 * @return bool
	 */
	private static function database_ready( array $methods ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || '' === InteractionRepository::table_name( 'poll_votes' ) ) {
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
		return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/', (string) $value ) ? (int) $value : 0;
	}

	/**
	 * Bounded group key.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function group_key( $value ) {
		$value = function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
		return '' !== $value ? substr( $value, 0, 64 ) : PollPolicy::VOTE_GROUP;
	}
}
