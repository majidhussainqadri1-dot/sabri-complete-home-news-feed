<?php
/**
 * Phase 3C REST comments and replies.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers bounded comment read and mutation endpoints.
 */
final class RestComments {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		}
	}

	/**
	 * Register Checkpoint 3C routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/posts/(?P<id>\d+)/comments',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'comments' ),
					'permission_callback' => array( __CLASS__, 'public_permission' ),
					'args'                => array( 'id' => self::id_argument() ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'create_comment' ),
					'permission_callback' => array( __CLASS__, 'private_permission' ),
					'args'                => array(
						'id'        => self::id_argument(),
						'content'   => array( 'required' => true, 'sanitize_callback' => array( __CLASS__, 'sanitize_content' ) ),
						'parent_id' => array( 'default' => 0, 'sanitize_callback' => array( __CLASS__, 'sanitize_non_negative_id' ), 'validate_callback' => array( __CLASS__, 'validate_non_negative_id' ) ),
					),
				),
			)
		);

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/comments/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( __CLASS__, 'update_comment' ),
					'permission_callback' => array( __CLASS__, 'private_permission' ),
					'args'                => array(
						'id'      => self::id_argument(),
						'content' => array( 'required' => true, 'sanitize_callback' => array( __CLASS__, 'sanitize_content' ) ),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'delete_comment' ),
					'permission_callback' => array( __CLASS__, 'private_permission' ),
					'args'                => array( 'id' => self::id_argument() ),
				),
			)
		);
	}

	/**
	 * Public read permission; callback enforces post visibility.
	 *
	 * @return bool
	 */
	public static function public_permission() {
		return Phase3FeatureSettings::enabled( 'comments_enabled' );
	}

	/**
	 * Authenticated nonce permission for writes.
	 *
	 * @param mixed $request Request.
	 * @return bool
	 */
	public static function private_permission( $request ) {
		return Phase3FeatureSettings::enabled( 'comments_enabled' ) && function_exists( 'is_user_logged_in' ) && is_user_logged_in()
			&& CanonicalIdentityAdapter::current_action_ready( (int) get_current_user_id() )
			&& InteractionPermissions::nonce_valid( self::request_nonce( $request ) );
	}

	/**
	 * Read thread callback.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function comments( $request ) {
		$post_id = self::request_id( $request );
		$result  = CommentService::thread( $post_id );
		return self::response( self::with_html( $result, $post_id ) );
	}

	/**
	 * Create callback.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function create_comment( $request ) {
		$post_id = self::request_id( $request );
		$result  = CommentService::create(
			$post_id,
			self::request_param( $request, 'content' ),
			self::sanitize_non_negative_id( self::request_param( $request, 'parent_id' ) ),
			self::request_nonce( $request )
		);
		return self::response( self::with_html( $result, $post_id ) );
	}

	/**
	 * Update callback.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function update_comment( $request ) {
		$comment_id = self::request_id( $request );
		$result = CommentService::update( $comment_id, self::request_param( $request, 'content' ), self::request_nonce( $request ) );
		return self::response( self::with_comment_html( $result ) );
	}

	/**
	 * Delete callback.
	 *
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function delete_comment( $request ) {
		$result = CommentService::delete( self::request_id( $request ), self::request_nonce( $request ) );
		return self::response( self::with_comment_html( $result ) );
	}

	/**
	 * Add rendered thread HTML when a post ID is known.
	 *
	 * @param array<string,mixed> $result Result.
	 * @param int                 $post_id Post ID.
	 * @return array<string,mixed>
	 */
	private static function with_html( array $result, $post_id ) {
		if ( ! empty( $result['ok'] ) ) {
			$result['data']['html'] = CommentRuntime::render_thread( $post_id );
		}
		return $result;
	}

	/**
	 * Add rendered thread HTML using the mutation thread payload.
	 *
	 * @param array<string,mixed> $result Result.
	 * @return array<string,mixed>
	 */
	private static function with_comment_html( array $result ) {
		if ( ! empty( $result['ok'] ) && ! empty( $result['data']['thread']['post_id'] ) ) {
			$result['data']['html'] = CommentRuntime::render_thread( (int) $result['data']['thread']['post_id'] );
		}
		return $result;
	}

	/**
	 * ID argument.
	 *
	 * @return array<string,mixed>
	 */
	private static function id_argument() {
		return array(
			'required'          => true,
			'sanitize_callback' => array( __CLASS__, 'sanitize_id' ),
			'validate_callback' => array( __CLASS__, 'validate_id' ),
		);
	}

	/**
	 * Validate a positive ID.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[0-9]+$/', (string) $value ) && (int) $value > 0;
	}

	/**
	 * Sanitize a positive ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function sanitize_id( $value ) {
		return self::validate_id( $value ) ? (int) $value : 0;
	}

	/**
	 * Validate parent ID including zero.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function validate_non_negative_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[0-9]+$/', (string) $value );
	}

	/**
	 * Sanitize parent ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function sanitize_non_negative_id( $value ) {
		return self::validate_non_negative_id( $value ) ? (int) $value : 0;
	}

	/**
	 * Sanitize plain-text content without changing validation semantics.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function sanitize_content( $value ) {
		$value = function_exists( 'wp_unslash' ) ? wp_unslash( $value ) : $value;
		return function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $value ) : trim( strip_tags( (string) $value ) );
	}

	/**
	 * Request ID.
	 *
	 * @param mixed $request Request.
	 * @return int
	 */
	private static function request_id( $request ) {
		return self::sanitize_id( self::request_param( $request, 'id' ) );
	}

	/**
	 * Request nonce.
	 *
	 * @param mixed $request Request.
	 * @return string
	 */
	private static function request_nonce( $request ) {
		if ( is_object( $request ) && method_exists( $request, 'get_header' ) ) {
			return sanitize_text_field( $request->get_header( 'X-WP-Nonce' ) );
		}
		if ( is_array( $request ) && isset( $request['_wpnonce'] ) ) {
			return sanitize_text_field( $request['_wpnonce'] );
		}
		return isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
	}

	/**
	 * Request parameter.
	 *
	 * @param mixed  $request Request.
	 * @param string $key Key.
	 * @return mixed
	 */
	private static function request_param( $request, $key ) {
		if ( is_array( $request ) && array_key_exists( $key, $request ) ) {
			return $request[ $key ];
		}
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			return $request->get_param( $key );
		}
		return null;
	}

	/**
	 * Build no-store response because a thread may include current-user pending state.
	 *
	 * @param array<string,mixed> $result Result.
	 * @return mixed
	 */
	private static function response( array $result ) {
		$status = isset( $result['status'] ) ? (int) $result['status'] : 200;
		if ( class_exists( 'WP_REST_Response' ) ) {
			$response = new \WP_REST_Response( $result, $status );
			if ( method_exists( $response, 'header' ) ) {
				$response->header( 'Cache-Control', 'no-store, private' );
			}
			return $response;
		}
		return $result;
	}
}
