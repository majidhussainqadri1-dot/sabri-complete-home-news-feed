<?php
/**
 * Explainable Home Feed ranking.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Scores authorized posts with bounded, auditable signals and no AI claims. */
final class FeedRanking {
	/** Rank posts for a controlled Feed mode. */
	public static function rank_posts( array $posts, $mode, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$mode = sanitize_key( $mode );
		$ranked = array();
		foreach ( $posts as $post ) {
			$post_id = self::post_id( $post );
			$ranked[] = array(
				'post' => $post,
				'score' => self::score_post( $post_id, $mode, $settings ),
				'timestamp' => self::post_timestamp( $post ),
				'id' => $post_id,
			);
		}
		usort(
			$ranked,
			static function ( $a, $b ) {
				if ( $a['score'] === $b['score'] ) {
					if ( $a['timestamp'] === $b['timestamp'] ) {
						return $b['id'] <=> $a['id'];
					}
					return $b['timestamp'] <=> $a['timestamp'];
				}
				return $b['score'] <=> $a['score'];
			}
		);
		return array_values( array_map( static function ( $item ) { return $item['post']; }, $ranked ) );
	}

	/** Explain the accepted local ranking model and its canonical limits. */
	public static function explanation() {
		return array(
			'authorized visibility and approved moderation state before ranking',
			'recency with bounded decay',
			'domain relevance and verified professional authority where contextually relevant',
			'pinned, featured and editorial quality state',
			'views, reactions, approved comments, saves, shares and watch-time when available',
			'confirmed-report penalty and logarithmic anti-abuse scaling',
			'diversity and explicit user-choice controls',
			'no Founder favoritism, donation, payment, paid promotion or purchased-engagement boost',
			'File 26 remains the canonical owner of global Search, Discovery, Recommendations and Ranking governance',
		);
	}

	/** Score one post for a Feed mode. */
	public static function score_post( $post_id, $mode, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$post_id = (int) $post_id;
		$mode = sanitize_key( $mode );
		if ( $post_id <= 0 || ! PostMetadata::user_can_view( $post_id ) ) {
			return -10000;
		}
		$type = PostMetadata::feed_type( $post_id );
		$score = 'most-viral' === $mode && class_exists( __NAMESPACE__ . '\\ViralRankingSignals' )
			? ViralRankingSignals::score( $post_id )
			: self::recency_score( $post_id );

		/* Reduced personalization deliberately removes engagement-derived For You blending. */
		$reduced = class_exists( __NAMESPACE__ . '\\FeedUserAgency' ) && FeedUserAgency::reduced_personalization();
		if ( 'for-you' === $mode && ! $reduced && class_exists( __NAMESPACE__ . '\\ViralRankingSignals' ) ) {
			$score += (int) round( ViralRankingSignals::score( $post_id ) * 0.25 );
		}
		if ( self::is_truthy_meta( $post_id, PostMetadata::META_PINNED ) ) {
			$score += 40;
		}
		if ( self::is_truthy_meta( $post_id, PostMetadata::META_FEATURED ) ) {
			$score += 18;
		}
		$author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0;

		/*
		 * Founder identity grants publication authority, not organic ranking advantage.
		 * Verified-doctor authority is used only inside the explicit Doctors feed.
		 */
		if ( 'doctors-posts' === $mode && $author_id > 0 && CanonicalIdentityAdapter::is_verified_doctor( $author_id ) ) {
			$score += 24;
		}

		$mode_map = FeedContext::mode_type_map();
		$mode_types = isset( $mode_map[ $mode ] ) ? (array) $mode_map[ $mode ] : array();
		if ( in_array( $type, $mode_types, true ) ) {
			$score += 16;
		}
		if ( 'approved' !== PostMetadata::review_state( $post_id ) ) {
			$score -= 1000;
		}
		if ( 'public' !== PostMetadata::visibility( $post_id ) ) {
			$score -= 4;
		}
		$score = function_exists( 'apply_filters' ) ? apply_filters( 'sabri_hnf_feed_rank_score', $score, $post_id, $mode, $settings ) : $score;
		return (int) $score;
	}

	/** Recency score based on age in days. */
	private static function recency_score( $post_id ) {
		$timestamp = function_exists( 'get_post_time' ) ? (int) get_post_time( 'U', true, $post_id ) : time();
		if ( $timestamp <= 0 ) {
			return 0;
		}
		$day = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
		$age_days = max( 0, floor( ( time() - $timestamp ) / $day ) );
		return max( 0, 30 - (int) $age_days );
	}

	/** Truthy post meta. */
	private static function is_truthy_meta( $post_id, $meta_key ) {
		$value = function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, $meta_key, true ) : '';
		return ! empty( $value );
	}

	/** Post ID from object or integer. */
	private static function post_id( $post ) {
		return is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
	}

	/** Post timestamp helper. */
	private static function post_timestamp( $post ) {
		$post_id = self::post_id( $post );
		return function_exists( 'get_post_time' ) ? (int) get_post_time( 'U', true, $post_id ) : 0;
	}
}
