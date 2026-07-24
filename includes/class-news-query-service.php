<?php
/**
 * Public Editorial News query service.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Performs bounded public-only News queries and stable projections. */
final class NewsQueryService {
	const DEFAULT_PER_PAGE = 12;
	const MAX_PER_PAGE = 24;
	const MAX_PAGE = 1000;
	const MAX_KEYWORD_LENGTH = 100;
	const MAX_DATE_RANGE_DAYS = 3660;

	/** Register no direct hooks; NewsCache owns invalidation. */
	public static function register() {}

	/** Public collection query. */
	public static function query( array $args = array() ) {
		if ( ! NewsPolicy::public_reads_allowed() ) {
			return self::error( 'news_disabled', __( 'Editorial News is not available.', 'sabri-complete-home-news-feed' ), '', 404 );
		}

		$normalized = self::normalize_args( $args );
		if ( ! $normalized['success'] ) {
			return $normalized;
		}
		$args = $normalized['data'];

		$cache_dimensions = $args;
		$cached = NewsCache::get( 'collection', $cache_dimensions );
		if ( is_array( $cached ) ) {
			$cached['cache_hit'] = true;
			return $cached;
		}

		$query_args = self::wp_query_args( $args );
		$posts = array();
		$total = 0;
		$max_pages = 0;

		if ( class_exists( 'WP_Query' ) ) {
			$query = new \WP_Query( $query_args );
			$posts = is_array( $query->posts ) ? $query->posts : array();
			$total = isset( $query->found_posts ) ? (int) $query->found_posts : count( $posts );
			$max_pages = isset( $query->max_num_pages ) ? (int) $query->max_num_pages : (int) ceil( $total / $args['per_page'] );
		} else {
			$posts = function_exists( 'apply_filters' ) ? apply_filters( 'sabri_phase4c_test_posts', array(), $query_args, $args ) : array();
			$posts = self::filter_test_posts( is_array( $posts ) ? $posts : array(), $args );
			$total = count( $posts );
			$posts = array_slice( $posts, ( $args['page'] - 1 ) * $args['per_page'], $args['per_page'] );
			$max_pages = (int) ceil( $total / $args['per_page'] );
		}

		$items = array();
		$seen = array();
		foreach ( $posts as $post ) {
			$post = self::post( $post );
			if ( ! $post || isset( $seen[ $post->ID ] ) ) {
				continue;
			}
			$seen[ $post->ID ] = true;
			$item = $args['retracted'] ? NewsPublicProjector::retraction( $post ) : NewsPublicProjector::card( $post );
			if ( $item ) {
				$items[] = $item;
			}
		}

		$result = array(
			'success'     => true,
			'code'        => 'news_collection',
			'status'      => 200,
			'data'        => array(
				'items'      => $items,
				'page'       => $args['page'],
				'per_page'   => $args['per_page'],
				'total'      => max( 0, $total ),
				'max_pages'  => max( 0, $max_pages ),
				'has_more'   => $args['page'] < max( 0, $max_pages ),
				'filters'    => $args,
			),
			'cache_hit'   => false,
			'query_args'  => $query_args,
		);
		NewsCache::set( 'collection', $cache_dimensions, $result );
		return $result;
	}

