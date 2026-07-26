<?php
/**
 * Versioned settings architecture.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Handles structured settings and per-tab sanitization. */
final class Settings {
	const OPTION_NAME = 'sabri_feed_settings';

	/** Register WordPress settings. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		}
	}

	/** Register the option for admin forms. */
	public static function register_settings() {
		if ( function_exists( 'register_setting' ) ) {
			register_setting(
				'sabri_feed_settings',
				self::OPTION_NAME,
				array(
					'type' => 'array',
					'sanitize_callback' => array( __CLASS__, 'sanitize_full' ),
					'default' => self::defaults(),
				)
			);
		}
	}

	/** Settings namespaces. */
	public static function namespaces() {
		return array( 'general', 'feed', 'news', 'composer', 'capabilities', 'moderation', 'media', 'performance', 'privacy', 'integrations', 'advanced' );
	}

	/** Safe canonical defaults. */
	public static function defaults() {
		return array(
			'version' => SABRI_HNF_VERSION,
			'general' => array(
				'enabled' => 1,
				'environment' => 'staging',
				'phase' => 'comprehensive_harmonization',
				'future_notice' => 'File 22 owns the complete public visual experience; File 21 owns Home, News, publishing and data contracts.',
				'admin_accent_hex' => '#f26100',
			),
			'feed' => array(
				'enabled' => 1,
				'default_mode' => 'for-you',
				'default_visibility' => 'public',
				'default_count' => 10,
				'posts_per_page' => 10,
				'pagination' => 'numbers',
				'load_more_enabled' => 1,
				'founder_priority' => 20,
				'verified_author_priority' => 8,
				'enabled_filters' => array_keys( FeedContext::modes() ),
				'allowed_types' => array_keys( Taxonomies::feed_type_terms() ),
				'show_author_details' => 1,
				'show_post_type' => 1,
				'show_media' => 1,
				'show_disclaimer' => 1,
				'cache_duration' => 300,
				'future_notice' => 'File 22 may replace presentation but must consume these canonical Feed contracts.',
			),
			'news' => array(
				'enabled' => 0,
				'breaking_news_enabled' => 0,
				'source_url' => '',
				'future_notice' => 'Public Editorial News remains gate-controlled and fail-closed by default.',
			),
			'composer' => array(
				'public_composer_enabled' => 1,
				'max_upload_mb' => 8,
				'max_image_count' => 4,
				'allowed_mime_types' => array( 'image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'video/mp4', 'video/quicktime', 'audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/ogg' ),
				'allowed_feed_types' => FeedContext::phase2_feed_type_slugs(),
				'allowed_visibility_modes' => array( 'public', 'members', 'doctors', 'students', 'patients', 'private' ),
				'immediate_publish_policy' => 'capability',
				'review_required_policy' => 'unverified_doctors',
				'require_patient_consent' => 1,
				'require_medical_disclaimer' => 1,
				'scheduling_enabled' => 0,
				'drafts_enabled' => 1,
				'previews_enabled' => 1,
				'comments_metadata_enabled' => 1,
				'future_notice' => 'Social Composer authority is separate from the institutional Editorial Newsroom.',
			),
			'capabilities' => array(
				'founder_roles' => array( 'founder', 'sabri_founder' ),
				'verified_doctor_roles' => array( 'verified_doctor', 'approved_doctor', 'doctor_verified', 'sabri_verified_doctor' ),
				'unverified_doctor_roles' => array( 'doctor', 'sabri_doctor', 'sabri_doctor_pending' ),
				'student_roles' => array( 'student', 'sabri_student' ),
				'patient_roles' => array( 'patient', 'sabri_patient', 'subscriber' ),
				'editorial_roles' => array( 'editor' ),
				'verified_doctor_policy' => 'trusted',
				'future_notice' => 'Founder and Administrator publish immediately; institutionally trusted verified Doctors publish immediately; other Doctors require review.',
			),
			'moderation' => array(
				'reports_enabled' => 0,
				'default_report_state' => 'open',
				'future_notice' => 'Moderation remains fail-closed and audit-logged.',
			),
			'media' => array(
				'uploads_enabled' => 1,
				'max_items' => 4,
				'future_notice' => 'Advanced media processing belongs to the owning media modules.',
			),
			'performance' => array(
				'cache_seconds' => 300,
				'log_views' => 0,
				'future_notice' => 'Viral signals remain bounded and privacy-safe.',
			),
			'privacy' => array(
				'retain_data_on_uninstall' => 1,
				'anonymize_views' => 1,
				'export_private_saves' => 1,
				'future_notice' => 'Private counts, moderation data and patient identity never enter public projections.',
			),
			'integrations' => array(
				'shell_required' => 0,
				'shell_home_url' => '',
				'shell_news_url' => '',
				'composer_page_url' => '',
				'functions' => array( 'notifications' => '', 'network' => '', 'messages' => '', 'appointments' => '' ),
				'confirmed_hooks' => Integrations::confirmed_shell_hooks(),
				'required_shell_slots' => Integrations::required_shell_slots(),
				'future_notice' => 'Companion status is resolved by the canonical integration registry, not guessed names.',
			),
			'advanced' => array(
				'safe_mode_enabled' => 1,
				'emergency_disabled' => 0,
				'debug_diagnostics' => 0,
				'allow_destructive_repair' => 0,
				'future_notice' => 'Destructive repair remains disabled.',
			),
		);
	}

	/** Return merged settings. */
	public static function get() {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();
		return self::merge_defaults( is_array( $stored ) ? $stored : array(), self::defaults() );
	}

	/** Ensure defaults exist without dropping future keys. */
	public static function ensure_defaults() {
		$settings = self::get();
		if ( function_exists( 'update_option' ) ) { update_option( self::OPTION_NAME, $settings, false ); }
		return $settings;
	}

	/** Sanitize an entire settings payload. */
	public static function sanitize_full( $input ) {
		$current = self::get();
		if ( ! is_array( $input ) ) { return $current; }
		foreach ( self::namespaces() as $namespace ) {
			if ( array_key_exists( $namespace, $input ) ) {
				$current[ $namespace ] = self::sanitize_tab( $namespace, $input[ $namespace ], isset( $current[ $namespace ] ) ? $current[ $namespace ] : array() );
			}
		}
		$current['version'] = SABRI_HNF_VERSION;
		return $current;
	}

	/** Update one settings tab without disturbing other tabs. */
	public static function update_tab( $tab, $input ) {
		$tab = self::clean_key( $tab );
		$settings = self::get();
		if ( ! in_array( $tab, self::namespaces(), true ) ) { return $settings; }
		$settings[ $tab ] = self::sanitize_tab( $tab, $input, isset( $settings[ $tab ] ) ? $settings[ $tab ] : array() );
		$settings['version'] = SABRI_HNF_VERSION;
		if ( function_exists( 'update_option' ) ) { update_option( self::OPTION_NAME, $settings, false ); }
		return $settings;
	}

	/** Sanitize a single settings tab while preserving unknown future keys. */
	public static function sanitize_tab( $tab, $input, array $current = array() ) {
		$input = is_array( $input ) ? $input : array();
		$out = $current;
		foreach ( self::checkbox_keys( $tab ) as $key ) { $out[ $key ] = empty( $input[ $key ] ) ? 0 : 1; }
		foreach ( self::integer_ranges( $tab ) as $key => $range ) {
			if ( array_key_exists( $key, $input ) ) { $out[ $key ] = self::range_int( $input[ $key ], $range[0], $range[1] ); }
		}
		foreach ( self::url_keys( $tab ) as $key ) {
			if ( array_key_exists( $key, $input ) ) { $out[ $key ] = self::clean_url( $input[ $key ] ); }
		}
		foreach ( self::selector_keys( $tab ) as $key => $allowed ) {
			if ( array_key_exists( $key, $input ) ) {
				$raw = 'verified_doctor_policy' === $key && 'submit' === self::clean_key( $input[ $key ] ) ? 'trusted' : $input[ $key ];
				$out[ $key ] = self::select_value( $raw, $allowed, isset( $out[ $key ] ) ? $out[ $key ] : reset( $allowed ) );
			}
		}
		foreach ( self::role_list_keys( $tab ) as $key ) {
			if ( array_key_exists( $key, $input ) ) { $out[ $key ] = self::clean_role_list( $input[ $key ] ); }
		}
		foreach ( self::list_keys( $tab ) as $key => $allowed ) {
			if ( array_key_exists( $key, $input ) ) { $out[ $key ] = self::clean_allowed_list( $input[ $key ], $allowed ); }
		}
		if ( 'composer' === $tab && array_key_exists( 'allowed_mime_types', $input ) ) { $out['allowed_mime_types'] = self::allowed_mimes( $input['allowed_mime_types'] ); }
		if ( 'integrations' === $tab && isset( $input['functions'] ) && is_array( $input['functions'] ) ) {
			$functions = isset( $out['functions'] ) && is_array( $out['functions'] ) ? self::sanitize_function_map( $out['functions'] ) : array();
			foreach ( $input['functions'] as $key => $function_name ) {
				$key = self::clean_key( $key );
				if ( in_array( $key, self::recognized_integration_function_keys(), true ) ) { $functions[ $key ] = self::clean_function_name( $function_name ); }
			}
			$out['functions'] = $functions;
		}
		foreach ( $input as $key => $value ) {
			$key = self::clean_key( $key );
			if ( ! array_key_exists( $key, $out ) ) { $out[ $key ] = self::sanitize_deep( $value ); }
		}
		return $out;
	}

	/** Merge stored settings with defaults while keeping unknown stored keys. */
	public static function merge_defaults( array $stored, array $defaults ) {
		foreach ( $defaults as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( ! array_key_exists( $key, $stored ) || ! is_array( $stored[ $key ] ) ) { $stored[ $key ] = $value; }
				elseif ( ! array_is_list( $value ) ) { $stored[ $key ] = self::merge_defaults( $stored[ $key ], $value ); }
			} elseif ( ! array_key_exists( $key, $stored ) ) { $stored[ $key ] = $value; }
		}
		return $stored;
	}

