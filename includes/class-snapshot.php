<?php
/**
 * Activation snapshot foundation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Captures non-destructive state before plugin mutations. */
final class Snapshot {
	const OPTION_NAME = 'sabri_feed_activation_snapshot';
	const FORMAT_VERSION = 2;

	/**
	 * Capture state before settings, schema, taxonomy, or capability mutations.
	 *
	 * The first complete baseline for a plugin version remains immutable. A legacy
	 * same-version snapshot is augmented only for fields absent from the old format;
	 * current Phase 4-mutated options are never rewritten as historical baseline.
	 */
	public static function capture_before_mutation( $reason ) {
		$existing = self::latest();
		if ( ! empty( $existing ) && isset( $existing['version'] ) && SABRI_HNF_VERSION === $existing['version'] ) {
			$augmented = self::augment_same_version_snapshot( $existing );
			if ( $augmented !== $existing && function_exists( 'update_option' ) ) {
				update_option( self::OPTION_NAME, $augmented, false );
			}
			return $augmented;
		}

		$settings = self::option_value( Settings::OPTION_NAME, array() );
		$snapshot = array(
			'format_version'              => self::FORMAT_VERSION,
			'version'                     => SABRI_HNF_VERSION,
			'schema_version'              => self::option_value( Migrations::SCHEMA_OPTION_NAME, '' ),
			'settings'                    => $settings,
			'phase4_settings'             => self::option_value( NewsFeatureSettings::OPTION_NAME, array() ),
			'phase4_contract_version'     => self::option_value( 'sabri_feed_phase4_contract_version', '' ),
			'phase4_terms_version'        => self::option_value( NewsTaxonomies::TERM_VERSION_OPTION, '' ),
			'phase4_capability_mutations' => self::option_value( NewsCapabilities::MUTATION_OPTION, array() ),
			'option_exists'               => self::phase4_option_existence(),
			'capability_roles'            => self::role_cap_snapshot(),
			'taxonomy_state'              => self::taxonomy_state(),
			'rewrite_state'               => array(
				'permalink_structure' => self::option_value( 'permalink_structure', '' ),
				'flush_scheduled'     => self::option_value( 'sabri_feed_flush_rewrite_rules', 0 ),
			),
			'integration_settings'        => is_array( $settings ) && isset( $settings['integrations'] ) ? $settings['integrations'] : array(),
			'reason'                      => function_exists( 'sanitize_key' ) ? sanitize_key( $reason ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $reason ) ),
			'created_at'                  => gmdate( 'Y-m-d H:i:s' ),
		);

		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_NAME, $snapshot, false );
		}
		return $snapshot;
	}

	/** Return the latest snapshot. */
	public static function latest() {
		$snapshot = self::option_value( self::OPTION_NAME, array() );
		return is_array( $snapshot ) ? $snapshot : array();
	}

	/** Snapshot all plugin-owned capability assignments for candidate roles. */
	public static function role_cap_snapshot() {
		$out  = array();
		$caps = array_values( array_unique( array_merge( Capabilities::capabilities(), NewsCapabilities::capabilities() ) ) );
		if ( ! function_exists( 'wp_roles' ) ) {
			return $out;
		}
		$roles = wp_roles();
		if ( ! $roles || empty( $roles->roles ) || ! is_array( $roles->roles ) ) {
			return $out;
		}
		$candidate_roles = array_values(
			array_unique(
				array_merge(
					Capabilities::candidate_role_slugs( Settings::get() ),
					NewsCapabilities::candidate_role_slugs()
				)
			)
		);
		foreach ( $roles->roles as $role_slug => $role_data ) {
			if ( ! in_array( $role_slug, $candidate_roles, true ) ) {
				continue;
			}
			$out[ $role_slug ] = array();
			foreach ( $caps as $capability ) {
				$out[ $role_slug ][ $capability ] = ! empty( $role_data['capabilities'][ $capability ] );
			}
		}
		return $out;
	}

	/** Complete only missing Phase 4 fields in a legacy same-version snapshot. */
	private static function augment_same_version_snapshot( array $snapshot ) {
		$changed = false;

		// These option names did not exist in the legacy snapshot format. A missing
		// field therefore means the baseline was absent, not whatever value exists now.
		$legacy_defaults = array(
			'phase4_settings'             => array(),
			'phase4_contract_version'     => '',
			'phase4_terms_version'        => '',
			'phase4_capability_mutations' => array(),
		);
		foreach ( $legacy_defaults as $key => $value ) {
			if ( ! array_key_exists( $key, $snapshot ) ) {
				$snapshot[ $key ] = $value;
				$changed = true;
			}
		}

		if ( ! isset( $snapshot['option_exists'] ) || ! is_array( $snapshot['option_exists'] ) ) {
			$snapshot['option_exists'] = array();
			$changed = true;
		}
		foreach ( array_keys( $legacy_defaults ) as $key ) {
			if ( ! array_key_exists( $key, $snapshot['option_exists'] ) ) {
				$snapshot['option_exists'][ $key ] = false;
				$changed = true;
			}
		}

		if ( ! isset( $snapshot['capability_roles'] ) || ! is_array( $snapshot['capability_roles'] ) ) {
			$snapshot['capability_roles'] = array();
			$changed = true;
		}
		$current_caps = self::role_cap_snapshot();
		$managed_caps = self::recorded_phase4_managed_caps();
		foreach ( $current_caps as $role_slug => $caps ) {
			if ( ! isset( $snapshot['capability_roles'][ $role_slug ] ) || ! is_array( $snapshot['capability_roles'][ $role_slug ] ) ) {
				$snapshot['capability_roles'][ $role_slug ] = array();
				$changed = true;
			}
			foreach ( NewsCapabilities::capabilities() as $capability ) {
				if ( array_key_exists( $capability, $snapshot['capability_roles'][ $role_slug ] ) ) {
					continue;
				}
				$baseline = ! empty( $caps[ $capability ] );
				if ( ! empty( $managed_caps[ $role_slug ][ $capability ] ) ) {
					$baseline = false;
				}
				$snapshot['capability_roles'][ $role_slug ][ $capability ] = $baseline;
				$changed = true;
			}
		}

		if ( ! isset( $snapshot['taxonomy_state'] ) || ! is_array( $snapshot['taxonomy_state'] ) ) {
			$snapshot['taxonomy_state'] = array();
			$changed = true;
		}
		foreach ( self::taxonomy_state() as $key => $value ) {
			if ( ! array_key_exists( $key, $snapshot['taxonomy_state'] ) ) {
				$snapshot['taxonomy_state'][ $key ] = $value;
				$changed = true;
			}
		}

		if ( ! isset( $snapshot['format_version'] ) || self::FORMAT_VERSION !== (int) $snapshot['format_version'] ) {
			$snapshot['format_version'] = self::FORMAT_VERSION;
			$changed = true;
		}
		if ( $changed ) {
			$snapshot['augmented_at'] = gmdate( 'Y-m-d H:i:s' );
		}
		return $snapshot;
	}

	/** Read the explicit plugin-managed capability record without inference. */
	private static function recorded_phase4_managed_caps() {
		$record  = self::option_value( NewsCapabilities::MUTATION_OPTION, array() );
		$managed = array();
		if ( is_array( $record ) && ! empty( $record['managed_caps'] ) && is_array( $record['managed_caps'] ) ) {
			return $record['managed_caps'];
		}
		if ( is_array( $record ) && ! empty( $record['roles'] ) && is_array( $record['roles'] ) ) {
			foreach ( $record['roles'] as $role_slug => $actions ) {
				foreach ( is_array( $actions ) ? $actions : array() as $capability => $action ) {
					if ( 'added' === $action ) {
						$managed[ $role_slug ][ $capability ] = true;
					}
				}
			}
		}
		return $managed;
	}

	/** Record whether Phase 4-owned options existed before mutation. */
	private static function phase4_option_existence() {
		return array(
			'phase4_settings'             => self::option_exists( NewsFeatureSettings::OPTION_NAME ),
			'phase4_contract_version'     => self::option_exists( 'sabri_feed_phase4_contract_version' ),
			'phase4_terms_version'        => self::option_exists( NewsTaxonomies::TERM_VERSION_OPTION ),
			'phase4_capability_mutations' => self::option_exists( NewsCapabilities::MUTATION_OPTION ),
		);
	}

	/** Snapshot taxonomy version and expected default term identities. */
	private static function taxonomy_state() {
		return array(
			'registered_taxonomies'        => array_keys( Taxonomies::taxonomies() ),
			'default_feed_types'           => array_keys( Taxonomies::feed_type_terms() ),
			'phase4_registered_taxonomies' => Phase4Contracts::taxonomies(),
			'phase4_sections'              => array_keys( Phase4Contracts::sections() ),
			'phase4_article_types'         => array_keys( Phase4Contracts::article_types() ),
			'version'                      => self::option_value( 'sabri_feed_taxonomy_version', '' ),
			'phase4_terms_version'         => self::option_value( NewsTaxonomies::TERM_VERSION_OPTION, '' ),
		);
	}

	/** Determine option existence without confusing a stored false value with absence. */
	private static function option_exists( $name ) {
		return function_exists( 'get_option' ) && null !== get_option( $name, null );
	}

	/** Get an option safely. */
	private static function option_value( $name, $default ) {
		return function_exists( 'get_option' ) ? get_option( $name, $default ) : $default;
	}
}
