<?php
/**
 * Phase 3F poll composer integration.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes the Poll feed type available only while the feature gate is configured.
 */
final class PollComposerIntegration {
	/** Register settings filters used by both form and REST composer validation. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'option_' . Settings::OPTION_NAME, array( __CLASS__, 'filter_settings' ), 20 );
			add_filter( 'default_option_' . Settings::OPTION_NAME, array( __CLASS__, 'filter_default_settings' ), 20, 3 );
		}
	}

	/** Filter stored settings. */
	public static function filter_settings( $settings ) {
		return self::apply_poll_type_policy( $settings );
	}

	/** Filter default settings with WordPress-compatible signature. */
	public static function filter_default_settings( $default, $option = '', $passed_default = false ) {
		unset( $option, $passed_default );
		return self::apply_poll_type_policy( $default );
	}

	/**
	 * Add or remove Poll from the Composer allow-list.
	 *
	 * Important: this method runs inside WordPress dynamic option filters for
	 * Settings::OPTION_NAME. It must not call Phase3FeatureSettings::enabled(),
	 * because enabled() consults SafeMode, which reads Settings::OPTION_NAME and
	 * would recursively re-enter this filter until PHP memory exhaustion.
	 *
	 * @param mixed $settings Settings.
	 * @return mixed
	 */
	private static function apply_poll_type_policy( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}
		if ( ! isset( $settings['composer'] ) || ! is_array( $settings['composer'] ) ) {
			$settings['composer'] = array();
		}

		$allowed = isset( $settings['composer']['allowed_feed_types'] ) && is_array( $settings['composer']['allowed_feed_types'] )
			? array_map( 'sanitize_key', $settings['composer']['allowed_feed_types'] )
			: FeedContext::phase2_feed_type_slugs();
		$allowed = array_values( array_diff( $allowed, array( 'poll' ) ) );

		if ( Phase3FeatureSettings::configured_enabled( 'polls_enabled' ) ) {
			$allowed[] = 'poll';
		}

		$settings['composer']['allowed_feed_types'] = array_values( array_unique( $allowed ) );
		return $settings;
	}
}
