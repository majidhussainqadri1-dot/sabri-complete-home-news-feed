<?php
/**
 * Safe Home Feed query layer.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queries existing WordPress posts without duplicating content.
 */
final class FeedQuery {
	const CACHE_GROUP = 'sabri_hnf_feed';
	const CACHE_VERSION_OPTION = 'sabri_feed_cache_version';

	/**
	 * Register invalidation hooks.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'save_post_post', array( __CLASS__, 'invalidate_cache' ) );
			add_action( 'deleted_post', array( __CLASS__, 'invalidate_cache' ) );
			add_action( 'transition_post_status', array( __CLASS__, 'invalidate_cache' ) );
			add_action( 'set_object_terms', array( __CLASS__, 'invalidate_cache' ) );
			add_action( 'updated_option', array( __CLASS__, 'invalidate_on_settings_change' ), 10, 3 );
		}
	}

	/**
	 * Run a feed query.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<string,mixed>
	 */
	public static function query( array $args = array() ) {
		$settings = Settings::get();
		$mode     = FeedContext::normalize_mode( isset( $args['mode'] ) ? $args['mode'] : self::request_value( 'sabri_feed_mode' ), $settings );
		$page     = FeedContext::page( isset( $args['page'] ) ? $args['page'] : self::request_value( 'sabri_feed_page' ) );
		$per_page = FeedContext::per_page( isset( $args['per_page'] ) ? $args['per_page'] : null, $settings );
		$user_id  = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

		if ( ! SafeMode::feature_enabled( 'feed' ) ) {
			return self::empty_result( $mode, $page, $per_page, 'disabled' );
		}

		$cache_key = self::cache_key( $mode, $page, $per_page, $user_id, $settings );
		$cached    = self::get_cache( $cache_key );
		if ( is_array( $cached ) ) {
			$cached['cache_hit'] = true;
			return $cached;
		}

		$query_args = self::wp_query_args( $mode, $page, $per_page, $user_id, $settings );
		$posts      = array();
		$total      = 0;
		$max_pages  = 0;

		if ( class_exists( 'WP_Query' ) ) {
			$wp_query  = new \WP_Query( $query_args );
			$posts     = is_array( $wp_query->posts ) ? $wp_query->posts : array();
			$total     = isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : count( $posts );
			$max_pages = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : (int) ceil( $total / $per_page );
		} elseif ( function_exists( 'apply_filters' ) ) {
			$posts = apply_filters( 'sabri_feed_test_posts', array(), $query_args );
			$posts = self::filter_posts_for_tests( is_array( $posts ) ? $posts : array(), $mode, $user_id, $settings );
			$total = count( $posts );
			$posts = array_slice( $posts, ( $page - 1 ) * $per_page, $per_page );
			$max_pages = (int) ceil( $total / $per_page );
		}

		$posts = self::dedupe_posts( $posts );
		if ( 'for-you' === $mode ) {
			$posts = FeedRanking::rank_posts( $posts, $mode, $settings );
		}

		$result = array(
			'status'      => 'ok',
			'mode'        => $mode,
			'page'        => $page,
			'per_page'    => $per_page,
			'posts'       => $posts,
			'total'       => $total,
			'max_pages'   => max( 0, $max_pages ),
			'has_more'    => $page < max( 0, $max_pages ),
			'cache_hit'   => false,
			'query_args'  => $query_args,
			'explanation' => FeedRanking::explanation(),
		);

		self::set_cache( $cache_key, $result, self::cache_seconds( $settings ) );

		return $result;
	}

	/**
	 * Build WP_Query args.
	 *
	 * @param string              $mode Mode.
	 * @param int                 $page Page.
	 * @param int                 $per_page Per page.
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $settings Settings.
	 * @return array<string,mixed>
	 */
	public static function wp_query_args( $mode, $page, $per_page, $user_id, array $settings ) {
		$visibility = FeedContext::visible_feed_scopes_for_user( $user_id, $settings );
		$meta_query = array(
			'relation' => 'AND',
			array(
				'relation' => 'OR',
				array(
					'key'     => PostMetadata::META_VISIBILITY,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => PostMetadata::META_VISIBILITY,
					'value'   => $visibility,
					'compare' => 'IN',
				),
			),
			array(
				'relation' => 'OR',
				array(
					'key'     => PostMetadata::META_REVIEW_STATE,
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => PostMetadata::META_REVIEW_STATE,
					'value'   => PostMetadata::excluded_review_states(),
					'compare' => 'NOT IN',
				),
			),
		);

		$args = array(
			'post_type'           => 'post',
			'post_status'         => array( 'publish' ),
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => false,
			'meta_query'          => $meta_query,
		);

		$mode_map = FeedContext::mode_type_map();
		if ( isset( $mode_map[ $mode ] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'sabri_feed_type',
					'field'    => 'slug',
					'terms'    => array( $mode_map[ $mode ] ),
				),
			);
		}

