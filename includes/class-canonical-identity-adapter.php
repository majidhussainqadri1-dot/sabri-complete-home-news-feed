<?php
/**
 * Canonical cross-plugin identity and publishing authority adapter.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolves accepted identity states across all project generations. */
final class CanonicalIdentityAdapter {
	/** Register extensibility hooks. */
	public static function register() {}

	/** Canonical role aliases accepted from the installed project generations. */
	public static function role_aliases() {
		return array(
			'founder' => array( 'founder', 'sabri_founder' ),
			'administrator' => array( 'administrator' ),
			'verified_doctor' => array( 'verified_doctor', 'approved_doctor', 'doctor_verified', 'sabri_verified_doctor' ),
			'unverified_doctor' => array( 'doctor', 'sabri_doctor', 'sabri_doctor_pending' ),
			'student' => array( 'student', 'sabri_student' ),
			'patient' => array( 'patient', 'sabri_patient', 'subscriber' ),
		);
	}

	/** Return normalized roles for an existing user. */
	public static function roles( $user_id ) {
		$user_id = self::positive_id( $user_id );
		$user = $user_id > 0 && function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : false;
		$roles = $user && isset( $user->roles ) && is_array( $user->roles ) ? $user->roles : array();
		return array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_key' ), $roles ) ) ) );
	}

	/** Whether the user is the configured Founder. */
	public static function is_founder( $user_id ) {
		$user_id = self::positive_id( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}
		foreach ( array( 'spf_founder_user_id', 'spd_founder_user_id', 'sabri_founder_user_id' ) as $option ) {
			if ( function_exists( 'get_option' ) && $user_id === absint( get_option( $option, 0 ) ) ) {
				return true;
			}
		}
		return self::has_role_group( $user_id, 'founder' );
	}

	/** Whether the user is an Administrator. */
	public static function is_administrator( $user_id ) {
		$user_id = self::positive_id( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}
		return in_array( 'administrator', self::roles( $user_id ), true ) || ( function_exists( 'user_can' ) && user_can( $user_id, 'manage_options' ) );
	}

