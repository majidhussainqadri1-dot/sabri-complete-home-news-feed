<?php
/**
 * Public Editorial News cache boundary.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps public News cache objects isolated, bounded, and versioned. */
final class NewsCache {
	const GROUP = 'sabri_hnf_public_news';
	const VERSION_OPTION = 'sabri_feed_phase4c_cache_version';
	const DEFAULT_TTL = 300;

	/** Register cache invalidation hooks. */
	public static function register() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'save_post_' . Phase4Contracts::POST_TYPE, array( __CLASS__, 'invalidate' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'invalidate_deleted' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'invalidate_transition' ), 10, 3 );
		add_action( 'set_object_terms', array( __CLASS__, 'invalidate_terms' ), 10, 6 );
		add_action( 'updated_option', array( __CLASS__, 'invalidate_option' ), 10, 3 );
	}

	/** Build one privacy-safe public cache key. */
	public static function key( $scope, array $dimensions = array() ) {
		$scope = self::clean_key( $scope );
		if ( '' === $scope ) {
			$scope = 'unknown';
		}
		$dimensions = self::normalize_dimensions( $dimensions );
		return 'sabri_hnf_news_' . md5( self::version() . '|' . $scope . '|' . self::encode( $dimensions ) );
	}

	/** Read a public cache object. */
	public static function get( $scope, array $dimensions = array() ) {
		if ( ! function_exists( 'get_transient' ) ) {
			return false;
		}
		return get_transient( self::key( $scope, $dimensions ) );
	}

	/** Store a public cache object. */
	public static function set( $scope, array $dimensions, $value, $ttl = self::DEFAULT_TTL ) {
		$ttl = self::bounded_ttl( $ttl );
		if ( $ttl < 1 || ! function_exists( 'set_transient' ) ) {
			return false;
		}
		return set_transient( self::key( $scope, $dimensions ), $value, $ttl );
	}

	/** Increment the generation without deleting data. */
	public static function invalidate() {
		$version = self::version() + 1;
		if ( function_exists( 'update_option' ) ) {
			update_option( self::VERSION_OPTION, $version, false );
		}
		if ( class_exists( __NAMESPACE__ . '\\FeedQuery' ) ) {
			FeedQuery::invalidate_cache();
		}
		return $version;
	}

	/** Invalidate only when a deleted object was Editorial News. */
	public static function invalidate_deleted( $post_id ) {
		if ( Phase4Contracts::POST_TYPE === self::post_type( $post_id ) ) {
			self::invalidate();
		}
	}

	/** Invalidate only Editorial News status transitions. */
	public static function invalidate_transition( $new_status, $old_status, $post ) {
		unset( $new_status, $old_status );
		if ( is_object( $post ) && isset( $post->post_type ) && Phase4Contracts::POST_TYPE === $post->post_type ) {
			self::invalidate();
		}
	}

	/** Invalidate only Editorial News taxonomy assignments. */
	public static function invalidate_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		unset( $terms, $tt_ids );
		if ( in_array( $taxonomy, Phase4Contracts::taxonomies(), true ) && Phase4Contracts::POST_TYPE === self::post_type( $object_id ) ) {
			self::invalidate();
		}
	}

	/** Invalidate on the dedicated Phase 4 feature option only. */
	public static function invalidate_option( $option, $old_value, $value ) {
		unset( $old_value, $value );
		if ( NewsFeatureSettings::OPTION_NAME === $option ) {
			self::invalidate();
		}
	}

	/** Return current cache generation. */
	public static function version() {
		$version = function_exists( 'get_option' ) ? (int) get_option( self::VERSION_OPTION, 1 ) : 1;
		return max( 1, $version );
	}

	/** Normalize dimensions recursively and reject objects/resources. */
	private static function normalize_dimensions( array $dimensions ) {
		$out = array();
		ksort( $dimensions, SORT_STRING );
		foreach ( $dimensions as $key => $value ) {
			$key = self::clean_key( $key );
			if ( '' === $key ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$out[ $key ] = self::normalize_dimensions( $value );
			} elseif ( is_bool( $value ) ) {
				$out[ $key ] = $value ? 1 : 0;
			} elseif ( is_int( $value ) || is_float( $value ) ) {
				$out[ $key ] = $value;
			} elseif ( is_string( $value ) ) {
				$out[ $key ] = substr( $value, 0, 256 );
			}
		}
		return $out;
	}

	/** Bound cache duration. */
	private static function bounded_ttl( $ttl ) {
		$ttl = is_numeric( $ttl ) ? (int) $ttl : self::DEFAULT_TTL;
		return min( 3600, max( 0, $ttl ) );
	}

	/** Return an exact post type safely. */
	private static function post_type( $post_id ) {
		return function_exists( 'get_post_type' ) ? (string) get_post_type( $post_id ) : (string) get_post_field( 'post_type', $post_id );
	}

	/** Encode without user/session state. */
	private static function encode( array $value ) {
		return function_exists( 'wp_json_encode' ) ? (string) wp_json_encode( $value ) : (string) json_encode( $value );
	}

	/** Sanitize one internal cache token. */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) );
	}
}
