<?php
/**
 * Runtime feature settings for implemented Phase 3 checkpoints.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps Phase 3 runtime activation isolated from the accepted Phase 2 option. */
final class Phase3FeatureSettings {
	const OPTION_NAME = 'sabri_hnf_phase3_features';

	/** Register the isolated option. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		}
	}

	/** Register WordPress setting. */
	public static function register_setting() {
		if ( function_exists( 'register_setting' ) ) {
			register_setting(
				'sabri_feed_settings',
				self::OPTION_NAME,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( __CLASS__, 'sanitize' ),
					'default'           => self::defaults(),
				)
			);
		}
	}

	/**
	 * Safe runtime defaults.
	 *
	 * Comments, follows, reports, polls, notifications, and view logging remain
	 * gated until their staged acceptance steps pass. Reactions, private saves,
	 * public reaction counts, and non-mutating Share retain safe defaults.
	 *
	 * @return array<string,int>
	 */
	public static function defaults() {
		return array(
			'reactions_enabled'            => 1,
			'dislikes_enabled'             => 1,
			'saves_enabled'                => 1,
			'share_enabled'                => 1,
			'show_public_reaction_counts'  => 1,
			'comments_enabled'             => 0,
			'follows_enabled'              => 0,
			'show_public_follower_counts'  => 0,
			'followers_visibility_enabled' => 0,
			'reports_enabled'              => 0,
			'polls_enabled'                => 0,
			'notification_bridge_enabled'  => 0,
			'view_logging_enabled'         => 0,
		);
	}

	/**
	 * Administrator-facing feature catalogue.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function catalog() {
		return array(
			'reactions_enabled' => array(
				'group'       => __( 'Core interactions', 'sabri-complete-home-news-feed' ),
				'label'       => __( 'Like reactions', 'sabri-complete-home-news-feed' ),
				'description' => __( 'Allow authenticated users to toggle Like reactions.', 'sabri-complete-home-news-feed' ),
			),
			'dislikes_enabled' => array(
				'group'       => __( 'Core interactions', 'sabri-complete-home-news-feed' ),
				'label'       => __( 'Dislike reactions', 'sabri-complete-home-news-feed' ),
				'description' => __( 'Allow authenticated users to toggle Dislike reactions. Requires Like reactions.', 'sabri-complete-home-news-feed' ),
			),
			'saves_enabled' => array(
				'group'       => __( 'Core interactions', 'sabri-complete-home-news-feed' ),
				'label'       => __( 'Private Save and Unsave', 'sabri-complete-home-news-feed' ),
				'description' => __( 'Allow authenticated users to maintain a private Saved Posts list.', 'sabri-complete-home-news-feed' ),
			),
			'share_enabled' => array(
				'group'       => __( 'Core interactions', 'sabri-complete-home-news-feed' ),
				'label'       => __( 'Share', 'sabri-complete-home-news-feed' ),
				'description' => __( 'Show a privacy-safe Share button using the browser Share API with copy-link fallback.', 'sabri-complete-home-news-feed' ),
			),
			'show_public_reaction_counts' => array(
				'group'       => __( 'Core interactions', 'sabri-complete-home-news-feed' ),
				'label'       => __( 'Public reaction counts', 'sabri-complete-home-news-feed' ),
				'description' => __( 'Display aggregate Like and Dislike counts. Requires Like reactions.', 'sabri-complete-home-news-feed' ),
			),
			'comments_enabled' => array(
				'group'       => __( 'Staged social systems', 'sabri-complete-home-news-feed' ),
				'label'       => __( 'Comments and replies', 'sabri-complete-home-news-feed' ),
				'description' => __( 'Render the Comment button, accessible thread, moderation states, replies, edit window, and privacy scanner.', 'sabri-complete-home-news-feed' ),
			),
			'follows_enabled' => array(
				'group'       => __( 'Staged social systems', 'sabri-complete-home-news-feed' ),
				'label'       => __( 'Follow and Following', 'sabri-complete-home-news-feed' ),
				'description' => __( 'Enable Follow and Unfollow plus the private Following list.', 'sabri-complete-home-news-feed' ),
			),
			'show_public_follower_counts' => array(
				'group'       => __( 'Staged social systems', 'sabri-complete-home-news-feed' ),
				'label'       => __( 'Public follower counts', 'sabri-complete-home-news-feed' ),
				'description' => __( 'Display aggregate follower counts. Requires Follow and Following.', 'sabri-complete-home-news-feed' ),
			),
			'followers_visibility_enabled' => array(
				'group'       => __( 'Staged social systems', 'sabri-complete-home-news-feed' ),
				'label'       => __( 'Followers-only post visibility', 'sabri-complete-home-news-feed' ),
				'description' => __( 'Permit Followers visibility in Composer and enforce it across direct posts, Feed, REST, and cache boundaries. Requires Follow and Following.', 'sabri-complete-home-news-feed' ),
			),
			'reports_enabled' => array(
				'group'       => __( 'Moderation and publishing', 'sabri-complete-home-news-feed' ),
				'label'       => __( 'Reports and moderation queue', 'sabri-complete-home-news-feed' ),
				'description' => __( 'Enable confidential reporting controls and the moderator-only queue.', 'sabri-complete-home-news-feed' ),
			),
			'polls_enabled' => array(
				'group'       => __( 'Moderation and publishing', 'sabri-complete-home-news-feed' ),
				'label'       => __( 'Polls', 'sabri-complete-home-news-feed' ),
				'description' => __( 'Enable bounded Composer Polls, voting, close rules, and aggregate results.', 'sabri-complete-home-news-feed' ),
			),
			'notification_bridge_enabled' => array(
				'group'       => __( 'Privacy and analytics', 'sabri-complete-home-news-feed' ),
				'label'       => __( 'Notification bridge', 'sabri-complete-home-news-feed' ),
				'description' => __( 'Send privacy-minimized social events only when a staging-safe notification callback is connected.', 'sabri-complete-home-news-feed' ),
			),
			'view_logging_enabled' => array(
				'group'       => __( 'Privacy and analytics', 'sabri-complete-home-news-feed' ),
				'label'       => __( 'Privacy-safe view logging', 'sabri-complete-home-news-feed' ),
				'description' => __( 'Record deduplicated direct-post views and display aggregate Views without storing raw IP addresses or user agents.', 'sabri-complete-home-news-feed' ),
			),
		);
	}

	/** Return merged runtime settings. */
	public static function get() {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();
		$stored = is_array( $stored ) ? $stored : array();
		return self::normalize_dependencies( array_merge( self::defaults(), self::sanitize( $stored ) ) );
	}

	/** Sanitize only known feature flags while preserving upgrade defaults. */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = array();
		foreach ( self::defaults() as $key => $default ) {
			$out[ $key ] = array_key_exists( $key, $input ) ? ( empty( $input[ $key ] ) ? 0 : 1 ) : $default;
		}
		return self::normalize_dependencies( $out );
	}

	/**
	 * Save a complete administrator checkbox submission.
	 *
	 * Missing checkbox keys must become disabled, not revert to their defaults.
	 *
	 * @param mixed $input Raw submitted values.
	 * @return array<string,int>
	 */
	public static function update_from_admin( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$complete = array_fill_keys( array_keys( self::defaults() ), 0 );
		foreach ( $complete as $key => $unused ) {
			$complete[ $key ] = array_key_exists( $key, $input ) && ! empty( $input[ $key ] ) ? 1 : 0;
		}
		$complete = self::normalize_dependencies( $complete );
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_NAME, $complete, false );
		}
		return $complete;
	}

	/** Enforce dependencies fail-closed. */
	public static function normalize_dependencies( array $settings ) {
		$settings = array_merge( self::defaults(), $settings );
		foreach ( $settings as $key => $value ) {
			$settings[ $key ] = empty( $value ) ? 0 : 1;
		}

		if ( empty( $settings['reactions_enabled'] ) ) {
			$settings['dislikes_enabled']            = 0;
			$settings['show_public_reaction_counts'] = 0;
		}
		if ( empty( $settings['follows_enabled'] ) ) {
			$settings['show_public_follower_counts']  = 0;
			$settings['followers_visibility_enabled'] = 0;
		}
		return $settings;
	}

	/** Return the configured flag without consulting the Phase 2 settings option. */
	public static function configured_enabled( $feature ) {
		$feature  = function_exists( 'sanitize_key' ) ? sanitize_key( $feature ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $feature ) );
		$settings = self::get();
		return array_key_exists( $feature, $settings ) && 1 === (int) $settings[ $feature ];
	}

	/** Fail-closed feature check with global safety controls. */
	public static function enabled( $feature ) {
		if ( ! self::configured_enabled( $feature ) ) {
			return false;
		}
		return ! SafeMode::public_features_disabled();
	}
}
