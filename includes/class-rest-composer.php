<?php
/**
 * REST composer endpoints.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides authenticated public composer REST actions.
 */
final class RestComposer {
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
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		foreach ( array( 'draft', 'preview', 'submit', 'publish', 'schedule' ) as $action ) {
			register_rest_route(
				RestFoundation::NAMESPACE,
				'/composer/' . $action,
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'handle' ),
					'permission_callback' => array( __CLASS__, 'permission_callback' ),
					'args'                => array(),
					'sabri_action'        => $action,
				)
			);
		}
	}

	/**
	 * Write permission callback.
	 *
	 * @return bool
	 */
	public static function permission_callback() {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		return ComposerPermissions::user_can_create( $user_id ) && self::rest_nonce_valid();
	}

	/**
	 * Handle a composer REST action.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function handle( $request ) {
		$params = self::request_params( $request );
		$route_action = self::route_action( $request );
		if ( $route_action ) {
			$params['composer_action'] = $route_action;
		}

		$result = Composer::create_or_update_from_request( $params, array() );
		if ( empty( $result['ok'] ) ) {
			return self::error_response( $result );
		}

		return self::response( $result, 200 );
	}

	/**
	 * Validate REST nonce when WordPress nonce helpers are available.
	 *
	 * @return bool
	 */
	private static function rest_nonce_valid() {
		if ( ! function_exists( 'wp_verify_nonce' ) ) {
			return true;
		}

		$nonce = '';
		if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
		}

		return '' !== $nonce && (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Response helper.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @param int                 $status Status.
	 * @return mixed
	 */
	private static function response( array $payload, $status ) {
		if ( class_exists( 'WP_REST_Response' ) ) {
			return new \WP_REST_Response( array( 'ok' => true, 'data' => $payload ), $status );
		}

		return array( 'ok' => true, 'data' => $payload, 'status' => $status );
	}

	/**
	 * Error response helper.
	 *
	 * @param array<string,mixed> $result Result.
	 * @return mixed
	 */
	private static function error_response( array $result ) {
		$status = isset( $result['status'] ) ? (int) $result['status'] : 400;
		if ( class_exists( 'WP_REST_Response' ) ) {
			return new \WP_REST_Response(
				array(
					'ok'      => false,
					'code'    => $result['code'],
					'message' => $result['message'],
					'details' => isset( $result['details'] ) ? $result['details'] : array(),
				),
				$status
			);
		}

		$result['ok'] = false;
		return $result;
	}

	/**
	 * Request params.
	 *
	 * @param mixed $request Request.
	 * @return array<string,mixed>
	 */
	private static function request_params( $request ) {
		if ( is_array( $request ) ) {
			return $request;
		}

		if ( is_object( $request ) && method_exists( $request, 'get_params' ) ) {
			$params = $request->get_params();
			return is_array( $params ) ? $params : array();
		}

		return array();
	}

	/**
	 * Extract route action.
	 *
	 * @param mixed $request Request.
	 * @return string
	 */
	private static function route_action( $request ) {
		if ( is_object( $request ) && method_exists( $request, 'get_route' ) ) {
			$route = (string) $request->get_route();
			$parts = explode( '/', trim( $route, '/' ) );
			return sanitize_key( end( $parts ) );
		}

		if ( is_array( $request ) && ! empty( $request['composer_action'] ) ) {
			return sanitize_key( $request['composer_action'] );
		}

		return '';
	}
}
