<?php
/**
 * File 17 relationship bridge for Home/Feed safety and Following discovery.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File 17 remains canonical for platform relationships/blocks. File 21 only
 * consumes its public in-process contract and retains its historical local
 * follow store solely as a fail-soft compatibility fallback when File 17 is
 * not installed/loaded.
 */
final class NetworkRelationshipBridge {
	const MAX_FOLLOWING = 200;

	/** Mark File 21 Feed queries so post-result block filtering is scoped. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'sabri_hnf_feed_query_args', array( __CLASS__, 'mark_feed_query' ), 5, 4 );
			add_filter( 'posts_results', array( __CLASS__, 'filter_feed_posts' ), 20, 2 );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'sn_network_follow_changed', array( __CLASS__, 'invalidate_on_network_change' ), 10, 2 );
			add_action( 'sn_network_block_changed', array( __CLASS__, 'invalidate_on_network_change' ), 10, 2 );
		}
	}

	/** File 17 native relationship runtime is available. */
	public static function native_available() {
		return class_exists( 'SN_Relationships' ) && is_callable( array( 'SN_Relationships', 'state' ) );
	}

	/** Scope an otherwise ordinary WP_Query to File 21 Feed post filtering. */
	public static function mark_feed_query( $args, $mode, $user_id, $settings ) {
		unset( $mode, $settings );
		$args = is_array( $args ) ? $args : array();
		$args['sabri_hnf_feed_query'] = 1;
		$args['sabri_hnf_feed_viewer'] = max( 0, (int) $user_id );
		return $args;
	}

	/** Honor File 17 block/suspension truth on Feed candidate results. */
	public static function filter_feed_posts( $posts, $query ) {
		if ( ! is_array( $posts ) || ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! $query->get( 'sabri_hnf_feed_query' ) ) {
			return $posts;
		}
		$viewer_id = (int) $query->get( 'sabri_hnf_feed_viewer' );
		if ( $viewer_id <= 0 || ! self::native_available() ) {
			return $posts;
		}
		return array_values(
			array_filter(
				$posts,
				static function ( $post ) use ( $viewer_id ) {
					$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : 0;
					$author_id = $post_id > 0 && function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0;
					return self::author_allowed( $viewer_id, $author_id );
				}
			)
		);
	}

