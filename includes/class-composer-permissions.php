<?php
/**
 * Public composer permission policy.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enforces Phase 2 create, publish, review, schedule, and edit rules.
 */
final class ComposerPermissions {
	/**
	 * Whether a user belongs to one configured role group.
	 *
	 * @param int                      $user_id User ID.
	 * @param string                   $group Role group setting key.
	 * @param array<string,mixed>|null $settings Settings.
	 * @return bool
	 */
	public static function user_has_role_group( $user_id, $group, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$roles    = self::user_role_slugs( $user_id );
		$allowed  = self::role_setting( $settings, $group );

		return (bool) array_intersect( $roles, $allowed );
	}

	/**
	 * Current or target user roles.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,string>
	 */
	public static function user_role_slugs( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : self::current_user_id();
		$user    = null;

		if ( $user_id > 0 && function_exists( 'get_userdata' ) ) {
			$user = get_userdata( $user_id );
		} elseif ( function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
		}

		if ( is_object( $user ) && isset( $user->roles ) && is_array( $user->roles ) ) {
			return array_values( array_unique( array_map( array( __CLASS__, 'clean_key' ), $user->roles ) ) );
		}

		return array();
	}

	/**
	 * Whether the current user can use the public composer.
	 *
	 * @param int                      $user_id User ID.
	 * @param array<string,mixed>|null $settings Settings.
	 * @return bool
	 */
	public static function user_can_create( $user_id = 0, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$user_id  = $user_id ? (int) $user_id : self::current_user_id();

		if ( $user_id <= 0 || ! SafeMode::feature_enabled( 'composer' ) ) {
			return false;
		}

		if ( self::is_student_or_patient( $user_id, $settings ) ) {
			return false;
		}

		if ( self::current_user_can_any( array( 'sabri_feed_create_posts', 'manage_options' ) ) ) {
			return true;
		}

		return self::user_has_role_group( $user_id, 'founder_roles', $settings )
			|| self::user_has_role_group( $user_id, 'verified_doctor_roles', $settings )
			|| self::user_has_role_group( $user_id, 'unverified_doctor_roles', $settings )
			|| self::user_has_role_group( $user_id, 'editorial_roles', $settings );
	}

