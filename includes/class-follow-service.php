<?php
/**
 * Phase 3D Follow and Following service.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File 21 interaction facade. File 17 is canonical when its relationship
 * runtime is available; the historical File 21 store remains a fail-soft
 * compatibility path only for installations not yet cut over to File 17.
 */
final class FollowService {
	const TARGET_TYPE = 'user';

	/** Follow an existing user. */
	public static function follow( $target_user_id, $nonce = '', $user_id = 0 ) {
		if ( ! Phase3FeatureSettings::enabled( 'follows_enabled' ) ) {
			return InteractionResult::error( 'follows_disabled', 'Following is currently unavailable.', array(), 503 );
		}
		$authorized = self::authorize( $target_user_id, $nonce, $user_id );
		if ( empty( $authorized['ok'] ) ) { return $authorized; }
		$user_id = (int) $authorized['data']['user_id'];
		$target_user_id = (int) $authorized['data']['target_user_id'];
		$limit = InteractionRateLimiter::attempt( 'follows', $user_id, $target_user_id );
		if ( empty( $limit['ok'] ) ) { return $limit; }

		if ( NetworkRelationshipBridge::native_available() ) {
			$native = NetworkRelationshipBridge::follow( $user_id, $target_user_id );
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $native ) ) {
				return self::native_error( $native, 'follow_failed' );
			}
			if ( ! is_array( $native ) ) {
				return InteractionResult::error( 'follow_failed', 'The canonical Network relationship could not be confirmed.', array(), 503 );
			}
			FeedQuery::invalidate_cache();
			AuditLog::record( 'user_followed_via_file17', array( 'target_user_id' => $target_user_id ), 'user', $target_user_id );
			return InteractionResult::success( 'user_followed', self::summary( $target_user_id, $user_id ), 'Following.', 200 );
		}

		$current = InteractionQueryRepository::follow_record( $user_id, $target_user_id, self::TARGET_TYPE );
		if ( is_array( $current ) && isset( $current['status'] ) && 'blocked' === sanitize_key( $current['status'] ) ) {
			return InteractionResult::error( 'relationship_blocked', 'This relationship is unavailable.', array(), 403 );
		}
		$became_active = ! is_array( $current ) || 'active' !== sanitize_key( isset( $current['status'] ) ? $current['status'] : '' );
		if ( is_array( $current ) ) {
			$result = self::set_status( $user_id, $target_user_id, 'active' );
		} else {
			$result = InteractionRepository::insert_row(
				'follows',
				array(
					'follower_user_id' => $user_id,
					'target_user_id'   => $target_user_id,
					'target_type'      => self::TARGET_TYPE,
					'status'           => 'active',
				)
			);
			if ( empty( $result['ok'] ) ) {
				$existing = InteractionQueryRepository::follow_record( $user_id, $target_user_id, self::TARGET_TYPE );
				if ( is_array( $existing ) && 'blocked' !== sanitize_key( isset( $existing['status'] ) ? $existing['status'] : '' ) ) {
					$result = self::set_status( $user_id, $target_user_id, 'active' );
				}
			}
		}
		if ( empty( $result['ok'] ) ) { return $result; }
		AuditLog::record( 'user_followed_legacy_fallback', array( 'target_user_id' => $target_user_id ) );
		FeedQuery::invalidate_cache();
		if ( $became_active ) { NotificationBridge::follow_event( $user_id, $target_user_id ); }
		return InteractionResult::success( 'user_followed', self::summary( $target_user_id, $user_id ), 'Following.', 200 );
	}

	/** Stop following a user while retaining history. */
	public static function unfollow( $target_user_id, $nonce = '', $user_id = 0 ) {
		if ( ! Phase3FeatureSettings::enabled( 'follows_enabled' ) ) {
			return InteractionResult::error( 'follows_disabled', 'Following is currently unavailable.', array(), 503 );
		}
		$authorized = self::authorize( $target_user_id, $nonce, $user_id );
		if ( empty( $authorized['ok'] ) ) { return $authorized; }
		$user_id = (int) $authorized['data']['user_id'];
		$target_user_id = (int) $authorized['data']['target_user_id'];
		$limit = InteractionRateLimiter::attempt( 'follows', $user_id, $target_user_id );
		if ( empty( $limit['ok'] ) ) { return $limit; }

		if ( NetworkRelationshipBridge::native_available() ) {
			$native = NetworkRelationshipBridge::unfollow( $user_id, $target_user_id );
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $native ) ) {
				return self::native_error( $native, 'unfollow_failed' );
			}
			if ( ! is_array( $native ) ) {
				return InteractionResult::error( 'unfollow_failed', 'The canonical Network relationship could not be confirmed.', array(), 503 );
			}
			FeedQuery::invalidate_cache();
			AuditLog::record( 'user_unfollowed_via_file17', array( 'target_user_id' => $target_user_id ), 'user', $target_user_id );
			return InteractionResult::success( 'user_unfollowed', self::summary( $target_user_id, $user_id ), 'No longer following.', 200 );
		}

		$current = InteractionQueryRepository::follow_record( $user_id, $target_user_id, self::TARGET_TYPE );
		if ( is_array( $current ) && isset( $current['status'] ) && 'blocked' === sanitize_key( $current['status'] ) ) {
			return InteractionResult::error( 'relationship_blocked', 'This relationship is unavailable.', array(), 403 );
		}
		if ( is_array( $current ) && isset( $current['status'] ) && 'active' === sanitize_key( $current['status'] ) ) {
			$result = self::set_status( $user_id, $target_user_id, 'removed' );
			if ( empty( $result['ok'] ) ) { return $result; }
		}
		AuditLog::record( 'user_unfollowed_legacy_fallback', array( 'target_user_id' => $target_user_id ) );
		FeedQuery::invalidate_cache();
		return InteractionResult::success( 'user_unfollowed', self::summary( $target_user_id, $user_id ), 'No longer following.', 200 );
	}

	/** Return policy-bounded public count and current-user state. */
	public static function summary( $target_user_id, $viewer_user_id = 0 ) {
		$target_user_id = self::positive_id( $target_user_id );
		$target = self::user( $target_user_id );
		$viewer_user_id = $viewer_user_id ? InteractionPermissions::authenticated_user_id( $viewer_user_id ) : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		$native = NetworkRelationshipBridge::summary( $target_user_id, $viewer_user_id );
		if ( is_array( $native ) ) {
			return $native;
		}
		$count_visible = Phase3FeatureSettings::enabled( 'show_public_follower_counts' );
		$following = false;
		if ( $target && $viewer_user_id > 0 && $viewer_user_id !== $target_user_id ) {
			$record = InteractionQueryRepository::follow_record( $viewer_user_id, $target_user_id, self::TARGET_TYPE );
			$following = is_array( $record ) && isset( $record['status'] ) && 'active' === sanitize_key( $record['status'] );
		}
		return array(
			'target_user_id' => $target ? $target_user_id : 0,
			'following'      => $following,
			'count_visible'  => $count_visible,
			'follower_count' => $target && $count_visible ? InteractionQueryRepository::follower_count( $target_user_id, self::TARGET_TYPE ) : 0,
			'profile_url'    => $target ? ProfileLinkResolver::url( $target_user_id ) : '',
		);
	}

	/** Return the current user's private Following list through REST. */
	public static function following( $nonce = '', $user_id = 0, $limit = 100 ) {
		if ( ! Phase3FeatureSettings::enabled( 'follows_enabled' ) ) {
			return InteractionResult::error( 'follows_disabled', 'Following is currently unavailable.', array(), 503 );
		}
		$user_id = InteractionPermissions::authenticated_user_id( $user_id );
		if ( $user_id <= 0 ) { return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 ); }
		if ( ! CanonicalIdentityAdapter::current_action_ready( $user_id ) ) { return InteractionResult::error( 'identity_assurance_required', 'Current account assurance is required.', array(), 403 ); }
		if ( ! InteractionPermissions::nonce_valid( $nonce ) ) { return InteractionResult::error( 'invalid_nonce', 'The security token is missing or invalid.', array(), 403 ); }
		return self::following_for_user( $user_id, $limit );
	}

	/** Server-rendered private Following list for the current session only. */
	public static function following_for_user( $user_id, $limit = 100 ) {
		if ( ! Phase3FeatureSettings::enabled( 'follows_enabled' ) ) {
			return InteractionResult::error( 'follows_disabled', 'Following is currently unavailable.', array(), 503 );
		}
		$user_id = InteractionPermissions::authenticated_user_id( $user_id );
		if ( $user_id <= 0 ) { return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 ); }
		$items = array();
		foreach ( NetworkRelationshipBridge::following_user_ids( $user_id, $limit ) as $target_user_id ) {
			$target = self::user( $target_user_id );
			if ( ! $target || ! NetworkRelationshipBridge::author_allowed( $user_id, $target_user_id ) ) { continue; }
			$items[] = array(
				'id'           => $target_user_id,
				'display_name' => ProfileLinkResolver::display_name( $target_user_id ),
				'profile_url'  => ProfileLinkResolver::url( $target_user_id ),
				'avatar'       => function_exists( 'get_avatar' ) ? get_avatar( $target_user_id, 48, '', '', array( 'class' => 'sabri-hnf-following__avatar-img' ) ) : '',
			);
		}
		return InteractionResult::success( 'following_list', array( 'items' => $items, 'count' => count( $items ) ), 'Following list loaded.', 200 );
	}

	/** Authorize a user relationship write. */
	private static function authorize( $target_user_id, $nonce, $user_id ) {
		$user_id = InteractionPermissions::authenticated_user_id( $user_id );
		if ( $user_id <= 0 ) { return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 ); }
		if ( ! CanonicalIdentityAdapter::current_action_ready( $user_id ) ) { return InteractionResult::error( 'identity_assurance_required', 'Current account assurance is required.', array(), 403 ); }
		if ( ! InteractionPermissions::nonce_valid( $nonce ) ) { return InteractionResult::error( 'invalid_nonce', 'The security token is missing or invalid.', array(), 403 ); }
		if ( SafeMode::public_features_disabled() ) { return InteractionResult::error( 'interaction_unavailable', 'This action is temporarily unavailable.', array(), 503 ); }
		$target_user_id = self::positive_id( $target_user_id );
		$target = self::user( $target_user_id );
		if ( ! $target ) { return InteractionResult::error( 'user_unavailable', 'The requested user is unavailable.', array(), 404 ); }
		if ( $target_user_id === $user_id ) { return InteractionResult::error( 'self_follow_forbidden', 'You cannot follow your own account.', array(), 400 ); }
		if ( ! NetworkRelationshipBridge::author_allowed( $user_id, $target_user_id ) ) { return InteractionResult::error( 'relationship_blocked', 'This relationship is unavailable.', array(), 403 ); }
		$followable = true;
		if ( function_exists( 'apply_filters' ) ) { $followable = (bool) apply_filters( 'sabri_feed_user_followable', true, $target_user_id, $user_id ); }
		if ( ! $followable ) { return InteractionResult::error( 'user_not_followable', 'This account cannot be followed.', array(), 403 ); }
		return InteractionResult::success( 'authorized', array( 'user_id' => $user_id, 'target_user_id' => $target_user_id ), 'Authorized.', 200 );
	}

	/** Convert a File 17 WP_Error into the existing File 21 interaction envelope. */
	private static function native_error( $error, $fallback_code ) {
		$code = method_exists( $error, 'get_error_code' ) ? sanitize_key( $error->get_error_code() ) : sanitize_key( $fallback_code );
		$message = method_exists( $error, 'get_error_message' ) ? sanitize_text_field( $error->get_error_message() ) : 'The Network relationship action could not be completed.';
		$data = method_exists( $error, 'get_error_data' ) ? $error->get_error_data() : array();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
		return InteractionResult::error( $code ? $code : $fallback_code, $message, array(), $status );
	}

	/** Update one historical local natural-key relationship row. */
	private static function set_status( $follower_user_id, $target_user_id, $status ) {
		return InteractionRepository::update_rows(
			'follows',
			array( 'status' => $status ),
			array( 'follower_user_id' => $follower_user_id, 'target_user_id' => $target_user_id, 'target_type' => self::TARGET_TYPE )
		);
	}

	/** Existing user object. */
	private static function user( $user_id ) {
		$user_id = self::positive_id( $user_id );
		return $user_id > 0 && function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : false;
	}

	/** Strict positive ID. */
	private static function positive_id( $value ) {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/', $value ) ) ) { return 0; }
		$value = (int) $value;
		return $value > 0 ? $value : 0;
	}
}
