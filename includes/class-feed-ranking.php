<?php
/**
 * Explainable Phase 2 feed ranking.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scores posts without AI claims or social-interaction signals.
 */
final class FeedRanking {
	/**
	 * Rank posts for a feed mode.
	 *
	 * @param array<int,mixed>         $posts Posts.
	 * @param string                   $mode Feed mode.
	 * @param array<string,mixed>|null $settings Settings.
	 * @return array<int,mixed>
	 */
	public static function rank_posts( array $posts, $mode, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$mode     = sanitize_key( $mode );
		$ranked   = array();

		foreach ( $posts as $post ) {
			$post_id = self::post_id( $post );
			$ranked[] = array(
				'post'      => $post,
				'score'     => self::score_post( $post_id, $mode, $settings ),
				'timestamp' => self::post_timestamp( $post ),
			);
		}

		usort(
			$ranked,
			static function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					return $b['timestamp'] <=> $a['timestamp'];
				}

				return $b['score'] <=> $a['score'];
			}
		);

		return array_values(
			array_map(
				static function ( $item ) {
					return $item['post'];
				},
				$ranked
			)
		);
	}

	/**
	 * Explain the ranking model.
	 *
	 * @return array<int,string>
	 */
	public static function explanation() {
		return array(
			'recency',
			'founder priority',
			'configured verified-author priority',
			'post-type and category relevance',
			'featured or pinned state',
			'moderation status',
			'content visibility',
			'balanced fallback when personalization data is unavailable',
		);
	}

	/**
	 * Score a post.
	 *
	 * @param int                      $post_id Post ID.
	 * @param string                   $mode Mode.
	 * @param array<string,mixed>|null $settings Settings.
	 * @return int
	 */
	public static function score_post( $post_id, $mode, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$post_id  = (int) $post_id;
		$type     = PostMetadata::feed_type( $post_id );
		$score    = self::recency_score( $post_id );

		if ( self::is_truthy_meta( $post_id, PostMetadata::META_PINNED ) ) {
			$score += 40;
		}

		if ( self::is_truthy_meta( $post_id, PostMetadata::META_FEATURED ) ) {
			$score += 18;
		}

		if ( 'founder-update' === $type ) {
			$score += isset( $settings['feed']['founder_priority'] ) ? (int) $settings['feed']['founder_priority'] : 20;
		}

		if ( self::author_is_verified( $post_id, $settings ) ) {
			$score += isset( $settings['feed']['verified_author_priority'] ) ? (int) $settings['feed']['verified_author_priority'] : 8;
		}

		$mode_map = FeedContext::mode_type_map();
		if ( isset( $mode_map[ $mode ] ) && $mode_map[ $mode ] === $type ) {
			$score += 16;
		}

		if ( 'approved' !== PostMetadata::review_state( $post_id ) ) {
			$score -= 100;
		}

		if ( 'public' !== PostMetadata::visibility( $post_id ) ) {
			$score -= 4;
		}

		return $score;
	}

	/**
	 * Recency score based on age in days.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	private static function recency_score( $post_id ) {
		$timestamp = function_exists( 'get_post_time' ) ? (int) get_post_time( 'U', true, $post_id ) : time();
		if ( $timestamp <= 0 ) {
			return 0;
		}

		$age_days = max( 0, floor( ( time() - $timestamp ) / DAY_IN_SECONDS ) );
		return max( 0, 30 - (int) $age_days );
	}

	/**
	 * Whether author belongs to a configured verified group.
	 *
	 * @param int                      $post_id Post ID.
	 * @param array<string,mixed>|null $settings Settings.
	 * @return bool
	 */
	private static function author_is_verified( $post_id, $settings = null ) {
		$author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0;
		return $author_id > 0 && ComposerPermissions::user_has_role_group( $author_id, 'verified_doctor_roles', $settings );
	}

	/**
	 * Truthy post meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @return bool
	 */
	private static function is_truthy_meta( $post_id, $meta_key ) {
		$value = function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, $meta_key, true ) : '';
		return ! empty( $value );
	}

	/**
	 * Post ID from object or integer.
	 *
	 * @param mixed $post Post.
	 * @return int
	 */
	private static function post_id( $post ) {
		if ( is_object( $post ) && isset( $post->ID ) ) {
			return (int) $post->ID;
		}

		return (int) $post;
	}

	/**
	 * Post timestamp helper.
	 *
	 * @param mixed $post Post.
	 * @return int
	 */
	private static function post_timestamp( $post ) {
		$post_id = self::post_id( $post );
		return function_exists( 'get_post_time' ) ? (int) get_post_time( 'U', true, $post_id ) : 0;
	}
}
