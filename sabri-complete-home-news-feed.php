<?php
/**
 * Plugin Name: Sabri Complete Home and News Feed
 * Plugin URI: https://github.com/majidhussainqadri1-dot/sabri-complete-home-news-feed
 * Description: Phase 2 Home Feed and public Composer runtime for Sabri home feed, social news, publishing, safety, and data architecture.
 * Version: 1.0.1
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

/*
 * Duplicate-copy recovery.
 *
 * Hostinger staging may retain an older copy under a different folder name.
 * Loading both copies would otherwise redeclare global functions and crash
 * WordPress before Safe Boot can run. The newer copy therefore registers a
 * one-time recovery callback, avoids all declarations, and lets WordPress
 * deactivate the previously loaded copy during activation/admin bootstrap.
 */
$sabri_hnf_loaded_file      = defined( 'SABRI_HNF_FILE' ) ? str_replace( '\\', '/', (string) SABRI_HNF_FILE ) : '';
$sabri_hnf_current_file     = str_replace( '\\', '/', __FILE__ );
$sabri_hnf_duplicate_loaded = ( '' !== $sabri_hnf_loaded_file && rtrim( $sabri_hnf_loaded_file, '/' ) !== rtrim( $sabri_hnf_current_file, '/' ) )
	|| ( '' === $sabri_hnf_loaded_file && ( function_exists( 'sabri_hnf_bootstrap' ) || function_exists( 'sabri_hnf_activate' ) ) );

if ( '' !== $sabri_hnf_loaded_file && rtrim( $sabri_hnf_loaded_file, '/' ) === rtrim( $sabri_hnf_current_file, '/' ) ) {
	return;
}

if ( $sabri_hnf_duplicate_loaded ) {
	$sabri_hnf_resolve_duplicate = static function () use ( $sabri_hnf_loaded_file, $sabri_hnf_current_file ) {
		if ( ! function_exists( 'deactivate_plugins' ) && defined( 'ABSPATH' ) ) {
			$plugin_api = ABSPATH . 'wp-admin/includes/plugin.php';
			if ( is_readable( $plugin_api ) ) {
				require_once $plugin_api;
			}
		}

		$current_basename = function_exists( 'plugin_basename' ) ? plugin_basename( $sabri_hnf_current_file ) : '';
		$duplicates       = array();
		if ( '' !== $sabri_hnf_loaded_file && function_exists( 'plugin_basename' ) ) {
			$duplicates[] = plugin_basename( $sabri_hnf_loaded_file );
		}

		if ( function_exists( 'get_plugins' ) ) {
			foreach ( get_plugins() as $plugin_basename => $headers ) {
				$plugin_name = isset( $headers['Name'] ) ? (string) $headers['Name'] : '';
				$text_domain = isset( $headers['TextDomain'] ) ? (string) $headers['TextDomain'] : '';
				if ( $plugin_basename !== $current_basename && ( 'Sabri Complete Home and News Feed' === $plugin_name || 'sabri-complete-home-news-feed' === $text_domain ) ) {
					$duplicates[] = $plugin_basename;
				}
			}
		}

		$duplicates = array_values( array_unique( array_filter( $duplicates ) ) );
		if ( function_exists( 'deactivate_plugins' ) ) {
			foreach ( $duplicates as $duplicate ) {
				if ( $duplicate !== $current_basename ) {
					deactivate_plugins( $duplicate, true );
				}
			}
		}

		if ( function_exists( 'update_option' ) ) {
			update_option(
				'sabri_hnf_duplicate_plugin_resolution',
				array(
					'resolved'        => 1,
					'previous_copies' => $duplicates,
					'current_copy'    => $current_basename,
					'resolved_at_utc' => gmdate( 'Y-m-d H:i:s' ),
				),
				false
			);
		}
	};

	if ( function_exists( 'register_activation_hook' ) ) {
		register_activation_hook( __FILE__, $sabri_hnf_resolve_duplicate );
	}
	if ( function_exists( 'add_action' ) ) {
		add_action( 'admin_init', $sabri_hnf_resolve_duplicate, 1 );
	}
	return;
}

define( 'SABRI_HNF_VERSION', '1.0.1' );
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
		if ( function_exists( 'esc_html' ) ) {
			return esc_html( $value );
		}
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

/** PHP compatibility check. */