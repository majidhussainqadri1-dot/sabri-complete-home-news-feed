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

/** Keeps NG30 mutations bounded, fail-closed and taxonomy-valid. */
final class NextGenerationHardening {
	/** Register REST guards. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'rest_pre_dispatch', array( __CLASS__, 'pre_dispatch' ), 8, 3 );
		}
	}

	/** Guard mutation requests before their owner callback executes. */
	public static function pre_dispatch( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! self::is_ng_route( $request ) ) {
			return $result;
		}

		$method = self::method( $request );
		$route  = self::route( $request );
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

	/** Whether this is a File 21 next-generation REST route. */
	private static function is_ng_route( $request ) {
		return false !== strpos( self::route( $request ), '/next-generation/' );
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

	/** Standard REST error. */
	private static function error( $code, $message, $status ) {
		if ( class_exists( 'WP_Error' ) ) {
			return new \WP_Error( self::clean_key( $code ), sanitize_text_field( $message ), array( 'status' => absint( $status ) ) );
		}
		return array( 'ok' => false, 'code' => self::clean_key( $code ), 'message' => sanitize_text_field( $message ), 'status' => absint( $status ) );
	}
}
