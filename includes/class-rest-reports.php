<?php
/**
 * Phase 3E REST report and moderation endpoints.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers confidential report submission and moderator-only queue routes.
 */
final class RestReports {
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
			'/reports',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create' ),
				'permission_callback' => array( __CLASS__, 'create_permission' ),
				'args'                => array(
					'object_type' => array( 'required' => true, 'sanitize_callback' => array( __CLASS__, 'sanitize_key_value' ), 'validate_callback' => array( __CLASS__, 'validate_object_type' ) ),
					'object_id'   => array( 'required' => true, 'sanitize_callback' => array( __CLASS__, 'sanitize_id' ), 'validate_callback' => array( __CLASS__, 'validate_id' ) ),
					'reason'      => array( 'required' => true, 'sanitize_callback' => array( __CLASS__, 'sanitize_key_value' ), 'validate_callback' => array( __CLASS__, 'validate_reason' ) ),
					'note'        => array( 'required' => false, 'sanitize_callback' => array( __CLASS__, 'sanitize_reporter_note' ) ),
				),
			)
		);

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/moderation/reports',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'queue' ),
				'permission_callback' => array( __CLASS__, 'moderator_permission' ),
				'args'                => array(
					'status'      => array( 'required' => false, 'sanitize_callback' => array( __CLASS__, 'sanitize_key_value' ) ),
					'reason'      => array( 'required' => false, 'sanitize_callback' => array( __CLASS__, 'sanitize_key_value' ) ),
					'object_type' => array( 'required' => false, 'sanitize_callback' => array( __CLASS__, 'sanitize_key_value' ) ),
					'page'        => array( 'default' => 1, 'sanitize_callback' => array( __CLASS__, 'sanitize_page' ), 'validate_callback' => array( __CLASS__, 'validate_page' ) ),
					'per_page'    => array( 'default' => 25, 'sanitize_callback' => array( __CLASS__, 'sanitize_limit' ), 'validate_callback' => array( __CLASS__, 'validate_limit' ) ),
				),
			)
		);

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/moderation/reports/(?P<id>\d+)',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( __CLASS__, 'moderate' ),
				'permission_callback' => array( __CLASS__, 'moderator_permission' ),
				'args'                => array(
					'id'             => array( 'required' => true, 'sanitize_callback' => array( __CLASS__, 'sanitize_id' ), 'validate_callback' => array( __CLASS__, 'validate_id' ) ),
					'status'         => array( 'required' => true, 'sanitize_callback' => array( __CLASS__, 'sanitize_key_value' ), 'validate_callback' => array( __CLASS__, 'validate_status' ) ),
					'moderator_note' => array( 'required' => false, 'sanitize_callback' => array( __CLASS__, 'sanitize_moderator_note' ) ),
				),
			)
		);
	}

	/**
	 * Public submission permission.
	 *
	 * @param mixed $request Request.
	 * @return bool
	 */
	public static function create_permission( $request ) {
		return Phase3FeatureSettings::enabled( 'reports_enabled' )
			&& function_exists( 'is_user_logged_in' )
			&& is_user_logged_in()
			&& InteractionPermissions::nonce_valid( self::request_nonce( $request ) );
	}

	/**
	 * Moderator permission. Existing reports remain manageable even when new reports are disabled.
	 *
	 * @param mixed $request Request.
	 * @return bool
	 */
	public static function moderator_permission( $request ) {
		return function_exists( 'is_user_logged_in' )
			&& is_user_logged_in()
			&& InteractionPermissions::nonce_valid( self::request_nonce( $request ) )
			&& InteractionPermissions::can_manage_reports();
	}

	/**
	 * Create callback.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function create( $request ) {
		return self::response(
			ReportService::create(
				self::request_param( $request, 'object_type' ),
				self::request_param( $request, 'object_id' ),
				self::request_param( $request, 'reason' ),
				self::request_param( $request, 'note' ),
				self::request_nonce( $request )
			)
		);
	}

	/**
	 * Queue callback.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function queue( $request ) {
		return self::response(
			ReportService::queue(
				array(
					'status'      => self::request_param( $request, 'status' ),
					'reason'      => self::request_param( $request, 'reason' ),
					'object_type' => self::request_param( $request, 'object_type' ),
					'page'        => self::sanitize_page( self::request_param( $request, 'page' ) ),
					'per_page'    => self::sanitize_limit( self::request_param( $request, 'per_page' ) ),
				)
			)
		);
	}

	/**
	 * Moderate callback.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function moderate( $request ) {
		return self::response(
			ReportService::moderate(
				self::request_param( $request, 'id' ),
				self::request_param( $request, 'status' ),
				self::request_param( $request, 'moderator_note' )
			)
		);
	}

	/** Validate strict positive ID. */
	public static function validate_id( $value ) { return is_scalar( $value ) && preg_match( '/^[0-9]+$/', (string) $value ) && (int) $value > 0; }
	/** Sanitize strict positive ID. */
	public static function sanitize_id( $value ) { return self::validate_id( $value ) ? (int) $value : 0; }
	/** Validate object type. */
	public static function validate_object_type( $value ) { return ReportPolicy::object_type_allowed( $value ); }
	/** Validate reason. */
	public static function validate_reason( $value ) { return ReportPolicy::reason_allowed( $value ); }
	/** Validate status. */
	public static function validate_status( $value ) { return ReportPolicy::state_allowed( $value ); }
	/** Sanitize key. */
	public static function sanitize_key_value( $value ) { return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ); }
	/** Sanitize reporter note. */
	public static function sanitize_reporter_note( $value ) { return ReportPolicy::reporter_note( $value ); }
	/** Sanitize moderator note. */
	public static function sanitize_moderator_note( $value ) { return ReportPolicy::moderator_note( $value ); }
	/** Validate page. */
	public static function validate_page( $value ) { return is_scalar( $value ) && preg_match( '/^[0-9]+$/', (string) $value ) && (int) $value >= 1; }
	/** Sanitize page. */
	public static function sanitize_page( $value ) { return self::validate_page( $value ) ? (int) $value : 1; }
	/** Validate per-page. */
	public static function validate_limit( $value ) { return is_scalar( $value ) && preg_match( '/^[0-9]+$/', (string) $value ) && (int) $value >= 1 && (int) $value <= 100; }
	/** Sanitize per-page. */
	public static function sanitize_limit( $value ) { return self::validate_limit( $value ) ? (int) $value : 25; }

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
	 * Build a no-store private response.
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
