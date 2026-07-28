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
 * Enforces core-post visibility before pagination and again after resolution.
 */
final class PublicQueryGuard {
	const FILTER_MARKER = 'sabri_hnf_public_query_filtered';

	/** Register SQL-stage and object-stage safeguards. */
	public static function register() {
		if ( function_exists( 'remove_action' ) ) {
			remove_action( 'pre_get_posts', array( PostMetadata::class, 'filter_public_queries' ), 10 );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'pre_get_posts', array( __CLASS__, 'filter_public_queries' ), 20 );
		}
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'the_posts', array( __CLASS__, 'filter_public_post_results' ), 10, 2 );
		}
	}

	/**
	 * Add File 21 visibility/review clauses before WordPress calculates totals.
	 *
	 * This runs only for unambiguous public core-post collections and explicit
	 * core-post queries. Pages, attachments, searches, 404s and mixed post types
	 * are left untouched so routing remains stable.
	 *
	 * @param mixed $query WP_Query-like object.
	 * @return void
	 */
	public static function filter_public_queries( $query ) {
		if ( ! self::public_query_allowed() || ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
			return;
		}
		if ( $query->get( self::FILTER_MARKER ) || ! self::targets_core_posts( $query ) ) {
			return;
		}

		$existing = $query->get( 'meta_query' );
		$existing = is_array( $existing ) ? $existing : array();
		$existing = array(
			'relation' => 'AND',
			$existing,
			PostMetadata::visibility_meta_clause(),
			PostMetadata::review_state_meta_clause(),
		);
		$query->set( 'meta_query', $existing );
		$query->set( self::FILTER_MARKER, 1 );
	}

	/**
	 * Object-level defense for single posts, custom loops and third-party queries.
	 *
	 * @param mixed $posts Resolved posts.
	 * @param mixed $query WP_Query-like object.
	 * @return mixed
	 */
	public static function filter_public_post_results( $posts, $query ) {
		unset( $query );
		if ( ! self::public_query_allowed() || ! is_array( $posts ) || empty( $posts ) ) {
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

	/** Determine whether a query is unambiguously a core-post collection/query. */
	public static function targets_core_posts( $query ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) ) {
			return false;
		}
		if ( self::positive_id( $query->get( 'page_id' ) ) > 0 ) {
			return false;
		}
		foreach ( array( 'pagename', 'attachment', 'error' ) as $route_key ) {
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

	/** Public request safety boundary. */
	private static function public_query_allowed() {
		if ( function_exists( 'is_admin' ) && is_admin() ) { return false; }
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) { return false; }
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) { return false; }
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return false; }
		return true;
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
