<?php
/**
 * Second fresh ten-round hardening for the current File 21 governing plans.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Closes defects found by the second independent ten-round review without
 * changing File 21 package/runtime/schema identity.
 */
final class SecondFreshReviewHardening {
	/** Maximum accepted body size for the bounded NG30 mutation endpoint. */
	const MAX_ACTION_BODY_BYTES = 32768;

	/** Maximum free-text size accepted by one NG30 mutation. */
	const MAX_ACTION_TEXT_CHARS = 5000;

	/** Register corrective guards. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			// Run after the first-wave NG30 pre-dispatch guard so its rate limits stay authoritative.
			add_filter( 'rest_pre_dispatch', array( __CLASS__, 'pre_dispatch' ), 9, 3 );

			// The governing 14 controls and ten Home rows are frozen contracts, not extension registries.
			add_filter( 'sabri_hnf_home_control_items', array( __CLASS__, 'freeze_home_controls' ), PHP_INT_MAX, 1 );
			add_filter( 'sabri_hnf_home_rows', array( __CLASS__, 'freeze_home_rows' ), PHP_INT_MAX, 1 );

			// Defense in depth for internal metadata writes, not only the public REST controller.
			add_filter( 'sanitize_post_meta__sabri_hnf_ng_coauthors', array( __CLASS__, 'sanitize_coauthors' ), 20, 4 );
			add_filter( 'sanitize_post_meta__sabri_hnf_ng_story_expires', array( __CLASS__, 'sanitize_story_expiry' ), 20, 4 );
		}
	}

	/**
	 * Correct cross-domain post-context visibility and harden the mutation surface.
	 *
	 * @param mixed $result  Pre-dispatch result.
	 * @param mixed $server  REST server.
	 * @param mixed $request REST request.
	 * @return mixed
	 */
	public static function pre_dispatch( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! method_exists( $request, 'get_method' ) ) {
			return $result;
		}

		$route  = (string) $request->get_route();
		$method = strtoupper( (string) $request->get_method() );
		if ( false === strpos( $route, '/next-generation/' ) ) {
			return $result;
		}

		if ( 'GET' === $method && preg_match( '#/next-generation/post/([0-9]+)/?$#', $route, $matches ) ) {
			return self::post_context_response( absint( $matches[1] ) );
		}

		if ( 'POST' !== $method || false === strpos( $route, '/next-generation/action' ) ) {
			return $result;
		}

		if ( ! self::request_body_within_limit( $request ) ) {
			return self::error( 'request_too_large', __( 'The request is too large.', 'sabri-complete-home-news-feed' ), 413 );
		}

		$action = self::clean_key( self::param( $request, 'action' ) );
		if ( in_array( $action, array( 'quote', 'expert-context', 'qna-question', 'qna-answer' ), true ) ) {
			$text = self::param( $request, 'text' );
			if ( self::text_length( $text ) > self::MAX_ACTION_TEXT_CHARS ) {
				return self::error( 'text_too_large', __( 'The submitted text is too long.', 'sabri-complete-home-news-feed' ), 413 );
			}
		}

		if ( 'editor-update' === $action ) {
			$params = self::json_params( $request );
			$fields = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array();
			$error  = self::validate_editor_fields( $fields );
			if ( null !== $error ) {
				return $error;
			}
		}

