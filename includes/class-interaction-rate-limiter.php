<?php
/**
 * Phase 3 interaction rate limiting.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides bounded, per-user/per-action/per-object write throttling.
 */
final class InteractionRateLimiter {
	const KEY_PREFIX = 'sabri_hnf_rl_';

	/**
	 * Safe default limits from the approved Phase 3 plan.
	 *
	 * @return array<string,array<string,int>>
	 */
	public static function limits() {
		return array(
			'reactions'  => array( 'limit' => 60, 'window' => 300 ),
			'saves'      => array( 'limit' => 60, 'window' => 300 ),
			'follows'    => array( 'limit' => 30, 'window' => 600 ),
			'comments'   => array( 'limit' => 10, 'window' => 600 ),
			'reports'    => array( 'limit' => 5, 'window' => 3600 ),
			'poll_votes' => array( 'limit' => 20, 'window' => 600 ),
		);
	}

	/**
	 * Consume one bounded action attempt.
	 *
	 * Unknown actions and unavailable storage fail closed.
	 *
	 * @param string   $action Action key.
	 * @param int      $user_id Authenticated user ID.
	 * @param int      $object_id Optional object ID.
	 * @param int|null $limit Optional lower test/configuration override.
	 * @param int|null $window Optional window override in seconds.
	 * @return array<string,mixed>
	 */
	public static function attempt( $action, $user_id, $object_id = 0, $limit = null, $window = null ) {
		$action    = self::clean_key( $action );
		$user_id   = self::positive_id( $user_id );
		$object_id = self::non_negative_id( $object_id );
		$limits    = self::limits();

		if ( ! isset( $limits[ $action ] ) ) {
			return InteractionResult::error( 'unknown_rate_limit_action', 'This action is unavailable.', array(), 400 );
		}

		if ( $user_id <= 0 ) {
			return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 );
		}

		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
			return InteractionResult::error( 'rate_limit_storage_unavailable', 'This action is temporarily unavailable.', array(), 503 );
		}

		$limit  = null === $limit ? $limits[ $action ]['limit'] : self::bounded_int( $limit, 1, 1000 );
		$window = null === $window ? $limits[ $action ]['window'] : self::bounded_int( $window, 60, 86400 );
		$now    = self::now();
		$key    = self::key( $action, $user_id, $object_id );
		$state  = get_transient( $key );

		if ( ! is_array( $state ) || empty( $state['reset_at'] ) || (int) $state['reset_at'] <= $now ) {
			$state = array(
				'count'    => 0,
				'reset_at' => $now + $window,
			);
		}

		$count = isset( $state['count'] ) ? max( 0, (int) $state['count'] ) : 0;
		if ( $count >= $limit ) {
			return InteractionResult::error(
				'rate_limited',
				'Too many requests. Please try again later.',
				array(
					'retry_after' => max( 1, (int) $state['reset_at'] - $now ),
					'limit'       => $limit,
					'remaining'   => 0,
				),
				429
			);
		}

		$state['count'] = $count + 1;
		$ttl            = max( 1, (int) $state['reset_at'] - $now );
		if ( ! set_transient( $key, $state, $ttl ) ) {
			return InteractionResult::error( 'rate_limit_storage_failed', 'This action is temporarily unavailable.', array(), 503 );
		}

		return InteractionResult::success(
			'rate_limit_ok',
			array(
				'limit'       => $limit,
				'remaining'   => max( 0, $limit - (int) $state['count'] ),
				'reset_after' => $ttl,
			),
			'Allowed.',
			200
		);
	}

	/**
	 * Delete a specific limiter bucket for controlled tests or repair.
	 *
	 * @param string $action Action key.
	 * @param int    $user_id User ID.
	 * @param int    $object_id Object ID.
	 * @return bool
	 */
	public static function reset( $action, $user_id, $object_id = 0 ) {
		$action = self::clean_key( $action );
		if ( ! isset( self::limits()[ $action ] ) || ! function_exists( 'delete_transient' ) ) {
			return false;
		}

		return (bool) delete_transient( self::key( $action, self::positive_id( $user_id ), self::non_negative_id( $object_id ) ) );
	}

	/**
	 * Build a privacy-safe transient key.
	 *
	 * @param string $action Action key.
	 * @param int    $user_id User ID.
	 * @param int    $object_id Object ID.
	 * @return string
	 */
	private static function key( $action, $user_id, $object_id ) {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'nonce' ) : SABRI_HNF_SLUG;
		$raw  = $action . '|' . (int) $user_id . '|' . (int) $object_id;
		return self::KEY_PREFIX . hash_hmac( 'sha256', $raw, (string) $salt );
	}

	/**
	 * Return a filterable UTC timestamp for deterministic tests.
	 *
	 * @return int
	 */
	private static function now() {
		$now = time();
		if ( function_exists( 'apply_filters' ) ) {
			$now = apply_filters( 'sabri_feed_rate_limit_now', $now );
		}

		return max( 1, (int) $now );
	}

	/**
	 * Sanitize an action key.
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
	 * Positive ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private static function positive_id( $value ) {
		$value = function_exists( 'absint' ) ? absint( $value ) : abs( (int) $value );
		return $value > 0 ? $value : 0;
	}

	/**
	 * Non-negative object ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private static function non_negative_id( $value ) {
		return function_exists( 'absint' ) ? absint( $value ) : abs( (int) $value );
	}

	/**
	 * Bound an integer.
	 *
	 * @param mixed $value Value.
	 * @param int   $min Minimum.
	 * @param int   $max Maximum.
	 * @return int
	 */
	private static function bounded_int( $value, $min, $max ) {
		$value = (int) $value;
		return min( $max, max( $min, $value ) );
	}
}
