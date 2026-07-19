<?php
/**
 * Phase 3F poll rendering runtime.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders accessible polls in feed cards and direct single posts.
 */
final class PollRuntime {
	/**
	 * Single-post duplicate guard.
	 *
	 * @var array<int,bool>
	 */
	private static $single_rendered = array();

	/**
	 * Register direct-post hook.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'append_single_poll' ), 25 );
		}
	}

	/**
	 * Render one visibility-safe poll.
	 *
	 * @param int $post_id Poll post ID.
	 * @return string
	 */
	public static function render_poll( $post_id ) {
		$post_id = self::positive_id( $post_id );
		if ( $post_id <= 0 || ! Phase3FeatureSettings::enabled( 'polls_enabled' ) || ! PostMetadata::user_can_view( $post_id ) || ! PollPolicy::is_poll( $post_id ) ) {
			return '';
		}

		Assets::enqueue_polls();
		$user_id   = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$logged_in = $user_id > 0;
		$result    = PollService::results( $post_id, $user_id );
		if ( empty( $result['ok'] ) || empty( $result['data'] ) ) {
			return '';
		}

		$base      = function_exists( 'rest_url' ) ? rest_url( RestFoundation::NAMESPACE . '/polls/' . $post_id ) : '';
		$nonce     = $logged_in && function_exists( 'wp_create_nonce' ) ? wp_create_nonce( InteractionPermissions::REST_NONCE_ACTION ) : '';
		$permalink = function_exists( 'get_permalink' ) ? get_permalink( $post_id ) : '';
		$login_url = function_exists( 'wp_login_url' ) ? wp_login_url( $permalink . '#sabri-hnf-poll-' . $post_id ) : '';

		return FeedRenderer::template(
			'poll',
			array(
				'post_id'     => $post_id,
				'data'        => $result['data'],
				'logged_in'   => $logged_in,
				'nonce'       => $nonce,
				'login_url'   => $login_url,
				'vote_url'    => $base . '/vote',
				'results_url' => $base . '/results',
			)
		);
	}

	/**
	 * Append poll before social actions on a direct single post.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function append_single_poll( $content ) {
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
		return $content . self::render_poll( $post_id );
	}

	/**
	 * Reset duplicate guard for deterministic tests.
	 *
	 * @return void
	 */
	public static function reset_runtime_guards() {
		self::$single_rendered = array();
	}

	/**
	 * Strict positive ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private static function positive_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/', (string) $value ) ? (int) $value : 0;
	}
}
