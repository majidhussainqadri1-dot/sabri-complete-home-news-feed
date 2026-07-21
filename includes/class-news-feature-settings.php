<?php
/**
 * Phase 4 Editorial News feature settings.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stores Phase 4 gates separately from Phase 2 and Phase 3 options. */
final class NewsFeatureSettings {
	const OPTION_NAME = 'sabri_feed_phase4_features';

	/** Register settings hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
			add_action( 'updated_option', array( __CLASS__, 'handle_option_update' ), 10, 3 );
		}
	}

	/** Register the dedicated option. */
	public static function register_setting() {
		if ( function_exists( 'register_setting' ) ) {
			register_setting(
				'sabri_feed_phase4_features',
				self::OPTION_NAME,
				array(
					'type'              => 'array',
					'default'           => self::defaults(),
					'sanitize_callback' => array( __CLASS__, 'sanitize' ),
					'show_in_rest'      => false,
				)
			);
		}
	}

	/** Return frozen disabled defaults. */
	public static function defaults() {
		return Phase4Contracts::feature_flags();
	}

	/** Ensure the option exists without enabling any gate. */
	public static function ensure_defaults() {
		$current = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, null ) : null;
		if ( null === $current || false === $current || ! is_array( $current ) ) {
			$defaults = self::defaults();
			if ( function_exists( 'update_option' ) ) {
				update_option( self::OPTION_NAME, $defaults, false );
			}
			return $defaults;
		}

		$clean = self::sanitize( $current );
		if ( $clean !== $current && function_exists( 'update_option' ) ) {
			update_option( self::OPTION_NAME, $clean, false );
		}
		return $clean;
	}

	/** Read a complete fail-closed option projection. */
	public static function get() {
		$current = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();
		return self::sanitize( is_array( $current ) ? $current : array() );
	}

	/**
	 * Sanitize a complete settings-form projection.
	 *
	 * Only the exact scalar values 1, "1", and true enable a gate. Missing keys
	 * are disabled for WordPress checkbox-form compatibility. Arrays, objects,
	 * floats, whitespace-padded values, and numeric prefixes fail closed.
	 */
	public static function sanitize( $value ) {
		$value = is_array( $value ) ? $value : array();
		$clean = array();
		foreach ( self::defaults() as $key => $default ) {
			unset( $default );
			$clean[ $key ] = array_key_exists( $key, $value ) && in_array( $value[ $key ], array( 1, '1', true ), true ) ? 1 : 0;
		}
		return $clean;
	}

	/** Whether an exact known gate is enabled and central safety controls are clear. */
	public static function enabled( $feature ) {
		$flags = self::defaults();
		if ( ! is_string( $feature ) || ! array_key_exists( $feature, $flags ) ) {
			return false;
		}
		if ( class_exists( __NAMESPACE__ . '\\SafeMode' ) && SafeMode::public_features_disabled() ) {
			return false;
		}
		$current = self::get();
		return isset( $current[ $feature ] ) && 1 === $current[ $feature ];
	}

	/**
	 * Apply a strict programmatic patch without disabling omitted gates.
	 *
	 * Admin settings forms continue to call sanitize() as a complete projection;
	 * this method is intentionally patch-oriented for internal services.
	 */
	public static function update( array $value ) {
		$old     = self::get();
		$allowed = array_intersect_key( $value, self::defaults() );
		$clean   = self::sanitize( array_merge( $old, $allowed ) );
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_NAME, $clean, false );
		}
		self::schedule_rewrite_if_changed( $old, $clean );
		return $clean;
	}

	/** Flag rewrite refresh when public routing-related gates change externally. */
	public static function handle_option_update( $option, $old_value, $value ) {
		if ( self::OPTION_NAME !== $option ) {
			return;
		}
		self::schedule_rewrite_if_changed( self::sanitize( $old_value ), self::sanitize( $value ) );
	}

	/** Schedule rewrite repair only when a route-producing gate changes. */
	private static function schedule_rewrite_if_changed( array $old_value, array $new_value ) {
		$routing_gates = array( 'editorial_news_enabled', 'news_rss_enabled' );
		foreach ( $routing_gates as $gate ) {
			$old = isset( $old_value[ $gate ] ) ? (int) $old_value[ $gate ] : 0;
			$new = isset( $new_value[ $gate ] ) ? (int) $new_value[ $gate ] : 0;
			if ( $old !== $new ) {
				if ( function_exists( 'update_option' ) ) {
					update_option( 'sabri_feed_flush_rewrite_rules', 1, false );
				}
				return;
			}
		}
	}
}
