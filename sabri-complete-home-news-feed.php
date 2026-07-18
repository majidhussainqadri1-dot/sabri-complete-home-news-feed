<?php
/**
 * Plugin Name: Sabri Complete Home and News Feed
 * Plugin URI: https://github.com/majidhussainqadri1-dot/sabri-complete-home-news-feed
 * Description: Phase 2 Home Feed and public Composer runtime for Sabri home feed, social news, publishing, safety, and data architecture.
 * Version: 1.0.0
 * Author: Dr. Allama Majid Hussain Sabri
 * Text Domain: sabri-complete-home-news-feed
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SABRI_HNF_VERSION', '1.0.0' );
define( 'SABRI_HNF_SCHEMA_VERSION', '1.0.0' );
define( 'SABRI_HNF_FILE', __FILE__ );
define( 'SABRI_HNF_PATH', function_exists( 'plugin_dir_path' ) ? plugin_dir_path( __FILE__ ) : dirname( __FILE__ ) . DIRECTORY_SEPARATOR );
define( 'SABRI_HNF_URL', function_exists( 'plugin_dir_url' ) ? plugin_dir_url( __FILE__ ) : '' );
define( 'SABRI_HNF_SLUG', 'sabri-complete-home-news-feed' );
define( 'SABRI_HNF_TEXT_DOMAIN', 'sabri-complete-home-news-feed' );
define( 'SABRI_HNF_MINIMUM_PHP', '8.1' );
define( 'SABRI_HNF_MINIMUM_WP', '6.0' );

/**
 * Escape helper that remains safe in lean test stubs.
 *
 * @param string $value Text to escape.
 * @return string
 */
function sabri_hnf_escape_html( $value ) {
	if ( function_exists( 'esc_html' ) ) {
		return esc_html( $value );
	}

	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

/**
 * PHP compatibility check.
 *
 * @return bool
 */
function sabri_hnf_php_supported() {
	return version_compare( PHP_VERSION, SABRI_HNF_MINIMUM_PHP, '>=' );
}

/**
 * WordPress compatibility check.
 *
 * @return bool
 */
function sabri_hnf_wp_supported() {
	global $wp_version;

	if ( empty( $wp_version ) ) {
		return true;
	}

	return version_compare( $wp_version, SABRI_HNF_MINIMUM_WP, '>=' );
}

/**
 * Render the PHP guard notice.
 *
 * @return void
 */
function sabri_hnf_php_notice() {
	$message = sprintf(
		/* translators: 1: required PHP version, 2: current PHP version. */
		__( 'Sabri Complete Home and News Feed requires PHP %1$s or higher. Current PHP version: %2$s.', 'sabri-complete-home-news-feed' ),
		SABRI_HNF_MINIMUM_PHP,
		PHP_VERSION
	);

	echo '<div class="notice notice-error"><p>' . sabri_hnf_escape_html( $message ) . '</p></div>';
}

/**
 * Render the WordPress guard notice.
 *
 * @return void
 */
function sabri_hnf_wp_notice() {
	global $wp_version;

	$message = sprintf(
		/* translators: 1: required WordPress version, 2: current WordPress version. */
		__( 'Sabri Complete Home and News Feed requires WordPress %1$s or higher. Current WordPress version: %2$s.', 'sabri-complete-home-news-feed' ),
		SABRI_HNF_MINIMUM_WP,
		$wp_version ? $wp_version : __( 'unknown', 'sabri-complete-home-news-feed' )
	);

	echo '<div class="notice notice-error"><p>' . sabri_hnf_escape_html( $message ) . '</p></div>';
}

if ( ! sabri_hnf_php_supported() ) {
	if ( function_exists( 'add_action' ) ) {
		add_action( 'admin_notices', 'sabri_hnf_php_notice' );
	}
	return;
}

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Sabri\\HomeNewsFeed\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$parts    = explode( DIRECTORY_SEPARATOR, $relative );
		$base     = array_shift( $parts );
		$file     = 'class-' . strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $base ) ) . '.php';

		if ( ! empty( $parts ) ) {
			$file = implode( DIRECTORY_SEPARATOR, $parts ) . DIRECTORY_SEPARATOR . $file;
		}

		$paths = array(
			SABRI_HNF_PATH . 'includes' . DIRECTORY_SEPARATOR . $file,
			SABRI_HNF_PATH . 'admin' . DIRECTORY_SEPARATOR . $file,
		);

		foreach ( $paths as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
);

/**
 * Activation boundary.
 *
 * @return void
 */
function sabri_hnf_activate() {
	if ( ! sabri_hnf_php_supported() || ! sabri_hnf_wp_supported() ) {
		return;
	}

	\Sabri\HomeNewsFeed\Activator::activate();
}

/**
 * Deactivation boundary.
 *
 * @return void
 */
function sabri_hnf_deactivate() {
	\Sabri\HomeNewsFeed\Deactivator::deactivate();
}

if ( function_exists( 'register_activation_hook' ) ) {
	register_activation_hook( __FILE__, 'sabri_hnf_activate' );
}

if ( function_exists( 'register_deactivation_hook' ) ) {
	register_deactivation_hook( __FILE__, 'sabri_hnf_deactivate' );
}

/**
 * Runtime bootstrap.
 *
 * @return void
 */
function sabri_hnf_bootstrap() {
	if ( ! sabri_hnf_wp_supported() ) {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_notices', 'sabri_hnf_wp_notice' );
		}
		return;
	}

	if ( function_exists( 'load_plugin_textdomain' ) ) {
		load_plugin_textdomain( SABRI_HNF_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	\Sabri\HomeNewsFeed\Plugin::instance()->register();
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'plugins_loaded', 'sabri_hnf_bootstrap' );
}
