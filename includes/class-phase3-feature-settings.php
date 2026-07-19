<?php
/**
 * Runtime feature settings for implemented Phase 3 checkpoints.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps Phase 3 runtime activation isolated from the accepted Phase 2 option. */
final class Phase3FeatureSettings {
	const OPTION_NAME = 'sabri_hnf_phase3_features';

	/** Register the isolated option. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		}
	}

	/** Register WordPress setting. */
	public static function register_setting() {
		if ( function_exists( 'register_setting' ) ) {
			register_setting(
				'sabri_feed_settings',
				self::OPTION_NAME,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( __CLASS__, 'sanitize' ),
					'default'           => self::defaults(),
				)
			);
		}
	}

	/**
	 * Safe runtime defaults.
	 *
	 * Comments, follows, follower counts, and reports remain gated until Hostinger
	 * staging acceptance. Reactions and private saves retain their accepted 3B defaults.
	 *
	 * @return array<string,int>
	 */
	public static function defaults() {
		return array(
			'reactions_enabled'            => 1,
			'dislikes_enabled'             => 1,
			'saves_enabled'                => 1,
			'show_public_reaction_counts'  => 1,
			'comments_enabled'             => 0,
			'follows_enabled'              => 0,
			'show_public_follower_counts'  => 0,
			'followers_visibility_enabled' => 0,
			'reports_enabled'              => 0,
			'polls_enabled'                => 0,
			'notification_bridge_enabled'  => 0,
			'view_logging_enabled'         => 0,
		);
	}

	/** Return merged runtime settings. */
	public static function get() {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();
		$stored = is_array( $stored ) ? $stored : array();
		return array_merge( self::defaults(), self::sanitize( $stored ) );
	}

	/** Sanitize only known feature flags. */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = array();
		foreach ( self::defaults() as $key => $default ) {
			$out[ $key ] = array_key_exists( $key, $input ) ? ( empty( $input[ $key ] ) ? 0 : 1 ) : $default;
		}
		return $out;
	}

	/** Fail-closed feature check with global safety controls. */
	public static function enabled( $feature ) {
		$feature  = function_exists( 'sanitize_key' ) ? sanitize_key( $feature ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $feature ) );
		$settings = self::get();
		if ( ! array_key_exists( $feature, $settings ) || SafeMode::public_features_disabled() ) {
			return false;
		}
		return 1 === (int) $settings[ $feature ];
	}
}
