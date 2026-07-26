<?php
/**
 * Global search-provider contract for File 20, File 21 and companion modules.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers visibility-safe Home/News providers without duplicating companion data. */
final class SearchProviderRegistry {
	const MAX_QUERY_LENGTH = 120;
	const MAX_RESULTS_PER_PROVIDER = 20;

	/** Register provider and result filters consumed by the Unified Shell. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'sabri_search_providers', array( __CLASS__, 'register_providers' ) );
			add_filter( 'sabri_shell_search_providers', array( __CLASS__, 'register_providers' ) );
			add_filter( 'sabri_search_results', array( __CLASS__, 'append_results' ), 10, 3 );
			add_filter( 'sabri_shell_search_results', array( __CLASS__, 'append_results' ), 10, 3 );
		}
	}

	/** File 21 provider definitions. */
	public static function providers() {
		return array(
			'file21-posts' => array(
				'label' => __( 'Posts', 'sabri-complete-home-news-feed' ),
				'callback' => array( __CLASS__, 'search_posts' ),
				'visibility' => 'object-authorized',
				'max_results' => self::MAX_RESULTS_PER_PROVIDER,
			),
			'file21-news' => array(
				'label' => __( 'News', 'sabri-complete-home-news-feed' ),
				'callback' => array( __CLASS__, 'search_news' ),
				'visibility' => 'approved-public-projection',
				'max_results' => self::MAX_RESULTS_PER_PROVIDER,
			),
		);
	}

	/** Merge File 21 providers into a shared registry. */
	public static function register_providers( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		return array_merge( $providers, self::providers() );
	}

	/** Append normalized results when a shared Shell calls a result filter. */
	public static function append_results( $results, $query = '', $args = array() ) {
		$results = is_array( $results ) ? $results : array();
		$query = self::normalize_query( $query );
		$args = is_array( $args ) ? $args : array();
		if ( '' === $query ) {
			return $results;
		}
		$limit = isset( $args['per_provider'] ) ? max( 1, min( self::MAX_RESULTS_PER_PROVIDER, (int) $args['per_provider'] ) ) : 10;
		foreach ( self::providers() as $provider_id => $provider ) {
			$items = call_user_func( $provider['callback'], $query, $limit );
			$results[ $provider_id ] = array(
				'provider' => $provider_id,
				'label' => $provider['label'],
				'items' => $items,
			);
		}
		return $results;
	}

	/** Search authorized core posts. */
	public static function search_posts( $query, $limit = 10 ) {
		$query = self::normalize_query( $query );
		$limit = max( 1, min( self::MAX_RESULTS_PER_PROVIDER, (int) $limit ) );
		if ( '' === $query || ! class_exists( 'WP_Query' ) ) {
			return array();
		}
		$viewer = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$wp_query = new \WP_Query(
			array(
				'post_type' => 'post',
				'post_status' => 'publish',
				's' => $query,
				'posts_per_page' => min( 100, $limit * 5 ),
				'orderby' => 'relevance',
				'order' => 'DESC',
				'no_found_rows' => true,
				'ignore_sticky_posts' => true,
				'meta_query' => array(
					'relation' => 'AND',
					PostMetadata::visibility_meta_clause(),
					PostMetadata::review_state_meta_clause(),
				),
			)
		);
		$items = array();
		foreach ( (array) $wp_query->posts as $post ) {
			$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : 0;
			if ( $post_id <= 0 || ! PostMetadata::user_can_view( $post_id, $viewer ) ) {
				continue;
			}
			$items[] = self::post_projection( $post_id );
			if ( count( $items ) >= $limit ) {
				break;
			}
		}
		return array_values( array_filter( $items ) );
	}

	/** Search public Editorial News through its approved query service. */
	public static function search_news( $query, $limit = 10 ) {
		$query = self::normalize_query( $query );
		$limit = max( 1, min( self::MAX_RESULTS_PER_PROVIDER, (int) $limit ) );
		if ( '' === $query || ! class_exists( __NAMESPACE__ . '\\NewsQueryService' ) || ! class_exists( __NAMESPACE__ . '\\NewsPolicy' ) || ! NewsPolicy::public_reads_allowed() ) {
			return array();
		}
		$result = NewsQueryService::query( array( 'search' => $query, 'per_page' => $limit ) );
		$items = array();
		foreach ( ! empty( $result['data']['items'] ) && is_array( $result['data']['items'] ) ? $result['data']['items'] : array() as $article ) {
			$headline = isset( $article['headline'] ) ? (string) $article['headline'] : '';
			$url = isset( $article['canonical_url'] ) ? (string) $article['canonical_url'] : '';
			if ( '' === $headline || '' === $url ) {
				continue;
			}
			$items[] = array(
				'id' => isset( $article['interaction_id'] ) ? absint( $article['interaction_id'] ) : 0,
				'type' => 'news',
				'title' => sanitize_text_field( $headline ),
				'url' => esc_url_raw( $url ),
				'excerpt' => isset( $article['summary'] ) ? sanitize_text_field( $article['summary'] ) : '',
				'provider' => 'file21-news',
			);
		}
		return $items;
	}

	/** Public-safe core-post projection. */
	private static function post_projection( $post_id ) {
		$title = function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : '';
		$url = function_exists( 'get_permalink' ) ? (string) get_permalink( $post_id ) : '';
		if ( '' === $title || '' === $url ) {
			return array();
		}
		$excerpt = function_exists( 'get_the_excerpt' ) ? (string) get_the_excerpt( $post_id ) : '';
		return array(
			'id' => (int) $post_id,
			'type' => 'post',
			'title' => sanitize_text_field( $title ),
			'url' => esc_url_raw( $url ),
			'excerpt' => sanitize_text_field( $excerpt ),
			'provider' => 'file21-posts',
		);
	}

	/** Normalize a bounded plain-text search term. */
	private static function normalize_query( $query ) {
		$query = is_scalar( $query ) ? trim( (string) $query ) : '';
		$query = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $query ) : strip_tags( $query );
		return '' !== $query ? substr( $query, 0, self::MAX_QUERY_LENGTH ) : '';
	}
}