	private static function checkbox_keys( $tab ) {
		$map = array(
			'general' => array( 'enabled' ),
			'feed' => array( 'enabled', 'load_more_enabled', 'show_author_details', 'show_post_type', 'show_media', 'show_disclaimer' ),
			'news' => array( 'enabled', 'breaking_news_enabled' ),
			'composer' => array( 'public_composer_enabled', 'require_patient_consent', 'require_medical_disclaimer', 'scheduling_enabled', 'drafts_enabled', 'previews_enabled', 'comments_metadata_enabled' ),
			'moderation' => array( 'reports_enabled' ), 'media' => array( 'uploads_enabled' ), 'performance' => array( 'log_views' ),
			'privacy' => array( 'retain_data_on_uninstall', 'anonymize_views', 'export_private_saves' ),
			'integrations' => array( 'shell_required' ), 'advanced' => array( 'safe_mode_enabled', 'emergency_disabled', 'debug_diagnostics', 'allow_destructive_repair' ),
		);
		return isset( $map[ $tab ] ) ? $map[ $tab ] : array();
	}

	private static function integer_ranges( $tab ) {
		$map = array(
			'feed' => array( 'default_count' => array( 1, 50 ), 'posts_per_page' => array( 1, 50 ), 'founder_priority' => array( 0, 100 ), 'verified_author_priority' => array( 0, 100 ), 'cache_duration' => array( 0, 86400 ) ),
			'composer' => array( 'max_upload_mb' => array( 1, 64 ), 'max_image_count' => array( 1, 20 ) ), 'media' => array( 'max_items' => array( 1, 20 ) ), 'performance' => array( 'cache_seconds' => array( 0, 86400 ) ),
		);
		return isset( $map[ $tab ] ) ? $map[ $tab ] : array();
	}

