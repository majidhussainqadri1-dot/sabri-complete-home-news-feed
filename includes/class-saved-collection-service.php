<?php
/**
 * Private saved collections with notes, tags and bounded export.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Uses the existing File 21 saves table; no duplicate bookmark backend. */
final class SavedCollectionService {
	const META_KEY = '_sabri_hnf_saved_collection_metadata_v1';
	const DEFAULT_COLLECTION = 'default';
	const MAX_COLLECTIONS = 50;
	const MAX_EXPORT_ITEMS = 500;
	const MAX_NOTE_LENGTH = 500;
	const MAX_TAGS = 12;

	/** No WordPress hooks are required; REST/runtime callers invoke the service. */
	public static function register() {}

	/** Save a visible post into a named private collection. */
	public static function save( $post_id, $collection, $note = '', $tags = array(), $nonce = '', $user_id = 0 ) {
		if ( ! Phase3FeatureSettings::enabled( 'saves_enabled' ) ) {
			return InteractionResult::error( 'saves_disabled', 'Saving posts is currently unavailable.', array(), 503 );
		}
		$authorized = InteractionPermissions::authorize_post_write( $post_id, $nonce, $user_id );
		if ( empty( $authorized['ok'] ) ) { return $authorized; }
		$user_id = (int) $authorized['data']['user_id'];
		$post_id = (int) $authorized['data']['post_id'];
		$collection = self::collection_key( $collection );
		if ( ! self::collection_allowed( $user_id, $collection ) ) {
			return InteractionResult::error( 'collection_limit_reached', 'The saved collection limit has been reached.', array(), 409 );
		}
		$limit = InteractionRateLimiter::attempt( 'saves', $user_id, $post_id );
		if ( empty( $limit['ok'] ) ) { return $limit; }

		$current = InteractionQueryRepository::save_record( $user_id, $post_id, $collection );
		if ( is_array( $current ) ) {
			$result = InteractionRepository::update_rows(
				'saves',
				array( 'status' => 'active' ),
				array( 'user_id' => $user_id, 'post_id' => $post_id, 'collection_key' => $collection )
			);
		} else {
			$result = InteractionRepository::insert_row(
				'saves',
				array( 'user_id' => $user_id, 'post_id' => $post_id, 'collection_key' => $collection, 'status' => 'active' )
			);
			if ( empty( $result['ok'] ) && is_array( InteractionQueryRepository::save_record( $user_id, $post_id, $collection ) ) ) {
				$result = InteractionRepository::update_rows(
					'saves',
					array( 'status' => 'active' ),
					array( 'user_id' => $user_id, 'post_id' => $post_id, 'collection_key' => $collection )
				);
			}
		}
		if ( empty( $result['ok'] ) ) { return $result; }

		$metadata_result = self::set_item_metadata( $user_id, $collection, $post_id, $note, $tags );
		if ( empty( $metadata_result['ok'] ) ) {
			AuditLog::record( 'saved_collection_metadata_failed', array( 'post_id' => $post_id, 'collection' => $collection ) );
			return $metadata_result;
		}
		AuditLog::record( 'post_saved_to_collection', array( 'post_id' => $post_id, 'collection' => $collection ) );
		return InteractionResult::success( 'post_saved_to_collection', array( 'post_id' => $post_id, 'collection' => $collection ), 'Post saved to collection.', 200 );
	}

