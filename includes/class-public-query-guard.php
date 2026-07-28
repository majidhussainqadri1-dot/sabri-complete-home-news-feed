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

/** Enforces visibility before list queries while retaining a singular fallback. */
final class PublicQueryGuard {
	const FILTER_MARKER = 'sabri_hnf_public_query_filtered';

	/** Register query-time enforcement and a narrow object-level safety net. */
	public static function register() {
		if ( function_exists( 'remove_action' ) ) {
			remove_action( 'pre_get_posts', array( PostMetadata::class, 'filter_public_queries' ), 10 );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'pre_get_posts', array( __CLASS__, 'filter_public_queries' ), 20 );
		}
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'the_posts', array( __CLASS__, 'filter_public_post_results' ), 20, 2 );
		}
	}

	/** Apply SQL eligibility clauses to unambiguous public main core-post lists. */
	public static function filter_public_queries( $query ) {
		if ( ! self::public_query_allowed() ) {
			return;
		}
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
			return;
		}
		if ( method_exists( $query, 'is_main_query' ) && ! $query->is_main_query() ) {
			return;
		}
		if ( $query->get( self::FILTER_MARKER ) || ! self::targets_core_posts( $query ) ) {
			return;
		}

		$existing = $query->get( 'meta_query' );
		$clauses = array( 'relation' => 'AND' );
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			$clauses[] = $existing;
		}
		$clauses[] = PostMetadata::visibility_meta_clause();
		$clauses[] = PostMetadata::review_state_meta_clause();
		$query->set( 'meta_query', $clauses );
		$query->set( self::FILTER_MARKER, 1 );
	}

	/**
	 * Object-level fallback for singular or ambiguous queries only.
	 * Queries marked above must not be shortened after pagination calculation.
	 */
	public static function filter_public_post_results( $posts, $query ) {
		if ( ! self::public_query_allowed() ) {
			return $posts;
		}
		if ( ! is_array( $posts ) || empty( $posts ) || ! is_object( $query ) ) {
			return $posts;
		}
		if ( method_exists( $query, 'get' ) && $query->get( self::FILTER_MARKER ) ) {
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

		$visible = array_values( $visible );
		if ( property_exists( $query, 'posts' ) ) {
			$query->posts = $visible;
		}
		if ( property_exists( $query, 'post_count' ) ) {
			$query->post_count = count( $visible );
		}
		return $visible;
	}

	/** Determine whether a query is unambiguously a core-post list. */
	public static function targets_core_posts( $query ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) ) {
			return false;
		}
		if ( self::positive_id( $query->get( 'page_id' ) ) > 0 || self::positive_id( $query->get( 'p' ) ) > 0 ) {
			return false;
		}
		foreach ( array( 'pagename', 'name', 'attachment', 'error' ) as $route_key ) {
			$value = $query->get( $route_key );
			if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
				return false;
			}
		}
		foreach ( array( 'is_page', 'is_attachment', 'is_search', 'is_404', 'is_single', 'is_singular' ) as $conditional ) {
			if ( method_exists( $query, $conditional ) && $query->{$conditional}() ) {
				return false;
			}
		}

		$post_type = $query->get( 'post_type' );
		if ( is_array( $post_type ) ) {
			$post_types = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_key' ), $post_type ) ) ) );
			sort( $post_types );
			return array( 'post' ) === $post_types;
		}
		if ( is_scalar( $post_type ) && '' !== trim( (string) $post_type ) ) {
			return 'post' === self::clean_key( $post_type );
		}
		foreach ( array( 'is_home', 'is_category', 'is_tag', 'is_date', 'is_author', 'is_feed' ) as $conditional ) {
			if ( method_exists( $query, $conditional ) && $query->{$conditional}() ) {
				return true;
			}
		}
		return false;
	}

	/** Public request safety boundary. */
	private static function public_query_allowed() {
		if ( function_exists( 'is_admin' ) && is_admin() ) { return false; }
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) { return false; }
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) { return false; }
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return false; }
		return true;
	}

	/** Pure controlled-key sanitizer; no filterable WordPress callbacks. */
	public static function clean_key( $value ) {
		$value = strtolower( (string) $value );
		$value = preg_replace( '/[^a-z0-9_\-]/', '', $value );
		return is_string( $value ) ? $value : '';
	}

	/** Strict positive query ID. */
	private static function positive_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/D', (string) $value ) ? (int) $value : 0;
	}
}