	/** Resolve one public article by strict ID or slug. */
	public static function single( $identifier ) {
		if ( ! NewsPolicy::public_reads_allowed() ) {
			return self::error( 'news_not_found', __( 'The requested News article was not found.', 'sabri-complete-home-news-feed' ), '', 404 );
		}

		$lookup = self::normalize_identifier( $identifier );
		if ( ! $lookup['success'] ) {
			return self::error( 'news_not_found', __( 'The requested News article was not found.', 'sabri-complete-home-news-feed' ), '', 404 );
		}

		$cached = NewsCache::get( 'single', $lookup['data'] );
		if ( is_array( $cached ) ) {
			$cached['cache_hit'] = true;
			return $cached;
		}

		$post = null;
		if ( class_exists( 'WP_Query' ) ) {
			$query_args = array(
				'post_type'              => Phase4Contracts::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'suppress_filters'       => false,
			);
			if ( isset( $lookup['data']['id'] ) ) {
				$query_args['p'] = $lookup['data']['id'];
			} else {
				$query_args['name'] = $lookup['data']['slug'];
			}
			$query = new \WP_Query( $query_args );
			$post = ! empty( $query->posts[0] ) ? $query->posts[0] : null;
		} else {
			$posts = function_exists( 'apply_filters' ) ? apply_filters( 'sabri_phase4c_test_posts', array(), array(), array() ) : array();
			foreach ( is_array( $posts ) ? $posts : array() as $candidate ) {
				$candidate = self::post( $candidate );
				if ( ! $candidate ) {
					continue;
				}
				if ( isset( $lookup['data']['id'] ) && (int) $candidate->ID === (int) $lookup['data']['id'] ) {
					$post = $candidate;
					break;
				}
				if ( isset( $lookup['data']['slug'] ) && self::post_slug( $candidate ) === $lookup['data']['slug'] ) {
					$post = $candidate;
					break;
				}
			}
		}

		if ( ! $post || ! NewsPolicy::is_public_post( $post, 'single' ) ) {
			return self::error( 'news_not_found', __( 'The requested News article was not found.', 'sabri-complete-home-news-feed' ), '', 404 );
		}

		$projection = NewsPublicProjector::article( $post );
		if ( ! $projection ) {
			return self::error( 'news_not_found', __( 'The requested News article was not found.', 'sabri-complete-home-news-feed' ), '', 404 );
		}

		$result = array(
			'success'   => true,
			'code'      => 'news_article',
			'status'    => 200,
			'data'      => $projection,
			'cache_hit' => false,
		);
		NewsCache::set( 'single', $lookup['data'], $result );
		return $result;
	}

