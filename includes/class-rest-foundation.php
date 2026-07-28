<?php
/**
 * REST foundation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Registers authenticated diagnostic routes only. */
final class RestFoundation {
	const NAMESPACE = 'sabri-home-news-feed/v1';

	/** Register full-runtime REST hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) { add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) ); }
	}

	/** Register minimal authenticated diagnostics while Safe Boot pauses runtime. */
	public static function register_safe_boot_routes() {
		if ( function_exists( 'add_action' ) ) { add_action( 'rest_api_init', array( __CLASS__, 'register_safe_boot_route_definitions' ) ); }
	}

	/** Register only status/schema routes that do not initialize plugin services. */
	public static function register_safe_boot_route_definitions() {
		if ( ! function_exists( 'register_rest_route' ) ) { return; }
		register_rest_route( self::NAMESPACE, '/status', array(
			'methods' => 'GET', 'callback' => array( __CLASS__, 'safe_boot_status' ),
			'permission_callback' => array( __CLASS__, 'permission_callback' ), 'args' => array(),
		) );
		register_rest_route( self::NAMESPACE, '/schema', array(
			'methods' => 'GET', 'callback' => array( __CLASS__, 'safe_boot_schema' ),
			'permission_callback' => array( __CLASS__, 'permission_callback' ), 'args' => array(),
		) );
	}

	/** Minimal Safe Boot status projection. */
	public static function safe_boot_status() {
		$state = class_exists( __NAMESPACE__ . '\\SafeBoot' ) && method_exists( SafeBoot::class, 'state' ) ? SafeBoot::state() : array();
		return self::response( array(
			'identity' => array( 'slug' => SABRI_HNF_SLUG, 'version' => SABRI_HNF_VERSION, 'schema_version' => SABRI_HNF_SCHEMA_VERSION ),
			'runtime_paused' => true,
			'safe_boot' => is_array( $state ) ? $state : array(),
		) );
	}

	/** Minimal Safe Boot schema projection. */
	public static function safe_boot_schema() {
		return self::response( array( 'schema_version' => SABRI_HNF_SCHEMA_VERSION, 'runtime_paused' => true, 'tables' => array(), 'indexes' => array(), 'statuses' => array() ) );
	}

	/** Register full-runtime routes. */
	public static function register_routes() {
		if ( ! function_exists( 'register_rest_route' ) ) { return; }
		register_rest_route( self::NAMESPACE, '/status', array(
			'methods' => 'GET', 'callback' => array( __CLASS__, 'status' ),
			'permission_callback' => array( __CLASS__, 'permission_callback' ), 'args' => array(),
		) );
		register_rest_route( self::NAMESPACE, '/schema', array(
			'methods' => 'GET', 'callback' => array( __CLASS__, 'schema' ),
			'permission_callback' => array( __CLASS__, 'permission_callback' ), 'args' => array(),
		) );
	}

	/** REST permission callback. */
	public static function permission_callback() {
		if ( ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() ) { return false; }
		return function_exists( 'current_user_can' ) && ( current_user_can( 'sabri_feed_manage_settings' ) || current_user_can( 'manage_options' ) );
	}

	/** Full-runtime status response. */
	public static function status() {
		return self::response( array(
			'identity'           => Plugin::identity(),
			'safe_mode_active'   => SafeMode::query_safe_mode(),
			'emergency_disabled' => SafeMode::emergency_disabled(),
			'migration_status'   => SystemCheck::migration_status(),
			'snapshot_status'    => SystemCheck::snapshot_status(),
			'integrations'       => Integrations::detect(),
		) );
	}

	/** Full-runtime schema diagnostic response. */
	public static function schema() {
		return self::response( array(
			'schema_version' => SABRI_HNF_SCHEMA_VERSION,
			'tables'         => Database::table_names(),
			'indexes'        => Database::expected_indexes(),
			'statuses'       => Database::allowed_statuses(),
		) );
	}

	/** Build a structured response. */
	private static function response( array $payload ) {
		if ( class_exists( 'WP_REST_Response' ) ) {
			return new \WP_REST_Response( array( 'ok' => true, 'data' => $payload ), 200 );
		}
		return array( 'ok' => true, 'data' => $payload );
	}
}
