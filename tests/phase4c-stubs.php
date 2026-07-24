<?php
/** Phase 4C-only WordPress behavior shims for lean public News tests. */

if ( ! isset( $sabri_test_rewrite_rules ) ) {
	$sabri_test_rewrite_rules = array();
}
if ( ! isset( $sabri_test_query_vars ) ) {
	$sabri_test_query_vars = array();
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strtolower( trim( wp_strip_all_tags( (string) $title ) ) );
		$title = preg_replace( '/[^a-z0-9]+/', '-', $title );
		return trim( $title, '-' );
	}
}
if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $class ) { return sanitize_key( $class ); }
}
if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, $timestamp ) { return gmdate( $format, (int) $timestamp ); }
}
if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = null ) { unset( $domain ); return 1 === (int) $number ? $single : $plural; }
}
if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type( $post_id ) { return (string) get_post_field( 'post_type', $post_id ); }
}
if ( ! function_exists( 'get_author_posts_url' ) ) {
	function get_author_posts_url( $user_id ) { return home_url( '/author/' . (int) $user_id . '/' ); }
}
if ( ! function_exists( 'get_term_link' ) ) {
	function get_term_link( $term, $taxonomy ) {
		$slug = is_object( $term ) && isset( $term->slug ) ? $term->slug : (string) $term;
		$map = array(
			'sabri_news_section' => 'section',
			'sabri_news_topic' => 'topic',
			'sabri_news_country' => 'country',
			'sabri_news_region' => 'region',
			'sabri_news_type' => 'type',
		);
		return home_url( '/news/' . ( isset( $map[ $taxonomy ] ) ? $map[ $taxonomy ] : 'section' ) . '/' . sanitize_key( $slug ) . '/' );
	}
}
if ( ! function_exists( 'get_term_by' ) ) {
	function get_term_by( $field, $value, $taxonomy ) {
		global $sabri_test_terms;
		unset( $field );
		if ( isset( $sabri_test_terms[ $taxonomy ][ $value ] ) ) {
			return (object) array( 'slug' => $value, 'name' => $sabri_test_terms[ $taxonomy ][ $value ] );
		}
		return false;
	}
}
if ( ! function_exists( 'add_rewrite_rule' ) ) {
	function add_rewrite_rule( $regex, $query, $position = 'bottom' ) {
		global $sabri_test_rewrite_rules;
		$sabri_test_rewrite_rules[] = compact( 'regex', 'query', 'position' );
	}
}
if ( ! function_exists( 'get_query_var' ) ) {
	function get_query_var( $key, $default = '' ) {
		global $sabri_test_query_vars;
		return array_key_exists( $key, $sabri_test_query_vars ) ? $sabri_test_query_vars[ $key ] : $default;
	}
}
if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
	function get_the_post_thumbnail_url( $post_id, $size = 'post-thumbnail' ) { unset( $size ); $id = get_post_thumbnail_id( $post_id ); return $id ? home_url( '/media/' . $id . '.jpg' ) : ''; }
}
if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	function wp_get_attachment_image_url( $attachment_id, $size = 'thumbnail' ) { unset( $size ); return $attachment_id ? home_url( '/media/' . (int) $attachment_id . '.jpg' ) : ''; }
}
if ( ! function_exists( 'wp_get_attachment_caption' ) ) {
	function wp_get_attachment_caption( $attachment_id ) { return (string) get_post_field( 'post_excerpt', $attachment_id ); }
}
