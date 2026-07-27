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

/** Stores corrective public mounting choices independently and fail closed. */
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
		if ( ! function_exists( 'register_setting' ) ) {
			return;
		}
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

	/** Safe defaults. Version 1.0.2 recovery enables only read-only surfaces when no wizard decision exists. */
	public static function defaults() {
		return array(
			'home_surface_enabled'          => 0,
			'profile_timeline_enabled'      => 0,
			'distinct_surface_marker'       => 1,
			'duplicate_feed_guard'          => 1,
			'replace_existing_feed_surface' => 0,
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

	/** Read a complete fail-closed projection. */
	public static function get() {
		$current = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();
		return self::sanitize( is_array( $current ) ? array_merge( self::defaults(), $current ) : self::defaults() );
	}

	/** Patch selected fields without disabling omitted settings. */
	public static function patch( array $patch ) {
		$current = self::get();
		$allowed = array_intersect_key( $patch, self::defaults() );
		$next    = self::sanitize( array_merge( $current, $allowed ) );
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_NAME, $next, false );
		}
		return $next;
	}

	/** Exact setting lookup. */
	public static function enabled( $key ) {
		$defaults = self::defaults();
		if ( ! is_string( $key ) || ! array_key_exists( $key, $defaults ) ) {
			return false;
		}
		$current = self::get();
		return isset( $current[ $key ] ) && 1 === $current[ $key ];
	}
}
