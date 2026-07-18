<?php
/**
 * Shared Phase 3 interaction authorization.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes authentication, nonce, visibility, ownership, and moderation checks.
 */
final class InteractionPermissions {
	const REST_NONCE_ACTION = 'wp_rest';

	/**
	 * Resolve an authenticated user ID without trusting request payloads.
	 *
	 * @param int $user_id Optional explicit user ID for internal service calls.
	 * @return int
	 */
	public static function authenticated_user_id( $user_id = 0 ) {
		$user_id = $user_id ? self::positive_id( $user_id ) : ( function_exists( 'get_current_user_id' ) ? self::positive_id( get_current_user_id() ) : 0 );
		if ( $user_id <= 0 ) {
			return 0;
		}

		if ( function_exists( 'get_userdata' ) && ! get_userdata( $user_id ) ) {
			return 0;
		}

		return $user_id;
	}

	/**
	 * Validate the WordPress REST nonce and fail closed when helpers are unavailable.
	 *
	 * @param string $nonce Optional explicit nonce.
	 * @return bool
	 */
	public static function nonce_valid( $nonce = '' ) {
		if ( '' === $nonce && isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = function_exists( 'wp_unslash' ) ? wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) : $_SERVER['HTTP_X_WP_NONCE'];
		}

		$nonce = self::clean_text( $nonce );
		if ( '' === $nonce || ! function_exists( 'wp_verify_nonce' ) ) {
			return false;
		}

		return false !== wp_verify_nonce( $nonce, self::REST_NONCE_ACTION );
	}

	/**
	 * Whether a user may see a post through the shared Phase 2 visibility service.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id Optional user ID.
	 * @return bool
	 */
	public static function can_view_post( $post_id, $user_id = 0 ) {
		$post_id = self::positive_id( $post_id );
		if ( $post_id <= 0 || ( function_exists( 'get_post' ) && ! get_post( $post_id ) ) ) {
			return false;
		}

		$user_id = $user_id ? self::positive_id( $user_id ) : ( function_exists( 'get_current_user_id' ) ? self::positive_id( get_current_user_id() ) : 0 );
		return PostMetadata::user_can_view( $post_id, $user_id );
	}

	/**
	 * Whether a logged-in user may perform a social write against a post.
	 *
	 * Pending, restricted, deleted, non-published, or invisible posts fail closed.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id Optional user ID.
	 * @return bool
	 */
	public static function can_interact_with_post( $post_id, $user_id = 0 ) {
		$user_id = self::authenticated_user_id( $user_id );
		$post_id = self::positive_id( $post_id );

		if ( $user_id <= 0 || $post_id <= 0 || SafeMode::public_features_disabled() ) {
			return false;
		}

		if ( function_exists( 'get_post_status' ) && 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}

		if ( ! PostMetadata::review_state_publicly_visible( $post_id ) ) {
			return false;
		}

		return self::can_view_post( $post_id, $user_id );
	}

	/**
	 * Require authentication, valid nonce, and post interaction permission.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $nonce REST nonce.
	 * @param int    $user_id Optional user ID.
	 * @return array<string,mixed>
	 */
	public static function authorize_post_write( $post_id, $nonce = '', $user_id = 0 ) {
		$user_id = self::authenticated_user_id( $user_id );
		if ( $user_id <= 0 ) {
			return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 );
		}

		if ( ! self::nonce_valid( $nonce ) ) {
			return InteractionResult::error( 'invalid_nonce', 'The security token is missing or invalid.', array(), 403 );
		}

		if ( ! self::can_interact_with_post( $post_id, $user_id ) ) {
			return InteractionResult::error( 'post_unavailable', 'The requested post is unavailable.', array(), 404 );
		}

		return InteractionResult::success(
			'authorized',
			array(
				'user_id' => $user_id,
				'post_id' => self::positive_id( $post_id ),
			),
			'Authorized.',
			200
		);
	}

	/**
	 * Whether the current authenticated user may manage confidential reports.
	 *
	 * Explicit user IDs other than the current user fail closed because WordPress
	 * capabilities are evaluated for the current session.
	 *
	 * @param int $user_id Optional user ID.
	 * @return bool
	 */
	public static function can_manage_reports( $user_id = 0 ) {
		$user_id    = self::authenticated_user_id( $user_id );
		$current_id = function_exists( 'get_current_user_id' ) ? self::positive_id( get_current_user_id() ) : 0;

		if ( $user_id <= 0 || $current_id <= 0 || $user_id !== $current_id || ! function_exists( 'current_user_can' ) ) {
			return false;
		}

		return current_user_can( 'sabri_feed_manage_reports' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Whether a private per-user resource belongs to the requesting user.
	 *
	 * @param int $owner_user_id Resource owner.
	 * @param int $request_user_id Requesting user.
	 * @return bool
	 */
	public static function owns_private_resource( $owner_user_id, $request_user_id = 0 ) {
		$owner_user_id   = self::positive_id( $owner_user_id );
		$request_user_id = self::authenticated_user_id( $request_user_id );
		return $owner_user_id > 0 && $owner_user_id === $request_user_id;
	}

	/**
	 * Normalize a positive integer ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private static function positive_id( $value ) {
		$value = function_exists( 'absint' ) ? absint( $value ) : abs( (int) $value );
		return $value > 0 ? $value : 0;
	}

	/**
	 * Sanitize a short token.
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
