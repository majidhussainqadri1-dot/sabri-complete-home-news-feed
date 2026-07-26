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

/** Queries existing WordPress posts and bounded Editorial News cards. */
final class FeedQuery {
	const CACHE_GROUP = 'sabri_hnf_feed';
	const CACHE_VERSION_OPTION = 'sabri_feed_cache_version';
	const MAX_RANK_SCAN = 200;

	/** Register invalidation hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'save_post_post', array( __CLASS__, 'invalidate_cache' ) );
			add_action( 'deleted_post', array( __CLASS__, 'invalidate_cache' ) );
			add_action( 'transition_post_status', array( __CLASS__, 'invalidate_cache' ) );
			add_action( 'set_object_terms', array( __CLASS__, 'invalidate_cache' ) );
			add_action( 'updated_option', array( __CLASS__, 'invalidate_on_settings_change' ), 10, 3 );
		}
	}

	/** Run a Feed query. */
	public static function query( array $args = array() ) {
		$settings = Settings::get();
		$mode = FeedContext::normalize_mode( isset( $args['mode'] ) ? $args['mode'] : self::request_value( 'sabri_feed_mode' ), $settings );
		$page = FeedContext::page( isset( $args['page'] ) ? $args['page'] : self::request_value( 'sabri_feed_page' ) );
		$per_page = FeedContext::per_page( isset( $args['per_page'] ) ? $args['per_page'] : null, $settings );
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( ! SafeMode::feature_enabled( 'feed' ) ) {
			return self::empty_result( $mode, $page, $per_page, 'disabled' );
		}

		$integration = class_exists( __NAMESPACE__ . '\\NewsFeedIntegration' )
			? NewsFeedIntegration::pagination_context( $mode, $page, $per_page )
			: array( 'enabled' => false, 'mode' => $mode, 'page' => $page, 'per_page' => $per_page, 'news_per_page' => 0, 'ordinary_per_page' => $per_page );
		$ordinary_per_page = isset( $integration['ordinary_per_page'] ) ? max( 1, (int) $integration['ordinary_per_page'] ) : $per_page;
		$cache_key = self::cache_key( $mode, $page, $per_page, $user_id, $settings );
		$cached = self::get_cache( $cache_key );
		if ( is_array( $cached ) ) {
			$cached['cache_hit'] = true;
			return $cached;
		}

		$ranked_mode = in_array( $mode, FeedContext::ranked_modes(), true );
		$query_page = $ranked_mode ? 1 : $page;
		$query_count = $ranked_mode ? min( self::MAX_RANK_SCAN, max( 50, $ordinary_per_page * 10 ) ) : $ordinary_per_page;
		$query_args = self::wp_query_args( $mode, $query_page, $query_count, $user_id, $settings );
		if ( $ranked_mode ) {
			$query_args['no_found_rows'] = true;
		}
		$posts = array();
		$total = 0;
		$max_pages = 0;
		$total_is_complete = true;

		if ( class_exists( 'WP_Query' ) ) {
			$wp_query = new \WP_Query( $query_args );
			$posts = is_array( $wp_query->posts ) ? $wp_query->posts : array();
			if ( $ranked_mode ) {
				$posts = self::dedupe_posts( $posts );
				$posts = FeedRanking::rank_posts( $posts, $mode, $settings );
				$total = count( $posts );
				$total_is_complete = $total < self::MAX_RANK_SCAN;
				$posts = array_slice( $posts, ( $page - 1 ) * $ordinary_per_page, $ordinary_per_page );
				$max_pages = (int) ceil( $total / max( 1, $ordinary_per_page ) );
			} else {
				$total = isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : count( $posts );
				$max_pages = isset( $wp_query->max_num_pages ) ? (int) $wp_query->max_num_pages : (int) ceil( $total / max( 1, $ordinary_per_page ) );
			}
		} elseif ( function_exists( 'apply_filters' ) ) {
			$posts = apply_filters( 'sabri_feed_test_posts', array(), $query_args );
			$posts = self::filter_posts_for_tests( is_array( $posts ) ? $posts : array(), $mode, $user_id, $settings );
			$posts = self::dedupe_posts( $posts );
			if ( $ranked_mode ) {
				$posts = FeedRanking::rank_posts( array_slice( $posts, 0, self::MAX_RANK_SCAN ), $mode, $settings );
			}
			$total = count( $posts );
			$total_is_complete = $total < self::MAX_RANK_SCAN;
			$posts = array_slice( $posts, ( $page - 1 ) * $ordinary_per_page, $ordinary_per_page );
			$max_pages = (int) ceil( $total / max( 1, $ordinary_per_page ) );
		}

		$posts = self::dedupe_posts( $posts );
		$result = array(
			'status' => 'ok',
			'mode' => $mode,
			'page' => $page,
			'per_page' => $per_page,
			'posts' => $posts,
			'total' => $total,
			'total_is_complete' => $total_is_complete,
			'max_pages' => max( 0, $max_pages ),
			'has_more' => $page < max( 0, $max_pages ) || ( $ranked_mode && ! $total_is_complete ),
			'cache_hit' => false,
			'query_args' => $query_args,
			'explanation' => FeedRanking::explanation(),
		);
		if ( ! empty( $integration['enabled'] ) && class_exists( __NAMESPACE__ . '\\NewsFeedIntegration' ) ) {
			$result = NewsFeedIntegration::integrate_result( $result, $integration );
		}
		self::set_cache( $cache_key, $result, self::cache_seconds( $settings ) );
		return $result;
	}

