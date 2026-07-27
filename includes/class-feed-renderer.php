<?php
/**
 * Home Feed renderer.
 *
 * @package SabriCompleteHomeNewsFeed
 */
namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Renders accessible feed UI from real WordPress posts. */
final class FeedRenderer {
	public static function render( array $atts = array() ) {
		Assets::enqueue_feed();
		$result = FeedQuery::query( $atts );
		$settings = Settings::get();
		if ( 'disabled' === $result['status'] ) {
			return self::template( 'feed-error', array( 'message' => __( 'The custom Home Feed is currently disabled.', 'sabri-complete-home-news-feed' ) ) );
		}
		return self::template(
			'feed',
			array(
				'result' => $result,
				'settings' => $settings,
				'filters' => self::render_filter_nav( $result['mode'], $settings ),
				'cards' => self::render_cards( $result['posts'], $settings ),
				'pagination' => self::render_pagination( $result, $settings ),
				'empty_state' => self::template( 'feed-empty', array() ),
			)
		);
	}

	/** Render the exact approved fourteen-item Home Control Bar. */
	public static function render_filter_nav( $active, array $settings ) {
		unset( $settings );
		if ( class_exists( __NAMESPACE__ . '\\HomeCompositionRegistry' ) ) {
			return HomeCompositionRegistry::render_control_bar( $active );
		}
		$modes = FeedContext::modes();
		$html = '<nav class="sabri-hnf-filter" aria-label="' . esc_attr__( 'Home Feed filters', 'sabri-complete-home-news-feed' ) . '"><ul>';
		foreach ( $modes as $mode => $label ) {
			$url = self::feed_url( $mode, 1 );
			$html .= '<li><a class="sabri-hnf-filter__link' . ( $active === $mode ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $active === $mode ? ' aria-current="page"' : '' ) . ' data-sabri-feed-mode="' . esc_attr( $mode ) . '">' . esc_html( $label ) . '</a></li>';
		}
		return $html . '</ul></nav>';
	}

	public static function render_cards( array $posts, array $settings ) {
		$html = '';
		foreach ( $posts as $post ) { $html .= self::render_card( $post, $settings ); }
		return $html;
	}

	public static function render_card( $post, array $settings ) {
		if ( is_array( $post ) && 'editorial_news' === ( isset( $post['item_type'] ) ? $post['item_type'] : '' ) ) {
			return class_exists( __NAMESPACE__ . '\\NewsPublicRuntime' ) ? NewsPublicRuntime::render_card( $post ) : '';
		}
		$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
		if ( $post_id <= 0 || ! PostMetadata::user_can_view( $post_id ) ) { return ''; }
		$type = PostMetadata::feed_type( $post_id );
		return self::template(
			'feed-card',
			array(
				'post_id' => $post_id,
				'title' => self::post_title( $post_id ),
				'excerpt' => self::post_excerpt( $post_id ),
				'permalink' => self::permalink( $post_id ),
				'author_avatar' => empty( $settings['feed']['show_author_details'] ) ? '' : self::author_avatar( $post_id ),
				'author_name' => empty( $settings['feed']['show_author_details'] ) ? '' : self::author_name( $post_id ),
				'author_label' => empty( $settings['feed']['show_author_details'] ) ? '' : self::author_label( $post_id ),
				'badges' => empty( $settings['feed']['show_author_details'] ) ? array() : self::author_badges( $post_id ),
				'feed_type' => empty( $settings['feed']['show_post_type'] ) ? '' : self::feed_type_label( $type ),
				'visibility' => PostMetadata::visibility( $post_id ),
				'date' => self::post_date( $post_id ),
				'time' => self::post_time( $post_id ),
				'edited' => self::is_edited( $post_id ),
				'featured' => self::featured_image( $post_id, $settings ),
				'gallery' => self::media_gallery( $post_id, $settings ),
				'topics' => self::term_links( $post_id, 'sabri_feed_topic' ),
				'categories' => self::term_links( $post_id, 'category' ),
				'hashtags' => self::term_links( $post_id, 'post_tag' ),
				'disclaimer' => self::medical_disclaimer( $type, $settings ),
			)
		);
	}

	public static function render_pagination( array $result, array $settings ) {
		return self::template( 'pagination', array( 'result' => $result, 'settings' => $settings, 'pagination' => self::pagination_links( $result ), 'next_url' => self::rest_feed_url( $result['mode'], (int) $result['page'] + 1, (int) $result['per_page'] ), 'state_url' => self::feed_url( $result['mode'], (int) $result['page'] + 1 ) ) );
	}

	public static function template( $template, array $vars ) {
		$file = SABRI_HNF_PATH . 'templates/' . sanitize_key( $template ) . '.php';
		if ( ! is_readable( $file ) ) { return ''; }
		ob_start(); extract( $vars, EXTR_SKIP ); include $file; return (string) ob_get_clean();
	}

	private static function pagination_links( array $result ) {
		if ( (int) $result['max_pages'] <= 1 ) { return ''; }
		if ( function_exists( 'paginate_links' ) ) {
			$links = paginate_links( array( 'base' => self::feed_url( $result['mode'], '%#%' ), 'format' => '', 'current' => (int) $result['page'], 'total' => (int) $result['max_pages'], 'type' => 'list', 'prev_text' => __( 'Previous', 'sabri-complete-home-news-feed' ), 'next_text' => __( 'Next', 'sabri-complete-home-news-feed' ) ) );
			return is_string( $links ) ? $links : '';
		}
		return '';
	}

