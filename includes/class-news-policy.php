<?php
/**
 * Editorial News authorization and public-visibility policy.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Centralizes object, queue, preview, reviewer, and public visibility policy. */
final class NewsPolicy {
	/** Register policy foundations. */
	public static function register() {
		// Services and controllers call this policy explicitly.
	}

	/** Whether Phase 4B writes are globally permitted. */
	public static function writes_allowed() {
		return ! ( class_exists( __NAMESPACE__ . '\\SafeMode' ) && SafeMode::public_features_disabled() );
	}

	/** Whether public Editorial News reads are enabled. */
	public static function public_reads_allowed() {
		if ( class_exists( __NAMESPACE__ . '\\SafeMode' ) && SafeMode::public_features_disabled() ) {
			return false;
		}
		return class_exists( __NAMESPACE__ . '\\NewsFeatureSettings' )
			&& NewsFeatureSettings::enabled( 'editorial_news_enabled' );
	}

	/** Public archive/feed states. */
	public static function public_archive_states() {
		return array( 'published', 'updated', 'correction-pending', 'corrected' );
	}

	/** Read the exact authoritative workflow state. */
	public static function workflow_state( $post_id ) {
		$post_id = self::positive_int( $post_id );
		if ( $post_id < 1 || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}
		$state = get_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, true );
		return is_string( $state ) && in_array( $state, Phase4Contracts::editorial_states(), true ) ? $state : '';
	}

	/** Whether one object is eligible for a specific public projection. */
	public static function is_public_post( $post, $context = 'archive' ) {
		if ( ! self::public_reads_allowed() ) {
			return false;
		}
		if ( is_numeric( $post ) && function_exists( 'get_post' ) ) {
			$post = get_post( (int) $post );
		}
		if ( ! is_object( $post ) || empty( $post->ID ) ) {
			return false;
		}
		$post_type = isset( $post->post_type ) ? (string) $post->post_type : ( function_exists( 'get_post_type' ) ? (string) get_post_type( $post->ID ) : '' );
		if ( Phase4Contracts::POST_TYPE !== $post_type ) {
			return false;
		}
		$state = self::workflow_state( $post->ID );
		$status = isset( $post->post_status ) ? (string) $post->post_status : ( function_exists( 'get_post_status' ) ? (string) get_post_status( $post->ID ) : '' );

		if ( 'retraction' === $context ) {
			return 'retracted' === $state && in_array( $status, array( 'private', 'publish' ), true );
		}
		if ( 'retracted' === $state ) {
			return in_array( $context, array( 'single', 'rest' ), true )
				&& in_array( $status, array( 'private', 'publish' ), true );
		}
		if ( ! in_array( $state, self::public_archive_states(), true ) ) {
			return false;
		}
		return 'publish' === $status;
	}

	/** Whether one ID may be read publicly. */
	public static function can_public_read( $post_id, $context = 'single' ) {
		$post_id = self::positive_int( $post_id );
		return $post_id > 0 && function_exists( 'get_post' ) && self::is_public_post( get_post( $post_id ), $context );
	}

	/** Whether the current user may create an Editorial News draft. */
	public static function can_create() {
		return self::writes_allowed()
			&& function_exists( 'current_user_can' )
			&& current_user_can( 'create_editorial_news' );
	}

	/** Whether the current user may edit a specific Editorial News object. */
	public static function can_edit( $post_id ) {
		$post_id = self::positive_int( $post_id );
		return self::writes_allowed()
			&& $post_id > 0
			&& function_exists( 'current_user_can' )
			&& current_user_can( 'edit_editorial_news', $post_id );
	}

	/** Whether the current user may preview a private Editorial News object. */
	public static function can_preview( $post_id ) {
		$post_id = self::positive_int( $post_id );
		if ( $post_id < 1 || ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		return current_user_can( 'edit_editorial_news', $post_id )
			|| current_user_can( 'review_editorial_news' )
			|| current_user_can( 'read_editorial_news_item', $post_id );
	}

	/** Exact capabilities required for one reviewer assignment. */
	public static function reviewer_capabilities( $review_type ) {
		$map = array(
			'editorial'  => array( 'review_editorial_news' ),
			'fact-check' => array( 'fact_check_editorial_news' ),
			'medical'    => array( 'medical_review_editorial_news' ),
		);
		return is_string( $review_type ) && isset( $map[ $review_type ] ) ? $map[ $review_type ] : array();
	}

	/** Primary capability retained for stable callers and diagnostics. */
	public static function reviewer_capability( $review_type ) {
		$required = self::reviewer_capabilities( $review_type );
		return $required ? $required[0] : 'do_not_allow';
	}

	/** Whether the current user may assign a reviewer to an article. */
	public static function can_assign_reviewer( $post_id, $reviewer_id, $review_type ) {
		$post_id = self::positive_int( $post_id );
		$reviewer_id = self::positive_int( $reviewer_id );
		$required = self::reviewer_capabilities( $review_type );
		if ( ! self::writes_allowed() || $reviewer_id < 1 || empty( $required ) || ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		if ( ! current_user_can( 'review_editorial_news' ) ) {
			return false;
		}
		if ( $post_id > 0 && ! current_user_can( 'edit_editorial_news', $post_id ) ) {
			return false;
		}
		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $reviewer_id === $current_user_id && ! current_user_can( 'manage_news_settings' ) && ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		if ( ! function_exists( 'user_can' ) ) {
			return false;
		}
		foreach ( $required as $capability ) {
			if ( ! user_can( $reviewer_id, $capability ) ) {
				return false;
			}
		}
		return true;
	}

	/** Whether the current user may access a queue requiring one capability. */
	public static function can_access_queue( $capability, $own_only = false ) {
		if ( ! function_exists( 'current_user_can' ) || ! function_exists( 'get_current_user_id' ) || get_current_user_id() < 1 ) {
			return false;
		}
		if ( $own_only ) {
			return current_user_can( 'create_editorial_news' ) || current_user_can( 'edit_own_editorial_news' );
		}
		return is_string( $capability ) && '' !== $capability && current_user_can( $capability );
	}

	/** Strict positive integer without converting negative values. */
	private static function positive_int( $value ) {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : 0;
		}
		return is_string( $value ) && preg_match( '/^[1-9][0-9]*$/D', $value ) ? (int) $value : 0;
	}
}
