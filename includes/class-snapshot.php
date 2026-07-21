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

/**
 * Captures non-destructive state before plugin mutations.
 */
final class Snapshot {
	const OPTION_NAME = 'sabri_feed_activation_snapshot';

	/**
	 * Capture a snapshot before settings, schema, taxonomy, or capability mutations.
	 *
	 * @param string $reason Snapshot reason.
	 * @return array<string,mixed>
	 */
	public static function capture_before_mutation( $reason ) {
		$settings = self::option_value( Settings::OPTION_NAME, array() );
		$snapshot = array(
			'version'              => SABRI_HNF_VERSION,
			'schema_version'       => self::option_value( Migrations::SCHEMA_OPTION_NAME, '' ),
			'settings'             => $settings,
			'phase4_settings'      => self::option_value( NewsFeatureSettings::OPTION_NAME, array() ),
			'capability_roles'     => self::role_cap_snapshot(),
			'taxonomy_state'       => self::taxonomy_state(),
			'rewrite_state'        => array(
				'permalink_structure' => self::option_value( 'permalink_structure', '' ),
				'flush_scheduled'     => self::option_value( 'sabri_feed_flush_rewrite_rules', 0 ),
			),
			'integration_settings' => is_array( $settings ) && isset( $settings['integrations'] ) ? $settings['integrations'] : array(),
			'reason'               => function_exists( 'sanitize_key' ) ? sanitize_key( $reason ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $reason ) ),
			'created_at'           => gmdate( 'Y-m-d H:i:s' ),
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

	/**
	 * Snapshot all plugin-owned capability assignments for candidate roles.
	 *
	 * @return array<string,array<string,bool>>
	 */
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

	/** Snapshot taxonomy version and expected default term identities. */
	private static function taxonomy_state() {
		return array(
			'registered_taxonomies'       => array_keys( Taxonomies::taxonomies() ),
			'default_feed_types'          => array_keys( Taxonomies::feed_type_terms() ),
			'phase4_registered_taxonomies' => Phase4Contracts::taxonomies(),
			'phase4_sections'              => array_keys( Phase4Contracts::sections() ),
			'phase4_article_types'         => array_keys( Phase4Contracts::article_types() ),
			'version'                      => self::option_value( 'sabri_feed_taxonomy_version', '' ),
		);
	}

	/** Get an option safely. */
	private static function option_value( $name, $default ) {
		if ( function_exists( 'get_option' ) ) {
			return get_option( $name, $default );
		}
		return $default;
	}
}
