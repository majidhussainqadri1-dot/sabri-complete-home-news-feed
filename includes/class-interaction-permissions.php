<?php
/**
 * Shared Phase 3 interaction authorization.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Centralizes authentication, nonce, visibility, ownership, and moderation checks. */
final class InteractionPermissions {
	const REST_NONCE_ACTION = 'wp_rest';

	public static function authenticated_user_id( $user_id = 0 ) {
		$current_id = function_exists( 'get_current_user_id' ) ? self::positive_id( get_current_user_id() ) : 0;
		if ( $current_id <= 0 ) { return 0; }
		$requested_id = $user_id ? self::positive_id( $user_id ) : $current_id;
		if ( $requested_id <= 0 || $requested_id !== $current_id ) { return 0; }
		if ( function_exists( 'get_userdata' ) && ! get_userdata( $current_id ) ) { return 0; }
		return $current_id;
	}

	public static function nonce_valid( $nonce = '' ) {
		if ( '' === $nonce && isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = function_exists( 'wp_unslash' ) ? wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) : $_SERVER['HTTP_X_WP_NONCE'];
		}
		$nonce = self::clean_text( $nonce );
		return '' !== $nonce && function_exists( 'wp_verify_nonce' ) && false !== wp_verify_nonce( $nonce, self::REST_NONCE_ACTION );
	}

	/** Whether a user may see a supported public interaction object. */
	public static function can_view_post( $post_id, $user_id = 0 ) {
		$post_id = self::positive_id( $post_id );
		if ( $post_id <= 0 || ( function_exists( 'get_post' ) && ! get_post( $post_id ) ) ) { return false; }
		if ( self::is_news_post( $post_id ) ) {
			return class_exists( __NAMESPACE__ . '\\NewsPolicy' ) && NewsPolicy::can_public_read( $post_id, 'single' );
		}
		$user_id = $user_id ? self::positive_id( $user_id ) : ( function_exists( 'get_current_user_id' ) ? self::positive_id( get_current_user_id() ) : 0 );
		return PostMetadata::user_can_view( $post_id, $user_id );
	}

	/** Whether a logged-in user may perform a social write against a supported object. */
	public static function can_interact_with_post( $post_id, $user_id = 0 ) {
		$user_id = self::authenticated_user_id( $user_id );
		$post_id = self::positive_id( $post_id );
		if ( $user_id <= 0 || $post_id <= 0 || SafeMode::public_features_disabled() ) { return false; }
		if ( self::is_news_post( $post_id ) ) {
			if ( ! NewsPolicy::can_public_read( $post_id, 'single' ) || 'retracted' === NewsPolicy::workflow_state( $post_id ) ) { return false; }
			return function_exists( 'get_post_status' ) ? 'publish' === get_post_status( $post_id ) : true;
		}
		if ( function_exists( 'get_post_status' ) && 'publish' !== get_post_status( $post_id ) ) { return false; }
		if ( ! PostMetadata::review_state_publicly_visible( $post_id ) ) { return false; }
		return self::can_view_post( $post_id, $user_id );
	}

	public static function authorize_post_write( $post_id, $nonce = '', $user_id = 0 ) {
		$user_id = self::authenticated_user_id( $user_id );
		if ( $user_id <= 0 ) { return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 ); }
		if ( ! self::nonce_valid( $nonce ) ) { return InteractionResult::error( 'invalid_nonce', 'The security token is missing or invalid.', array(), 403 ); }
		if ( ! self::can_interact_with_post( $post_id, $user_id ) ) { return InteractionResult::error( 'post_unavailable', 'The requested post is unavailable.', array(), 404 ); }
		return InteractionResult::success( 'authorized', array( 'user_id' => $user_id, 'post_id' => self::positive_id( $post_id ) ), 'Authorized.', 200 );
	}

	public static function can_manage_reports( $user_id = 0 ) {
		$user_id = self::authenticated_user_id( $user_id );
		return $user_id > 0 && function_exists( 'current_user_can' ) && ( current_user_can( 'sabri_feed_manage_reports' ) || current_user_can( 'manage_options' ) );
	}

	public static function owns_private_resource( $owner_user_id, $request_user_id = 0 ) {
		$owner_user_id = self::positive_id( $owner_user_id );
		$request_user_id = self::authenticated_user_id( $request_user_id );
		return $owner_user_id > 0 && $owner_user_id === $request_user_id;
	}

	private static function is_news_post( $post_id ) {
		return class_exists( __NAMESPACE__ . '\\Phase4Contracts' ) && function_exists( 'get_post_type' ) && Phase4Contracts::POST_TYPE === get_post_type( $post_id );
	}
	private static function positive_id( $value ) {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/', $value ) ) ) { return 0; }
		$value = (int) $value; return $value > 0 ? $value : 0;
	}
	private static function clean_text( $value ) { return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( (string) $value ) ); }
}
