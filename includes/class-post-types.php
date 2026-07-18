<?php
/**
 * WordPress post usage and metadata.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uses core posts and registers only needed metadata.
 */
final class PostTypes {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		}
	}

	/**
	 * Documented post usage policy.
	 *
	 * @return array<string,string>
	 */
	public static function usage_policy() {
		return array(
			'normal_feed_posts'  => 'Use the core post type with feed taxonomies and metadata.',
			'founder_updates'    => 'Use core posts with the Founder Update feed type.',
			'platform_news'      => 'Use core posts with the Platform News or Breaking News feed type.',
			'educational_posts'  => 'Use core posts with education-oriented feed type terms.',
			'clinical_cases'     => 'Use core posts with Clinical Case type, privacy review, and evidence metadata.',
			'research_posts'     => 'Use core posts with Research type and evidence-level taxonomy.',
		);
	}

	/**
	 * Register phase-1 metadata on posts.
	 *
	 * @return void
	 */
	public static function register_meta() {
		if ( class_exists( __NAMESPACE__ . '\\PostMetadata' ) ) {
			PostMetadata::register_meta();
			return;
		}

		if ( ! function_exists( 'register_post_meta' ) ) {
			return;
		}

		$common = array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'auth_callback'     => array( __CLASS__, 'meta_auth_callback' ),
			'sanitize_callback' => 'sanitize_key',
		);

		register_post_meta( 'post', '_sabri_feed_type', $common );
		register_post_meta( 'post', '_sabri_feed_visibility', $common );
		register_post_meta( 'post', '_sabri_evidence_level', $common );
		register_post_meta( 'post', '_sabri_feed_review_state', $common );
	}

	/**
	 * Metadata edit authorization.
	 *
	 * @param bool   $allowed Current value.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id Post ID.
	 * @param int    $user_id User ID.
	 * @return bool
	 */
	public static function meta_auth_callback( $allowed, $meta_key, $post_id, $user_id ) {
		unset( $allowed, $meta_key, $post_id, $user_id );

		return function_exists( 'current_user_can' ) && current_user_can( 'sabri_feed_create_posts' );
	}
}