	/** Build WP_Query arguments. */
	public static function wp_query_args( $mode, $page, $per_page, $user_id, array $settings ) {
		$visibility = FeedContext::visible_feed_scopes_for_user( $user_id, $settings );
		$meta_query = array(
			'relation' => 'AND',
			array(
				'relation' => 'OR',
				array( 'key' => PostMetadata::META_VISIBILITY, 'compare' => 'NOT EXISTS' ),
				array( 'key' => PostMetadata::META_VISIBILITY, 'value' => $visibility, 'compare' => 'IN' ),
			),
			PostMetadata::review_state_meta_clause(),
		);
		$args = array(
			'post_type' => 'post',
			'post_status' => array( 'publish' ),
			'posts_per_page' => max( 1, (int) $per_page ),
			'paged' => max( 1, (int) $page ),
			'ignore_sticky_posts' => true,
			'orderby' => 'date',
			'order' => 'DESC',
			'no_found_rows' => false,
			'meta_query' => $meta_query,
		);

		$mode_map = FeedContext::mode_type_map();
		$all_types = array_keys( Taxonomies::feed_type_terms() );
		$configured_types = isset( $settings['feed']['allowed_types'] ) && is_array( $settings['feed']['allowed_types'] ) ? array_map( 'sanitize_key', $settings['feed']['allowed_types'] ) : $all_types;
		$allowed_types = array_values( array_intersect( $all_types, $configured_types ) );
		if ( isset( $mode_map[ $mode ] ) ) {
			$requested = array_values( array_intersect( (array) $mode_map[ $mode ], $allowed_types ) );
			$args['tax_query'] = array( array( 'taxonomy' => 'sabri_feed_type', 'field' => 'slug', 'terms' => $requested ? $requested : array( '__sabri_disabled_feed_type__' ) ) );
		} elseif ( count( $allowed_types ) !== count( $all_types ) ) {
			$args['tax_query'] = array( array( 'taxonomy' => 'sabri_feed_type', 'field' => 'slug', 'terms' => $allowed_types ? $allowed_types : array( '__sabri_no_allowed_types__' ) ) );
		}
		if ( 'doctors-posts' === $mode ) {
			$doctor_ids = CanonicalIdentityAdapter::verified_doctor_ids();
			$args['author__in'] = $doctor_ids ? $doctor_ids : array( 0 );
		}
		return function_exists( 'apply_filters' ) ? apply_filters( 'sabri_hnf_feed_query_args', $args, $mode, $user_id, $settings ) : $args;
	}

