<?php
/**
 * Revocable private preview tokens.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Issues short-lived article-bound one-way-hashed preview tokens. */
final class PreviewTokenService {
	public static function register() {}

	public static function issue( $article_id, $ttl = 1800, $scope = 'preview' ) {
		$article_id = Phase5Contracts::positive_int( $article_id );
		$ttl = max( 300, min( DAY_IN_SECONDS, (int) $ttl ) );
		if ( $article_id < 1 || ! Phase5FeatureSettings::enabled( 'private_previews_enabled' ) || ( function_exists( 'get_post_type' ) && Phase4Contracts::POST_TYPE !== get_post_type( $article_id ) ) || ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_editorial_news', $article_id ) ) {
			return array( 'success' => false, 'status' => 403, 'code' => 'phase5_permission_denied' );
		}
		$token = bin2hex( random_bytes( 32 ) );
		$hash = hash_hmac( 'sha256', $token, self::salt() );
		$id = Phase5Repository::insert(
			'preview_tokens',
			array(
				'article_id' => $article_id, 'token_hash' => $hash, 'scope' => 'preview' === $scope ? 'preview' : 'review',
				'state' => 'active', 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + $ttl ),
				'created_by' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
				'created_at' => gmdate( 'Y-m-d H:i:s' ), 'revoked_at' => null,
			)
		);
		if ( $id < 1 ) { return array( 'success' => false, 'status' => 500, 'code' => 'phase5_query_failed' ); }
		Phase5AuditIntegrity::record( 'preview-issued', 'article', $article_id, array( 'result' => 'active' ) );
		return array( 'success' => true, 'status' => 201, 'data' => array( 'token' => $token, 'expires_in' => $ttl ) );
	}

	public static function validate( $article_id, $token, $scope = 'preview' ) {
		$article_id = Phase5Contracts::positive_int( $article_id );
		if ( $article_id < 1 || ! Phase5FeatureSettings::enabled( 'private_previews_enabled' ) || ! in_array( $scope, array( 'preview', 'review' ), true ) || ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{64}$/D', $token ) ) { return false; }
		$hash = hash_hmac( 'sha256', $token, self::salt() );
		$rows = Phase5Repository::query( 'preview_tokens', array( 'article_id' => $article_id, 'token_hash' => $hash, 'state' => 'active', 'scope' => $scope ), 1, 0, 'id', 'DESC' );
		if ( ! $rows ) { return false; }
		return strtotime( (string) $rows[0]['expires_at'] . ' UTC' ) > time();
	}

	public static function resolve( $article_id, $token, $scope = 'preview' ) {
		if ( ! self::validate( $article_id, $token, $scope ) || ! function_exists( 'get_post' ) ) {
			return array( 'success' => false, 'status' => 404, 'code' => 'phase5_not_found' );
		}
		$post = get_post( Phase5Contracts::positive_int( $article_id ) );
		if ( ! $post || ( function_exists( 'get_post_type' ) && Phase4Contracts::POST_TYPE !== get_post_type( $post ) ) ) {
			return array( 'success' => false, 'status' => 404, 'code' => 'phase5_not_found' );
		}
		$body = function_exists( 'wp_kses_post' ) ? wp_kses_post( (string) $post->post_content ) : strip_tags( (string) $post->post_content, '<p><br><strong><em><ul><ol><li><a><blockquote><h2><h3><h4>' );
		$body = preg_replace( '/\s+on[a-z0-9_-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', (string) $body );
		$body = preg_replace( '/\s+(?:href|src)\s*=\s*(["\'])?\s*(?:javascript|data|vbscript)\s*:[^>\s]*\1/iu', '', (string) $body );
		return array(
			'success' => true,
			'status' => 200,
			'data' => array(
				'article_id' => (int) $post->ID,
				'title' => function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $post->post_title ) : strip_tags( (string) $post->post_title ),
				'summary' => function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $post->post_excerpt ) : strip_tags( (string) $post->post_excerpt ),
				'body_html' => $body,
				'scope' => $scope,
			),
		);
	}

	public static function revoke_article( $article_id ) {
		$rows = Phase5Repository::query( 'preview_tokens', array( 'article_id' => Phase5Contracts::positive_int( $article_id ), 'state' => 'active' ), 100, 0, 'id', 'ASC' );
		$count = 0;
		foreach ( $rows as $row ) {
			if ( Phase5Repository::update( 'preview_tokens', $row['id'], array( 'state' => 'revoked', 'revoked_at' => gmdate( 'Y-m-d H:i:s' ) ) ) ) { $count++; }
		}
		return $count;
	}

	private static function salt() { return function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : ( defined( 'AUTH_SALT' ) ? AUTH_SALT : 'sabri-preview-salt' ); }
}
