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
	const MAX_TERM_OPTIONS = 100;

	/** Register no direct hooks; NewsCache owns invalidation. */
	public static function register() {}

	/** Public collection query. */
	public static function query( array $args = array() ) {
		if ( ! NewsPolicy::public_reads_allowed() ) {
			return self::error( 'editorial_news_disabled', __( 'Editorial News is not available.', 'sabri-complete-home-news-feed' ), '', 404 );
		}
		$normalized = self::normalize_args( $args );
		if ( empty( $normalized['success'] ) ) {
			return $normalized;
		}
		$args = $normalized['data'];
		$cached = NewsCache::get( 'collection', $args );
		if ( is_array( $cached ) ) {
			$cached['cache_hit'] = true;
			return $cached;
		}

		$query_args = self::wp_query_args( $args );
		$posts = array();
		$total = 0;
		$max_pages = 0;
		try {
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
		} catch ( \Throwable $error ) {
			return self::error( 'public_news_query_failed', __( 'The News request could not be completed.', 'sabri-complete-home-news-feed' ), '', 500 );
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
			'success'   => true,
			'code'      => 'public_news_collection',
			'status'    => 200,
			'data'      => array(
				'items'     => $items,
				'page'      => $args['page'],
				'per_page'  => $args['per_page'],
				'total'     => max( 0, $total ),
				'max_pages' => max( 0, $max_pages ),
				'has_more'  => $args['page'] < max( 0, $max_pages ),
				'filters'   => self::public_filter_projection( $args ),
			),
			'cache_hit' => false,
			'query_args'=> $query_args,
		);
		NewsCache::set( 'collection', $args, $result );
		return $result;
	}

	/** Assemble the bounded, deduplicated public News landing page. */
	public static function landing() {
		if ( ! NewsPolicy::public_reads_allowed() ) {
			return self::error( 'editorial_news_disabled', __( 'Editorial News is not available.', 'sabri-complete-home-news-feed' ), '', 404 );
		}
		$cached = NewsCache::get( 'landing', array() );
		if ( is_array( $cached ) ) {
			$cached['cache_hit'] = true;
			return $cached;
		}
		$seen = array();
		$components = array();
		self::append_component( $components, $seen, 'featured', __( 'Featured Story', 'sabri-complete-home-news-feed' ), array( 'featured' => 1, 'per_page' => 1 ), '' );
		self::append_component( $components, $seen, 'latest', __( 'Latest News', 'sabri-complete-home-news-feed' ), array( 'per_page' => 8 ), '/news/?view=latest' );
		self::append_component( $components, $seen, 'editors-picks', __( 'Editor’s Picks', 'sabri-complete-home-news-feed' ), array( 'editor_pick' => 1, 'per_page' => 4 ), '/news/?view=editors-picks' );
		self::append_component( $components, $seen, 'research', __( 'Research News', 'sabri-complete-home-news-feed' ), array( 'research' => 1, 'per_page' => 4 ), '/news/type/research-news/' );
		$sections = array(
			'classical-homeopathy'          => __( 'Classical Homeopathy', 'sabri-complete-home-news-feed' ),
			'public-health'                 => __( 'Public Health', 'sabri-complete-home-news-feed' ),
			'homeopathy-education'          => __( 'Homeopathy Education', 'sabri-complete-home-news-feed' ),
			'platform-news'                 => __( 'Platform News', 'sabri-complete-home-news-feed' ),
			'founder-updates'               => __( 'Founder Updates', 'sabri-complete-home-news-feed' ),
			'worldwide-health-developments' => __( 'Worldwide Health Developments', 'sabri-complete-home-news-feed' ),
		);
		foreach ( $sections as $slug => $label ) {
			self::append_component( $components, $seen, $slug, $label, array( 'section' => $slug, 'per_page' => 4 ), '/news/section/' . $slug . '/' );
		}
		self::append_component( $components, $seen, 'recently-updated', __( 'Recently Updated and Corrected', 'sabri-complete-home-news-feed' ), array( 'recently_updated' => 1, 'per_page' => 4 ), '/news/?view=recently-updated' );
		$result = array(
			'success' => true,
			'code'    => 'public_news_landing',
			'status'  => 200,
			'data'    => array( 'components' => $components, 'article_count' => count( $seen ) ),
			'cache_hit' => false,
		);
		NewsCache::set( 'landing', array(), $result );
		return $result;
	}

	/** Resolve one public article by strict ID or slug. */
	public static function single( $identifier ) {
		if ( ! NewsPolicy::public_reads_allowed() ) {
			return self::error( 'public_news_not_found', __( 'The requested News article is not available.', 'sabri-complete-home-news-feed' ), '', 404 );
		}
		$lookup = self::normalize_identifier( $identifier );
		if ( empty( $lookup['success'] ) ) {
			return self::error( 'public_news_not_found', __( 'The requested News article is not available.', 'sabri-complete-home-news-feed' ), '', 404 );
		}
		$cached = NewsCache::get( 'single', $lookup['data'] );
		if ( is_array( $cached ) ) {
			$cached['cache_hit'] = true;
			return $cached;
		}
		$post = null;
		try {
			if ( class_exists( 'WP_Query' ) ) {
				$query_args = array(
					'post_type' => Phase4Contracts::POST_TYPE,
					'post_status' => 'any',
					'posts_per_page' => 1,
					'no_found_rows' => true,
					'ignore_sticky_posts' => true,
					'suppress_filters' => false,
				);
				if ( isset( $lookup['data']['id'] ) ) { $query_args['p'] = $lookup['data']['id']; } else { $query_args['name'] = $lookup['data']['slug']; }
				$query = new \WP_Query( $query_args );
				$post = ! empty( $query->posts[0] ) ? $query->posts[0] : null;
			} else {
				$posts = function_exists( 'apply_filters' ) ? apply_filters( 'sabri_phase4c_test_posts', array(), array(), array() ) : array();
				foreach ( is_array( $posts ) ? $posts : array() as $candidate ) {
					$candidate = self::post( $candidate );
					if ( ! $candidate ) { continue; }
					if ( isset( $lookup['data']['id'] ) && (int) $candidate->ID === (int) $lookup['data']['id'] ) { $post = $candidate; break; }
					if ( isset( $lookup['data']['slug'] ) && self::post_slug( $candidate ) === $lookup['data']['slug'] ) { $post = $candidate; break; }
				}
			}
		} catch ( \Throwable $error ) {
			return self::error( 'public_news_query_failed', __( 'The News request could not be completed.', 'sabri-complete-home-news-feed' ), '', 500 );
		}
		if ( ! $post || ! NewsPolicy::is_public_post( $post, 'single' ) ) {
			return self::error( 'public_news_not_found', __( 'The requested News article is not available.', 'sabri-complete-home-news-feed' ), '', 404 );
		}
		$projection = NewsPublicProjector::article( $post );
		if ( ! $projection ) {
			return self::error( 'public_news_not_found', __( 'The requested News article is not available.', 'sabri-complete-home-news-feed' ), '', 404 );
		}
		$code = 'retraction' === ( isset( $projection['projection'] ) ? $projection['projection'] : '' ) ? 'public_news_retracted' : 'public_news_found';
		$result = array( 'success' => true, 'code' => $code, 'status' => 200, 'data' => $projection, 'cache_hit' => false );
		NewsCache::set( 'single', $lookup['data'], $result );
		return $result;
	}

	/** Return related public cards from the same first section. */
	public static function related( $post_id, $limit = 4 ) {
		$post_id = self::positive_int( $post_id );
		$limit = min( 8, max( 1, self::positive_int( $limit ) ) );
		if ( $post_id < 1 ) { return array(); }
		$sections = NewsPublicProjector::terms( $post_id, 'sabri_news_section' );
		$args = array( 'per_page' => $limit + 1, 'page' => 1 );
		if ( $sections ) { $args['section'] = $sections[0]['slug']; }
		$result = self::query( $args );
		if ( empty( $result['success'] ) ) { return array(); }
		$out = array();
		foreach ( $result['data']['items'] as $item ) {
			if ( (int) $item['object_id'] === $post_id ) { continue; }
			$out[] = $item;
			if ( count( $out ) >= $limit ) { break; }
		}
		return $out;
	}

	/** Select deterministic News candidates for a Home Feed page. */
	public static function feed_candidates( $mode, $page, $per_page = 1 ) {
		$mode = self::clean_key( $mode );
		$page = max( 1, self::positive_int( $page ) );
		$per_page = min( 3, max( 1, self::positive_int( $per_page ) ) );
		$args = array( 'page' => $page, 'per_page' => $per_page );
		$section_modes = array(
			'founder-updates' => 'founder-updates', 'classical-homeopathy' => 'classical-homeopathy',
			'materia-medica' => 'materia-medica', 'repertory' => 'repertory', 'public-health' => 'public-health', 'platform-news' => 'platform-news',
		);
		if ( isset( $section_modes[ $mode ] ) ) { $args['section'] = $section_modes[ $mode ]; }
		elseif ( 'research' === $mode ) { $args['research'] = 1; }
		elseif ( 'education' === $mode ) { $args['section_any'] = array( 'clinical-education', 'homeopathy-education' ); }
		elseif ( 'clinical-cases' === $mode ) { return self::empty_collection(); }
		elseif ( ! in_array( $mode, array( 'for-you', 'latest' ), true ) ) { return self::empty_collection(); }
		$result = self::query( $args );
		return ! empty( $result['success'] ) ? $result['data'] : self::empty_collection();
	}

	/** Return controlled public taxonomy options. */
	public static function public_terms( $taxonomy, $limit = self::MAX_TERM_OPTIONS ) {
		if ( ! in_array( $taxonomy, Phase4Contracts::taxonomies(), true ) ) { return array(); }
		if ( 'sabri_news_section' === $taxonomy ) {
			$out = array(); foreach ( Phase4Contracts::sections() as $slug => $name ) { $out[] = array( 'slug' => $slug, 'name' => $name ); } return $out;
		}
		if ( 'sabri_news_type' === $taxonomy ) {
			$out = array(); foreach ( Phase4Contracts::article_types() as $slug => $name ) { $out[] = array( 'slug' => $slug, 'name' => $name ); } return $out;
		}
		if ( ! function_exists( 'get_terms' ) ) { return array(); }
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => min( self::MAX_TERM_OPTIONS, max( 1, (int) $limit ) ), 'orderby' => 'name', 'order' => 'ASC' ) );
		if ( ! is_array( $terms ) || ( function_exists( 'is_wp_error' ) && is_wp_error( $terms ) ) ) { return array(); }
		$out = array();
		foreach ( $terms as $term ) {
			$slug = isset( $term->slug ) ? self::strict_slug( $term->slug ) : '';
			$name = isset( $term->name ) ? self::clean_text( $term->name, 120 ) : '';
			if ( '' !== $slug && '' !== $name ) { $out[] = array( 'slug' => $slug, 'name' => $name ); }
		}
		return $out;
	}

	/** Build public WP_Query arguments from normalized values only. */
	public static function wp_query_args( array $args ) {
		$states = $args['retracted'] ? array( 'retracted' ) : ( $args['corrected'] ? array( 'corrected' ) : NewsPolicy::public_archive_states() );
		$query = array(
			'post_type' => Phase4Contracts::POST_TYPE,
			'post_status' => $args['retracted'] ? array( 'private', 'publish' ) : array( 'publish' ),
			'posts_per_page' => $args['per_page'], 'paged' => $args['page'], 'ignore_sticky_posts' => true, 'no_found_rows' => false,
			'orderby' => $args['recently_updated'] ? array( 'modified' => 'DESC', 'ID' => 'DESC' ) : array( 'date' => 'DESC', 'ID' => 'DESC' ),
			'meta_query' => array( 'relation' => 'AND', array( 'key' => Phase4Contracts::WORKFLOW_META_KEY, 'value' => $states, 'compare' => 'IN' ) ),
		);
		if ( '' !== $args['keyword'] ) { $query['s'] = $args['keyword']; }
		if ( $args['author'] > 0 ) {
			$query['author'] = $args['author'];
			$query['meta_query'][] = array( 'key' => '_sabri_news_public_author_approved', 'value' => '1', 'compare' => '=' );
		}
		if ( '' !== $args['institution'] ) {
			$query['meta_query'][] = array( 'key' => '_sabri_news_public_institution_slug', 'value' => $args['institution'], 'compare' => '=' );
		}
		if ( $args['editor_pick'] ) { $query['meta_query'][] = array( 'key' => '_sabri_news_editor_pick', 'value' => '1', 'compare' => '=' ); }
		if ( $args['featured'] ) {
			$query['meta_query'][] = array( 'key' => '_sabri_news_priority', 'value' => 80, 'compare' => '>=', 'type' => 'NUMERIC' );
			$query['meta_key'] = '_sabri_news_priority';
			$query['orderby'] = array( 'meta_value_num' => 'DESC', 'date' => 'DESC', 'ID' => 'DESC' );
		}
		$tax_query = array( 'relation' => 'AND' );
		foreach ( self::filter_taxonomy_map() as $filter => $taxonomy ) {
			if ( '' !== $args[ $filter ] ) { $tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => array( $args[ $filter ] ) ); }
		}
		if ( ! empty( $args['section_any'] ) ) { $tax_query[] = array( 'taxonomy' => 'sabri_news_section', 'field' => 'slug', 'terms' => $args['section_any'] ); }
		if ( $args['research'] ) { $tax_query[] = array( 'taxonomy' => 'sabri_news_type', 'field' => 'slug', 'terms' => array( 'research-news' ) ); }
		if ( count( $tax_query ) > 1 ) { $query['tax_query'] = $tax_query; }
		if ( '' !== $args['date_from'] || '' !== $args['date_to'] ) {
			$date = array( 'inclusive' => true );
			if ( '' !== $args['date_from'] ) { $date['after'] = $args['date_from'] . ' 00:00:00'; }
			if ( '' !== $args['date_to'] ) { $date['before'] = $args['date_to'] . ' 23:59:59'; }
			$query['date_query'] = array( $date );
		}
		return $query;
	}

	/** Normalize and validate every public filter. */
	public static function normalize_args( array $args ) {
		$allowed = array( 'keyword','q','search','section','topic','country','region','type','date_from','date_to','author','institution','research','corrected','retracted','section_any','page','per_page','editor_pick','featured','recently_updated','view' );
		foreach ( array_keys( $args ) as $key ) {
			if ( ! in_array( $key, $allowed, true ) ) { return self::error( 'public_news_filter_invalid', __( 'An unsupported News filter was supplied.', 'sabri-complete-home-news-feed' ), (string) $key, 400 ); }
		}
		$out = array(
			'keyword'=>'','section'=>'','topic'=>'','country'=>'','region'=>'','type'=>'','date_from'=>'','date_to'=>'','author'=>0,'institution'=>'',
			'research'=>0,'corrected'=>0,'retracted'=>0,'section_any'=>array(),'page'=>1,'per_page'=>self::DEFAULT_PER_PAGE,
			'editor_pick'=>0,'featured'=>0,'recently_updated'=>0,
		);
		if ( isset( $args['keyword'] ) || isset( $args['q'] ) || isset( $args['search'] ) ) {
			$value = isset( $args['keyword'] ) ? $args['keyword'] : ( isset( $args['q'] ) ? $args['q'] : $args['search'] );
			if ( ! is_string( $value ) ) { return self::error( 'public_news_filter_invalid', __( 'The keyword filter is invalid.', 'sabri-complete-home-news-feed' ), 'keyword', 400 ); }
			$value = trim( self::clean_text( $value, self::MAX_KEYWORD_LENGTH + 1 ) );
			if ( self::strlen( $value ) > self::MAX_KEYWORD_LENGTH ) { return self::error( 'public_news_filter_invalid', __( 'The keyword filter is too long.', 'sabri-complete-home-news-feed' ), 'keyword', 400 ); }
			$out['keyword'] = $value;
		}
		foreach ( self::filter_taxonomy_map() as $filter => $taxonomy ) {
			if ( ! isset( $args[ $filter ] ) || '' === $args[ $filter ] ) { continue; }
			$slug = self::strict_slug( $args[ $filter ] );
			if ( '' === $slug || ! self::known_filter_slug( $taxonomy, $slug ) ) { return self::error( 'public_news_taxonomy_invalid', __( 'A News taxonomy filter is invalid.', 'sabri-complete-home-news-feed' ), $filter, 400 ); }
			$out[ $filter ] = $slug;
		}
		if ( isset( $args['institution'] ) && '' !== $args['institution'] ) {
			$out['institution'] = self::strict_slug( $args['institution'] );
			if ( '' === $out['institution'] ) { return self::error( 'public_news_filter_invalid', __( 'The institution filter is invalid.', 'sabri-complete-home-news-feed' ), 'institution', 400 ); }
		}
		if ( isset( $args['section_any'] ) ) {
			if ( ! is_array( $args['section_any'] ) || count( $args['section_any'] ) > 5 ) { return self::error( 'public_news_taxonomy_invalid', __( 'The News section filter is invalid.', 'sabri-complete-home-news-feed' ), 'section_any', 400 ); }
			foreach ( $args['section_any'] as $slug ) {
				$slug = self::strict_slug( $slug );
				if ( '' === $slug || ! isset( Phase4Contracts::sections()[ $slug ] ) ) { return self::error( 'public_news_taxonomy_invalid', __( 'The News section filter is invalid.', 'sabri-complete-home-news-feed' ), 'section_any', 400 ); }
				$out['section_any'][] = $slug;
			}
			$out['section_any'] = array_values( array_unique( $out['section_any'] ) );
		}
		foreach ( array( 'date_from', 'date_to' ) as $date_key ) {
			if ( isset( $args[ $date_key ] ) && '' !== $args[ $date_key ] ) {
				$date = self::strict_date( $args[ $date_key ] );
				if ( '' === $date ) { return self::error( 'public_news_filter_invalid', __( 'A publication date filter is invalid.', 'sabri-complete-home-news-feed' ), $date_key, 400 ); }
				$out[ $date_key ] = $date;
			}
		}
		if ( '' !== $out['date_from'] && '' !== $out['date_to'] ) {
			$from = strtotime( $out['date_from'] . ' UTC' ); $to = strtotime( $out['date_to'] . ' UTC' );
			if ( $from > $to || ( $to - $from ) > self::MAX_DATE_RANGE_DAYS * ( defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400 ) ) { return self::error( 'public_news_filter_invalid', __( 'The publication date range is invalid.', 'sabri-complete-home-news-feed' ), 'date_to', 400 ); }
		}
		foreach ( array( 'page', 'per_page', 'author' ) as $int_key ) {
			if ( ! isset( $args[ $int_key ] ) || '' === $args[ $int_key ] ) { continue; }
			$value = self::positive_int( $args[ $int_key ] );
			if ( $value < 1 ) { return self::error( 'public_news_page_invalid', __( 'News pagination is invalid.', 'sabri-complete-home-news-feed' ), $int_key, 400 ); }
			$out[ $int_key ] = $value;
		}
		if ( $out['page'] > self::MAX_PAGE || $out['per_page'] > self::MAX_PER_PAGE ) { return self::error( 'public_news_page_invalid', __( 'News pagination is outside the allowed range.', 'sabri-complete-home-news-feed' ), $out['page'] > self::MAX_PAGE ? 'page' : 'per_page', 400 ); }
		foreach ( array( 'research','corrected','retracted','editor_pick','featured','recently_updated' ) as $bool_key ) {
			if ( isset( $args[ $bool_key ] ) && '' !== $args[ $bool_key ] ) {
				$bool = self::strict_bool( $args[ $bool_key ] );
				if ( null === $bool ) { return self::error( 'public_news_filter_invalid', __( 'A News status filter is invalid.', 'sabri-complete-home-news-feed' ), $bool_key, 400 ); }
				$out[ $bool_key ] = $bool ? 1 : 0;
			}
		}
		if ( $out['corrected'] && $out['retracted'] ) { return self::error( 'public_news_filter_invalid', __( 'Corrected and retracted filters cannot be combined.', 'sabri-complete-home-news-feed' ), 'retracted', 400 ); }
		return array( 'success' => true, 'code' => 'public_news_filters_valid', 'status' => 200, 'data' => $out );
	}

	/** Add a landing component while deduplicating across the entire page. */
	private static function append_component( array &$components, array &$seen, $key, $title, array $args, $view_all ) {
		$result = self::query( $args );
		$items = array();
		if ( ! empty( $result['success'] ) ) {
			foreach ( $result['data']['items'] as $item ) {
				$id = isset( $item['object_id'] ) ? (int) $item['object_id'] : 0;
				if ( $id < 1 || isset( $seen[ $id ] ) ) { continue; }
				$seen[ $id ] = true; $items[] = $item;
			}
		}
		$components[] = array( 'key' => $key, 'title' => $title, 'items' => $items, 'view_all_url' => self::local_url( $view_all ) );
	}

	private static function normalize_identifier( $identifier ) {
		if ( is_int( $identifier ) || ( is_string( $identifier ) && preg_match( '/^[1-9][0-9]*$/D', $identifier ) ) ) {
			$id = self::positive_int( $identifier ); return $id > 0 ? array( 'success' => true, 'data' => array( 'id' => $id ) ) : array( 'success' => false );
		}
		$slug = self::strict_slug( $identifier ); return '' !== $slug ? array( 'success' => true, 'data' => array( 'slug' => $slug ) ) : array( 'success' => false );
	}

	private static function filter_test_posts( array $posts, array $args ) {
		$out = array();
		foreach ( $posts as $post ) {
			$post = self::post( $post ); if ( ! $post ) { continue; }
			$context = $args['retracted'] ? 'retraction' : 'archive';
			if ( ! NewsPolicy::is_public_post( $post, $context ) ) { continue; }
			if ( '' !== $args['keyword'] ) {
				$haystack = strtolower( (string) $post->post_title . ' ' . (string) $post->post_excerpt . ' ' . (string) $post->post_content );
				if ( false === strpos( $haystack, strtolower( $args['keyword'] ) ) ) { continue; }
			}
			$failed = false;
			foreach ( self::filter_taxonomy_map() as $filter => $taxonomy ) { if ( '' !== $args[ $filter ] && ! self::post_has_term( $post->ID, $taxonomy, $args[ $filter ] ) ) { $failed = true; break; } }
			if ( $failed ) { continue; }
			if ( $args['section_any'] ) {
				$matched = false; foreach ( $args['section_any'] as $section ) { if ( self::post_has_term( $post->ID, 'sabri_news_section', $section ) ) { $matched = true; break; } }
				if ( ! $matched ) { continue; }
			}
			if ( $args['research'] && ! self::post_has_term( $post->ID, 'sabri_news_type', 'research-news' ) ) { continue; }
			if ( $args['author'] && (int) $post->post_author !== $args['author'] ) { continue; }
			if ( $args['author'] && ! NewsPublicProjector::author_is_approved( $post->ID ) ) { continue; }
			if ( '' !== $args['institution'] && self::strict_slug( get_post_meta( $post->ID, '_sabri_news_public_institution_slug', true ) ) !== $args['institution'] ) { continue; }
			if ( $args['editor_pick'] && ! self::truthy_meta( $post->ID, '_sabri_news_editor_pick' ) ) { continue; }
			if ( $args['featured'] && (int) get_post_meta( $post->ID, '_sabri_news_priority', true ) < 80 ) { continue; }
			$post_date = substr( (string) $post->post_date, 0, 10 );
			if ( '' !== $args['date_from'] && $post_date < $args['date_from'] ) { continue; }
			if ( '' !== $args['date_to'] && $post_date > $args['date_to'] ) { continue; }
			if ( $args['corrected'] && 'corrected' !== NewsPolicy::workflow_state( $post->ID ) ) { continue; }
			$out[] = $post;
		}
		usort( $out, static function ( $a, $b ) use ( $args ) {
			$a_date = $args['recently_updated'] ? ( isset( $a->post_modified ) ? $a->post_modified : '' ) : ( isset( $a->post_date ) ? $a->post_date : '' );
			$b_date = $args['recently_updated'] ? ( isset( $b->post_modified ) ? $b->post_modified : '' ) : ( isset( $b->post_date ) ? $b->post_date : '' );
			if ( $a_date === $b_date ) { return (int) $b->ID <=> (int) $a->ID; }
			return strcmp( $b_date, $a_date );
		} );
		return $out;
	}

	private static function filter_taxonomy_map() { return array( 'section'=>'sabri_news_section','topic'=>'sabri_news_topic','country'=>'sabri_news_country','region'=>'sabri_news_region','type'=>'sabri_news_type' ); }
	private static function known_filter_slug( $taxonomy, $slug ) {
		if ( 'sabri_news_section' === $taxonomy ) { return isset( Phase4Contracts::sections()[ $slug ] ); }
		if ( 'sabri_news_type' === $taxonomy ) { return isset( Phase4Contracts::article_types()[ $slug ] ); }
		if ( function_exists( 'term_exists' ) ) { $exists = term_exists( $slug, $taxonomy ); return ! empty( $exists ) && ! ( function_exists( 'is_wp_error' ) && is_wp_error( $exists ) ); }
		if ( function_exists( 'get_term_by' ) ) { $term = get_term_by( 'slug', $slug, $taxonomy ); return $term && ! ( function_exists( 'is_wp_error' ) && is_wp_error( $term ) ); }
		return function_exists( 'apply_filters' ) ? (bool) apply_filters( 'sabri_phase4c_test_term_exists', false, $taxonomy, $slug ) : false;
	}
	private static function post_has_term( $post_id, $taxonomy, $slug ) {
		if ( function_exists( 'has_term' ) ) { return has_term( $slug, $taxonomy, $post_id ); }
		foreach ( NewsPublicProjector::terms( $post_id, $taxonomy ) as $term ) { if ( $term['slug'] === $slug ) { return true; } }
		return false;
	}
	private static function post( $post ) { if ( is_numeric( $post ) && function_exists( 'get_post' ) ) { $post = get_post( (int) $post ); } return is_object( $post ) && ! empty( $post->ID ) ? $post : null; }
	private static function post_slug( $post ) { if ( ! empty( $post->post_name ) ) { return self::strict_slug( $post->post_name ); } $title = isset( $post->post_title ) ? strtolower( trim( $post->post_title ) ) : ''; return self::strict_slug( preg_replace( '/[^a-z0-9]+/', '-', $title ) ); }
	private static function positive_int( $value ) { return ( is_int( $value ) || ( is_string( $value ) && preg_match( '/^[1-9][0-9]*$/D', $value ) ) ) ? (int) $value : 0; }
	private static function strict_slug( $value ) { return is_string( $value ) && strlen( $value ) <= 120 && preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) ? $value : ''; }
	private static function strict_date( $value ) { if ( ! is_string( $value ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m ) ) { return ''; } return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $value : ''; }
	private static function strict_bool( $value ) { if ( in_array( $value, array( true, 1, '1' ), true ) ) { return true; } if ( in_array( $value, array( false, 0, '0' ), true ) ) { return false; } return null; }
	private static function clean_key( $value ) { return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) ); }
	private static function clean_text( $value, $limit ) { $value = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( (string) $value ) ); return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $limit ) : substr( $value, 0, $limit ); }
	private static function strlen( $value ) { return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value ) : strlen( (string) $value ); }
	private static function error( $code, $message, $field, $status ) { return array( 'success'=>false,'code'=>(string)$code,'message'=>(string)$message,'field'=>(string)$field,'status'=>(int)$status,'data'=>array() ); }
	private static function public_filter_projection( array $args ) { return array_intersect_key( $args, array_flip( array( 'keyword','section','topic','country','region','type','date_from','date_to','author','institution','research','corrected','retracted','page','per_page' ) ) ); }
	private static function empty_collection() { return array( 'items'=>array(),'total'=>0,'max_pages'=>0,'has_more'=>false ); }
	private static function local_url( $path ) { if ( '' === $path ) { return ''; } return function_exists( 'home_url' ) ? home_url( $path ) : $path; }
	private static function truthy_meta( $post_id, $key ) { $value = function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, $key, true ) : 0; return in_array( $value, array( true, 1, '1' ), true ); }
}
