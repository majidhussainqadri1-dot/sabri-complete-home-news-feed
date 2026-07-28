<?php
/**
 * File 21 corrective public-component settings.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stores public mounting choices without enabling Editorial News gates. */
final class CorrectivePublicSettings {
	const OPTION_NAME = 'sabri_hnf_corrective_public_components';

	/** Register settings. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		}
	}

	/** Register the dedicated option. */
	public static function register_setting() {
		if ( ! function_exists( 'register_setting' ) ) { return; }
		register_setting(
			'sabri_hnf_corrective_public_components',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => self::defaults(),
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
	}

	/** Safe read-only defaults; Editorial News/write/migration gates remain separate and fail closed. */
	public static function defaults() {
		return array(
			'home_surface_enabled'          => 1,
			'profile_timeline_enabled'      => 1,
			'distinct_surface_marker'       => 1,
			'duplicate_feed_guard'          => 1,
			'replace_existing_feed_surface' => 1,
			'duplicate_navigation_guard'    => 1,
			'read_only_surface_recovered'   => 0,
			'wizard_completed'              => 0,
		);
	}

	/** Strict complete-form sanitizer. */
	public static function sanitize( $value ) {
		$value = is_array( $value ) ? $value : array();
		$clean = array();
		foreach ( self::defaults() as $key => $unused ) {
			unset( $unused );
			$clean[ $key ] = array_key_exists( $key, $value ) && in_array( $value[ $key ], array( 1, '1', true ), true ) ? 1 : 0;
		}
		return $clean;
	}

	/** Return the stored projection without adding persistent runtime changes. */
	public static function stored() {
		$current = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, null ) : null;
		return is_array( $current ) ? self::sanitize( array_merge( self::defaults(), $current ) ) : self::defaults();
	}

	/** Read a complete projection. */
	public static function get() { return self::stored(); }

	/** Patch selected fields without disabling omitted settings. */
	public static function patch( array $patch ) {
		$current = self::stored();
		$allowed = array_intersect_key( $patch, self::defaults() );
		$next    = self::sanitize( array_merge( $current, $allowed ) );
		if ( function_exists( 'update_option' ) ) { update_option( self::OPTION_NAME, $next, false ); }
		return $next;
	}

	/**
	 * Exact setting lookup with a non-persistent compatibility layer.
	 *
	 * Older 1.0.2 installations may have stored all-zero corrective settings.
	 * Until a completed Wizard records an explicit decision, read-only Home,
	 * Profile Timeline and duplicate-protection behavior is enabled in memory.
	 * No public GET request writes options.
	 */
	public static function enabled( $key ) {
		$defaults = self::defaults();
		if ( ! is_string( $key ) || ! array_key_exists( $key, $defaults ) ) { return false; }
		$current = self::stored();
		$safe_runtime_defaults = array(
			'home_surface_enabled', 'profile_timeline_enabled', 'distinct_surface_marker',
			'duplicate_feed_guard', 'replace_existing_feed_surface', 'duplicate_navigation_guard',
		);
		if ( empty( $current['wizard_completed'] ) && in_array( $key, $safe_runtime_defaults, true ) ) { return true; }
		return isset( $current[ $key ] ) && 1 === $current[ $key ];
	}
}
