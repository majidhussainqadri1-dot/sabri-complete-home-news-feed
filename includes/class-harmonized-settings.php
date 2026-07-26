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
		if ( ! isset( $value['capabilities'] ) || ! is_array( $value['capabilities'] ) ) {
			$value['capabilities'] = array();
		}
		$value['capabilities']['founder_roles'] = self::merge_roles(
			isset( $value['capabilities']['founder_roles'] ) ? $value['capabilities']['founder_roles'] : array(),
			array( 'founder', 'sabri_founder' )
		);
		$value['capabilities']['verified_doctor_roles'] = self::merge_roles(
			isset( $value['capabilities']['verified_doctor_roles'] ) ? $value['capabilities']['verified_doctor_roles'] : array(),
			array( 'verified_doctor', 'approved_doctor', 'doctor_verified', 'sabri_verified_doctor' )
		);
		$value['capabilities']['unverified_doctor_roles'] = self::merge_roles(
			isset( $value['capabilities']['unverified_doctor_roles'] ) ? $value['capabilities']['unverified_doctor_roles'] : array(),
			array( 'doctor', 'sabri_doctor', 'sabri_doctor_pending' )
		);
		$value['capabilities']['student_roles'] = self::merge_roles(
			isset( $value['capabilities']['student_roles'] ) ? $value['capabilities']['student_roles'] : array(),
			array( 'student', 'sabri_student' )
		);
		$value['capabilities']['patient_roles'] = self::merge_roles(
			isset( $value['capabilities']['patient_roles'] ) ? $value['capabilities']['patient_roles'] : array(),
			array( 'patient', 'sabri_patient', 'subscriber' )
		);
		$policy = isset( $value['capabilities']['verified_doctor_policy'] ) ? sanitize_key( $value['capabilities']['verified_doctor_policy'] ) : '';
		if ( ! in_array( $policy, array( 'trusted', 'publish', 'submit' ), true ) || 'submit' === $policy ) {
			$value['capabilities']['verified_doctor_policy'] = 'trusted';
		}
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

	/** Merge and sanitize role aliases. */
	private static function merge_roles( $current, array $required ) {
		$current = is_array( $current ) ? $current : array();
		$roles = array_merge( $current, $required );
		$roles = array_map( 'sanitize_key', $roles );
		return array_values( array_unique( array_filter( $roles ) ) );
	}
}