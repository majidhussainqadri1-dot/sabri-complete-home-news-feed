<?php
/**
 * Home runtime integration.
 *
 * @package SabriCompleteHomeNewsFeed
 */
namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Integrates without replacing Shell header, sidebar, or layout resolver. */
final class HomeIntegration {
	private static $feed_rendered = false;
	private static $single_context_rendered = array();

	public static function register() {
		if ( function_exists( 'add_action' ) ) { add_action( 'sabri_feed_home_center_content', array( __CLASS__, 'render_home_center' ) ); }
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'append_single_post_context' ), 20 );
			add_filter( 'sabri_shell_create_url', array( __CLASS__, 'filter_shell_create_url' ) );
			add_filter( 'sabri_shell_system_check_report', array( __CLASS__, 'append_shell_report' ) );
		}
	}

	/** Render plugin-owned Home center content through the complete observable surface. */
	public static function render_home_center() {
		if ( ! self::is_static_front_page_request() ) { return; }
		if ( class_exists( __NAMESPACE__ . '\\CorrectivePublicMount' ) ) {
			$surface = CorrectivePublicMount::render_complete_surface( 'plugin_owned_hook' );
			if ( '' !== $surface ) { echo $surface; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/** Render feed once per request; a blank renderer must not consume the guard. */
	public static function render_feed_once( $source, array $atts = array() ) {
		unset( $source );
		if ( self::is_single_post_request() || self::$feed_rendered ) { return ''; }
		$html = FeedRenderer::render( $atts );
		if ( '' !== $html ) { self::$feed_rendered = true; }
		return $html;
	}

	/** Determine whether the current request resolves to a single standard post. */
	public static function is_single_post_request() {
		if ( function_exists( 'is_singular' ) && is_singular( 'post' ) ) { return true; }
		if ( function_exists( 'get_queried_object' ) ) {
			$queried_object = get_queried_object();
			return is_object( $queried_object ) && isset( $queried_object->post_type ) && 'post' === $queried_object->post_type;
		}
		if ( function_exists( 'get_queried_object_id' ) && function_exists( 'get_post' ) ) {
			$queried_post = get_post( (int) get_queried_object_id() );
			return is_object( $queried_post ) && isset( $queried_post->post_type ) && 'post' === $queried_post->post_type;
		}
		return false;
	}

	/** Determine whether the plugin-owned Home slot is on the configured front page. */
	private static function is_static_front_page_request() {
		if ( self::is_single_post_request() || ! function_exists( 'is_front_page' ) || ! is_front_page() ) { return false; }
		if ( function_exists( 'is_home' ) && is_home() ) { return false; }
		if ( function_exists( 'get_option' ) && 'page' === get_option( 'show_on_front' ) ) {
			$front_page_id = (int) get_option( 'page_on_front' );
			$queried_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
			return $front_page_id > 0 && $front_page_id === $queried_id;
		}
		return true;
	}

	/** Reset duplicate guards for tests. */
	public static function reset_runtime_guards() {
		self::$feed_rendered = false;
		self::$single_context_rendered = array();
		if ( class_exists( __NAMESPACE__ . '\\Shortcodes' ) ) { Shortcodes::reset_runtime_guards(); }
	}

	/** Append structured context to single post content once. */
	public static function append_single_post_context( $content ) {
		if ( ! self::is_single_post_request() ) { return $content; }
		if ( function_exists( 'in_the_loop' ) && ! in_the_loop() ) { return $content; }
		if ( function_exists( 'is_main_query' ) && ! is_main_query() ) { return $content; }
		$post_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		if ( $post_id <= 0 || isset( self::$single_context_rendered[ $post_id ] ) || ! PostMetadata::user_can_view( $post_id ) ) { return $content; }
		self::$single_context_rendered[ $post_id ] = true;
		return $content . PostMetadata::render_single_context( $post_id );
	}

	/** Provide a create URL to Shell without claiming a content slot. */
	public static function filter_shell_create_url( $url ) {
		$settings = Settings::get();
		if ( empty( $settings['composer']['public_composer_enabled'] ) || SafeMode::public_features_disabled() ) { return $url; }
		if ( ! empty( $settings['integrations']['composer_page_url'] ) ) { return esc_url_raw( $settings['integrations']['composer_page_url'] ); }
		return $url;
	}

	/** Append runtime status to Shell diagnostics. */
	public static function append_shell_report( $rows ) {
		if ( ! is_array( $rows ) ) { return $rows; }
		$settings = Settings::get();
		$integrations = Integrations::detect();
		$shell = isset( $integrations['shell'] ) && is_array( $integrations['shell'] ) ? $integrations['shell'] : array();
		$status = 'Unknown';
		$detail = __( 'No confirmed Unified Shell runtime signal was detected; File 21 retains bounded public fallbacks.', 'sabri-complete-home-news-feed' );
		if ( SafeMode::public_features_disabled() ) {
			$status = 'Disabled';
			$detail = __( 'Home Feed and Composer runtime is disabled by Safe Mode or Emergency Disable.', 'sabri-complete-home-news-feed' );
		} elseif ( ! empty( $shell['status'] ) && 'Connected' === $shell['status'] ) {
			$status = 'Connected';
			$detail = __( 'Unified Shell is detected; File 21 uses its native slot when available and does not replace global layout.', 'sabri-complete-home-news-feed' );
		} elseif ( ! empty( $settings['integrations']['composer_page_url'] ) ) {
			$status = 'Configured';
			$detail = __( 'Composer page URL is configured; public Home fallbacks remain available.', 'sabri-complete-home-news-feed' );
		}
		$rows[] = array( 'label' => __( 'Home Feed and Composer runtime', 'sabri-complete-home-news-feed' ), 'status' => $status, 'detail' => $detail );
		return $rows;
	}
}
