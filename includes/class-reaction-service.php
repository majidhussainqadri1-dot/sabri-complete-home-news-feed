<?php
/**
 * Phase 3B reaction service.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements one active Like/Dislike reaction per user and post.
 */
final class ReactionService {
	/**
	 * Set or switch the current user's reaction.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $reaction_type Like or dislike.
	 * @param string $nonce REST nonce.
	 * @param int    $user_id Optional current session user ID.
	 * @return array<string,mixed>
	 */
	public static function set( $post_id, $reaction_type, $nonce = '', $user_id = 0 ) {
		if ( ! Phase3FeatureSettings::enabled( 'reactions_enabled' ) ) {
			return InteractionResult::error( 'reactions_disabled', 'Reactions are currently unavailable.', array(), 503 );
		}

		$reaction_type = sanitize_key( $reaction_type );
		if ( ! in_array( $reaction_type, Phase3Contracts::reaction_types(), true ) ) {
			return InteractionResult::error( 'invalid_reaction', 'The selected reaction is invalid.', array(), 400 );
		}
		if ( 'dislike' === $reaction_type && ! Phase3FeatureSettings::enabled( 'dislikes_enabled' ) ) {
			return InteractionResult::error( 'dislikes_disabled', 'Dislikes are currently unavailable.', array(), 503 );
		}

		$authorized = InteractionPermissions::authorize_post_write( $post_id, $nonce, $user_id );
		if ( empty( $authorized['ok'] ) ) {
			return $authorized;
		}

		$user_id = (int) $authorized['data']['user_id'];
		$post_id = (int) $authorized['data']['post_id'];
		$limit   = InteractionRateLimiter::attempt( 'reactions', $user_id, $post_id );
		if ( empty( $limit['ok'] ) ) {
			return $limit;
		}

		$current = InteractionQueryRepository::active_reaction( $user_id, $post_id );
		if ( is_array( $current ) && isset( $current['reaction_type'] ) && $reaction_type === sanitize_key( $current['reaction_type'] ) ) {
			return self::remove( $post_id, $nonce, $user_id, true );
		}

		if ( is_array( $current ) ) {
			$result = InteractionRepository::update_rows(
				'reactions',
				array(
					'reaction_type' => $reaction_type,
					'status'        => 'active',
				),
				array(
					'user_id' => $user_id,
					'post_id' => $post_id,
				)
			);
		} else {
			$result = InteractionRepository::insert_row(
				'reactions',
				array(
					'post_id'       => $post_id,
					'user_id'       => $user_id,
					'reaction_type' => $reaction_type,
					'status'        => 'active',
				)
			);
		}

		if ( empty( $result['ok'] ) ) {
			return $result;
		}

		EngagementService::invalidate( $post_id );
		AuditLog::record( 'reaction_set', array( 'post_id' => $post_id, 'reaction_type' => $reaction_type ) );

		return InteractionResult::success(
			'reaction_saved',
			EngagementService::summary( $post_id, $user_id ),
			'Reaction saved.',
			200
		);
	}

	/**
	 * Remove the current user's reaction.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $nonce REST nonce.
	 * @param int    $user_id Optional current session user ID.
	 * @param bool   $already_authorized Whether set() already authorized and rate-limited.
	 * @return array<string,mixed>
	 */
	public static function remove( $post_id, $nonce = '', $user_id = 0, $already_authorized = false ) {
		if ( ! Phase3FeatureSettings::enabled( 'reactions_enabled' ) ) {
			return InteractionResult::error( 'reactions_disabled', 'Reactions are currently unavailable.', array(), 503 );
		}

		if ( $already_authorized ) {
			$user_id = InteractionPermissions::authenticated_user_id( $user_id );
			$post_id = (int) $post_id;
			if ( $user_id <= 0 || ! InteractionPermissions::can_interact_with_post( $post_id, $user_id ) ) {
				return InteractionResult::error( 'post_unavailable', 'The requested post is unavailable.', array(), 404 );
			}
		} else {
			$authorized = InteractionPermissions::authorize_post_write( $post_id, $nonce, $user_id );
			if ( empty( $authorized['ok'] ) ) {
				return $authorized;
			}
			$user_id = (int) $authorized['data']['user_id'];
			$post_id = (int) $authorized['data']['post_id'];
			$limit   = InteractionRateLimiter::attempt( 'reactions', $user_id, $post_id );
			if ( empty( $limit['ok'] ) ) {
				return $limit;
			}
		}

		$current = InteractionQueryRepository::active_reaction( $user_id, $post_id );
		if ( is_array( $current ) && ! InteractionQueryRepository::delete_active_reaction( $user_id, $post_id ) ) {
			return InteractionResult::error( 'reaction_remove_failed', 'The reaction could not be removed.', array(), 500 );
		}

		EngagementService::invalidate( $post_id );
		AuditLog::record( 'reaction_removed', array( 'post_id' => $post_id ) );

		return InteractionResult::success(
			'reaction_removed',
			EngagementService::summary( $post_id, $user_id ),
			'Reaction removed.',
			200
		);
	}
}
