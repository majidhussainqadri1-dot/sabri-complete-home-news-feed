<?php
/**
 * Public read-only Editorial News REST routes.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Exposes only allow-listed public News projections. */
final class RestNews {
	/** Register public read hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		}
	}

	/** Register exact GET-only routes. */
	public static function register_routes() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}
		register_rest_route(
			Phase4Contracts::REST_NAMESPACE,
			'/news',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'collection' ),
				'permission_callback' => array( __CLASS__, 'permission_callback' ),
				'args'                => self::collection_args(),
			)
		);
		register_rest_route(
			Phase4Contracts::REST_NAMESPACE,
			'/news/(?P<id>[1-9][0-9]*)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'single' ),
				'permission_callback' => array( __CLASS__, 'permission_callback' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => array( __CLASS__, 'sanitize_positive_int' ),
						'validate_callback' => array( __CLASS__, 'validate_positive_int' ),
					),
				),
			)
		);
	}

	/** Gate public REST reads. */
	public static function permission_callback() {
		return NewsPolicy::public_reads_allowed();
	}

	/** Collection callback. */
	public static function collection( $request ) {
		$args = array();
		foreach ( array_keys( self::collection_args() ) as $key ) {
			$value = self::request_param( $request, $key );
			if ( null !== $value && '' !== $value ) {
				$args[ $key ] = $value;
			}
		}
		$result = NewsQueryService::query( $args );
		return self::response_from_result( $result );
	}

	/** Single callback. */
	public static function single( $request ) {
		$result = NewsQueryService::single( self::request_param( $request, 'id' ) );
		return self::response_from_result( $result );
	}

	/** REST argument schema. */
	public static function collection_args() {
		$slug = array(
			'sanitize_callback' => array( __CLASS__, 'sanitize_slug' ),
			'validate_callback' => array( __CLASS__, 'validate_slug' ),
		);
		return array(
			'keyword'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'q'         => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'section'   => $slug,
			'topic'     => $slug,
			'country'   => $slug,
			'region'    => $slug,
			'type'      => $slug,
			'date_from' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'date_to'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'author'    => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_positive_int' ) ),
			'research'  => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_boolean' ) ),
			'corrected' => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_boolean' ) ),
			'retracted' => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_boolean' ) ),
			'page'      => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_positive_int' ) ),
			'per_page'  => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_positive_int' ) ),
		);
	}

	/** Strict positive integer validation. */
	public static function validate_positive_int( $value ) {
		return is_int( $value ) || ( is_string( $value ) && preg_match( '/^[1-9][0-9]*$/D', $value ) );
	}

	/** Strict positive integer sanitization. */
	public static function sanitize_positive_int( $value ) {
		return self::validate_positive_int( $value ) ? (int) $value : 0;
	}

	/** Strict slug validation. */
	public static function validate_slug( $value ) {
		return '' === (string) $value || ( is_string( $value ) && strlen( $value ) <= 120 && preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) );
	}

	/** Strict slug sanitization without repairing invalid input. */
	public static function sanitize_slug( $value ) {
		return self::validate_slug( $value ) ? (string) $value : '';
	}

	/** Strict boolean sanitization. */
	public static function sanitize_boolean( $value ) {
		return in_array( $value, array( true, 1, '1' ), true ) ? 1 : 0;
	}

	/** Map stable service results to REST responses. */
	private static function response_from_result( array $result ) {
		$status = isset( $result['status'] ) ? (int) $result['status'] : 500;
		$payload = ! empty( $result['success'] )
			? array( 'ok' => true, 'data' => $result['data'] )
			: array(
				'ok'      => false,
				'code'    => isset( $result['code'] ) ? $result['code'] : 'news_error',
				'message' => isset( $result['message'] ) ? $result['message'] : __( 'The News request could not be completed.', 'sabri-complete-home-news-feed' ),
				'field'   => isset( $result['field'] ) ? $result['field'] : '',
			);
		if ( class_exists( 'WP_REST_Response' ) ) {
			$response = new \WP_REST_Response( $payload, $status );
			if ( method_exists( $response, 'header' ) ) {
				$response->header( 'Cache-Control', 200 === $status ? 'public, max-age=60, s-maxage=60' : 'no-store' );
				$response->header( 'X-Content-Type-Options', 'nosniff' );
			}
			return $response;
		}
		return array( 'status' => $status, 'payload' => $payload );
	}

	/** Read one request parameter. */
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
