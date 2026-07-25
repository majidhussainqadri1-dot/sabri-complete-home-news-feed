<?php
/**
 * Phase 5 feature settings.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stores final-phase gates independently and fail closed. */
final class Phase5FeatureSettings {
	const OPTION_NAME = 'sabri_feed_phase5_features';

	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
			add_action( 'updated_option', array( __CLASS__, 'handle_update' ), 10, 3 );
		}
	}

	public static function register_setting() {
		if ( function_exists( 'register_setting' ) ) {
			register_setting(
				'sabri_feed_phase5_features',
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

	public static function defaults() {
		return Phase5Contracts::feature_flags();
	}

	public static function sanitize( $value ) {
		$value = is_array( $value ) ? $value : array();
		$clean = array();
		foreach ( self::defaults() as $key => $unused ) {
			unset( $unused );
			$clean[ $key ] = array_key_exists( $key, $value ) && Phase5Contracts::scalar_enabled( $value[ $key ] ) ? 1 : 0;
		}
		return $clean;
	}

	public static function ensure_defaults() {
		$current = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, null ) : null;
		if ( ! is_array( $current ) ) {
			$current = self::defaults();
			if ( function_exists( 'update_option' ) ) {
				update_option( self::OPTION_NAME, $current, false );
			}
		}
		return self::sanitize( $current );
	}

	public static function get() {
		$current = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();
		return self::sanitize( is_array( $current ) ? $current : array() );
	}

	public static function enabled( $feature ) {
		$defaults = self::defaults();
		if ( ! is_string( $feature ) || ! array_key_exists( $feature, $defaults ) ) {
			return false;
		}
		if ( class_exists( __NAMESPACE__ . '\\SafeMode' ) && SafeMode::public_features_disabled() ) {
			return false;
		}
		$settings = self::get();
		return isset( $settings[ $feature ] ) && 1 === $settings[ $feature ];
	}

	public static function patch( array $patch ) {
		$old     = self::get();
		$allowed = array_intersect_key( $patch, self::defaults() );
		$new     = self::sanitize( array_merge( $old, $allowed ) );
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_NAME, $new, false );
		}
		self::schedule_rewrite_if_needed( $old, $new );
		return $new;
	}

	public static function handle_update( $option, $old_value, $new_value ) {
		if ( self::OPTION_NAME !== $option ) {
			return;
		}
		self::schedule_rewrite_if_needed( self::sanitize( $old_value ), self::sanitize( $new_value ) );
	}

	private static function schedule_rewrite_if_needed( array $old, array $new ) {
		foreach ( array( 'news_rss_enabled', 'news_sitemap_enabled' ) as $gate ) {
			if ( (int) $old[ $gate ] !== (int) $new[ $gate ] ) {
				if ( function_exists( 'update_option' ) ) {
					update_option( 'sabri_feed_flush_rewrite_rules', 1, false );
				}
				return;
			}
		}
	}
}
