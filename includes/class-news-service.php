<?php
/**
 * Phase 4B Editorial News application service.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Coordinates secure composer saves, transitions, assignments, and scheduling. */
final class NewsService {
	/** Register service foundations without exposing REST routes. */
	public static function register() {
		// NewsroomAdmin is the only Phase 4B request controller.
	}

	/** Enforce exact request method and verified nonce context. */
	public static function request_guard( array $request, $required_method = 'POST' ) {
		$method = isset( $request['method'] ) && is_string( $request['method'] ) ? strtoupper( $request['method'] ) : '';
		if ( strtoupper( (string) $required_method ) !== $method ) {
			return self::result( false, 'request_method_invalid' );
		}
		if ( empty( $request['nonce_verified'] ) || true !== $request['nonce_verified'] ) {
			return self::result( false, 'request_nonce_invalid' );
		}
		return self::result( true, 'request_authorized' );
	}

	/** Create or update one Editorial News item through the composer contract. */
	public static function save( $post_id, array $input, array $request ) {
		$guard = self::request_guard( $request );
		if ( empty( $guard['success'] ) ) {
			return $guard;
		}
		$post_id = function_exists( 'absint' ) ? absint( $post_id ) : max( 0, (int) $post_id );
		$is_new = $post_id < 1;
		$featured_image_supplied = array_key_exists( 'featured_image_id', $input );
		if ( ! NewsPolicy::writes_allowed() ) {
			return self::result( false, 'newsroom_writes_disabled' );
		}
		if ( $is_new ? ! NewsPolicy::can_create() : ! NewsPolicy::can_edit( $post_id ) ) {
			return self::result( false, 'composer_authorization_denied' );
		}
		if ( ! $is_new && function_exists( 'get_post_type' ) && Phase4Contracts::POST_TYPE !== get_post_type( $post_id ) ) {
			return self::result( false, 'composer_wrong_post_type' );
		}

		$validation = NewsComposerValidator::validate( $input );
		if ( empty( $validation['success'] ) ) {
			return self::result( false, 'composer_validation_failed', array( 'errors' => $validation['errors'], 'data' => $validation['data'] ) );
		}
		$data = $validation['data'];
		$current_state = $is_new || ! function_exists( 'get_post_meta' ) ? 'draft' : NewsStatuses::sanitize_state( get_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, true ) );
		$current_state = $current_state ? $current_state : 'draft';
		$target_state = $data['target_state'] ? $data['target_state'] : $current_state;
		if ( 'published' === $target_state ) {
			return self::result( false, 'phase4b_publication_closed' );
		}
		if ( $target_state !== $current_state ) {
			$transition = NewsWorkflow::validate_transition( $current_state, $target_state );
			if ( empty( $transition['success'] ) || ! NewsWorkflow::can_transition( $current_state, $target_state, $post_id ) ) {
				return self::result( false, 'workflow_transition_denied', array( 'from' => $current_state, 'to' => $target_state ) );
			}
		}

		$prerequisites = self::validate_prerequisites( $data, $target_state );
		if ( ! empty( $prerequisites ) ) {
			return self::result( false, 'workflow_prerequisites_missing', array( 'errors' => $prerequisites, 'data' => $data ) );
		}
		$assignment_error = self::validate_assignments( $post_id, $data );
		if ( $assignment_error ) {
			return self::result( false, $assignment_error );
		}
		$taxonomy_error = self::validate_taxonomy_authority( $data );
		if ( $taxonomy_error ) {
			return self::result( false, $taxonomy_error );
		}
		if ( $featured_image_supplied ) {
			$image_error = self::validate_featured_image( $data['featured_image_id'] );
			if ( $image_error ) {
				return self::result( false, $image_error );
			}
		}