	/** Whether File 17 allows this viewer/author pair to remain visible in Feed relationship context. */
	public static function author_allowed( $viewer_id, $author_id ) {
		$viewer_id = max( 0, (int) $viewer_id );
		$author_id = max( 0, (int) $author_id );
		if ( $viewer_id <= 0 || $author_id <= 0 || $viewer_id === $author_id ) {
			return true;
		}
		$allowed = true;
		if ( self::native_available() ) {
			$state = call_user_func( array( 'SN_Relationships', 'state' ), $viewer_id, $author_id );
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $state ) ) {
				$allowed = false;
			} elseif ( is_array( $state ) && ! empty( $state['blocked'] ) ) {
				$allowed = false;
			}
		}
		return function_exists( 'apply_filters' ) ? (bool) apply_filters( 'sabri_hnf_network_author_allowed', $allowed, $viewer_id, $author_id ) : $allowed;
	}

	/** Resolve current Following IDs from File 17 first; local history is fallback only. */
	public static function following_user_ids( $user_id, $limit = self::MAX_FOLLOWING ) {
		$user_id = max( 0, (int) $user_id );
		$limit = min( self::MAX_FOLLOWING, max( 1, (int) $limit ) );
		if ( $user_id <= 0 ) {
			return array();
		}
		if ( self::native_available() && is_callable( array( 'SN_Relationships', 'lists' ) ) ) {
			$ids = array();
			$cursor = '';
			$pages = 0;
			while ( count( $ids ) < $limit && $pages < 4 ) {
				$page_limit = min( 50, $limit - count( $ids ) );
				$result = call_user_func( array( 'SN_Relationships', 'lists' ), $user_id, 'following', $page_limit, $cursor );
				/* When File 17 is present it is authoritative: failures must not fall back to stale File 21 relationship data. */
				if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) || ! is_array( $result ) ) {
					return array();
				}
				$items = isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
				foreach ( $items as $item ) {
					$id = isset( $item['followed_id'] ) ? absint( $item['followed_id'] ) : 0;
					if ( $id > 0 && self::author_allowed( $user_id, $id ) ) {
						$ids[] = $id;
					}
				}
				$ids = array_values( array_unique( $ids ) );
				$next = isset( $result['next_cursor'] ) && is_scalar( $result['next_cursor'] ) ? (string) $result['next_cursor'] : '';
				if ( '' === $next || $next === $cursor ) {
					break;
				}
				$cursor = $next;
				$pages++;
			}
			return array_slice( $ids, 0, $limit );
		}
		if ( Phase3FeatureSettings::enabled( 'follows_enabled' ) ) {
			return InteractionQueryRepository::following_user_ids( $user_id, FollowService::TARGET_TYPE, $limit );
		}
		return array();
	}

	/** File 17-backed follow state projected into File 21's existing action-bar shape. */
	public static function summary( $target_user_id, $viewer_user_id = 0 ) {
		$target_user_id = max( 0, (int) $target_user_id );
		$viewer_user_id = $viewer_user_id ? max( 0, (int) $viewer_user_id ) : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		if ( ! self::native_available() || $target_user_id <= 0 || $viewer_user_id <= 0 || $viewer_user_id === $target_user_id ) {
			return null;
		}
		$state = call_user_func( array( 'SN_Relationships', 'state' ), $viewer_user_id, $target_user_id );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $state ) ) {
			return array( 'target_user_id' => $target_user_id, 'following' => false, 'count_visible' => false, 'follower_count' => 0, 'profile_url' => ProfileLinkResolver::url( $target_user_id ), 'blocked' => true );
		}
		if ( ! is_array( $state ) ) {
			return null;
		}
		$follow_state = isset( $state['follow']['state'] ) ? sanitize_key( $state['follow']['state'] ) : 'none';
		return array(
			'target_user_id' => $target_user_id,
			'following'      => in_array( $follow_state, array( 'active', 'pending' ), true ),
			'count_visible'  => false,
			'follower_count' => 0,
			'profile_url'    => ProfileLinkResolver::url( $target_user_id ),
			'blocked'        => ! empty( $state['blocked'] ),
			'native_version' => isset( $state['follow']['version'] ) ? (int) $state['follow']['version'] : 0,
		);
	}

	/** Execute follow through File 17 when installed. */
	public static function follow( $follower_id, $target_user_id ) {
		if ( ! self::native_available() || ! is_callable( array( 'SN_Relationships', 'follow' ) ) ) {
			return null;
		}
		return call_user_func( array( 'SN_Relationships', 'follow' ), (int) $follower_id, (int) $target_user_id );
	}

	/** Execute unfollow through File 17 using its optimistic-version contract. */
	public static function unfollow( $follower_id, $target_user_id ) {
		if ( ! self::native_available() || ! is_callable( array( 'SN_Relationships', 'unfollow' ) ) ) {
			return null;
		}
		$state = call_user_func( array( 'SN_Relationships', 'state' ), (int) $follower_id, (int) $target_user_id );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $state ) ) {
			return $state;
		}
		$version = is_array( $state ) && isset( $state['follow']['version'] ) ? (int) $state['follow']['version'] : 0;
		return call_user_func( array( 'SN_Relationships', 'unfollow' ), (int) $follower_id, (int) $target_user_id, $version );
	}

	/** Canonical relationship changes invalidate all File 21 Feed caches. */
	public static function invalidate_on_network_change() {
		FeedQuery::invalidate_cache();
	}
}