		return $result;
	}

	/** Build the public-safe post context through the canonical social/news visibility gate. */
	private static function post_context_response( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 || ! InteractionPermissions::can_view_post( $post_id ) ) {
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

	/** Validate co-author authority and professional Story eligibility before the controller mutates data. */
	private static function validate_editor_fields( array $fields ) {
		if ( array_key_exists( 'coauthors', $fields ) ) {
			if ( ! is_array( $fields['coauthors'] ) ) {
				return self::error( 'coauthors_invalid', __( 'Co-authors must be canonical public identities.', 'sabri-complete-home-news-feed' ), 422 );
			}
			foreach ( $fields['coauthors'] as $candidate ) {
				$user_id = absint( $candidate );
				if ( $user_id < 1 || empty( CanonicalIdentityAdapter::public_projection( $user_id ) ) ) {
					return self::error( 'coauthor_unavailable', __( 'One or more co-authors are not currently eligible for public attribution.', 'sabri-complete-home-news-feed' ), 422 );
				}
			}
		}

		if ( array_key_exists( 'story', $fields ) && self::truthy( $fields['story'] ) ) {
			$user_id = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
			if ( $user_id < 1 || ( ! ComposerPermissions::user_is_privileged_publisher( $user_id ) && ! ComposerPermissions::user_can_moderate() ) ) {
				return self::error( 'story_not_allowed', __( 'Professional Stories require current authorized professional publishing authority.', 'sabri-complete-home-news-feed' ), 403 );
			}
		}

		return null;
	}

	/** Keep co-authors limited to current public canonical identities even for internal metadata writes. */
	public static function sanitize_coauthors( $value, $meta_key = '', $object_type = '', $object_subtype = '' ) {
		unset( $meta_key, $object_type, $object_subtype );
		$values = is_array( $value ) ? $value : array();
		$out    = array();
		foreach ( $values as $candidate ) {
			$user_id = absint( $candidate );
			if ( $user_id < 1 || empty( CanonicalIdentityAdapter::public_projection( $user_id ) ) ) {
				continue;
			}
			$out[] = $user_id;
			if ( count( $out ) >= 12 ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** Enforce the 24-hour professional Story law for every metadata write. */
	public static function sanitize_story_expiry( $value, $meta_key = '', $object_type = '', $object_subtype = '' ) {
		unset( $meta_key, $object_type, $object_subtype );
		$expires = absint( $value );
		if ( $expires < 1 ) {
			return 0;
		}
		$user_id = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
		if ( $user_id < 1 || ( ! ComposerPermissions::user_is_privileged_publisher( $user_id ) && ! ComposerPermissions::user_can_moderate() ) ) {
			return 0;
		}
		$maximum = time() + DAY_IN_SECONDS;
		return min( $expires, $maximum );
	}

	/** Restore the exact governing 14-control registry after all ordinary filters. */
	public static function freeze_home_controls( $items ) {
		unset( $items );
		return array(
			'for-you'              => array( 'label' => __( 'For You', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'most-viral'           => array( 'label' => __( 'Most Viral', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'latest'               => array( 'label' => __( 'Latest', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'founder-updates'      => array( 'label' => __( 'Founder Posts', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'doctors-posts'        => array( 'label' => __( 'Doctors Posts', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'classical-homeopathy' => array( 'label' => __( 'Classical Learning', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'remedies'             => array( 'label' => __( 'Remedies', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'diseases'             => array( 'label' => __( 'Diseases', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'clinical-cases'       => array( 'label' => __( 'Clinical Cases', 'sabri-complete-home-news-feed' ), 'kind' => 'feed' ),
			'videos'               => array( 'label' => __( 'Videos', 'sabri-complete-home-news-feed' ), 'kind' => 'module', 'module' => 'video_wall', 'path' => '/video-wall/' ),
			'reels'                => array( 'label' => __( 'Reels', 'sabri-complete-home-news-feed' ), 'kind' => 'module', 'module' => 'reels', 'path' => '/reels/' ),
			'pdf-books'            => array( 'label' => __( 'PDF Books', 'sabri-complete-home-news-feed' ), 'kind' => 'module', 'module' => 'pdf_library', 'path' => '/pdf-library/' ),
			'clinics'              => array( 'label' => __( 'Clinics', 'sabri-complete-home-news-feed' ), 'kind' => 'module', 'module' => 'appointments', 'path' => '/worldwide-clinic/' ),
			'marketplace'          => array( 'label' => __( 'Marketplace', 'sabri-complete-home-news-feed' ), 'kind' => 'module', 'module' => 'marketplace', 'path' => '/marketplace/' ),
		);
	}

	/** Restore the exact governing ten Home rows after all ordinary filters. */
	public static function freeze_home_rows( $rows ) {
		unset( $rows );
		return array(
			'most-viral-now'            => array( 'label' => __( 'Most Viral Now', 'sabri-complete-home-news-feed' ), 'provider' => 'feed', 'mode' => 'most-viral', 'limit' => 6 ),
			'latest-news'                => array( 'label' => __( 'Latest News', 'sabri-complete-home-news-feed' ), 'provider' => 'news', 'limit' => 6 ),
			'from-founder'               => array( 'label' => __( 'From the Founder', 'sabri-complete-home-news-feed' ), 'provider' => 'feed', 'mode' => 'founder-updates', 'limit' => 6 ),
			'from-verified-doctors'      => array( 'label' => __( 'From Verified Doctors', 'sabri-complete-home-news-feed' ), 'provider' => 'feed', 'mode' => 'doctors-posts', 'limit' => 6 ),
			'learn-classical-homeopathy' => array( 'label' => __( 'Learn Sabri Classical Homeopathy', 'sabri-complete-home-news-feed' ), 'provider' => 'learning', 'limit' => 6 ),
			'videos'                     => array( 'label' => __( 'Videos', 'sabri-complete-home-news-feed' ), 'provider' => 'video_wall', 'limit' => 6 ),
			'reels'                      => array( 'label' => __( 'Reels', 'sabri-complete-home-news-feed' ), 'provider' => 'reels', 'limit' => 6 ),
			'pdf-books'                  => array( 'label' => __( 'PDF Books', 'sabri-complete-home-news-feed' ), 'provider' => 'pdf_library', 'limit' => 6 ),
			'clinics'                    => array( 'label' => __( 'Worldwide Clinics', 'sabri-complete-home-news-feed' ), 'provider' => 'appointments', 'limit' => 6 ),
			'marketplace'                => array( 'label' => __( 'Marketplace', 'sabri-complete-home-news-feed' ), 'provider' => 'marketplace', 'limit' => 6 ),
		);
	}

	/** Confirm a mutation request is bounded before JSON parsing/service work. */
	private static function request_body_within_limit( $request ) {
		$body = is_object( $request ) && method_exists( $request, 'get_body' ) ? (string) $request->get_body() : '';
		if ( '' === $body ) {
			$params = self::json_params( $request );
			$body   = function_exists( 'wp_json_encode' ) ? (string) wp_json_encode( $params ) : (string) json_encode( $params );
		}
		return strlen( $body ) <= self::MAX_ACTION_BODY_BYTES;
	}

	/** Request JSON params. */
	private static function json_params( $request ) {
		if ( is_object( $request ) && method_exists( $request, 'get_json_params' ) ) {
			$params = $request->get_json_params();
			return is_array( $params ) ? $params : array();
		}
		return is_array( $request ) ? $request : array();
	}

	/** Generic request parameter. */
	private static function param( $request, $key ) {
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			return $request->get_param( $key );
		}
		return is_array( $request ) && array_key_exists( $key, $request ) ? $request[ $key ] : '';
	}

	/** Truthy scalar. */
	private static function truthy( $value ) {
		return true === $value || 1 === $value || in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
	}

	/** Unicode-aware bounded text length. */
	private static function text_length( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/** Safe key. */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) );
	}

	/** Standard REST success response. */
	private static function response( array $data, $status ) {
		$payload = array( 'ok' => true, 'data' => $data );
		return class_exists( 'WP_REST_Response' ) ? new \WP_REST_Response( $payload, absint( $status ) ) : $payload;
	}

	/** Standard safe REST error. */
	private static function error( $code, $message, $status ) {
		if ( class_exists( 'WP_Error' ) ) {
			return new \WP_Error( self::clean_key( $code ), sanitize_text_field( $message ), array( 'status' => absint( $status ) ) );
		}
		return array( 'ok' => false, 'code' => self::clean_key( $code ), 'message' => sanitize_text_field( $message ), 'status' => absint( $status ) );
	}
}
