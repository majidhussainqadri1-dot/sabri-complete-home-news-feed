<?php
/**
 * Third fresh ten-round hardening for the File 21 NG30 runtime.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps safe REST reads side-effect free and serializes shared-meta mutations.
 *
 * File 19 remains the sole digest scheduling/delivery owner. File 21's GET
 * digest route is therefore a read-only candidate preview. NG30 mutations that
 * update shared user/post metadata are guarded by short cross-request locks so
 * concurrent requests fail explicitly instead of silently overwriting state.
 */
final class ThirdFreshReviewHardening {
	/** Exact NG30 action route. */
	private const ACTION_ROUTE = '/sabri-home-news-feed/v1/next-generation/action';

	/** Exact NG30 digest preview route. */
	private const DIGEST_ROUTE = '/sabri-home-news-feed/v1/next-generation/digest';

	/** Lock option prefix. */
	private const LOCK_PREFIX = 'sabri_hnf_ng30_lock_';

	/** Lock lifetime in seconds. */
	private const LOCK_TTL = 30;

	/** @var array<string,string> Request-local lock key => owner token. */
	private static $locks = array();

	/** Register REST hardening after normal route registration. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_callbacks' ), 20, 3 );
			add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'after_callbacks' ), 20, 3 );
		}
	}

	/**
	 * Intercept the stateful GET digest and acquire bounded mutation locks.
	 *
	 * @param mixed $response Existing short-circuit response.
	 * @param mixed $handler  Matched REST handler.
	 * @param mixed $request  WP_REST_Request.
	 * @return mixed
	 */
	public static function before_callbacks( $response, $handler, $request ) {
		unset( $handler );
		if ( null !== $response || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! method_exists( $request, 'get_method' ) ) {
			return $response;
		}

		$route  = (string) $request->get_route();
		$method = strtoupper( (string) $request->get_method() );

		if ( self::DIGEST_ROUTE === $route && 'GET' === $method ) {
			return self::digest_preview( $request );
		}

		if ( self::ACTION_ROUTE !== $route || 'POST' !== $method ) {
			return $response;
		}

		$scope = self::mutation_scope( $request );
		if ( '' === $scope ) {
			return $response;
		}

		if ( ! self::acquire_lock( $scope ) ) {
			return new \WP_Error(
				'ng30_mutation_conflict',
				__( 'Another update to this item is still being processed. Please retry.', 'sabri-complete-home-news-feed' ),
				array( 'status' => 409 )
			);
		}

		return $response;
	}

