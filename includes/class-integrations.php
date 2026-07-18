<?php
/**
 * Unified Shell integration contract.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects the Shell without requiring it.
 */
final class Integrations {
	/**
	 * Register optional Shell filters.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'sabri_shell_system_check_report', array( __CLASS__, 'append_shell_system_check_report' ) );
			add_filter( 'sabri_shell_home_feed_post_types', array( __CLASS__, 'filter_shell_home_feed_post_types' ) );
		}
	}

	/**
	 * Confirmed public Shell hooks inspected from the sibling repository.
	 *
	 * @return array<string,string>
	 */
	public static function confirmed_shell_hooks() {
		return array(
			'sabri_shell_navigation_destinations' => 'filter',
			'sabri_shell_home_feed_post_types'   => 'filter',
			'sabri_shell_create_url'             => 'filter',
			'sabri_shell_layout_mode'            => 'filter',
			'sabri_shell_system_check_report'    => 'filter',
			'sabri_shell_complete_repair_ran'    => 'action',
		);
	}

	/**
	 * Plugin-owned fallback hooks for future reviewed integration.
	 *
	 * @return array<string,string>
	 */
	public static function plugin_owned_hooks() {
		return array(
			'sabri_feed_home_center_content'          => 'action',
			'sabri_feed_home_contextual_sidebar'      => 'action',
			'sabri_feed_news_center_content'          => 'action',
			'sabri_feed_news_contextual_sidebar'      => 'action',
			'sabri_feed_post_detail_context'          => 'action',
			'sabri_feed_mobile_context_drawer_modules' => 'filter',
		);
	}

	/**
	 * Proposed future Shell integration points.
	 *
	 * @return array<string,string>
	 */
	public static function proposed_future_integrations() {
		return array(
			'home_center_content'          => 'Proposed Shell slot for a Home feed center column. Not confirmed in Shell 1.0.0.',
			'home_contextual_sidebar'      => 'Proposed Shell slot for Home-specific right sidebar modules. Not confirmed in Shell 1.0.0.',
			'news_center_content'          => 'Proposed Shell slot for News center content. Not confirmed in Shell 1.0.0.',
			'news_contextual_sidebar'      => 'Proposed Shell slot for News right sidebar modules. Not confirmed in Shell 1.0.0.',
			'post_detail_context'          => 'Proposed Shell slot for post detail companion context. Not confirmed in Shell 1.0.0.',
			'mobile_context_drawer_modules' => 'Proposed Shell mobile drawer module filter. Not confirmed in Shell 1.0.0.',
		);
	}

	/**
	 * Detect Shell and companion states.
	 *
	 * @return array<string,mixed>
	 */
	public static function detect() {
		$shell_active = defined( 'SABRI_SHELL_VERSION' ) || class_exists( 'Sabri\\UnifiedShell\\Plugin' );

		return array(
			'shell'           => array(
				'status'        => $shell_active ? 'Connected' : 'Missing',
				'version'       => defined( 'SABRI_SHELL_VERSION' ) ? SABRI_SHELL_VERSION : '',
				'confirmed_hooks' => self::confirmed_shell_hooks(),
				'plugin_owned_hooks' => self::plugin_owned_hooks(),
				'proposed_future' => self::proposed_future_integrations(),
			),
			'notifications'   => self::shortcode_or_function_state( 'sabri_notifications', 'sabri_notifications_render' ),
			'messages'        => self::shortcode_or_function_state( 'sabri_messages', 'sabri_messages_render' ),
			'appointments'    => self::shortcode_or_function_state( 'sabri_appointments', 'sabri_appointments_render' ),
			'marketplace'     => function_exists( 'post_type_exists' ) && post_type_exists( 'product' ) ? 'Connected' : 'Missing',
		);
	}

	/**
	 * Keep the Shell feed on core posts in Phase 1; no duplicate custom output.
	 *
	 * @param array<int,string> $post_types Post types.
	 * @return array<int,string>
	 */
	public static function filter_shell_home_feed_post_types( $post_types ) {
		$post_types = is_array( $post_types ) ? $post_types : array();
		$post_types[] = 'post';
		return array_values( array_unique( array_filter( $post_types ) ) );
	}

	/**
	 * Add this plugin's status to Shell system checks when the Shell asks.
	 *
	 * @param mixed $rows Existing rows.
	 * @return mixed
	 */
	public static function append_shell_system_check_report( $rows ) {
		if ( ! is_array( $rows ) ) {
			return $rows;
		}

		$rows[] = array(
			'label'  => __( 'Home and News Feed foundation', 'sabri-complete-home-news-feed' ),
			'status' => 'Connected',
			'detail' => __( 'Phase 1 foundation plugin is active. Full feed rendering is reserved for later phases.', 'sabri-complete-home-news-feed' ),
		);

		return $rows;
	}

	/**
	 * Detect a shortcode or function integration.
	 *
	 * @param string $shortcode Shortcode.
	 * @param string $function Function name.
	 * @return string
	 */
	private static function shortcode_or_function_state( $shortcode, $function ) {
		if ( function_exists( $function ) ) {
			return 'Connected';
		}

		if ( function_exists( 'shortcode_exists' ) && shortcode_exists( $shortcode ) ) {
			return 'Available but not configured';
		}

		return 'Missing';
	}
}
