<?php
/**
 * Phase 3B private save service.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements private Save/Unsave state for the current user.
 */
final class SaveService {
	const DEFAULT_COLLECTION = 'default';

	/**
	 * Save a visible post for the current user.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $nonce REST nonce.
	 * @param int    $user_id Optional current session user ID.
	 * @return array<string,mixed>
	 */
	public static function save( $post_id, $nonce = '', $user_id = 0 ) {
		if ( ! Phase3FeatureSettings::enabled( 'saves_enabled' ) ) {
			return InteractionResult::error( 'saves_disabled', 'Saving posts is currently unavailable.', array(), 503 );
		}

		$authorized = InteractionPermissions::authorize_post_write( $post_id, $nonce, $user_id );
		if ( empty( $authorized['ok'] ) ) {
			return $authorized;
		}

		$user_id = (int) $authorized['data']['user_id'];
		$post_id = (int) $authorized['data']['post_id'];
		$limit   = InteractionRateLimiter::attempt( 'saves', $user_id, $post_id );
		if ( empty( $limit['ok'] ) ) {
			return $limit;
		}

		$current = InteractionQueryRepository::save_record( $user_id, $post_id, self::DEFAULT_COLLECTION );
		if ( is_array( $current ) ) {
			$result = self::set_status( $user_id, $post_id, 'active' );
		} else {
			$result = InteractionRepository::insert_row(
				'saves',
				array(
					'user_id'        => $user_id,
					'post_id'        => $post_id,
					'collection_key' => self::DEFAULT_COLLECTION,
					'status'         => 'active',
				)
			);

			// A concurrent request may win the unique insert. Re-read and activate
			// the existing private row rather than returning a duplicate error.
			if ( empty( $result['ok'] ) && is_array( InteractionQueryRepository::save_record( $user_id, $post_id, self::DEFAULT_COLLECTION ) ) ) {
				$result = self::set_status( $user_id, $post_id, 'active' );
			}
		}

		if ( empty( $result['ok'] ) ) {
			return $result;
		}

		AuditLog::record( 'post_saved', array( 'post_id' => $post_id, 'collection' => self::DEFAULT_COLLECTION ) );
		return InteractionResult::success(
			'post_saved',
			EngagementService::summary( $post_id, $user_id ),
			'Post saved.',
			200
		);
	}

	/**
	 * Remove a post from the current user's saved collection.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $nonce REST nonce.
	 * @param int    $user_id Optional current session user ID.
	 * @return array<string,mixed>
	 */
	public static function unsave( $post_id, $nonce = '', $user_id = 0 ) {
		if ( ! Phase3FeatureSettings::enabled( 'saves_enabled' ) ) {
			return InteractionResult::error( 'saves_disabled', 'Saving posts is currently unavailable.', array(), 503 );
		}

		$authorized = InteractionPermissions::authorize_post_write( $post_id, $nonce, $user_id );
		if ( empty( $authorized['ok'] ) ) {
			return $authorized;
		}

		$user_id = (int) $authorized['data']['user_id'];
		$post_id = (int) $authorized['data']['post_id'];
		$limit   = InteractionRateLimiter::attempt( 'saves', $user_id, $post_id );
		if ( empty( $limit['ok'] ) ) {
			return $limit;
		}

		$current = InteractionQueryRepository::save_record( $user_id, $post_id, self::DEFAULT_COLLECTION );
		if ( is_array( $current ) && isset( $current['status'] ) && 'active' === sanitize_key( $current['status'] ) ) {
			$result = self::set_status( $user_id, $post_id, 'removed' );
			if ( empty( $result['ok'] ) ) {
				return $result;
			}
		}

		AuditLog::record( 'post_unsaved', array( 'post_id' => $post_id, 'collection' => self::DEFAULT_COLLECTION ) );
		return InteractionResult::success(
			'post_unsaved',
			EngagementService::summary( $post_id, $user_id ),
			'Post removed from saved items.',
			200
		);
	}

	/**
	 * Return private saved posts visible to the current user.
	 *
	 * @param string $nonce REST nonce.
	 * @param int    $user_id Optional current session user ID.
	 * @param int    $limit Maximum records.
	 * @return array<string,mixed>
	 */
	public static function saved_posts( $nonce = '', $user_id = 0, $limit = 100 ) {
		if ( ! Phase3FeatureSettings::enabled( 'saves_enabled' ) ) {
			return InteractionResult::error( 'saves_disabled', 'Saving posts is currently unavailable.', array(), 503 );
		}

		$user_id = InteractionPermissions::authenticated_user_id( $user_id );
		if ( $user_id <= 0 ) {
			return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 );
		}
		if ( ! InteractionPermissions::nonce_valid( $nonce ) ) {
			return InteractionResult::error( 'invalid_nonce', 'The security token is missing or invalid.', array(), 403 );
		}

		return self::saved_posts_for_user( $user_id, $limit );
	}

	/**
	 * Server-rendered private saved posts for an already authenticated session.
	 *
	 * @param int $user_id Current session user ID.
	 * @param int $limit Maximum records.
	 * @return array<string,mixed>
	 */
	public static function saved_posts_for_user( $user_id, $limit = 100 ) {
		$user_id = InteractionPermissions::authenticated_user_id( $user_id );
		if ( $user_id <= 0 ) {
			return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 );
		}

		$ids   = InteractionQueryRepository::saved_post_ids( $user_id, $limit );
		$items = array();
		foreach ( $ids as $post_id ) {
			if ( ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
				continue;
			}
			$items[] = array(
				'id'        => $post_id,
				'title'     => function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : '',
				'permalink' => function_exists( 'get_permalink' ) ? (string) get_permalink( $post_id ) : '',
			);
		}

		return InteractionResult::success(
			'saved_posts',
			array(
				'items' => $items,
				'count' => count( $items ),
			),
			'Saved posts loaded.',
			200
		);
	}

	/**
	 * Set the private save row status.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $post_id Post ID.
	 * @param string $status Active or removed.
	 * @return array<string,mixed>
	 */
	private static function set_status( $user_id, $post_id, $status ) {
		return InteractionRepository::update_rows(
			'saves',
			array( 'status' => $status ),
			array(
				'user_id'        => $user_id,
				'post_id'        => $post_id,
				'collection_key' => self::DEFAULT_COLLECTION,
			)
		);
	}
}
