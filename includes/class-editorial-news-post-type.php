<?php
/**
 * Editorial News post type and metadata.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers the distinct, fail-closed Editorial News content model. */
final class EditorialNewsPostType {
	/** Register hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', array( __CLASS__, 'register_post_type' ), 8 );
			add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		}
	}

	/** Return the post-type registration definition. */
	public static function definition() {
		$public_enabled = NewsFeatureSettings::enabled( 'editorial_news_enabled' );
		return array(
			'labels' => array(
				'name'               => __( 'Editorial News', 'sabri-complete-home-news-feed' ),
				'singular_name'      => __( 'Editorial News Article', 'sabri-complete-home-news-feed' ),
				'add_new_item'       => __( 'Add Editorial News Article', 'sabri-complete-home-news-feed' ),
				'edit_item'          => __( 'Edit Editorial News Article', 'sabri-complete-home-news-feed' ),
				'view_item'          => __( 'View Editorial News Article', 'sabri-complete-home-news-feed' ),
				'search_items'       => __( 'Search Editorial News', 'sabri-complete-home-news-feed' ),
				'not_found'          => __( 'No Editorial News found.', 'sabri-complete-home-news-feed' ),
				'not_found_in_trash' => __( 'No Editorial News found in Trash.', 'sabri-complete-home-news-feed' ),
			),
			'public'              => false,
			'publicly_queryable'  => $public_enabled,
			'exclude_from_search' => ! $public_enabled,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'query_var'           => $public_enabled ? Phase4Contracts::POST_TYPE : false,
			'rewrite'             => $public_enabled ? array( 'slug' => 'news', 'with_front' => false ) : false,
			'has_archive'         => $public_enabled ? 'news' : false,
			'hierarchical'        => false,
			'menu_icon'           => 'dashicons-media-document',
			'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions' ),
			'map_meta_cap'        => true,
			'capabilities'        => self::capability_map(),
			'delete_with_user'    => false,
		);
	}

	/** Register the custom post type. */
	public static function register_post_type() {
		if ( function_exists( 'register_post_type' ) ) {
			register_post_type( Phase4Contracts::POST_TYPE, self::definition() );
		}
	}

	/** Map singular object checks and deny every destructive delete primitive. */
	public static function capability_map() {
		return array(
			'edit_post'              => 'edit_editorial_news',
			'read_post'              => 'read_editorial_news_item',
			'delete_post'            => 'delete_editorial_news',
			'edit_posts'             => 'edit_own_editorial_news',
			'edit_others_posts'      => 'edit_others_editorial_news',
			'publish_posts'          => 'publish_editorial_news',
			'read_private_posts'     => 'review_editorial_news',
			'delete_posts'           => 'do_not_allow',
			'delete_private_posts'   => 'do_not_allow',
			'delete_published_posts' => 'do_not_allow',
			'delete_others_posts'    => 'do_not_allow',
			'edit_private_posts'     => 'review_editorial_news',
			'edit_published_posts'   => 'manage_news_corrections',
			'create_posts'           => 'create_editorial_news',
		);
	}

	/** Register private, explicitly authorized article metadata. */
	public static function register_meta() {
		if ( ! function_exists( 'register_post_meta' ) ) {
			return;
		}
		foreach ( self::meta_definitions() as $key => $definition ) {
			register_post_meta(
				Phase4Contracts::POST_TYPE,
				$key,
				array(
					'type'              => $definition['type'],
					'single'            => true,
					'default'           => $definition['default'],
					'show_in_rest'      => false,
					'auth_callback'     => array( __CLASS__, 'meta_auth_callback' ),
					'sanitize_callback' => $definition['sanitize_callback'],
				)
			);
		}
	}

	/** Frozen Phase 4A metadata definitions. */
	public static function meta_definitions() {
		return array(
			Phase4Contracts::WORKFLOW_META_KEY     => array( 'type' => 'string', 'default' => 'draft', 'sanitize_callback' => array( NewsStatuses::class, 'sanitize_state' ) ),
			'_sabri_news_subtitle'                 => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'_sabri_news_summary'                  => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_textarea_field' ),
			'_sabri_news_language'                 => array( 'type' => 'string', 'default' => 'en-US', 'sanitize_callback' => array( __CLASS__, 'sanitize_language' ) ),
			'_sabri_news_priority'                 => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => array( __CLASS__, 'sanitize_priority' ) ),
			'_sabri_news_fact_check_status'        => array( 'type' => 'string', 'default' => 'not-started', 'sanitize_callback' => array( __CLASS__, 'sanitize_token' ) ),
			'_sabri_news_medical_review_status'    => array( 'type' => 'string', 'default' => 'not-required', 'sanitize_callback' => array( __CLASS__, 'sanitize_token' ) ),
			'_sabri_news_breaking_status'          => array( 'type' => 'string', 'default' => 'inactive', 'sanitize_callback' => array( __CLASS__, 'sanitize_token' ) ),
			'_sabri_news_breaking_starts_at'       => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => array( __CLASS__, 'sanitize_datetime' ) ),
			'_sabri_news_breaking_expires_at'      => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => array( __CLASS__, 'sanitize_datetime' ) ),
			'_sabri_news_last_verified_at'         => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => array( __CLASS__, 'sanitize_datetime' ) ),
			'_sabri_news_correction_status'        => array( 'type' => 'string', 'default' => 'none', 'sanitize_callback' => array( __CLASS__, 'sanitize_token' ) ),
			'_sabri_news_retraction_status'        => array( 'type' => 'string', 'default' => 'none', 'sanitize_callback' => array( __CLASS__, 'sanitize_token' ) ),
			'_sabri_news_reviewing_editor_id'      => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
			'_sabri_news_medical_reviewer_id'      => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
			'_sabri_news_source_article_id'        => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
		);
	}

	/** Return the exact capability required to change a registered metadata key. */
	public static function meta_capability( $meta_key ) {
		$map = array(
			'_sabri_news_subtitle'              => 'edit_editorial_news',
			'_sabri_news_summary'               => 'edit_editorial_news',
			'_sabri_news_language'              => 'edit_editorial_news',
			Phase4Contracts::WORKFLOW_META_KEY  => 'publish_editorial_news',
			'_sabri_news_priority'              => 'manage_breaking_news',
			'_sabri_news_fact_check_status'     => 'fact_check_editorial_news',
			'_sabri_news_last_verified_at'      => 'fact_check_editorial_news',
			'_sabri_news_medical_review_status' => 'medical_review_editorial_news',
			'_sabri_news_medical_reviewer_id'   => 'review_editorial_news',
			'_sabri_news_reviewing_editor_id'   => 'review_editorial_news',
			'_sabri_news_breaking_status'       => 'manage_breaking_news',
			'_sabri_news_breaking_starts_at'    => 'manage_breaking_news',
			'_sabri_news_breaking_expires_at'   => 'manage_breaking_news',
			'_sabri_news_correction_status'     => 'manage_news_corrections',
			'_sabri_news_retraction_status'     => 'retract_editorial_news',
			'_sabri_news_source_article_id'     => 'translate_editorial_news',
		);
		return is_string( $meta_key ) && isset( $map[ $meta_key ] ) ? $map[ $meta_key ] : 'do_not_allow';
	}

	/** Authorize metadata edits through exact field, object, and assignment rules. */
	public static function meta_auth_callback( $allowed = false, $meta_key = '', $post_id = 0, $user_id = 0 ) {
		unset( $allowed );
		$post_id = absint( $post_id );
		$user_id = absint( $user_id );
		if ( $post_id < 1 || ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		$current_user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user_id > 0 && $user_id !== $current_user_id ) {
			return false;
		}

		$post_type = '';
		if ( function_exists( 'get_post_type' ) ) {
			$post_type = (string) get_post_type( $post_id );
		} elseif ( function_exists( 'get_post' ) ) {
			$post = get_post( $post_id );
			$post_type = $post && isset( $post->post_type ) ? (string) $post->post_type : '';
		}
		if ( Phase4Contracts::POST_TYPE !== $post_type ) {
			return false;
		}

		$required = self::meta_capability( $meta_key );
		if ( 'do_not_allow' === $required ) {
			return false;
		}
		$can_edit_object = current_user_can( 'edit_editorial_news', $post_id );
		if ( 'edit_editorial_news' === $required ) {
			return $can_edit_object;
		}

		if ( '_sabri_news_medical_review_status' === $meta_key ) {
			if ( $can_edit_object && current_user_can( 'review_editorial_news' ) ) {
				return true;
			}
			$assigned_id = function_exists( 'get_post_meta' ) ? absint( get_post_meta( $post_id, '_sabri_news_medical_reviewer_id', true ) ) : 0;
			return $current_user_id > 0 && $assigned_id === $current_user_id && current_user_can( 'medical_review_editorial_news' );
		}

		return $can_edit_object && current_user_can( $required );
	}

	/** Validate a bounded BCP-47-style language tag without repairing unsafe input. */
	public static function sanitize_language( $value ) {
		$value = is_string( $value ) ? $value : '';
		if ( '' === $value || strlen( $value ) > 20 || ! preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $value ) ) {
			return 'en-US';
		}
		return $value;
	}

	/** Retain only an exact lowercase status token; never repair malformed input. */
	public static function sanitize_token( $value ) {
		if ( ! is_string( $value ) || '' === $value || strlen( $value ) > 64 ) {
			return '';
		}
		return preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) ? $value : '';
	}

	/** Store a bounded editorial priority from zero through one hundred. */
	public static function sanitize_priority( $value ) {
		if ( is_int( $value ) ) {
			$priority = $value;
		} elseif ( is_string( $value ) && preg_match( '/^(?:0|[1-9][0-9]{0,2})$/D', $value ) ) {
			$priority = (int) $value;
		} else {
			return 0;
		}
		return $priority >= 0 && $priority <= 100 ? $priority : 0;
	}

	/** Accept only an empty value or a bounded ISO/WordPress datetime string. */
	public static function sanitize_datetime( $value ) {
		if ( '' === $value ) {
			return '';
		}
		if ( ! is_string( $value ) || strlen( $value ) > 35 ) {
			return '';
		}
		$pattern = '/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2})(?:Z|[+-]\d{2}:\d{2})?$/D';
		return preg_match( $pattern, $value ) && false !== strtotime( $value ) ? $value : '';
	}
}
