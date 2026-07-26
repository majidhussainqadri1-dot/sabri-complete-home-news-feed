<?php
/**
 * Canonical companion-module integration registry.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Detects actual numbered-file contracts instead of guessed shortcode names. */
final class CompanionIntegrationRegistry {
	/** Register diagnostics filters. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'sabri_hnf_companion_integrations', array( __CLASS__, 'filter_registry' ) );
		}
	}

	/** Canonical definitions for current project modules and legacy aliases. */
	public static function definitions() {
		return array(
			'membership' => array( 'label' => 'Membership Core', 'constants' => array( 'SABRI_MEMBERSHIP_CORE_VERSION' ), 'classes' => array( 'Sabri_Membership_Core', 'Sabri\\Membership\\Plugin' ) ),
			'profiles' => array( 'label' => 'Profiles and Doctors', 'shortcodes' => array( 'sabri_founder_profile', 'sabri_member_profile' ) ),
			'shell' => array( 'label' => 'Unified Application Shell', 'constants' => array( 'SABRI_SHELL_VERSION' ), 'classes' => array( 'Sabri\\UnifiedShell\\Plugin' ), 'shortcodes' => array( 'sabri_shell_home_feed' ) ),
			'notifications' => array( 'label' => 'Unified Notifications', 'constants' => array( 'SABRI_UNIFIED_NOTIFICATIONS_VERSION', 'SUN_VERSION' ), 'classes' => array( 'Sabri\\Notifications\\Plugin' ), 'shortcodes' => array( 'sabri_notifications' ), 'functions' => array( 'sabri_notifications_render' ), 'hooks' => array( 'sun_notify' ) ),
			'network' => array( 'label' => 'Network and Messaging', 'constants' => array( 'SABRI_NETWORK_VERSION' ), 'classes' => array( 'Sabri\\Network\\Plugin' ), 'shortcodes' => array( 'sabri_network', 'sabri_messages' ), 'functions' => array( 'sabri_network_render', 'sabri_messages_render' ) ),
			'appointments' => array( 'label' => 'Worldwide Clinic and Appointments', 'constants' => array( 'SWC_VERSION', 'SABRI_WORLDWIDE_CLINIC_VERSION' ), 'classes' => array( 'Sabri\\WorldwideClinic\\Plugin' ), 'shortcodes' => array( 'swc_worldwide_clinic', 'swc_request_appointment', 'swc_my_appointments', 'swc_doctor_appointments', 'sabri_appointments' ), 'functions' => array( 'swc_render_worldwide_clinic', 'sabri_appointments_render' ) ),
			'marketplace' => array( 'label' => 'Marketplace', 'constants' => array( 'SABRI_MARKETPLACE_VERSION' ), 'classes' => array( 'Sabri\\Marketplace\\Plugin' ), 'shortcodes' => array( 'sabri_marketplace' ), 'functions' => array( 'sabri_marketplace_render' ), 'table_suffixes' => array( 'sabri_marketplace_products', 'sabri_marketplace_sellers' ) ),
			'legacy_feed' => array( 'label' => 'Legacy Social News and Publications', 'constants' => array( 'SNP_VERSION' ), 'classes' => array( 'SNP_Plugin' ), 'shortcodes' => array( 'sabri_platform_home', 'sabri_news_feed', 'sabri_news_home' ), 'post_types' => array( 'snp_publication' ) ),
			'learning' => array( 'label' => 'Learning', 'shortcodes' => array( 'slc_learning_home', 'sabri_learning' ) ),
			'encyclopedia' => array( 'label' => 'Encyclopedia', 'shortcodes' => array( 'he_encyclopedia_home', 'sabri_encyclopedia' ) ),
			'doctors' => array( 'label' => 'Doctors Directory', 'shortcodes' => array( 'sdd_doctors_directory', 'sabri_doctors' ) ),
			'video_wall' => array( 'label' => 'Video Wall', 'shortcodes' => array( 'svw_video_wall', 'sabri_video_wall' ) ),
			'reels' => array( 'label' => 'Reels', 'shortcodes' => array( 'srl_reels', 'sabri_reels' ) ),
			'pdf_library' => array( 'label' => 'PDF Library', 'shortcodes' => array( 'spl_library', 'sabri_pdf_library' ) ),
			'radar' => array( 'label' => 'Radar', 'shortcodes' => array( 'srf_radar', 'sabri_radar' ) ),
			'ai' => array( 'label' => 'Sabri Classical Homeopathy AI', 'shortcodes' => array( 'sai_study_guide', 'sabri_ai' ) ),
		);
	}

