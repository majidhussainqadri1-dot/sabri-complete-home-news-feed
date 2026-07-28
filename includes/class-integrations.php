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

	/** Confirmed public Shell 1.0.0 hooks. */
	public static function confirmed_shell_hooks() {
		return array(
			'sabri_shell_navigation_destinations' => 'filter',
			'sabri_shell_home_feed_post_types' => 'filter',
			'sabri_shell_create_url' => 'filter',
			'sabri_shell_layout_mode' => 'filter',
			'sabri_shell_system_check_report' => 'filter',
			'sabri_shell_complete_repair_ran' => 'action',
		);
	}

	/** Required File 20 harmonization slots. */
	public static function required_shell_slots() {
		return array(
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

	/** Proposed future integrations are superseded by required versioned slots. */
	public static function proposed_future_integrations() {
		return self::required_shell_slots();
	}

	/** Read the machine-advertised slot contract from File 20. */
	public static function advertised_shell_slots() {
		$slots = function_exists( 'apply_filters' ) ? apply_filters( 'sabri_shell_rendering_slots', array() ) : array();
		if ( ! is_array( $slots ) ) {
			return array();
		}
		if ( array_is_list( $slots ) ) {
			$normalized = array();
			foreach ( $slots as $slot ) {
				$key = self::clean_key( $slot );
				if ( '' !== $key ) {
					$normalized[ $key ] = 'action';
				}
			}
			return $normalized;
		}
		$normalized = array();
		foreach ( $slots as $slot => $type ) {
			$key = self::clean_key( $slot );
			$type = self::clean_key( $type );
			if ( '' !== $key ) {
				$normalized[ $key ] = '' !== $type ? $type : 'action';
			}
		}
		return $normalized;
	}

	/** Exact Shell slot acceptance state. */
	public static function shell_slot_status() {
		$required = self::required_shell_slots();
		$advertised = self::advertised_shell_slots();
		$missing = array();
		foreach ( $required as $slot => $type ) {
			if ( ! isset( $advertised[ $slot ] ) || $type !== $advertised[ $slot ] ) {
				$missing[] = $slot;
			}
		}
		return array(
			'connected' => empty( $missing ),
			'required' => $required,
			'advertised' => $advertised,
			'missing' => $missing,
		);
	}

	/** Detect Shell and all known companion states. */
	public static function detect() {
		$registry = CompanionIntegrationRegistry::all();
		$shell = isset( $registry['shell'] ) ? $registry['shell'] : array( 'status' => 'Missing', 'evidence' => array() );
		$slot_status = self::shell_slot_status();
		$shell_status = isset( $shell['status'] ) ? $shell['status'] : 'Missing';
		if ( 'Missing' !== $shell_status && ! $slot_status['connected'] ) {
			$shell_status = 'Incomplete';
		}
		return array(
			'shell' => array(
				'status' => $shell_status,
				'version' => defined( 'SABRI_SHELL_VERSION' ) ? SABRI_SHELL_VERSION : '',
				'confirmed_hooks' => self::confirmed_shell_hooks(),
				'required_slots' => self::required_shell_slots(),
				'advertised_slots' => $slot_status['advertised'],
				'missing_slots' => $slot_status['missing'],
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

	/** Add truthful File 21 and companion contract status to Shell System Check. */
	public static function append_shell_system_check_report( $rows ) {
		if ( ! is_array( $rows ) ) {
			return $rows;
		}
		$slot_status = self::shell_slot_status();
		if ( $slot_status['connected'] ) {
			$status = 'Connected';
			$detail = __( 'File 20 owns the global Shell, advertises every required Home and News rendering slot, and File 21 is the canonical content provider.', 'sabri-complete-home-news-feed' );
		} else {
			$status = defined( 'SABRI_SHELL_VERSION' ) ? 'Incomplete' : 'Missing';
			$detail = sprintf(
				/* translators: %s: comma-separated Shell slot names. */
				__( 'File 21 cannot receive final native placement. Missing File 20 slots: %s.', 'sabri-complete-home-news-feed' ),
				implode( ', ', $slot_status['missing'] )
			);
		}
		$rows[] = array(
			'label' => __( 'Home and News Feed foundation', 'sabri-complete-home-news-feed' ),
			'status' => $status,
			'detail' => $detail,
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

	/** Pure controlled-key sanitizer for integration contracts. */
	private static function clean_key( $value ) {
		$value = strtolower( (string) $value );
		$value = preg_replace( '/[^a-z0-9_\-]/', '', $value );
		return is_string( $value ) ? $value : '';
	}
}
