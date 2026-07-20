<?php
/**
 * Followers-only query routing guard.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps followers-only query expansion on unambiguous core-post queries.
 */
final class FollowersQueryGuard {
	/** Replace the direct followers pre_get_posts callback with a strict wrapper. */
	public static function register() {
		if ( function_exists( 'remove_action' ) ) {
			remove_action( 'pre_get_posts', array( FollowersVisibility::class, 'extend_post_queries' ), 20 );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'pre_get_posts', array( __CLASS__, 'extend_post_queries' ), 20 );
		}
	}

	/**
	 * Delegate only after the shared routing guard confirms a post-only query.
	 *
	 * @param mixed $query WP_Query-like object.
	 * @return void
	 */
	public static function extend_post_queries( $query ) {
		if ( ! PublicQueryGuard::targets_core_posts( $query ) ) {
			return;
		}
		FollowersVisibility::extend_post_queries( $query );
	}
}
