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
 * Removes early followers-only query mutation.
 *
 * Followers authorization remains enforced by the metadata filter and by the
 * resolved-result guard in PublicQueryGuard. This preserves Page and missing
 * route semantics for both guests and authenticated users.
 */
final class FollowersQueryGuard {
	/** Remove the direct followers pre_get_posts callback. */
	public static function register() {
		if ( function_exists( 'remove_action' ) ) {
			remove_action( 'pre_get_posts', array( FollowersVisibility::class, 'extend_post_queries' ), 20 );
		}
	}
}
