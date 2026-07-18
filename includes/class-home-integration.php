<?php
/**
 * Home runtime integration.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrates without replacing Shell header, sidebar, or layout resolver.
 */
final class HomeIntegration {
	/**
	 * Whether a feed instance has rendered.
	 *
	 * @var bool
	 */
	private static $feed_rendered = false;

	/**
	 * Whether single context was appended.
	 *
	 * @var array<int,bool>
	 */
	private static $single_context_rendered = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'sabri_feed_home_center_content', array( __CLASS__, 'render_home_center' ) );
		}

		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'append_single_post_context' ), 20 );
			add_filter( 'sabri_shell_create_url', array( __CLASS__, 'filter_shell_create_url' ) );
			add_filter( 'sabri_shell_system_check_report', array( __CLASS__, 'append_shell_report' ) );
		}
	}

	/**
	 * Render plugin-owned Home center content.
	 *
	 * @return void
	 */
	public static function render_home_center() {
		echo self::render_feed_once( 'plugin_owned_hook', array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render feed once per request.
	 *
	 * @param string              $source Source.
	 * @param array<string,mixed> $atts Attributes.
	 * @return string
	 */
	public static function render_feed_once( $source, array $atts = array() ) {
		unset( $source );

		if ( self::$feed_rendered ) {
			return '';
		}

		self::$feed_rendered = true;
		return FeedRenderer::render( $atts );
	}

	/**
	 * Reset duplicate guards for tests.
	 *
	 * @return void
	 */
	public static function reset_runtime_guards() {
		self::$feed_rendered = false;
		self::$single_context_rendered = array();
	}

	/**
	 * Append structured context to single post content once.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function append_single_post_context( $content ) {
		if ( function_exists( 'is_singular' ) && ! is_singular( 'post' ) ) {
			return $content;
		}

		if ( function_exists( 'in_the_loop' ) && ! in_the_loop() ) {
			return $content;
		}

		if ( function_exists( 'is_main_query' ) && ! is_main_query() ) {
			return $content;
		}

		$post_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		if ( $post_id <= 0 || isset( self::$single_context_rendered[ $post_id ] ) || ! PostMetadata::user_can_view( $post_id ) ) {
			return $content;
		}

		self::$single_context_rendered[ $post_id ] = true;
		return $content . PostMetadata::render_single_context( $post_id );
	}

	/**
	 * Provide a create URL to Shell without claiming a content slot.
	 *
	 * @param string $url Existing URL.
	 * @return string
	 */
	public static function filter_shell_create_url( $url ) {
		$settings = Settings::get();
		if ( empty( $settings['composer']['public_composer_enabled'] ) || SafeMode::public_features_disabled() ) {
			return $url;
		}

		if ( ! empty( $settings['integrations']['composer_page_url'] ) ) {
			return esc_url_raw( $settings['integrations']['composer_page_url'] );
		}

		return $url;
	}

	/**
	 * Append Phase 2 status to Shell diagnostics.
	 *
	 * @param mixed $rows Rows.
	 * @return mixed
	 */
	public static function append_shell_report( $rows ) {
		if ( ! is_array( $rows ) ) {
			return $rows;
		}

		$rows[] = array(
			'label'  => __( 'Home Feed and Composer runtime', 'sabri-complete-home-news-feed' ),
			'status' => SafeMode::public_features_disabled() ? 'Disabled' : 'Connected',
			'detail' => __( 'Phase 2 renders by shortcode or plugin-owned hook only; no Shell header, sidebar, or layout resolver is replaced.', 'sabri-complete-home-news-feed' ),
		);

		return $rows;
	}
}
