<?php
/**
 * Doctor and contributor submission workflow.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Owns submission state, ownership, declarations, files, and conversion. */
final class SubmissionService {
	public static function register() {}

	public static function create( array $input ) {
		$actor = self::actor();
		if ( $actor < 1 || ! self::gate() || ! function_exists( 'current_user_can' ) || ! current_user_can( 'submit_editorial_news' ) ) {
			return self::error( 'phase5_permission_denied', 403 );
		}
		if ( ! Phase5RateLimiter::allow( 'submission-create', 5, HOUR_IN_SECONDS, $actor ) ) {
			return self::error( 'phase5_rate_limited', 429 );
		}
		$clean = self::validate( $input, false );
		if ( empty( $clean['success'] ) ) {
			return $clean;
		}
		$now = gmdate( 'Y-m-d H:i:s' );
		$data = $clean['data'];
		$data['submitter_user_id'] = $actor;
		$data['status'] = 'draft';
		$data['converted_article_id'] = 0;
		$data['private_editor_notes'] = '';
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		$data['submitted_at'] = null;
		$id = Phase5Repository::insert( 'submissions', $data );
		if ( $id < 1 ) {
			return self::error( 'phase5_query_failed', 500 );
		}
		Phase5AuditIntegrity::record( 'submission-created', 'submission', $id, array( 'state' => 'draft' ) );
		return array( 'success' => true, 'status' => 201, 'data' => self::find_for_actor( $id ) );
	}

	public static function update( $id, array $patch ) {
		$row = Phase5Repository::find( 'submissions', $id );
		if ( ! $row || ! self::can_edit( $row ) || ! in_array( $row['status'], array( 'draft', 'needs-information' ), true ) ) {
			return self::error( 'phase5_not_found', 404 );
		}
		$merged = array_merge(
			array(
				'title' => $row['title'], 'summary' => $row['summary'], 'body' => $row['body'],
				'source_urls' => json_decode( (string) $row['source_urls'], true ),
				'declarations' => json_decode( (string) $row['declarations'], true ),
			),
			$patch
		);
		$clean = self::validate( $merged, false );
		if ( empty( $clean['success'] ) ) {
			return $clean;
		}
		$data = $clean['data'];
		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		if ( ! Phase5Repository::update( 'submissions', $id, $data ) ) {
			return self::error( 'phase5_query_failed', 500 );
		}
		Phase5AuditIntegrity::record( 'submission-updated', 'submission', $id, array( 'state' => $row['status'] ) );
		return array( 'success' => true, 'status' => 200, 'data' => self::find_for_actor( $id ) );
	}

