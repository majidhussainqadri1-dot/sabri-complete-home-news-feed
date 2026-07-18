<?php
/**
 * Local admin assets.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues local admin-only assets.
 */
final class Assets {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return void
	 */
	public static function enqueue_admin( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'sabri-feed' ) ) {
			return;
		}

		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'sabri-feed-admin', SABRI_HNF_URL . 'assets/css/admin.css', array(), SABRI_HNF_VERSION );
		}

		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'sabri-feed-admin', SABRI_HNF_URL . 'assets/js/admin.js', array(), SABRI_HNF_VERSION, true );
		}
	}
}
