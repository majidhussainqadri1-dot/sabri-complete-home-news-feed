<?php
/**
 * Public route and result visibility guard.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preserves WordPress routing and filters core-post results after resolution.
 */
final class PublicQueryGuard {
	const FILTER_MARKER = 'sabri_hnf_public_query_filtered';

	/**
	 * Remove the unsafe main-query mutation and enforce visibility on results.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'remove_action' ) ) {
			remove_action( 'pre_get_posts', array( PostMetadata::class, 'filter_public_queries' ), 10 );
		}

		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'the_posts', array( __CLASS__, 'filter_public_post_results' ), 10, 2 );
		}
	}

	/**
	 * Filter core-post objects only after WordPress has resolved the request.
	 *
	 * Mutating unresolved pre_get_posts queries is unsafe because Pages, missing
	 * pretty permalinks, and single Posts can share the same early query shape.
	 * Filtering the resolved result set preserves HTTP routing while preventing
	 * unauthorized core posts from reaching themes, REST-derived loops, widgets,
	 * archives, or direct-post rendering.
	 *
	 * @param mixed $posts Resolved posts.
	 * @param mixed $query WP_Query-like object.
	 * @return mixed
	 */
	public static function filter_public_post_results( $posts, $query ) {
		unset( $query );

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return $posts;
		}
		if ( ! is_array( $posts ) || empty( $posts ) ) {
			return $posts;
		}

		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$visible = array();
		foreach ( $posts as $post ) {
			if ( ! is_object( $post ) || empty( $post->ID ) ) {
				$visible[] = $post;
				continue;
			}

			$post_type = isset( $post->post_type ) ? self::clean_key( $post->post_type ) : '';
			if ( 'post' !== $post_type || PostMetadata::user_can_view( (int) $post->ID, $user_id ) ) {
				$visible[] = $post;
			}
		}

		return array_values( $visible );
	}

	/**
	 * Compatibility method retained for direct callers; no longer registered.
	 *
	 * @param mixed $query WP_Query-like object.
	 * @return void
	 */
	public static function filter_public_queries( $query ) {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return;
		}
		if ( ! is_object( $query ) || ! method_exists( $query, 'is_main_query' ) || ! $query->is_main_query() || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
			return;
		}
		if ( $query->get( self::FILTER_MARKER ) || ! self::targets_core_posts( $query ) ) {
			return;
		}

		$meta_query   = $query->get( 'meta_query' );
		$meta_query   = is_array( $meta_query ) ? $meta_query : array();
		$meta_query[] = PostMetadata::visibility_meta_clause();
		$meta_query[] = PostMetadata::review_state_meta_clause();
		$query->set( 'meta_query', $meta_query );
		$query->set( self::FILTER_MARKER, 1 );
	}

	/** Determine whether a query is unambiguously a core-post list/query. */
	public static function targets_core_posts( $query ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) ) {
			return false;
		}

		if ( self::positive_id( $query->get( 'page_id' ) ) > 0 ) {
			return false;
		}
		foreach ( array( 'pagename', 'name', 'attachment', 'error' ) as $route_key ) {
			$value = $query->get( $route_key );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return false;
			}
		}
		foreach ( array( 'is_page', 'is_attachment', 'is_search', 'is_404' ) as $conditional ) {
			if ( method_exists( $query, $conditional ) && $query->{$conditional}() ) {
				return false;
			}
		}

		$post_type = $query->get( 'post_type' );
		if ( is_array( $post_type ) ) {
			$post_types = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_key' ), $post_type ) ) ) );
			return array( 'post' ) === $post_types;
		}
		if ( is_scalar( $post_type ) && '' !== trim( (string) $post_type ) ) {
			return 'post' === self::clean_key( $post_type );
		}
		if ( self::positive_id( $query->get( 'p' ) ) > 0 ) {
			return true;
		}
		foreach ( array( 'is_home', 'is_category', 'is_tag', 'is_date', 'is_author', 'is_feed' ) as $conditional ) {
			if ( method_exists( $query, $conditional ) && $query->{$conditional}() ) {
				return true;
			}
		}
		return false;
	}

	/** Clean a query key in WordPress and lean test environments. */
	public static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}

	/** Strict positive query ID. */
	private static function positive_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/', (string) $value ) ? (int) $value : 0;
	}
}
