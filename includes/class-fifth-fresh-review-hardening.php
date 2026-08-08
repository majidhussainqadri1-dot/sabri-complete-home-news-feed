<?php
/**
 * Fifth fresh ten-round hardening for File 21.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Closes newly discovered cross-plan and dependency-boundary gaps without
 * taking ownership from File 00, File 04, File 20, File 24 or File 26.
 */
final class FifthFreshReviewHardening {
	/** Exact NG30 mutation route. */
	private const NG_ACTION_ROUTE = '/sabri-home-news-feed/v1/next-generation/action';

	/** Social capabilities that never belong to an ordinary non-doctor member. */
	private const SOCIAL_CREATE_CAPS = array(
		'sabri_feed_create_posts',
		'sabri_feed_submit_for_review',
	);

	/** Register fifth-review runtime guards. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'user_has_cap', array( __CLASS__, 'guard_social_publication_capabilities' ), 99, 4 );
			add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_callbacks' ), 25, 3 );
			add_filter( 'posts_results', array( __CLASS__, 'filter_story_results' ), 25, 2 );
		}
	}

	/**
	 * Native fail-closed guard for File 21 social publishing powers.
	 *
	 * File 00 remains the identity/assertion owner. File 21 nevertheless owns
	 * its publication authorization boundary, so a stale/mis-issued generic
	 * capability or can_publish assertion must not turn a non-doctor member
	 * into a public social publisher.
	 *
	 * @param mixed $allcaps Current allcaps map.
	 * @param mixed $caps    Primitive caps requested by WordPress.
	 * @param mixed $args    Capability arguments.
	 * @param mixed $user    WP_User-like subject.
	 * @return mixed
	 */
	public static function guard_social_publication_capabilities( $allcaps, $caps, $args, $user ) {
		unset( $args );
		if ( ! is_array( $allcaps ) || ! is_object( $user ) || empty( $user->ID ) ) {
			return $allcaps;
		}
		$caps = is_array( $caps ) ? array_values( array_unique( array_map( 'strval', $caps ) ) ) : array();
		$relevant = array_merge( self::SOCIAL_CREATE_CAPS, array( 'sabri_feed_publish_posts' ) );
		if ( ! array_intersect( $caps, $relevant ) ) {
			return $allcaps;
		}

		$user_id    = absint( $user->ID );
		$assertions = CanonicalIdentityAdapter::membership_assertions( $user_id );
		$creator    = self::subject_is_allowed_social_creator( $user_id, $assertions );
		$publisher  = self::subject_is_allowed_public_social_publisher( $user_id, $assertions );

		if ( ! $creator ) {
			foreach ( self::SOCIAL_CREATE_CAPS as $capability ) {
				$allcaps[ $capability ] = false;
			}
		}
		if ( ! $publisher ) {
			$allcaps['sabri_feed_publish_posts'] = false;
		}
		return $allcaps;
	}

	/**
	 * Reject unsafe NG30 mutations before their native callback runs.
	 *
	 * @param mixed $response Existing short-circuit response.
	 * @param mixed $handler  Matched REST handler.
	 * @param mixed $request  REST request.
	 * @return mixed
	 */
	public static function before_callbacks( $response, $handler, $request ) {
		unset( $handler );
		if ( null !== $response || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! method_exists( $request, 'get_method' ) ) {
			return $response;
		}
		if ( self::NG_ACTION_ROUTE !== (string) $request->get_route() || 'POST' !== strtoupper( (string) $request->get_method() ) ) {
			return $response;
		}

		$action  = sanitize_key( self::request_scalar( $request, 'action' ) );
		$post_id = absint( self::request_scalar( $request, 'post_id' ) );

		if ( in_array( $action, array( 'repost', 'quote' ), true ) && ! self::strict_public_source_is_shareable( $post_id ) ) {
			return new \WP_Error(
				'ng30_source_policy_unavailable',
				__( 'The source is not currently eligible for public reposting or quoting.', 'sabri-complete-home-news-feed' ),
				array( 'status' => 409 )
			);
		}

		if ( 'editor-update' !== $action || $post_id < 1 ) {
			return $response;
		}
		$input  = self::json_params( $request );
		$fields = isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array();
		if ( array_key_exists( 'story', $fields ) && self::truthy( $fields['story'] ) ) {
			$author_id = function_exists( 'get_post_field' ) ? absint( get_post_field( 'post_author', $post_id ) ) : 0;
			if ( ! self::professional_story_author_is_eligible( $author_id ) ) {
				return new \WP_Error(
					'ng30_story_professional_required',
					__( 'A public professional Story requires a current Founder, Administrator, or verified doctor identity.', 'sabri-complete-home-news-feed' ),
					array( 'status' => 403 )
				);
			}
		}
		return $response;
	}

