<?php
/**
 * Public REST projection for Profile Timeline.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Exposes only public-safe timeline data for File 22 progressive enhancement. */
final class RestProfileTimeline {
	const NAMESPACE = 'sabri-home-news-feed/v1';

	/** Register REST hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		}
	}

	/** Register the bounded GET route. */
	public static function register_routes() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		register_rest_route(
			self::NAMESPACE,
			'/profile-timeline/(?P<user_id>[1-9][0-9]*)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'timeline' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'user_id'  => array(
						'required'          => true,
						'validate_callback' => array( __CLASS__, 'validate_positive_int' ),
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'default'           => 1,
						'validate_callback' => array( __CLASS__, 'validate_positive_int' ),
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => 10,
						'validate_callback' => array( __CLASS__, 'validate_per_page' ),
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/** Return a public-safe response. */
	public static function timeline( $request ) {
		$user_id  = self::request_value( $request, 'user_id', 0 );
		$page     = self::request_value( $request, 'page', 1 );
		$per_page = self::request_value( $request, 'per_page', 10 );
		$result   = ProfileTimeline::query(
			$user_id,
			array(
				'page'     => $page,
				'per_page' => $per_page,
			)
		);

		$status = 'disabled' === $result['status'] ? 503 : 200;
		$body   = array(
			'ok'   => 200 === $status,
			'data' => $result,
		);
		return class_exists( 'WP_REST_Response' ) ? new \WP_REST_Response( $body, $status ) : $body;
	}

	/** Strict positive integer validation. */
	public static function validate_positive_int( $value ) {
		return is_int( $value ) ? $value > 0 : ( is_string( $value ) && preg_match( '/^[1-9][0-9]*$/D', $value ) );
	}

	/** Bounded page-size validation. */
	public static function validate_per_page( $value ) {
		return self::validate_positive_int( $value ) && (int) $value <= ProfileTimeline::MAX_PER_PAGE;
	}

	/** Read from array-like or WP_REST_Request input. */
	private static function request_value( $request, $key, $default ) {
		if ( is_array( $request ) && isset( $request[ $key ] ) ) {
			return absint( $request[ $key ] );
		}
		if ( is_object( $request ) && isset( $request[ $key ] ) ) {
			return absint( $request[ $key ] );
		}
		return absint( $default );
	}
}