		return $args;
	}

	/**
	 * Cache invalidation hook.
	 *
	 * @return void
	 */
	public static function invalidate_cache() {
		$version = self::cache_version() + 1;
		if ( function_exists( 'update_option' ) ) {
			update_option( self::CACHE_VERSION_OPTION, $version, false );
		}
	}

	/**
	 * Invalidate when relevant settings change.
	 *
	 * @param string $option Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $value New value.
	 * @return void
	 */
	public static function invalidate_on_settings_change( $option, $old_value, $value ) {
		unset( $old_value, $value );

		if ( Settings::OPTION_NAME === $option ) {
			self::invalidate_cache();
		}
	}

	/**
	 * Build a cache key.
	 *
	 * @param string              $mode Mode.
	 * @param int                 $page Page.
	 * @param int                 $per_page Per page.
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	public static function cache_key( $mode, $page, $per_page, $user_id, array $settings ) {
		$scope = implode( ',', FeedContext::visible_feed_scopes_for_user( $user_id, $settings ) );
		return 'sabri_hnf_feed_' . md5( self::cache_version() . '|' . $mode . '|' . $page . '|' . $per_page . '|' . $user_id . '|' . $scope );
	}

	/**
	 * Current cache version.
	 *
	 * @return int
	 */
	private static function cache_version() {
		return function_exists( 'get_option' ) ? (int) get_option( self::CACHE_VERSION_OPTION, 1 ) : 1;
	}

	/**
	 * Cache duration.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return int
	 */
	private static function cache_seconds( array $settings ) {
		if ( isset( $settings['feed']['cache_duration'] ) ) {
			return max( 0, (int) $settings['feed']['cache_duration'] );
		}

		return isset( $settings['performance']['cache_seconds'] ) ? max( 0, (int) $settings['performance']['cache_seconds'] ) : 300;
	}

	/**
	 * Get cache.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	private static function get_cache( $key ) {
		if ( function_exists( 'get_transient' ) ) {
			return get_transient( $key );
		}

		return false;
	}

	/**
	 * Set cache.
	 *
	 * @param string              $key Key.
	 * @param array<string,mixed> $value Value.
	 * @param int                 $seconds Seconds.
	 * @return void
	 */
	private static function set_cache( $key, array $value, $seconds ) {
		if ( $seconds > 0 && function_exists( 'set_transient' ) ) {
			set_transient( $key, $value, $seconds );
		}
	}

	/**
	 * Empty result.
	 *
	 * @param string $mode Mode.
	 * @param int    $page Page.
	 * @param int    $per_page Per page.
	 * @param string $status Status.
	 * @return array<string,mixed>
	 */
	private static function empty_result( $mode, $page, $per_page, $status ) {
		return array(
			'status'      => $status,
			'mode'        => $mode,
			'page'        => $page,
			'per_page'    => $per_page,
			'posts'       => array(),
			'total'       => 0,
			'max_pages'   => 0,
			'has_more'    => false,
			'cache_hit'   => false,
			'query_args'  => array(),
			'explanation' => FeedRanking::explanation(),
		);
	}

	/**
	 * Deduplicate posts by ID.
	 *
	 * @param array<int,mixed> $posts Posts.
	 * @return array<int,mixed>
	 */
	private static function dedupe_posts( array $posts ) {
		$seen = array();
		$out  = array();

		foreach ( $posts as $post ) {
			$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
			if ( $post_id <= 0 || isset( $seen[ $post_id ] ) ) {
				continue;
			}
			$seen[ $post_id ] = true;
			$out[] = $post;
		}

		return $out;
	}

	/**
	 * Test fallback filtering when WP_Query is not loaded.
	 *
	 * @param array<int,mixed>    $posts Posts.
	 * @param string              $mode Mode.
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $settings Settings.
	 * @return array<int,mixed>
	 */
	private static function filter_posts_for_tests( array $posts, $mode, $user_id, array $settings ) {
		$mode_map = FeedContext::mode_type_map();
		return array_values(
			array_filter(
				$posts,
				static function ( $post ) use ( $mode, $mode_map, $user_id, $settings ) {
					unset( $settings );
					$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
					if ( ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
						return false;
					}
					return ! isset( $mode_map[ $mode ] ) || $mode_map[ $mode ] === PostMetadata::feed_type( $post_id );
				}
			)
		);
	}

	/**
	 * Read a request value.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function request_value( $key ) {
		if ( isset( $_GET[ $key ] ) ) {
			return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		}

		return '';
	}
}
