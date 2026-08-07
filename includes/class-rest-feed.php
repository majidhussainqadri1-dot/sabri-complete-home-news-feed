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

/** Provides progressive-enhancement feed loading and private user-agency preferences. */
final class RestFeed {
	/** Register hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		}
	}

	/** Register routes. */
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

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/feed/preferences',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'preferences' ),
					'permission_callback' => array( __CLASS__, 'permission_callback' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'update_preferences' ),
					'permission_callback' => array( __CLASS__, 'permission_callback' ),
					'args'                => array(
						'action'   => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ),
						'value'    => array( 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
						'duration' => array( 'required' => false, 'sanitize_callback' => array( __CLASS__, 'sanitize_positive_int' ) ),
					),
				),
			)
		);
	}

	/** Public read registration; callbacks enforce authentication for private preferences. */
	public static function permission_callback() {
		return true;
	}

	/** Validate feed mode. */
	public static function validate_mode( $value ) {
		return '' === (string) $value || in_array( sanitize_key( $value ), FeedContext::enabled_modes(), true );
	}

	/** Validate page. */
	public static function validate_page( $value ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^[0-9]+$/', (string) $value ) ) {
			return false;
		}
		$value = (int) $value;
		return $value >= 1 && $value <= 1000;
	}

	/** Validate per-page. */
	public static function validate_per_page( $value ) {
		if ( ! is_scalar( $value ) || ! preg_match( '/^[0-9]+$/', (string) $value ) ) {
			return false;
		}
		$value = (int) $value;
		return $value >= 1 && $value <= 50;
	}

	/** Sanitize a positive integer without converting negatives to positives. */
	public static function sanitize_positive_int( $value ) {
		return is_scalar( $value ) && preg_match( '/^[0-9]+$/', (string) $value ) ? (int) $value : 0;
	}

	/** Feed callback. */
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

	/** Current user's private Feed preferences. */
	public static function preferences() {
		$user_id = InteractionPermissions::authenticated_user_id();
		if ( $user_id <= 0 ) {
			return self::result_response( InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 ) );
		}
		if ( ! CanonicalIdentityAdapter::current_action_ready( $user_id ) ) {
			return self::result_response( InteractionResult::error( 'identity_assurance_required', 'Current account assurance is required.', array(), 403 ) );
		}
		return self::response( array( 'preferences' => FeedUserAgency::preferences( $user_id ) ) );
	}

	/** Mutate one explicit private Feed preference. */
	public static function update_preferences( $request ) {
		$result = FeedUserAgency::update(
			self::request_param( $request, 'action' ),
			self::request_param( $request, 'value' ),
			self::request_param( $request, 'duration' ),
			'',
			0
		);
		return self::result_response( $result );
	}

	/** Response helper. */
	private static function response( array $payload ) {
		if ( class_exists( 'WP_REST_Response' ) ) {
			return new \WP_REST_Response( array( 'ok' => true, 'data' => $payload ), 200 );
		}
		return array( 'ok' => true, 'data' => $payload );
	}

	/** Convert InteractionResult to a REST response without losing status truth. */
	private static function result_response( array $result ) {
		$status = isset( $result['status'] ) ? (int) $result['status'] : ( ! empty( $result['ok'] ) ? 200 : 400 );
		if ( class_exists( 'WP_REST_Response' ) ) {
			return new \WP_REST_Response( $result, $status );
		}
		return $result;
	}

	/** Request param helper. */
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
