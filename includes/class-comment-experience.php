<?php
/**
 * Explainable threaded-comment presentation helpers.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Presentation-only enrichment; native comment truth remains CommentService/WordPress. */
final class CommentExperience {
	const DEFAULT_SORT = 'oldest';
	const QUOTE_LENGTH = 180;

	public static function register() {}

	/** Supported deterministic user-selected sort modes. */
	public static function sort_modes() {
		return array(
			'oldest' => __( 'Oldest first', 'sabri-complete-home-news-feed' ),
			'newest' => __( 'Newest first', 'sabri-complete-home-news-feed' ),
		);
	}

	/** Normalize a sort key; no opaque popularity sort is invented. */
	public static function normalize_sort( $sort ) {
		$sort = function_exists( 'sanitize_key' ) ? sanitize_key( $sort ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $sort ) );
		return array_key_exists( $sort, self::sort_modes() ) ? $sort : self::DEFAULT_SORT;
	}

	/** Enrich only from already-visible thread items, preventing existence leaks. */
	public static function enrich_items( array $items ) {
		$visible = array();
		foreach ( $items as $item ) {
			if ( ! empty( $item['id'] ) ) {
				$visible[ (int) $item['id'] ] = $item;
			}
		}
		$out = array();
		foreach ( $items as $item ) {
			$content = isset( $item['content'] ) ? (string) $item['content'] : '';
			$item['mentions'] = self::mention_tokens( $content );
			$item['quote_context'] = array();
			$parent_id = isset( $item['parent_id'] ) ? (int) $item['parent_id'] : 0;
			if ( $parent_id > 0 && isset( $visible[ $parent_id ] ) ) {
				$parent = $visible[ $parent_id ];
				$item['quote_context'] = array(
					'comment_id' => $parent_id,
					'author_name' => isset( $parent['author_name'] ) ? (string) $parent['author_name'] : '',
					'excerpt' => self::excerpt( isset( $parent['content'] ) ? (string) $parent['content'] : '' ),
				);
			}
			$out[] = $item;
		}
		return $out;
	}

	/** Sort the flat list so tree construction retains the requested top-level chronology. */
	public static function sort_items( array $items, $sort ) {
		$sort = self::normalize_sort( $sort );
		usort(
			$items,
			static function ( $left, $right ) use ( $sort ) {
				$l = isset( $left['date_gmt'] ) ? strtotime( (string) $left['date_gmt'] . ' GMT' ) : 0;
				$r = isset( $right['date_gmt'] ) ? strtotime( (string) $right['date_gmt'] . ' GMT' ) : 0;
				if ( $l === $r ) {
					$l_id = isset( $left['id'] ) ? (int) $left['id'] : 0;
					$r_id = isset( $right['id'] ) ? (int) $right['id'] : 0;
					return 'newest' === $sort ? $r_id <=> $l_id : $l_id <=> $r_id;
				}
				return 'newest' === $sort ? $r <=> $l : $l <=> $r;
			}
		);
		return $items;
	}

	/** Extract textual @mention tokens without resolving hidden account existence. */
	private static function mention_tokens( $content ) {
		$matches = array();
		preg_match_all( '/(?:^|\s)@([A-Za-z0-9._-]{2,60})/u', (string) $content, $matches );
		$tokens = isset( $matches[1] ) && is_array( $matches[1] ) ? $matches[1] : array();
		$tokens = array_map( 'strtolower', $tokens );
		return array_slice( array_values( array_unique( array_filter( $tokens ) ) ), 0, 20 );
	}

	/** Safe short context from a parent that is already in the visible thread. */
	private static function excerpt( $content ) {
		$content = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $content ) ) );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $content, 0, self::QUOTE_LENGTH );
		}
		return substr( $content, 0, self::QUOTE_LENGTH );
	}
}
