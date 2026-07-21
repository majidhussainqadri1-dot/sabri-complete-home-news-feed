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

	/**
	 * Map singular object checks to distinct meta capabilities and role checks to
	 * the frozen primitive capabilities. Destructive core deletion is denied;
	 * retraction is a separate non-destructive workflow operation.
	 */
	public static function capability_map() {
		return array(
			'edit_post'              => 'edit_editorial_news',
			'read_post'              => 'read_editorial_news_item',
			'delete_post'            => 'do_not_allow',
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
			'_sabri_news_priority'                 => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
			'_sabri_news_fact_check_status'        => array( 'type' => 'string', 'default' => 'not-started', 'sanitize_callback' => 'sanitize_key' ),
			'_sabri_news_medical_review_status'    => array( 'type' => 'string', 'default' => 'not-required', 'sanitize_callback' => 'sanitize_key' ),
			'_sabri_news_breaking_status'          => array( 'type' => 'string', 'default' => 'inactive', 'sanitize_callback' => 'sanitize_key' ),
			'_sabri_news_breaking_starts_at'       => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'_sabri_news_breaking_expires_at'      => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'_sabri_news_last_verified_at'         => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			'_sabri_news_correction_status'        => array( 'type' => 'string', 'default' => 'none', 'sanitize_callback' => 'sanitize_key' ),
			'_sabri_news_retraction_status'        => array( 'type' => 'string', 'default' => 'none', 'sanitize_callback' => 'sanitize_key' ),
			'_sabri_news_reviewing_editor_id'      => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
			'_sabri_news_medical_reviewer_id'      => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
			'_sabri_news_source_article_id'        => array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => 'absint' ),
		);
	}

	/** Authorize metadata edits through WordPress's ownership-aware object cap. */
	public static function meta_auth_callback( $allowed = false, $meta_key = '', $post_id = 0, $user_id = 0 ) {
		unset( $allowed, $meta_key, $user_id );
		$post_id = absint( $post_id );
		if ( $post_id < 1 || ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		if ( function_exists( 'get_post_type' ) && Phase4Contracts::POST_TYPE !== get_post_type( $post_id ) ) {
			return false;
		}
		return current_user_can( 'edit_editorial_news', $post_id );
	}

	/** Validate a bounded BCP-47-style language tag without repairing unsafe input. */
	public static function sanitize_language( $value ) {
		$value = (string) $value;
		if ( '' === $value || strlen( $value ) > 20 || ! preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $value ) ) {
			return 'en-US';
		}
		return $value;
	}
}
