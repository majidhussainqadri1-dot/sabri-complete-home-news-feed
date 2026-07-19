<?php
/**
 * Phase 3H followers-only visibility.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enforces author/follower visibility at metadata, query, and cache boundaries.
 */
final class FollowersVisibility {
	const VISIBILITY            = 'followers';
	const DENIED_VISIBILITY     = 'followers-denied';
	const QUERY_VIEWER_KEY      = 'sabri_hnf_followers_viewer';

	/** Register runtime guards. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'get_post_metadata', array( __CLASS__, 'filter_visibility_metadata' ), 10, 4 );
			add_filter( 'posts_where', array( __CLASS__, 'filter_posts_where' ), 10, 2 );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'pre_get_posts', array( __CLASS__, 'extend_post_queries' ), 20 );
			add_action( 'updated_option', array( __CLASS__, 'invalidate_on_feature_change' ), 10, 3 );
		}
	}

	/**
	 * Preserve the public visibility label for authorized viewers and fail closed
	 * for everybody else without changing stored post metadata.
	 *
	 * @param mixed  $value Existing short-circuit value.
	 * @param int    $post_id Post ID.
	 * @param string $meta_key Metadata key.
	 * @param bool   $single Single value request.
	 * @return mixed
	 */
	public static function filter_visibility_metadata( $value, $post_id, $meta_key, $single ) {
		if ( null !== $value || PostMetadata::META_VISIBILITY !== (string) $meta_key ) {
			return $value;
		}

		$post_id = self::positive_id( $post_id );
		if ( $post_id <= 0 || self::VISIBILITY !== self::raw_visibility( $post_id ) ) {
			return $value;
		}

		$user_id = function_exists( 'get_current_user_id' ) ? self::positive_id( get_current_user_id() ) : 0;
		if ( self::viewer_is_author_or_moderator( $post_id, $user_id ) ) {
			$visible_value = Phase3FeatureSettings::enabled( 'followers_visibility_enabled' ) ? self::VISIBILITY : 'private';
			return $single ? $visible_value : array( $visible_value );
		}

		if ( Phase3FeatureSettings::enabled( 'followers_visibility_enabled' ) && self::viewer_follows_author( $post_id, $user_id ) ) {
			return $single ? self::VISIBILITY : array( self::VISIBILITY );
		}

		return $single ? self::DENIED_VISIBILITY : array( self::DENIED_VISIBILITY );
	}

	/**
	 * Add the followers candidate scope to public post queries for authenticated
	 * viewers. The SQL WHERE guard below still requires the author relationship.
	 *
	 * @param mixed $query WP_Query-like object.
	 * @return void
	 */
	public static function extend_post_queries( $query ) {
		if ( ! Phase3FeatureSettings::enabled( 'followers_visibility_enabled' ) || ( function_exists( 'is_admin' ) && is_admin() ) ) {
			return;
		}
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
			return;
		}

		$user_id = function_exists( 'get_current_user_id' ) ? self::positive_id( get_current_user_id() ) : 0;
		if ( $user_id <= 0 ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		if ( $post_type && 'post' !== $post_type && ! ( is_array( $post_type ) && in_array( 'post', $post_type, true ) ) ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' );
		$meta_query = is_array( $meta_query ) ? $meta_query : array();
		if ( ! self::append_scope_to_visibility_clause( $meta_query ) ) {
			$meta_query[] = PostMetadata::visibility_meta_clause();
			self::append_scope_to_visibility_clause( $meta_query );
		}

		$query->set( 'meta_query', $meta_query );
		$query->set( self::QUERY_VIEWER_KEY, $user_id );
	}

	/**
	 * Restrict followers candidates to the author, a moderator, or an active
	 * user-to-user follower relationship.
	 *
	 * @param string $where SQL WHERE fragment.
	 * @param mixed  $query WP_Query-like object.
	 * @return string
	 */
	public static function filter_posts_where( $where, $query ) {
		if ( ! Phase3FeatureSettings::enabled( 'followers_visibility_enabled' ) || ! is_object( $query ) || ! method_exists( $query, 'get' ) ) {
			return $where;
		}

		$user_id = self::positive_id( $query->get( self::QUERY_VIEWER_KEY ) );
		if ( $user_id <= 0 || self::viewer_can_moderate() ) {
			return $where;
		}

		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $where . ' AND 1 = 0';
		}

		$posts_table    = isset( $wpdb->posts ) ? str_replace( '`', '', (string) $wpdb->posts ) : '';
		$postmeta_table = isset( $wpdb->postmeta ) ? str_replace( '`', '', (string) $wpdb->postmeta ) : '';
		$follows_table  = str_replace( '`', '', InteractionRepository::table_name( 'follows' ) );
		if ( '' === $posts_table || '' === $postmeta_table || '' === $follows_table ) {
			return $where . ' AND 1 = 0';
		}

		$guard = $wpdb->prepare(
			" AND ( NOT EXISTS ( SELECT 1 FROM `{$postmeta_table}` sabri_fv_pm WHERE sabri_fv_pm.post_id = `{$posts_table}`.ID AND sabri_fv_pm.meta_key = %s AND sabri_fv_pm.meta_value = %s ) OR `{$posts_table}`.post_author = %d OR EXISTS ( SELECT 1 FROM `{$follows_table}` sabri_fv_f WHERE sabri_fv_f.follower_user_id = %d AND sabri_fv_f.target_user_id = `{$posts_table}`.post_author AND sabri_fv_f.target_type = %s AND sabri_fv_f.status = %s ) )",
			PostMetadata::META_VISIBILITY,
			self::VISIBILITY,
			$user_id,
			$user_id,
			FollowService::TARGET_TYPE,
			'active'
		);

		return is_string( $guard ) ? $where . $guard : $where . ' AND 1 = 0';
	}