	/** Return related public cards from the same first section. */
	public static function related( $post_id, $limit = 4 ) {
		$post_id = self::positive_int( $post_id );
		$limit = min( 8, max( 1, self::positive_int( $limit ) ) );
		if ( $post_id < 1 ) {
			return array();
		}
		$sections = NewsPublicProjector::terms( $post_id, 'sabri_news_section' );
		$args = array(
			'per_page' => $limit + 1,
			'page'     => 1,
		);
		if ( $sections ) {
			$args['section'] = $sections[0]['slug'];
		}
		$result = self::query( $args );
		if ( empty( $result['success'] ) ) {
			return array();
		}
		$out = array();
		foreach ( $result['data']['items'] as $item ) {
			if ( (int) $item['object_id'] === $post_id ) {
				continue;
			}
			$out[] = $item;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/** Select one deterministic News candidate for a Home Feed page. */
	public static function feed_candidates( $mode, $page, $per_page = 1 ) {
		$mode = self::clean_key( $mode );
		$page = max( 1, self::positive_int( $page ) );
		$per_page = min( 3, max( 1, self::positive_int( $per_page ) ) );

		$args = array(
			'page'     => $page,
			'per_page' => $per_page,
		);
		$section_modes = array(
			'founder-updates'      => 'founder-updates',
			'classical-homeopathy' => 'classical-homeopathy',
			'materia-medica'       => 'materia-medica',
			'repertory'            => 'repertory',
			'public-health'        => 'public-health',
			'platform-news'        => 'platform-news',
		);
		if ( isset( $section_modes[ $mode ] ) ) {
			$args['section'] = $section_modes[ $mode ];
		} elseif ( 'research' === $mode ) {
			$args['research'] = 1;
		} elseif ( 'education' === $mode ) {
			$args['section_any'] = array( 'clinical-education', 'homeopathy-education' );
		} elseif ( 'clinical-cases' === $mode ) {
			return array( 'items' => array(), 'total' => 0, 'max_pages' => 0, 'has_more' => false );
		} elseif ( ! in_array( $mode, array( 'for-you', 'latest' ), true ) ) {
			return array( 'items' => array(), 'total' => 0, 'max_pages' => 0, 'has_more' => false );
		}

		$result = self::query( $args );
		return ! empty( $result['success'] ) ? $result['data'] : array( 'items' => array(), 'total' => 0, 'max_pages' => 0, 'has_more' => false );
	}

	/** Build public WP_Query arguments from normalized values only. */
	public static function wp_query_args( array $args ) {
		$states = $args['retracted'] ? array( 'retracted' ) : ( $args['corrected'] ? array( 'corrected' ) : NewsPolicy::public_archive_states() );
		$query = array(
			'post_type'           => Phase4Contracts::POST_TYPE,
			'post_status'         => $args['retracted'] ? array( 'private', 'publish' ) : array( 'publish' ),
			'posts_per_page'      => $args['per_page'],
			'paged'               => $args['page'],
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
			'orderby'             => array( 'date' => 'DESC', 'ID' => 'DESC' ),
			'meta_query'          => array(
				array(
					'key'     => Phase4Contracts::WORKFLOW_META_KEY,
					'value'   => $states,
					'compare' => 'IN',
				),
			),
		);

		if ( '' !== $args['keyword'] ) {
			$query['s'] = $args['keyword'];
		}
		if ( $args['author'] > 0 ) {
			$query['author'] = $args['author'];
		}

		$tax_query = array( 'relation' => 'AND' );
		$map = self::filter_taxonomy_map();
		foreach ( $map as $filter => $taxonomy ) {
			if ( '' !== $args[ $filter ] ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => array( $args[ $filter ] ),
				);
			}
		}
		if ( ! empty( $args['section_any'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'sabri_news_section',
				'field'    => 'slug',
				'terms'    => $args['section_any'],
			);
		}
		if ( $args['research'] ) {
			$tax_query[] = array(
				'taxonomy' => 'sabri_news_type',
				'field'    => 'slug',
				'terms'    => array( 'research-news' ),
			);
		}
		if ( count( $tax_query ) > 1 ) {
			$query['tax_query'] = $tax_query;
		}

		if ( '' !== $args['date_from'] || '' !== $args['date_to'] ) {
			$date = array( 'inclusive' => true );
			if ( '' !== $args['date_from'] ) {
				$date['after'] = $args['date_from'] . ' 00:00:00';
			}
			if ( '' !== $args['date_to'] ) {
				$date['before'] = $args['date_to'] . ' 23:59:59';
			}
			$query['date_query'] = array( $date );
		}

		return $query;
	}

	/** Normalize and validate every public filter. */
	public static function normalize_args( array $args ) {
		$out = array(
			'keyword'     => '',
			'section'     => '',
			'topic'       => '',
			'country'     => '',
			'region'      => '',
			'type'        => '',
			'date_from'   => '',
			'date_to'     => '',
			'author'      => 0,
			'research'    => 0,
			'corrected'   => 0,
			'retracted'   => 0,
			'section_any' => array(),
			'page'        => 1,
			'per_page'    => self::DEFAULT_PER_PAGE,
		);

		if ( isset( $args['keyword'] ) || isset( $args['q'] ) || isset( $args['search'] ) ) {
			$value = isset( $args['keyword'] ) ? $args['keyword'] : ( isset( $args['q'] ) ? $args['q'] : $args['search'] );
			if ( ! is_string( $value ) ) {
				return self::error( 'news_filter_invalid', __( 'The keyword filter is invalid.', 'sabri-complete-home-news-feed' ), 'keyword', 400 );
			}
			$value = trim( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : strip_tags( $value ) );
			if ( self::strlen( $value ) > self::MAX_KEYWORD_LENGTH ) {
				return self::error( 'news_filter_invalid', __( 'The keyword filter is too long.', 'sabri-complete-home-news-feed' ), 'keyword', 400 );
			}
			$out['keyword'] = $value;
		}

		foreach ( self::filter_taxonomy_map() as $filter => $taxonomy ) {
			unset( $taxonomy );
			if ( ! isset( $args[ $filter ] ) || '' === $args[ $filter ] ) {
				continue;
			}
			$slug = self::strict_slug( $args[ $filter ] );
			if ( '' === $slug || ! self::known_filter_slug( $filter, $slug ) ) {
				return self::error( 'news_filter_invalid', __( 'A News taxonomy filter is invalid.', 'sabri-complete-home-news-feed' ), $filter, 400 );
			}
			$out[ $filter ] = $slug;
		}

		if ( isset( $args['section_any'] ) ) {
			if ( ! is_array( $args['section_any'] ) || count( $args['section_any'] ) > 5 ) {
				return self::error( 'news_filter_invalid', __( 'The News section filter is invalid.', 'sabri-complete-home-news-feed' ), 'section_any', 400 );
			}
			foreach ( $args['section_any'] as $slug ) {
				$slug = self::strict_slug( $slug );
				if ( '' === $slug || ! isset( Phase4Contracts::sections()[ $slug ] ) ) {
					return self::error( 'news_filter_invalid', __( 'The News section filter is invalid.', 'sabri-complete-home-news-feed' ), 'section_any', 400 );
				}
				$out['section_any'][] = $slug;
			}
			$out['section_any'] = array_values( array_unique( $out['section_any'] ) );
		}

		foreach ( array( 'date_from', 'date_to' ) as $date_key ) {
			if ( isset( $args[ $date_key ] ) && '' !== $args[ $date_key ] ) {
				$date = self::strict_date( $args[ $date_key ] );
				if ( '' === $date ) {
					return self::error( 'news_filter_invalid', __( 'A publication date filter is invalid.', 'sabri-complete-home-news-feed' ), $date_key, 400 );
				}
				$out[ $date_key ] = $date;
			}
		}
		if ( '' !== $out['date_from'] && '' !== $out['date_to'] ) {
			$from = strtotime( $out['date_from'] . ' UTC' );
			$to = strtotime( $out['date_to'] . ' UTC' );
			if ( $from > $to || ( $to - $from ) > self::MAX_DATE_RANGE_DAYS * DAY_IN_SECONDS ) {
				return self::error( 'news_filter_invalid', __( 'The publication date range is invalid.', 'sabri-complete-home-news-feed' ), 'date_to', 400 );
			}
		}

		foreach ( array( 'page', 'per_page', 'author' ) as $int_key ) {
			if ( ! isset( $args[ $int_key ] ) || '' === $args[ $int_key ] ) {
				continue;
			}
			$value = self::positive_int( $args[ $int_key ] );
			if ( $value < 1 ) {
				return self::error( 'news_filter_invalid', __( 'A numeric News filter is invalid.', 'sabri-complete-home-news-feed' ), $int_key, 400 );
			}
			$out[ $int_key ] = $value;
		}
		if ( $out['page'] > self::MAX_PAGE || $out['per_page'] > self::MAX_PER_PAGE ) {
			return self::error( 'news_filter_invalid', __( 'News pagination is outside the allowed range.', 'sabri-complete-home-news-feed' ), $out['page'] > self::MAX_PAGE ? 'page' : 'per_page', 400 );
		}

		foreach ( array( 'research', 'corrected', 'retracted' ) as $bool_key ) {
			if ( isset( $args[ $bool_key ] ) && '' !== $args[ $bool_key ] ) {
				$bool = self::strict_bool( $args[ $bool_key ] );
				if ( null === $bool ) {
					return self::error( 'news_filter_invalid', __( 'A News status filter is invalid.', 'sabri-complete-home-news-feed' ), $bool_key, 400 );
				}
				$out[ $bool_key ] = $bool ? 1 : 0;
			}
		}
		if ( $out['corrected'] && $out['retracted'] ) {
			return self::error( 'news_filter_invalid', __( 'Corrected and retracted filters cannot be combined.', 'sabri-complete-home-news-feed' ), 'retracted', 400 );
		}
		if ( $out['corrected'] ) {
			$out['type'] = '';
		}

		return array( 'success' => true, 'code' => 'news_filters_valid', 'status' => 200, 'data' => $out );
	}

	/** Strict identifier normalization. */
	private static function normalize_identifier( $identifier ) {
		if ( is_int( $identifier ) || ( is_string( $identifier ) && preg_match( '/^[1-9][0-9]*$/D', $identifier ) ) ) {
			$id = self::positive_int( $identifier );
			return $id > 0 ? array( 'success' => true, 'data' => array( 'id' => $id ) ) : array( 'success' => false );
		}
		$slug = self::strict_slug( $identifier );
		return '' !== $slug ? array( 'success' => true, 'data' => array( 'slug' => $slug ) ) : array( 'success' => false );
	}

	/** Filter lean-test posts through the same public policy. */
	private static function filter_test_posts( array $posts, array $args ) {
		$out = array();
		foreach ( $posts as $post ) {
			$post = self::post( $post );
			if ( ! $post ) {
				continue;
			}
			$context = $args['retracted'] ? 'retraction' : 'archive';
			if ( ! NewsPolicy::is_public_post( $post, $context ) ) {
				continue;
			}
			if ( '' !== $args['keyword'] ) {
				$haystack = strtolower( (string) $post->post_title . ' ' . (string) $post->post_excerpt . ' ' . (string) $post->post_content );
				if ( false === strpos( $haystack, strtolower( $args['keyword'] ) ) ) {
					continue;
				}
			}
			$failed = false;
			foreach ( self::filter_taxonomy_map() as $filter => $taxonomy ) {
				if ( '' !== $args[ $filter ] && ! self::post_has_term( $post->ID, $taxonomy, $args[ $filter ] ) ) {
					$failed = true;
					break;
				}
			}
			if ( $failed ) {
				continue;
			}
			if ( $args['section_any'] ) {
				$matched = false;
				foreach ( $args['section_any'] as $section ) {
					if ( self::post_has_term( $post->ID, 'sabri_news_section', $section ) ) {
						$matched = true;
						break;
					}
				}
				if ( ! $matched ) {
					continue;
				}
			}
			if ( $args['research'] && ! self::post_has_term( $post->ID, 'sabri_news_type', 'research-news' ) ) {
				continue;
			}
			if ( $args['author'] && (int) $post->post_author !== $args['author'] ) {
				continue;
			}
			$post_date = substr( (string) $post->post_date, 0, 10 );
			if ( '' !== $args['date_from'] && $post_date < $args['date_from'] ) {
				continue;
			}
			if ( '' !== $args['date_to'] && $post_date > $args['date_to'] ) {
				continue;
			}
			if ( $args['corrected'] && 'corrected' !== NewsPolicy::workflow_state( $post->ID ) ) {
				continue;
			}
			$out[] = $post;
		}
		usort(
			$out,
			static function ( $a, $b ) {
				$a_date = isset( $a->post_date ) ? $a->post_date : '';
				$b_date = isset( $b->post_date ) ? $b->post_date : '';
				if ( $a_date === $b_date ) {
					return (int) $b->ID <=> (int) $a->ID;
				}
				return strcmp( $b_date, $a_date );
			}
		);
		return $out;
	}

	/** Exact public filter-to-taxonomy map. */
	private static function filter_taxonomy_map() {
		return array(
			'section' => 'sabri_news_section',
			'topic'   => 'sabri_news_topic',
			'country' => 'sabri_news_country',
			'region'  => 'sabri_news_region',
			'type'    => 'sabri_news_type',
		);
	}

	/** Require frozen slugs where available. */
	private static function known_filter_slug( $filter, $slug ) {
		if ( 'section' === $filter ) {
			return isset( Phase4Contracts::sections()[ $slug ] );
		}
		if ( 'type' === $filter ) {
			return isset( Phase4Contracts::article_types()[ $slug ] );
		}
		return true;
	}

	/** Check a term in WordPress or tests. */
	private static function post_has_term( $post_id, $taxonomy, $slug ) {
		if ( function_exists( 'has_term' ) ) {
			return has_term( $slug, $taxonomy, $post_id );
		}
		foreach ( NewsPublicProjector::terms( $post_id, $taxonomy ) as $term ) {
			if ( $term['slug'] === $slug ) {
				return true;
			}
		}
		return false;
	}

	/** Resolve post object. */
	private static function post( $post ) {
		if ( is_numeric( $post ) && function_exists( 'get_post' ) ) {
			$post = get_post( (int) $post );
		}
		return is_object( $post ) && ! empty( $post->ID ) ? $post : null;
	}

	/** Post slug fallback. */
	private static function post_slug( $post ) {
		if ( ! empty( $post->post_name ) ) {
			return self::strict_slug( $post->post_name );
		}
		$title = isset( $post->post_title ) ? strtolower( trim( $post->post_title ) ) : '';
		return self::strict_slug( preg_replace( '/[^a-z0-9]+/', '-', $title ) );
	}

	/** Strict positive integer; negatives never become positive. */
	private static function positive_int( $value ) {
		return ( is_int( $value ) || ( is_string( $value ) && preg_match( '/^[1-9][0-9]*$/D', $value ) ) ) ? (int) $value : 0;
	}

	/** Strict lowercase slug. */
	private static function strict_slug( $value ) {
		return is_string( $value ) && strlen( $value ) <= 120 && preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) ? $value : '';
	}

	/** Strict ISO calendar date. */
	private static function strict_date( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value ) ) {
			return '';
		}
		$parts = array_map( 'intval', explode( '-', $value ) );
		return checkdate( $parts[1], $parts[2], $parts[0] ) ? $value : '';
	}

	/** Strict boolean scalar. */
	private static function strict_bool( $value ) {
		if ( in_array( $value, array( true, 1, '1' ), true ) ) {
			return true;
		}
		if ( in_array( $value, array( false, 0, '0' ), true ) ) {
			return false;
		}
		return null;
	}

	/** Error result. */
	private static function error( $code, $message, $field, $status ) {
		return array(
			'success' => false,
			'code'    => $code,
			'message' => $message,
			'field'   => $field,
			'status'  => (int) $status,
			'data'    => array(),
		);
	}

	/** Clean key. */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) );
	}

	/** Multibyte-safe length. */
	private static function strlen( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value ) : strlen( (string) $value );
	}
}
