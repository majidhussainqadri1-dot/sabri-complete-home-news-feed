<?php
/**
 * Local admin and public assets.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Registers and enqueues plugin-owned assets. */
final class Assets {
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_public' ) );
		}
	}
	public static function enqueue_admin( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'sabri-feed' ) ) { return; }
		if ( function_exists( 'wp_enqueue_style' ) ) { wp_enqueue_style( 'sabri-feed-admin', SABRI_HNF_URL . 'assets/css/admin.css', array(), SABRI_HNF_VERSION ); }
		if ( function_exists( 'wp_enqueue_script' ) ) { wp_enqueue_script( 'sabri-feed-admin', SABRI_HNF_URL . 'assets/js/admin.js', array(), SABRI_HNF_VERSION, true ); }
		if ( false !== strpos( (string) $hook_suffix, 'sabri-feed-staging-preview' ) ) { self::enqueue_feed(); self::enqueue_composer(); }
	}
	public static function register_public() {
		if ( function_exists( 'wp_register_style' ) ) {
			wp_register_style( 'sabri-hnf-feed', SABRI_HNF_URL . 'assets/css/feed.css', array(), SABRI_HNF_VERSION );
			wp_register_style( 'sabri-hnf-interactions', SABRI_HNF_URL . 'assets/css/interactions.css', array( 'sabri-hnf-feed' ), SABRI_HNF_VERSION );
			wp_register_style( 'sabri-hnf-comments', SABRI_HNF_URL . 'assets/css/comments.css', array( 'sabri-hnf-feed', 'sabri-hnf-interactions' ), SABRI_HNF_VERSION );
			wp_register_style( 'sabri-hnf-polls', SABRI_HNF_URL . 'assets/css/polls.css', array( 'sabri-hnf-feed' ), SABRI_HNF_VERSION );
			wp_register_style( 'sabri-hnf-composer', SABRI_HNF_URL . 'assets/css/composer.css', array( 'sabri-hnf-feed' ), SABRI_HNF_VERSION );
			wp_register_style( 'sabri-hnf-news', SABRI_HNF_URL . 'assets/css/news.css', array(), SABRI_HNF_VERSION );
		}
		if ( function_exists( 'wp_register_script' ) ) {
			wp_register_script( 'sabri-hnf-feed', SABRI_HNF_URL . 'assets/js/feed.js', array(), SABRI_HNF_VERSION, true );
			wp_register_script( 'sabri-hnf-share', SABRI_HNF_URL . 'assets/js/share.js', array(), SABRI_HNF_VERSION, true );
			wp_register_script( 'sabri-hnf-comments', SABRI_HNF_URL . 'assets/js/comments.js', array( 'sabri-hnf-feed' ), SABRI_HNF_VERSION, true );
			wp_register_script( 'sabri-hnf-polls', SABRI_HNF_URL . 'assets/js/polls.js', array(), SABRI_HNF_VERSION, true );
			wp_register_script( 'sabri-hnf-composer', SABRI_HNF_URL . 'assets/js/composer.js', array( 'sabri-hnf-feed' ), SABRI_HNF_VERSION, true );
			wp_register_script( 'sabri-hnf-news', SABRI_HNF_URL . 'assets/js/news.js', array(), SABRI_HNF_VERSION, true );
		}
	}

	/** Enqueue feed assets; News assets load only when the resolved page contains a News card. */
	public static function enqueue_feed( $contains_news = false ) {
		self::register_public();
		$interaction_assets = Phase3FeatureSettings::enabled( 'reactions_enabled' ) || Phase3FeatureSettings::enabled( 'saves_enabled' ) || Phase3FeatureSettings::enabled( 'share_enabled' ) || Phase3FeatureSettings::enabled( 'comments_enabled' ) || Phase3FeatureSettings::enabled( 'follows_enabled' ) || Phase3FeatureSettings::enabled( 'reports_enabled' ) || Phase3FeatureSettings::enabled( 'view_logging_enabled' );
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'sabri-hnf-feed' );
			if ( $interaction_assets ) { wp_enqueue_style( 'sabri-hnf-interactions' ); }
			if ( $contains_news && NewsPolicy::public_reads_allowed() ) { wp_enqueue_style( 'sabri-hnf-news' ); }
		}
		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( 'sabri-hnf-feed' );
			if ( Phase3FeatureSettings::enabled( 'share_enabled' ) ) { wp_enqueue_script( 'sabri-hnf-share' ); }
			if ( $contains_news && NewsPolicy::public_reads_allowed() ) { wp_enqueue_script( 'sabri-hnf-news' ); }
		}
	}

	/** Enqueue Phase 3 interaction assets without forcing a Home Feed or News card. */
	public static function enqueue_interactions() {
		self::register_public();
		if ( function_exists( 'wp_enqueue_style' ) ) { wp_enqueue_style( 'sabri-hnf-feed' ); wp_enqueue_style( 'sabri-hnf-interactions' ); }
		if ( function_exists( 'wp_enqueue_script' ) && Phase3FeatureSettings::enabled( 'share_enabled' ) ) { wp_enqueue_script( 'sabri-hnf-share' ); }
	}

	public static function enqueue_news() {
		if ( ! NewsPolicy::public_reads_allowed() ) { return; }
		self::register_public();
		if ( function_exists( 'wp_enqueue_style' ) ) { wp_enqueue_style( 'sabri-hnf-news' ); }
		if ( function_exists( 'wp_enqueue_script' ) ) { wp_enqueue_script( 'sabri-hnf-news' ); }
	}
	public static function enqueue_comments() {
		self::enqueue_interactions();
		if ( ! Phase3FeatureSettings::enabled( 'comments_enabled' ) ) { return; }
		if ( function_exists( 'wp_enqueue_style' ) ) { wp_enqueue_style( 'sabri-hnf-comments' ); }
		if ( function_exists( 'wp_enqueue_script' ) ) { wp_enqueue_script( 'sabri-hnf-comments' ); }
	}
	public static function enqueue_polls() {
		self::enqueue_feed();
		if ( ! Phase3FeatureSettings::enabled( 'polls_enabled' ) ) { return; }
		if ( function_exists( 'wp_enqueue_style' ) ) { wp_enqueue_style( 'sabri-hnf-polls' ); }
		if ( function_exists( 'wp_enqueue_script' ) ) { wp_enqueue_script( 'sabri-hnf-polls' ); }
	}
	public static function enqueue_composer() {
		self::register_public();
		if ( function_exists( 'wp_enqueue_style' ) ) { wp_enqueue_style( 'sabri-hnf-composer' ); }
		if ( function_exists( 'wp_enqueue_script' ) ) { wp_enqueue_script( 'sabri-hnf-composer' ); }
	}
}
