<?php
/**
 * REST Home Feed endpoint.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides progressive-enhancement feed loading.
 */
final class RestFeed {
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

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/feed',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'feed' ),
				'permission_callback' => array( __CLASS__, 'permission_callback' ),
				'args'                => array(
					'mode'     => array( 'sanitize_callback' => 'sanitize_key', 'validate_callback' => array( __CLASS__, 'validate_mode' ) ),
					'page'     => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_positive_int' ), 'validate_callback' => array( __CLASS__, 'validate_page' ) ),
					'per_page' => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_positive_int' ), 'validate_callback' => array( __CLASS__, 'validate_per_page' ) ),
				),
			)
		);
	}

	/**
	 * Public read permission callback.
	 *
	 * @return bool
	 */
	public static function permission_callback() {
		return true;
	}

	/**
	 * Validate feed mode.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_mode( $value ) {
		return '' === (string) $value || in_array( sanitize_key( $value ), FeedContext::enabled_modes(), true );
	}

	/**
	 * Validate page.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_page( $value ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^[0-9]+$/', (string) $value ) ) {
			return false;
		}
		$value = (int) $value;
		return $value >= 1 && $value <= 1000;
	}

	/**
	 * Validate per-page.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_per_page( $value ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^[0-9]+$/', (string) $value ) ) {
			return false;
		}
		$value = (int) $value;
		return $value >= 1 && $value <= 50;
	}

	/**
	 * Sanitize a positive integer without converting negatives to positives.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function sanitize_positive_int( $value ) {
		return is_scalar( $value ) && preg_match( '/^[0-9]+$/', (string) $value ) ? (int) $value : 0;
	}

	/**
	 * Feed callback.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function feed( $request ) {
		$args = array(
			'mode'     => self::request_param( $request, 'mode' ),
			'page'     => self::request_param( $request, 'page' ),
			'per_page' => self::request_param( $request, 'per_page' ),
		);

		$result = FeedQuery::query( $args );
		$html   = FeedRenderer::render_cards( $result['posts'], Settings::get() );

		return self::response(
			array(
				'mode'      => $result['mode'],
				'page'      => $result['page'],
				'has_more'  => $result['has_more'],
				'next_page' => $result['has_more'] ? (int) $result['page'] + 1 : null,
				'html'      => $html,
			)
		);
	}

	/**
	 * Response helper.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return mixed
	 */
	private static function response( array $payload ) {
		if ( class_exists( 'WP_REST_Response' ) ) {
			return new \WP_REST_Response( array( 'ok' => true, 'data' => $payload ), 200 );
		}

		return array( 'ok' => true, 'data' => $payload );
	}

	/**
	 * Request param helper.
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
}
