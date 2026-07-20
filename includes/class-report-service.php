<?php
/**
 * Phase 3E confidential reporting and moderation service.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates bounded reports and exposes confidential moderator-only workflows.
 */
final class ReportService {
	/**
	 * Submit a confidential report.
	 *
	 * @param string $object_type Post or comment.
	 * @param int    $object_id Object ID.
	 * @param string $reason Allow-listed reason.
	 * @param string $reporter_note Optional confidential reporter note.
	 * @param string $nonce REST nonce.
	 * @param int    $user_id Optional current session user ID.
	 * @return array<string,mixed>
	 */
	public static function create( $object_type, $object_id, $reason, $reporter_note = '', $nonce = '', $user_id = 0 ) {
		if ( ! Phase3FeatureSettings::enabled( 'reports_enabled' ) ) {
			return InteractionResult::error( 'reports_disabled', 'Reporting is currently unavailable.', array(), 503 );
		}

		$user_id = InteractionPermissions::authenticated_user_id( $user_id );
		if ( $user_id <= 0 ) {
			return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 );
		}
		if ( ! InteractionPermissions::nonce_valid( $nonce ) ) {
			return InteractionResult::error( 'invalid_nonce', 'The security token is missing or invalid.', array(), 403 );
		}

		$object_type = self::clean_key( $object_type );
		$object_id   = self::positive_id( $object_id );
		$reason      = self::clean_key( $reason );
		if ( ! ReportPolicy::object_type_allowed( $object_type ) || $object_id <= 0 ) {
			return InteractionResult::error( 'invalid_report_object', 'The selected content cannot be reported.', array(), 400 );
		}
		if ( ! ReportPolicy::reason_allowed( $reason ) ) {
			return InteractionResult::error( 'invalid_report_reason', 'Select a valid report reason.', array(), 400 );
		}

		$object = self::reportable_object( $object_type, $object_id, $user_id );
		if ( empty( $object['ok'] ) ) {
			return $object;
		}
		if ( (int) $object['data']['owner_user_id'] === $user_id ) {
			return InteractionResult::error( 'self_report_forbidden', 'You cannot report your own content.', array(), 400 );
		}

		$reporter_note = ReportPolicy::reporter_note( $reporter_note );
		if ( 'other' === $reason && self::text_length( $reporter_note ) < 10 ) {
			return InteractionResult::error( 'report_note_required', 'Describe the concern when selecting Other.', array( 'minimum_length' => 10 ), 400 );
		}

		$limit = InteractionRateLimiter::attempt( 'reports', $user_id, self::rate_object_id( $object_type, $object_id ) );
		if ( empty( $limit['ok'] ) ) {
			return $limit;
		}

		$duplicate_hash = self::duplicate_hash( $user_id, $object_type, $object_id, $reason );
		$existing       = ReportQueryRepository::duplicate_record( $user_id, $object_type, $object_id, $duplicate_hash );
		if ( is_array( $existing ) ) {
			return self::submitted_result( true );
		}

		$insert = InteractionRepository::insert_row(
			'reports',
			array(
				'reporter_user_id' => $user_id,
				'object_type'      => $object_type,
				'object_id'        => $object_id,
				'reason'           => $reason,
				'status'           => 'open',
				'duplicate_hash'   => $duplicate_hash,
				'notes'            => ReportPolicy::encode_notes( $reporter_note ),
			)
		);

		if ( empty( $insert['ok'] ) ) {
			// Recover safely from a concurrent unique-key insert.
			$existing = ReportQueryRepository::duplicate_record( $user_id, $object_type, $object_id, $duplicate_hash );
			if ( is_array( $existing ) ) {
				return self::submitted_result( true );
			}
			return InteractionResult::error( 'report_create_failed', 'The report could not be submitted.', array(), 500 );
		}

