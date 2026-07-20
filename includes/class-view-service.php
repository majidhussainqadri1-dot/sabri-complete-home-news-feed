<?php
/**
 * Phase 3G privacy-minimized view logging.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Records one bounded view identity per configured UTC date window. */
final class ViewService {
	/**
	 * Record a direct visible post view.
	 *
	 * @param int                      $post_id Post ID.
	 * @param int                      $user_id Optional current session user ID.
	 * @param array<string,mixed>|null $server Optional server environment for tests.
	 * @return array<string,mixed>
	 */
	public static function record( $post_id, $user_id = 0, $server = null ) {
		if ( ! Phase3FeatureSettings::enabled( 'view_logging_enabled' ) ) {
			return InteractionResult::success( 'view_logging_disabled', array( 'counted' => false ), 'View logging disabled.', 200 );
		}

		$post_id = self::positive_id( $post_id );
		$current = function_exists( 'get_current_user_id' ) ? self::non_negative_id( get_current_user_id() ) : 0;
		if ( $user_id && self::positive_id( $user_id ) !== $current ) {
			return InteractionResult::error( 'view_identity_mismatch', 'The view identity is unavailable.', array(), 403 );
		}
		$user_id = $current;

		if ( $post_id <= 0 || ( function_exists( 'get_post_status' ) && 'publish' !== get_post_status( $post_id ) ) || ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
			return InteractionResult::error( 'post_unavailable', 'The requested post is unavailable.', array(), 404 );
		}

		$server = is_array( $server ) ? $server : ( isset( $_SERVER ) && is_array( $_SERVER ) ? $_SERVER : array() );
		if ( self::request_ignored( $server ) ) {
			return InteractionResult::success( 'view_ignored', array( 'counted' => false ), 'View ignored.', 200 );
		}

		$allowed = true;
		if ( function_exists( 'apply_filters' ) ) {
			$allowed = (bool) apply_filters( 'sabri_feed_should_count_view', true, $post_id, $user_id, $server );
		}
		if ( ! $allowed ) {
			return InteractionResult::success( 'view_filtered', array( 'counted' => false ), 'View ignored.', 200 );
		}

		$anonymous_hash = '';
		if ( 0 === $user_id ) {
			$anonymous_hash = self::anonymous_hash_for_request( $server );
			if ( '' === $anonymous_hash ) {
				return InteractionResult::success( 'anonymous_view_unidentifiable', array( 'counted' => false ), 'Anonymous view was not identifiable.', 200 );
			}
		}

		$now        = self::now();
		$view_date  = gmdate( 'Y-m-d', $now );
		$days       = self::deduplication_days();
		$start_date = gmdate( 'Y-m-d', $now - ( ( $days - 1 ) * 86400 ) );
		$existing   = ViewQueryRepository::identity_record( $post_id, $user_id, $anonymous_hash, $start_date, $view_date );
		if ( is_array( $existing ) ) {
			return InteractionResult::success(
				'view_already_counted',
				array(
					'counted'   => false,
					'duplicate' => true,
					'post_id'   => $post_id,
				),
				'View already counted.',
				200
			);
		}

		$inserted = InteractionRepository::insert_row(
			'views',
			array(
				'post_id'        => $post_id,
				'user_id'        => $user_id,
				'anonymous_hash' => $anonymous_hash,
				'view_date'      => $view_date,
				'view_count'     => 1,
				'status'         => 'counted',
			)
		);
		if ( empty( $inserted['ok'] ) ) {
			$race = ViewQueryRepository::identity_record( $post_id, $user_id, $anonymous_hash, $start_date, $view_date );
			if ( ! is_array( $race ) ) {
				return $inserted;
			}
			return InteractionResult::success( 'view_already_counted', array( 'counted' => false, 'duplicate' => true, 'post_id' => $post_id ), 'View already counted.', 200 );
		}

		EngagementService::invalidate( $post_id );
		if ( function_exists( 'do_action' ) ) {
			do_action( 'sabri_feed_view_recorded', $post_id, $user_id > 0 );
		}

		return InteractionResult::success(
			'view_counted',
			array(
				'counted' => true,
				'post_id' => $post_id,
			),
			'View counted.',
			201
		);
	}

	/** Public aggregate count; no viewer identities are returned. */
	public static function count( $post_id ) {
		$post_id = self::positive_id( $post_id );
		if ( $post_id <= 0 || ! Phase3FeatureSettings::enabled( 'view_logging_enabled' ) || ! PostMetadata::user_can_view( $post_id ) ) {
			return 0;
		}
		return ViewQueryRepository::aggregate_count( $post_id );
	}

	/** Privacy-safe HMAC for a guest request. */
	public static function anonymous_hash_for_request( array $server ) {
		$settings  = Settings::get();
		$anonymize = ! isset( $settings['privacy']['anonymize_views'] ) || ! empty( $settings['privacy']['anonymize_views'] );
		if ( ! $anonymize ) {
			return '';
		}

		$ip = isset( $server['REMOTE_ADDR'] ) ? trim( (string) $server['REMOTE_ADDR'] ) : '';
		if ( '' === $ip || false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		$agent = isset( $server['HTTP_USER_AGENT'] ) ? substr( trim( (string) $server['HTTP_USER_AGENT'] ), 0, 255 ) : '';
		$salt  = function_exists( 'wp_salt' ) ? wp_salt( 'nonce' ) : SABRI_HNF_SLUG;
		return hash_hmac( 'sha256', $ip . '|' . $agent, (string) $salt );
	}

	/** Respect DNT and exclude obvious automated agents. */
	public static function request_ignored( array $server ) {
		if ( isset( $server['HTTP_DNT'] ) && '1' === trim( (string) $server['HTTP_DNT'] ) ) {
			return true;
		}
		$agent = isset( $server['HTTP_USER_AGENT'] ) ? strtolower( (string) $server['HTTP_USER_AGENT'] ) : '';
		if ( '' === $agent ) {
			return false;
		}
		return (bool) preg_match( '/bot|crawler|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegrambot|uptimerobot|monitoring|headlesschrome/i', $agent );
	}

	/** Filterable 1-30 day deduplication window. */
	public static function deduplication_days() {
		$settings = Settings::get();
		$days     = isset( $settings['performance']['view_deduplication_days'] ) ? (int) $settings['performance']['view_deduplication_days'] : 1;
		if ( function_exists( 'apply_filters' ) ) {
			$days = (int) apply_filters( 'sabri_feed_view_deduplication_days', $days );
		}
		return min( 30, max( 1, $days ) );
	}

	/** Filterable UTC timestamp for deterministic tests. */
	private static function now() {
		$now = time();
		return function_exists( 'apply_filters' ) ? max( 1, (int) apply_filters( 'sabri_feed_view_now', $now ) ) : max( 1, (int) $now );
	}

	/** Strict positive ID. */
	private static function positive_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/', (string) $value ) ? (int) $value : 0;
	}

	/** Strict non-negative ID. */
	private static function non_negative_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[0-9]+$/', (string) $value ) ? (int) $value : 0;
	}
}