	/** Complete registry state. */
	public static function all() {
		$out = array();
		foreach ( self::definitions() as $key => $definition ) {
			$out[ $key ] = self::detect_definition( $key, $definition );
		}
		return function_exists( 'apply_filters' ) ? apply_filters( 'sabri_hnf_companion_registry_state', $out ) : $out;
	}

	/** One service state. */
	public static function service( $key ) {
		$key = self::clean_key( $key );
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : array( 'key' => $key, 'status' => 'Missing', 'evidence' => array() );
	}

	/** Filter callback preserving an existing registry payload. */
	public static function filter_registry( $registry ) {
		$registry = is_array( $registry ) ? $registry : array();
		return array_merge( $registry, self::all() );
	}

	/** Detect one definition through multiple independent signals. */
	private static function detect_definition( $key, array $definition ) {
		$evidence = array();
		$bootstrap_only = false;
		foreach ( isset( $definition['constants'] ) ? $definition['constants'] : array() as $constant ) {
			if ( defined( $constant ) ) { $evidence[] = 'constant:' . $constant; $bootstrap_only = true; }
		}
		foreach ( isset( $definition['classes'] ) ? $definition['classes'] : array() as $class ) {
			if ( class_exists( $class ) ) { $evidence[] = 'class:' . $class; $bootstrap_only = true; }
		}
		foreach ( isset( $definition['functions'] ) ? $definition['functions'] : array() as $function ) {
			if ( function_exists( $function ) ) { $evidence[] = 'function:' . $function; $bootstrap_only = false; }
		}
		foreach ( isset( $definition['shortcodes'] ) ? $definition['shortcodes'] : array() as $shortcode ) {
			if ( function_exists( 'shortcode_exists' ) && shortcode_exists( $shortcode ) ) { $evidence[] = 'shortcode:' . $shortcode; $bootstrap_only = false; }
		}
		foreach ( isset( $definition['post_types'] ) ? $definition['post_types'] : array() as $post_type ) {
			if ( function_exists( 'post_type_exists' ) && post_type_exists( $post_type ) ) { $evidence[] = 'post_type:' . $post_type; $bootstrap_only = false; }
		}
		foreach ( isset( $definition['hooks'] ) ? $definition['hooks'] : array() as $hook ) {
			if ( function_exists( 'has_action' ) && has_action( $hook ) ) { $evidence[] = 'hook:' . $hook; $bootstrap_only = false; }
		}
		foreach ( isset( $definition['table_suffixes'] ) ? $definition['table_suffixes'] : array() as $suffix ) {
			if ( self::table_exists( $suffix ) ) { $evidence[] = 'table:' . $suffix; $bootstrap_only = false; }
		}
		$status = empty( $evidence ) ? 'Missing' : ( $bootstrap_only ? 'Available but not configured' : 'Connected' );
		return array(
			'key' => $key,
			'label' => isset( $definition['label'] ) ? $definition['label'] : $key,
			'status' => $status,
			'evidence' => array_values( array_unique( $evidence ) ),
			'contracts' => $definition,
		);
	}

	/** Detect a known custom table without coupling to one exact table prefix. */
	private static function table_exists( $suffix ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return false;
		}
		$table = $wpdb->prefix . ltrim( (string) $suffix, '_' );
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/** Normalize a service key. */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}
}