	/** Cache invalidation hook. */
	public static function invalidate_cache() {
		$version = self::cache_version() + 1;
		if ( function_exists( 'update_option' ) {
			update_option( self::CACHE_VERSION_OPTION, $version, false );
		}
	}

	/** Invalidate when relevant settings change. */
	public static function invalidate_on_settings_change( $option, $old_value, $value ) {
		unset( $old_value, $value );
		if ( Settings::OPTION_NAME === $option || PostMetadata::LEGACY_BLANK_REVIEW_STATE_OPTION === $option || ( class_exists( __NAMESPACE__ . '\\NewsFeatureSettings' ) && NewsFeatureSettings::OPTION_NAME === $option ) ) {
			self::invalidate_cache();
		}
	}

	/** Build a cache key. */
	public static function cache_key( $mode, $page, $per_page, $user_id, array $settings ) {
		$scope = implode( ',', FeedContext::visible_feed_scopes_for_user( $user_id, $settings ) );
		$news_generation = class_exists( __NAMESPACE__ . '\\NewsCache' ) ? NewsCache::version() : 1;
		$news_gate = class_exists( __NAMESPACE__ . '\\NewsPolicy' ) && NewsPolicy::public_reads_allowed() ? 1 : 0;
		return 'sabri_hnf_feed_' . md5( self::cache_version() . '|' . $news_generation . '|' . $news_gate . '|' . $mode . '|' . $page . '|' . $per_page . '|' . $user_id . '|' . $scope );
	}

	/** Current cache version. */
	private static function cache_version() {
		return function_exists( 'get_option' ) ? max( 1, (int) get_option( self::CACHE_VERSION_OPTION, 1 ) ) : 1;
	}

	/** Cache duration. */
	private static function cache_seconds( array $settings ) {
		if ( isset( $settings['feed']['cache_duration'] ) ) {
			return max( 0, (int) $settings['feed']['cache_duration'] );
		}
		return isset( $settings['performance']['cache_seconds'] ) ? max( 0, (int) $settings['performance']['cache_seconds'] ) : 300;
	}

	/** Get cache. */
	private static function get_cache( $key ) {
		return function_exists( 'get_transient' ) ? get_transient( $key ) : false;
	}

	/** Set cache. */
	private static function set_cache( $key, array $value, $seconds ) {
		if ( $seconds > 0 && function_exists( 'set_transient' ) ) {
			set_transient( $key, $value, $seconds );
		}
	}

	/** Empty result. */
	private static function empty_result( $mode, $page, $per_page, $status ) {
		return array( 'status' => $status, 'mode' => $mode, 'page' => $page, 'per_page' => $per_page, 'posts' => array(), 'total' => 0, 'total_is_complete' => true, 'max_pages' => 0, 'has_more' => false, 'cache_hit' => false, 'query_args' => array(), 'explanation' => FeedRanking::explanation() );
	}

	/** Deduplicate ordinary posts by ID. */
	private static function dedupe_posts( array $posts ) {
		$seen = array();
		$out = array();
		foreach ( $posts as $post ) {
			$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : ( is_numeric( $post ) ? (int) $post : 0 );
			if ( $post_id <= 0 || isset( $seen[ $post_id ] ) ) {
				continue;
			}
			$seen[ $post_id ] = true;
			$out[] = $post;
		}
		return $out;
	}

	/** Test fallback filtering when WP_Query is not loaded. */
	private static function filter_posts_for_tests( array $posts, $mode, $user_id, array $settings ) {
		$mode_map = FeedContext::mode_type_map();
		$all_types = array_keys( Taxonomies::feed_type_terms() );
		$configured_types = isset( $settings['feed']['allowed_types'] ) && is_array( $settings['feed']['allowed_types'] ) ? array_map( 'sanitize_key', $settings['feed']['allowed_types'] ) : $all_types;
		$allowed_types = array_values( array_intersect( $all_types, $configured_types ) );
		return array_values(
			array_filter(
				$posts,
				static function ( $post ) use ( $mode, $mode_map, $user_id, $allowed_types ) {
					$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
					if ( ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
						return false;
					}
					if ( 'doctors-posts' === $mode ) {
						$author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0;
						if ( ! CanonicalIdentityAdapter::is_verified_doctor( $author_id ) ) {
							return false;
						}
					}
					$type = PostMetadata::feed_type( $post_id );
					$requested = isset( $mode_map[ $mode ] ) ? (array) $mode_map[ $mode ] : array();
					return in_array( $type, $allowed_types, true ) && ( empty( $requested ) || in_array( $type, $requested, true ) );
				}
			)
		);
	}

	/** Read a request value. */
	private static function request_value( $key ) {
		if ( isset( $_GET[ $key ] ) ) {
			return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
		}
		return '';
	}
}