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
					'args'                => self::route_args(),
					'sabri_action'        => $action,
				)
			);
		}
	}

	/**
	 * Route-level schema for composer requests.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private static function route_args() {
		return array(
			'post_id'                     => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_optional_positive_id' ), 'validate_callback' => array( __CLASS__, 'validate_optional_positive_id' ) ),
			'action'                      => array( 'sanitize_callback' => 'sanitize_key', 'validate_callback' => array( __CLASS__, 'validate_action' ) ),
			'composer_action'             => array( 'sanitize_callback' => 'sanitize_key', 'validate_callback' => array( __CLASS__, 'validate_action' ) ),
			'title'                       => array( 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( __CLASS__, 'validate_text' ) ),
			'content'                     => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_content' ), 'validate_callback' => array( __CLASS__, 'validate_textarea' ) ),
			'feed_type'                   => array( 'sanitize_callback' => 'sanitize_key', 'validate_callback' => array( __CLASS__, 'validate_feed_type' ) ),
			'topic'                       => array( 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( __CLASS__, 'validate_text' ) ),
			'visibility'                  => array( 'sanitize_callback' => 'sanitize_key', 'validate_callback' => array( __CLASS__, 'validate_visibility' ) ),
			'language'                    => array( 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( __CLASS__, 'validate_text' ) ),
			'country_region'              => array( 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( __CLASS__, 'validate_text' ) ),
			'country'                     => array( 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( __CLASS__, 'validate_text' ) ),
			'post_status'                 => array( 'sanitize_callback' => 'sanitize_key', 'validate_callback' => array( __CLASS__, 'validate_post_status' ) ),
			'scheduled_date'              => array( 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => array( __CLASS__, 'validate_optional_datetime' ) ),
			'attachments'                 => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_id_list' ), 'validate_callback' => array( __CLASS__, 'validate_id_list' ) ),
			'gallery'                     => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_id_list' ), 'validate_callback' => array( __CLASS__, 'validate_id_list' ) ),
			'clinical_case'               => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_structured_array' ), 'validate_callback' => array( __CLASS__, 'validate_clinical_case_args' ) ),
			'research'                    => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_structured_array' ), 'validate_callback' => array( __CLASS__, 'validate_research_args' ) ),
			'comments_enabled'            => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ), 'validate_callback' => array( __CLASS__, 'validate_boolish' ) ),
			'medical_disclaimer_confirmed' => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ), 'validate_callback' => array( __CLASS__, 'validate_boolish' ) ),
			'patient_privacy_confirmed'   => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ), 'validate_callback' => array( __CLASS__, 'validate_boolish' ) ),
		);
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
		$helpers_available = function_exists( 'wp_verify_nonce' ) && function_exists( 'sanitize_text_field' ) && function_exists( 'wp_unslash' );
		if ( function_exists( 'apply_filters' ) ) {
			$helpers_available = (bool) apply_filters( 'sabri_hnf_rest_nonce_helpers_available', $helpers_available );
		}
		if ( ! $helpers_available ) {
			return false;
		}

		$nonce = '';
		if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) );
		}

		return '' !== $nonce && (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Validate optional positive ID.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_optional_positive_id( $value ) {
		return '' === $value || null === $value || ( is_scalar( $value ) && preg_match( '/^[0-9]+$/', (string) $value ) && (int) $value > 0 );
	}

	/**
	 * Sanitize optional positive ID without converting negatives to positives.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function sanitize_optional_positive_id( $value ) {
		return self::validate_optional_positive_id( $value ) ? (int) $value : 0;
	}

	/**
	 * Validate action.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_action( $value ) {
		return '' === (string) $value || in_array( sanitize_key( $value ), array( 'draft', 'preview', 'submit', 'publish', 'schedule' ), true );
	}

	/**
	 * Validate text.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_text( $value ) {
		return is_scalar( $value ) && strlen( (string) $value ) <= 500;
	}

	/**
	 * Validate textarea.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_textarea( $value ) {
		return is_scalar( $value ) && strlen( (string) $value ) <= 20000;
	}

	/**
	 * Validate feed type.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_feed_type( $value ) {
		$settings = Settings::get();
		$allowed  = isset( $settings['composer']['allowed_feed_types'] ) && is_array( $settings['composer']['allowed_feed_types'] ) ? $settings['composer']['allowed_feed_types'] : FeedContext::phase2_feed_type_slugs();
		return '' === (string) $value || in_array( sanitize_key( $value ), $allowed, true );
	}

	/**
	 * Validate visibility.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_visibility( $value ) {
		return '' === (string) $value || in_array( sanitize_key( $value ), FeedContext::allowed_composer_visibility( null, true ), true );
	}

	/**
	 * Validate post status.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_post_status( $value ) {
		return '' === (string) $value || in_array( sanitize_key( $value ), array( 'draft', 'pending', 'publish', 'future' ), true );
	}

	/**
	 * Validate optional date/time.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_optional_datetime( $value ) {
		return '' === trim( (string) $value ) || false !== self::parse_datetime( $value );
	}

	/**
	 * Parse one accepted date/time format with round-trip validation.
	 *
	 * @param mixed $value Value.
	 * @return int|false
	 */
	public static function parse_datetime( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return false;
		}

		foreach ( self::accepted_datetime_formats() as $format ) {
			$date = \DateTimeImmutable::createFromFormat( '!' . $format, $value );
			$errors = \DateTimeImmutable::getLastErrors();
			$valid = is_array( $errors ) ? 0 === (int) $errors['warning_count'] && 0 === (int) $errors['error_count'] : true;
			if ( $date && $valid && $date->format( $format ) === $value ) {
				return $date->getTimestamp();
			}
		}

		return false;
	}

	/**
	 * Accepted scheduled-date formats.
	 *
	 * @return array<int,string>
	 */
	public static function accepted_datetime_formats() {
		return array( 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i' );
	}

	/**
	 * Sanitize ID list.
	 *
	 * @param mixed $value Value.
	 * @return array<int,int>
	 */
	public static function sanitize_id_list( $value ) {
		$items = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
		return array_values(
			array_filter(
				array_map(
					static function ( $item ) {
						return is_scalar( $item ) && preg_match( '/^[0-9]+$/', (string) $item ) ? (int) $item : 0;
					},
					(array) $items
				),
				static function ( $item ) {
					return $item > 0;
				}
			)
		);
	}

	/**
	 * Validate bounded ID list.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_id_list( $value ) {
		$raw_items = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
		foreach ( (array) $raw_items as $item ) {
			if ( '' === (string) $item ) {
				continue;
			}
			if ( ! is_scalar( $item ) || ! preg_match( '/^[0-9]+$/', (string) $item ) || (int) $item <= 0 ) {
				return false;
			}
		}
		$items = self::sanitize_id_list( $value );
		return count( $items ) <= 20 && count( $items ) === count( array_unique( $items ) );
	}

	/**
	 * Sanitize structured array.
	 *
	 * @param mixed $value Value.
	 * @return array<string,string>
	 */
	public static function sanitize_structured_array( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		foreach ( $value as $key => $item ) {
			$out[ sanitize_key( $key ) ] = is_scalar( $item ) ? sanitize_textarea_field( $item ) : '';
		}

		return $out;
	}

	/**
	 * Validate Clinical Case REST args.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_clinical_case_args( $value ) {
		return self::validate_structured_keys( $value, array_keys( ComposerValidation::clinical_fields() ) );
	}

	/**
	 * Validate Research REST args.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_research_args( $value ) {
		return self::validate_structured_keys( $value, array_merge( array_keys( ComposerValidation::research_fields() ), array( 'evidence_level' ) ) );
	}

	/**
	 * Validate structured keys and values.
	 *
	 * @param mixed      $value Value.
	 * @param array<int,string> $allowed Allowed keys.
	 * @return bool
	 */
	private static function validate_structured_keys( $value, array $allowed ) {
		if ( null === $value || '' === $value ) {
			return true;
		}
		if ( ! is_array( $value ) || count( $value ) > 40 ) {
			return false;
		}
		foreach ( $value as $key => $item ) {
			if ( ! in_array( sanitize_key( $key ), $allowed, true ) || ! is_scalar( $item ) || strlen( (string) $item ) > 5000 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitize boolean-like value.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function sanitize_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 1 : 0;
		}

		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( '1', 'true' ), true ) ? 1 : 0;
	}

	/**
	 * Validate boolean-like value.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_boolish( $value ) {
		return in_array( (string) $value, array( '', '0', '1', 'true', 'false' ), true ) || is_bool( $value ) || is_int( $value );
	}

	/**
	 * Sanitize composer content.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function sanitize_content( $value ) {
		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $value ) : strip_tags( (string) $value, '<p><br><strong><em><b><i><ul><ol><li><a><blockquote><code><pre>' );
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
