<?php
/**
 * Unified Shell and companion integration contracts.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Detects the Shell and numbered companion modules without hard dependencies. */
final class Integrations {
	/** Register optional Shell filters. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'sabri_shell_system_check_report', array( __CLASS__, 'append_shell_system_check_report' ) );
			add_filter( 'sabri_shell_home_feed_post_types', array( __CLASS__, 'filter_shell_home_feed_post_types' ) );
		}
	}

	/** Confirmed public Shell hooks. */
	public static function confirmed_shell_hooks() {
		return array(
			'sabri_shell_navigation_destinations' => 'filter',
			'sabri_shell_home_feed_post_types' => 'filter',
			'sabri_shell_create_url' => 'filter',
			'sabri_shell_layout_mode' => 'filter',
			'sabri_shell_system_check_report' => 'filter',
			'sabri_shell_complete_repair_ran' => 'action',
			// File 20 harmonization line adds these official rendering slots.
			'sabri_shell_home_before_main' => 'action',
			'sabri_shell_home_main' => 'action',
			'sabri_shell_home_after_main' => 'action',
			'sabri_shell_home_right_sidebar' => 'action',
			'sabri_shell_news_main' => 'action',
		);
	}

	/** Plugin-owned fallback hooks retained for backward compatibility. */
	public static function plugin_owned_hooks() {
		return array(
			'sabri_feed_home_center_content' => 'action',
			'sabri_feed_home_contextual_sidebar' => 'action',
			'sabri_feed_news_center_content' => 'action',
			'sabri_feed_news_contextual_sidebar' => 'action',
			'sabri_feed_post_detail_context' => 'action',
			'sabri_feed_mobile_context_drawer_modules' => 'filter',
		);
	}

	/** Historical proposed hooks, now superseded by the official File 20 slot contract. */
	public static function proposed_future_integrations() {
		return array();
	}

	/** Detect Shell and all known companion states. */
	public static function detect() {
		$registry = CompanionIntegrationRegistry::all();
		$shell = isset( $registry['shell'] ) ? $registry['shell'] : array( 'status' => 'Missing', 'evidence' => array() );
		return array(
			'shell' => array(
				'status' => isset( $shell['status'] ) ? $shell['status'] : 'Missing',
				'version' => defined( 'SABRI_SHELL_VERSION' ) ? SABRI_SHELL_VERSION : '',
				'confirmed_hooks' => self::confirmed_shell_hooks(),
				'plugin_owned_hooks' => self::plugin_owned_hooks(),
				'proposed_future' => array(),
				'evidence' => isset( $shell['evidence'] ) ? $shell['evidence'] : array(),
			),
			'notifications' => self::status( $registry, 'notifications' ),
			'messages' => self::status( $registry, 'network' ),
			'network' => self::status( $registry, 'network' ),
			'appointments' => self::status( $registry, 'appointments' ),
			'marketplace' => self::status( $registry, 'marketplace' ),
			'profiles' => self::status( $registry, 'profiles' ),
			'membership' => self::status( $registry, 'membership' ),
			'legacy_feed' => self::status( $registry, 'legacy_feed' ),
			'registry' => $registry,
		);
	}

	/** Keep the Shell Feed on core posts; File 21 owns normalized rendering. */
	public static function filter_shell_home_feed_post_types( $post_types ) {
		$post_types = is_array( $post_types ) ? $post_types : array();
		$post_types[] = 'post';
		return array_values( array_unique( array_filter( $post_types ) ) );
	}

	/** Add File 21 and companion contract status to Shell System Check. */
	public static function append_shell_system_check_report( $rows ) {
		if ( ! is_array( $rows ) ) {
			return $rows;
		}
		$rows[] = array(
			'label' => __( 'Home and News Feed foundation', 'sabri-complete-home-news-feed' ),
			'status' => 'Connected',
			'detail' => __( 'File 21 is the canonical Home/News content engine. File 20 owns the global Shell and official rendering slots.', 'sabri-complete-home-news-feed' ),
		);
		foreach ( CompanionIntegrationRegistry::all() as $service ) {
			$rows[] = array(
				'label' => isset( $service['label'] ) ? $service['label'] : __( 'Companion module', 'sabri-complete-home-news-feed' ),
				'status' => isset( $service['status'] ) ? $service['status'] : 'Missing',
				'detail' => ! empty( $service['evidence'] ) ? implode( ', ', array_map( 'sanitize_text_field', $service['evidence'] ) ) : __( 'No accepted runtime evidence detected.', 'sabri-complete-home-news-feed' ),
			);
		}
		return $rows;
	}

	/** Service status helper. */
	private static function status( array $registry, $key ) {
		return isset( $registry[ $key ]['status'] ) ? $registry[ $key ]['status'] : 'Missing';
	}
}