	private static function url_keys( $tab ) {
		$map = array( 'news' => array( 'source_url' ), 'integrations' => array( 'shell_home_url', 'shell_news_url', 'composer_page_url' ) );
		return isset( $map[ $tab ] ) ? $map[ $tab ] : array();
	}

	private static function selector_keys( $tab ) {
		$map = array(
			'general' => array( 'environment' => array( 'local', 'staging', 'production' ), 'phase' => array( 'phase_1_foundation', 'phase_2_home_feed_composer', 'comprehensive_harmonization' ) ),
			'feed' => array( 'default_visibility' => array_keys( Taxonomies::visibility_terms() ), 'default_mode' => array_keys( FeedContext::modes() ), 'pagination' => array( 'numbers', 'previous_next' ) ),
			'composer' => array( 'immediate_publish_policy' => array( 'capability' ), 'review_required_policy' => array( 'unverified_doctors', 'all_doctors' ) ),
			'moderation' => array( 'default_report_state' => array( 'open', 'triaged', 'resolved', 'dismissed' ) ),
			'capabilities' => array( 'verified_doctor_policy' => array( 'trusted', 'publish' ) ),
		);
		return isset( $map[ $tab ] ) ? $map[ $tab ] : array();
	}

	private static function role_list_keys( $tab ) { return 'capabilities' === $tab ? array( 'founder_roles', 'verified_doctor_roles', 'unverified_doctor_roles', 'student_roles', 'patient_roles', 'editorial_roles' ) : array(); }
	private static function list_keys( $tab ) {
		$map = array(
			'feed' => array( 'enabled_filters' => array_keys( FeedContext::modes() ), 'allowed_types' => array_keys( Taxonomies::feed_type_terms() ) ),
			'composer' => array( 'allowed_feed_types' => FeedContext::phase2_feed_type_slugs(), 'allowed_visibility_modes' => FeedContext::phase2_visibility_slugs( true ) ),
		);
		return isset( $map[ $tab ] ) ? $map[ $tab ] : array();
	}

