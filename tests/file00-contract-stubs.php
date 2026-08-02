<?php
/**
 * Subject-bound File 00 contract fixtures for repository behavior tests.
 *
 * Production code still requires the real File 00 SMC_Contracts owner. This
 * fixture exists only in the test tree and derives assertions from the explicit
 * test user/role/capability state.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! class_exists( 'SMC_Contracts', false ) ) {
	final class SMC_Contracts {
		public static function assertions( $user_id ) {
			$user_id = absint( $user_id );
			if ( $user_id <= 0 ) {
				return array();
			}

			global $sabri_test_user_roles, $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_roles;
			global $sabri_test_membership_assertions;

			if ( isset( $sabri_test_membership_assertions[ $user_id ] ) && is_array( $sabri_test_membership_assertions[ $user_id ] ) ) {
				return array_merge(
					array(
						'contract_version' => '1.1.2',
						'user_id' => $user_id,
					),
					$sabri_test_membership_assertions[ $user_id ]
				);
			}

			$roles = isset( $sabri_test_user_roles[ $user_id ] ) && is_array( $sabri_test_user_roles[ $user_id ] )
				? array_values( array_unique( array_map( 'sanitize_key', $sabri_test_user_roles[ $user_id ] ) ) )
				: array();
			$has_role = static function ( array $aliases ) use ( $roles ) {
				return (bool) array_intersect( $aliases, $roles );
			};
			$has_cap = static function ( $capability ) use ( $user_id, $roles, $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_roles ) {
				if ( $user_id === (int) $sabri_test_current_user_id && ! empty( $sabri_test_current_caps[ $capability ] ) ) {
					return true;
				}
				foreach ( $roles as $role_slug ) {
					if ( isset( $sabri_test_roles[ $role_slug ]->capabilities[ $capability ] ) && ! empty( $sabri_test_roles[ $role_slug ]->capabilities[ $capability ] ) ) {
						return true;
					}
				}
				return false;
			};

			$is_founder = $has_role( array( 'founder', 'sabri_founder' ) );
			$is_admin = $has_role( array( 'administrator' ) );
			$is_verified_doctor = $has_role( array( 'verified_doctor', 'sabri_doctor_verified', 'sabri_verified_doctor', 'approved_doctor', 'doctor_verified' ) );
			$is_doctor = $is_verified_doctor || $has_role( array( 'doctor', 'sabri_doctor', 'sabri_doctor_pending' ) );
			$account_class = $is_founder ? 'founder' : ( $is_admin ? 'administrator' : 'member' );
			$membership_type = $is_doctor ? 'doctor' : ( $has_role( array( 'student', 'sabri_student' ) ) ? 'student' : 'patient' );
			$can_publish = $is_founder || $is_admin || $has_cap( 'sabri_feed_publish_posts' );

			return array(
				'contract_version' => '1.1.2',
				'user_id' => $user_id,
				'account_class' => $account_class,
				'membership_type' => $membership_type,
				'status' => 'active',
				'approved' => true,
				'eligible' => true,
				'guardian_verified' => true,
				'identity_evidence_current' => true,
				'two_factor_ready' => true,
				'session_two_factor' => true,
				'sensitive_action_ready' => true,
				'professional_verified' => $is_verified_doctor,
				'can_publish' => $can_publish,
				'can_practice' => $is_verified_doctor,
				'public_profile_allowed' => $is_founder || $is_verified_doctor,
				'institutional_account' => $is_founder || $is_admin,
				'suspended' => false,
			);
		}
	}
}
