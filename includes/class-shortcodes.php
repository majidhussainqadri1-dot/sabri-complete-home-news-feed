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
	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_shortcode' ) ) {
			add_shortcode( 'sabri_complete_home_feed', array( __CLASS__, 'home_feed' ) );
			add_shortcode( 'sabri_public_post_composer', array( __CLASS__, 'composer' ) );
		}
	}

	/**
	 * Home Feed shortcode.
	 *
	 * @param array<string,mixed>|string $atts Attributes.
	 * @return string
	 */
	public static function home_feed( $atts = array() ) {
		$atts = is_array( $atts ) ? $atts : array();
		return HomeIntegration::render_feed_once( 'shortcode', $atts );
	}

	/**
	 * Composer shortcode.
	 *
	 * @param array<string,mixed>|string $atts Attributes.
	 * @return string
	 */
	public static function composer( $atts = array() ) {
		$atts = is_array( $atts ) ? $atts : array();
		return Composer::render( $atts );
	}
}
