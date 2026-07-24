<?php
/**
 * Phase 3 social action rendering with controlled Editorial News support.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Renders progressive-enhancement reactions, comments, saves, shares, follows, reports, and views. */
final class SocialRuntime {
	private static $single_rendered = array();

	public static function register() {
		if ( function_exists( 'add_filter' ) ) { add_filter( 'the_content', array( __CLASS__, 'append_single_actions' ), 30 ); }
	}

	/** Render one action bar for an ordinary post or an approved Editorial News article. */
	public static function render_action_bar( $post_id, array $context = array() ) {
		$post_id = self::positive_id( $post_id );
		$is_news = self::is_news( $post_id );
		$features = array(
			'reactions' => Phase3FeatureSettings::enabled( 'reactions_enabled' ),
			'saves'     => Phase3FeatureSettings::enabled( 'saves_enabled' ),
			'share'     => Phase3FeatureSettings::enabled( 'share_enabled' ),
			'comments'  => Phase3FeatureSettings::enabled( 'comments_enabled' ),
			'follows'   => Phase3FeatureSettings::enabled( 'follows_enabled' ),
			'reports'   => Phase3FeatureSettings::enabled( 'reports_enabled' ),
			'views'     => Phase3FeatureSettings::enabled( 'view_logging_enabled' ),
		);
		if ( $is_news ) {
			$features['follows'] = false;
			$features = function_exists( 'apply_filters' ) ? (array) apply_filters( 'sabri_news_interaction_features', $features, $post_id ) : $features;
		}
		$anything_enabled = in_array( true, array_map( 'boolval', $features ), true );
		if ( $post_id <= 0 || ! $anything_enabled || ! InteractionPermissions::can_view_post( $post_id ) ) { return ''; }
		if ( $is_news && 'retracted' === NewsPolicy::workflow_state( $post_id ) ) { return ''; }

		Assets::enqueue_interactions();
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$logged_in = $user_id > 0;
		$summary = EngagementService::summary( $post_id, $user_id );
		$base = function_exists( 'rest_url' ) ? rest_url( RestFoundation::NAMESPACE . '/posts/' . $post_id ) : '';
		$nonce = $logged_in && function_exists( 'wp_create_nonce' ) ? wp_create_nonce( InteractionPermissions::REST_NONCE_ACTION ) : '';
		$permalink = ! empty( $context['canonical_url'] ) ? (string) $context['canonical_url'] : ( function_exists( 'get_permalink' ) ? (string) get_permalink( $post_id ) : '' );
		$share_title = ! empty( $context['title'] ) ? (string) $context['title'] : ( function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : '' );
		$login_url = function_exists( 'wp_login_url' ) ? wp_login_url( $permalink ) : '';
		$target_user_id = ! $is_news && function_exists( 'get_post_field' ) ? self::positive_id( get_post_field( 'post_author', $post_id ) ) : 0;
		$follow_summary = $features['follows'] && $target_user_id > 0 ? FollowService::summary( $target_user_id, $user_id ) : array( 'target_user_id'=>0,'following'=>false,'count_visible'=>false,'follower_count'=>0,'profile_url'=>'' );
		$can_follow = $features['follows'] && $target_user_id > 0 && ( ! $logged_in || $target_user_id !== $user_id );
		$follow_url = $target_user_id > 0 && function_exists( 'rest_url' ) ? rest_url( RestFoundation::NAMESPACE . '/users/' . $target_user_id . '/follow' ) : '';
		$report_control = $features['reports'] ? ReportRuntime::render_control( 'post', $post_id, $target_user_id ) : '';

		return FeedRenderer::template(
			'action-bar',
			array(
				'post_id'=>$post_id,'summary'=>$summary,'logged_in'=>$logged_in,'nonce'=>$nonce,'login_url'=>$login_url,
				'engagement_url'=>$base . '/engagement','reaction_url'=>$base . '/reaction','save_url'=>$base . '/save',
				'share_url'=>$permalink,'share_title'=>$share_title,'comments_url'=>$permalink . '#sabri-hnf-comments-' . $post_id,
				'comment_count'=>$features['comments'] ? CommentService::approved_count( $post_id ) : 0,
				'follow_url'=>$follow_url,'follow_summary'=>$follow_summary,'profile_url'=>isset($follow_summary['profile_url'])?(string)$follow_summary['profile_url']:'','can_follow'=>$can_follow,
				'reactions_enabled'=>(bool)$features['reactions'],'dislikes_enabled'=>Phase3FeatureSettings::enabled( 'dislikes_enabled' ),
				'saves_enabled'=>(bool)$features['saves'],'share_enabled'=>(bool)$features['share'],'comments_enabled'=>(bool)$features['comments'],
				'follows_enabled'=>(bool)$features['follows'],'reports_enabled'=>(bool)$features['reports'],'views_enabled'=>(bool)$features['views'],
				'report_control'=>$report_control,'show_public_counts'=>Phase3FeatureSettings::enabled( 'show_public_reaction_counts' ),
			)
		);
	}

	/** Render actions for a public News projection through the existing Phase 3 boundary. */
	public static function render_news_action_bar( array $article ) {
		$post_id = isset( $article['interaction_id'] ) ? self::positive_id( $article['interaction_id'] ) : 0;
		return $post_id > 0 ? self::render_action_bar( $post_id, array( 'canonical_url'=>isset($article['canonical_url'])?$article['canonical_url']:'', 'title'=>isset($article['headline'])?$article['headline']:'' ) ) : '';
	}

	public static function append_single_actions( $content ) {
		if ( ! HomeIntegration::is_single_post_request() ) { return $content; }
		if ( function_exists( 'in_the_loop' ) && ! in_the_loop() ) { return $content; }
		if ( function_exists( 'is_main_query' ) && ! is_main_query() ) { return $content; }
		$post_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		if ( $post_id <= 0 || isset( self::$single_rendered[ $post_id ] ) ) { return $content; }
		self::$single_rendered[ $post_id ] = true;
		return $content . self::render_action_bar( $post_id );
	}
	public static function reset_runtime_guards() { self::$single_rendered = array(); }
	private static function is_news( $post_id ) { return $post_id > 0 && function_exists( 'get_post_type' ) && class_exists( __NAMESPACE__ . '\\Phase4Contracts' ) && Phase4Contracts::POST_TYPE === get_post_type( $post_id ); }
	private static function positive_id( $value ) { if ( ! is_int($value) && !(is_string($value)&&preg_match('/^[0-9]+$/',$value)) ) { return 0; } $value=(int)$value; return $value>0?$value:0; }
}
