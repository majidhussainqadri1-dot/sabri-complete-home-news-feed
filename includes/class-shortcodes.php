<?php
/**
 * Public shortcodes.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers shortcode fallback rendering.
 */
final class Shortcodes {
	/** Whether the composer shortcode rendered in this request. */
	private static $composer_rendered = false;

	/** Register shortcodes. */
	public static function register() {
		if ( function_exists( 'add_shortcode' ) ) {
			add_shortcode( 'sabri_complete_home_feed', array( __CLASS__, 'home_feed' ) );
			add_shortcode( 'sabri_public_post_composer', array( __CLASS__, 'composer' ) );
			add_shortcode( 'sabri_profile_timeline', array( __CLASS__, 'profile_timeline' ) );
		}
	}

	/** Home Feed shortcode. */
	public static function home_feed( $atts = array() ) {
		$atts = is_array( $atts ) ? $atts : array();
		return HomeIntegration::render_feed_once( 'shortcode', $atts );
	}

	/** Composer shortcode. */
	public static function composer( $atts = array() ) {
		$atts = is_array( $atts ) ? $atts : array();
		if ( self::$composer_rendered ) {
			return '';
		}
		self::$composer_rendered = true;
		return Composer::render( $atts );
	}

	/**
	 * Profile Timeline shortcode.
	 *
	 * @param array<string,mixed>|string $atts Attributes.
	 * @return string
	 */
	public static function profile_timeline( $atts = array() ) {
		$atts = is_array( $atts ) ? $atts : array();
		$defaults = array(
			'user_id'  => function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0,
			'page'     => 1,
			'per_page' => 10,
		);
		if ( function_exists( 'shortcode_atts' ) ) {
			$atts = shortcode_atts( $defaults, $atts, 'sabri_profile_timeline' );
		} else {
			$atts = array_merge( $defaults, $atts );
		}
		$user_id = absint( isset( $atts['user_id'] ) ? $atts['user_id'] : 0 );
		if ( $user_id <= 0 && function_exists( 'get_current_user_id' ) ) {
			$user_id = (int) get_current_user_id();
		}
		return ProfileTimeline::render(
			$user_id,
			array(
				'page'     => isset( $atts['page'] ) ? absint( $atts['page'] ) : 1,
				'per_page' => isset( $atts['per_page'] ) ? absint( $atts['per_page'] ) : 10,
			)
		);
	}

	/** Reset shortcode runtime guards for tests. */
	public static function reset_runtime_guards() {
		self::$composer_rendered = false;
	}
}
