<?php
/**
 * Fourth fresh ten-round hardening for the File 21 NG30 runtime.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Revalidates public-source, professional-story and canonical-coauthor rules.
 *
 * The NG30 amendment requires Repost/Quote source relations to remain
 * public-safe, Stories to revalidate current professional eligibility, and
 * co-authors to remain canonical public identity projections. These checks
 * sit before the existing mutation lock and also revalidate stored projections
 * at read time so later suspension/revocation cannot leave stale public output.
 */
final class FourthFreshReviewHardening {
	/** Exact NG30 action route. */
	private const ACTION_ROUTE = '/sabri-home-news-feed/v1/next-generation/action';

	/** Prevent recursive metadata filtering. */
	private static $reading_coauthors = false;

	/** Register fourth-review runtime guards. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'before_callbacks' ), 15, 3 );
			add_filter( 'get_post_metadata', array( __CLASS__, 'filter_coauthor_metadata' ), 20, 5 );
			add_filter( 'posts_results', array( __CLASS__, 'filter_story_results' ), 20, 2 );
		}
	}

	/**
	 * Reject unsafe source publication and invalid Story/coauthor mutations.
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
		if ( self::ACTION_ROUTE !== (string) $request->get_route() || 'POST' !== strtoupper( (string) $request->get_method() ) ) {
			return $response;
		}

		$action  = sanitize_key( self::request_scalar( $request, 'action' ) );
		$post_id = absint( self::request_scalar( $request, 'post_id' ) );

		if ( in_array( $action, array( 'repost', 'quote' ), true ) && ! self::public_source_is_shareable( $post_id ) ) {
			return new \WP_Error(
				'ng30_source_not_public',
				__( 'Only a currently public, approved source may be reposted or quoted.', 'sabri-complete-home-news-feed' ),
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
			if ( ! self::story_author_is_eligible( $author_id ) ) {
				return new \WP_Error(
					'ng30_story_author_ineligible',
					__( 'A professional Story requires a currently eligible public professional publisher.', 'sabri-complete-home-news-feed' ),
					array( 'status' => 403 )
				);
			}
		}

		if ( array_key_exists( 'coauthors', $fields ) ) {
			$author_id = function_exists( 'get_post_field' ) ? absint( get_post_field( 'post_author', $post_id ) ) : 0;
			$ids       = self::normalize_ids( $fields['coauthors'], 12, $author_id );
			foreach ( $ids as $user_id ) {
				if ( ! self::canonical_public_identity( $user_id ) ) {
					return new \WP_Error(
						'ng30_coauthor_not_public',
						__( 'Every co-author must resolve to a current canonical public identity.', 'sabri-complete-home-news-feed' ),
						array( 'status' => 409 )
					);
				}
			}
		}

		return $response;
	}

	/**
	 * Revalidate stored coauthors whenever the public/runtime projection reads them.
	 *
	 * @param mixed  $value     Existing filtered value.
	 * @param int    $object_id Post ID.
	 * @param string $meta_key  Metadata key.
	 * @param bool   $single    Single-value request.
	 * @param string $meta_type Metadata type.
	 * @return mixed
	 */
	public static function filter_coauthor_metadata( $value, $object_id, $meta_key, $single, $meta_type ) {
		if ( null !== $value || self::$reading_coauthors || 'post' !== $meta_type || NextGenerationFeed::META_COAUTHORS !== $meta_key || ! function_exists( 'get_metadata_raw' ) ) {
			return $value;
		}

		self::$reading_coauthors = true;
		try {
			$raw = get_metadata_raw( 'post', absint( $object_id ), $meta_key, true );
			$ids = self::normalize_ids( $raw, 12 );
			$ids = array_values(
				array_filter(
					$ids,
					static function ( $user_id ) {
						return self::canonical_public_identity( $user_id );
					}
				)
			);
			return $single ? $ids : array( $ids );
		} finally {
			self::$reading_coauthors = false;
		}
	}

	/**
	 * Remove expired/revoked professional authors from Story query results.
	 *
	 * @param array<int,mixed> $posts Query posts.
	 * @param mixed            $query WP_Query.
	 * @return array<int,mixed>
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
					return $post_id > 0 && self::story_author_is_eligible( $author_id );
				}
			)
		);
	}

	/** A Repost/Quote source must itself be currently public and approved. */
	public static function public_source_is_shareable( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 || ! function_exists( 'get_post_status' ) || 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}
		$post_type = function_exists( 'get_post_type' ) ? sanitize_key( get_post_type( $post_id ) ) : '';
		if ( ! in_array( $post_type, array( 'post', 'sabri_news' ), true ) ) {
			return false;
		}
		if ( 'post' === $post_type ) {
			return 'public' === PostMetadata::visibility( $post_id )
				&& PostMetadata::review_state_publicly_visible( $post_id )
				&& InteractionPermissions::can_view_post( $post_id, 0 );
		}
		if ( class_exists( __NAMESPACE__ . '\\NewsPolicy' ) && is_callable( array( NewsPolicy::class, 'can_public_read' ) ) ) {
			return (bool) NewsPolicy::can_public_read( $post_id, 'single' );
		}
		return InteractionPermissions::can_view_post( $post_id, 0 );
	}

	/** Story authors must remain current, public and professionally authorized. */
	public static function story_author_is_eligible( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 || ! CanonicalIdentityAdapter::subject_is_active( $user_id ) || ! self::canonical_public_identity( $user_id ) ) {
			return false;
		}
		return CanonicalIdentityAdapter::is_founder( $user_id )
			|| CanonicalIdentityAdapter::is_verified_doctor( $user_id )
			|| CanonicalIdentityAdapter::is_trusted_publisher( $user_id );
	}

	/** Current File 00/File 09-backed public identity projection. */
	private static function canonical_public_identity( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id < 1 ) {
			return false;
		}
		$projection = CanonicalIdentityAdapter::public_projection( $user_id );
		return is_array( $projection ) && ! empty( $projection['id'] ) && absint( $projection['id'] ) === $user_id && ! empty( $projection['name'] ) && ! empty( $projection['profile_url'] );
	}

	/** Detect a Story query by the File 21 expiry meta key. */
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

	/** Normalize bounded user IDs. */
	private static function normalize_ids( $values, $limit, $exclude = 0 ) {
		$values = is_array( $values ) ? $values : array();
		$ids    = array_values( array_unique( array_filter( array_map( 'absint', $values ) ) ) );
		if ( $exclude > 0 ) {
			$ids = array_values( array_diff( $ids, array( absint( $exclude ) ) ) );
		}
		return array_slice( $ids, 0, max( 1, absint( $limit ) ) );
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