	private static function feed_url( $mode, $page ) {
		$base = function_exists( 'get_pagenum_link' ) ? get_pagenum_link( 1 ) : '';
		if ( '' === $base && function_exists( 'home_url' ) ) { $base = home_url( '/' ); }
		if ( function_exists( 'add_query_arg' ) ) { return add_query_arg( array( 'sabri_feed_mode' => sanitize_key( $mode ), 'sabri_feed_page' => $page ), $base ); }
		return $base . '?sabri_feed_mode=' . rawurlencode( sanitize_key( $mode ) ) . '&sabri_feed_page=' . rawurlencode( (string) $page );
	}

	private static function rest_feed_url( $mode, $page, $per_page ) {
		$base = function_exists( 'rest_url' ) ? rest_url( RestFoundation::NAMESPACE . '/feed' ) : '';
		if ( function_exists( 'add_query_arg' ) ) { return add_query_arg( array( 'mode' => sanitize_key( $mode ), 'page' => (int) $page, 'per_page' => (int) $per_page ), $base ); }
		return $base . '?mode=' . rawurlencode( sanitize_key( $mode ) ) . '&page=' . rawurlencode( (string) $page ) . '&per_page=' . rawurlencode( (string) $per_page );
	}

	private static function post_title( $post_id ) { return function_exists( 'get_the_title' ) ? get_the_title( $post_id ) : ''; }
	private static function post_excerpt( $post_id ) { $excerpt = function_exists( 'get_the_excerpt' ) ? get_the_excerpt( $post_id ) : ''; return function_exists( 'wp_trim_words' ) ? wp_trim_words( wp_strip_all_tags( $excerpt ), 42, '…' ) : $excerpt; }
	private static function permalink( $post_id ) { return function_exists( 'get_permalink' ) ? get_permalink( $post_id ) : ''; }
	private static function author_name( $post_id ) { $author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0; return ProfileLinkResolver::display_name( $author_id ); }
	private static function author_avatar( $post_id ) { $author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0; return function_exists( 'get_avatar' ) ? get_avatar( $author_id, 48, '', '', array( 'class' => 'sabri-hnf-card__avatar' ) ) : ''; }
	private static function author_label( $post_id ) { $author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0; $projection = CanonicalIdentityAdapter::public_projection( $author_id ); return ! empty( $projection['is_founder'] ) ? __( 'Founder', 'sabri-complete-home-news-feed' ) : ( ! empty( $projection['is_verified_doctor'] ) ? __( 'Verified Doctor', 'sabri-complete-home-news-feed' ) : __( 'Author', 'sabri-complete-home-news-feed' ) ); }
	private static function author_badges( $post_id ) { $author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0; $projection = CanonicalIdentityAdapter::public_projection( $author_id ); $badges = array(); if ( ! empty( $projection['is_founder'] ) ) { $badges[] = __( 'Founder', 'sabri-complete-home-news-feed' ); } if ( ! empty( $projection['is_verified_doctor'] ) ) { $badges[] = __( 'Verified Doctor', 'sabri-complete-home-news-feed' ); } return $badges; }
	private static function feed_type_label( $type ) { $terms = Taxonomies::feed_type_terms(); return isset( $terms[ $type ] ) ? $terms[ $type ] : ucwords( str_replace( '-', ' ', $type ) ); }
	private static function post_date( $post_id ) { return function_exists( 'get_the_date' ) ? get_the_date( '', $post_id ) : ''; }
	private static function post_time( $post_id ) { return function_exists( 'get_the_time' ) ? get_the_time( '', $post_id ) : ''; }
	private static function is_edited( $post_id ) { if ( ! function_exists( 'get_post_time' ) || ! function_exists( 'get_post_modified_time' ) ) { return false; } return (int) get_post_modified_time( 'U', true, $post_id ) > (int) get_post_time( 'U', true, $post_id ) + 60; }
	private static function featured_image( $post_id, array $settings ) { if ( empty( $settings['feed']['show_media'] ) || ! function_exists( 'get_the_post_thumbnail' ) ) { return ''; } return get_the_post_thumbnail( $post_id, 'large', array( 'loading' => 'lazy' ) ); }
	private static function media_gallery( $post_id, array $settings ) { if ( empty( $settings['feed']['show_media'] ) ) { return ''; } $ids = get_post_meta( $post_id, PostMetadata::META_GALLERY, true ); if ( ! is_array( $ids ) || ! function_exists( 'wp_get_attachment_image' ) ) { return ''; } $html = ''; foreach ( array_slice( array_filter( array_map( 'absint', $ids ) ), 0, 4 ) as $id ) { $html .= wp_get_attachment_image( $id, 'large', false, array( 'loading' => 'lazy' ) ); } return '' !== $html ? '<div class="sabri-hnf-card__gallery">' . $html . '</div>' : ''; }
	private static function term_links( $post_id, $taxonomy ) { if ( ! function_exists( 'get_the_terms' ) ) { return array(); } $terms = get_the_terms( $post_id, $taxonomy ); if ( ! is_array( $terms ) ) { return array(); } $out = array(); foreach ( $terms as $term ) { if ( ! is_object( $term ) || empty( $term->name ) ) { continue; } $url = function_exists( 'get_term_link' ) ? get_term_link( $term ) : ''; $out[] = array( 'name' => $term->name, 'url' => is_wp_error( $url ) ? '' : $url ); } return $out; }
	private static function medical_disclaimer( $type, array $settings ) { if ( empty( $settings['feed']['show_disclaimer'] ) || ! in_array( $type, array( 'clinical-case', 'patient-case', 'clinical-education', 'public-health-education', 'pathology', 'nutrition' ), true ) ) { return ''; } return __( 'Educational information only. It does not replace personal diagnosis, emergency care, or advice from a qualified healthcare professional.', 'sabri-complete-home-news-feed' ); }
}
