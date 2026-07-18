<?php
/**
 * Safe Mode and Emergency Disable.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central feature gate for safety controls.
 */
final class SafeMode {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
			add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_indicator' ), 100 );
		}
	}

	/**
	 * Whether the administrator-only Safe Mode query is active.
	 *
	 * @return bool
	 */
	public static function query_safe_mode() {
		$settings = Settings::get();
		if ( empty( $settings['advanced']['safe_mode_enabled'] ) ) {
			return false;
		}

		$value = isset( $_GET['sabri_feed_safe'] ) ? self::clean_query_value( wp_unslash( $_GET['sabri_feed_safe'] ) ) : '';
		if ( '1' !== $value ) {
			return false;
		}

		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	/**
	 * Whether Emergency Disable is active.
	 *
	 * @return bool
	 */
	public static function emergency_disabled() {
		$settings = Settings::get();
		return ! empty( $settings['advanced']['emergency_disabled'] );
	}

	/**
	 * Whether future public social features must be disabled.
	 *
	 * @return bool
	 */
	public static function public_features_disabled() {
		return self::query_safe_mode() || self::emergency_disabled();
	}

	/**
	 * Central feature gate for future features.
	 *
	 * @param string $feature Feature key.
	 * @return bool
	 */
	public static function feature_enabled( $feature ) {
		$settings = Settings::get();
		$feature  = sanitize_key( $feature );

		if ( self::public_features_disabled() ) {
			return false;
		}

		if ( 'composer' === $feature ) {
			return ! empty( $settings['composer']['public_composer_enabled'] );
		}

		if ( 'news' === $feature ) {
			return ! empty( $settings['news']['enabled'] );
		}

		if ( 'feed' === $feature ) {
			return ! empty( $settings['feed']['enabled'] );
		}

		return ! empty( $settings['general']['enabled'] );
	}

	/**
	 * Set Emergency Disable.
	 *
	 * @param bool $disabled Disabled state.
	 * @return array<string,mixed>
	 */
	public static function set_emergency_disabled( $disabled ) {
		$settings = Settings::get();
		$settings['advanced']['emergency_disabled'] = $disabled ? 1 : 0;

		if ( function_exists( 'update_option' ) ) {
			update_option( Settings::OPTION_NAME, $settings, false );
		}

		AuditLog::record(
			$disabled ? 'emergency_disable' : 'emergency_reenable',
			array(
				'public_features_disabled' => $disabled ? 1 : 0,
				'data_preserved'           => true,
			)
		);

		return array(
			'emergency_disabled' => (bool) $disabled,
			'data_preserved'     => true,
		);
	}

	/**
	 * Admin notice for active emergency state.
	 *
	 * @return void
	 */
	public static function admin_notice() {
		if ( ! self::emergency_disabled() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Home and News Feed Emergency Disable is active. Future public composer and social actions are disabled, while data and admin access are preserved.', 'sabri-complete-home-news-feed' ) . '</p></div>';
	}

	/**
	 * Add an administrator-only toolbar indicator.
	 *
	 * @param mixed $admin_bar Admin bar object.
	 * @return void
	 */
	public static function admin_bar_indicator( $admin_bar ) {
		if ( ! self::public_features_disabled() || ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) || ! is_object( $admin_bar ) || ! method_exists( $admin_bar, 'add_node' ) ) {
			return;
		}

		$admin_bar->add_node(
			array(
				'id'    => 'sabri-feed-safe-mode',
				'title' => self::emergency_disabled() ? __( 'Home Feed Emergency Disabled', 'sabri-complete-home-news-feed' ) : __( 'Home Feed Safe Mode', 'sabri-complete-home-news-feed' ),
				'href'  => function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=sabri-feed-overview' ) : '',
			)
		);
	}

	/**
	 * Clean a query flag without trusting host headers or redirects.
	 *
	 * @param mixed $value Query value.
	 * @return string
	 */
	private static function clean_query_value( $value ) {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}

		return preg_replace( '/[^0-9A-Za-z_\-]/', '', (string) $value );
	}
}
