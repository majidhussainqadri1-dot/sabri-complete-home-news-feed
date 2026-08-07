<?php
/**
 * Phase 3B REST interactions.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers bounded engagement, reaction, save, and saved-collection endpoints. */
final class RestInteractions {
	/** Register hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		}
	}

	/** Register interaction routes. */
	public static function register_routes() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/posts/(?P<id>\d+)/engagement',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'engagement' ),
				'permission_callback' => array( __CLASS__, 'public_permission' ),
				'args'                => array( 'id' => self::id_argument() ),
			)
		);

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/posts/(?P<id>\d+)/reaction',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'set_reaction' ),
					'permission_callback' => array( __CLASS__, 'private_permission' ),
					'args'                => array(
						'id'            => self::id_argument(),
						'reaction_type' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => array( __CLASS__, 'validate_reaction_type' ),
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'remove_reaction' ),
					'permission_callback' => array( __CLASS__, 'private_permission' ),
					'args'                => array( 'id' => self::id_argument() ),
				),
			)
		);

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/posts/(?P<id>\d+)/save',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_post' ),
					'permission_callback' => array( __CLASS__, 'private_permission' ),
					'args'                => array( 'id' => self::id_argument() ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'unsave_post' ),
					'permission_callback' => array( __CLASS__, 'private_permission' ),
					'args'                => array( 'id' => self::id_argument() ),
				),
			)
		);

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/posts/(?P<id>\d+)/save-collection',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_to_collection' ),
					'permission_callback' => array( __CLASS__, 'private_permission' ),
					'args'                => array(
						'id'         => self::id_argument(),
						'collection' => array(
							'default'           => SavedCollectionService::DEFAULT_COLLECTION,
							'sanitize_callback' => array( __CLASS__, 'sanitize_collection' ),
							'validate_callback' => array( __CLASS__, 'validate_collection' ),
						),
						'note'       => array(
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'tags'       => array(
							'default'           => array(),
							'sanitize_callback' => array( __CLASS__, 'sanitize_tags' ),
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'remove_from_collection' ),
					'permission_callback' => array( __CLASS__, 'private_permission' ),
					'args'                => array(
						'id'         => self::id_argument(),
						'collection' => array(
							'default'           => SavedCollectionService::DEFAULT_COLLECTION,
							'sanitize_callback' => array( __CLASS__, 'sanitize_collection' ),
							'validate_callback' => array( __CLASS__, 'validate_collection' ),
						),
					),
				),
			)
		);

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/me/saves',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'saved_posts' ),
				'permission_callback' => array( __CLASS__, 'private_permission' ),
				'args'                => array(
					'per_page' => array(
						'default'           => 100,
						'sanitize_callback' => array( __CLASS__, 'sanitize_limit' ),
						'validate_callback' => array( __CLASS__, 'validate_limit' ),
					),
				),
			)
		);

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/me/save-collections',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'saved_collections' ),
				'permission_callback' => array( __CLASS__, 'private_permission' ),
				'args'                => array(
					'per_page' => array(
						'default'           => 100,
						'sanitize_callback' => array( __CLASS__, 'sanitize_limit' ),
						'validate_callback' => array( __CLASS__, 'validate_limit' ),
					),
				),
			)
		);

		register_rest_route(
			RestFoundation::NAMESPACE,
			'/me/saves/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'export_saved_collections' ),
				'permission_callback' => array( __CLASS__, 'private_permission' ),
			)
		);
	}

	/** Public endpoint permission; object visibility is checked in the callback. */
	public static function public_permission() {
		return true;
	}

	/** Private endpoint permission. */
	public static function private_permission( $request ) {
		return function_exists( 'is_user_logged_in' ) && is_user_logged_in()
			&& CanonicalIdentityAdapter::current_action_ready( (int) get_current_user_id() )
			&& InteractionPermissions::nonce_valid( self::request_nonce( $request ) );
	}

	/** Engagement callback. */
	public static function engagement( $request ) {
		$post_id = self::request_id( $request );
		if ( $post_id <= 0 || ! PostMetadata::user_can_view( $post_id ) ) {
			return self::response( InteractionResult::error( 'post_unavailable', 'The requested post is unavailable.', array(), 404 ) );
		}
		return self::response( InteractionResult::success( 'engagement', EngagementService::summary( $post_id ), 'Engagement loaded.', 200 ) );
	}

	/** Set reaction callback. */
	public static function set_reaction( $request ) {
		return self::response(
			ReactionService::set(
				self::request_id( $request ),
				self::request_param( $request, 'reaction_type' ),
				self::request_nonce( $request )
			)
		);
	}

	/** Remove reaction callback. */
	public static function remove_reaction( $request ) {
		return self::response( ReactionService::remove( self::request_id( $request ), self::request_nonce( $request ) ) );
	}

	/** Save post callback for the default Saved list. */
	public static function save_post( $request ) {
		return self::response( SaveService::save( self::request_id( $request ), self::request_nonce( $request ) ) );
	}

	/** Unsave post callback for the default Saved list. */
	public static function unsave_post( $request ) {
		return self::response( SaveService::unsave( self::request_id( $request ), self::request_nonce( $request ) ) );
	}

	/** Save into a named private collection with optional note and tags. */
	public static function save_to_collection( $request ) {
		return self::response(
			SavedCollectionService::save(
				self::request_id( $request ),
				self::request_param( $request, 'collection' ),
				self::request_param( $request, 'note' ),
				self::request_param( $request, 'tags' ),
				self::request_nonce( $request )
			)
		);
	}

	/** Remove an item from one named private collection. */
	public static function remove_from_collection( $request ) {
		return self::response(
			SavedCollectionService::unsave(
				self::request_id( $request ),
				self::request_param( $request, 'collection' ),
				self::request_nonce( $request )
			)
		);
	}

	/** Private saved posts callback. */
	public static function saved_posts( $request ) {
		return self::response( SaveService::saved_posts( self::request_nonce( $request ), 0, self::sanitize_limit( self::request_param( $request, 'per_page' ) ) ) );
	}

	/** Private saved collections callback. */
	public static function saved_collections( $request ) {
		return self::response( SavedCollectionService::collections( self::request_nonce( $request ), 0, self::sanitize_limit( self::request_param( $request, 'per_page' ) ) ) );
	}

	/** Portable, private, no-store export callback. */
	public static function export_saved_collections( $request ) {
		return self::response( SavedCollectionService::export( self::request_nonce( $request ) ) );
	}

	/** Validate reaction type. */
	public static function validate_reaction_type( $value ) {
		return is_scalar( $value ) && in_array( sanitize_key( $value ), Phase3Contracts::reaction_types(), true );
	}

	/** Validate private list limit. */
	public static function validate_limit( $value ) {
		return is_scalar( $value ) && preg_match( '/^[0-9]+$/', (string) $value ) && (int) $value >= 1 && (int) $value <= 200;
	}

	/** Sanitize private list limit. */
	public static function sanitize_limit( $value ) {
		return self::validate_limit( $value ) ? (int) $value : 100;
	}

	/** Validate a bounded collection key. */
	public static function validate_collection( $value ) {
		return is_scalar( $value ) && '' !== SavedCollectionService::collection_key( $value ) && strlen( SavedCollectionService::collection_key( $value ) ) <= 64;
	}

	/** Sanitize collection key. */
	public static function sanitize_collection( $value ) {
		return SavedCollectionService::collection_key( $value );
	}

	/** Sanitize collection tags. */
	public static function sanitize_tags( $value ) {
		$value = is_array( $value ) ? $value : preg_split( '/\s*,\s*/', (string) $value );
		return array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_key', $value ) ) ) ), 0, SavedCollectionService::MAX_TAGS );
	}

	/** ID argument contract. */
	private static function id_argument() {
		return array(
			'required'          => true,
			'sanitize_callback' => array( __CLASS__, 'sanitize_id' ),
			'validate_callback' => array( __CLASS__, 'validate_id' ),
		);
	}

	/** Validate ID. */
	public static function validate_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[0-9]+$/', (string) $value ) && (int) $value > 0;
	}

	/** Sanitize ID. */
	public static function sanitize_id( $value ) {
		return self::validate_id( $value ) ? (int) $value : 0;
	}

	/** Get request ID. */
	private static function request_id( $request ) {
		return self::sanitize_id( self::request_param( $request, 'id' ) );
	}

	/** Get request nonce. */
	private static function request_nonce( $request ) {
		if ( is_object( $request ) && method_exists( $request, 'get_header' ) ) {
			return sanitize_text_field( $request->get_header( 'X-WP-Nonce' ) );
		}
		if ( is_array( $request ) && isset( $request['_wpnonce'] ) ) {
			return sanitize_text_field( $request['_wpnonce'] );
		}
		return isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
	}

	/** Get request parameter. */
	private static function request_param( $request, $key ) {
		if ( is_array( $request ) && array_key_exists( $key, $request ) ) {
			return $request[ $key ];
		}
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			return $request->get_param( $key );
		}
		return null;
	}

	/** Build no-store REST response. */
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
