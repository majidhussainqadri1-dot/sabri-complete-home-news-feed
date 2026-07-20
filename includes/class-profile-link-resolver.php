<?php
/**
 * Profile URL bridge for Phase 3 social integrations.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves a safe profile URL without coupling this plugin to a Profiles repository.
 */
final class ProfileLinkResolver {
	/**
	 * Resolve a public profile URL for an existing WordPress user.
	 *
	 * Companion profile plugins may replace the fallback through the
	 * `sabri_feed_profile_url` filter. Invalid filtered values fail back to the
	 * WordPress author archive rather than exposing arbitrary content.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function url( $user_id ) {
		$user_id = self::positive_id( $user_id );
		if ( $user_id <= 0 || ( function_exists( 'get_userdata' ) && ! get_userdata( $user_id ) ) ) {
			return '';
		}

		$fallback = '';
		if ( function_exists( 'get_author_posts_url' ) ) {
			$fallback = (string) get_author_posts_url( $user_id );
		} elseif ( function_exists( 'home_url' ) ) {
			$fallback = (string) home_url( '/?author=' . $user_id );
		}

		$candidate = $fallback;
		if ( function_exists( 'apply_filters' ) ) {
			$candidate = apply_filters( 'sabri_feed_profile_url', $fallback, $user_id );
		}
		if ( ! is_scalar( $candidate ) ) {
			$candidate = $fallback;
		}

		$candidate = trim( (string) $candidate );
		if ( '' === $candidate ) {
			return '';
		}

		if ( function_exists( 'esc_url_raw' ) ) {
			$clean = esc_url_raw( $candidate );
			if ( '' !== $clean ) {
				return $clean;
			}
			$clean_fallback = esc_url_raw( $fallback );
			return '' !== $clean_fallback ? $clean_fallback : '';
		}

		return preg_match( '#^https?://#i', $candidate ) ? $candidate : $fallback;
	}

	/**
	 * Safe public display name.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function display_name( $user_id ) {
		$user_id = self::positive_id( $user_id );
		$user    = $user_id > 0 && function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : false;
		$name    = $user && isset( $user->display_name ) ? trim( (string) $user->display_name ) : '';
		$is_email = '' !== $name && ( ( function_exists( 'is_email' ) && is_email( $name ) ) || false !== filter_var( $name, FILTER_VALIDATE_EMAIL ) );
		return '' !== $name && ! $is_email ? $name : __( 'Sabri member', 'sabri-complete-home-news-feed' );
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
}
