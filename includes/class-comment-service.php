<?php
/**
 * Phase 3C WordPress-native comments and replies.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements authenticated comments, bounded replies, edits, and soft deletion.
 */
final class CommentService {
	/**
	 * Create a top-level comment or reply.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Plain-text content.
	 * @param int    $parent_id Optional parent comment ID.
	 * @param string $nonce REST nonce.
	 * @param int    $user_id Optional current session user ID.
	 * @return array<string,mixed>
	 */
	public static function create( $post_id, $content, $parent_id = 0, $nonce = '', $user_id = 0 ) {
		if ( ! Phase3FeatureSettings::enabled( 'comments_enabled' ) ) {
			return InteractionResult::error( 'comments_disabled', 'Comments are currently unavailable.', array(), 503 );
		}

		$authorized = InteractionPermissions::authorize_post_write( $post_id, $nonce, $user_id );
		if ( empty( $authorized['ok'] ) ) {
			return $authorized;
		}

		$user_id = (int) $authorized['data']['user_id'];
		$post_id = (int) $authorized['data']['post_id'];
		if ( function_exists( 'comments_open' ) && ! comments_open( $post_id ) ) {
			return InteractionResult::error( 'comments_closed', 'Comments are closed for this post.', array(), 403 );
		}

		$validated = self::validate_content( $post_id, $content );
		if ( empty( $validated['ok'] ) ) {
			return $validated;
		}
		$content = $validated['data']['content'];

		$parent_id = self::positive_or_zero_id( $parent_id );
		if ( $parent_id > 0 ) {
			$parent = self::comment( $parent_id );
			if ( ! $parent || (int) $parent->comment_post_ID !== $post_id || CommentPolicy::COMMENT_TYPE !== (string) $parent->comment_type || self::is_deleted( $parent_id ) ) {
				return InteractionResult::error( 'invalid_comment_parent', 'The selected reply target is unavailable.', array(), 400 );
			}
			if ( self::depth( $parent_id ) + 1 > CommentPolicy::max_reply_depth() ) {
				return InteractionResult::error( 'reply_depth_exceeded', 'The maximum reply depth has been reached.', array(), 400 );
			}
		}

		$limit = InteractionRateLimiter::attempt( 'comments', $user_id, $post_id );
		if ( empty( $limit['ok'] ) ) {
			return $limit;
		}

		$user = function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : false;
		if ( ! $user ) {
			return InteractionResult::error( 'comment_user_unavailable', 'The commenting account is unavailable.', array(), 400 );
		}

		$now_local = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
		$now_gmt   = function_exists( 'current_time' ) ? current_time( 'mysql', true ) : gmdate( 'Y-m-d H:i:s' );
		$data      = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => isset( $user->display_name ) ? sanitize_text_field( $user->display_name ) : 'Sabri member',
			'comment_author_email' => isset( $user->user_email ) && function_exists( 'sanitize_email' ) ? sanitize_email( $user->user_email ) : ( isset( $user->user_email ) ? sanitize_text_field( $user->user_email ) : '' ),
			'comment_author_url'   => '',
			'comment_content'      => $content,
			'comment_type'         => CommentPolicy::COMMENT_TYPE,
			'comment_parent'       => $parent_id,
			'user_id'              => $user_id,
			'comment_author_IP'    => '',
			'comment_agent'        => '',
			'comment_approved'     => CommentPolicy::new_comment_approved_value(),
			'comment_date'         => $now_local,
			'comment_date_gmt'     => $now_gmt,
		);

		if ( ! function_exists( 'wp_insert_comment' ) ) {
			return InteractionResult::error( 'comment_storage_unavailable', 'Comment storage is temporarily unavailable.', array(), 503 );
		}

		$comment_id = wp_insert_comment( $data );
		if ( ! is_numeric( $comment_id ) || (int) $comment_id <= 0 ) {
			return InteractionResult::error( 'comment_create_failed', 'The comment could not be saved.', array(), 500 );
		}
		$comment_id = (int) $comment_id;

		if ( function_exists( 'update_comment_meta' ) ) {
			update_comment_meta( $comment_id, CommentPolicy::META_PRIVACY_SCAN, 'safe' );
		}
		AuditLog::record( 'comment_created', array( 'post_id' => $post_id, 'comment_id' => $comment_id, 'parent_id' => $parent_id ) );

