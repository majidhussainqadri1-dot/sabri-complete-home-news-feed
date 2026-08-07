<?php
/**
 * Plugin Name: Sabri Complete Home and News Feed
 * Plugin URI: https://github.com/majidhussainqadri1-dot/sabri-complete-home-news-feed
 * Description: Complete public Home, social Feed, Profile Timeline, Editorial News, publishing, migration, safety, and integration runtime for the Sabri Social Homeopathy Platform.
 * Version: 1.0.5
 * Author: Dr. Allamah Majid Hussain Sabri Muhaddith Mursheed
 * Text Domain: sabri-complete-home-news-feed
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/*
 * Duplicate-copy recovery.
 *
 * Only the highest-version canonical File 21 copy may win. The winning copy
 * deactivates older folder variants and performs one controlled admin reload;
 * no classes are redeclared in the collision request.
 */
$sabri_hnf_loaded_file      = defined( 'SABRI_HNF_FILE' ) ? str_replace( '\\', '/', (string) SABRI_HNF_FILE ) : '';
$sabri_hnf_current_file     = str_replace( '\\', '/', __FILE__ );
$sabri_hnf_duplicate_loaded = ( '' !== $sabri_hnf_loaded_file && rtrim( $sabri_hnf_loaded_file, '/' ) !== rtrim( $sabri_hnf_current_file, '/' ) )
	|| ( '' === $sabri_hnf_loaded_file && ( function_exists( 'sabri_hnf_bootstrap' ) || function_exists( 'sabri_hnf_activate' ) ) );

if ( '' !== $sabri_hnf_loaded_file && rtrim( $sabri_hnf_loaded_file, '/' ) === rtrim( $sabri_hnf_current_file, '/' ) ) { return; }

if ( $sabri_hnf_duplicate_loaded ) {
	$sabri_hnf_resolve_duplicate = static function () use ( $sabri_hnf_loaded_file, $sabri_hnf_current_file ) {
		if ( ! function_exists( 'deactivate_plugins' ) && defined( 'ABSPATH' ) ) {
			$plugin_api = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( is_readable( $plugin_api ) ) { require_once $plugin_api; }
		}
		if ( ! function_exists( 'plugin_basename' ) || ! function_exists( 'get_plugins' ) ) { return; }

		$current_basename = plugin_basename( $sabri_hnf_current_file );
		$loaded_basename  = '' !== $sabri_hnf_loaded_file ? plugin_basename( $sabri_hnf_loaded_file ) : '';
		$candidates       = array();
		foreach ( get_plugins() as $plugin_basename => $headers ) {
			$name   = isset( $headers['Name'] ) ? (string) $headers['Name'] : '';
			$domain = isset( $headers['TextDomain'] ) ? (string) $headers['TextDomain'] : '';
			if ( 'Sabri Complete Home and News Feed' !== $name && 'sabri-complete-home-news-feed' !== $domain ) { continue; }
			$candidates[ $plugin_basename ] = array(
				'basename'  => $plugin_basename,
				'version'   => isset( $headers['Version'] ) ? (string) $headers['Version'] : '0.0.0',
				'canonical' => 0 === strpos( $plugin_basename, 'sabri-complete-home-news-feed/' ) ? 1 : 0,
			);
		}
		foreach ( array_filter( array( $current_basename, $loaded_basename ) ) as $basename ) {
			if ( ! isset( $candidates[ $basename ] ) ) {
				$candidates[ $basename ] = array( 'basename' => $basename, 'version' => $basename === $current_basename ? '1.0.5' : '0.0.0', 'canonical' => 0 === strpos( $basename, 'sabri-complete-home-news-feed/' ) ? 1 : 0 );
			}
		}
		if ( empty( $candidates ) ) { return; }
		uasort( $candidates, static function ( $left, $right ) {
			$version = version_compare( $right['version'], $left['version'] );
			return 0 !== $version ? $version : (int) $right['canonical'] - (int) $left['canonical'];
		} );
		$winner = (string) key( $candidates );
		$losers = array_values( array_diff( array_keys( $candidates ), array( $winner ) ) );
		$changed = false;
		if ( function_exists( 'deactivate_plugins' ) ) {
			foreach ( $losers as $loser ) { deactivate_plugins( $loser, true ); $changed = true; }
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( 'sabri_hnf_duplicate_plugin_resolution', array(
				'resolved' => $changed ? 1 : 0, 'winner' => $winner, 'previous_copies' => $losers,
				'current_copy' => $current_basename, 'resolved_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			), false );
		}
		if ( $changed && $winner === $current_basename && function_exists( 'current_filter' ) && 'admin_init' === current_filter()
			&& function_exists( 'wp_safe_redirect' ) && function_exists( 'admin_url' )
			&& ! ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) && ! ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) && ! headers_sent() ) {
			wp_safe_redirect( admin_url( 'plugins.php?sabri_hnf_duplicate_resolved=1' ) );
			exit;
		}
	};
	if ( function_exists( 'register_activation_hook' ) ) { register_activation_hook( __FILE__, $sabri_hnf_resolve_duplicate ); }
	if ( function_exists( 'add_action' ) ) { add_action( 'admin_init', $sabri_hnf_resolve_duplicate, 1 ); }
	return;
}

