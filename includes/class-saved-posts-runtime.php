<?php
/**
 * Phase 3B private Saved Posts shortcode.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides a server-rendered, current-user-only Saved Posts list.
 */
final class SavedPostsRuntime {
	const SHORTCODE = 'sabri_saved_posts';

	/**
	 * Register shortcode.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_shortcode' ) ) {
			add_shortcode( self::SHORTCODE, array( __CLASS__, 'render' ) );
		}
	}

	/**
	 * Render private saved posts.
	 *
	 * @param array<string,mixed> $atts Attributes.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		if ( ! Phase3FeatureSettings::enabled( 'saves_enabled' ) ) {
			return FeedRenderer::template( 'feed-error', array( 'message' => __( 'Saved Posts are currently unavailable.', 'sabri-complete-home-news-feed' ) ) );
		}

		$atts = function_exists( 'shortcode_atts' ) ? shortcode_atts( array( 'limit' => 100 ), is_array( $atts ) ? $atts : array(), self::SHORTCODE ) : array( 'limit' => 100 );
		$limit = isset( $atts['limit'] ) && is_scalar( $atts['limit'] ) && preg_match( '/^[0-9]+$/', (string) $atts['limit'] ) ? min( 200, max( 1, (int) $atts['limit'] ) ) : 100;
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

		Assets::enqueue_feed();
		if ( $user_id <= 0 ) {
			$redirect = '';
			$page_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
			if ( $page_id > 0 && function_exists( 'get_permalink' ) ) {
				$redirect = (string) get_permalink( $page_id );
			} elseif ( function_exists( 'home_url' ) ) {
				$redirect = (string) home_url( '/' );
			}
			$login_url = function_exists( 'wp_login_url' ) ? wp_login_url( $redirect ) : '';
			return FeedRenderer::template( 'saved-posts', array( 'logged_in' => false, 'login_url' => $login_url, 'items' => array() ) );
		}

		$result = SaveService::saved_posts_for_user( $user_id, $limit );
		$items  = ! empty( $result['ok'] ) && isset( $result['data']['items'] ) && is_array( $result['data']['items'] ) ? $result['data']['items'] : array();
		return FeedRenderer::template( 'saved-posts', array( 'logged_in' => true, 'login_url' => '', 'items' => $items ) );
	}
}
