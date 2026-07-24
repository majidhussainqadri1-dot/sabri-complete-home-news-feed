<?php
/**
 * Canonical public Editorial News routing.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers fail-closed public routes and resolves one public projection. */
final class NewsRouting {
	const Q_ARCHIVE = 'sabri_news_public_archive';
	const Q_SLUG = 'sabri_news_public_slug';
	const Q_TAXONOMY = 'sabri_news_public_taxonomy';
	const Q_TERM = 'sabri_news_public_term';

	/** Register WordPress route hooks. */
	public static function register() {
		if ( ! function_exists( 'add_action' ) || ! function_exists( 'add_filter' ) ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'rewrite_rules' ), 12 );
		add_action( 'template_redirect', array( __CLASS__, 'prepare_request' ), 0 );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 99 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'redirect_canonical' ), 10, 2 );
		add_filter( 'document_title_parts', array( __CLASS__, 'document_title_parts' ) );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
	}

	/** Add exact route rules only while the public gate is enabled. */
	public static function rewrite_rules() {
		if ( ! NewsPolicy::public_reads_allowed() || ! function_exists( 'add_rewrite_rule' ) ) {
			return;
		}
		add_rewrite_rule( '^news/?$', 'index.php?' . self::Q_ARCHIVE . '=1', 'top' );
		$routes = array(
			'section' => 'sabri_news_section',
			'topic'   => 'sabri_news_topic',
			'country' => 'sabri_news_country',
			'region'  => 'sabri_news_region',
			'type'    => 'sabri_news_type',
		);
		foreach ( $routes as $route => $taxonomy ) {
			add_rewrite_rule(
				'^news/' . $route . '/([a-z0-9]+(?:-[a-z0-9]+)*)/?$',
				'index.php?' . self::Q_ARCHIVE . '=1&' . self::Q_TAXONOMY . '=' . $taxonomy . '&' . self::Q_TERM . '=$matches[1]',
				'top'
			);
		}
		add_rewrite_rule( '^news/([a-z0-9]+(?:-[a-z0-9]+)*)/?$', 'index.php?' . self::Q_SLUG . '=$matches[1]', 'top' );
	}

	/** Register route query variables. */
	public static function query_vars( $vars ) {
		$vars = is_array( $vars ) ? $vars : array();
		foreach ( array( self::Q_ARCHIVE, self::Q_SLUG, self::Q_TAXONOMY, self::Q_TERM ) as $var ) {
			$vars[] = $var;
		}
		return array_values( array_unique( $vars ) );
	}

	/** Resolve the request before headers/templates are sent. */
	public static function prepare_request() {
		if ( ! self::is_news_request() ) {
			return;
		}
		if ( ! NewsPolicy::public_reads_allowed() ) {
			self::mark_404();
			return;
		}

		$slug = self::query_var( self::Q_SLUG );
		$native_single_id = self::native_single_id();
		if ( '' !== $slug || $native_single_id > 0 ) {
			$result = NewsQueryService::single( $native_single_id > 0 ? $native_single_id : $slug );
			if ( empty( $result['success'] ) ) {
				self::mark_404();
				return;
			}
			NewsPublicRuntime::set_context(
				array(
					'route'          => 'single',
					'article'        => $result['data'],
					'canonical_base' => $result['data']['canonical_url'],
					'title'          => $result['data']['headline'],
				)
			);
			return;
		}

		$taxonomy = self::query_var( self::Q_TAXONOMY );
		$term = self::query_var( self::Q_TERM );
		if ( '' === $taxonomy && '' === $term ) {
			$native_term = self::native_taxonomy_context();
			$taxonomy = $native_term['taxonomy'];
			$term = $native_term['term'];
		}
		$args = self::request_filters();
		$title = __( 'News', 'sabri-complete-home-news-feed' );
		$canonical = function_exists( 'home_url' ) ? home_url( '/news/' ) : '/news/';
		if ( '' !== $taxonomy || '' !== $term ) {
			$filter = self::taxonomy_filter( $taxonomy );
			if ( '' === $filter || '' === self::strict_slug( $term ) || ! self::term_exists( $taxonomy, $term ) ) {
				self::mark_404();
				return;
			}
			$args[ $filter ] = $term;
			$title = self::term_title( $taxonomy, $term );
			$canonical = function_exists( 'home_url' ) ? home_url( '/news/' . self::taxonomy_route( $taxonomy ) . '/' . rawurlencode( $term ) . '/' ) : '/news/';
		}
		$result = NewsQueryService::query( $args );
		if ( empty( $result['success'] ) ) {
			if ( 404 === ( isset( $result['status'] ) ? (int) $result['status'] : 0 ) ) {
				self::mark_404();
				return;
			}
			$result = array(
				'success' => true,
				'data'    => array(
					'items' => array(),
					'page' => 1,
					'per_page' => NewsQueryService::DEFAULT_PER_PAGE,
					'total' => 0,
					'max_pages' => 0,
					'has_more' => false,
					'filters' => array(),
				),
			);
		}
		NewsPublicRuntime::set_context(
			array(
				'route'          => '' !== $taxonomy ? 'taxonomy' : 'archive',
				'result'         => $result,
				'title'          => $title,
				'description'    => '',
				'canonical_base' => $canonical,
			)
		);
	}

	/** Replace the theme template only for a successfully resolved News request. */
	public static function template_include( $template ) {
		$context = NewsPublicRuntime::context();
		if ( empty( $context['route'] ) ) {
			return $template;
		}
		$file = 'single' === $context['route']
			? SABRI_HNF_PATH . 'templates/news-single-page.php'
			: SABRI_HNF_PATH . 'templates/news-archive-page.php';
		return is_readable( $file ) ? $file : $template;
	}

	/** Force canonical local News URLs. */
	public static function redirect_canonical( $redirect_url, $requested_url ) {
		unset( $requested_url );
		$context = NewsPublicRuntime::context();
		if ( 'single' !== ( isset( $context['route'] ) ? $context['route'] : '' ) || empty( $context['canonical_base'] ) ) {
			return $redirect_url;
		}
		return $context['canonical_base'];
	}

	/** Public document title. */
	public static function document_title_parts( $parts ) {
		$parts = is_array( $parts ) ? $parts : array();
		$context = NewsPublicRuntime::context();
		if ( ! empty( $context['title'] ) ) {
			$parts['title'] = $context['title'];
		}
		return $parts;
	}

	/** Retracted and unresolved News surfaces are noindex. */
	public static function robots( $robots ) {
		$robots = is_array( $robots ) ? $robots : array();
		$context = NewsPublicRuntime::context();
		if ( ! empty( $context['article']['projection'] ) && 'retraction' === $context['article']['projection'] ) {
			$robots['noindex'] = true;
			$robots['nofollow'] = false;
		}
		return $robots;
	}

	/** Is this request owned by the explicit News router? */
	private static function is_news_request() {
		if ( '1' === (string) self::query_var( self::Q_ARCHIVE ) || '' !== self::query_var( self::Q_SLUG ) ) {
			return true;
		}
		if ( function_exists( 'is_singular' ) && is_singular( Phase4Contracts::POST_TYPE ) ) {
			return true;
		}
		if ( function_exists( 'is_post_type_archive' ) && is_post_type_archive( Phase4Contracts::POST_TYPE ) ) {
			return true;
		}
		if ( function_exists( 'is_tax' ) ) {
			foreach ( Phase4Contracts::taxonomies() as $taxonomy ) {
				if ( is_tax( $taxonomy ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** Resolve a native CPT single query without trusting request identifiers. */
	private static function native_single_id() {
		if ( ! function_exists( 'is_singular' ) || ! is_singular( Phase4Contracts::POST_TYPE ) ) {
			return 0;
		}
		return function_exists( 'get_queried_object_id' ) ? max( 0, (int) get_queried_object_id() ) : 0;
	}

	/** Resolve a native controlled taxonomy query. */
	private static function native_taxonomy_context() {
		$out = array( 'taxonomy' => '', 'term' => '' );
		if ( ! function_exists( 'get_queried_object' ) ) {
			return $out;
		}
		$object = get_queried_object();
		if ( ! is_object( $object ) || empty( $object->taxonomy ) || empty( $object->slug ) ) {
			return $out;
		}
		$taxonomy = (string) $object->taxonomy;
		$term = self::strict_slug( (string) $object->slug );
		if ( ! in_array( $taxonomy, Phase4Contracts::taxonomies(), true ) || '' === $term ) {
			return $out;
		}
		return array( 'taxonomy' => $taxonomy, 'term' => $term );
	}

	/** Read and bound public filters from GET. */
	private static function request_filters() {
		$args = array();
		$map = array(
			'q'          => 'q',
			'section'    => 'section',
			'topic'      => 'topic',
			'country'    => 'country',
			'region'     => 'region',
			'type'       => 'type',
			'date_from'  => 'date_from',
			'date_to'    => 'date_to',
			'author'     => 'author',
			'research'   => 'research',
			'corrected'  => 'corrected',
			'retracted'  => 'retracted',
			'page'       => 'page',
			'per_page'   => 'per_page',
		);
		foreach ( $map as $request_key => $arg_key ) {
			if ( isset( $_GET[ $request_key ] ) && is_scalar( $_GET[ $request_key ] ) ) {
				$args[ $arg_key ] = function_exists( 'wp_unslash' ) ? wp_unslash( $_GET[ $request_key ] ) : $_GET[ $request_key ];
			}
		}
		return $args;
	}

	/** Convert taxonomy identifier to query filter. */
	private static function taxonomy_filter( $taxonomy ) {
		$map = array(
			'sabri_news_section' => 'section',
			'sabri_news_topic'   => 'topic',
			'sabri_news_country' => 'country',
			'sabri_news_region'  => 'region',
			'sabri_news_type'    => 'type',
		);
		return isset( $map[ $taxonomy ] ) ? $map[ $taxonomy ] : '';
	}

	/** Convert taxonomy identifier to public route. */
	private static function taxonomy_route( $taxonomy ) {
		$filter = self::taxonomy_filter( $taxonomy );
		return '' !== $filter ? $filter : 'section';
	}

	/** Require a real controlled taxonomy term for public route resolution. */
	private static function term_exists( $taxonomy, $term ) {
		if ( 'sabri_news_section' === $taxonomy ) {
			return isset( Phase4Contracts::sections()[ $term ] );
		}
		if ( 'sabri_news_type' === $taxonomy ) {
			return isset( Phase4Contracts::article_types()[ $term ] );
		}
		if ( function_exists( 'get_term_by' ) ) {
			$object = get_term_by( 'slug', $term, $taxonomy );
			return $object && ! ( function_exists( 'is_wp_error' ) && is_wp_error( $object ) );
		}
		return function_exists( 'apply_filters' ) ? (bool) apply_filters( 'sabri_phase4c_test_term_exists', false, $taxonomy, $term ) : false;
	}

	/** Safe taxonomy title. */
	private static function term_title( $taxonomy, $term ) {
		if ( function_exists( 'get_term_by' ) ) {
			$object = get_term_by( 'slug', $term, $taxonomy );
			if ( $object && ! ( function_exists( 'is_wp_error' ) && is_wp_error( $object ) ) && ! empty( $object->name ) ) {
				return sanitize_text_field( $object->name );
			}
		}
		return ucwords( str_replace( '-', ' ', $term ) );
	}

	/** Read one route query var. */
	private static function query_var( $key ) {
		$value = function_exists( 'get_query_var' ) ? get_query_var( $key, '' ) : '';
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** Mark a request as a non-enumerating 404. */
	private static function mark_404() {
		global $wp_query;
		if ( is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
			$wp_query->set_404();
		}
		if ( function_exists( 'status_header' ) ) {
			status_header( 404 );
		}
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}
		NewsPublicRuntime::set_context( array() );
	}

	/** Strict route slug. */
	private static function strict_slug( $value ) {
		return is_string( $value ) && strlen( $value ) <= 120 && preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) ? $value : '';
	}
}
