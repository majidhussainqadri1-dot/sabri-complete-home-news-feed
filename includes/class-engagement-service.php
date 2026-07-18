<?php
/**
 * Phase 3B engagement summary service.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Combines public counts with private current-user state.
 */
final class EngagementService {
	const CACHE_PREFIX = 'sabri_hnf_engagement_';

	/**
	 * Return a visibility-safe engagement summary.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id Optional current user ID.
	 * @return array<string,mixed>
	 */
	public static function summary( $post_id, $user_id = 0 ) {
		$post_id = self::positive_id( $post_id );
		if ( $post_id <= 0 || ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
			return array(
				'post_id'          => 0,
				'like_count'       => 0,
				'dislike_count'    => 0,
				'reaction_count'   => 0,
				'current_reaction' => '',
				'saved'            => false,
			);
		}

		$counts = self::public_counts( $post_id );
		$user_id = $user_id ? self::positive_id( $user_id ) : ( function_exists( 'get_current_user_id' ) ? self::positive_id( get_current_user_id() ) : 0 );
		$current_reaction = '';
		$saved = false;

		if ( $user_id > 0 ) {
			$reaction = InteractionQueryRepository::active_reaction( $user_id, $post_id );
			if ( is_array( $reaction ) && isset( $reaction['reaction_type'] ) ) {
				$type = sanitize_key( $reaction['reaction_type'] );
				$current_reaction = in_array( $type, Phase3Contracts::reaction_types(), true ) ? $type : '';
			}

			$save = InteractionQueryRepository::save_record( $user_id, $post_id );
			$saved = is_array( $save ) && isset( $save['status'] ) && 'active' === sanitize_key( $save['status'] );
		}

		return array(
			'post_id'          => $post_id,
			'like_count'       => $counts['like'],
			'dislike_count'    => $counts['dislike'],
			'reaction_count'   => $counts['like'] + $counts['dislike'],
			'current_reaction' => $current_reaction,
			'saved'            => $saved,
		);
	}

	/**
	 * Invalidate public engagement count cache.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function invalidate( $post_id ) {
		$post_id = self::positive_id( $post_id );
		if ( $post_id > 0 && function_exists( 'delete_transient' ) ) {
			delete_transient( self::CACHE_PREFIX . $post_id );
		}
	}

	/**
	 * Return cached public counts.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,int>
	 */
	private static function public_counts( $post_id ) {
		$defaults = array( 'like' => 0, 'dislike' => 0 );
		$key = self::CACHE_PREFIX . $post_id;
		$cached = function_exists( 'get_transient' ) ? get_transient( $key ) : false;
		if ( is_array( $cached ) && isset( $cached['like'], $cached['dislike'] ) ) {
			return array(
				'like'    => max( 0, (int) $cached['like'] ),
				'dislike' => max( 0, (int) $cached['dislike'] ),
			);
		}

		$counts = array_merge( $defaults, InteractionQueryRepository::reaction_counts( $post_id ) );
		$counts = array(
			'like'    => max( 0, (int) $counts['like'] ),
			'dislike' => max( 0, (int) $counts['dislike'] ),
		);

		if ( function_exists( 'set_transient' ) ) {
			$base_settings = Settings::get();
			$ttl = isset( $base_settings['performance']['cache_seconds'] ) ? max( 0, min( 3600, (int) $base_settings['performance']['cache_seconds'] ) ) : 60;
			if ( $ttl > 0 ) {
				set_transient( $key, $counts, $ttl );
			}
		}

		return $counts;
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
