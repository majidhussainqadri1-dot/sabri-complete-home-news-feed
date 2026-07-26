<?php
/**
 * Bounded, explainable and anti-abuse viral ranking signals.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Supplies normalized engagement metrics without claiming AI personalization. */
final class ViralRankingSignals {
	/** Register extension point. */
	public static function register() {}

	/** Public-safe bounded metrics for one authorized post. */
	public static function metrics( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! PostMetadata::user_can_view( $post_id ) ) {
			return self::empty_metrics();
		}
		$summary = class_exists( __NAMESPACE__ . '\\EngagementService' ) ? EngagementService::summary( $post_id, 0 ) : array();
		$metrics = array(
			'views' => self::bounded( isset( $summary['view_count'] ) ? $summary['view_count'] : 0, 1000000 ),
			'reactions' => self::bounded( isset( $summary['reaction_count'] ) ? $summary['reaction_count'] : 0, 100000 ),
			'comments' => class_exists( __NAMESPACE__ . '\\CommentService' ) ? self::bounded( CommentService::approved_count( $post_id ), 100000 ) : 0,
			'saves' => self::save_count( $post_id ),
			'shares' => self::bounded_meta( $post_id, '_sabri_feed_share_count', 100000 ),
			'watch_seconds' => self::bounded_meta( $post_id, '_sabri_feed_watch_seconds', 100000000 ),
			'reports' => self::bounded_meta( $post_id, '_sabri_feed_confirmed_report_count', 100000 ),
			'quality' => self::bounded_meta( $post_id, '_sabri_feed_quality_score', 100 ),
		);
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'sabri_hnf_viral_metrics', $metrics, $post_id );
			if ( is_array( $filtered ) ) {
				foreach ( $metrics as $key => $value ) {
					$metrics[ $key ] = self::bounded( isset( $filtered[ $key ] ) ? $filtered[ $key ] : $value, 'watch_seconds' === $key ? 100000000 : ( 'quality' === $key ? 100 : 1000000 ) );
				}
			}
		}
		return $metrics;
	}

	/** Weighted score using logarithmic growth to reduce manipulation. */
	public static function score( $post_id ) {
		$metrics = self::metrics( $post_id );
		$score = 0.0;
		$score += log( 1 + $metrics['views'] ) * 2.0;
		$score += log( 1 + $metrics['reactions'] ) * 5.0;
		$score += log( 1 + $metrics['comments'] ) * 6.0;
		$score += log( 1 + $metrics['saves'] ) * 7.0;
		$score += log( 1 + $metrics['shares'] ) * 8.0;
		$score += log( 1 + floor( $metrics['watch_seconds'] / 30 ) ) * 3.0;
		$score += $metrics['quality'] * 0.25;
		$score -= log( 1 + $metrics['reports'] ) * 25.0;
		$score += self::freshness_score( $post_id );
		if ( function_exists( 'apply_filters' ) ) {
			$score = (float) apply_filters( 'sabri_hnf_viral_score', $score, $post_id, $metrics );
		}
		return (int) round( max( -1000, min( 10000, $score ) ) );
	}

	/** Save count from the accepted interaction table, if installed. */
	private static function save_count( $post_id ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) || ! class_exists( __NAMESPACE__ . '\\InteractionRepository' ) ) {
			return 0;
		}
		$table = InteractionRepository::table_name( 'saves' );
		if ( '' === $table ) {
			return 0;
		}
		$sql = $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE post_id = %d AND status = %s", $post_id, 'active' );
		return self::bounded( $wpdb->get_var( $sql ), 100000 );
	}

	/** Freshness decay over the first ninety days. */
	private static function freshness_score( $post_id ) {
		$timestamp = function_exists( 'get_post_time' ) ? (int) get_post_time( 'U', true, $post_id ) : 0;
		if ( $timestamp <= 0 ) {
			return 0;
		}
		$age_hours = max( 0, floor( ( time() - $timestamp ) / HOUR_IN_SECONDS ) );
		return max( 0, 90 - (int) floor( $age_hours / 24 ) );
	}

	/** Read one bounded numeric post meta. */
	private static function bounded_meta( $post_id, $key, $maximum ) {
		$value = function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, $key, true ) : 0;
		return self::bounded( $value, $maximum );
	}

	/** Normalize a non-negative integer. */
	private static function bounded( $value, $maximum ) {
		return max( 0, min( (int) $maximum, is_numeric( $value ) ? (int) $value : 0 ) );
	}

	/** Empty metric shape. */
	private static function empty_metrics() {
		return array( 'views' => 0, 'reactions' => 0, 'comments' => 0, 'saves' => 0, 'shares' => 0, 'watch_seconds' => 0, 'reports' => 0, 'quality' => 0 );
	}
}