	/**
	 * Recheck Story author class after the fourth-review public-identity filter.
	 *
	 * @param mixed $posts Query result list.
	 * @param mixed $query WP_Query-like object.
	 * @return mixed
	 */
	public static function filter_story_results( $posts, $query ) {
		if ( ! is_array( $posts ) || ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! self::query_requests_stories( $query->get( 'meta_query' ) ) ) {
			return $posts;
		}
		return array_values(
			array_filter(
				$posts,
				static function ( $post ) {
					$post_id   = is_object( $post ) && isset( $post->ID ) ? absint( $post->ID ) : absint( $post );
					$author_id = is_object( $post ) && isset( $post->post_author ) ? absint( $post->post_author ) : ( function_exists( 'get_post_field' ) ? absint( get_post_field( 'post_author', $post_id ) ) : 0 );
					return $post_id > 0 && self::professional_story_author_is_eligible( $author_id );
				}
			)
		);
	}

	/** Public social creator class: institutional actor or doctor, never a general member. */
	public static function subject_is_allowed_social_creator( $user_id, $assertions = null ) {
		$user_id    = absint( $user_id );
		$assertions = is_array( $assertions ) ? $assertions : CanonicalIdentityAdapter::membership_assertions( $user_id );
		if ( $user_id < 1 || ! CanonicalIdentityAdapter::subject_is_active( $user_id ) || ! empty( $assertions['_contract_error'] ) ) {
			return false;
		}
		$account_class   = sanitize_key( isset( $assertions['account_class'] ) ? $assertions['account_class'] : '' );
		$membership_type = sanitize_key( isset( $assertions['membership_type'] ) ? $assertions['membership_type'] : '' );
		return in_array( $account_class, array( 'founder', 'administrator' ), true ) || 'doctor' === $membership_type;
	}

	/** Public social publisher class: institutional actor or currently verified public doctor. */
	public static function subject_is_allowed_public_social_publisher( $user_id, $assertions = null ) {
		$user_id    = absint( $user_id );
		$assertions = is_array( $assertions ) ? $assertions : CanonicalIdentityAdapter::membership_assertions( $user_id );
		if ( ! self::subject_is_allowed_social_creator( $user_id, $assertions ) ) {
			return false;
		}
		$account_class = sanitize_key( isset( $assertions['account_class'] ) ? $assertions['account_class'] : '' );
		if ( in_array( $account_class, array( 'founder', 'administrator' ), true ) ) {
			return true;
		}
		return 'doctor' === sanitize_key( isset( $assertions['membership_type'] ) ? $assertions['membership_type'] : '' )
			&& ! empty( $assertions['professional_verified'] )
			&& ! empty( $assertions['public_profile_allowed'] );
	}

	/** Strict Story class plus canonical public projection. */
	public static function professional_story_author_is_eligible( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 || ! self::subject_is_allowed_public_social_publisher( $user_id ) ) {
			return false;
		}
		$projection = CanonicalIdentityAdapter::public_projection( $user_id );
		return is_array( $projection )
			&& absint( isset( $projection['id'] ) ? $projection['id'] : 0 ) === $user_id
			&& ! empty( $projection['name'] )
			&& ! empty( $projection['profile_url'] );
	}

	/**
	 * A public Editorial News source has no permissive fallback when its native
	 * NewsPolicy contract is unavailable. Social posts retain the prior exact
	 * public/review/visibility checks.
	 */
	public static function strict_public_source_is_shareable( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 || ! function_exists( 'get_post_status' ) || 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}
		$post_type = function_exists( 'get_post_type' ) ? sanitize_key( get_post_type( $post_id ) ) : '';
		if ( 'sabri_news' === $post_type ) {
			return class_exists( __NAMESPACE__ . '\\NewsPolicy' )
				&& is_callable( array( NewsPolicy::class, 'can_public_read' ) )
				&& (bool) NewsPolicy::can_public_read( $post_id, 'single' );
		}
		return 'post' === $post_type
			&& FourthFreshReviewHardening::public_source_is_shareable( $post_id );
	}

	/** Detect Story queries recursively by the canonical expiry meta key. */
	private static function query_requests_stories( $meta_query ) {
		if ( ! is_array( $meta_query ) ) {
			return false;
		}
		foreach ( $meta_query as $key => $clause ) {
			if ( 'key' === $key && NextGenerationFeed::META_STORY_EXPIRES === $clause ) {
				return true;
			}
			if ( is_array( $clause ) && self::query_requests_stories( $clause ) ) {
				return true;
			}
		}
		return false;
	}

	/** Read JSON parameters without assuming ArrayAccess. */
	private static function json_params( $request ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_json_params' ) ) {
			return array();
		}
		$params = $request->get_json_params();
		return is_array( $params ) ? $params : array();
	}

	/** Read one scalar request parameter. */
	private static function request_scalar( $request, $key ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_param' ) ) {
			return '';
		}
		$value = $request->get_param( $key );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** Normalize boolean-like request values. */
	private static function truthy( $value ) {
		return true === $value || 1 === $value || in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
	}
}
