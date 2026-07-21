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

/**
 * Stores Phase 4 gates separately from Phase 2 and Phase 3 options.
 */
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

	/** Sanitize recognized checkboxes only. */
	public static function sanitize( $value ) {
		$value = is_array( $value ) ? $value : array();
		$clean = array();
		foreach ( self::defaults() as $key => $default ) {
			unset( $default );
			$clean[ $key ] = isset( $value[ $key ] ) && 1 === (int) $value[ $key ] ? 1 : 0;
		}
		return $clean;
	}

	/** Whether a known gate is enabled and all central safety controls are clear. */
	public static function enabled( $feature ) {
		if ( class_exists( __NAMESPACE__ . '\\SafeMode' ) && SafeMode::public_features_disabled() ) {
			return false;
		}
		return Phase4Contracts::feature_enabled( $feature, self::get() );
	}

	/** Update gates through the same strict sanitizer. */
	public static function update( array $value ) {
		$old   = self::get();
		$clean = self::sanitize( $value );
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

	/** Schedule one non-destructive rewrite refresh when the effective option changes. */
	private static function schedule_rewrite_if_changed( array $old_value, array $new_value ) {
		if ( $old_value === $new_value || ! function_exists( 'update_option' ) ) {
			return;
		}
		update_option( 'sabri_feed_flush_rewrite_rules', 1, false );
	}
}
