<?php
/**
 * Editorial News Home Feed integration.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Adds normalized News cards without changing ordinary post persistence. */
final class NewsFeedIntegration {
	const NEWS_PER_PAGE = 1;
	const INSERT_POSITION = 2;

	/** Register no direct hooks; FeedQuery and FeedRenderer call this adapter. */
	public static function register() {}

	/** Return pagination dimensions before the ordinary post query runs. */
	public static function pagination_context( $mode, $page, $per_page ) {
		$enabled = NewsPolicy::public_reads_allowed() && self::mode_supported( $mode );
		$per_page = min( 50, max( 1, (int) $per_page ) );
		$news_per_page = $enabled ? min( self::NEWS_PER_PAGE, max( 0, $per_page - 1 ) ) : 0;
		return array(
			'enabled'           => $enabled && $news_per_page > 0,
			'mode'              => self::clean_key( $mode ),
			'page'              => max( 1, (int) $page ),
			'per_page'          => $per_page,
			'news_per_page'     => $news_per_page,
			'ordinary_per_page' => max( 1, $per_page - $news_per_page ),
		);
	}

	/** Add bounded News items to one already-built ordinary feed result. */
	public static function integrate_result( array $result, array $context ) {
		if ( empty( $context['enabled'] ) || empty( $result['posts'] ) && $context['news_per_page'] < 1 ) {
			return $result;
		}

		$news = NewsQueryService::feed_candidates( $context['mode'], $context['page'], $context['news_per_page'] );
		$news_items = ! empty( $news['items'] ) && is_array( $news['items'] ) ? $news['items'] : array();

		$ordinary = isset( $result['posts'] ) && is_array( $result['posts'] ) ? $result['posts'] : array();
		$items = array();
		$seen = array();

		$inserted = false;
		foreach ( $ordinary as $index => $post ) {
			if ( ! $inserted && $index >= self::INSERT_POSITION && $news_items ) {
				foreach ( $news_items as $news_item ) {
					self::append_unique( $items, $seen, $news_item );
				}
				$inserted = true;
			}
			self::append_unique( $items, $seen, $post );
		}
		if ( ! $inserted && $news_items ) {
			foreach ( $news_items as $news_item ) {
				self::append_unique( $items, $seen, $news_item );
			}
		}
		$items = array_slice( $items, 0, $context['per_page'] );

		$ordinary_total = isset( $result['total'] ) ? (int) $result['total'] : count( $ordinary );
		$ordinary_pages = isset( $result['max_pages'] ) ? (int) $result['max_pages'] : (int) ceil( $ordinary_total / max( 1, $context['ordinary_per_page'] ) );
		$news_total = isset( $news['total'] ) ? (int) $news['total'] : count( $news_items );
		$news_pages = isset( $news['max_pages'] ) ? (int) $news['max_pages'] : (int) ceil( $news_total / max( 1, $context['news_per_page'] ) );

		$result['posts']             = $items;
		$result['ordinary_per_page'] = $context['ordinary_per_page'];
		$result['news_per_page']     = $context['news_per_page'];
		$result['news_total']        = $news_total;
		$result['total']             = $ordinary_total + $news_total;
		$result['max_pages']         = max( $ordinary_pages, $news_pages );
		$result['has_more']          = $context['page'] < $result['max_pages'];
		return $result;
	}

	/** Whether one existing feed mode is eligible for News distribution. */
	public static function mode_supported( $mode ) {
		return in_array(
			self::clean_key( $mode ),
			array(
				'for-you', 'latest', 'founder-updates', 'classical-homeopathy',
				'materia-medica', 'repertory', 'research', 'education',
				'public-health', 'platform-news',
			),
			true
		);
	}

	/** Append a post/news item once using type-safe global identity. */
	private static function append_unique( array &$items, array &$seen, $item ) {
		$key = self::global_key( $item );
		if ( '' === $key || isset( $seen[ $key ] ) ) {
			return;
		}
		$seen[ $key ] = true;
		$items[] = $item;
	}

	/** Stable identity keeps ordinary posts and Editorial News distinct. */
	public static function global_key( $item ) {
		if ( is_array( $item ) && 'editorial_news' === ( isset( $item['item_type'] ) ? $item['item_type'] : '' ) ) {
			$key = isset( $item['global_key'] ) ? (string) $item['global_key'] : '';
			return preg_match( '/^news:[1-9][0-9]*$/D', $key ) ? $key : '';
		}
		$id = is_object( $item ) && isset( $item->ID ) ? (int) $item->ID : ( is_numeric( $item ) ? (int) $item : 0 );
		return $id > 0 ? 'post:' . $id : '';
	}

	/** Clean mode key. */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) );
	}
}
