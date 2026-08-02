<?php
/**
 * Canonical cross-plugin identity and publishing authority adapter.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Resolves public identity and privileged publishing exclusively through File 00. */
final class CanonicalIdentityAdapter {
	const MINIMUM_CONTRACT_VERSION = '1.1.2';

	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'user_has_cap', array( __CLASS__, 'guard_file21_capabilities' ), 95, 4 );
		}
	}

	public static function role_aliases() {
		return array(
			'founder' => array( 'founder', 'sabri_founder' ),
			'administrator' => array( 'administrator' ),
			'verified_doctor' => array( 'sabri_doctor_verified', 'verified_doctor', 'approved_doctor', 'doctor_verified', 'sabri_verified_doctor' ),
			'unverified_doctor' => array( 'doctor', 'sabri_doctor', 'sabri_doctor_pending' ),
			'student' => array( 'student', 'sabri_student' ),
			'patient' => array( 'patient', 'sabri_patient', 'subscriber' ),
		);
	}

	public static function roles( $user_id ) {
		$user_id = self::positive_id( $user_id );
		$user = $user_id > 0 && function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : false;
		$roles = $user && is_array( $user->roles ) ? $user->roles : array();
		return array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_key' ), $roles ) ) ) );
	}

	/** Return validated, subject-bound File 00 assertions or a fail-closed marker. */
	public static function membership_assertions( $user_id ) {
		$user_id = self::positive_id( $user_id );
		if ( $user_id <= 0 ) { return array( '_contract_error' => true ); }

		try {
			if ( ! class_exists( 'SMC_Contracts' ) || ! is_callable( array( 'SMC_Contracts', 'assertions' ) ) ) {
				return array( '_contract_error' => true );
			}
			$assertions = \SMC_Contracts::assertions( $user_id );
		} catch ( \Throwable $error ) {
			unset( $error );
			return array( '_contract_error' => true );
		}

		if ( ! is_array( $assertions ) ) { return array( '_contract_error' => true ); }
		$contract = isset( $assertions['contract_version'] ) ? (string) $assertions['contract_version'] : '';
		$subject = isset( $assertions['user_id'] ) ? self::positive_id( $assertions['user_id'] ) : 0;
		if ( $subject !== $user_id || '' === $contract || version_compare( $contract, self::MINIMUM_CONTRACT_VERSION, '<' ) ) {
			return array( '_contract_error' => true );
		}
		return $assertions;
	}

	/** Whether File 00 currently considers the subject active for privileged use. */
	public static function subject_is_active( $user_id ) {
		return self::assertions_are_active( self::membership_assertions( $user_id ) );
	}

	/** Whether the current actor has a fresh File 00 action assurance. */
	public static function current_action_ready( $user_id ) {
		$user_id = self::positive_id( $user_id );
		if ( $user_id <= 0 || ! function_exists( 'get_current_user_id' ) || (int) get_current_user_id() !== $user_id ) {
			return false;
		}
		$assertions = self::membership_assertions( $user_id );
		return self::assertions_are_active( $assertions )
			&& ! empty( $assertions['two_factor_ready'] )
			&& ! empty( $assertions['session_two_factor'] );
	}

	public static function is_founder( $user_id ) {
		$assertions = self::membership_assertions( $user_id );
		return self::assertions_identify_active_class( $assertions, 'founder' );
	}

	/** Bounded canonical Founder IDs for Founder-only public queries. */
	public static function founder_ids( $limit = 50 ) {
		$limit = max( 1, min( 100, (int) $limit ) );
		$ids = array();
		if ( function_exists( 'smc_founder_user_id' ) ) {
			$ids[] = absint( smc_founder_user_id() );
		}
		if ( function_exists( 'get_option' ) ) {
			foreach ( array( 'smc_founder_user_id', 'spf_founder_user_id', 'spd_founder_user_id', 'sabri_founder_user_id' ) as $option ) {
				$ids[] = absint( get_option( $option, 0 ) );
			}
		}
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		$ids = array_values( array_filter( $ids, array( __CLASS__, 'is_founder' ) ) );
		$filtered = function_exists( 'apply_filters' ) ? apply_filters( 'sabri_hnf_founder_ids', $ids, $limit ) : $ids;
		if ( is_array( $filtered ) ) {
			$filtered = array_values( array_unique( array_filter( array_map( 'absint', $filtered ) ) ) );
			$ids = array_values( array_filter( $filtered, array( __CLASS__, 'is_founder' ) ) );
		}
		return array_slice( $ids, 0, $limit );
	}

	public static function is_administrator( $user_id ) {
		$user_id = self::positive_id( $user_id );
		$assertions = self::membership_assertions( $user_id );
		return self::assertions_identify_active_class( $assertions, 'administrator' )
			&& function_exists( 'user_can' ) && user_can( $user_id, 'manage_options' );
	}

	public static function is_verified_doctor( $user_id ) {
		$user_id = self::positive_id( $user_id );
		$assertions = self::membership_assertions( $user_id );
		if ( ! self::assertions_are_active( $assertions )
			|| 'doctor' !== ( $assertions['membership_type'] ?? '' )
			|| empty( $assertions['professional_verified'] )
			|| empty( $assertions['public_profile_allowed'] ) ) {
			return false;
		}
		if ( class_exists( 'SPD_Verification_Adapter' ) && is_callable( array( 'SPD_Verification_Adapter', 'directory_eligible' ) ) ) {
			return (bool) \SPD_Verification_Adapter::directory_eligible( $user_id );
		}
		return true;
	}

	public static function verified_doctor_ids( $limit = 500 ) {
		$limit = max( 1, min( 500, (int) $limit ) );
		if ( ! function_exists( 'get_users' ) ) { return array(); }
		$candidates = get_users( array( 'role__in' => array( 'sabri_doctor_verified' ), 'fields' => 'ID', 'number' => $limit ) );
		$ids = array_values( array_unique( array_filter( array_map( 'absint', is_array( $candidates ) ? $candidates : array() ) ) ) );
		$ids = array_values( array_filter( $ids, array( __CLASS__, 'is_verified_doctor' ) ) );
		$filtered = function_exists( 'apply_filters' ) ? apply_filters( 'sabri_hnf_verified_doctor_ids', $ids, $limit ) : $ids;
		if ( is_array( $filtered ) ) {
			$filtered = array_values( array_unique( array_filter( array_map( 'absint', $filtered ) ) ) );
			$ids = array_values( array_filter( $filtered, array( __CLASS__, 'is_verified_doctor' ) ) );
		}
		return array_slice( $ids, 0, $limit );
	}

	public static function is_unverified_doctor( $user_id ) {
		$assertions = self::membership_assertions( $user_id );
		return empty( $assertions['_contract_error'] )
			&& 'doctor' === ( $assertions['membership_type'] ?? '' )
			&& empty( $assertions['professional_verified'] )
			&& ! self::assertions_have_hard_block( $assertions );
	}

	public static function is_trusted_publisher( $user_id ) {
		$user_id = self::positive_id( $user_id );
		$assertions = self::membership_assertions( $user_id );
		if ( ! self::assertions_are_active( $assertions ) ) { return false; }
		if ( ! empty( $assertions['can_publish'] ) ) { return true; }
		return function_exists( 'user_can' ) && user_can( $user_id, 'sabri_feed_publish_posts' );
	}

	/** Policy-level authority; request-level session assurance is enforced by ComposerPermissions. */
	public static function can_publish_immediately( $user_id, $settings = null ) {
		$user_id = self::positive_id( $user_id );
		$assertions = self::membership_assertions( $user_id );
		if ( ! self::assertions_are_active( $assertions ) ) { return false; }
		if ( 'founder' === ( $assertions['account_class'] ?? '' ) ) { return ! empty( $assertions['can_publish'] ); }
		if ( 'administrator' === ( $assertions['account_class'] ?? '' ) ) {
			return function_exists( 'user_can' ) && user_can( $user_id, 'manage_options' );
		}
		$settings = is_array( $settings ) ? $settings : Settings::get();
		$policy = isset( $settings['capabilities']['verified_doctor_policy'] ) ? self::clean_key( $settings['capabilities']['verified_doctor_policy'] ) : 'trusted';
		if ( 'doctor' === ( $assertions['membership_type'] ?? '' ) && ! empty( $assertions['professional_verified'] ) ) {
			if ( 'publish' === $policy ) { return ! empty( $assertions['can_publish'] ); }
			return 'trusted' === $policy && self::is_trusted_publisher( $user_id );
		}
		return self::is_trusted_publisher( $user_id );
	}

	/** Whether an active File 00 identity is eligible to enter the social composer. */
	public static function can_create_social_content( $user_id ) {
		$user_id = self::positive_id( $user_id );
		$assertions = self::membership_assertions( $user_id );
		if ( ! self::assertions_are_active( $assertions ) ) { return false; }
		if ( in_array( $assertions['account_class'] ?? '', array( 'founder', 'administrator' ), true ) ) { return true; }
		return 'doctor' === ( $assertions['membership_type'] ?? '' );
	}

	public static function public_projection( $user_id ) {
		$user_id = self::positive_id( $user_id );
		if ( $user_id <= 0 ) { return array(); }
		$assertions = self::membership_assertions( $user_id );
		$founder = self::is_founder( $user_id );
		if ( ! $founder && ( ! self::assertions_are_active( $assertions ) || empty( $assertions['public_profile_allowed'] ) ) ) {
			return array();
		}

		$approved = array();
		if ( class_exists( 'SPD_Verification_Adapter' ) && is_callable( array( 'SPD_Verification_Adapter', 'approved_fields' ) ) ) {
			$approved = \SPD_Verification_Adapter::approved_fields( $user_id );
			$approved = is_array( $approved ) ? $approved : array();
		}
		$projection = array(
			'id' => $user_id,
			'name' => (string) ( $approved['display_name'] ?? ProfileLinkResolver::display_name( $user_id ) ),
			'profile_url' => ProfileLinkResolver::url( $user_id ),
			'specialty' => (string) ( $approved['specialty'] ?? ( $approved['specialization'] ?? '' ) ),
			'country' => (string) ( $approved['country'] ?? '' ),
			'clinic_name' => (string) ( $approved['clinic_name'] ?? ( $approved['clinic'] ?? '' ) ),
			'is_founder' => $founder,
			'is_administrator' => self::is_administrator( $user_id ),
			'is_verified_doctor' => self::is_verified_doctor( $user_id ),
			'is_trusted_publisher' => self::is_trusted_publisher( $user_id ),
		);
		foreach ( $projection as $key => $value ) {
			if ( in_array( $key, array( 'id', 'is_founder', 'is_administrator', 'is_verified_doctor', 'is_trusted_publisher' ), true ) ) { continue; }
			$projection[ $key ] = is_scalar( $value ) ? ( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $value ) : strip_tags( (string) $value ) ) : '';
		}
		$filtered = self::filtered( 'sabri_hnf_public_author_projection', $projection, $user_id );
		$filtered = is_array( $filtered ) ? $filtered : $projection;
		$allowed = array_fill_keys( array_keys( $projection ), true );
		$filtered = array_intersect_key( $filtered, $allowed );
		foreach ( $projection as $key => $default ) {
			if ( ! array_key_exists( $key, $filtered ) ) { $filtered[ $key ] = $default; }
			if ( in_array( $key, array( 'id', 'is_founder', 'is_administrator', 'is_verified_doctor', 'is_trusted_publisher' ), true ) ) {
				$filtered[ $key ] = 'id' === $key ? $user_id : (bool) $default;
			} else {
				$filtered[ $key ] = is_scalar( $filtered[ $key ] ) ? ( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( (string) $filtered[ $key ] ) : strip_tags( (string) $filtered[ $key ] ) ) : '';
			}
		}
		return $filtered;
	}

	/** Deny stale File 21 powers unless File 00 validates the exact subject. */
	public static function guard_file21_capabilities( $allcaps, $caps, $args, $user ) {
		unset( $args );
		if ( ! is_array( $allcaps ) || ! is_object( $user ) || empty( $user->ID ) ) { return $allcaps; }
		$owned = self::file21_capabilities();
		$requested = array_values( array_intersect( is_array( $caps ) ? $caps : array(), $owned ) );
		if ( empty( $requested ) ) { return $allcaps; }
		$user_id = self::positive_id( $user->ID );
		$active = self::subject_is_active( $user_id );
		$current_id = function_exists( 'get_current_user_id' ) ? self::positive_id( get_current_user_id() ) : 0;
		$action_ready = $current_id !== $user_id || self::current_action_ready( $user_id );
		if ( ! $active || ! $action_ready ) {
			foreach ( $owned as $capability ) { $allcaps[ $capability ] = false; }
		}
		return $allcaps;
	}

	private static function file21_capabilities() {
		$capabilities = array();
		foreach ( array( __NAMESPACE__ . '\\Capabilities', __NAMESPACE__ . '\\NewsCapabilities' ) as $class_name ) {
			if ( class_exists( $class_name ) && is_callable( array( $class_name, 'capabilities' ) ) ) {
				$values = call_user_func( array( $class_name, 'capabilities' ) );
				if ( is_array( $values ) ) { $capabilities = array_merge( $capabilities, $values ); }
			}
		}
		if ( class_exists( __NAMESPACE__ . '\\Phase5Contracts' ) && is_callable( array( __NAMESPACE__ . '\\Phase5Contracts', 'capabilities' ) ) ) {
			$values = Phase5Contracts::capabilities();
			if ( is_array( $values ) ) { $capabilities = array_merge( $capabilities, $values ); }
		}
		return array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_key' ), $capabilities ) ) ) );
	}

	private static function assertions_identify_active_class( array $assertions, $class ) {
		return empty( $assertions['_contract_error'] )
			&& $class === ( $assertions['account_class'] ?? '' )
			&& ! empty( $assertions['approved'] )
			&& ! self::assertions_have_hard_block( $assertions );
	}

	private static function assertions_are_active( array $assertions ) {
		return empty( $assertions['_contract_error'] )
			&& ! self::assertions_have_hard_block( $assertions )
			&& ! empty( $assertions['approved'] )
			&& ! empty( $assertions['eligible'] )
			&& ( ! array_key_exists( 'guardian_verified', $assertions ) || ! empty( $assertions['guardian_verified'] ) );
	}

	private static function assertions_have_hard_block( array $assertions ) {
		$status = self::clean_key( $assertions['status'] ?? '' );
		return ! empty( $assertions['_contract_error'] )
			|| ! empty( $assertions['suspended'] )
			|| in_array( $status, array( 'rejected', 'suspended', 'expired', 'appeal_review', 'erasure_pending', 'invalid_application' ), true );
	}

	private static function filtered( $hook, $value, $user_id ) { return function_exists( 'apply_filters' ) ? apply_filters( $hook, $value, $user_id ) : $value; }
	private static function positive_id( $value ) { return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/', (string) $value ) ? (int) $value : 0; }
	private static function clean_key( $value ) { return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ); }
}
