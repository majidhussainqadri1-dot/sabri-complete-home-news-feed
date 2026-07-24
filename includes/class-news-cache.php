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

/** Keeps public News cache objects isolated, bounded, versioned, and purgeable. */
final class NewsCache {
	const GROUP = 'sabri_hnf_public_news';
	const VERSION_OPTION = 'sabri_feed_phase4c_cache_version';
	const INDEX_OPTION = 'sabri_feed_phase4c_cache_index';
	const DEFAULT_TTL = 300;
	const MAX_INDEX_KEYS = 500;

	/** Register cache invalidation hooks. */
	public static function register() {
		if ( ! function_exists( 'add_action' ) ) { return; }
		add_action( 'save_post_' . Phase4Contracts::POST_TYPE, array( __CLASS__, 'invalidate' ), 60, 3 );
		add_action( 'save_post_attachment', array( __CLASS__, 'invalidate_attachment' ), 60, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'invalidate_deleted' ) );
		add_action( 'transition_post_status', array( __CLASS__, 'invalidate_transition' ), 10, 3 );
		add_action( 'set_object_terms', array( __CLASS__, 'invalidate_terms' ), 10, 6 );
		add_action( 'added_post_meta', array( __CLASS__, 'invalidate_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'invalidate_meta' ), 10, 4 );
		add_action( 'deleted_post_meta', array( __CLASS__, 'invalidate_meta' ), 10, 4 );
		add_action( 'updated_option', array( __CLASS__, 'invalidate_option' ), 10, 3 );
	}

	/** Build one privacy-safe public cache key. */
	public static function key( $scope, array $dimensions = array() ) {
		$scope = self::clean_key( $scope );
		if ( '' === $scope ) { $scope = 'unknown'; }
		$payload = array(
			'context'    => self::context_dimensions(),
			'dimensions' => self::normalize_dimensions( $dimensions ),
		);
		return 'sabri_hnf_news_' . md5( $scope . '|' . self::encode( $payload ) );
	}

	/** Read a public cache object. */
	public static function get( $scope, array $dimensions = array() ) {
		if ( ! function_exists( 'get_transient' ) || ! NewsPolicy::public_reads_allowed() ) { return false; }
		return get_transient( self::key( $scope, $dimensions ) );
	}

	/** Store a public cache object. */
	public static function set( $scope, array $dimensions, $value, $ttl = self::DEFAULT_TTL ) {
		$ttl = self::bounded_ttl( $ttl );
		if ( $ttl < 1 || ! function_exists( 'set_transient' ) || ! NewsPolicy::public_reads_allowed() ) { return false; }
		$key = self::key( $scope, $dimensions );
		$stored = set_transient( $key, $value, $ttl );
		if ( $stored ) { self::index_key( $key ); }
		return $stored;
	}

	/** Increment the public-state generation without touching content. */
	public static function invalidate() {
		$version = self::version() + 1;
		if ( function_exists( 'update_option' ) ) { update_option( self::VERSION_OPTION, $version, false ); }
		if ( class_exists( __NAMESPACE__ . '\\FeedQuery' ) ) { FeedQuery::invalidate_cache(); }
		return $version;
	}

	/** Purge only plugin-owned News transients. */
	public static function purge_owned() {
		$keys = function_exists( 'get_option' ) ? get_option( self::INDEX_OPTION, array() ) : array();
		$keys = is_array( $keys ) ? $keys : array();
		$count = 0;
		foreach ( $keys as $key ) {
			if ( is_string( $key ) && 0 === strpos( $key, 'sabri_hnf_news_' ) && function_exists( 'delete_transient' ) ) {
				delete_transient( $key ); $count++;
			}
		}
		if ( function_exists( 'delete_option' ) ) { delete_option( self::INDEX_OPTION ); }
		self::invalidate();
		return $count;
	}

	public static function invalidate_deleted( $post_id ) {
		$type = self::post_type( $post_id );
		if ( Phase4Contracts::POST_TYPE === $type || 'attachment' === $type ) { self::invalidate(); }
	}
	public static function invalidate_transition( $new_status, $old_status, $post ) {
		unset( $new_status, $old_status );
		if ( is_object( $post ) && isset( $post->post_type ) && in_array( $post->post_type, array( Phase4Contracts::POST_TYPE, 'attachment' ), true ) ) { self::invalidate(); }
	}
	public static function invalidate_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		unset( $terms, $tt_ids );
		if ( in_array( $taxonomy, Phase4Contracts::taxonomies(), true ) && Phase4Contracts::POST_TYPE === self::post_type( $object_id ) ) { self::invalidate(); }
	}
	public static function invalidate_attachment( $post_id, $post, $update ) {
		unset( $post_id, $post, $update ); self::invalidate();
	}
	public static function invalidate_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
		unset( $meta_id, $meta_value );
		$public_keys = array(
			Phase4Contracts::WORKFLOW_META_KEY, NewsPublicSnapshot::SNAPSHOT_META, '_thumbnail_id', '_wp_attachment_image_alt',
			'_sabri_news_subtitle', '_sabri_news_summary', '_sabri_news_language', '_sabri_news_priority', '_sabri_news_public_author_approved',
			'_sabri_news_public_institution_name', '_sabri_news_public_institution_url', '_sabri_news_public_institution_slug',
			'_sabri_news_reviewing_editor_id', '_sabri_news_featured_image_credit', '_sabri_news_disclaimer', '_sabri_news_conflict_disclosure',
			'_sabri_news_correction_status', '_sabri_news_correction_notice', '_sabri_news_retraction_notice', '_sabri_news_editor_pick',
		);
		if ( in_array( $meta_key, $public_keys, true ) && in_array( self::post_type( $post_id ), array( Phase4Contracts::POST_TYPE, 'attachment' ), true ) ) { self::invalidate(); }
	}
	public static function invalidate_option( $option, $old_value, $value ) {
		unset( $old_value, $value );
		if ( in_array( $option, array( NewsFeatureSettings::OPTION_NAME, Settings::OPTION_NAME ), true ) ) { self::invalidate(); }
	}

	/** Return current cache generation. */
	public static function version() {
		$version = function_exists( 'get_option' ) ? (int) get_option( self::VERSION_OPTION, 1 ) : 1;
		return max( 1, $version );
	}

	/** Include every privacy/staleness dimension in every key. */
	private static function context_dimensions() {
		return array(
			'blog_id'       => function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1,
			'cache_version' => self::version(),
			'gate'          => NewsFeatureSettings::enabled( 'editorial_news_enabled' ) ? 1 : 0,
			'emergency'     => class_exists( __NAMESPACE__ . '\\SafeMode' ) && SafeMode::public_features_disabled() ? 1 : 0,
			'language'      => function_exists( 'determine_locale' ) ? (string) determine_locale() : ( function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US' ),
		);
	}

	private static function normalize_dimensions( array $dimensions ) {
		$out = array(); ksort( $dimensions, SORT_STRING );
		foreach ( $dimensions as $key => $value ) {
			$key = self::clean_key( $key ); if ( '' === $key ) { continue; }
			if ( is_array( $value ) ) { $out[ $key ] = self::normalize_dimensions( $value ); }
			elseif ( is_bool( $value ) ) { $out[ $key ] = $value ? 1 : 0; }
			elseif ( is_int( $value ) || is_float( $value ) ) { $out[ $key ] = $value; }
			elseif ( is_string( $value ) ) { $out[ $key ] = substr( $value, 0, 256 ); }
		}
		return $out;
	}
	private static function bounded_ttl( $ttl ) { $ttl = is_numeric( $ttl ) ? (int) $ttl : self::DEFAULT_TTL; return min( 3600, max( 0, $ttl ) ); }
	private static function post_type( $post_id ) { return function_exists( 'get_post_type' ) ? (string) get_post_type( $post_id ) : ( function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_type', $post_id ) : '' ); }
	private static function encode( array $value ) { return function_exists( 'wp_json_encode' ) ? (string) wp_json_encode( $value ) : (string) json_encode( $value ); }
	private static function clean_key( $value ) { return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) ); }
	private static function index_key( $key ) {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) { return; }
		$keys = get_option( self::INDEX_OPTION, array() ); $keys = is_array( $keys ) ? $keys : array();
		$keys[] = $key; $keys = array_values( array_unique( array_slice( $keys, -self::MAX_INDEX_KEYS ) ) );
		update_option( self::INDEX_OPTION, $keys, false );
	}
}
