<?php
/**
 * Non-destructive settings harmonization for the revised File 21 architecture.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps old installations compatible while applying current canonical defaults. */
final class HarmonizedSettings {
	/** Register option normalization. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'option_' . Settings::OPTION_NAME, array( __CLASS__, 'normalize' ) );
			add_filter( 'default_option_' . Settings::OPTION_NAME, array( __CLASS__, 'normalize' ) );
			add_filter( 'pre_update_option_' . Settings::OPTION_NAME, array( __CLASS__, 'normalize_update' ), 10, 2 );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_init', array( __CLASS__, 'persist_once' ), 5 );
		}
	}

	/** Normalize an option payload without deleting unknown future keys. */
	public static function normalize( $value ) {
		$value = is_array( $value ) ? $value : array();
		$value['capabilities'] = isset( $value['capabilities'] ) && is_array( $value['capabilities'] ) ? $value['capabilities'] : array();
		$value['feed'] = isset( $value['feed'] ) && is_array( $value['feed'] ) ? $value['feed'] : array();
		$value['composer'] = isset( $value['composer'] ) && is_array( $value['composer'] ) ? $value['composer'] : array();

		$value['capabilities']['founder_roles'] = self::merge_keys(
			isset( $value['capabilities']['founder_roles'] ) ? $value['capabilities']['founder_roles'] : array(),
			array( 'founder', 'sabri_founder' )
		);
		$value['capabilities']['verified_doctor_roles'] = self::merge_keys(
			isset( $value['capabilities']['verified_doctor_roles'] ) ? $value['capabilities']['verified_doctor_roles'] : array(),
			array( 'verified_doctor', 'approved_doctor', 'doctor_verified', 'sabri_verified_doctor' )
		);
		$value['capabilities']['unverified_doctor_roles'] = self::merge_keys(
			isset( $value['capabilities']['unverified_doctor_roles'] ) ? $value['capabilities']['unverified_doctor_roles'] : array(),
			array( 'doctor', 'sabri_doctor', 'sabri_doctor_pending' )
		);
		$value['capabilities']['student_roles'] = self::merge_keys(
			isset( $value['capabilities']['student_roles'] ) ? $value['capabilities']['student_roles'] : array(),
			array( 'student', 'sabri_student' )
		);
		$value['capabilities']['patient_roles'] = self::merge_keys(
			isset( $value['capabilities']['patient_roles'] ) ? $value['capabilities']['patient_roles'] : array(),
			array( 'patient', 'sabri_patient', 'subscriber' )
		);

		$policy = isset( $value['capabilities']['verified_doctor_policy'] ) ? self::clean_key( $value['capabilities']['verified_doctor_policy'] ) : '';
		$value['capabilities']['verified_doctor_policy'] = in_array( $policy, array( 'trusted', 'publish' ), true ) ? $policy : 'trusted';

		$value['feed']['enabled_filters'] = self::merge_allowed(
			isset( $value['feed']['enabled_filters'] ) ? $value['feed']['enabled_filters'] : array(),
			array_keys( FeedContext::modes() ),
			array_keys( FeedContext::modes() )
		);
		$value['feed']['allowed_types'] = self::merge_allowed(
			isset( $value['feed']['allowed_types'] ) ? $value['feed']['allowed_types'] : array(),
			array_keys( Taxonomies::feed_type_terms() ),
			array_keys( Taxonomies::feed_type_terms() )
		);
		$value['composer']['allowed_feed_types'] = self::merge_allowed(
			isset( $value['composer']['allowed_feed_types'] ) ? $value['composer']['allowed_feed_types'] : array(),
			FeedContext::phase2_feed_type_slugs(),
			FeedContext::phase2_feed_type_slugs()
		);
		$value['composer']['allowed_visibility_modes'] = self::merge_allowed(
			isset( $value['composer']['allowed_visibility_modes'] ) ? $value['composer']['allowed_visibility_modes'] : array(),
			FeedContext::phase2_visibility_slugs( true ),
			FeedContext::phase2_visibility_slugs( true )
		);
		$value['version'] = SABRI_HNF_VERSION;
		return $value;
	}

	/** Pre-update filter signature. */
	public static function normalize_update( $new_value, $old_value ) {
		unset( $old_value );
		return self::normalize( $new_value );
	}

	/** Persist one canonical option revision for old installations. */
	public static function persist_once() {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) || ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$marker = 'sabri_hnf_settings_harmonized_' . str_replace( '.', '_', SABRI_HNF_VERSION );
		if ( get_option( $marker, 0 ) ) {
			return;
		}
		$current = get_option( Settings::OPTION_NAME, array() );
		update_option( Settings::OPTION_NAME, self::normalize( $current ), false );
		update_option( $marker, 1, false );
		AuditLog::record( 'file21_settings_harmonized', array( 'version' => SABRI_HNF_VERSION ) );
	}

	/** Merge and sanitize role or controlled-key aliases. */
	private static function merge_keys( $current, array $required ) {
		$current = is_array( $current ) ? $current : array();
		$keys = array_map( array( __CLASS__, 'clean_key' ), array_merge( $current, $required ) );
		return array_values( array_unique( array_filter( $keys ) ) );
	}

	/** Merge a stored allow-list with newly accepted values while rejecting unknown keys. */
	private static function merge_allowed( $current, array $required, array $allowed ) {
		$current = is_array( $current ) ? $current : array();
		$items = self::merge_keys( $current, $required );
		return array_values( array_intersect( $items, array_map( array( __CLASS__, 'clean_key' ), $allowed ) ) );
	}

	/** Normalize a controlled key without assuming WordPress helpers in lean tests. */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}
}