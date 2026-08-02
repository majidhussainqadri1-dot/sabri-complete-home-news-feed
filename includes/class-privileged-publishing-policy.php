<?php
/**
 * Founder and administrator publishing policy.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps privileged publishing deterministic across Composer and core WordPress.
 */
final class PrivilegedPublishingPolicy {
	/**
	 * Review states that must never be silently reopened by a publish sync.
	 *
	 * @return array<int,string>
	 */
	public static function protected_review_states() {
		return array( 'removed', 'rejected', 'archived', 'limited' );
	}

	/** Register runtime hooks. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'wp_insert_post_data', array( __CLASS__, 'normalize_core_pending_submission' ), 20, 4 );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'save_post_post', array( __CLASS__, 'sync_published_review_state' ), 30, 3 );
		}
	}

	/**
	 * Convert a core-editor pending submission authored by a privileged publisher
	 * to publish. Drafts, autosaves, revisions, privacy-held submissions, and
	 * non-post content are not changed here.
	 *
	 * The author check is deliberate: an Administrator reviewing an unverified
	 * Doctor's pending post must not accidentally publish it merely because the
	 * current actor is privileged. Administrators may still deliberately select
	 * WordPress `publish`; this normalizer only corrects privileged authors' own
	 * pending submissions.
	 *
	 * @param array<string,mixed> $data Sanitized post data.
	 * @param array<string,mixed> $postarr Submitted post array.
	 * @param array<string,mixed> $unsanitized_postarr Raw submitted array.
	 * @param bool                $update Whether this is an update.
	 * @return array<string,mixed>
	 */
	public static function normalize_core_pending_submission( $data, $postarr, $unsanitized_postarr = array(), $update = false ) {
		unset( $update );
		if ( ! is_array( $data ) || 'post' !== ( isset( $data['post_type'] ) ? (string) $data['post_type'] : 'post' ) ) {
			return $data;
		}
		if ( 'pending' !== ( isset( $data['post_status'] ) ? (string) $data['post_status'] : '' ) ) {
			return $data;
		}
		if ( ! empty( $unsanitized_postarr['sabri_privacy_review_required'] ) ) {
			return $data;
		}

		$author_id = 0;
		foreach ( array( $data, $postarr, $unsanitized_postarr ) as $source ) {
			if ( is_array( $source ) && ! empty( $source['post_author'] ) ) {
				$author_id = (int) $source['post_author'];
				break;
			}
		}
		if ( $author_id <= 0 && function_exists( 'get_current_user_id' ) ) {
			$author_id = (int) get_current_user_id();
		}
		if ( ! ComposerPermissions::user_is_privileged_publisher( $author_id ) ) {
			return $data;
		}

		$data['post_status'] = 'publish';
		return $data;
	}

	/**
	 * Ensure a published privileged-author post is publicly approved.
	 * Explicit moderation restrictions remain protected.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $post Post object.
	 * @param bool  $update Whether this is an update.
	 * @return void
	 */
	public static function sync_published_review_state( $post_id, $post, $update ) {
		unset( $update );
		$post_id = (int) $post_id;
		$status  = is_object( $post ) && isset( $post->post_status ) ? (string) $post->post_status : ( function_exists( 'get_post_status' ) ? (string) get_post_status( $post_id ) : '' );
		$author  = is_object( $post ) && isset( $post->post_author ) ? (int) $post->post_author : ( function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0 );

		if ( $post_id <= 0 || 'publish' !== $status || ! ComposerPermissions::subject_can_publish_immediately( $author ) || ! function_exists( 'update_post_meta' ) ) {
			return;
		}

		$current = PostMetadata::review_state( $post_id );
		if ( in_array( $current, self::protected_review_states(), true ) || 'approved' === $current ) {
			return;
		}

		update_post_meta( $post_id, PostMetadata::META_REVIEW_STATE, 'approved' );
		FeedQuery::invalidate_cache();
		AuditLog::record(
			'privileged_post_approved',
			array(
				'post_id'        => $post_id,
				'author_id'      => $author,
				'previous_state' => $current,
			)
		);
	}
}
