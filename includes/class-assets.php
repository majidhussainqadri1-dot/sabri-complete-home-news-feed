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
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_public' ) );
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

		if ( false !== strpos( (string) $hook_suffix, 'sabri-feed-staging-preview' ) ) {
			self::enqueue_feed();
			self::enqueue_composer();
		}
	}

	/**
	 * Register public assets without loading them globally.
	 *
	 * @return void
	 */
	public static function register_public() {
		if ( function_exists( 'wp_register_style' ) ) {
			wp_register_style( 'sabri-hnf-feed', SABRI_HNF_URL . 'assets/css/feed.css', array(), SABRI_HNF_VERSION );
			wp_register_style( 'sabri-hnf-composer', SABRI_HNF_URL . 'assets/css/composer.css', array( 'sabri-hnf-feed' ), SABRI_HNF_VERSION );
		}

		if ( function_exists( 'wp_register_script' ) ) {
			wp_register_script( 'sabri-hnf-feed', SABRI_HNF_URL . 'assets/js/feed.js', array(), SABRI_HNF_VERSION, true );
			wp_register_script( 'sabri-hnf-composer', SABRI_HNF_URL . 'assets/js/composer.js', array(), SABRI_HNF_VERSION, true );
		}
	}

	/**
	 * Enqueue feed assets.
	 *
	 * @return void
	 */
	public static function enqueue_feed() {
		self::register_public();
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'sabri-hnf-feed' );
		}
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'sabri-hnf-feed' );
		}
	}

	/**
	 * Enqueue composer assets.
	 *
	 * @return void
	 */
	public static function enqueue_composer() {
		self::register_public();
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'sabri-hnf-composer' );
		}
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'sabri-hnf-composer' );
		}
	}
}