/*
 * Package 1.0.5 is the four-plan current-wave reconciliation release. It
 * preserves the established File 21 runtime/API contract at 1.0.3 and schema
 * at 1.0.0 while correcting user agency, relationship ownership, green brand,
 * saved collections, comments, privacy/cache and ranking boundaries.
 */
define( 'SABRI_HNF_PACKAGE_VERSION', '1.0.5' );
define( 'SABRI_HNF_VERSION', '1.0.3' );
define( 'SABRI_HNF_SCHEMA_VERSION', '1.0.0' );
define( 'SABRI_HNF_FILE', __FILE__ );
define( 'SABRI_HNF_PATH', function_exists( 'plugin_dir_path' ) ? plugin_dir_path( __FILE__ ) : dirname( __FILE__ ) . DIRECTORY_SEPARATOR );
define( 'SABRI_HNF_URL', function_exists( 'plugin_dir_url' ) ? plugin_dir_url( __FILE__ ) : '' );
define( 'SABRI_HNF_SLUG', 'sabri-complete-home-news-feed' );
define( 'SABRI_HNF_TEXT_DOMAIN', 'sabri-complete-home-news-feed' );
define( 'SABRI_HNF_MINIMUM_PHP', '8.1' );
define( 'SABRI_HNF_MINIMUM_WP', '6.0' );

/** Escape helper that remains safe in lean test stubs. */
if ( ! function_exists( 'sabri_hnf_escape_html' ) ) {
	function sabri_hnf_escape_html( $value ) {
		if ( function_exists( 'esc_html' ) ) { return esc_html( $value ); }
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

/** PHP compatibility check. */
if ( ! function_exists( 'sabri_hnf_php_supported' ) ) {
	function sabri_hnf_php_supported() { return version_compare( PHP_VERSION, SABRI_HNF_MINIMUM_PHP, '>=' ); }
}

/** WordPress compatibility check. */
if ( ! function_exists( 'sabri_hnf_wp_supported' ) ) {
	function sabri_hnf_wp_supported() {
		global $wp_version;
		if ( empty( $wp_version ) ) { return true; }
		return version_compare( $wp_version, SABRI_HNF_MINIMUM_WP, '>=' );
	}
}

/** Render the PHP guard notice. */
if ( ! function_exists( 'sabri_hnf_php_notice' ) ) {
	function sabri_hnf_php_notice() {
		$message = sprintf( __( 'Sabri Complete Home and News Feed requires PHP %1$s or higher. Current PHP version: %2$s.', 'sabri-complete-home-news-feed' ), SABRI_HNF_MINIMUM_PHP, PHP_VERSION );
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	}
}

/** Render the WordPress guard notice. */
if ( ! function_exists( 'sabri_hnf_wp_notice' ) ) {
	function sabri_hnf_wp_notice() {
		global $wp_version;
		$message = sprintf( __( 'Sabri Complete Home and News Feed requires WordPress %1$s or higher. Current WordPress version: %2$s.', 'sabri-complete-home-news-feed' ), SABRI_HNF_MINIMUM_WP, $wp_version ? $wp_version : __( 'unknown', 'sabri-complete-home-news-feed' ) );
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	}
}

if ( ! sabri_hnf_php_supported() ) {
	if ( function_exists( 'add_action' ) ) { add_action( 'admin_notices', 'sabri_hnf_php_notice' ); }
	return;
}

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Sabri\\HomeNewsFeed\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) { return; }
		$relative = substr( $class_name, strlen( $prefix ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
		$parts    = explode( DIRECTORY_SEPARATOR, $relative );
		$base     = array_shift( $parts );
		$file     = 'class-' . strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $base ) ) . '.php';
		if ( ! empty( $parts ) ) { $file = implode( DIRECTORY_SEPARATOR, $parts ) . DIRECTORY_SEPARATOR . $file; }
		$paths = array(
			SABRI_HNF_PATH . 'includes' . DIRECTORY_SEPARATOR . $file,
			SABRI_HNF_PATH . 'admin' . DIRECTORY_SEPARATOR . $file,
			SABRI_HNF_PATH . 'public' . DIRECTORY_SEPARATOR . $file,
		);
		foreach ( $paths as $path ) {
			if ( file_exists( $path ) ) { require_once $path; return; }
		}
	}
);