	private static function allowed_mimes( $value ) {
		$allowed = array( 'image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'video/mp4', 'video/quicktime', 'audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/ogg' );
		$items = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
		$clean = array(); foreach ( (array) $items as $item ) { $item = strtolower( trim( (string) $item ) ); if ( in_array( $item, $allowed, true ) ) { $clean[] = $item; } }
		return array_values( array_unique( $clean ) );
	}

	private static function clean_allowed_list( $value, array $allowed ) {
		$items = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
		$clean = array(); foreach ( (array) $items as $item ) { $item = self::clean_key( $item ); if ( in_array( $item, $allowed, true ) ) { $clean[] = $item; } }
		return array_values( array_unique( $clean ) );
	}
	private static function clean_role_list( $value ) {
		$items = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
		return array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_key' ), (array) $items ) ) ) );
	}
	private static function range_int( $value, $minimum, $maximum ) { $value = is_numeric( $value ) ? (int) $value : $minimum; return max( $minimum, min( $maximum, $value ) ); }
	private static function clean_url( $value ) { return function_exists( 'esc_url_raw' ) ? esc_url_raw( $value ) : filter_var( $value, FILTER_SANITIZE_URL ); }
	private static function select_value( $value, array $allowed, $fallback ) { $value = self::clean_key( $value ); return in_array( $value, $allowed, true ) ? $value : $fallback; }
	private static function recognized_integration_function_keys() { return array( 'notifications', 'network', 'messages', 'appointments' ); }
	private static function sanitize_function_map( array $map ) { $out = array(); foreach ( self::recognized_integration_function_keys() as $key ) { $out[ $key ] = isset( $map[ $key ] ) ? self::clean_function_name( $map[ $key ] ) : ''; } return $out; }
	private static function clean_function_name( $value ) { $value = trim( (string) $value ); return preg_match( '/^[A-Za-z_][A-Za-z0-9_\\]*$/', $value ) ? $value : ''; }
	private static function sanitize_deep( $value ) {
		if ( is_array( $value ) ) { $out = array(); foreach ( $value as $key => $item ) { $out[ self::clean_key( $key ) ] = self::sanitize_deep( $item ); } return $out; }
		if ( is_bool( $value ) || is_int( $value ) ) { return $value; }
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : strip_tags( (string) $value );
	}
	private static function clean_key( $value ) { return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ); }
}