	/** Remove a post from one collection while retaining audit/history rows. */
	public static function unsave( $post_id, $collection, $nonce = '', $user_id = 0 ) {
		if ( ! Phase3FeatureSettings::enabled( 'saves_enabled' ) ) {
			return InteractionResult::error( 'saves_disabled', 'Saving posts is currently unavailable.', array(), 503 );
		}
		$authorized = InteractionPermissions::authorize_post_write( $post_id, $nonce, $user_id );
		if ( empty( $authorized['ok'] ) ) { return $authorized; }
		$user_id = (int) $authorized['data']['user_id'];
		$post_id = (int) $authorized['data']['post_id'];
		$collection = self::collection_key( $collection );
		$current = InteractionQueryRepository::save_record( $user_id, $post_id, $collection );
		if ( is_array( $current ) && 'active' === sanitize_key( isset( $current['status'] ) ? $current['status'] : '' ) ) {
			$result = InteractionRepository::update_rows(
				'saves',
				array( 'status' => 'removed' ),
				array( 'user_id' => $user_id, 'post_id' => $post_id, 'collection_key' => $collection )
			);
			if ( empty( $result['ok'] ) ) { return $result; }
		}
		self::remove_item_metadata( $user_id, $collection, $post_id );
		AuditLog::record( 'post_removed_from_collection', array( 'post_id' => $post_id, 'collection' => $collection ) );
		return InteractionResult::success( 'post_removed_from_collection', array( 'post_id' => $post_id, 'collection' => $collection ), 'Post removed from collection.', 200 );
	}

