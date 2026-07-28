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

/** Detects the Shell and numbered companion modules without false positives. */
final class Integrations {
	/** Register optional Shell filters. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'sabri_shell_system_check_report', array( __CLASS__, 'append_shell_system_check_report' ) );
			add_filter( 'sabri_shell_home_feed_post_types', array( __CLASS__, 'filter_shell_home_feed_post_types' ) );
		}
	}

	/** Confirmed public Shell hooks that existed before native File 21 slots. */
	public static function confirmed_shell_hooks() {
		return array(
			'sabri_shell_navigation_destinations' => 'filter',
			'sabri_shell_home_feed_post_types'     => 'filter',
			'sabri_shell_create_url'               => 'filter',
			'sabri_shell_layout_mode'              => 'filter',
			'sabri_shell_system_check_report'      => 'filter',
			'sabri_shell_complete_repair_ran'      => 'action',
		);
	}

	/** Required File 20 native slots. */
	public static function required_shell_slots() {
		return array(
			'sabri_shell_home_before_main'   => 'action',
			'sabri_shell_home_main'          => 'action',
			'sabri_shell_home_after_main'    => 'action',
			'sabri_shell_home_right_sidebar' => 'action',
			'sabri_shell_news_main'          => 'action',
		);
	}

	/** Plugin-owned compatibility hooks retained for older Shell versions. */
	public static function plugin_owned_hooks() {
		return array(
			'sabri_feed_home_center_content'         => 'action',
			'sabri_feed_home_contextual_sidebar'      => 'action',
			'sabri_feed_news_center_content'          => 'action',
			'sabri_feed_news_contextual_sidebar'       => 'action',
			'sabri_feed_post_detail_context'           => 'action',
			'sabri_feed_mobile_context_drawer_modules' => 'filter',
		);
	}

	/** Audit the Shell's explicit machine-readable slot advertisement. */
	public static function shell_slot_audit() {
		$advertised = array();
		if ( defined( 'SABRI_SHELL_NATIVE_SLOTS' ) && is_array( SABRI_SHELL_NATIVE_SLOTS ) ) {
			$advertised = SABRI_SHELL_NATIVE_SLOTS;
		}
		if ( function_exists( 'apply_filters' ) ) {
			$advertised = apply_filters( 'sabri_shell_available_slots', $advertised );
			$advertised = apply_filters( 'sabri_shell_native_slots', $advertised );
		}
		$normalized = array();
		if ( is_array( $advertised ) ) {
			foreach ( $advertised as $key => $value ) {
				$candidate = is_string( $key ) && ! is_numeric( $key ) ? $key : ( is_scalar( $value ) ? (string) $value : '' );
				if ( '' !== $candidate ) { $normalized[] = $candidate; }
			}
		}
		$advertised = array_values( array_unique( $normalized ) );
		$required   = array_keys( self::required_shell_slots() );
		$missing    = array_values( array_diff( $required, $advertised ) );
		return array(
			'advertised' => $advertised,
			'required'   => $required,
			'missing'    => $missing,
			'complete'   => empty( $missing ),
		);
	}

	/** Detect Shell and all known companion states. */
	public static function detect() {
		$registry = CompanionIntegrationRegistry::all();
		$shell    = isset( $registry['shell'] ) ? $registry['shell'] : array( 'status' => 'Missing', 'evidence' => array() );
		$slots    = self::shell_slot_audit();
		$status   = isset( $shell['status'] ) ? (string) $shell['status'] : 'Missing';
		if ( 'Missing' !== $status ) {
			$status = $slots['complete'] ? 'Connected' : 'Compatibility fallback';
		}
		return array(
			'shell' => array(
				'status'             => $status,
				'version'            => defined( 'SABRI_SHELL_VERSION' ) ? SABRI_SHELL_VERSION : '',
				'confirmed_hooks'    => self::confirmed_shell_hooks(),
				'required_slots'     => self::required_shell_slots(),
				'advertised_slots'   => $slots['advertised'],
				'missing_slots'      => $slots['missing'],
				'native_slots_ready' => $slots['complete'],
				'plugin_owned_hooks' => self::plugin_owned_hooks(),
				'evidence'           => isset( $shell['evidence'] ) ? $shell['evidence'] : array(),
			),
			'notifications' => self::status( $registry, 'notifications' ),
			'messages'      => self::status( $registry, 'network' ),
			'network'       => self::status( $registry, 'network' ),
			'appointments'  => self::status( $registry, 'appointments' ),
			'marketplace'   => self::status( $registry, 'marketplace' ),
			'profiles'      => self::status( $registry, 'profiles' ),
			'membership'    => self::status( $registry, 'membership' ),
			'legacy_feed'   => self::status( $registry, 'legacy_feed' ),
			'registry'      => $registry,
		);
	}

	/** Keep the Shell Feed on core posts; File 21 owns normalized rendering. */
	public static function filter_shell_home_feed_post_types( $post_types ) {
		$post_types   = is_array( $post_types ) ? $post_types : array();
		$post_types[] = 'post';
		return array_values( array_unique( array_filter( $post_types ) ) );
	}

	/** Add truthful File 21/Shell status to Shell System Check. */
	public static function append_shell_system_check_report( $rows ) {
		if ( ! is_array( $rows ) ) { return $rows; }
		$detected = self::detect();
		$shell    = $detected['shell'];
		$detail   = __( 'File 21 compatibility fallbacks are available, but final native placement requires all five File 20 slots.', 'sabri-complete-home-news-feed' );
		if ( 'Missing' === $shell['status'] ) {
			$detail = __( 'File 20 Unified Shell was not detected. File 21 will use guarded compatibility mounts only.', 'sabri-complete-home-news-feed' );
		} elseif ( ! empty( $shell['native_slots_ready'] ) ) {
			$detail = __( 'File 20 explicitly advertises every required native Home and News slot.', 'sabri-complete-home-news-feed' );
		} elseif ( ! empty( $shell['missing_slots'] ) ) {
			$detail .= ' ' . sprintf( __( 'Missing: %s', 'sabri-complete-home-news-feed' ), implode( ', ', array_map( 'sanitize_text_field', $shell['missing_slots'] ) ) );
		}
		$rows[] = array(
			'label'  => __( 'Home and News Feed / Unified Shell contract', 'sabri-complete-home-news-feed' ),
			'status' => $shell['status'],
			'detail' => $detail,
		);
		foreach ( CompanionIntegrationRegistry::all() as $service ) {
			$rows[] = array(
				'label'  => isset( $service['label'] ) ? $service['label'] : __( 'Companion module', 'sabri-complete-home-news-feed' ),
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
