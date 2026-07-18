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

/**
 * Handles structured settings and per-tab sanitization.
 */
final class Settings {
	const OPTION_NAME = 'sabri_feed_settings';

	/**
	 * Register WordPress settings.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		}
	}

	/**
	 * Register the option for admin forms.
	 *
	 * @return void
	 */
	public static function register_settings() {
		if ( function_exists( 'register_setting' ) ) {
			register_setting(
				'sabri_feed_settings',
				self::OPTION_NAME,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( __CLASS__, 'sanitize_full' ),
					'default'           => self::defaults(),
				)
			);
		}
	}

	/**
	 * Settings namespaces.
	 *
	 * @return array<int,string>
	 */
	public static function namespaces() {
		return array(
			'general',
			'feed',
			'news',
			'composer',
			'capabilities',
			'moderation',
			'media',
			'performance',
			'privacy',
			'integrations',
			'advanced',
		);
	}

	/**
	 * Safe default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'version'      => SABRI_HNF_VERSION,
			'general'     => array(
				'enabled'          => 1,
				'environment'      => 'staging',
				'phase'            => 'phase_1_foundation',
				'future_notice'    => 'Available after the relevant implementation phase',
				'admin_accent_hex' => '#f26100',
			),
			'feed'        => array(
				'enabled'            => 0,
				'default_visibility' => 'public',
				'default_count'      => 10,
				'allowed_types'      => array_keys( Taxonomies::feed_type_terms() ),
				'future_notice'      => 'Available after the relevant implementation phase',
			),
			'news'        => array(
				'enabled'               => 0,
				'breaking_news_enabled' => 0,
				'source_url'            => '',
				'future_notice'         => 'Available after the relevant implementation phase',
			),
			'composer'    => array(
				'public_composer_enabled' => 0,
				'max_upload_mb'           => 8,
				'allowed_mime_types'      => array( 'image/jpeg', 'image/png', 'image/webp', 'application/pdf' ),
				'future_notice'           => 'Available after the relevant implementation phase',
			),
			'capabilities' => array(
				'founder_roles'           => array( 'founder', 'sabri_founder' ),
				'verified_doctor_roles'   => array( 'verified_doctor', 'approved_doctor', 'doctor_verified' ),
				'unverified_doctor_roles' => array( 'doctor', 'sabri_doctor' ),
				'student_roles'           => array( 'student', 'sabri_student' ),
				'patient_roles'           => array( 'patient', 'subscriber' ),
				'editorial_roles'         => array( 'editor' ),
				'verified_doctor_policy'  => 'submit',
				'future_notice'           => 'Available after the relevant implementation phase',
			),
			'moderation'  => array(
				'reports_enabled'      => 0,
				'default_report_state' => 'open',
				'future_notice'        => 'Available after the relevant implementation phase',
			),
			'media'       => array(
				'uploads_enabled' => 0,
				'max_items'       => 4,
				'future_notice'   => 'Available after the relevant implementation phase',
			),
			'performance' => array(
				'cache_seconds' => 300,
				'log_views'     => 0,
				'future_notice' => 'Available after the relevant implementation phase',
			),
			'privacy'     => array(
				'retain_data_on_uninstall' => 1,
				'anonymize_views'          => 1,
				'export_private_saves'     => 1,
				'future_notice'            => 'Available after the relevant implementation phase',
			),
			'integrations' => array(
				'shell_required'    => 0,
				'shell_home_url'    => '',
				'shell_news_url'    => '',
				'functions'         => array(
					'notifications' => '',
					'network'       => '',
					'messages'      => '',
					'appointments'  => '',
				),
				'confirmed_hooks'   => Integrations::confirmed_shell_hooks(),
				'future_notice'     => 'Available after the relevant implementation phase',
			),
			'advanced'    => array(
				'safe_mode_enabled'       => 1,
				'emergency_disabled'      => 0,
				'debug_diagnostics'       => 0,
				'allow_destructive_repair' => 0,
				'future_notice'           => 'Available after the relevant implementation phase',
			),
		);
	}

	/**
	 * Return merged settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get() {
		$stored = array();
		if ( function_exists( 'get_option' ) ) {
			$stored = get_option( self::OPTION_NAME, array() );
		}

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return self::merge_defaults( $stored, self::defaults() );
	}

	/**
	 * Ensure defaults exist without dropping future keys.
	 *
	 * @return array<string,mixed>
	 */
	public static function ensure_defaults() {
		$settings = self::get();

		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_NAME, $settings, false );
		}

		return $settings;
	}

	/**
	 * Sanitize an entire settings payload.
	 *
	 * @param mixed $input Input.
	 * @return array<string,mixed>
	 */
	public static function sanitize_full( $input ) {
		$current = self::get();
		if ( ! is_array( $input ) ) {
			return $current;
		}

		foreach ( self::namespaces() as $namespace ) {
			if ( array_key_exists( $namespace, $input ) ) {
				$current[ $namespace ] = self::sanitize_tab( $namespace, $input[ $namespace ], isset( $current[ $namespace ] ) ? $current[ $namespace ] : array() );
			}
		}

		$current['version'] = SABRI_HNF_VERSION;

		return $current;
	}

	/**
	 * Update one settings tab without disturbing other tabs.
	 *
	 * @param string $tab Tab key.
	 * @param mixed  $input Input.
	 * @return array<string,mixed>
	 */
	public static function update_tab( $tab, $input ) {
		$tab      = self::clean_key( $tab );
		$settings = self::get();

		if ( ! in_array( $tab, self::namespaces(), true ) ) {
			return $settings;
		}

		$settings[ $tab ] = self::sanitize_tab( $tab, $input, isset( $settings[ $tab ] ) ? $settings[ $tab ] : array() );
		$settings['version'] = SABRI_HNF_VERSION;

		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_NAME, $settings, false );
		}

		return $settings;
	}

	/**
	 * Sanitize a single settings tab while preserving unknown future keys.
	 *
	 * @param string              $tab Tab key.
	 * @param mixed               $input Raw input.
	 * @param array<string,mixed> $current Current tab values.
	 * @return array<string,mixed>
	 */
	public static function sanitize_tab( $tab, $input, array $current = array() ) {
		$input = is_array( $input ) ? $input : array();
		$out   = $current;

		foreach ( self::checkbox_keys( $tab ) as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		foreach ( self::integer_ranges( $tab ) as $key => $range ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = self::range_int( $input[ $key ], $range[0], $range[1] );
			}
		}

		foreach ( self::url_keys( $tab ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = self::clean_url( $input[ $key ] );
			}
		}

		foreach ( self::selector_keys( $tab ) as $key => $allowed ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = self::select_value( $input[ $key ], $allowed, isset( $out[ $key ] ) ? $out[ $key ] : reset( $allowed ) );
			}
		}

		foreach ( self::role_list_keys( $tab ) as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$out[ $key ] = self::clean_role_list( $input[ $key ] );
			}
		}

		if ( 'composer' === $tab && array_key_exists( 'allowed_mime_types', $input ) ) {
			$out['allowed_mime_types'] = self::allowed_mimes( $input['allowed_mime_types'] );
		}

		if ( 'integrations' === $tab && isset( $input['functions'] ) && is_array( $input['functions'] ) ) {
			$functions = isset( $out['functions'] ) && is_array( $out['functions'] ) ? self::sanitize_function_map( $out['functions'] ) : array();
			$recognized = self::recognized_integration_function_keys();
			foreach ( $input['functions'] as $key => $function_name ) {
				$key = self::clean_key( $key );
				if ( in_array( $key, $recognized, true ) ) {
					$functions[ $key ] = self::clean_function_name( $function_name );
				}
			}
			$out['functions'] = $functions;
		}

		foreach ( $input as $key => $value ) {
			$key = self::clean_key( $key );
			if ( ! array_key_exists( $key, $out ) ) {
				$out[ $key ] = self::sanitize_deep( $value );
			}
		}

		return $out;
	}

	/**
	 * Merge stored settings with defaults while keeping unknown stored keys.
	 *
	 * @param array<string,mixed> $stored Stored settings.
	 * @param array<string,mixed> $defaults Default settings.
	 * @return array<string,mixed>
	 */
	public static function merge_defaults( array $stored, array $defaults ) {
		foreach ( $defaults as $key => $value ) {
			if ( is_array( $value ) ) {
				$stored[ $key ] = self::merge_defaults( isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? $stored[ $key ] : array(), $value );
			} elseif ( ! array_key_exists( $key, $stored ) ) {
				$stored[ $key ] = $value;
			}
		}

		return $stored;
	}

	/**
	 * Checkbox keys by tab.
	 *
	 * @param string $tab Tab.
	 * @return array<int,string>
	 */
	private static function checkbox_keys( $tab ) {
		$map = array(
			'general'     => array( 'enabled' ),
			'feed'        => array( 'enabled' ),
			'news'        => array( 'enabled', 'breaking_news_enabled' ),
			'composer'    => array( 'public_composer_enabled' ),
			'moderation'  => array( 'reports_enabled' ),
			'media'       => array( 'uploads_enabled' ),
			'performance' => array( 'log_views' ),
			'privacy'     => array( 'retain_data_on_uninstall', 'anonymize_views', 'export_private_saves' ),
			'integrations' => array( 'shell_required' ),
			'advanced'    => array( 'safe_mode_enabled', 'emergency_disabled', 'debug_diagnostics', 'allow_destructive_repair' ),
		);

		return isset( $map[ $tab ] ) ? $map[ $tab ] : array();
	}

	/**
	 * Integer ranges by tab.
	 *
	 * @param string $tab Tab.
	 * @return array<string,array<int,int>>
	 */
	private static function integer_ranges( $tab ) {
		$map = array(
			'feed'        => array( 'default_count' => array( 1, 50 ) ),
			'composer'    => array( 'max_upload_mb' => array( 1, 64 ) ),
			'media'       => array( 'max_items' => array( 1, 20 ) ),
			'performance' => array( 'cache_seconds' => array( 0, 86400 ) ),
		);

		return isset( $map[ $tab ] ) ? $map[ $tab ] : array();
	}

	/**
	 * URL keys by tab.
	 *
	 * @param string $tab Tab.
	 * @return array<int,string>
	 */
	private static function url_keys( $tab ) {
		$map = array(
			'news'         => array( 'source_url' ),
			'integrations' => array( 'shell_home_url', 'shell_news_url' ),
		);

		return isset( $map[ $tab ] ) ? $map[ $tab ] : array();
	}

	/**
	 * Selector keys and allowed values.
	 *
	 * @param string $tab Tab.
	 * @return array<string,array<int,string>>
	 */
	private static function selector_keys( $tab ) {
		$map = array(
			'general'      => array( 'environment' => array( 'local', 'staging', 'production' ), 'phase' => array( 'phase_1_foundation' ) ),
			'feed'         => array( 'default_visibility' => array( 'public', 'members', 'doctors', 'private' ) ),
			'moderation'   => array( 'default_report_state' => array( 'open', 'triaged', 'resolved', 'dismissed' ) ),
			'capabilities' => array( 'verified_doctor_policy' => array( 'submit', 'publish' ) ),
		);

		return isset( $map[ $tab ] ) ? $map[ $tab ] : array();
	}

	/**
	 * Role list keys.
	 *
	 * @param string $tab Tab.
	 * @return array<int,string>
	 */
	private static function role_list_keys( $tab ) {
		if ( 'capabilities' !== $tab ) {
			return array();
		}

		return array( 'founder_roles', 'verified_doctor_roles', 'unverified_doctor_roles', 'student_roles', 'patient_roles', 'editorial_roles' );
	}

	/**
	 * Sanitize allowed MIME values.
	 *
	 * @param mixed $value MIME list.
	 * @return array<int,string>
	 */
	private static function allowed_mimes( $value ) {
		$allowed = array( 'image/jpeg', 'image/png', 'image/webp', 'application/pdf' );
		$items   = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
		$clean   = array();

		foreach ( (array) $items as $item ) {
			$item = strtolower( trim( (string) $item ) );
			if ( in_array( $item, $allowed, true ) ) {
				$clean[] = $item;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Recognized integration function keys.
	 *
	 * @return array<int,string>
	 */
	private static function recognized_integration_function_keys() {
		return array( 'notifications', 'network', 'messages', 'appointments' );
	}

	/**
	 * Sanitize a function map while preserving existing future keys.
	 *
	 * @param array<string,mixed> $functions Function map.
	 * @return array<string,string>
	 */
	private static function sanitize_function_map( array $functions ) {
		$out = array();
		foreach ( $functions as $key => $function_name ) {
			$key = self::clean_key( $key );
			if ( '' !== $key ) {
				$out[ $key ] = self::clean_function_name( $function_name );
			}
		}

		return $out;
	}

	/**
	 * Sanitize a callable function name.
	 *
	 * @param mixed $function_name Function name.
	 * @return string
	 */
	private static function clean_function_name( $function_name ) {
		return preg_replace( '/[^A-Za-z0-9_\\\\]/', '', (string) $function_name );
	}

	/**
	 * Sanitize role list.
	 *
	 * @param mixed $value Raw role list.
	 * @return array<int,string>
	 */
	private static function clean_role_list( $value ) {
		$items = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
		$roles = array();

		foreach ( (array) $items as $item ) {
			$key = self::clean_key( $item );
			if ( '' !== $key ) {
				$roles[] = $key;
			}
		}

		return array_values( array_unique( $roles ) );
	}

	/**
	 * Sanitize a selector value.
	 *
	 * @param mixed        $value Value.
	 * @param array<int,string> $allowed Allowed values.
	 * @param string       $fallback Fallback.
	 * @return string
	 */
	private static function select_value( $value, array $allowed, $fallback ) {
		$value = self::clean_key( $value );
		return in_array( $value, $allowed, true ) ? $value : (string) $fallback;
	}

	/**
	 * Bound integer.
	 *
	 * @param mixed $value Value.
	 * @param int   $min Minimum.
	 * @param int   $max Maximum.
	 * @return int
	 */
	private static function range_int( $value, $min, $max ) {
		$value = function_exists( 'absint' ) ? absint( $value ) : abs( (int) $value );
		return min( $max, max( $min, $value ) );
	}

	/**
	 * Sanitize deeply while preserving simple future keys.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function sanitize_deep( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $item ) {
				$out[ self::clean_key( $key ) ] = self::sanitize_deep( $item );
			}
			return $out;
		}

		return self::clean_text( $value );
	}

	/**
	 * Clean a key.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_key( $value ) {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}

	/**
	 * Clean text.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_text( $value ) {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}

		return trim( strip_tags( (string) $value ) );
	}

	/**
	 * Clean URL.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_url( $value ) {
		if ( function_exists( 'esc_url_raw' ) ) {
			return esc_url_raw( $value );
		}

		return filter_var( (string) $value, FILTER_VALIDATE_URL ) ? (string) $value : '';
	}
}