	/** Whether a doctor is institutionally verified by any accepted project source. */
	public static function is_verified_doctor( $user_id ) {
		$user_id = self::positive_id( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( self::has_role_group( $user_id, 'verified_doctor' ) ) {
			return true;
		}
		foreach ( array( '_smc_doctor_verified', '_sabri_doctor_verified' ) as $key ) {
			if ( self::truthy_user_meta( $user_id, $key ) ) {
				return true;
			}
		}
		$status = self::clean_key( self::user_meta( $user_id, '_spd_verification_status' ) );
		if ( in_array( $status, array( 'verified', 'approved', 'active' ), true ) ) {
			return true;
		}
		return (bool) self::filtered( 'sabri_hnf_identity_is_verified_doctor', false, $user_id );
	}

	/** Bounded IDs of institutionally verified doctors for the Home filter. */
	public static function verified_doctor_ids( $limit = 500 ) {
		$limit = max( 1, min( 500, (int) $limit ) );
		if ( ! function_exists( 'get_users' ) ) {
			return array();
		}
		$aliases = self::role_aliases();
		$role_ids = get_users( array( 'role__in' => $aliases['verified_doctor'], 'fields' => 'ID', 'number' => $limit ) );
		$meta_ids = array();
		foreach ( array( '_smc_doctor_verified', '_sabri_doctor_verified' ) as $key ) {
			$ids = get_users( array( 'meta_key' => $key, 'meta_value' => '1', 'fields' => 'ID', 'number' => $limit ) );
			$meta_ids = array_merge( $meta_ids, is_array( $ids ) ? $ids : array() );
		}
		$status_ids = get_users( array( 'meta_key' => '_spd_verification_status', 'meta_value' => array( 'verified', 'approved', 'active' ), 'meta_compare' => 'IN', 'fields' => 'ID', 'number' => $limit ) );
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', array_merge( (array) $role_ids, $meta_ids, (array) $status_ids ) ) ) ) ), 0, $limit );
		$filtered = function_exists( 'apply_filters' ) ? apply_filters( 'sabri_hnf_verified_doctor_ids', $ids, $limit ) : $ids;
		return is_array( $filtered ) ? array_slice( array_values( array_unique( array_filter( array_map( 'absint', $filtered ) ) ) ), 0, $limit ) : $ids;
	}

	/** Whether the user is a doctor whose institutional verification is incomplete. */
	public static function is_unverified_doctor( $user_id ) {
		$user_id = self::positive_id( $user_id );
		return $user_id > 0 && ! self::is_verified_doctor( $user_id ) && self::has_role_group( $user_id, 'unverified_doctor' );
	}

	/** Whether the institution explicitly trusts the account for direct publication. */
	public static function is_trusted_publisher( $user_id ) {
		$user_id = self::positive_id( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( self::is_founder( $user_id ) || self::is_administrator( $user_id ) ) {
			return true;
		}
		foreach ( array( '_smc_trusted_publisher', '_sabri_trusted_publisher' ) as $key ) {
			if ( self::truthy_user_meta( $user_id, $key ) ) {
				return true;
			}
		}
		return function_exists( 'user_can' ) && user_can( $user_id, 'sabri_feed_publish_posts' );
	}

	/** Canonical immediate-publication decision. */
	public static function can_publish_immediately( $user_id, $settings = null ) {
		$user_id = self::positive_id( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( self::is_founder( $user_id ) || self::is_administrator( $user_id ) ) {
			return true;
		}
		$settings = is_array( $settings ) ? $settings : Settings::get();
		$policy = isset( $settings['capabilities']['verified_doctor_policy'] ) ? self::clean_key( $settings['capabilities']['verified_doctor_policy'] ) : 'trusted';
		if ( ! self::is_verified_doctor( $user_id ) ) {
			return false;
		}
		if ( 'publish' === $policy ) {
			return true;
		}
		// Old `submit` installations are safely interpreted as trusted-only,
		// rather than silently demoting an institutionally trusted doctor.
		return in_array( $policy, array( 'trusted', 'submit' ), true ) && self::is_trusted_publisher( $user_id );
	}

	/** Public-safe author projection for Feed cards and File 22. */
	public static function public_projection( $user_id ) {
		$user_id = self::positive_id( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}
		$projection = array(
			'id' => $user_id,
			'name' => ProfileLinkResolver::display_name( $user_id ),
			'profile_url' => ProfileLinkResolver::url( $user_id ),
			'specialty' => self::first_public_meta( $user_id, array( '_smc_specialty', 'spd_specialty', 'specialty', 'doctor_specialty' ) ),
			'country' => self::first_public_meta( $user_id, array( '_smc_country', 'spd_country', 'country', 'doctor_country' ) ),
			'clinic_name' => self::first_public_meta( $user_id, array( '_smc_clinic_name', 'spd_clinic_name', 'clinic_name' ) ),
			'is_founder' => self::is_founder( $user_id ),
			'is_administrator' => self::is_administrator( $user_id ),
			'is_verified_doctor' => self::is_verified_doctor( $user_id ),
			'is_trusted_publisher' => self::is_trusted_publisher( $user_id ),
		);
		$filtered = self::filtered( 'sabri_hnf_public_author_projection', $projection, $user_id );
		return is_array( $filtered ) ? $filtered : $projection;
	}

	/** Whether a user belongs to a canonical role group. */
	private static function has_role_group( $user_id, $group ) {
		$aliases = self::role_aliases();
		$allowed = isset( $aliases[ $group ] ) ? $aliases[ $group ] : array();
		return (bool) array_intersect( self::roles( $user_id ), $allowed );
	}

	/** First non-empty public metadata value. */
	private static function first_public_meta( $user_id, array $keys ) {
		foreach ( $keys as $key ) {
			$value = self::user_meta( $user_id, $key );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				$value = trim( (string) $value );
				return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : strip_tags( $value );
			}
		}
		return '';
	}

	/** Read one user metadata value. */
	private static function user_meta( $user_id, $key ) {
		return function_exists( 'get_user_meta' ) ? get_user_meta( $user_id, $key, true ) : '';
	}

	/** Strict truthy metadata. */
	private static function truthy_user_meta( $user_id, $key ) {
		$value = self::user_meta( $user_id, $key );
		return in_array( $value, array( 1, '1', true, 'yes', 'approved', 'verified', 'active' ), true );
	}

	/** Apply an optional filter. */
	private static function filtered( $hook, $value, $user_id ) {
		return function_exists( 'apply_filters' ) ? apply_filters( $hook, $value, $user_id ) : $value;
	}

	/** Strict positive identifier. */
	private static function positive_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/', (string) $value ) ? (int) $value : 0;
	}

	/** Normalize a controlled key. */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}
}