		AuditLog::record(
			'report_created',
			array( 'object_type' => $object_type, 'object_id' => $object_id, 'reason' => $reason ),
			$object_type,
			$object_id
		);
		return self::submitted_result( false );
	}

	/**
	 * Return the confidential moderator queue.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @param int                 $user_id Optional moderator user ID.
	 * @return array<string,mixed>
	 */
	public static function queue( array $filters = array(), $user_id = 0 ) {
		if ( ! InteractionPermissions::can_manage_reports( $user_id ) ) {
			return InteractionResult::error( 'report_permission_denied', 'You cannot access the report queue.', array(), 403 );
		}

		$query = ReportQueryRepository::queue( $filters );
		$items = array();
		foreach ( isset( $query['items'] ) && is_array( $query['items'] ) ? $query['items'] : array() as $row ) {
			$items[] = self::serialize_for_moderator( $row );
		}

		return InteractionResult::success(
			'reports_loaded',
			array(
				'items'     => $items,
				'total'     => isset( $query['total'] ) ? (int) $query['total'] : 0,
				'page'      => isset( $query['page'] ) ? (int) $query['page'] : 1,
				'per_page'  => isset( $query['per_page'] ) ? (int) $query['per_page'] : 25,
				'max_pages' => isset( $query['max_pages'] ) ? (int) $query['max_pages'] : 0,
				'filters'   => self::safe_filters( $filters ),
			),
			'Reports loaded.',
			200
		);
	}

	/**
	 * Apply a bounded moderator status transition and private note.
	 *
	 * @param int    $report_id Report ID.
	 * @param string $status Requested state.
	 * @param string $moderator_note Private moderator note.
	 * @param int    $user_id Optional current moderator ID.
	 * @return array<string,mixed>
	 */
	public static function moderate( $report_id, $status, $moderator_note = '', $user_id = 0 ) {
		$user_id = InteractionPermissions::authenticated_user_id( $user_id );
		if ( $user_id <= 0 || ! InteractionPermissions::can_manage_reports( $user_id ) ) {
			return InteractionResult::error( 'report_permission_denied', 'You cannot moderate reports.', array(), 403 );
		}

		$report_id = self::positive_id( $report_id );
		$status    = self::clean_key( $status );
		if ( $report_id <= 0 || ! ReportPolicy::state_allowed( $status ) ) {
			return InteractionResult::error( 'invalid_report_update', 'The report update is invalid.', array(), 400 );
		}

		$row = ReportQueryRepository::report( $report_id );
		if ( ! is_array( $row ) ) {
			return InteractionResult::error( 'report_unavailable', 'The requested report is unavailable.', array(), 404 );
		}
		$current = isset( $row['status'] ) ? self::clean_key( $row['status'] ) : '';
		if ( ! ReportPolicy::transition_allowed( $current, $status ) ) {
			return InteractionResult::error( 'invalid_report_transition', 'That report status transition is not allowed.', array( 'current_status' => $current ), 409 );
		}

		$notes          = ReportPolicy::decode_notes( isset( $row['notes'] ) ? $row['notes'] : '' );
		$moderator_note = ReportPolicy::moderator_note( $moderator_note );
		$now            = gmdate( 'Y-m-d H:i:s' );
		$encoded        = ReportPolicy::encode_notes( $notes['reporter_note'], $moderator_note, $user_id, $now );
		$update         = InteractionRepository::update_rows(
			'reports',
			array(
				'status' => $status,
				'notes'  => $encoded,
			),
			array( 'id' => $report_id )
		);
		if ( empty( $update['ok'] ) ) {
			return InteractionResult::error( 'report_update_failed', 'The report could not be updated.', array(), 500 );
		}

		AuditLog::record(
			'report_moderated',
			array( 'report_id' => $report_id, 'from' => $current, 'to' => $status, 'has_note' => '' !== $moderator_note ),
			'report',
			$report_id
		);

		$row = ReportQueryRepository::report( $report_id );
		return InteractionResult::success(
			'report_updated',
			array( 'report' => is_array( $row ) ? self::serialize_for_moderator( $row ) : array( 'id' => $report_id, 'status' => $status ) ),
			'Report updated.',
			200
		);
	}

	/**
	 * Determine whether an object can be reported by the current user.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id Object ID.
	 * @param int    $user_id Current user ID.
	 * @return array<string,mixed>
	 */
	private static function reportable_object( $object_type, $object_id, $user_id ) {
		if ( 'post' === $object_type ) {
			if ( ! InteractionPermissions::can_view_post( $object_id, $user_id ) || ( function_exists( 'get_post_status' ) && 'publish' !== get_post_status( $object_id ) ) || ! PostMetadata::review_state_publicly_visible( $object_id ) ) {
				return InteractionResult::error( 'report_object_unavailable', 'The selected content is unavailable.', array(), 404 );
			}
			$owner_id = function_exists( 'get_post_field' ) ? self::positive_id( get_post_field( 'post_author', $object_id ) ) : 0;
			return InteractionResult::success( 'report_object_valid', array( 'owner_user_id' => $owner_id ), 'Valid.', 200 );
		}

		$comment = function_exists( 'get_comment' ) ? get_comment( $object_id ) : null;
		if ( ! $comment || CommentPolicy::COMMENT_TYPE !== (string) $comment->comment_type ) {
			return InteractionResult::error( 'report_object_unavailable', 'The selected content is unavailable.', array(), 404 );
		}
		$post_id  = isset( $comment->comment_post_ID ) ? self::positive_id( $comment->comment_post_ID ) : 0;
		$owner_id = isset( $comment->user_id ) ? self::positive_id( $comment->user_id ) : 0;
		$approved = isset( $comment->comment_approved ) && in_array( (string) $comment->comment_approved, array( '1', 'approve', 'approved' ), true );
		$deleted  = function_exists( 'get_comment_meta' ) && 1 === (int) get_comment_meta( $object_id, CommentPolicy::META_DELETED, true );
		$visible  = $approved || $owner_id === $user_id || InteractionPermissions::can_manage_reports( $user_id );
		if ( $post_id <= 0 || ! PostMetadata::user_can_view( $post_id, $user_id ) || ! $visible || $deleted ) {
			return InteractionResult::error( 'report_object_unavailable', 'The selected content is unavailable.', array(), 404 );
		}
		return InteractionResult::success( 'report_object_valid', array( 'owner_user_id' => $owner_id, 'post_id' => $post_id ), 'Valid.', 200 );
	}

	/**
	 * Serialize a report only for an authorized moderator.
	 *
	 * @param array<string,mixed> $row Database row.
	 * @return array<string,mixed>
	 */
	private static function serialize_for_moderator( array $row ) {
		$reporter_id = isset( $row['reporter_user_id'] ) ? max( 0, (int) $row['reporter_user_id'] ) : 0;
		$object_type = isset( $row['object_type'] ) ? self::clean_key( $row['object_type'] ) : '';
		$object_id   = isset( $row['object_id'] ) ? self::positive_id( $row['object_id'] ) : 0;
		$notes       = ReportPolicy::decode_notes( isset( $row['notes'] ) ? $row['notes'] : '' );
		$object      = self::object_summary( $object_type, $object_id );

		return array(
			'id'                 => isset( $row['id'] ) ? self::positive_id( $row['id'] ) : 0,
			'reporter_user_id'   => $reporter_id,
			'reporter_name'      => self::public_user_name( $reporter_id ),
			'object_type'        => $object_type,
			'object_id'          => $object_id,
			'object_label'       => $object['label'],
			'object_url'         => $object['url'],
			'object_excerpt'     => $object['excerpt'],
			'reason'             => isset( $row['reason'] ) ? self::clean_key( $row['reason'] ) : '',
			'status'             => isset( $row['status'] ) ? self::clean_key( $row['status'] ) : '',
			'reporter_note'      => $notes['reporter_note'],
			'moderator_note'     => $notes['moderator_note'],
			'last_moderator_id'  => $notes['last_moderator_id'],
			'last_moderated_at'  => $notes['last_moderated_at'],
			'created_at'         => isset( $row['created_at'] ) ? sanitize_text_field( $row['created_at'] ) : '',
			'updated_at'         => isset( $row['updated_at'] ) ? sanitize_text_field( $row['updated_at'] ) : '',
		);
	}

	/**
	 * Build a safe object summary for moderators.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id Object ID.
	 * @return array<string,string>
	 */
	private static function object_summary( $object_type, $object_id ) {
		if ( 'post' === $object_type ) {
			$title = function_exists( 'get_the_title' ) ? (string) get_the_title( $object_id ) : '';
			$url   = function_exists( 'get_permalink' ) ? (string) get_permalink( $object_id ) : '';
			return array(
				'label'   => '' !== trim( $title ) ? sanitize_text_field( $title ) : sprintf( __( 'Post #%d', 'sabri-complete-home-news-feed' ), $object_id ),
				'url'     => function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url,
				'excerpt' => '',
			);
		}

		$comment = function_exists( 'get_comment' ) ? get_comment( $object_id ) : null;
		$content = $comment && isset( $comment->comment_content ) ? sanitize_textarea_field( $comment->comment_content ) : '';
		$content = function_exists( 'mb_substr' ) ? mb_substr( $content, 0, 240 ) : substr( $content, 0, 240 );
		$url     = function_exists( 'get_comment_link' ) ? (string) get_comment_link( $object_id ) : '';
		return array(
			'label'   => sprintf( __( 'Comment #%d', 'sabri-complete-home-news-feed' ), $object_id ),
			'url'     => function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url,
			'excerpt' => $content,
		);
	}

	/**
	 * Public display name without email fallback leakage.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private static function public_user_name( $user_id ) {
		$user = $user_id > 0 && function_exists( 'get_userdata' ) ? get_userdata( $user_id ) : false;
		$name = $user && isset( $user->display_name ) ? trim( sanitize_text_field( $user->display_name ) ) : '';
		$is_email = '' !== $name && ( ( function_exists( 'is_email' ) && is_email( $name ) ) || false !== filter_var( $name, FILTER_VALIDATE_EMAIL ) );
		return '' !== $name && ! $is_email ? $name : ( $user_id > 0 ? sprintf( __( 'Sabri member #%d', 'sabri-complete-home-news-feed' ), $user_id ) : __( 'Anonymized member', 'sabri-complete-home-news-feed' ) );
	}

	/**
	 * Generic public success without confidential record details.
	 *
	 * @param bool $duplicate Whether an existing record satisfied the request.
	 * @return array<string,mixed>
	 */
	private static function submitted_result( $duplicate ) {
		unset( $duplicate );
		return InteractionResult::success( 'report_submitted', array( 'submitted' => true ), 'Report submitted for confidential review.', 201 );
	}

	/**
	 * Safe queue filters.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @return array<string,mixed>
	 */
	private static function safe_filters( array $filters ) {
		return array(
			'status'      => isset( $filters['status'] ) && ReportPolicy::state_allowed( $filters['status'] ) ? self::clean_key( $filters['status'] ) : '',
			'reason'      => isset( $filters['reason'] ) && ReportPolicy::reason_allowed( $filters['reason'] ) ? self::clean_key( $filters['reason'] ) : '',
			'object_type' => isset( $filters['object_type'] ) && ReportPolicy::object_type_allowed( $filters['object_type'] ) ? self::clean_key( $filters['object_type'] ) : '',
		);
	}

	/**
	 * Stable duplicate hash.
	 *
	 * @param int    $user_id Reporter ID.
	 * @param string $object_type Object type.
	 * @param int    $object_id Object ID.
	 * @param string $reason Reason.
	 * @return string
	 */
	private static function duplicate_hash( $user_id, $object_type, $object_id, $reason ) {
		return hash( 'sha256', (int) $user_id . '|' . $object_type . '|' . (int) $object_id . '|' . $reason );
	}

	/**
	 * Separate post and comment rate-limit keyspaces.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id Object ID.
	 * @return int
	 */
	private static function rate_object_id( $object_type, $object_id ) {
		return 'comment' === $object_type ? 1000000000 + (int) $object_id : (int) $object_id;
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
	 * Sanitize key.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}

	/**
	 * Text length.
	 *
	 * @param string $value Value.
	 * @return int
	 */
	private static function text_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}
}
