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
 * Implements one bounded user-to-user follow relationship.
 */
final class FollowService {
	const TARGET_TYPE = 'user';

	/**
	 * Follow an existing user.
	 *
	 * @param int    $target_user_id Target user ID.
	 * @param string $nonce REST nonce.
	 * @param int    $user_id Optional current session user ID.
	 * @return array<string,mixed>
	 */
	public static function follow( $target_user_id, $nonce = '', $user_id = 0 ) {
		if ( ! Phase3FeatureSettings::enabled( 'follows_enabled' ) ) {
			return InteractionResult::error( 'follows_disabled', 'Following is currently unavailable.', array(), 503 );
		}

		$authorized = self::authorize( $target_user_id, $nonce, $user_id );
		if ( empty( $authorized['ok'] ) ) {
			return $authorized;
		}

		$user_id        = (int) $authorized['data']['user_id'];
		$target_user_id = (int) $authorized['data']['target_user_id'];
		$limit          = InteractionRateLimiter::attempt( 'follows', $user_id, $target_user_id );
		if ( empty( $limit['ok'] ) ) {
			return $limit;
		}

		$current = InteractionQueryRepository::follow_record( $user_id, $target_user_id, self::TARGET_TYPE );
		if ( is_array( $current ) && isset( $current['status'] ) && 'blocked' === sanitize_key( $current['status'] ) ) {
			return InteractionResult::error( 'relationship_blocked', 'This relationship is unavailable.', array(), 403 );
		}

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

			// Recover safely when a concurrent request wins the unique insert.
			if ( empty( $result['ok'] ) ) {
				$existing = InteractionQueryRepository::follow_record( $user_id, $target_user_id, self::TARGET_TYPE );
				if ( is_array( $existing ) && 'blocked' !== sanitize_key( isset( $existing['status'] ) ? $existing['status'] : '' ) ) {
					$result = self::set_status( $user_id, $target_user_id, 'active' );
				}
			}
		}

		if ( empty( $result['ok'] ) ) {
			return $result;
		}

