<?php
/**
 * Safe restoration workflow for legacy Founder and Administrator posts.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Previews and selectively restores privileged posts that were moved to review.
 */
final class LegacyFounderPostMigration {
	const LAST_REPORT_OPTION = 'sabri_feed_last_legacy_founder_restore_report';
	const MAX_BATCH          = 100;

	/**
	 * Preview bounded restoration candidates.
	 *
	 * @param int $limit Maximum candidates.
	 * @return array<string,mixed>
	 */
	public static function preview( $limit = self::MAX_BATCH ) {
		$limit      = max( 1, min( self::MAX_BATCH, (int) $limit ) );
		$author_ids = self::privileged_author_ids();
		$candidates = array();

		if ( ! empty( $author_ids ) && class_exists( 'WP_Query' ) ) {
			$query = new \WP_Query(
				array(
					'post_type'           => 'post',
					'post_status'         => array( 'pending' ),
					'author__in'          => $author_ids,
					'posts_per_page'      => $limit,
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'orderby'             => 'modified',
					'order'               => 'DESC',
				)
			);

			foreach ( (array) $query->posts as $post ) {
				$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
				if ( self::is_candidate( $post_id ) ) {
					$candidates[] = self::candidate_summary( $post_id );
				}
			}
		}

		return array(
			'candidate_count' => count( $candidates ),
			'candidates'      => $candidates,
			'author_ids'      => $author_ids,
			'destructive'     => false,
			'automatic'       => false,
			'max_batch'       => self::MAX_BATCH,
		);
	}

	/**
	 * Restore only explicitly selected valid candidates.
	 *
	 * @param array<int,mixed> $post_ids Post IDs.
	 * @param int              $actor_id Acting user ID.
	 * @return array<string,mixed>
	 */
	public static function restore_selected( array $post_ids, $actor_id = 0 ) {
		$actor_id = $actor_id ? (int) $actor_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		if ( ! self::actor_can_restore( $actor_id ) ) {
			return array(
				'success'  => false,
				'error'    => 'permission_denied',
				'restored' => array(),
				'skipped'  => array(),
			);
		}

		$post_ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) ), 0, self::MAX_BATCH );
		if ( empty( $post_ids ) ) {
			return array(
				'success'  => false,
				'error'    => 'no_posts_selected',
				'restored' => array(),
				'skipped'  => array(),
			);
		}

		Snapshot::capture_before_mutation( 'legacy_founder_post_restore' );
		$restored = array();
		$skipped  = array();

		foreach ( $post_ids as $post_id ) {
			if ( ! self::is_candidate( $post_id ) ) {
				$skipped[ $post_id ] = 'not_a_valid_candidate';
				continue;
			}

			$result = function_exists( 'wp_update_post' ) ? wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ), true ) : 0;
			if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) || (int) $result <= 0 ) {
				$skipped[ $post_id ] = 'publish_failed';
				continue;
			}

			if ( function_exists( 'update_post_meta' ) ) {
				update_post_meta( $post_id, PostMetadata::META_REVIEW_STATE, 'approved' );
			}
			$restored[] = $post_id;
			AuditLog::record(
				'legacy_founder_post_restored',
				array(
					'post_id'  => $post_id,
					'actor_id' => $actor_id,
				),
				'post',
				$post_id
			);
		}

		if ( ! empty( $restored ) ) {
			FeedQuery::invalidate_cache();
		}

		$report = array(
			'success'     => empty( $skipped ),
			'partial'     => ! empty( $restored ) && ! empty( $skipped ),
			'actor_id'    => $actor_id,
			'restored'    => $restored,
			'skipped'     => $skipped,
			'restored_at' => gmdate( 'Y-m-d H:i:s' ),
			'destructive' => false,
			'automatic'   => false,
		);

		if ( function_exists( 'update_option' ) ) {
			update_option( self::LAST_REPORT_OPTION, $report, false );
		}

		return $report;
	}

	/**
	 * Whether one post is a safe manual restoration candidate.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_candidate( $post_id ) {
		$post_id = (int) $post_id;
		$post    = $post_id > 0 && function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		if ( ! is_object( $post ) || 'post' !== ( isset( $post->post_type ) ? (string) $post->post_type : '' ) || 'pending' !== ( isset( $post->post_status ) ? (string) $post->post_status : '' ) ) {
			return false;
		}

		$author_id = isset( $post->post_author ) ? (int) $post->post_author : 0;
		if ( ! ComposerPermissions::user_is_privileged_publisher( $author_id ) ) {
			return false;
		}

		$state = PostMetadata::review_state( $post_id );
		return '' === $state || 'pending' === $state;
	}

	/**
	 * Privileged author IDs currently present in WordPress.
	 *
	 * @return array<int,int>
	 */
	public static function privileged_author_ids() {
		if ( ! function_exists( 'get_users' ) ) {
			return array();
		}

		$settings = Settings::get();
		$roles    = array( 'administrator' );
		if ( ! empty( $settings['capabilities']['founder_roles'] ) && is_array( $settings['capabilities']['founder_roles'] ) ) {
			$roles = array_merge( $roles, array_map( 'sanitize_key', $settings['capabilities']['founder_roles'] ) );
		}

		$users = get_users(
			array(
				'role__in' => array_values( array_unique( array_filter( $roles ) ) ),
				'fields'   => 'ID',
				'number'   => 500,
			)
		);

		return array_values( array_unique( array_filter( array_map( 'absint', (array) $users ) ) ) );
	}

	/**
	 * Whether the actor may perform a restoration.
	 *
	 * @param int $actor_id Actor ID.
	 * @return bool
	 */
	private static function actor_can_restore( $actor_id ) {
		if ( $actor_id <= 0 || ! function_exists( 'current_user_can' ) || ( function_exists( 'get_current_user_id' ) && (int) get_current_user_id() !== $actor_id ) ) {
			return false;
		}

		return current_user_can( 'manage_options' ) || current_user_can( 'sabri_feed_run_migrations' );
	}

	/**
	 * Publicly safe candidate summary.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	private static function candidate_summary( $post_id ) {
		$post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		return array(
			'id'           => (int) $post_id,
			'title'        => is_object( $post ) && isset( $post->post_title ) ? (string) $post->post_title : '',
			'author_id'    => is_object( $post ) && isset( $post->post_author ) ? (int) $post->post_author : 0,
			'status'       => is_object( $post ) && isset( $post->post_status ) ? (string) $post->post_status : '',
			'review_state' => PostMetadata::review_state( $post_id ),
			'modified'     => is_object( $post ) && isset( $post->post_modified_gmt ) ? (string) $post->post_modified_gmt : '',
		);
	}
}
