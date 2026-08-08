<?php
/**
 * REST API for File 21 next-generation Home and News Feed features.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Provides bounded, permission-aware endpoints for the 30-feature expansion. */
final class RestNextGeneration {
	/** Register REST bootstrap. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		}
	}

	/** Register routes. */
	public static function register_routes() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/next-generation/manifest',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'manifest' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			RestFoundation::NAMESPACE,
			'/next-generation/post/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'post_context' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
			)
		);
		register_rest_route(
			RestFoundation::NAMESPACE,
			'/next-generation/action',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'action' ),
				'permission_callback' => array( __CLASS__, 'authenticated_permission' ),
			)
		);
		register_rest_route(
			RestFoundation::NAMESPACE,
			'/next-generation/my-topics',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'my_topics' ),
				'permission_callback' => array( __CLASS__, 'authenticated_permission' ),
			)
		);
		register_rest_route(
			RestFoundation::NAMESPACE,
			'/next-generation/catch-up',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'catch_up' ),
				'permission_callback' => array( __CLASS__, 'authenticated_permission' ),
			)
		);
		register_rest_route(
			RestFoundation::NAMESPACE,
			'/next-generation/stories',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'stories' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			RestFoundation::NAMESPACE,
			'/next-generation/offline-pack',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'offline_pack' ),
				'permission_callback' => array( __CLASS__, 'authenticated_permission' ),
			)
		);
		register_rest_route(
			RestFoundation::NAMESPACE,
			'/next-generation/compare',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'compare' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			RestFoundation::NAMESPACE,
			'/next-generation/share-card/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'share_card' ),
				'permission_callback' => '__return_true',
				'args'                => array( 'id' => array( 'sanitize_callback' => 'absint' ) ),
			)
		);
		register_rest_route(
			RestFoundation::NAMESPACE,
			'/next-generation/digest',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'digest' ),
				'permission_callback' => array( __CLASS__, 'authenticated_permission' ),
			)
		);
	}

	/** Authenticated, assurance-ready users only. */
	public static function authenticated_permission( $request = null ) {
		unset( $request );
		$user_id = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
		return $user_id > 0 && CanonicalIdentityAdapter::current_action_ready( $user_id );
	}

	/** Public feature manifest. */
	public static function manifest() {
		return self::response(
			array(
				'contract_version' => NextGenerationFeed::CONTRACT_VERSION,
				'features'         => NextGenerationFeed::feature_manifest(),
				'ownership_law'    => 'File 21 owns local post/news/feed semantics; Files 16, 19, 25 and 26 retain their canonical AI, notification, visual and global discovery backends.',
			),
			200
		);
	}

	/** Public-safe post context. */
	public static function post_context( $request ) {
		$post_id = self::param_int( $request, 'id' );
		if ( $post_id < 1 || ! PostMetadata::user_can_view( $post_id ) ) {
			return self::error( 'post_not_found', __( 'The post is unavailable.', 'sabri-complete-home-news-feed' ), 404 );
		}
		return self::response(
			array(
				'post_id'          => $post_id,
				'kind'             => NextGenerationFeed::post_kind( $post_id ),
				'original'         => NextGenerationFeed::original_post( $post_id ),
				'thread'           => NextGenerationFeed::thread_projection( $post_id ),
				'coauthors'        => NextGenerationFeed::coauthors( $post_id ),
				'developing_story' => NextGenerationFeed::developing_story_timeline( $post_id ),
				'expert_context'   => NextGenerationFeed::expert_contexts( $post_id, true ),
				'evidence'         => NextGenerationFeed::evidence_card( $post_id ),
				'source_diversity' => NextGenerationFeed::source_diversity( $post_id ),
				'history'          => NextGenerationFeed::edit_history( $post_id ),
				'share_warning'    => NextGenerationFeed::share_warning( $post_id ),
				'ai_summary'       => NextGenerationIntegrations::ai_summary( $post_id ),
				'ask_article'      => NextGenerationIntegrations::ask_article( $post_id ),
				'translations'     => NextGenerationIntegrations::translation_options( $post_id ),
				'qna'              => NextGenerationFeed::qna( $post_id ),
				'why_trending'     => NextGenerationIntegrations::why_trending( $post_id ),
				'related'          => NextGenerationIntegrations::related_knowledge( $post_id, 6 ),
			),
			200
		);
	}

	/** Mutation dispatcher with nonce and owner-specific authorization. */
	public static function action( $request ) {
		if ( ! self::nonce_valid( $request ) ) {
			return self::error( 'invalid_nonce', __( 'The security token is missing or invalid.', 'sabri-complete-home-news-feed' ), 403 );
		}
		$action = NextGenerationFeed::clean_key( self::param( $request, 'action' ) );
		$input  = self::json_params( $request );
		$result = array();

		switch ( $action ) {
			case 'repost':
			case 'quote':
				$result = NextGenerationFeed::create_repost( self::param_int( $request, 'post_id' ), 'quote' === $action ? self::param_textarea( $request, 'text' ) : '' );
				break;
			case 'editor-update':
				$result = NextGenerationFeed::editor_update( self::param_int( $request, 'post_id' ), isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array() );
				break;
			case 'expert-context':
				$result = NextGenerationFeed::add_expert_context( self::param_int( $request, 'post_id' ), self::param_textarea( $request, 'text' ) );
				break;
			case 'qna-question':
				$result = NextGenerationFeed::qna_action( self::param_int( $request, 'post_id' ), 'question', self::param_textarea( $request, 'text' ) );
				break;
			case 'qna-answer':
				$result = NextGenerationFeed::qna_action( self::param_int( $request, 'post_id' ), 'answer', self::param_textarea( $request, 'text' ), self::param_text( $request, 'question_id' ) );
				break;
			case 'follow-topic':
			case 'unfollow-topic':
			case 'progress':
			case 'queue-toggle':
			case 'offline-toggle':
			case 'set-low-bandwidth':
			case 'set-data-saver':
			case 'mark-caught-up':
			case 'recipe':
				$result = NextGenerationFeed::user_action( $action, $input );
				break;
			default:
				return self::error( 'action_invalid', __( 'The requested action is invalid.', 'sabri-complete-home-news-feed' ), 400 );
		}

		return self::result( $result );
	}

	/** My Topics Feed. */
	public static function my_topics() {
		$user_id = absint( get_current_user_id() );
		return self::response(
			array(
				'state' => NextGenerationFeed::public_user_state( NextGenerationFeed::user_state( $user_id ) ),
				'items' => NextGenerationFeed::my_topics_posts( $user_id, 20 ),
			),
			200
		);
	}

	/** Catch-up feed. */
	public static function catch_up() {
		$user_id = absint( get_current_user_id() );
		return self::response( array( 'items' => NextGenerationFeed::catch_up_posts( $user_id, 20 ) ), 200 );
	}

	/** Public professional Stories. */
	public static function stories() {
		return self::response( array( 'items' => NextGenerationFeed::active_stories( 20 ) ), 200 );
	}

	/** Portable offline reading pack. */
	public static function offline_pack() {
		$user_id = absint( get_current_user_id() );
		return self::response( NextGenerationFeed::offline_pack( $user_id ), 200 );
	}

	/** Compare 2-4 posts/news items. */
	public static function compare( $request ) {
		$raw = self::param( $request, 'ids' );
		$ids = is_array( $raw ) ? $raw : preg_split( '/[\s,]+/', (string) $raw );
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		if ( count( $ids ) < 2 || count( $ids ) > 4 ) {
			return self::error( 'compare_invalid', __( 'Choose between two and four items to compare.', 'sabri-complete-home-news-feed' ), 400 );
		}
		return self::response( array( 'items' => NextGenerationFeed::compare_posts( $ids ) ), 200 );
	}

	/** Shareable knowledge-card semantic payload. */
	public static function share_card( $request ) {
		$post_id = self::param_int( $request, 'id' );
		$payload = NextGenerationFeed::share_card_payload( $post_id );
		if ( ! $payload ) {
			return self::error( 'post_not_found', __( 'The post is unavailable.', 'sabri-complete-home-news-feed' ), 404 );
		}
		return self::response( $payload, 200 );
	}

	/** Daily/weekly digest candidate preview and File 19 handoff. */
	public static function digest( $request ) {
		$frequency = NextGenerationFeed::clean_key( self::param( $request, 'frequency' ) );
		$frequency = in_array( $frequency, array( 'daily', 'weekly' ), true ) ? $frequency : 'daily';
		$user_id   = absint( get_current_user_id() );
		return self::response( NextGenerationFeed::digest_candidates( $user_id, $frequency ), 200 );
	}

	/** Verify standard WordPress REST nonce. */
	private static function nonce_valid( $request ) {
		$nonce = self::header( $request, 'X-WP-Nonce' );
		if ( '' === $nonce ) {
			$nonce = self::param_text( $request, '_wpnonce' );
		}
		return '' !== $nonce && function_exists( 'wp_verify_nonce' ) && (bool) wp_verify_nonce( $nonce, InteractionPermissions::REST_NONCE_ACTION );
	}

	/** Convert service result to REST response. */
	private static function result( $result ) {
		if ( ! is_array( $result ) ) {
			return self::error( 'runtime_error', __( 'The action returned an invalid result.', 'sabri-complete-home-news-feed' ), 500 );
		}
		$status = isset( $result['status'] ) ? absint( $result['status'] ) : 200;
		if ( empty( $result['success'] ) ) {
			return self::error(
				isset( $result['code'] ) ? $result['code'] : 'action_failed',
				isset( $result['message'] ) ? $result['message'] : __( 'The action could not be completed.', 'sabri-complete-home-news-feed' ),
				$status
			);
		}
		return self::response( isset( $result['data'] ) ? $result['data'] : array(), $status );
	}

	/** Request JSON body. */
	private static function json_params( $request ) {
		if ( is_object( $request ) && method_exists( $request, 'get_json_params' ) ) {
			$params = $request->get_json_params();
			return is_array( $params ) ? $params : array();
		}
		return is_array( $request ) ? $request : array();
	}

	/** Generic parameter. */
	private static function param( $request, $key ) {
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			return $request->get_param( $key );
		}
		return is_array( $request ) && array_key_exists( $key, $request ) ? $request[ $key ] : '';
	}

	/** Integer parameter. */
	private static function param_int( $request, $key ) {
		return absint( self::param( $request, $key ) );
	}

	/** Text parameter. */
	private static function param_text( $request, $key ) {
		$value = self::param( $request, $key );
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( (string) $value ) );
	}

	/** Textarea parameter. */
	private static function param_textarea( $request, $key ) {
		$value = self::param( $request, $key );
		return function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $value ) : trim( strip_tags( (string) $value ) );
	}

	/** Request header. */
	private static function header( $request, $name ) {
		if ( is_object( $request ) && method_exists( $request, 'get_header' ) ) {
			return sanitize_text_field( $request->get_header( $name ) );
		}
		return '';
	}

	/** Standard success response. */
	private static function response( array $data, $status ) {
		$payload = array( 'ok' => true, 'data' => $data );
		return class_exists( 'WP_REST_Response' ) ? new \WP_REST_Response( $payload, absint( $status ) ) : $payload;
	}

	/** Standard safe error. */
	private static function error( $code, $message, $status ) {
		$payload = array( 'ok' => false, 'code' => sanitize_key( $code ), 'message' => sanitize_text_field( $message ) );
		return class_exists( 'WP_REST_Response' ) ? new \WP_REST_Response( $payload, absint( $status ) ) : $payload;
	}
}
