<?php
/**
 * REST foundation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers authenticated diagnostic routes only.
 */
final class RestFoundation {
	const NAMESPACE = 'sabri-home-news-feed/v1';

	/**
	 * Register REST hooks.
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
			self::NAMESPACE,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'status' ),
				'permission_callback' => array( __CLASS__, 'permission_callback' ),
				'args'                => array(),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/schema',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'schema' ),
				'permission_callback' => array( __CLASS__, 'permission_callback' ),
				'args'                => array(),
			)
		);
	}

	/**
	 * REST permission callback.
	 *
	 * @return bool
	 */
	public static function permission_callback() {
		return function_exists( 'current_user_can' ) && ( current_user_can( 'sabri_feed_manage_settings' ) || current_user_can( 'manage_options' ) );
	}

	/**
	 * Status response.
	 *
	 * @return mixed
	 */
	public static function status() {
		return self::response(
			array(
				'identity'           => Plugin::identity(),
				'safe_mode_active'   => SafeMode::query_safe_mode(),
				'emergency_disabled' => SafeMode::emergency_disabled(),
				'migration_status'   => SystemCheck::migration_status(),
				'snapshot_status'    => SystemCheck::snapshot_status(),
				'integrations'       => Integrations::detect(),
			)
		);
	}

	/**
	 * Schema diagnostic response.
	 *
	 * @return mixed
	 */
	public static function schema() {
		return self::response(
			array(
				'schema_version' => SABRI_HNF_SCHEMA_VERSION,
				'tables'         => Database::table_names(),
				'indexes'        => Database::expected_indexes(),
				'statuses'       => Database::allowed_statuses(),
			)
		);
	}

	/**
	 * Build a structured response.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return mixed
	 */
	private static function response( array $payload ) {
		if ( class_exists( 'WP_REST_Response' ) ) {
			return new \WP_REST_Response(
				array(
					'ok'   => true,
					'data' => $payload,
				),
				200
			);
		}

		return array(
			'ok'   => true,
			'data' => $payload,
		);
	}
}