	/** Active follower relationship for a viewer and author. */
	public static function relationship_active( $viewer_user_id, $author_user_id ) {
		$viewer_user_id = self::positive_id( $viewer_user_id );
		$author_user_id = self::positive_id( $author_user_id );
		if ( $viewer_user_id <= 0 || $author_user_id <= 0 || $viewer_user_id === $author_user_id ) {
			return false;
		}
		$record = InteractionQueryRepository::follow_record( $viewer_user_id, $author_user_id, FollowService::TARGET_TYPE );
		return is_array( $record ) && isset( $record['status'] ) && 'active' === sanitize_key( $record['status'] );
	}

	/** Invalidate per-user feed caches whenever the feature setting changes. */
	public static function invalidate_on_feature_change( $option, $old_value, $new_value ) {
		if ( Phase3FeatureSettings::OPTION_NAME !== (string) $option ) {
			return;
		}
		$old = is_array( $old_value ) && ! empty( $old_value['followers_visibility_enabled'] );
		$new = is_array( $new_value ) && ! empty( $new_value['followers_visibility_enabled'] );
		if ( $old !== $new ) {
			FeedQuery::invalidate_cache();
		}
	}

	/** Recursively append the candidate scope to a visibility IN clause. */
	private static function append_scope_to_visibility_clause( array &$clauses ) {
		$changed = false;
		foreach ( $clauses as &$clause ) {
			if ( ! is_array( $clause ) ) {
				continue;
			}
			if ( isset( $clause['key'], $clause['compare'] ) && PostMetadata::META_VISIBILITY === $clause['key'] && 'IN' === strtoupper( (string) $clause['compare'] ) ) {
				$values = isset( $clause['value'] ) && is_array( $clause['value'] ) ? $clause['value'] : array( $clause['value'] );
				$values[] = self::VISIBILITY;
				$clause['value'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', $values ) ) ) );
				$changed = true;
			}
			if ( self::append_scope_to_visibility_clause( $clause ) ) {
				$changed = true;
			}
		}
		unset( $clause );
		return $changed;
	}

	/** Whether current viewer owns or can moderate the post. */
	private static function viewer_is_author_or_moderator( $post_id, $user_id ) {
		$author_id = function_exists( 'get_post_field' ) ? self::positive_id( get_post_field( 'post_author', $post_id ) ) : 0;
		return ( $user_id > 0 && $author_id === $user_id ) || self::viewer_can_moderate();
	}

	/** Whether current viewer actively follows the post author. */
	private static function viewer_follows_author( $post_id, $user_id ) {
		$author_id = function_exists( 'get_post_field' ) ? self::positive_id( get_post_field( 'post_author', $post_id ) ) : 0;
		return self::relationship_active( $user_id, $author_id );
	}

	/** Moderator authority. */
	private static function viewer_can_moderate() {
		return ( class_exists( __NAMESPACE__ . '\\ComposerPermissions' ) && ComposerPermissions::user_can_moderate() ) || ( function_exists( 'current_user_can' ) && current_user_can( 'sabri_feed_moderate_posts' ) );
	}

	/** Read raw visibility without re-entering the metadata filter. */
	private static function raw_visibility( $post_id ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_var' ) || empty( $wpdb->postmeta ) ) {
			return '';
		}
		$table = str_replace( '`', '', (string) $wpdb->postmeta );
		$sql   = $wpdb->prepare( "SELECT meta_value FROM `{$table}` WHERE post_id = %d AND meta_key = %s ORDER BY meta_id DESC LIMIT 1", $post_id, PostMetadata::META_VISIBILITY );
		$value = is_string( $sql ) ? $wpdb->get_var( $sql ) : '';
		return sanitize_key( is_scalar( $value ) ? $value : '' );
	}

	/** Strict positive ID. */
	private static function positive_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/', (string) $value ) ? (int) $value : 0;
	}
}
