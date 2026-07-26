<?php
/**
 * Persistent bounded rate limiter.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Limits abuse-prone Phase 5 actions without storing raw IPs. */
final class Phase5RateLimiter {
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'sabri_hnf_phase5_cleanup', array( __CLASS__, 'cleanup' ) );
		}
	}

	public static function allow( $bucket, $limit, $window_seconds, $actor = 0 ) {
		global $wpdb;
		$bucket = Phase5Contracts::slug( str_replace( '_', '-', (string) $bucket ), 64 );
		$limit = max( 1, min( 1000, (int) $limit ) );
		$window_seconds = max( 60, min( DAY_IN_SECONDS, (int) $window_seconds ) );
		if ( '' === $bucket ) { return false; }
		$actor = max( 0, (int) $actor );
		$identity = $actor > 0 ? 'u:' . $actor : 'a:' . self::anonymous_identity();
		$window_start = (int) floor( time() / $window_seconds ) * $window_seconds;
		$window_key = $bucket . ':' . $window_start;
		$hash = hash_hmac( 'sha256', $identity . '|' . $bucket, self::salt() );
		$table = Phase5Repository::table( 'rate_limits' );
		if ( ! isset( $wpdb ) || '' === $table ) { return false; }
		$expires = gmdate( 'Y-m-d H:i:s', $window_start + $window_seconds + 60 );
		$now = gmdate( 'Y-m-d H:i:s' );
		$sql = "INSERT INTO `{$table}` (bucket_hash,window_key,hit_count,expires_at,updated_at) VALUES (%s,%s,1,%s,%s) ON DUPLICATE KEY UPDATE hit_count=hit_count+1,expires_at=VALUES(expires_at),updated_at=VALUES(updated_at)"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table is allow-listed.
		$result = $wpdb->query( $wpdb->prepare( $sql, $hash, $window_key, $expires, $now ) );
		if ( false === $result ) { return false; }
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT hit_count FROM `{$table}` WHERE bucket_hash=%s AND window_key=%s LIMIT 1", $hash, $window_key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table is allow-listed.
		return (int) $count <= $limit;
	}

	public static function cleanup() {
		global $wpdb;
		$table = Phase5Repository::table( 'rate_limits' );
		if ( isset( $wpdb ) && '' !== $table ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE expires_at < %s LIMIT 5000", gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table is allow-listed.
		}
	}

	private static function anonymous_identity() {
		$parts = array(
			isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '',
			isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( (string) $_SERVER['HTTP_USER_AGENT'], 0, 255 ) : '',
		);
		return hash_hmac( 'sha256', implode( '|', $parts ), self::salt() );
	}

	private static function salt() {
		return function_exists( 'wp_salt' ) ? wp_salt( 'nonce' ) : ( defined( 'AUTH_SALT' ) ? AUTH_SALT : 'sabri-phase5-local-salt' );
	}
}