/** Register Safe Boot recovery and minimal authenticated diagnostics. */
if ( ! function_exists( 'sabri_hnf_register_safe_boot_notice' ) ) {
	function sabri_hnf_register_safe_boot_notice() {
		\Sabri\HomeNewsFeed\SafeBoot::register_recovery_hooks();
		if ( class_exists( '\\Sabri\\HomeNewsFeed\\RestFoundation' ) ) { \Sabri\HomeNewsFeed\RestFoundation::register_safe_boot_routes(); }
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_notices', array( '\\Sabri\\HomeNewsFeed\\SafeBoot', 'admin_notice' ) );
			add_action( 'network_admin_notices', array( '\\Sabri\\HomeNewsFeed\\SafeBoot', 'admin_notice' ) );
		}
	}
}

/** Activation boundary. */
if ( ! function_exists( 'sabri_hnf_activate' ) ) {
	function sabri_hnf_activate() {
		if ( ! sabri_hnf_php_supported() || ! sabri_hnf_wp_supported() ) { return; }
		try {
			\Sabri\HomeNewsFeed\SafeBoot::register_shutdown_guard();
			\Sabri\HomeNewsFeed\SafeBoot::clear();
			\Sabri\HomeNewsFeed\Activator::activate();
		} catch ( \Throwable $error ) { \Sabri\HomeNewsFeed\SafeBoot::record_exception( 'activation', $error ); }
	}
}

/** Deactivation boundary. */
if ( ! function_exists( 'sabri_hnf_deactivate' ) ) {
	function sabri_hnf_deactivate() {
		try {
			\Sabri\HomeNewsFeed\Deactivator::deactivate();
			\Sabri\HomeNewsFeed\SafeBoot::clear();
		} catch ( \Throwable $error ) { \Sabri\HomeNewsFeed\SafeBoot::record_exception( 'deactivation', $error ); }
	}
}

if ( function_exists( 'register_activation_hook' ) ) { register_activation_hook( __FILE__, 'sabri_hnf_activate' ); }
if ( function_exists( 'register_deactivation_hook' ) ) { register_deactivation_hook( __FILE__, 'sabri_hnf_deactivate' ); }

/** Runtime bootstrap. */
if ( ! function_exists( 'sabri_hnf_bootstrap' ) ) {
	function sabri_hnf_bootstrap() {
		if ( ! sabri_hnf_wp_supported() ) {
			if ( function_exists( 'add_action' ) ) { add_action( 'admin_notices', 'sabri_hnf_wp_notice' ); }
			return;
		}
		try {
			\Sabri\HomeNewsFeed\SafeBoot::register_shutdown_guard();
			if ( \Sabri\HomeNewsFeed\SafeBoot::is_blocked() ) { sabri_hnf_register_safe_boot_notice(); return; }
			if ( function_exists( 'load_plugin_textdomain' ) ) { load_plugin_textdomain( SABRI_HNF_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' ); }
			\Sabri\HomeNewsFeed\Plugin::instance()->register();
			$blocked_after_register = \Sabri\HomeNewsFeed\SafeBoot::state();
			if ( ! empty( $blocked_after_register['active'] ) ) { sabri_hnf_register_safe_boot_notice(); }
		} catch ( \Throwable $error ) {
			\Sabri\HomeNewsFeed\SafeBoot::record_exception( 'bootstrap', $error );
			sabri_hnf_register_safe_boot_notice();
		}
	}
}

if ( function_exists( 'add_action' ) ) { add_action( 'plugins_loaded', 'sabri_hnf_bootstrap' ); }
