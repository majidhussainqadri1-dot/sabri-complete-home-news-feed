<?php
/**
 * Phase 3C accessible comment-thread runtime.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders a progressively enhanced comment thread on direct single posts.
 */
final class CommentRuntime {
	/**
	 * Single-post duplicate guard.
	 *
	 * @var array<int,bool>
	 */
	private static $single_rendered = array();

	/**
	 * Register single-post hook.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'append_single_comments' ), 40 );
		}
	}

	/**
	 * Render one visibility-safe thread.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function render_thread( $post_id ) {
		$post_id = self::positive_id( $post_id );
		if ( $post_id <= 0 || ! Phase3FeatureSettings::enabled( 'comments_enabled' ) || ! PostMetadata::user_can_view( $post_id ) ) {
			return '';
		}

		Assets::enqueue_comments();
		$user_id   = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$logged_in = $user_id > 0;
		$result    = CommentService::thread( $post_id, $logged_in ? $user_id : 0 );
		$data      = ! empty( $result['ok'] ) && isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : CommentService::thread_data( $post_id, $user_id );
		$base      = function_exists( 'rest_url' ) ? rest_url( RestFoundation::NAMESPACE ) : '';
		$nonce     = $logged_in && function_exists( 'wp_create_nonce' ) ? wp_create_nonce( InteractionPermissions::REST_NONCE_ACTION ) : '';
		$permalink = function_exists( 'get_permalink' ) ? get_permalink( $post_id ) : '';
		$login_url = function_exists( 'wp_login_url' ) ? wp_login_url( $permalink . '#sabri-hnf-comments-' . $post_id ) : '';
		$tree      = self::tree( isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array() );

		return FeedRenderer::template(
			'comment-thread',
			array(
				'post_id'       => $post_id,
				'data'          => $data,
				'tree'          => $tree,
				'logged_in'     => $logged_in,
				'nonce'         => $nonce,
				'login_url'     => $login_url,
				'create_url'    => $base . '/posts/' . $post_id . '/comments',
				'comment_base'  => $base . '/comments/',
			)
		);
	}

	/**
	 * Render one comment item and descendants.
	 *
	 * @param array<string,mixed> $item Item with children.
	 * @return string
	 */
	public static function render_item( array $item ) {
		return FeedRenderer::template( 'comment-item', array( 'item' => $item ) );
	}

	/**
	 * Append thread to visible single-post content once.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function append_single_comments( $content ) {
		if ( ! HomeIntegration::is_single_post_request() ) {
			return $content;
		}
		if ( function_exists( 'in_the_loop' ) && ! in_the_loop() ) {
			return $content;
		}
		if ( function_exists( 'is_main_query' ) && ! is_main_query() ) {
			return $content;
		}

		$post_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		if ( $post_id <= 0 || isset( self::$single_rendered[ $post_id ] ) ) {
			return $content;
		}
		self::$single_rendered[ $post_id ] = true;
		return $content . self::render_thread( $post_id );
	}

	/**
	 * Reset duplicate guard for tests.
	 *
	 * @return void
	 */
	public static function reset_runtime_guards() {
		self::$single_rendered = array();
	}

	/**
	 * Convert flat safe items into a bounded tree.
	 *
	 * Orphaned items are promoted to the root instead of disappearing.
	 *
	 * @param array<int,array<string,mixed>> $items Items.
	 * @return array<int,array<string,mixed>>
	 */
	private static function tree( array $items ) {
		$nodes = array();
		foreach ( $items as $item ) {
			if ( empty( $item['id'] ) ) {
				continue;
			}
			$item['children'] = array();
			$nodes[ (int) $item['id'] ] = $item;
		}

		$roots = array();
		foreach ( array_keys( $nodes ) as $id ) {
			$parent_id = isset( $nodes[ $id ]['parent_id'] ) ? (int) $nodes[ $id ]['parent_id'] : 0;
			if ( $parent_id > 0 && isset( $nodes[ $parent_id ] ) && $parent_id !== $id ) {
				$nodes[ $parent_id ]['children'][] =& $nodes[ $id ];
			} else {
				$roots[] =& $nodes[ $id ];
			}
		}
		return $roots;
	}

	/**
	 * Strict positive ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private static function positive_id( $value ) {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/', $value ) ) ) {
			return 0;
		}
		$value = (int) $value;
		return $value > 0 ? $value : 0;
	}
}
