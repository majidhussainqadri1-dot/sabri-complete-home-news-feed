<?php
/**
 * Phase 3C comment policy.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides bounded, filterable comment rules from the frozen Phase 3 contract.
 */
final class CommentPolicy {
	const COMMENT_TYPE = 'sabri_feed_comment';
	const META_DELETED = '_sabri_hnf_comment_deleted';
	const META_EDITED_AT = '_sabri_hnf_comment_edited_at';
	const META_PRIVACY_SCAN = '_sabri_hnf_comment_privacy_scan';

	/**
	 * Maximum plain-text comment length.
	 *
	 * @return int
	 */
	public static function max_length() {
		return self::filtered_int( 'sabri_feed_comment_max_length', 2000, 100, 10000 );
	}

	/**
	 * Maximum reply nesting below a top-level comment.
	 *
	 * @return int
	 */
	public static function max_reply_depth() {
		return self::filtered_int( 'sabri_feed_comment_max_reply_depth', 3, 1, 6 );
	}

	/**
	 * Owner edit window in minutes.
	 *
	 * @return int
	 */
	public static function edit_minutes() {
		return self::filtered_int( 'sabri_feed_comment_edit_minutes', 15, 1, 1440 );
	}

	/**
	 * New-comment moderation policy.
	 *
	 * Safe default is hold. Only moderators receive immediate approval.
	 *
	 * @return string
	 */
	public static function new_comment_policy() {
		$policy = 'hold';
		if ( function_exists( 'apply_filters' ) ) {
			$policy = apply_filters( 'sabri_feed_new_comment_policy', $policy );
		}
		$policy = function_exists( 'sanitize_key' ) ? sanitize_key( $policy ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $policy ) );
		return in_array( $policy, array( 'hold', 'approve' ), true ) ? $policy : 'hold';
	}

	/**
	 * Whether clinical-case comments must pass the privacy scanner.
	 *
	 * @return bool
	 */
	public static function clinical_privacy_scan_enabled() {
		$enabled = true;
		if ( function_exists( 'apply_filters' ) ) {
			$enabled = apply_filters( 'sabri_feed_clinical_comment_privacy_scan', $enabled );
		}
		return (bool) $enabled;
	}

	/**
	 * Whether the current session may moderate comments.
	 *
	 * @return bool
	 */
	public static function current_user_can_moderate() {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}

		return current_user_can( 'moderate_comments' ) || current_user_can( 'sabri_feed_moderate_posts' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Resolve the native approval value for a new comment.
	 *
	 * @return string
	 */
	public static function new_comment_approved_value() {
		if ( self::current_user_can_moderate() || 'approve' === self::new_comment_policy() ) {
			return '1';
		}
		return '0';
	}

	/**
	 * Bound a filterable integer.
	 *
	 * @param string $hook Filter name.
	 * @param int    $default Default.
	 * @param int    $minimum Minimum.
	 * @param int    $maximum Maximum.
	 * @return int
	 */
	private static function filtered_int( $hook, $default, $minimum, $maximum ) {
		$value = $default;
		if ( function_exists( 'apply_filters' ) ) {
			$value = apply_filters( $hook, $value );
		}
		return min( $maximum, max( $minimum, (int) $value ) );
	}
}