	public static function transition( $id, $target, array $input = array() ) {
		$row = Phase5Repository::find( 'submissions', $id );
		if ( ! $row ) {
			return self::error( 'phase5_not_found', 404 );
		}
		$current = (string) $row['status'];
		$target = is_string( $target ) && in_array( $target, Phase5Contracts::submission_states(), true ) ? $target : '';
		$allowed = self::allowed_targets( $current, self::can_manage() );
		if ( '' === $target || ! in_array( $target, $allowed, true ) || ! self::can_transition( $row, $target ) ) {
			return self::error( 'phase5_state_invalid', 409 );
		}
		if ( 'submitted' === $target ) {
			$validation = self::validate( array( 'title' => $row['title'], 'summary' => $row['summary'], 'body' => $row['body'], 'source_urls' => json_decode( (string) $row['source_urls'], true ), 'declarations' => json_decode( (string) $row['declarations'], true ) ), true );
			if ( empty( $validation['success'] ) ) {
				return $validation;
			}
		}
		$data = array( 'status' => $target, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) );
		if ( 'submitted' === $target ) {
			$data['submitted_at'] = gmdate( 'Y-m-d H:i:s' );
		}
		if ( self::can_manage() && isset( $input['private_editor_notes'] ) ) {
			$data['private_editor_notes'] = substr( trim( (string) $input['private_editor_notes'] ), 0, 10000 );
		}
		if ( ! Phase5Repository::update( 'submissions', $id, $data ) ) {
			return self::error( 'phase5_query_failed', 500 );
		}
		Phase5AuditIntegrity::record( 'submission-transitioned', 'submission', $id, array( 'previous_state' => $current, 'state' => $target ) );
		return array( 'success' => true, 'status' => 200, 'data' => self::find_for_actor( $id, self::can_manage() ) );
	}

	public static function convert_to_article( $id ) {
		$row = Phase5Repository::find( 'submissions', $id );
		if ( ! $row || ! self::can_manage() || ! in_array( $row['status'], array( 'accepted', 'converted' ), true ) ) {
			return self::error( 'phase5_permission_denied', 403 );
		}
		if ( ! empty( $row['converted_article_id'] ) ) {
			return array( 'success' => true, 'status' => 200, 'data' => array( 'article_id' => (int) $row['converted_article_id'], 'idempotent' => true ) );
		}
		if ( ! function_exists( 'wp_insert_post' ) ) {
			return self::error( 'phase5_query_failed', 500 );
		}
		$post_id = wp_insert_post(
			array(
				'post_type' => Phase4Contracts::POST_TYPE,
				'post_status' => 'draft',
				'post_title' => (string) $row['title'],
				'post_excerpt' => (string) $row['summary'],
				'post_content' => function_exists( 'wp_kses_post' ) ? wp_kses_post( (string) $row['body'] ) : strip_tags( (string) $row['body'], '<p><br><strong><em><ul><ol><li><a><blockquote>' ),
				'post_author' => (int) $row['submitter_user_id'],
			),
			true
		);
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $post_id ) ) {
			return self::error( 'phase5_query_failed', 500 );
		}
		$post_id = (int) $post_id;
		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, 'draft' );
			update_post_meta( $post_id, '_sabri_news_submission_id', (int) $id );
		}
		if ( ! Phase5Repository::update( 'submissions', $id, array( 'status' => 'converted', 'converted_article_id' => $post_id, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ) ) ) {
			if ( function_exists( 'wp_delete_post' ) ) {
				wp_delete_post( $post_id, true );
			}
			return self::error( 'phase5_query_failed', 500 );
		}
		Phase5AuditIntegrity::record( 'submission-converted', 'submission', $id, array( 'result' => 'draft-created' ) );
		return array( 'success' => true, 'status' => 201, 'data' => array( 'article_id' => $post_id, 'idempotent' => false ) );
	}

	public static function attach_file_record( $submission_id, array $validated, $attachment_id = 0, $consent_status = 'not-applicable' ) {
		$row = Phase5Repository::find( 'submissions', $submission_id );
		if ( ! $row || ! self::can_edit( $row ) || empty( $validated['success'] ) || empty( $validated['data'] ) ) {
			return self::error( 'phase5_upload_rejected', 400 );
		}
		if ( ! in_array( $consent_status, array( 'not-applicable', 'documented-private', 'prohibited' ), true ) || 'prohibited' === $consent_status ) {
			return self::error( 'phase5_privacy_blocked', 400 );
		}
		$data = $validated['data'];
		$id = Phase5Repository::insert(
			'submission_files',
			array(
				'submission_id' => (int) $submission_id,
				'attachment_id' => max( 0, (int) $attachment_id ),
				'original_name' => $data['original_name'],
				'stored_mime' => $data['mime'],
				'sha256' => $data['sha256'],
				'size_bytes' => $data['size_bytes'],
				'consent_status' => $consent_status,
				'status' => 'active',
				'created_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		return $id > 0 ? array( 'success' => true, 'status' => 201, 'data' => array( 'id' => $id ) ) : self::error( 'phase5_query_failed', 500 );
	}

	public static function list_for_current_user( $limit = 50, $offset = 0 ) {
		$actor = self::actor();
		return $actor > 0 ? Phase5Repository::query( 'submissions', array( 'submitter_user_id' => $actor ), $limit, $offset, 'id', 'DESC' ) : array();
	}

	public static function find_for_actor( $id, $private = false ) {
		$row = Phase5Repository::find( 'submissions', $id );
		if ( ! $row || ( ! self::can_manage() && (int) $row['submitter_user_id'] !== self::actor() ) ) {
			return null;
		}
		$out = array(
			'id' => (int) $row['id'], 'status' => (string) $row['status'], 'title' => (string) $row['title'],
			'summary' => (string) $row['summary'], 'body' => (string) $row['body'],
			'source_urls' => json_decode( (string) $row['source_urls'], true ),
			'declarations' => json_decode( (string) $row['declarations'], true ),
			'converted_article_id' => (int) $row['converted_article_id'],
			'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'], 'submitted_at' => $row['submitted_at'],
		);
		if ( $private && self::can_manage() ) {
			$out['submitter_user_id'] = (int) $row['submitter_user_id'];
			$out['private_editor_notes'] = (string) $row['private_editor_notes'];
		}
		return $out;
	}

	private static function validate( array $input, $submission_ready ) {
		$title = isset( $input['title'] ) ? trim( strip_tags( (string) $input['title'] ) ) : '';
		$summary = isset( $input['summary'] ) ? trim( strip_tags( (string) $input['summary'] ) ) : '';
		$body = isset( $input['body'] ) ? trim( (string) $input['body'] ) : '';
		$sources = isset( $input['source_urls'] ) && is_array( $input['source_urls'] ) ? array_slice( array_values( array_unique( array_filter( array_map( array( __CLASS__, 'safe_url' ), $input['source_urls'] ) ) ) ), 0, 25 ) : array();
		$declared = isset( $input['declarations'] ) && is_array( $input['declarations'] ) ? $input['declarations'] : array();
		$declarations = array();
		foreach ( array( 'owns_text','owns_media','conflicts_declared','sponsorship_declared','patient_identifiers_absent','ai_assistance_declared','emergency_content_declared' ) as $key ) {
			$declarations[ $key ] = ! empty( $declared[ $key ] ) ? 1 : 0;
		}
		if ( '' === $title || strlen( $title ) > 300 || strlen( $summary ) > 2000 || strlen( $body ) > 100000 ) {
			return self::error( 'phase5_payload_invalid', 400 );
		}
		$privacy = PrivacyScanner::scan( $title . "\n" . $summary . "\n" . strip_tags( $body ) );
		if ( ! empty( $privacy['blocked'] ) ) {
			return self::error( 'phase5_privacy_blocked', 400, implode( ',', $privacy['categories'] ) );
		}
		if ( $submission_ready && ( strlen( $body ) < 50 || ! $sources || empty( $declarations['owns_text'] ) || empty( $declarations['patient_identifiers_absent'] ) ) ) {
			return self::error( 'phase5_payload_invalid', 400 );
		}
		return array(
			'success' => true,
			'data' => array(
				'title' => $title, 'summary' => $summary,
				'body' => function_exists( 'wp_kses_post' ) ? wp_kses_post( $body ) : strip_tags( $body, '<p><br><strong><em><ul><ol><li><a><blockquote>' ),
				'source_urls' => json_encode( $sources ), 'declarations' => json_encode( $declarations ),
			)
		);
	}

	private static function allowed_targets( $current, $manager ) {
		$owner = array(
			'draft' => array( 'submitted', 'withdrawn' ),
			'needs-information' => array( 'submitted', 'withdrawn' ),
			'submitted' => array( 'withdrawn' ),
		);
		$admin = array(
			'submitted' => array( 'needs-information', 'under-assessment', 'rejected' ),
			'under-assessment' => array( 'needs-information', 'accepted', 'rejected' ),
			'accepted' => array( 'archived' ),
			'converted' => array( 'archived' ),
			'rejected' => array( 'archived' ),
			'withdrawn' => array( 'archived' ),
		);
		return $manager && isset( $admin[ $current ] ) ? $admin[ $current ] : ( isset( $owner[ $current ] ) ? $owner[ $current ] : array() );
	}

	private static function can_transition( array $row, $target ) {
		if ( self::can_manage() ) {
			return true;
		}
		return (int) $row['submitter_user_id'] === self::actor() && in_array( $target, array( 'submitted', 'withdrawn' ), true );
	}

	private static function can_edit( array $row ) {
		return self::can_manage() || (int) $row['submitter_user_id'] === self::actor();
	}

	private static function can_manage() {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_news_submissions' );
	}

	private static function actor() {
		return function_exists( 'get_current_user_id' ) ? max( 0, (int) get_current_user_id() ) : 0;
	}

	private static function gate() {
		return Phase5FeatureSettings::enabled( 'submissions_enabled' );
	}

	private static function safe_url( $url ) {
		$url = trim( (string) $url );
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return '';
		}
		return function_exists( 'esc_url_raw' ) ? esc_url_raw( $url, array( 'http', 'https' ) ) : $url;
	}

	private static function error( $code, $status, $message = '' ) {
		return array( 'success' => false, 'status' => $status, 'code' => $code, 'message' => $message );
	}
}
