<?php
/**
 * Phase 3D REST Follow and Following endpoints.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers bounded user follow mutations and the private Following list.
 */
final class RestFollows {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		}
	}

	/**
	 * Register Checkpoint 3D routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/users/(?P<id>\d+)/follow',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'follow' ),
					'permission_callback' => array( __CLASS__, 'private_permission' ),
					'args'                => array( 'id' => self::id_argument() ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'unfollow' ),
					'permission_callback' => array( __CLASS__, 'private_permission' ),
					'args'                => array( 'id' => self::id_argument() ),
				),
			)
		);

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/me/following',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'following' ),
				'permission_callback' => array( __CLASS__, 'private_permission' ),
				'args'                => array(
					'per_page' => array(
						'default'           => 100,
						'sanitize_callback' => array( __CLASS__, 'sanitize_limit' ),
						'validate_callback' => array( __CLASS__, 'validate_limit' ),
					),
				),
			)
		);
	}

	/**
	 * Current-session and REST-nonce permission, gated by the feature flag.
	 *
	 * @param mixed $request Request.
	 * @return bool
	 */
	public static function private_permission( $request ) {
		return Phase3FeatureSettings::enabled( 'follows_enabled' )
			&& function_exists( 'is_user_logged_in' )
			&& is_user_logged_in()
			&& CanonicalIdentityAdapter::current_action_ready( (int) get_current_user_id() )
			&& InteractionPermissions::nonce_valid( self::request_nonce( $request ) );
	}

	/**
	 * Follow callback.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function follow( $request ) {
		return self::response( FollowService::follow( self::request_id( $request ), self::request_nonce( $request ) ) );
	}

	/**
	 * Unfollow callback.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function unfollow( $request ) {
		return self::response( FollowService::unfollow( self::request_id( $request ), self::request_nonce( $request ) ) );
	}

	/**
	 * Private Following list callback.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function following( $request ) {
		return self::response(
			FollowService::following(
				self::request_nonce( $request ),
				0,
				self::sanitize_limit( self::request_param( $request, 'per_page' ) )
			)
		);
	}

	/**
	 * Validate a positive ID.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[0-9]+$/', (string) $value ) && (int) $value > 0;
	}

	/**
	 * Sanitize a positive ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function sanitize_id( $value ) {
		return self::validate_id( $value ) ? (int) $value : 0;
	}

	/**
	 * Validate list size.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_limit( $value ) {
		return is_scalar( $value ) && preg_match( '/^[0-9]+$/', (string) $value ) && (int) $value >= 1 && (int) $value <= 200;
	}

	/**
	 * Sanitize list size.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function sanitize_limit( $value ) {
		return self::validate_limit( $value ) ? (int) $value : 100;
	}

	/**
	 * ID argument contract.
	 *
	 * @return array<string,mixed>
	 */
	private static function id_argument() {
		return array(
			'required'          => true,
			'sanitize_callback' => array( __CLASS__, 'sanitize_id' ),
			'validate_callback' => array( __CLASS__, 'validate_id' ),
		);
	}

	/**
	 * Request ID.
	 *
	 * @param mixed $request Request.
	 * @return int
	 */
	private static function request_id( $request ) {
		return self::sanitize_id( self::request_param( $request, 'id' ) );
	}

	/**
	 * Request nonce.
	 *
	 * @param mixed $request Request.
	 * @return string
	 */
	private static function request_nonce( $request ) {
		if ( is_object( $request ) && method_exists( $request, 'get_header' ) ) {
			return sanitize_text_field( $request->get_header( 'X-WP-Nonce' ) );
		}
		if ( is_array( $request ) && isset( $request['_wpnonce'] ) ) {
			return sanitize_text_field( $request['_wpnonce'] );
		}
		return isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
	}

	/**
	 * Request parameter.
	 *
	 * @param mixed  $request Request.
	 * @param string $key Key.
	 * @return mixed
	 */
	private static function request_param( $request, $key ) {
		if ( is_array( $request ) && array_key_exists( $key, $request ) ) {
			return $request[ $key ];
		}
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			return $request->get_param( $key );
		}
		return null;
	}

	/**
	 * Build a no-store private REST response.
	 *
	 * @param array<string,mixed> $result Result.
	 * @return mixed
	 */
	private static function response( array $result ) {
		$status = isset( $result['status'] ) ? (int) $result['status'] : 200;
		if ( class_exists( 'WP_REST_Response' ) ) {
			$response = new \WP_REST_Response( $result, $status );
			if ( method_exists( $response, 'header' ) ) {
				$response->header( 'Cache-Control', 'no-store, private' );
			}
			return $response;
		}
		return $result;
	}
}
