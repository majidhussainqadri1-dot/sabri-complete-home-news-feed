<?php
/**
 * Public Composer permission policy.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Enforces create, publish, review, schedule and edit rules. */
final class ComposerPermissions {
	/** Whether a user belongs to one configured or canonical role group. */
	public static function user_has_role_group( $user_id, $group, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$user_id = $user_id ? (int) $user_id : self::current_user_id();
		$roles = self::user_role_slugs( $user_id );
		$allowed = self::role_setting( $settings, $group );
		$canonical_key = self::canonical_group_key( $group );
		$aliases = CanonicalIdentityAdapter::role_aliases();
		if ( $canonical_key && isset( $aliases[ $canonical_key ] ) ) {
			$allowed = array_merge( $allowed, $aliases[ $canonical_key ] );
		}
		return (bool) array_intersect( $roles, array_values( array_unique( $allowed ) ) );
	}

	/** Current or target user roles. */
	public static function user_role_slugs( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : self::current_user_id();
		return CanonicalIdentityAdapter::roles( $user_id );
	}

	/** Whether a user is an immediate publisher under the canonical policy. */
	public static function user_is_privileged_publisher( $user_id = 0, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$user_id = $user_id ? (int) $user_id : self::current_user_id();
		return self::current_actor_matches( $user_id )
			&& CanonicalIdentityAdapter::current_action_ready( $user_id )
			&& CanonicalIdentityAdapter::can_publish_immediately( $user_id, $settings )
			&& self::current_user_can_any( array( 'sabri_feed_publish_posts', 'manage_options' ) );
	}

	/** Whether the current user can use the public social Composer. */
	public static function user_can_create( $user_id = 0, $settings = null ) {
		unset( $settings );
		$user_id = $user_id ? (int) $user_id : self::current_user_id();
		if ( $user_id <= 0 || ! SafeMode::feature_enabled( 'composer' ) || ! self::current_actor_matches( $user_id ) ) {
			return false;
		}
		return CanonicalIdentityAdapter::current_action_ready( $user_id )
			&& CanonicalIdentityAdapter::can_create_social_content( $user_id )
			&& self::current_user_can_any( array( 'sabri_feed_create_posts', 'manage_options' ) );
	}

	/** Whether the current user may publish immediately. */
	public static function user_can_publish( $user_id = 0, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$user_id = $user_id ? (int) $user_id : self::current_user_id();
		if ( $user_id <= 0 || SafeMode::public_features_disabled() || ! self::current_actor_matches( $user_id ) ) {
			return false;
		}
		return CanonicalIdentityAdapter::current_action_ready( $user_id )
			&& CanonicalIdentityAdapter::can_publish_immediately( $user_id, $settings )
			&& self::current_user_can_any( array( 'sabri_feed_publish_posts', 'manage_options' ) );
	}

	/** Whether the current user may submit social posts for review. */
	public static function user_can_submit_for_review( $user_id = 0, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$user_id = $user_id ? (int) $user_id : self::current_user_id();
		if ( $user_id <= 0 || SafeMode::public_features_disabled() || ! self::current_actor_matches( $user_id ) || self::user_can_publish( $user_id, $settings ) ) {
			return false;
		}
		return CanonicalIdentityAdapter::current_action_ready( $user_id )
			&& CanonicalIdentityAdapter::can_create_social_content( $user_id )
			&& self::current_user_can_any( array( 'sabri_feed_submit_for_review' ) );
	}

	/** Whether a user may edit a post. */
	public static function user_can_edit_post( $post_id, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : self::current_user_id();
		$post_id = (int) $post_id;
		if ( $user_id <= 0 || $post_id <= 0 || SafeMode::public_features_disabled() ) {
			return false;
		}
		if ( self::current_actor_matches( $user_id ) && self::user_can_moderate() ) {
			return true;
		}
		$author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0;
		return $author_id === $user_id && self::user_can_create( $user_id );
	}

