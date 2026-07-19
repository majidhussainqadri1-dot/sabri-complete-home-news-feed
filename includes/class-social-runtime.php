<?php
/**
 * Phase 3 social action rendering.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders progressive-enhancement reactions, comments, saves, follows, and reports.
 */
final class SocialRuntime {
	/**
	 * Single-post duplicate guard.
	 *
	 * @var array<int,bool>
	 */
	private static $single_rendered = array();

	/** Register hooks. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'append_single_actions' ), 30 );
		}
	}

	/**
	 * Render one action bar.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function render_action_bar( $post_id ) {
		$post_id = self::positive_id( $post_id );
		$reactions_enabled = Phase3FeatureSettings::enabled( 'reactions_enabled' );
		$saves_enabled     = Phase3FeatureSettings::enabled( 'saves_enabled' );
		$comments_enabled  = Phase3FeatureSettings::enabled( 'comments_enabled' );
		$follows_enabled   = Phase3FeatureSettings::enabled( 'follows_enabled' );
		$reports_enabled   = Phase3FeatureSettings::enabled( 'reports_enabled' );
		if ( $post_id <= 0 || ( ! $reactions_enabled && ! $saves_enabled && ! $comments_enabled && ! $follows_enabled && ! $reports_enabled ) || ! PostMetadata::user_can_view( $post_id ) ) {
			return '';
		}

		Assets::enqueue_feed();
		$user_id        = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$logged_in      = $user_id > 0;
		$summary        = EngagementService::summary( $post_id, $user_id );
		$base           = function_exists( 'rest_url' ) ? rest_url( RestFoundation::NAMESPACE . '/posts/' . $post_id ) : '';
		$nonce          = $logged_in && function_exists( 'wp_create_nonce' ) ? wp_create_nonce( InteractionPermissions::REST_NONCE_ACTION ) : '';
		$permalink      = function_exists( 'get_permalink' ) ? get_permalink( $post_id ) : '';
		$login_url      = function_exists( 'wp_login_url' ) ? wp_login_url( $permalink ) : '';
		$target_user_id = function_exists( 'get_post_field' ) ? self::positive_id( get_post_field( 'post_author', $post_id ) ) : 0;
		$follow_summary = $follows_enabled && $target_user_id > 0 ? FollowService::summary( $target_user_id, $user_id ) : array(
			'target_user_id' => 0,
			'following'      => false,
			'count_visible'  => false,
			'follower_count' => 0,
			'profile_url'    => '',
		);
		$can_follow    = $follows_enabled && $target_user_id > 0 && ( ! $logged_in || $target_user_id !== $user_id );
		$follow_url    = $target_user_id > 0 && function_exists( 'rest_url' ) ? rest_url( RestFoundation::NAMESPACE . '/users/' . $target_user_id . '/follow' ) : '';
		$report_control = $reports_enabled ? ReportRuntime::render_control( 'post', $post_id, $target_user_id ) : '';

		return FeedRenderer::template(
			'action-bar',
			array(
				'post_id'             => $post_id,
				'summary'             => $summary,
				'logged_in'           => $logged_in,
				'nonce'               => $nonce,
				'login_url'           => $login_url,
				'engagement_url'      => $base . '/engagement',
				'reaction_url'        => $base . '/reaction',
				'save_url'            => $base . '/save',
				'comments_url'        => $permalink . '#sabri-hnf-comments-' . $post_id,
				'comment_count'       => $comments_enabled ? CommentService::approved_count( $post_id ) : 0,
				'follow_url'          => $follow_url,
				'follow_summary'      => $follow_summary,
				'profile_url'         => isset( $follow_summary['profile_url'] ) ? (string) $follow_summary['profile_url'] : '',
				'can_follow'          => $can_follow,
				'reactions_enabled'   => $reactions_enabled,
				'dislikes_enabled'    => Phase3FeatureSettings::enabled( 'dislikes_enabled' ),
				'saves_enabled'       => $saves_enabled,
				'comments_enabled'    => $comments_enabled,
				'follows_enabled'     => $follows_enabled,
				'reports_enabled'     => $reports_enabled,
				'report_control'      => $report_control,
				'show_public_counts'  => Phase3FeatureSettings::enabled( 'show_public_reaction_counts' ),
			)
		);
	}

	/**
	 * Append actions to a visible single post once.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function append_single_actions( $content ) {
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
		return $content . self::render_action_bar( $post_id );
	}

	/** Reset guards for tests. */
	public static function reset_runtime_guards() {
		self::$single_rendered = array();
	}

	/** Strict positive ID. */
	private static function positive_id( $value ) {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/', $value ) ) ) {
			return 0;
		}
		$value = (int) $value;
		return $value > 0 ? $value : 0;
	}
}
