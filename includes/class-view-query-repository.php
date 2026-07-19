<?php
/**
 * Phase 3G view read queries.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Provides prepared, identity-bounded reads for minimized view rows. */
final class ViewQueryRepository {
	/**
	 * Find a counted identity inside the configured deduplication window.
	 *
	 * @param int    $post_id Post ID.
	 * @param int    $user_id Authenticated user ID or zero.
	 * @param string $anonymous_hash Anonymous HMAC or empty.
	 * @param string $start_date Inclusive UTC date.
	 * @param string $end_date Inclusive UTC date.
	 * @return array<string,mixed>|null
	 */
	public static function identity_record( $post_id, $user_id, $anonymous_hash, $start_date, $end_date ) {
		global $wpdb;
		if ( ! self::database_ready( array( 'get_row', 'prepare' ) ) ) {
			return null;
		}

		$post_id        = self::positive_id( $post_id );
		$user_id        = self::non_negative_id( $user_id );
		$anonymous_hash = self::hash_value( $anonymous_hash );
		$start_date     = self::date_value( $start_date );
		$end_date       = self::date_value( $end_date );
		if ( $post_id <= 0 || '' === $start_date || '' === $end_date || ( 0 === $user_id && '' === $anonymous_hash ) ) {
			return null;
		}

		$table = InteractionRepository::table_name( 'views' );
		if ( $user_id > 0 ) {
			$sql = $wpdb->prepare(
				"SELECT id, post_id, user_id, anonymous_hash, view_date, view_count, status FROM `{$table}` WHERE post_id = %d AND user_id = %d AND anonymous_hash = %s AND status = %s AND view_date >= %s AND view_date <= %s ORDER BY view_date DESC, id DESC LIMIT 1",
				$post_id,
				$user_id,
				'',
				'counted',
				$start_date,
				$end_date
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT id, post_id, user_id, anonymous_hash, view_date, view_count, status FROM `{$table}` WHERE post_id = %d AND user_id = %d AND anonymous_hash = %s AND status = %s AND view_date >= %s AND view_date <= %s ORDER BY view_date DESC, id DESC LIMIT 1",
				$post_id,
				0,
				$anonymous_hash,
				'counted',
				$start_date,
				$end_date
			);
		}

		$row = $wpdb->get_row( $sql, ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** Return aggregate counted views for one visible post. */
	public static function aggregate_count( $post_id ) {
		global $wpdb;
		if ( ! self::database_ready( array( 'get_var', 'prepare' ) ) ) {
			return 0;
		}
		$post_id = self::positive_id( $post_id );
		if ( $post_id <= 0 ) {
			return 0;
		}
		$table = InteractionRepository::table_name( 'views' );
		$sql   = $wpdb->prepare( "SELECT COALESCE(SUM(view_count), 0) FROM `{$table}` WHERE post_id = %d AND status = %s", $post_id, 'counted' );
		return max( 0, (int) $wpdb->get_var( $sql ) );
	}

	/** Database adapter readiness. */
	private static function database_ready( array $methods ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || '' === InteractionRepository::table_name( 'views' ) ) {
			return false;
		}
		foreach ( $methods as $method ) {
			if ( ! method_exists( $wpdb, $method ) ) {
				return false;
			}
		}
		return true;
	}

	/** Strict positive ID. */
	private static function positive_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/', (string) $value ) ? (int) $value : 0;
	}

	/** Strict non-negative ID. */
	private static function non_negative_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[0-9]+$/', (string) $value ) ? (int) $value : 0;
	}

	/** SHA-256 value or empty. */
	private static function hash_value( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return '' === $value || preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
	}

	/** ISO date or empty. */
	private static function date_value( $value ) {
		$value = trim( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
	}
}