	/**
	 * Whether the current user may publish immediately.
	 *
	 * @param int                      $user_id User ID.
	 * @param array<string,mixed>|null $settings Settings.
	 * @return bool
	 */
	public static function user_can_publish( $user_id = 0, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$user_id  = $user_id ? (int) $user_id : self::current_user_id();

		if ( $user_id <= 0 || self::is_student_or_patient( $user_id, $settings ) || SafeMode::public_features_disabled() ) {
			return false;
		}

		if ( self::current_user_can_any( array( 'sabri_feed_publish_posts', 'manage_options' ) ) ) {
			return true;
		}

		foreach ( self::user_role_slugs( $user_id ) as $role_slug ) {
			if ( Capabilities::role_can_publish( $role_slug, $settings ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the current user may submit posts for editorial review.
	 *
	 * @param int                      $user_id User ID.
	 * @param array<string,mixed>|null $settings Settings.
	 * @return bool
	 */
	public static function user_can_submit_for_review( $user_id = 0, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$user_id  = $user_id ? (int) $user_id : self::current_user_id();

		if ( $user_id <= 0 || self::is_student_or_patient( $user_id, $settings ) || SafeMode::public_features_disabled() ) {
			return false;
		}

		return self::user_can_publish( $user_id, $settings )
			|| self::current_user_can_any( array( 'sabri_feed_submit_for_review', 'manage_options' ) )
			|| self::user_has_role_group( $user_id, 'verified_doctor_roles', $settings )
			|| self::user_has_role_group( $user_id, 'unverified_doctor_roles', $settings );
	}

	/**
	 * Whether a user may edit a post.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_can_edit_post( $post_id, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : self::current_user_id();
		$post_id = (int) $post_id;

		if ( $user_id <= 0 || $post_id <= 0 || SafeMode::public_features_disabled() ) {
			return false;
		}

		if ( self::user_can_moderate() ) {
			return true;
		}

		$author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0;
		return $author_id === $user_id && self::user_can_create( $user_id );
	}

	/**
	 * Whether the current user may moderate.
	 *
	 * @return bool
	 */
	public static function user_can_moderate() {
		return self::current_user_can_any( array( 'sabri_feed_moderate_posts', 'edit_others_posts', 'manage_options' ) );
	}

	/**
	 * Resolve a requested composer action to an allowed post status.
	 *
	 * @param string                   $action Action key.
	 * @param int                      $user_id User ID.
	 * @param array<string,mixed>|null $settings Settings.
	 * @param string                   $scheduled_date Scheduled date.
	 * @return array<string,mixed>
	 */
	public static function resolve_status_for_action( $action, $user_id = 0, $settings = null, $scheduled_date = '' ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$action   = self::clean_key( $action );
		$user_id  = $user_id ? (int) $user_id : self::current_user_id();

		if ( ! self::user_can_create( $user_id, $settings ) ) {
			return self::denied( 'composer_denied', __( 'You do not have permission to create posts.', 'sabri-complete-home-news-feed' ) );
		}

		if ( 'draft' === $action ) {
			if ( empty( $settings['composer']['drafts_enabled'] ) ) {
				return self::denied( 'drafts_disabled', __( 'Draft saving is disabled.', 'sabri-complete-home-news-feed' ) );
			}
			return array( 'allowed' => true, 'status' => 'draft' );
		}

		if ( 'schedule' === $action ) {
			if ( empty( $settings['composer']['scheduling_enabled'] ) || ! self::user_can_publish( $user_id, $settings ) || ! self::is_future_date( $scheduled_date ) ) {
				return self::denied( 'schedule_denied', __( 'You do not have permission to schedule this post.', 'sabri-complete-home-news-feed' ) );
			}
			return array( 'allowed' => true, 'status' => 'future' );
		}

		if ( 'publish' === $action ) {
			if ( ! self::user_can_publish( $user_id, $settings ) ) {
				return self::denied( 'publish_denied', __( 'This post must be submitted for review.', 'sabri-complete-home-news-feed' ) );
			}
			return array( 'allowed' => true, 'status' => 'publish' );
		}

		if ( self::user_can_submit_for_review( $user_id, $settings ) ) {
			return array( 'allowed' => true, 'status' => 'pending' );
		}

		return self::denied( 'submit_denied', __( 'You do not have permission to submit this post.', 'sabri-complete-home-news-feed' ) );
	}

	/**
	 * Whether user is configured as student or patient.
	 *
	 * @param int                      $user_id User ID.
	 * @param array<string,mixed>|null $settings Settings.
	 * @return bool
	 */
	public static function is_student_or_patient( $user_id = 0, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$user_id  = $user_id ? (int) $user_id : self::current_user_id();

		return self::user_has_role_group( $user_id, 'student_roles', $settings ) || self::user_has_role_group( $user_id, 'patient_roles', $settings );
	}

	/**
	 * Standard denied response payload.
	 *
	 * @param string $code Code.
	 * @param string $message Message.
	 * @return array<string,mixed>
	 */
	private static function denied( $code, $message ) {
		return array(
			'allowed' => false,
			'code'    => $code,
			'message' => $message,
		);
	}

	/**
	 * Check current user capabilities.
	 *
	 * @param array<int,string> $capabilities Capabilities.
	 * @return bool
	 */
	private static function current_user_can_any( array $capabilities ) {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}

		foreach ( $capabilities as $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Configured role list.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $key Key.
	 * @return array<int,string>
	 */
	private static function role_setting( array $settings, $key ) {
		if ( empty( $settings['capabilities'][ $key ] ) || ! is_array( $settings['capabilities'][ $key ] ) ) {
			return array();
		}

		$roles = array();
		foreach ( $settings['capabilities'][ $key ] as $role ) {
			$role = self::clean_key( $role );
			if ( '' !== $role ) {
				$roles[] = $role;
			}
		}

		return array_values( array_unique( $roles ) );
	}

	/**
	 * Whether a date is in the future.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	private static function is_future_date( $date ) {
		if ( '' === trim( (string) $date ) ) {
			return false;
		}

		$timestamp = strtotime( (string) $date );
		return $timestamp && $timestamp > time();
	}

	/**
	 * Current user ID.
	 *
	 * @return int
	 */
	private static function current_user_id() {
		return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
	}

	/**
	 * Clean key.
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
}