		$core_status = NewsStatuses::wordpress_status( 'scheduled' === $target_state ? 'ready-for-publication' : $target_state );
		$core_status = $core_status ? $core_status : 'draft';
		$postarr = array(
			'post_type' => Phase4Contracts::POST_TYPE,
			'post_title' => $data['title'],
			'post_content' => $data['content'],
			'post_excerpt' => $data['summary'],
			'post_status' => $core_status,
		);
		if ( $is_new ) {
			$postarr['post_author'] = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
			$stored_id = function_exists( 'wp_insert_post' ) ? wp_insert_post( $postarr, true ) : 0;
		} else {
			$postarr['ID'] = $post_id;
			$stored_id = function_exists( 'wp_update_post' ) ? wp_update_post( $postarr, true ) : 0;
		}
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $stored_id ) ) {
			return self::result( false, 'composer_persistence_failed', array( 'message' => $stored_id->get_error_message() ) );
		}
		$stored_id = function_exists( 'absint' ) ? absint( $stored_id ) : max( 0, (int) $stored_id );
		if ( $stored_id < 1 ) {
			return self::result( false, 'composer_persistence_failed' );
		}

		self::store_metadata( $stored_id, $data, 'scheduled' === $target_state ? 'ready-for-publication' : $target_state );
		$taxonomy_result = self::store_taxonomies( $stored_id, $data );
		if ( empty( $taxonomy_result['success'] ) ) {
			NewsAudit::record( $stored_id, 'article_save_failed', array( 'reason' => $taxonomy_result['code'] ) );
			return self::result( false, 'taxonomy_persistence_failed', array( 'post_id' => $stored_id, 'detail' => $taxonomy_result ) );
		}
		$image_result = self::store_featured_image( $stored_id, $data['featured_image_id'], $featured_image_supplied );
		if ( empty( $image_result['success'] ) ) {
			NewsAudit::record( $stored_id, 'article_save_failed', array( 'reason' => $image_result['code'] ) );
			return self::result( false, 'featured_image_persistence_failed', array( 'post_id' => $stored_id, 'detail' => $image_result ) );
		}

		if ( 'scheduled' === $target_state ) {
			$schedule = NewsSchedulingService::schedule( $stored_id, isset( $input['schedule_at'] ) ? $input['schedule_at'] : '' );
			if ( empty( $schedule['success'] ) ) {
				return self::result( false, 'composer_schedule_failed', array( 'schedule' => $schedule, 'post_id' => $stored_id ) );
			}
		}

		NewsAudit::record(
			$stored_id,
			$is_new ? 'article_created' : 'article_updated',
			array(
				'from_state' => $current_state,
				'target_state' => $target_state,
				'revision_safe' => true,
				'featured_image_operation' => $image_result['code'],
			)
		);
		return self::result( true, $is_new ? 'article_created' : 'article_updated', array( 'post_id' => $stored_id, 'state' => $target_state ) );
	}

	/** Apply one authorized workflow transition without arbitrary metadata writes. */
	public static function transition( $post_id, $target_state, array $request ) {
		$guard = self::request_guard( $request );
		if ( empty( $guard['success'] ) ) {
			return $guard;
		}
		$post_id = function_exists( 'absint' ) ? absint( $post_id ) : max( 0, (int) $post_id );
		$target_state = NewsStatuses::sanitize_state( $target_state );
		if ( $post_id < 1 || ! $target_state || ! NewsPolicy::can_edit( $post_id ) ) {
			return self::result( false, 'transition_authorization_or_state_invalid' );
		}
		$current_state = function_exists( 'get_post_meta' ) ? NewsStatuses::sanitize_state( get_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, true ) ) : '';
		$current_state = $current_state ? $current_state : 'draft';
		if ( $current_state === $target_state ) {
			return self::result( true, 'workflow_unchanged', array( 'post_id' => $post_id, 'state' => $current_state ) );
		}
		if ( 'published' === $target_state ) {
			return self::result( false, 'phase4b_publication_closed' );
		}
		if ( ! NewsWorkflow::can_transition( $current_state, $target_state, $post_id ) ) {
			return self::result( false, 'workflow_transition_denied', array( 'from' => $current_state, 'to' => $target_state ) );
		}
		$data = self::article_projection( $post_id, $target_state );
		$errors = self::validate_prerequisites( $data, $target_state );
		if ( ! empty( $errors ) ) {
			return self::result( false, 'workflow_prerequisites_missing', array( 'errors' => $errors ) );
		}
		$updated = true;
		if ( function_exists( 'wp_update_post' ) ) {
			$updated = wp_update_post( array( 'ID' => $post_id, 'post_status' => NewsStatuses::wordpress_status( $target_state ) ), true );
		}
		if ( false === $updated || ( function_exists( 'is_wp_error' ) && is_wp_error( $updated ) ) ) {
			$message = function_exists( 'is_wp_error' ) && is_wp_error( $updated ) ? $updated->get_error_message() : '';
			NewsAudit::record( $post_id, 'workflow_transition_failed', array( 'from_state' => $current_state, 'to_state' => $target_state, 'message' => $message ) );
			return self::result( false, 'workflow_post_update_failed', array( 'message' => $message ) );
		}
		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, $target_state );
		}
		NewsAudit::record( $post_id, 'workflow_transition', array( 'from_state' => $current_state, 'to_state' => $target_state ) );
		return self::result( true, 'workflow_transition_completed', array( 'post_id' => $post_id, 'state' => $target_state ) );
	}

	/** Validate state-specific completeness. */
	private static function validate_prerequisites( array $data, $target_state ) {
		$errors = array();
		$review_states = array( 'editorial-review', 'fact-check', 'medical-review', 'ready-for-publication', 'scheduled' );
		if ( in_array( $target_state, $review_states, true ) ) {
			if ( '' === $data['content'] ) {
				$errors['content'] = 'required_for_review';
			}
			if ( '' === $data['section'] ) {
				$errors['section'] = 'required_for_review';
			}
			if ( '' === $data['article_type'] ) {
				$errors['article_type'] = 'required_for_review';
			}
		}
		if ( in_array( $target_state, array( 'ready-for-publication', 'scheduled' ), true ) && '' === $data['summary'] ) {
			$errors['summary'] = 'required_for_approval';
		}
		if ( 'fact-check' === $target_state && $data['fact_check_required'] && $data['reviewing_editor_id'] < 1 ) {
			$errors['reviewing_editor_id'] = 'fact_checker_required';
		}
		if ( 'medical-review' === $target_state && $data['medical_review_required'] && $data['medical_reviewer_id'] < 1 ) {
			$errors['medical_reviewer_id'] = 'medical_reviewer_required';
		}
		if ( 'scheduled' === $target_state && '' === $data['schedule_at_utc'] ) {
			$errors['schedule_at'] = 'required_for_scheduling';
		}
		return $errors;
	}

	/** Validate reviewer assignments before persistence. */
	private static function validate_assignments( $post_id, array $data ) {
		$review_type = ! empty( $data['fact_check_required'] ) || 'fact-check' === $data['target_state'] ? 'fact-check' : 'editorial';
		if ( $data['reviewing_editor_id'] > 0 && ! NewsPolicy::can_assign_reviewer( $post_id, $data['reviewing_editor_id'], $review_type ) ) {
			return 'fact-check' === $review_type ? 'fact_checker_assignment_denied' : 'reviewing_editor_assignment_denied';
		}
		if ( $data['medical_reviewer_id'] > 0 && ! NewsPolicy::can_assign_reviewer( $post_id, $data['medical_reviewer_id'], 'medical' ) ) {
			return 'medical_reviewer_assignment_denied';
		}
		return '';
	}

	/** Enforce taxonomy-management authority for broad classification fields. */
	private static function validate_taxonomy_authority( array $data ) {
		$has_broad_taxonomy = ! empty( $data['topics'] ) || ! empty( $data['countries'] ) || ! empty( $data['regions'] );
		if ( $has_broad_taxonomy && ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_news_taxonomies' ) ) ) {
			return 'news_taxonomy_assignment_denied';
		}
		return '';
	}

	/** Validate featured-image identity and upload authority before persistence. */
	private static function validate_featured_image( $attachment_id ) {
		$attachment_id = function_exists( 'absint' ) ? absint( $attachment_id ) : max( 0, (int) $attachment_id );
		if ( 0 === $attachment_id ) {
			return '';
		}
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'upload_files' ) ) {
			return 'featured_image_authorization_denied';
		}
		if ( ! function_exists( 'wp_attachment_is_image' ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return 'featured_image_invalid';
		}
		return '';
	}

	/** Persist registered private metadata through the service boundary. */
	private static function store_metadata( $post_id, array $data, $state ) {
		if ( ! function_exists( 'update_post_meta' ) ) {
			return;
		}
		$map = array(
			Phase4Contracts::WORKFLOW_META_KEY => $state,
			'_sabri_news_subtitle' => $data['subtitle'],
			'_sabri_news_summary' => $data['summary'],
			'_sabri_news_language' => $data['language'],
			'_sabri_news_priority' => $data['priority'],
			'_sabri_news_reviewing_editor_id' => $data['reviewing_editor_id'],
			'_sabri_news_medical_reviewer_id' => $data['medical_reviewer_id'],
			'_sabri_news_fact_check_required' => $data['fact_check_required'] ? 1 : 0,
			'_sabri_news_medical_review_required' => $data['medical_review_required'] ? 1 : 0,
		);
		foreach ( $map as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	/** Persist controlled taxonomies and surface any core errors. */
	private static function store_taxonomies( $post_id, array $data ) {
		if ( ! function_exists( 'wp_set_object_terms' ) ) {
			return self::result( false, 'taxonomy_api_unavailable' );
		}
		$assignments = array(
			'sabri_news_section' => $data['section'] ? array( $data['section'] ) : array(),
			'sabri_news_type' => $data['article_type'] ? array( $data['article_type'] ) : array(),
			'sabri_news_topic' => $data['topics'],
			'sabri_news_country' => $data['countries'],
			'sabri_news_region' => $data['regions'],
		);
		foreach ( $assignments as $taxonomy => $terms ) {
			$result = wp_set_object_terms( $post_id, $terms, $taxonomy, false );
			if ( false === $result || ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) ) {
				$message = function_exists( 'is_wp_error' ) && is_wp_error( $result ) ? $result->get_error_message() : '';
				return self::result( false, 'taxonomy_assignment_failed', array( 'taxonomy' => $taxonomy, 'message' => $message ) );
			}
		}
		return self::result( true, 'taxonomies_stored' );
	}

	/** Store, remove, or leave a featured image through WordPress core. */
	private static function store_featured_image( $post_id, $attachment_id, $supplied ) {
		if ( ! $supplied ) {
			return self::result( true, 'featured_image_unchanged' );
		}
		$attachment_id = function_exists( 'absint' ) ? absint( $attachment_id ) : max( 0, (int) $attachment_id );
		if ( 0 === $attachment_id ) {
			$current = function_exists( 'get_post_thumbnail_id' ) ? (int) get_post_thumbnail_id( $post_id ) : 0;
			if ( 0 === $current ) {
				return self::result( true, 'featured_image_unchanged' );
			}
			if ( ! function_exists( 'delete_post_thumbnail' ) ) {
				return self::result( false, 'featured_image_remove_api_unavailable' );
			}
			$result = delete_post_thumbnail( $post_id );
			return false !== $result ? self::result( true, 'featured_image_removed' ) : self::result( false, 'featured_image_remove_failed' );
		}
		if ( ! function_exists( 'set_post_thumbnail' ) ) {
			return self::result( false, 'featured_image_api_unavailable' );
		}
		$result = set_post_thumbnail( $post_id, $attachment_id );
		return false !== $result ? self::result( true, 'featured_image_stored' ) : self::result( false, 'featured_image_store_failed' );
	}

	/** Build the minimum current article projection used by transition checks. */
	private static function article_projection( $post_id, $target_state ) {
		$term_slug = static function ( $taxonomy ) use ( $post_id ) {
			$terms = function_exists( 'get_the_terms' ) ? get_the_terms( $post_id, $taxonomy ) : array();
			return is_array( $terms ) && ! empty( $terms[0]->slug ) ? (string) $terms[0]->slug : '';
		};
		return array(
			'title' => function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_title', $post_id ) : '',
			'content' => function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_content', $post_id ) : '',
			'summary' => function_exists( 'get_post_meta' ) ? (string) get_post_meta( $post_id, '_sabri_news_summary', true ) : '',
			'section' => $term_slug( 'sabri_news_section' ),
			'article_type' => $term_slug( 'sabri_news_type' ),
			'reviewing_editor_id' => function_exists( 'get_post_meta' ) ? (int) get_post_meta( $post_id, '_sabri_news_reviewing_editor_id', true ) : 0,
			'medical_reviewer_id' => function_exists( 'get_post_meta' ) ? (int) get_post_meta( $post_id, '_sabri_news_medical_reviewer_id', true ) : 0,
			'fact_check_required' => function_exists( 'get_post_meta' ) ? (bool) get_post_meta( $post_id, '_sabri_news_fact_check_required', true ) : false,
			'medical_review_required' => function_exists( 'get_post_meta' ) ? (bool) get_post_meta( $post_id, '_sabri_news_medical_review_required', true ) : false,
			'schedule_at_utc' => function_exists( 'get_post_meta' ) ? (string) get_post_meta( $post_id, NewsSchedulingService::META_KEY, true ) : '',
			'target_state' => $target_state,
		);
	}

	/** Stable application result. */
	private static function result( $success, $code, array $data = array() ) {
		return array( 'success' => (bool) $success, 'code' => (string) $code, 'data' => $data );
	}
}