		$comment = self::comment( $comment_id );
		$status  = $comment && self::is_approved( $comment ) ? 'approved' : 'pending';
		return InteractionResult::success(
			'comment_created',
			array(
				'comment' => $comment ? self::serialize( $comment, $user_id ) : array(),
				'status'  => $status,
				'thread'  => self::thread_data( $post_id, $user_id ),
			),
			'approved' === $status ? 'Comment posted.' : 'Comment submitted for review.',
			201
		);
	}

	/**
	 * Edit a comment inside the owner window or as a moderator.
	 *
	 * @param int    $comment_id Comment ID.
	 * @param string $content Plain-text content.
	 * @param string $nonce REST nonce.
	 * @param int    $user_id Optional current session user ID.
	 * @return array<string,mixed>
	 */
	public static function update( $comment_id, $content, $nonce = '', $user_id = 0 ) {
		$authorized = self::authorize_comment_mutation( $comment_id, $nonce, $user_id, 'edit' );
		if ( empty( $authorized['ok'] ) ) {
			return $authorized;
		}

		$comment   = $authorized['data']['comment'];
		$user_id   = (int) $authorized['data']['user_id'];
		$comment_id = (int) $comment->comment_ID;
		$post_id   = (int) $comment->comment_post_ID;
		if ( self::is_deleted( $comment_id ) ) {
			return InteractionResult::error( 'comment_deleted', 'A removed comment cannot be edited.', array(), 409 );
		}

		$validated = self::validate_content( $post_id, $content );
		if ( empty( $validated['ok'] ) ) {
			return $validated;
		}

		if ( ! function_exists( 'wp_update_comment' ) ) {
			return InteractionResult::error( 'comment_storage_unavailable', 'Comment storage is temporarily unavailable.', array(), 503 );
		}

		$result = wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => $validated['data']['content'],
			),
			true
		);
		if ( is_wp_error( $result ) || false === $result || 0 === $result ) {
			return InteractionResult::error( 'comment_update_failed', 'The comment could not be updated.', array(), 500 );
		}

		if ( function_exists( 'update_comment_meta' ) ) {
			update_comment_meta( $comment_id, CommentPolicy::META_EDITED_AT, gmdate( 'Y-m-d H:i:s' ) );
			update_comment_meta( $comment_id, CommentPolicy::META_PRIVACY_SCAN, 'safe' );
		}
		AuditLog::record( 'comment_updated', array( 'post_id' => $post_id, 'comment_id' => $comment_id ) );

		return InteractionResult::success(
			'comment_updated',
			array( 'thread' => self::thread_data( $post_id, $user_id ) ),
			'Comment updated.',
			200
		);
	}

	/**
	 * Soft-delete a comment while retaining thread structure and accountability.
	 *
	 * @param int    $comment_id Comment ID.
	 * @param string $nonce REST nonce.
	 * @param int    $user_id Optional current session user ID.
	 * @return array<string,mixed>
	 */
	public static function delete( $comment_id, $nonce = '', $user_id = 0 ) {
		$authorized = self::authorize_comment_mutation( $comment_id, $nonce, $user_id, 'delete' );
		if ( empty( $authorized['ok'] ) ) {
			return $authorized;
		}

		$comment    = $authorized['data']['comment'];
		$user_id    = (int) $authorized['data']['user_id'];
		$comment_id = (int) $comment->comment_ID;
		$post_id    = (int) $comment->comment_post_ID;
		if ( self::is_deleted( $comment_id ) ) {
			return InteractionResult::success( 'comment_removed', array( 'thread' => self::thread_data( $post_id, $user_id ) ), 'Comment removed.', 200 );
		}

		if ( ! function_exists( 'wp_update_comment' ) ) {
			return InteractionResult::error( 'comment_storage_unavailable', 'Comment storage is temporarily unavailable.', array(), 503 );
		}

		$result = wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => '[Comment removed]',
			),
			true
		);
		if ( is_wp_error( $result ) || false === $result || 0 === $result ) {
			return InteractionResult::error( 'comment_remove_failed', 'The comment could not be removed.', array(), 500 );
		}

		if ( function_exists( 'update_comment_meta' ) ) {
			update_comment_meta( $comment_id, CommentPolicy::META_DELETED, 1 );
			update_comment_meta( $comment_id, CommentPolicy::META_EDITED_AT, gmdate( 'Y-m-d H:i:s' ) );
		}
		AuditLog::record( 'comment_removed', array( 'post_id' => $post_id, 'comment_id' => $comment_id ) );

		return InteractionResult::success(
			'comment_removed',
			array( 'thread' => self::thread_data( $post_id, $user_id ) ),
			'Comment removed.',
			200
		);
	}

	/**
	 * Return a visibility-safe thread result.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id Optional current session user ID.
	 * @return array<string,mixed>
	 */
	public static function thread( $post_id, $user_id = 0 ) {
		$post_id = self::positive_id( $post_id );
		if ( $post_id <= 0 || ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
			return InteractionResult::error( 'post_unavailable', 'The requested post is unavailable.', array(), 404 );
		}

		$user_id = $user_id ? InteractionPermissions::authenticated_user_id( $user_id ) : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		return InteractionResult::success( 'comments_loaded', self::thread_data( $post_id, $user_id ), 'Comments loaded.', 200 );
	}

	/**
	 * Approved comment count for feed and single-post links.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function approved_count( $post_id ) {
		$post_id = self::positive_id( $post_id );
		if ( $post_id <= 0 || ! function_exists( 'get_comments' ) ) {
			return 0;
		}
		$comments = get_comments(
			array(
				'post_id' => $post_id,
				'type'    => CommentPolicy::COMMENT_TYPE,
				'status'  => 'approve',
			)
		);
		return is_array( $comments ) ? count( $comments ) : 0;
	}

	/**
	 * Build safe thread data, including only approved comments and authorized pending rows.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id Current user ID or zero.
	 * @return array<string,mixed>
	 */
	public static function thread_data( $post_id, $user_id = 0 ) {
		$comments = self::query_comments( $post_id, $user_id );
		$items    = array();
		foreach ( $comments as $comment ) {
			$items[] = self::serialize( $comment, $user_id );
		}

		return array(
			'post_id'        => (int) $post_id,
			'items'          => $items,
			'approved_count' => self::approved_count( $post_id ),
			'comments_open'  => ! function_exists( 'comments_open' ) || comments_open( $post_id ),
			'max_length'     => CommentPolicy::max_length(),
			'max_reply_depth'=> CommentPolicy::max_reply_depth(),
			'edit_minutes'   => CommentPolicy::edit_minutes(),
		);
	}

	/**
	 * Serialize without email, IP, agent, or private moderation data.
	 *
	 * @param mixed $comment Comment object.
	 * @param int   $user_id Current user ID.
	 * @return array<string,mixed>
	 */
	public static function serialize( $comment, $user_id = 0 ) {
		$comment_id = isset( $comment->comment_ID ) ? (int) $comment->comment_ID : 0;
		$author_id  = isset( $comment->user_id ) ? (int) $comment->user_id : 0;
		$deleted    = self::is_deleted( $comment_id );
		$approved   = self::is_approved( $comment );
		$depth      = self::depth( $comment_id );
		$user_id    = (int) $user_id;

		return array(
			'id'          => $comment_id,
			'post_id'     => isset( $comment->comment_post_ID ) ? (int) $comment->comment_post_ID : 0,
			'parent_id'   => isset( $comment->comment_parent ) ? (int) $comment->comment_parent : 0,
			'user_id'     => $author_id,
			'author_name' => isset( $comment->comment_author ) ? sanitize_text_field( $comment->comment_author ) : 'Sabri member',
			'avatar'      => function_exists( 'get_avatar' ) ? get_avatar( $author_id, 40, '', '', array( 'class' => 'sabri-hnf-comment__avatar-image' ) ) : '',
			'content'     => $deleted ? __( 'Comment removed.', 'sabri-complete-home-news-feed' ) : ( isset( $comment->comment_content ) ? sanitize_textarea_field( $comment->comment_content ) : '' ),
			'date_gmt'    => isset( $comment->comment_date_gmt ) ? sanitize_text_field( $comment->comment_date_gmt ) : '',
			'status'      => $approved ? 'approved' : 'pending',
			'deleted'     => $deleted,
			'edited'      => self::is_edited( $comment_id ),
			'depth'       => $depth,
			'can_reply'   => ! $deleted && $user_id > 0 && $depth < CommentPolicy::max_reply_depth(),
			'can_edit'    => ! $deleted && self::can_edit( $comment, $user_id ),
			'can_delete'  => ! $deleted && self::can_delete( $comment, $user_id ),
		);
	}

	/**
	 * Authorize edit/delete against the current session and nonce.
	 *
	 * @param int    $comment_id Comment ID.
	 * @param string $nonce Nonce.
	 * @param int    $user_id Optional session user ID.
	 * @param string $action Edit or delete.
	 * @return array<string,mixed>
	 */
	private static function authorize_comment_mutation( $comment_id, $nonce, $user_id, $action ) {
		if ( ! Phase3FeatureSettings::enabled( 'comments_enabled' ) ) {
			return InteractionResult::error( 'comments_disabled', 'Comments are currently unavailable.', array(), 503 );
		}
		$user_id = InteractionPermissions::authenticated_user_id( $user_id );
		if ( $user_id <= 0 ) {
			return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 );
		}
		if ( ! InteractionPermissions::nonce_valid( $nonce ) ) {
			return InteractionResult::error( 'invalid_nonce', 'The security token is missing or invalid.', array(), 403 );
		}

		$comment = self::comment( $comment_id );
		if ( ! $comment || CommentPolicy::COMMENT_TYPE !== (string) $comment->comment_type || ! PostMetadata::user_can_view( (int) $comment->comment_post_ID, $user_id ) ) {
			return InteractionResult::error( 'comment_unavailable', 'The requested comment is unavailable.', array(), 404 );
		}

		$allowed = 'edit' === $action ? self::can_edit( $comment, $user_id ) : self::can_delete( $comment, $user_id );
		if ( ! $allowed ) {
			return InteractionResult::error( 'comment_permission_denied', 'You cannot modify this comment.', array(), 403 );
		}

		return InteractionResult::success( 'comment_authorized', array( 'comment' => $comment, 'user_id' => $user_id ), 'Authorized.', 200 );
	}

	/**
	 * Validate content, including clinical-case privacy protection.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Content.
	 * @return array<string,mixed>
	 */
	private static function validate_content( $post_id, $content ) {
		$content = function_exists( 'wp_unslash' ) ? wp_unslash( $content ) : $content;
		$content = function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $content ) : trim( strip_tags( (string) $content ) );
		$content = trim( preg_replace( '/\R{3,}/', "\n\n", (string) $content ) );
		$length  = function_exists( 'mb_strlen' ) ? mb_strlen( $content ) : strlen( $content );

		if ( $length < 2 ) {
			return InteractionResult::error( 'comment_too_short', 'Please enter a meaningful comment.', array(), 400 );
		}
		if ( $length > CommentPolicy::max_length() ) {
			return InteractionResult::error( 'comment_too_long', 'The comment exceeds the maximum length.', array( 'max_length' => CommentPolicy::max_length() ), 400 );
		}

		if ( CommentPolicy::clinical_privacy_scan_enabled() && 'clinical-case' === PostMetadata::feed_type( $post_id ) ) {
			$scan = CommentPrivacyScanner::scan( $content );
			if ( empty( $scan['safe'] ) ) {
				return InteractionResult::error(
					'patient_privacy_risk',
					'Remove patient-identifying information before posting this clinical comment.',
					array( 'risk_categories' => isset( $scan['risks'] ) ? $scan['risks'] : array() ),
					422
				);
			}
		}

		return InteractionResult::success( 'comment_valid', array( 'content' => $content ), 'Valid.', 200 );
	}

	/**
	 * Query approved comments plus only authorized pending comments.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id Current user ID.
	 * @return array<int,mixed>
	 */
	private static function query_comments( $post_id, $user_id ) {
		if ( ! function_exists( 'get_comments' ) ) {
			return array();
		}

		$args = array(
			'post_id' => (int) $post_id,
			'type'    => CommentPolicy::COMMENT_TYPE,
			'status'  => 'approve',
			'orderby' => 'comment_date_gmt',
			'order'   => 'ASC',
		);
		$comments = get_comments( $args );
		$comments = is_array( $comments ) ? $comments : array();

		if ( $user_id > 0 ) {
			$pending_args = $args;
			$pending_args['status'] = 'hold';
			if ( ! CommentPolicy::current_user_can_moderate() ) {
				$pending_args['user_id'] = (int) $user_id;
			}
			$pending = get_comments( $pending_args );
			foreach ( is_array( $pending ) ? $pending : array() as $comment ) {
				$comments[] = $comment;
			}
		}

		$unique = array();
		foreach ( $comments as $comment ) {
			if ( is_object( $comment ) && isset( $comment->comment_ID ) ) {
				$unique[ (int) $comment->comment_ID ] = $comment;
			}
		}
		usort(
			$unique,
			static function ( $a, $b ) {
				$left  = isset( $a->comment_date_gmt ) ? strtotime( $a->comment_date_gmt . ' GMT' ) : 0;
				$right = isset( $b->comment_date_gmt ) ? strtotime( $b->comment_date_gmt . ' GMT' ) : 0;
				return $left === $right ? (int) $a->comment_ID <=> (int) $b->comment_ID : $left <=> $right;
			}
		);
		return array_values( $unique );
	}

	/**
	 * Whether a user may edit the comment.
	 *
	 * @param mixed $comment Comment object.
	 * @param int   $user_id Current user ID.
	 * @return bool
	 */
	private static function can_edit( $comment, $user_id ) {
		if ( CommentPolicy::current_user_can_moderate() ) {
			return true;
		}
		if ( $user_id <= 0 || (int) $comment->user_id !== (int) $user_id ) {
			return false;
		}
		$created = isset( $comment->comment_date_gmt ) ? strtotime( $comment->comment_date_gmt . ' GMT' ) : 0;
		return $created > 0 && self::now_timestamp() <= $created + ( CommentPolicy::edit_minutes() * MINUTE_IN_SECONDS );
	}

	/**
	 * Whether a user may soft-delete the comment.
	 *
	 * @param mixed $comment Comment object.
	 * @param int   $user_id Current user ID.
	 * @return bool
	 */
	private static function can_delete( $comment, $user_id ) {
		return CommentPolicy::current_user_can_moderate() || ( $user_id > 0 && (int) $comment->user_id === (int) $user_id );
	}

	/**
	 * Compute parent depth with cycle protection.
	 *
	 * @param int $comment_id Comment ID.
	 * @return int
	 */
	private static function depth( $comment_id ) {
		$depth   = 0;
		$current = self::comment( $comment_id );
		$seen    = array();
		while ( $current && ! empty( $current->comment_parent ) && $depth <= CommentPolicy::max_reply_depth() + 2 ) {
			$parent_id = (int) $current->comment_parent;
			if ( isset( $seen[ $parent_id ] ) ) {
				break;
			}
			$seen[ $parent_id ] = true;
			$depth++;
			$current = self::comment( $parent_id );
		}
		return $depth;
	}

	/**
	 * Get one native comment.
	 *
	 * @param int $comment_id Comment ID.
	 * @return mixed
	 */
	private static function comment( $comment_id ) {
		$comment_id = self::positive_id( $comment_id );
		return $comment_id > 0 && function_exists( 'get_comment' ) ? get_comment( $comment_id ) : null;
	}

	/**
	 * Native approval check.
	 *
	 * @param mixed $comment Comment object.
	 * @return bool
	 */
	private static function is_approved( $comment ) {
		return isset( $comment->comment_approved ) && in_array( (string) $comment->comment_approved, array( '1', 'approve', 'approved' ), true );
	}

	/**
	 * Soft-deleted meta check.
	 *
	 * @param int $comment_id Comment ID.
	 * @return bool
	 */
	private static function is_deleted( $comment_id ) {
		return function_exists( 'get_comment_meta' ) && 1 === (int) get_comment_meta( $comment_id, CommentPolicy::META_DELETED, true );
	}

	/**
	 * Edited meta check.
	 *
	 * @param int $comment_id Comment ID.
	 * @return bool
	 */
	private static function is_edited( $comment_id ) {
		return function_exists( 'get_comment_meta' ) && '' !== (string) get_comment_meta( $comment_id, CommentPolicy::META_EDITED_AT, true );
	}

	/**
	 * Current UTC timestamp, filterable for deterministic tests.
	 *
	 * @return int
	 */
	private static function now_timestamp() {
		$now = time();
		if ( function_exists( 'apply_filters' ) ) {
			$now = apply_filters( 'sabri_feed_comment_now', $now );
		}
		return (int) $now;
	}

	/**
	 * Strict positive ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private static function positive_id( $value ) {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/', $value ) ) ) {
			return 0;
		}
		$value = (int) $value;
		return $value > 0 ? $value : 0;
	}

	/**
	 * Strict non-negative ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private static function positive_or_zero_id( $value ) {
		if ( 0 === $value || '0' === $value || null === $value || '' === $value ) {
			return 0;
		}
		return self::positive_id( $value );
	}
}
