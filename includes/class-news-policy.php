<?php
/**
 * Phase 4B Editorial News authorization policy.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Centralizes object, queue, preview, and reviewer-assignment policy. */
final class NewsPolicy {
	/** Register policy foundations without opening public routes. */
	public static function register() {
		// Services and administration controllers call this policy explicitly.
	}

	/** Whether Phase 4B writes are globally permitted. */
	public static function writes_allowed() {
		return ! ( class_exists( __NAMESPACE__ . '\\SafeMode' ) && SafeMode::public_features_disabled() );
	}

	/** Whether the current user may create an Editorial News draft. */
	public static function can_create() {
		return self::writes_allowed()
			&& function_exists( 'current_user_can' )
			&& current_user_can( 'create_editorial_news' );
	}

	/** Whether the current user may edit a specific Editorial News object. */
	public static function can_edit( $post_id ) {
		$post_id = function_exists( 'absint' ) ? absint( $post_id ) : max( 0, (int) $post_id );
		return self::writes_allowed()
			&& $post_id > 0
			&& function_exists( 'current_user_can' )
			&& current_user_can( 'edit_editorial_news', $post_id );
	}

	/** Whether the current user may preview a private Editorial News object. */
	public static function can_preview( $post_id ) {
		$post_id = function_exists( 'absint' ) ? absint( $post_id ) : max( 0, (int) $post_id );
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
			// The Phase 4B reviewing-editor field also owns fact-check assignment.
			'editorial' => array( 'review_editorial_news', 'fact_check_editorial_news' ),
			'fact-check' => array( 'fact_check_editorial_news' ),
			'medical' => array( 'medical_review_editorial_news' ),
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
		$post_id = function_exists( 'absint' ) ? absint( $post_id ) : max( 0, (int) $post_id );
		$reviewer_id = function_exists( 'absint' ) ? absint( $reviewer_id ) : max( 0, (int) $reviewer_id );
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
}