	/**
	 * Release only locks owned by this request process.
	 *
	 * @param mixed $response Callback response.
	 * @param mixed $handler  Matched REST handler.
	 * @param mixed $request  WP_REST_Request.
	 * @return mixed
	 */
	public static function after_callbacks( $response, $handler, $request ) {
		unset( $handler );
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! method_exists( $request, 'get_method' ) ) {
			return $response;
		}
		if ( self::ACTION_ROUTE === (string) $request->get_route() && 'POST' === strtoupper( (string) $request->get_method() ) ) {
			self::release_all_locks();
		}
		return $response;
	}

	/**
	 * Build a read-only digest preview. No File 19 event is created here.
	 *
	 * @param mixed $request WP_REST_Request.
	 * @return mixed
	 */
	private static function digest_preview( $request ) {
		$user_id = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
		if ( $user_id < 1 || ! CanonicalIdentityAdapter::current_action_ready( $user_id ) ) {
			return new \WP_Error( 'authentication_required', __( 'Authentication is required.', 'sabri-complete-home-news-feed' ), array( 'status' => 401 ) );
		}

		$frequency = self::request_param( $request, 'frequency' );
		$frequency = in_array( $frequency, array( 'daily', 'weekly' ), true ) ? $frequency : 'daily';
		$days      = 'weekly' === $frequency ? 7 : 1;
		$items     = array();

		if ( class_exists( 'WP_Query' ) ) {
			$query = new \WP_Query(
				array(
					'post_type'           => array( 'post', 'sabri_news' ),
					'post_status'         => 'publish',
					'posts_per_page'      => 20,
					'orderby'             => 'date',
					'order'               => 'DESC',
					'ignore_sticky_posts' => true,
					'no_found_rows'       => true,
					'date_query'           => array( array( 'after' => $days . ' days ago', 'inclusive' => true ) ),
				)
			);
			foreach ( (array) $query->posts as $post ) {
				$post_id = is_object( $post ) && isset( $post->ID ) ? absint( $post->ID ) : absint( $post );
				if ( $post_id < 1 || ! PostMetadata::user_can_view( $post_id ) ) {
					continue;
				}
				$items[] = array(
					'id'       => $post_id,
					'title'    => function_exists( 'get_the_title' ) ? sanitize_text_field( get_the_title( $post_id ) ) : '',
					'url'      => function_exists( 'get_permalink' ) ? esc_url_raw( get_permalink( $post_id ) ) : '',
					'type'     => function_exists( 'get_post_type' ) ? sanitize_key( get_post_type( $post_id ) ) : 'post',
					'modified' => function_exists( 'get_post_modified_time' ) ? sanitize_text_field( get_post_modified_time( 'c', true, $post_id ) ) : '',
				);
			}
		}

		$payload = array(
			'contract_version'   => NextGenerationFeed::CONTRACT_VERSION,
			'owner'              => 'file-21',
			'delivery_owner'     => 'file-19',
			'preview_only'       => true,
			'frequency'          => $frequency,
			'item_count'         => count( $items ),
			'items'              => $items,
			'generated_at_utc'   => gmdate( 'c' ),
			'delivery_scheduled' => false,
		);

		return function_exists( 'rest_ensure_response' ) ? rest_ensure_response( $payload ) : $payload;
	}

	/** Resolve a lock scope from the native NG30 action. */
	private static function mutation_scope( $request ) {
		$action  = sanitize_key( self::request_param( $request, 'action' ) );
		$user_id = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
		$post_id = absint( self::request_param( $request, 'post_id' ) );

		if ( in_array( $action, array( 'editor-update', 'expert-context', 'qna-question', 'qna-answer' ), true ) && $post_id > 0 ) {
			return 'post-' . $post_id;
		}
		if ( in_array( $action, array( 'repost', 'quote' ), true ) && $user_id > 0 && $post_id > 0 ) {
			return 'repost-' . $user_id . '-' . $post_id;
		}
		if ( in_array( $action, array( 'follow-topic', 'unfollow-topic', 'progress', 'queue-toggle', 'offline-toggle', 'set-low-bandwidth', 'set-data-saver', 'mark-caught-up', 'recipe' ), true ) && $user_id > 0 ) {
			return 'user-' . $user_id;
		}
		return '';
	}

	/** Atomically acquire one short-lived option lock. */
	private static function acquire_lock( $scope ) {
		if ( ! function_exists( 'add_option' ) || ! function_exists( 'get_option' ) || ! function_exists( 'delete_option' ) ) {
			return false;
		}
		$scope = preg_replace( '/[^a-z0-9_-]/', '-', strtolower( (string) $scope ) );
		$key   = self::LOCK_PREFIX . substr( hash( 'sha256', $scope ), 0, 32 );
		$token = self::lock_token();
		$now   = time();
		$value = array( 'token' => $token, 'expires' => $now + self::LOCK_TTL );

		if ( add_option( $key, $value, '', false ) ) {
			self::$locks[ $key ] = $token;
			return true;
		}

		$current = get_option( $key, array() );
		if ( is_array( $current ) && isset( $current['expires'] ) && absint( $current['expires'] ) < $now ) {
			delete_option( $key );
			if ( add_option( $key, $value, '', false ) ) {
				self::$locks[ $key ] = $token;
				return true;
			}
		}
		return false;
	}

	/** Release request-owned locks without deleting a newer owner's lock. */
	private static function release_all_locks() {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'delete_option' ) ) {
			self::$locks = array();
			return;
		}
		foreach ( self::$locks as $key => $token ) {
			$current = get_option( $key, array() );
			if ( is_array( $current ) && isset( $current['token'] ) && hash_equals( (string) $current['token'], (string) $token ) ) {
				delete_option( $key );
			}
		}
		self::$locks = array();
	}

	/** Generate an unguessable request lock token. */
	private static function lock_token() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( \Throwable $error ) {
			unset( $error );
			return hash( 'sha256', uniqid( 'f21-ng30-', true ) . microtime( true ) );
		}
	}

	/** Read and normalize one scalar request parameter. */
	private static function request_param( $request, $key ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_param' ) ) {
			return '';
		}
		$value = $request->get_param( $key );
		return is_scalar( $value ) ? (string) $value : '';
	}
}
