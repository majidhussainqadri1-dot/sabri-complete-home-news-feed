<?php
/**
 * Canonical Home and News composition registry.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps File 21 as the Home/News content engine without copying companion data. */
final class HomeCompositionRegistry {
	private static $rows_rendered = false;

	/** Register native Shell slots and fallback content mounting. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'sabri_shell_home_main', array( __CLASS__, 'render_shell_home' ), 20 );
			add_action( 'sabri_shell_news_main', array( __CLASS__, 'render_shell_news' ), 20 );
			add_action( 'sabri_feed_home_after_primary', array( __CLASS__, 'render_rows_action' ), 20 );
		}
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'append_rows_to_corrective_surface' ), 9 );
		}
	}

	/** Canonical Home control items from the governing Master Plan. */
	public static function control_items() {
		$items = array(
			'for-you'              => array( 'label' => __( 'For You', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'most-viral'           => array( 'label' => __( 'Most Viral', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'latest'               => array( 'label' => __( 'Latest', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'founder-updates'      => array( 'label' => __( 'Founder Posts', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'doctors-posts'        => array( 'label' => __( 'Doctors Posts', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'classical-homeopathy' => array( 'label' => __( 'Classical Learning', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'remedies'             => array( 'label' => __( 'Remedies', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'diseases'             => array( 'label' => __( 'Diseases', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'clinical-cases'       => array( 'label' => __( 'Clinical Cases', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'videos'               => array( 'label' => __( 'Videos', 'sabri-complete-home-news-feed' ), 'kind' => 'module', 'module' => 'video_wall', 'path' => '/video-wall/' ),
			'reels'                => array( 'label' => __( 'Reels', 'sabri-complete-home-news-feed' ), 'kind' => 'module', 'module' => 'reels', 'path' => '/reels/' ),
			'pdf-books'            => array( 'label' => __( 'PDF Books', 'sabri-complete-home-news-feed' ), 'kind' => 'module', 'module' => 'pdf_library', 'path' => '/pdf-library/' ),
			'clinics'              => array( 'label' => __( 'Clinics', 'sabri-complete-home-news-feed' ), 'kind' => 'module', 'module' => 'appointments', 'path' => '/worldwide-clinic/' ),
			'marketplace'          => array( 'label' => __( 'Marketplace', 'sabri-complete-home-news-feed' ), 'kind' => 'module', 'module' => 'marketplace', 'path' => '/marketplace/' ),
		);
		return function_exists( 'apply_filters' ) ? (array) apply_filters( 'sabri_hnf_home_control_items', $items ) : $items;
	}

	/** Render the complete control bar without passing module links into FeedQuery. */
	public static function render_control_bar( $active_mode ) {
		$active_mode = function_exists( 'sanitize_key' ) ? sanitize_key( $active_mode ) : (string) $active_mode;
		$html = '<nav class="sabri-hnf-filter sabri-hnf-home-control" aria-label="' . esc_attr__( 'Home content filters', 'sabri-complete-home-news-feed' ) . '"><ul>';
		foreach ( self::control_items() as $key => $item ) {
			$key = sanitize_key( $key );
			if ( empty( $item['label'] ) ) { continue; }
			$kind = isset( $item['kind'] ) ? $item['kind'] : '';
			$url = 'feed' === $kind ? self::feed_url( $key ) : self::module_url( $item );
			if ( '' === $url ) { continue; }
			$is_active = 'feed' === $kind && $active_mode === $key;
			$html .= '<li><a class="sabri-hnf-filter__link' . ( $is_active ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $is_active ? ' aria-current="page"' : '' ) . ' data-sabri-home-control="' . esc_attr( $key ) . '">' . esc_html( $item['label'] ) . '</a></li>';
		}
		return $html . '</ul></nav>';
	}

	/** Exactly ten governing Home rows and their owning providers. */
	public static function rows() {
		$rows = array(
			'most-viral-now'            => array( 'label' => __( 'Most Viral Now', 'sabri-complete-home-news-feed' ), 'provider' => 'feed', 'mode' => 'most-viral', 'limit' => 6 ),
			'latest-news'                => array( 'label' => __( 'Latest News', 'sabri-complete-home-news-feed' ), 'provider' => 'news', 'limit' => 6 ),
			'from-founder'               => array( 'label' => __( 'From the Founder', 'sabri-complete-home-news-feed' ), 'provider' => 'feed', 'mode' => 'founder-updates', 'limit' => 6 ),
			'from-verified-doctors'      => array( 'label' => __( 'From Verified Doctors', 'sabri-complete-home-news-feed' ), 'provider' => 'feed', 'mode' => 'doctors-posts', 'limit' => 6 ),
			'learn-classical-homeopathy' => array( 'label' => __( 'Learn Sabri Classical Homeopathy', 'sabri-complete-home-news-feed' ), 'provider' => 'learning', 'limit' => 6 ),
			'videos'                     => array( 'label' => __( 'Videos', 'sabri-complete-home-news-feed' ), 'provider' => 'video_wall', 'limit' => 6 ),
			'reels'                      => array( 'label' => __( 'Reels', 'sabri-complete-home-news-feed' ), 'provider' => 'reels', 'limit' => 6 ),
			'pdf-books'                  => array( 'label' => __( 'PDF Books', 'sabri-complete-home-news-feed' ), 'provider' => 'pdf_library', 'limit' => 6 ),
			'clinics'                    => array( 'label' => __( 'Worldwide Clinics', 'sabri-complete-home-news-feed' ), 'provider' => 'appointments', 'limit' => 6 ),
			'marketplace'                => array( 'label' => __( 'Marketplace', 'sabri-complete-home-news-feed' ), 'provider' => 'marketplace', 'limit' => 6 ),
		);
		return function_exists( 'apply_filters' ) ? (array) apply_filters( 'sabri_hnf_home_rows', $rows ) : $rows;
	}

	/** Render all ten rows; unavailable providers produce an explicit empty state. */
	public static function render_rows() {
		if ( self::$rows_rendered ) { return ''; }
		self::$rows_rendered = true;
		$html = '<div class="sabri-hnf-home-rows" data-sabri-home-rows data-sabri-home-row-count="10">';
		foreach ( self::rows() as $key => $row ) {
			$items = self::row_items( $key, $row );
			$state = empty( $items ) ? 'unavailable' : 'available';
			$html .= '<section class="sabri-hnf-home-row is-' . esc_attr( $state ) . '" data-sabri-home-row="' . esc_attr( $key ) . '" data-provider-state="' . esc_attr( $state ) . '"><header><h2>' . esc_html( $row['label'] ) . '</h2></header>';
			if ( empty( $items ) ) {
				$html .= '<p class="sabri-hnf-home-row__empty" role="status">' . esc_html__( 'Content is not available from this module yet.', 'sabri-complete-home-news-feed' ) . '</p>';
			} else {
				$html .= '<div class="sabri-hnf-home-row__items">';
				foreach ( $items as $item ) { $html .= self::render_row_item( $item ); }
				$html .= '</div>';
			}
			$html .= '</section>';
		}
		return $html . '</div>';
	}

	/** Render the official Shell Home slot. */
	public static function render_shell_home() {
		if ( ! class_exists( __NAMESPACE__ . '\\CorrectivePublicMount' ) ) { return; }
		$surface = CorrectivePublicMount::render_complete_surface( 'shell_home_main' );
		if ( '' !== $surface ) { echo $surface; }
	}

	/** Render the official Shell News slot. */
	public static function render_shell_news() {
		if ( ! class_exists( __NAMESPACE__ . '\\CorrectivePublicMount' ) ) { return; }
		$surface = CorrectivePublicMount::render_news_surface( 'shell_news_main' );
		if ( '' !== $surface ) { echo $surface; }
	}

	/** Action wrapper after a primary Feed rendered by another accepted slot. */
	public static function render_rows_action() { echo self::render_rows(); }

	/** Append rows after a legacy corrective surface that did not yet include them. */
	public static function append_rows_to_corrective_surface( $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, 'data-sabri-hnf-surface="file-21-corrective"' ) || false !== strpos( $content, 'data-sabri-home-rows' ) ) { return $content; }
		return $content . self::render_rows();
	}

	/** Resolve one row's normalized items. */
	private static function row_items( $key, array $row ) {
		$limit = isset( $row['limit'] ) ? max( 1, min( 12, (int) $row['limit'] ) ) : 6;
		$provider = isset( $row['provider'] ) ? sanitize_key( $row['provider'] ) : '';
		$items = array();
		if ( 'feed' === $provider ) {
			$result = FeedQuery::query( array( 'mode' => isset( $row['mode'] ) ? $row['mode'] : 'latest', 'page' => 1, 'per_page' => $limit ) );
			foreach ( isset( $result['posts'] ) && is_array( $result['posts'] ) ? $result['posts'] : array() as $post ) {
				$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
				if ( $post_id > 0 ) {
					$items[] = array( 'title' => function_exists( 'get_the_title' ) ? get_the_title( $post_id ) : '', 'url' => function_exists( 'get_permalink' ) ? get_permalink( $post_id ) : '', 'type' => 'post' );
				}
			}
		} elseif ( 'news' === $provider && class_exists( __NAMESPACE__ . '\\NewsQueryService' ) && class_exists( __NAMESPACE__ . '\\NewsPolicy' ) && NewsPolicy::public_reads_allowed() ) {
			$result = NewsQueryService::query( array( 'per_page' => $limit ) );
			foreach ( ! empty( $result['data']['items'] ) && is_array( $result['data']['items'] ) ? $result['data']['items'] : array() as $item ) {
				$items[] = array( 'title' => isset( $item['headline'] ) ? $item['headline'] : '', 'url' => isset( $item['canonical_url'] ) ? $item['canonical_url'] : '', 'type' => 'news' );
			}
		}
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'sabri_hnf_home_row_items_' . sanitize_key( $key ), $items, $row, $limit );
			$items = is_array( $filtered ) ? $filtered : $items;
		}
		return array_slice( array_values( array_filter( $items, 'is_array' ) ), 0, $limit );
	}

	/** Render a safe generic card projection. */
	private static function render_row_item( array $item ) {
		$title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
		$url = isset( $item['url'] ) ? esc_url( $item['url'] ) : '';
		if ( '' === $title || '' === $url ) { return ''; }
		$summary = isset( $item['summary'] ) ? sanitize_text_field( $item['summary'] ) : '';
		return '<article class="sabri-hnf-home-row-card"><h3><a href="' . $url . '">' . esc_html( $title ) . '</a></h3>' . ( '' !== $summary ? '<p>' . esc_html( $summary ) . '</p>' : '' ) . '</article>';
	}

	/** Feed URL retaining only the controlled mode. */
	private static function feed_url( $mode ) {
		$base = function_exists( 'home_url' ) ? home_url( '/' ) : '/';
		return function_exists( 'add_query_arg' ) ? add_query_arg( array( 'sabri_feed_mode' => sanitize_key( $mode ), 'sabri_feed_page' => 1 ), $base ) : $base;
	}

	/** Module URL from a filter or stable path fallback. */
	private static function module_url( array $item ) {
		$module = isset( $item['module'] ) ? sanitize_key( $item['module'] ) : '';
		$path = isset( $item['path'] ) ? (string) $item['path'] : '/';
		$url = function_exists( 'home_url' ) ? home_url( $path ) : $path;
		if ( function_exists( 'apply_filters' ) ) { $url = apply_filters( 'sabri_hnf_module_url_' . $module, $url, $item ); }
		return is_scalar( $url ) ? (string) $url : '';
	}

	/** Reset request-level row guard for tests. */
	public static function reset_runtime_guards() { self::$rows_rendered = false; }
}
