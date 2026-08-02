<?php
/**
 * Authenticated and token-bound Phase 5 REST API.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers strict private writes, operator reads, uploads, and token preview resolution. */
final class Phase5Rest {
	const NAMESPACE = 'sabri-home-news-feed/v1';

	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		}
	}

	public static function routes() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		register_rest_route(
			self::NAMESPACE,
			'/news/(?P<article_id>[1-9][0-9]*)/sources',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'sources_list' ), 'permission_callback' => array( __CLASS__, 'can_manage_sources' ), 'args' => array( 'article_id' => self::id_arg() ) ),
				array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'source_create' ), 'permission_callback' => array( __CLASS__, 'can_manage_sources' ), 'args' => array( 'article_id' => self::id_arg() ) ),
			)
		);
		register_rest_route( self::NAMESPACE, '/sources/(?P<id>[1-9][0-9]*)', array( 'methods' => 'PATCH', 'callback' => array( __CLASS__, 'source_update' ), 'permission_callback' => array( __CLASS__, 'can_manage_sources' ), 'args' => array( 'id' => self::id_arg() ) ) );
		register_rest_route( self::NAMESPACE, '/sources/(?P<id>[1-9][0-9]*)/verify', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'source_verify' ), 'permission_callback' => array( __CLASS__, 'can_verify_sources' ), 'args' => array( 'id' => self::id_arg() ) ) );

		register_rest_route( self::NAMESPACE, '/news/(?P<article_id>[1-9][0-9]*)/reviews', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'review_assign' ), 'permission_callback' => array( __CLASS__, 'can_assign_reviews' ), 'args' => array( 'article_id' => self::id_arg() ) ) );
		register_rest_route( self::NAMESPACE, '/reviews/(?P<id>[1-9][0-9]*)/decision', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'review_decide' ), 'permission_callback' => array( __CLASS__, 'can_authenticated_write' ), 'args' => array( 'id' => self::id_arg() ) ) );

		register_rest_route(
			self::NAMESPACE,
			'/submissions',
			array(
				array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'submissions_list' ), 'permission_callback' => array( __CLASS__, 'logged_in' ) ),
				array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'submission_create' ), 'permission_callback' => array( __CLASS__, 'can_submit' ) ),
			)
		);
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[1-9][0-9]*)', array( 'methods' => 'PATCH', 'callback' => array( __CLASS__, 'submission_update' ), 'permission_callback' => array( __CLASS__, 'can_authenticated_write' ), 'args' => array( 'id' => self::id_arg() ) ) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[1-9][0-9]*)/transition', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'submission_transition' ), 'permission_callback' => array( __CLASS__, 'can_authenticated_write' ), 'args' => array( 'id' => self::id_arg() ) ) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[1-9][0-9]*)/convert', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'submission_convert' ), 'permission_callback' => array( __CLASS__, 'can_manage_submissions' ), 'args' => array( 'id' => self::id_arg() ) ) );
		register_rest_route( self::NAMESPACE, '/submissions/(?P<id>[1-9][0-9]*)/files', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'submission_file_upload' ), 'permission_callback' => array( __CLASS__, 'can_authenticated_write' ), 'args' => array( 'id' => self::id_arg() ) ) );

		register_rest_route( self::NAMESPACE, '/breaking', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'breaking_schedule' ), 'permission_callback' => array( __CLASS__, 'can_manage_breaking' ) ) );
		register_rest_route( self::NAMESPACE, '/breaking/(?P<id>[1-9][0-9]*)/cancel', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'breaking_cancel' ), 'permission_callback' => array( __CLASS__, 'can_manage_breaking' ), 'args' => array( 'id' => self::id_arg() ) ) );

		register_rest_route( self::NAMESPACE, '/news/(?P<article_id>[1-9][0-9]*)/corrections', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'correction_request' ), 'permission_callback' => array( __CLASS__, 'can_manage_corrections' ), 'args' => array( 'article_id' => self::id_arg() ) ) );
		register_rest_route( self::NAMESPACE, '/corrections/(?P<id>[1-9][0-9]*)/approve', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'correction_approve' ), 'permission_callback' => array( __CLASS__, 'can_manage_corrections' ), 'args' => array( 'id' => self::id_arg() ) ) );
		register_rest_route( self::NAMESPACE, '/corrections/(?P<id>[1-9][0-9]*)/publish', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'correction_publish' ), 'permission_callback' => array( __CLASS__, 'can_manage_corrections' ), 'args' => array( 'id' => self::id_arg() ) ) );

		register_rest_route( self::NAMESPACE, '/news/(?P<article_id>[1-9][0-9]*)/preview-token', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'preview_issue' ), 'permission_callback' => array( __CLASS__, 'can_authenticated_write' ), 'args' => array( 'article_id' => self::id_arg() ) ) );
		register_rest_route( self::NAMESPACE, '/preview/(?P<article_id>[1-9][0-9]*)/resolve', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'preview_resolve' ), 'permission_callback' => array( __CLASS__, 'public_permission' ), 'args' => array( 'article_id' => self::id_arg() ) ) );

		register_rest_route( self::NAMESPACE, '/translations', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'translation_link' ), 'permission_callback' => array( __CLASS__, 'can_translate' ) ) );
		register_rest_route( self::NAMESPACE, '/translations/(?P<id>[1-9][0-9]*)/review', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'translation_review' ), 'permission_callback' => array( __CLASS__, 'can_translate' ), 'args' => array( 'id' => self::id_arg() ) ) );
		register_rest_route( self::NAMESPACE, '/translations/(?P<id>[1-9][0-9]*)/approve', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'translation_approve' ), 'permission_callback' => array( __CLASS__, 'can_translate' ), 'args' => array( 'id' => self::id_arg() ) ) );
		register_rest_route( self::NAMESPACE, '/translations/(?P<id>[1-9][0-9]*)/publish', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'translation_publish' ), 'permission_callback' => array( __CLASS__, 'can_translate' ), 'args' => array( 'id' => self::id_arg() ) ) );

		register_rest_route( self::NAMESPACE, '/diagnostics/phase5', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'diagnostics' ), 'permission_callback' => array( __CLASS__, 'can_diagnostics' ) ) );
	}

	public static function sources_list( $request ) {
		return self::response( array( 'success' => true, 'data' => SourceRegistry::list_for_article( $request['article_id'], true ) ), 200 );
	}
	public static function source_create( $request ) {
		$bad = self::allow_json( $request, array( 'source_type', 'evidence_class', 'title', 'publisher', 'public_url', 'doi', 'publication_date', 'public_citation', 'private_notes', 'conflict_flags' ) );
		return $bad ? $bad : self::from_result( SourceRegistry::create( $request['article_id'], self::json( $request ) ) );
	}
	public static function source_update( $request ) {
		$bad = self::allow_json( $request, array( 'source_type', 'evidence_class', 'title', 'publisher', 'public_url', 'doi', 'publication_date', 'public_citation', 'private_notes', 'conflict_flags' ) );
		return $bad ? $bad : self::from_result( SourceRegistry::update( $request['id'], self::json( $request ) ) );
	}
	public static function source_verify( $request ) {
		$bad = self::allow_json( $request, array( 'decision' ) );
		if ( $bad ) return $bad;
		$params = self::json( $request );
		return self::from_result( SourceRegistry::verify( $request['id'], isset( $params['decision'] ) ? (string) $params['decision'] : 'verified' ) );
	}
	public static function review_assign( $request ) {
		$bad = self::allow_json( $request, array( 'revision_id', 'review_type', 'reviewer_user_id' ) );
		if ( $bad ) return $bad;
		$params = self::json( $request );
		return self::from_result( ReviewLedger::assign( $request['article_id'], $params['revision_id'] ?? 0, $params['review_type'] ?? '', $params['reviewer_user_id'] ?? 0 ) );
	}
	public static function review_decide( $request ) {
		$bad = self::allow_json( $request, array( 'decision', 'public_summary', 'private_notes', 'requirements' ) );
		if ( $bad ) return $bad;
		$params = self::json( $request );
		return self::from_result( ReviewLedger::decide( $request['id'], $params['decision'] ?? '', $params ) );
	}
	public static function submissions_list( $request ) {
		unset( $request );
		return self::response( array( 'success' => true, 'data' => SubmissionService::list_for_current_user() ), 200 );
	}
	public static function submission_create( $request ) {
		$bad = self::allow_json( $request, array( 'title', 'summary', 'body', 'source_urls', 'declarations' ) );
		return $bad ? $bad : self::from_result( SubmissionService::create( self::json( $request ) ) );
	}
	public static function submission_update( $request ) {
		$bad = self::allow_json( $request, array( 'title', 'summary', 'body', 'source_urls', 'declarations' ) );
		return $bad ? $bad : self::from_result( SubmissionService::update( $request['id'], self::json( $request ) ) );
	}
	public static function submission_transition( $request ) {
		$bad = self::allow_json( $request, array( 'target', 'private_editor_notes' ) );
		if ( $bad ) return $bad;
		$params = self::json( $request );
		return self::from_result( SubmissionService::transition( $request['id'], $params['target'] ?? '', $params ) );
	}
	public static function submission_convert( $request ) {
		$bad = self::allow_json( $request, array() );
		return $bad ? $bad : self::from_result( SubmissionService::convert_to_article( $request['id'] ) );
	}
	public static function submission_file_upload( $request ) {
		$body = method_exists( $request, 'get_body_params' ) ? $request->get_body_params() : array();
		$body = is_array( $body ) ? $body : array();
		$unknown = array_diff( array_keys( $body ), array( 'consent_status' ) );
		if ( $unknown ) return self::response( array( 'success' => false, 'code' => 'phase5_payload_invalid', 'fields' => array_values( $unknown ) ), 400 );
		if ( ! SubmissionService::find_for_actor( $request['id'], true ) ) return self::response( array( 'success' => false, 'code' => 'phase5_not_found' ), 404 );
		$files = method_exists( $request, 'get_file_params' ) ? $request->get_file_params() : array();
		if ( ! is_array( $files ) || array_keys( $files ) !== array( 'file' ) || ! is_array( $files['file'] ) ) return self::response( array( 'success' => false, 'code' => 'phase5_upload_rejected' ), 400 );
		$file = $files['file'];
		if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) return self::response( array( 'success' => false, 'code' => 'phase5_upload_rejected' ), 400 );
		$validated = UploadSecurity::validate_file( $file['tmp_name'] ?? '', $file['name'] ?? '', $file['type'] ?? '', $file['size'] ?? null );
		if ( empty( $validated['success'] ) ) return self::from_result( $validated );
		if ( ! function_exists( 'media_handle_sideload' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) return self::response( array( 'success' => false, 'code' => 'phase5_query_failed' ), 500 );
		$sideload = $file;
		$sideload['name'] = UploadSecurity::safe_filename( $file['name'] ?? '', $validated['data']['sha256'] );
		$attachment_id = media_handle_sideload( $sideload, 0 );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $attachment_id ) ) return self::response( array( 'success' => false, 'code' => 'phase5_upload_rejected' ), 400 );
		$result = SubmissionService::attach_file_record( $request['id'], $validated, (int) $attachment_id, isset( $body['consent_status'] ) ? (string) $body['consent_status'] : 'not-applicable' );
		if ( empty( $result['success'] ) && function_exists( 'wp_delete_attachment' ) ) wp_delete_attachment( (int) $attachment_id, true );
		return self::from_result( $result );
	}
	public static function breaking_schedule( $request ) {
		$bad = self::allow_json( $request, array( 'article_id', 'starts_at', 'expires_at', 'priority' ) );
		if ( $bad ) return $bad;
		$params = self::json( $request );
		return self::from_result( BreakingNewsService::schedule( $params['article_id'] ?? 0, $params['starts_at'] ?? '', $params['expires_at'] ?? '', $params['priority'] ?? 1 ) );
	}
	public static function breaking_cancel( $request ) {
		$bad = self::allow_json( $request, array() );
		return $bad ? $bad : self::from_result( BreakingNewsService::cancel( $request['id'] ) );
	}
	public static function correction_request( $request ) {
		$bad = self::allow_json( $request, array( 'correction_class', 'private_reason', 'affected_claim', 'previous_revision_id' ) );
		if ( $bad ) return $bad;
		$params = self::json( $request );
		return self::from_result( CorrectionLedger::request( $request['article_id'], $params['correction_class'] ?? '', $params ) );
	}
	public static function correction_approve( $request ) {
		$bad = self::allow_json( $request, array( 'public_note', 'corrected_revision_id' ) );
		return $bad ? $bad : self::from_result( CorrectionLedger::approve( $request['id'], self::json( $request ) ) );
	}
	public static function correction_publish( $request ) {
		$bad = self::allow_json( $request, array() );
		return $bad ? $bad : self::from_result( CorrectionLedger::publish( $request['id'] ) );
	}
	public static function preview_issue( $request ) {
		$bad = self::allow_json( $request, array( 'ttl', 'scope' ) );
		if ( $bad ) return $bad;
		$params = self::json( $request );
		return self::from_result( PreviewTokenService::issue( $request['article_id'], $params['ttl'] ?? 1800, $params['scope'] ?? 'preview' ) );
	}
	public static function preview_resolve( $request ) {
		$bad = self::allow_json( $request, array( 'token', 'scope' ) );
		if ( $bad ) return $bad;
		if ( ! Phase5RateLimiter::allow( 'preview-resolve', 20, HOUR_IN_SECONDS, 0 ) ) return self::response( array( 'success' => false, 'code' => 'phase5_rate_limited' ), 429 );
		$params = self::json( $request );
		return self::from_result( PreviewTokenService::resolve( $request['article_id'], $params['token'] ?? '', $params['scope'] ?? 'preview' ) );
	}
	public static function translation_link( $request ) {
		$bad = self::allow_json( $request, array( 'article_id', 'source_article_id', 'language_tag', 'translator_user_id', 'source_revision_id' ) );
		if ( $bad ) return $bad;
		$params = self::json( $request );
		return self::from_result( TranslationService::link( $params['article_id'] ?? 0, $params['source_article_id'] ?? 0, $params['language_tag'] ?? '', $params['translator_user_id'] ?? 0, $params['source_revision_id'] ?? 0 ) );
	}
	public static function translation_review( $request ) {
		$bad = self::allow_json( $request, array( 'reviewer_user_id', 'revision_id' ) );
		if ( $bad ) return $bad;
		$params = self::json( $request );
		return self::from_result( TranslationService::submit_for_review( $request['id'], $params['reviewer_user_id'] ?? 0, $params['revision_id'] ?? 0 ) );
	}
	public static function translation_approve( $request ) {
		$bad = self::allow_json( $request, array( 'review_id' ) );
		if ( $bad ) return $bad;
		$params = self::json( $request );
		return self::from_result( TranslationService::approve( $request['id'], $params['review_id'] ?? 0 ) );
	}
	public static function translation_publish( $request ) {
		$bad = self::allow_json( $request, array() );
		return $bad ? $bad : self::from_result( TranslationService::publish( $request['id'] ) );
	}
	public static function diagnostics( $request ) {
		unset( $request );
		return self::response( array( 'success' => true, 'data' => Phase5Diagnostics::report() ), 200 );
	}

	public static function logged_in() {
		return function_exists( 'is_user_logged_in' ) && is_user_logged_in();
	}
	public static function public_permission() {
		return true;
	}
	public static function can_authenticated_write( $request ) {
		return self::current_actor_ready() && self::nonce( $request );
	}
	public static function can_manage_sources( $request ) {
		return self::current_actor_ready() && current_user_can( 'manage_news_sources' ) && self::nonce( $request );
	}
	public static function can_verify_sources( $request ) {
		return self::current_actor_ready() && current_user_can( 'verify_news_sources' ) && self::nonce( $request );
	}
	public static function can_assign_reviews( $request ) {
		return self::current_actor_ready() && current_user_can( 'review_editorial_news' ) && self::nonce( $request );
	}
	public static function can_submit( $request ) {
		return self::current_actor_ready() && current_user_can( 'submit_editorial_news' ) && self::nonce( $request );
	}
	public static function can_manage_submissions( $request ) {
		return self::current_actor_ready() && current_user_can( 'manage_news_submissions' ) && self::nonce( $request );
	}
	public static function can_manage_breaking( $request ) {
		return self::current_actor_ready() && current_user_can( 'manage_breaking_news' ) && self::nonce( $request );
	}
	public static function can_manage_corrections( $request ) {
		return self::current_actor_ready() && current_user_can( 'manage_news_corrections' ) && self::nonce( $request );
	}
	public static function can_translate( $request ) {
		return self::current_actor_ready() && current_user_can( 'translate_editorial_news' ) && self::nonce( $request );
	}
	public static function can_diagnostics() {
		return self::current_actor_ready() && current_user_can( 'view_news_diagnostics' );
	}

	private static function current_actor_ready() {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		return self::logged_in() && $user_id > 0 && CanonicalIdentityAdapter::current_action_ready( $user_id );
	}

	private static function nonce( $request ) {
		if ( ! function_exists( 'wp_verify_nonce' ) || ! is_object( $request ) || ! method_exists( $request, 'get_header' ) ) return false;
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return is_string( $nonce ) && '' !== $nonce && false !== wp_verify_nonce( $nonce, 'wp_rest' );
	}
	private static function id_arg() {
		return array(
			'type' => 'integer', 'minimum' => 1, 'required' => true,
			'validate_callback' => static function ( $value ) { return Phase5Contracts::positive_int( $value ) > 0; },
			'sanitize_callback' => 'absint',
		);
	}
	private static function json( $request ) {
		$data = is_object( $request ) && method_exists( $request, 'get_json_params' ) ? $request->get_json_params() : array();
		return is_array( $data ) ? $data : array();
	}
	private static function allow_json( $request, array $allowed ) {
		$params = self::json( $request );
		$unknown = array_diff( array_keys( $params ), $allowed );
		return $unknown ? self::response( array( 'success' => false, 'code' => 'phase5_payload_invalid', 'fields' => array_values( $unknown ) ), 400 ) : null;
	}
	private static function from_result( $result ) {
		$result = is_array( $result ) ? $result : array( 'success' => false, 'code' => 'phase5_query_failed' );
		$status = isset( $result['status'] ) ? (int) $result['status'] : ( empty( $result['success'] ) ? 400 : 200 );
		return self::response( $result, $status );
	}
	private static function response( $data, $status ) {
		if ( class_exists( 'WP_REST_Response' ) ) {
			$response = new \WP_REST_Response( $data, $status );
			$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
			$response->header( 'Pragma', 'no-cache' );
			$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
			$response->header( 'Referrer-Policy', 'no-referrer' );
			return $response;
		}
		return array( 'payload' => $data, 'status' => $status );
	}
}
