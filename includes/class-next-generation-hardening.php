<?php
/**
 * Security and integrity guards for File 21 next-generation REST surfaces.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps NG30 mutations bounded, fail-closed and privacy-safe. */
final class NextGenerationHardening {
	/** Register REST guards and progressive-enhancement fixes. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'rest_pre_dispatch', array( __CLASS__, 'pre_dispatch' ), 8, 3 );
			add_filter( 'rest_post_dispatch', array( __CLASS__, 'post_dispatch' ), 20, 3 );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 35 );
			add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ), 20 );
		}
	}

	/** Load small corrective UX enhancements after the main NG30 client. */
	public static function enqueue_assets() {
		if ( class_exists( __NAMESPACE__ . '\\NextGenerationFeed' ) && ! NextGenerationFeed::assets_required_on_current_request() ) {
			return;
		}
		if ( ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		wp_enqueue_script(
			'sabri-hnf-next-generation-share',
			SABRI_HNF_URL . 'assets/js/next-generation-share.js',
			array( 'sabri-hnf-next-generation' ),
			SABRI_HNF_PACKAGE_VERSION,
			true
		);
		wp_enqueue_script(
			'sabri-hnf-next-generation-accessibility',
			SABRI_HNF_URL . 'assets/js/next-generation-accessibility.js',
			array( 'sabri-hnf-next-generation' ),
			SABRI_HNF_PACKAGE_VERSION,
			true
		);
	}

	/** Register explicit POST handoff so GET digest preview remains side-effect free. */
	public static function register_routes() {
		if ( ! function_exists( 'register_rest_route' ) ) {
			return;
		}
		register_rest_route(
			RestFoundation::NAMESPACE,
			'/next-generation/digest/dispatch',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'dispatch_digest' ),
				'permission_callback' => array( __CLASS__, 'dispatch_permission' ),
			)
		);
	}

	/** Current authenticated/assured member gate for explicit digest dispatch. */
	public static function dispatch_permission( $request = null ) {
		unset( $request );
		$user_id = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
		return $user_id > 0 && CanonicalIdentityAdapter::current_action_ready( $user_id );
	}

	/** Explicit user-requested handoff to File 19. */
	public static function dispatch_digest( $request ) {
		$user_id = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
		$nonce   = is_object( $request ) && method_exists( $request, 'get_header' ) ? sanitize_text_field( $request->get_header( 'X-WP-Nonce' ) ) : '';
		if ( ! InteractionPermissions::nonce_valid( $nonce ) ) {
			return self::error( 'invalid_nonce', __( 'The security token is missing or invalid.', 'sabri-complete-home-news-feed' ), 403 );
		}
		if ( SafeMode::public_features_disabled() ) {
			return self::error( 'next_generation_unavailable', __( 'Knowledge digest delivery is temporarily unavailable.', 'sabri-complete-home-news-feed' ), 503 );
		}
		if ( class_exists( __NAMESPACE__ . '\\Phase5RateLimiter' ) && ! Phase5RateLimiter::allow( 'ng-digest-dispatch', 4, HOUR_IN_SECONDS, $user_id ) ) {
			return self::error( 'rate_limited', __( 'Digest dispatch is temporarily rate limited.', 'sabri-complete-home-news-feed' ), 429 );
		}
		$frequency = self::clean_key( self::param( $request, 'frequency' ) );
		$frequency = in_array( $frequency, array( 'daily', 'weekly' ), true ) ? $frequency : 'daily';
		$items     = self::digest_preview_items( $user_id, $frequency );
		$payload   = NextGenerationIntegrations::dispatch_digest_candidates( $user_id, $frequency, $items );
		return self::response( $payload, 200 );
	}

	/** Guard mutation requests and replace the legacy side-effecting digest GET with a pure preview. */
	public static function pre_dispatch( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! self::is_ng_route( $request ) ) {
			return $result;
		}

		$method = self::method( $request );
		$route  = self::route( $request );

		if ( 'GET' === $method ) {
			$rate_error = self::read_rate_limit_error( $route );
			if ( null !== $rate_error ) {
				return $rate_error;
			}
		}

		if ( 'GET' === $method && preg_match( '#/next-generation/digest/?$#', $route ) ) {
			$user_id = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
			if ( $user_id < 1 || ! CanonicalIdentityAdapter::current_action_ready( $user_id ) ) {
				return self::error( 'authentication_required', __( 'Authentication is required.', 'sabri-complete-home-news-feed' ), 401 );
			}
			$frequency = self::clean_key( self::param( $request, 'frequency' ) );
			$frequency = in_array( $frequency, array( 'daily', 'weekly' ), true ) ? $frequency : 'daily';
			return self::response(
				array(
					'contract_version' => NextGenerationFeed::CONTRACT_VERSION,
					'owner'            => 'file-21',
					'delivery_owner'   => 'file-19',
					'frequency'        => $frequency,
					'preview_only'     => true,
					'items'            => self::digest_preview_items( $user_id, $frequency ),
				),
				200
			);
		}

		if ( 'POST' !== $method || false === strpos( $route, '/next-generation/action' ) ) {
			return $result;
		}

		if ( SafeMode::public_features_disabled() ) {
			return self::error( 'next_generation_unavailable', __( 'Next-generation Feed actions are temporarily unavailable.', 'sabri-complete-home-news-feed' ), 503 );
		}

		$user_id = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
		if ( $user_id < 1 ) {
			return $result;
		}

		if ( class_exists( __NAMESPACE__ . '\\Phase5RateLimiter' ) && ! Phase5RateLimiter::allow( 'ng-action', 60, 60, $user_id ) ) {
			return self::error( 'rate_limited', __( 'Too many actions were attempted. Please wait a moment and try again.', 'sabri-complete-home-news-feed' ), 429 );
		}

		$action = self::clean_key( self::param( $request, 'action' ) );
		if ( in_array( $action, array( 'follow-topic', 'unfollow-topic' ), true ) ) {
			$topic = self::clean_key( self::param( $request, 'topic' ) );
			if ( '' === $topic || ! self::topic_exists( $topic ) ) {
				return self::error( 'topic_unavailable', __( 'The selected topic is unavailable.', 'sabri-complete-home-news-feed' ), 404 );
			}
		}

		return $result;
	}

	/** Prevent user-specific next-generation REST data from being cached or indexed. */
	public static function post_dispatch( $response, $server, $request ) {
		unset( $server );
		if ( ! self::is_private_ng_route( $request ) || ! is_object( $response ) || ! method_exists( $response, 'header' ) ) {
			return $response;
		}
		$response->header( 'Cache-Control', 'no-store, private, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow' );
		return $response;
	}

	/** Pure bounded digest candidate projection. */
	private static function digest_preview_items( $user_id, $frequency ) {
		$user_id = absint( $user_id );
		$since   = time() - ( 'weekly' === $frequency ? WEEK_IN_SECONDS : DAY_IN_SECONDS );
		if ( ! class_exists( 'WP_Query' ) ) {
			return array();
		}
		$query = new \WP_Query(
			array(
				'post_type'      => array( 'post', 'sabri_news' ),
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'date_query'     => array( array( 'after' => gmdate( 'Y-m-d H:i:s', $since ), 'inclusive' => true, 'column' => 'post_date_gmt' ) ),
				'no_found_rows'  => true,
			)
		);
		$items = array();
		foreach ( (array) $query->posts as $post ) {
			$post_id = is_object( $post ) && isset( $post->ID ) ? absint( $post->ID ) : absint( $post );
			if ( $post_id < 1 || ! InteractionPermissions::can_view_post( $post_id, $user_id ) ) {
				continue;
			}
			$items[] = array(
				'id'    => $post_id,
				'title' => get_the_title( $post_id ),
				'url'   => get_permalink( $post_id ),
				'date'  => get_the_date( '', $post_id ),
			);
		}
		return $items;
	}


	/** Bounded abuse control for read-heavy NG30 REST surfaces. */
	private static function read_rate_limit_error( $route ) {
		if ( ! class_exists( __NAMESPACE__ . '\\Phase5RateLimiter' ) ) {
			return null;
		}
		$rules = array(
			'/next-generation/post/'         => array( 'ng-read-post-context', 30, 60 ),
			'/next-generation/compare'       => array( 'ng-read-compare', 60, 60 ),
			'/next-generation/share-card/'   => array( 'ng-read-share-card', 60, 60 ),
			'/next-generation/stories'       => array( 'ng-read-stories', 120, 60 ),
			'/next-generation/my-topics'     => array( 'ng-read-my-topics', 60, 60 ),
			'/next-generation/catch-up'      => array( 'ng-read-catch-up', 30, 60 ),
			'/next-generation/offline-pack'  => array( 'ng-read-offline-pack', 12, 60 ),
			'/next-generation/digest'        => array( 'ng-read-digest', 12, HOUR_IN_SECONDS ),
		);
		foreach ( $rules as $needle => $rule ) {
			if ( false === strpos( $route, $needle ) ) {
				continue;
			}
			$user_id = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
			if ( ! Phase5RateLimiter::allow( $rule[0], $rule[1], $rule[2], $user_id ) ) {
				return self::error( 'rate_limited', __( 'Too many requests were attempted. Please wait and try again.', 'sabri-complete-home-news-feed' ), 429 );
			}
			break;
		}
		return null;
	}

	/** Whether this is a File 21 next-generation REST route. */
	private static function is_ng_route( $request ) {
		return false !== strpos( self::route( $request ), '/next-generation/' );
	}

	/** Whether the route carries user-private state or causes a private mutation. */
	private static function is_private_ng_route( $request ) {
		$route = self::route( $request );
		if ( false === strpos( $route, '/next-generation/' ) ) {
			return false;
		}
		foreach ( array( '/next-generation/action', '/next-generation/my-topics', '/next-generation/catch-up', '/next-generation/offline-pack', '/next-generation/digest' ) as $private_path ) {
			if ( false !== strpos( $route, $private_path ) ) {
				return true;
			}
		}
		return false;
	}

	/** Route string. */
	private static function route( $request ) {
		return is_object( $request ) && method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
	}

	/** HTTP method. */
	private static function method( $request ) {
		return is_object( $request ) && method_exists( $request, 'get_method' ) ? strtoupper( (string) $request->get_method() ) : '';
	}

	/** Request parameter. */
	private static function param( $request, $key ) {
		return is_object( $request ) && method_exists( $request, 'get_param' ) ? $request->get_param( $key ) : '';
	}

	/** Confirm a followed topic is canonical rather than arbitrary user metadata. */
	private static function topic_exists( $slug ) {
		if ( ! function_exists( 'term_exists' ) ) {
			return false;
		}
		$exists = term_exists( $slug, 'sabri_feed_topic' );
		return ! empty( $exists ) && ! ( function_exists( 'is_wp_error' ) && is_wp_error( $exists ) );
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

	/** Standard REST error. */
	private static function error( $code, $message, $status ) {
		if ( class_exists( 'WP_Error' ) ) {
			return new \WP_Error( self::clean_key( $code ), sanitize_text_field( $message ), array( 'status' => absint( $status ) ) );
		}
		return array( 'ok' => false, 'code' => self::clean_key( $code ), 'message' => sanitize_text_field( $message ), 'status' => absint( $status ) );
	}
}