	/** Return private collections and visibility-safe items. */
	public static function collections( $nonce = '', $user_id = 0, $limit = 100 ) {
		$user_id = InteractionPermissions::authenticated_user_id( $user_id );
		if ( $user_id <= 0 ) { return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 ); }
		if ( ! CanonicalIdentityAdapter::current_action_ready( $user_id ) ) { return InteractionResult::error( 'identity_assurance_required', 'Current account assurance is required.', array(), 403 ); }
		if ( ! InteractionPermissions::nonce_valid( $nonce ) ) { return InteractionResult::error( 'invalid_nonce', 'The security token is missing or invalid.', array(), 403 ); }
		$limit = min( 200, max( 1, (int) $limit ) );
		$rows = self::active_rows( $user_id, $limit * self::MAX_COLLECTIONS );
		$metadata = self::metadata( $user_id );
		$collections = array();
		foreach ( $rows as $row ) {
			$post_id = isset( $row['post_id'] ) ? absint( $row['post_id'] ) : 0;
			$collection = self::collection_key( isset( $row['collection_key'] ) ? $row['collection_key'] : self::DEFAULT_COLLECTION );
			if ( $post_id <= 0 || ! PostMetadata::user_can_view( $post_id, $user_id ) ) { continue; }
			if ( ! isset( $collections[ $collection ] ) ) {
				$collections[ $collection ] = array( 'key' => $collection, 'items' => array() );
			}
			if ( count( $collections[ $collection ]['items'] ) >= $limit ) { continue; }
			$item_meta = isset( $metadata['items'][ $collection ][ (string) $post_id ] ) && is_array( $metadata['items'][ $collection ][ (string) $post_id ] ) ? $metadata['items'][ $collection ][ (string) $post_id ] : array();
			$collections[ $collection ]['items'][] = array(
				'id' => $post_id,
				'title' => function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : '',
				'permalink' => function_exists( 'get_permalink' ) ? (string) get_permalink( $post_id ) : '',
				'note' => isset( $item_meta['note'] ) ? (string) $item_meta['note'] : '',
				'tags' => isset( $item_meta['tags'] ) && is_array( $item_meta['tags'] ) ? $item_meta['tags'] : array(),
			);
		}
		return InteractionResult::success( 'saved_collections', array( 'collections' => array_values( $collections ), 'count' => count( $collections ) ), 'Saved collections loaded.', 200 );
	}

	/** Portable bounded JSON-ready export; caller decides transport/download UI. */
	public static function export( $nonce = '', $user_id = 0 ) {
		$result = self::collections( $nonce, $user_id, self::MAX_EXPORT_ITEMS );
		if ( empty( $result['ok'] ) ) { return $result; }
		return InteractionResult::success(
			'saved_collections_export',
			array(
				'format' => 'sabri-saved-collections-v1',
				'exported_at_utc' => gmdate( 'c' ),
				'collections' => isset( $result['data']['collections'] ) ? $result['data']['collections'] : array(),
			),
			'Saved collections export prepared.',
			200
		);
	}

	/** Bounded private rows using File 21's canonical saves table. */
	private static function active_rows( $user_id, $limit ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'prepare' ) ) { return array(); }
		$table = InteractionRepository::table_name( 'saves' );
		if ( '' === $table ) { return array(); }
		$limit = min( self::MAX_EXPORT_ITEMS * self::MAX_COLLECTIONS, max( 1, (int) $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is the plugin-owned canonical saves table; all value inputs are prepared below.
		$sql = $wpdb->prepare( "SELECT post_id, collection_key, updated_at FROM `{$table}` WHERE user_id = %d AND status = %s ORDER BY updated_at DESC, id DESC LIMIT %d", $user_id, 'active', $limit );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is prepared immediately above.
		$rows = $wpdb->get_results( $sql, 'ARRAY_A' );
		return is_array( $rows ) ? $rows : array();
	}

	private static function collection_allowed( $user_id, $collection ) {
		$keys = array();
		foreach ( self::active_rows( $user_id, self::MAX_EXPORT_ITEMS * self::MAX_COLLECTIONS ) as $row ) {
			$keys[] = self::collection_key( isset( $row['collection_key'] ) ? $row['collection_key'] : self::DEFAULT_COLLECTION );
		}
		$keys = array_values( array_unique( $keys ) );
		return in_array( $collection, $keys, true ) || count( $keys ) < self::MAX_COLLECTIONS;
	}

	private static function metadata( $user_id ) {
		$stored = function_exists( 'get_user_meta' ) ? get_user_meta( $user_id, self::META_KEY, true ) : array();
		return is_array( $stored ) ? $stored : array( 'items' => array() );
	}

	/** Persist collection item metadata and verify idempotent writes. */
	private static function set_item_metadata( $user_id, $collection, $post_id, $note, $tags ) {
		$meta = self::metadata( $user_id );
		if ( ! isset( $meta['items'] ) || ! is_array( $meta['items'] ) ) { $meta['items'] = array(); }
		if ( ! isset( $meta['items'][ $collection ] ) || ! is_array( $meta['items'][ $collection ] ) ) { $meta['items'][ $collection ] = array(); }
		$note = function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $note ) : trim( strip_tags( (string) $note ) );
		if ( function_exists( 'mb_substr' ) ) { $note = mb_substr( $note, 0, self::MAX_NOTE_LENGTH ); } else { $note = substr( $note, 0, self::MAX_NOTE_LENGTH ); }
		$tags = is_array( $tags ) ? $tags : preg_split( '/\s*,\s*/', (string) $tags );
		$tags = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_key', $tags ) ) ) ), 0, self::MAX_TAGS );
		$desired = array( 'note' => $note, 'tags' => $tags );
		$meta['items'][ $collection ][ (string) $post_id ] = $desired;
		if ( ! function_exists( 'update_user_meta' ) ) {
			return InteractionResult::error( 'saved_collection_metadata_unavailable', 'Saved collection notes and tags cannot be stored right now.', array(), 503 );
		}
		$updated = update_user_meta( $user_id, self::META_KEY, $meta );
		if ( false === $updated ) {
			$persisted = self::metadata( $user_id );
			$actual = isset( $persisted['items'][ $collection ][ (string) $post_id ] ) && is_array( $persisted['items'][ $collection ][ (string) $post_id ] ) ? $persisted['items'][ $collection ][ (string) $post_id ] : null;
			if ( $actual !== $desired ) {
				return InteractionResult::error( 'saved_collection_metadata_failed', 'Saved collection notes and tags could not be stored.', array(), 500 );
			}
		}
		return InteractionResult::success( 'saved_collection_metadata_saved', array(), 'Saved collection metadata stored.', 200 );
	}

	private static function remove_item_metadata( $user_id, $collection, $post_id ) {
		$meta = self::metadata( $user_id );
		if ( isset( $meta['items'][ $collection ][ (string) $post_id ] ) ) {
			unset( $meta['items'][ $collection ][ (string) $post_id ] );
			if ( function_exists( 'update_user_meta' ) ) { update_user_meta( $user_id, self::META_KEY, $meta ); }
		}
	}

	public static function collection_key( $value ) {
		$value = function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
		return '' !== $value ? substr( $value, 0, 64 ) : self::DEFAULT_COLLECTION;
	}
}