		AuditLog::record( 'user_followed', array( 'target_user_id' => $target_user_id ) );
		return InteractionResult::success(
			'user_followed',
			self::summary( $target_user_id, $user_id ),
			'Following.',
			200
		);
	}

	/**
	 * Stop following a user while retaining the relationship row.
	 *
	 * @param int    $target_user_id Target user ID.
	 * @param string $nonce REST nonce.
	 * @param int    $user_id Optional current session user ID.
	 * @return array<string,mixed>
	 */
	public static function unfollow( $target_user_id, $nonce = '', $user_id = 0 ) {
		if ( ! Phase3FeatureSettings::enabled( 'follows_enabled' ) ) {
			return InteractionResult::error( 'follows_disabled', 'Following is currently unavailable.', array(), 503 );
		}

		$authorized = self::authorize( $target_user_id, $nonce, $user_id );
		if ( empty( $authorized['ok'] ) ) {
			return $authorized;
		}

		$user_id        = (int) $authorized['data']['user_id'];
		$target_user_id = (int) $authorized['data']['target_user_id'];
		$limit          = InteractionRateLimiter::attempt( 'follows', $user_id, $target_user_id );
		if ( empty( $limit['ok'] ) ) {
			return $limit;
		}

		$current = InteractionQueryRepository::follow_record( $user_id, $target_user_id, self::TARGET_TYPE );
		if ( is_array( $current ) && isset( $current['status'] ) && 'blocked' === sanitize_key( $current['status'] ) ) {
			return InteractionResult::error( 'relationship_blocked', 'This relationship is unavailable.', array(), 403 );
		}

		if ( is_array( $current ) && isset( $current['status'] ) && 'active' === sanitize_key( $current['status'] ) ) {
			$result = self::set_status( $user_id, $target_user_id, 'removed' );
			if ( empty( $result['ok'] ) ) {
				return $result;
			}
		}

		AuditLog::record( 'user_unfollowed', array( 'target_user_id' => $target_user_id ) );
		return InteractionResult::success(
			'user_unfollowed',
			self::summary( $target_user_id, $user_id ),
			'No longer following.',
			200
		);
	}

	/**
	 * Return policy-bounded public count and current-user state.
	 *
	 * @param int $target_user_id Target user ID.
	 * @param int $viewer_user_id Optional current viewer.
	 * @return array<string,mixed>
	 */
	public static function summary( $target_user_id, $viewer_user_id = 0 ) {
		$target_user_id = self::positive_id( $target_user_id );
		$target          = self::user( $target_user_id );
		$viewer_user_id  = $viewer_user_id ? InteractionPermissions::authenticated_user_id( $viewer_user_id ) : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		$count_visible   = Phase3FeatureSettings::enabled( 'show_public_follower_counts' );
		$following       = false;

		if ( $target && $viewer_user_id > 0 && $viewer_user_id !== $target_user_id ) {
			$record    = InteractionQueryRepository::follow_record( $viewer_user_id, $target_user_id, self::TARGET_TYPE );
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

	/**
	 * Return the current user's private Following list through REST.
	 *
	 * @param string $nonce REST nonce.
	 * @param int    $user_id Optional current session user ID.
	 * @param int    $limit Maximum items.
	 * @return array<string,mixed>
	 */
	public static function following( $nonce = '', $user_id = 0, $limit = 100 ) {
		if ( ! Phase3FeatureSettings::enabled( 'follows_enabled' ) ) {
			return InteractionResult::error( 'follows_disabled', 'Following is currently unavailable.', array(), 503 );
		}

		$user_id = InteractionPermissions::authenticated_user_id( $user_id );
		if ( $user_id <= 0 ) {
			return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 );
		}
		if ( ! InteractionPermissions::nonce_valid( $nonce ) ) {
			return InteractionResult::error( 'invalid_nonce', 'The security token is missing or invalid.', array(), 403 );
		}

		return self::following_for_user( $user_id, $limit );
	}

	/**
	 * Server-rendered private Following list for the current session only.
	 *
	 * @param int $user_id Current session user ID.
	 * @param int $limit Maximum items.
	 * @return array<string,mixed>
	 */
	public static function following_for_user( $user_id, $limit = 100 ) {
		if ( ! Phase3FeatureSettings::enabled( 'follows_enabled' ) ) {
			return InteractionResult::error( 'follows_disabled', 'Following is currently unavailable.', array(), 503 );
		}

		$user_id = InteractionPermissions::authenticated_user_id( $user_id );
		if ( $user_id <= 0 ) {
			return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 );
		}

		$items = array();
		foreach ( InteractionQueryRepository::following_user_ids( $user_id, self::TARGET_TYPE, $limit ) as $target_user_id ) {
			$target = self::user( $target_user_id );
			if ( ! $target ) {
				continue;
			}
			$items[] = array(
				'id'          => $target_user_id,
				'display_name'=> ProfileLinkResolver::display_name( $target_user_id ),
				'profile_url' => ProfileLinkResolver::url( $target_user_id ),
				'avatar'      => function_exists( 'get_avatar' ) ? get_avatar( $target_user_id, 48, '', '', array( 'class' => 'sabri-hnf-following__avatar-img' ) ) : '',
			);
		}

		return InteractionResult::success(
			'following_list',
			array(
				'items' => $items,
				'count' => count( $items ),
			),
			'Following list loaded.',
			200
		);
	}

	/**
	 * Authorize a user relationship write.
	 *
	 * @param int    $target_user_id Target user ID.
	 * @param string $nonce REST nonce.
	 * @param int    $user_id Optional current user ID.
	 * @return array<string,mixed>
	 */
	private static function authorize( $target_user_id, $nonce, $user_id ) {
		$user_id = InteractionPermissions::authenticated_user_id( $user_id );
		if ( $user_id <= 0 ) {
			return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 );
		}
		if ( ! InteractionPermissions::nonce_valid( $nonce ) ) {
			return InteractionResult::error( 'invalid_nonce', 'The security token is missing or invalid.', array(), 403 );
		}
		if ( SafeMode::public_features_disabled() ) {
			return InteractionResult::error( 'interaction_unavailable', 'This action is temporarily unavailable.', array(), 503 );
		}

		$target_user_id = self::positive_id( $target_user_id );
		$target          = self::user( $target_user_id );
		if ( ! $target ) {
			return InteractionResult::error( 'user_unavailable', 'The requested user is unavailable.', array(), 404 );
		}
		if ( $target_user_id === $user_id ) {
			return InteractionResult::error( 'self_follow_forbidden', 'You cannot follow your own account.', array(), 400 );
		}

		$followable = true;
		if ( function_exists( 'apply_filters' ) ) {
			$followable = (bool) apply_filters( 'sabri_feed_user_followable', true, $target_user_id, $user_id );
		}
		if ( ! $followable ) {
			return InteractionResult::error( 'user_not_followable', 'This account cannot be followed.', array(), 403 );
		}

		return InteractionResult::success(
			'authorized',
			array(
				'user_id'        => $user_id,
				'target_user_id' => $target_user_id,
			),
			'Authorized.',
			200
		);
	}

	/**
	 * Update one natural-key relationship row.
	 *
	 * @param int    $follower_user_id Follower.
	 * @param int    $target_user_id Target.
	 * @param string $status Status.
	 * @return array<string,mixed>
	 */
	private static function set_status( $follower_user_id, $target_user_id, $status ) {
		return InteractionRepository::update_rows(
			'follows',
			array( 'status' => $status ),
			array(
				'follower_user_id' => $follower_user_id,
				'target_user_id'   => $target_user_id,
				'target_type'      => self::TARGET_TYPE,
			)
		);
	}

	/**
	 * Existing user object.
	 *
	 * @param int $user_id User ID.
	 * @return mixed
	 */
	private static function user( $user_id ) {
		$user_id = self::positive_id( $user_id );
		return $user_id > 0 && function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : false;
	}

	/**
	 * Strict positive ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private static function positive_id( $value ) {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/', $value ) ) ) {
			return 0;
		}
		$value = (int) $value;
		return $value > 0 ? $value : 0;
	}
}
