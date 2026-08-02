<?php
/**
 * Phase 3F poll REST endpoints.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers bounded vote mutations and aggregate-only results.
 */
final class RestPolls {
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
	 * Register poll routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/polls/(?P<id>\d+)/vote',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'vote' ),
					'permission_callback' => array( __CLASS__, 'private_permission' ),
					'args'                => array(
						'id'         => self::id_argument(),
						'option_key' => array(
							'required'          => true,
							'sanitize_callback' => array( __CLASS__, 'sanitize_option_key' ),
							'validate_callback' => array( __CLASS__, 'validate_option_key' ),
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'remove_vote' ),
					'permission_callback' => array( __CLASS__, 'private_permission' ),
					'args'                => array( 'id' => self::id_argument() ),
				),
			)
		);

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/polls/(?P<id>\d+)/results',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'results' ),
				'permission_callback' => array( __CLASS__, 'public_permission' ),
				'args'                => array( 'id' => self::id_argument() ),
			)
		);
	}

	/**
	 * Authenticated nonce permission.
	 *
	 * @param mixed $request Request.
	 * @return bool
	 */
	public static function private_permission( $request ) {
		return Phase3FeatureSettings::enabled( 'polls_enabled' )
			&& function_exists( 'is_user_logged_in' )
			&& is_user_logged_in()
			&& CanonicalIdentityAdapter::current_action_ready( (int) get_current_user_id() )
			&& InteractionPermissions::nonce_valid( self::request_nonce( $request ) );
	}

	/**
	 * Visible-poll read permission without exposing hidden posts.
	 *
	 * @param mixed $request Request.
	 * @return bool
	 */
	public static function public_permission( $request ) {
		$id = self::sanitize_id( self::request_param( $request, 'id' ) );
		return Phase3FeatureSettings::enabled( 'polls_enabled' ) && $id > 0 && PostMetadata::user_can_view( $id ) && PollPolicy::is_poll( $id );
	}

	/**
	 * Vote callback.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function vote( $request ) {
		$result = PollService::vote(
			self::sanitize_id( self::request_param( $request, 'id' ) ),
			self::sanitize_option_key( self::request_param( $request, 'option_key' ) ),
			self::request_nonce( $request )
		);
		return self::response_with_html( $result );
	}

	/**
	 * Remove vote callback.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function remove_vote( $request ) {
		$result = PollService::remove_vote(
			self::sanitize_id( self::request_param( $request, 'id' ) ),
			self::request_nonce( $request )
		);
		return self::response_with_html( $result );
	}

	/**
	 * Results callback.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function results( $request ) {
		$result = PollService::results( self::sanitize_id( self::request_param( $request, 'id' ) ) );
		return self::response_with_html( $result );
	}

	/**
	 * Validate positive ID.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/', (string) $value );
	}

	/**
	 * Sanitize positive ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function sanitize_id( $value ) {
		return self::validate_id( $value ) ? (int) $value : 0;
	}

	/**
	 * Validate bounded option key.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_option_key( $value ) {
		return is_scalar( $value ) && preg_match( '/^[A-Za-z0-9_-]{1,64}$/', (string) $value );
	}

	/**
	 * Sanitize option key.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function sanitize_option_key( $value ) {
		return self::validate_option_key( $value ) ? PollPolicy::option_key( $value ) : '';
	}

	/**
	 * ID argument.
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
	 * Add refreshed server-rendered poll HTML and build a response.
	 *
	 * @param array<string,mixed> $result Result.
	 * @return mixed
	 */
	private static function response_with_html( array $result ) {
		if ( ! empty( $result['ok'] ) && ! empty( $result['data']['post_id'] ) ) {
			$result['data']['html'] = PollRuntime::render_poll( (int) $result['data']['post_id'] );
		}
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