	/** Whether the current user may moderate. */
	public static function user_can_moderate() {
		$user_id = self::current_user_id();
		return $user_id > 0
			&& CanonicalIdentityAdapter::current_action_ready( $user_id )
			&& self::current_user_can_any( array( 'sabri_feed_moderate_posts', 'edit_others_posts', 'manage_options' ) );
	}

	/** Resolve a requested Composer action to an allowed WordPress status. */
	public static function resolve_status_for_action( $action, $user_id = 0, $settings = null, $scheduled_date = '' ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$action = self::clean_key( $action );
		$user_id = $user_id ? (int) $user_id : self::current_user_id();
		if ( ! self::user_can_create( $user_id, $settings ) ) {
			return self::denied( 'composer_denied', __( 'You do not have permission to create posts.', 'sabri-complete-home-news-feed' ) );
		}
		if ( 'draft' === $action ) {
			return empty( $settings['composer']['drafts_enabled'] )
				? self::denied( 'drafts_disabled', __( 'Draft saving is disabled.', 'sabri-complete-home-news-feed' ) )
				: array( 'allowed' => true, 'status' => 'draft' );
		}
		if ( 'schedule' === $action ) {
			if ( empty( $settings['composer']['scheduling_enabled'] ) || ! self::user_can_publish( $user_id, $settings ) || ! self::is_future_date( $scheduled_date ) ) {
				return self::denied( 'schedule_denied', __( 'You do not have permission to schedule this post.', 'sabri-complete-home-news-feed' ) );
			}
			return array( 'allowed' => true, 'status' => 'future' );
		}
		if ( 'publish' === $action ) {
			return self::user_can_publish( $user_id, $settings )
				? array( 'allowed' => true, 'status' => 'publish' )
				: self::denied( 'publish_denied', __( 'This post must be submitted for review.', 'sabri-complete-home-news-feed' ) );
		}
		if ( self::user_can_publish( $user_id, $settings ) ) {
			return array( 'allowed' => true, 'status' => 'publish', 'normalized_action' => 'publish' );
		}
		if ( self::user_can_submit_for_review( $user_id, $settings ) ) {
			return array( 'allowed' => true, 'status' => 'pending' );
		}
		return self::denied( 'submit_denied', __( 'You do not have permission to submit this post.', 'sabri-complete-home-news-feed' ) );
	}

	/** Whether a user is configured as a Student or Patient. */
	public static function is_student_or_patient( $user_id = 0, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$user_id = $user_id ? (int) $user_id : self::current_user_id();
		return self::user_has_role_group( $user_id, 'student_roles', $settings ) || self::user_has_role_group( $user_id, 'patient_roles', $settings );
	}

	/** Standard denied response payload. */
	private static function denied( $code, $message ) {
		return array( 'allowed' => false, 'code' => $code, 'message' => $message );
	}

	/** Check current actor capabilities only; never authorize a different target user. */
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

	/** Configured role list. */
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

	/** Map old settings group names to canonical adapter groups. */
	private static function canonical_group_key( $group ) {
		$map = array(
			'founder_roles' => 'founder',
			'verified_doctor_roles' => 'verified_doctor',
			'unverified_doctor_roles' => 'unverified_doctor',
			'student_roles' => 'student',
			'patient_roles' => 'patient',
		);
		return isset( $map[ $group ] ) ? $map[ $group ] : '';
	}

	/** Whether a date is in the future. */
	private static function is_future_date( $date ) {
		$timestamp = class_exists( __NAMESPACE__ . '\\RestComposer' ) ? RestComposer::parse_datetime( $date ) : false;
		return false !== $timestamp && $timestamp > time();
	}

	/** Ensure current-user capability checks are never borrowed for another user. */
	private static function current_actor_matches( $user_id ) {
		return function_exists( 'get_current_user_id' ) && (int) get_current_user_id() === (int) $user_id;
	}

	/** Current user ID. */
	private static function current_user_id() {
		return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
	}

	/** Clean key. */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}
}
