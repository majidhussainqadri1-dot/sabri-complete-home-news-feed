<?php
/**
 * Phase 3 checkpoint 3.0 contract freeze.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines the bounded Phase 3 social-interaction contract without enabling runtime UI.
 */
final class Phase3Contracts {
	const TARGET_VERSION = '1.1.0';
	const CHECKPOINT = '3.0';
	const REST_NAMESPACE = 'sabri-home-news-feed/v1';

	/**
	 * Feature flags introduced by Phase 3.
	 *
	 * Every public runtime flag is disabled at checkpoint 3.0 so Phase 2 behavior
	 * remains unchanged until the relevant checkpoint is implemented and tested.
	 *
	 * @return array<string,int>
	 */
	public static function feature_flags() {
		return array(
			'reactions_enabled'             => 0,
			'dislikes_enabled'              => 0,
			'comments_enabled'              => 0,
			'saves_enabled'                 => 0,
			'follows_enabled'               => 0,
			'followers_visibility_enabled'  => 0,
			'reports_enabled'               => 0,
			'polls_enabled'                 => 0,
			'notification_bridge_enabled'   => 0,
			'view_logging_enabled'          => 0,
		);
	}

	/**
	 * Frozen Phase 3 settings names and safe defaults.
	 *
	 * These contracts are not merged into the live Settings runtime during
	 * checkpoint 3.0. Checkpoint 3A will integrate them after permission,
	 * repository, schema, and rate-limit behavior is implemented.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function settings_defaults() {
		return array(
			'social' => array(
				'reactions_enabled'            => 0,
				'dislikes_enabled'             => 0,
				'comments_enabled'             => 0,
				'max_comment_length'           => 2000,
				'max_reply_depth'              => 3,
				'comment_edit_minutes'         => 15,
				'saves_enabled'                => 0,
				'follows_enabled'              => 0,
				'followers_visibility_enabled' => 0,
				'polls_enabled'                => 0,
				'show_public_reaction_counts'  => 0,
				'show_public_follower_counts'  => 0,
			),
			'moderation' => array(
				'reports_enabled'               => 0,
				'allowed_report_reasons'        => self::report_reasons(),
				'clinical_comment_privacy_scan' => 1,
				'new_comment_policy'            => 'hold',
				'rate_limit_window_seconds'     => 300,
				'rate_limit_max_actions'        => 60,
			),
			'performance' => array(
				'engagement_cache_seconds' => 60,
				'log_views'               => 0,
				'view_deduplication_days' => 1,
			),
			'privacy' => array(
				'export_private_saves'              => 1,
				'export_follows'                    => 1,
				'retain_reports_for_accountability' => 1,
				'anonymize_views'                   => 1,
			),
		);
	}

	/**
	 * Fail-closed feature check against a future settings payload.
	 *
	 * @param string              $feature Feature key.
	 * @param array<string,mixed> $settings Settings payload.
	 * @return bool
	 */
	public static function feature_enabled( $feature, array $settings = array() ) {
		$feature = self::clean_key( $feature );
		$flags   = self::feature_flags();

		if ( ! array_key_exists( $feature, $flags ) ) {
			return false;
		}

		if ( isset( $settings['social'] ) && is_array( $settings['social'] ) && array_key_exists( $feature, $settings['social'] ) ) {
			return 1 === (int) $settings['social'][ $feature ];
		}

		if ( 'reports_enabled' === $feature && isset( $settings['moderation'] ) && is_array( $settings['moderation'] ) && array_key_exists( $feature, $settings['moderation'] ) ) {
			return 1 === (int) $settings['moderation'][ $feature ];
		}

		if ( 'view_logging_enabled' === $feature && isset( $settings['performance'] ) && is_array( $settings['performance'] ) && array_key_exists( 'log_views', $settings['performance'] ) ) {
			return 1 === (int) $settings['performance']['log_views'];
		}

		return false;
	}

	/**
	 * Shared result keys for every Phase 3 service and REST mutation.
	 *
	 * @return array<int,string>
	 */
	public static function response_keys() {
		return array( 'ok', 'code', 'message', 'data', 'status' );
	}

	/**
	 * Frozen REST route contracts. Registration is intentionally deferred.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function rest_routes() {
		return array(
			'engagement' => array( 'method' => 'GET', 'path' => '/posts/{id}/engagement', 'permission' => 'visible_post' ),
			'reaction_create' => array( 'method' => 'POST', 'path' => '/posts/{id}/reaction', 'permission' => 'authenticated_nonce' ),
			'reaction_delete' => array( 'method' => 'DELETE', 'path' => '/posts/{id}/reaction', 'permission' => 'authenticated_nonce' ),
			'comments' => array( 'method' => 'GET', 'path' => '/posts/{id}/comments', 'permission' => 'visible_post' ),
			'comment_create' => array( 'method' => 'POST', 'path' => '/posts/{id}/comments', 'permission' => 'authenticated_nonce' ),
			'comment_update' => array( 'method' => 'PATCH', 'path' => '/comments/{id}', 'permission' => 'owner_window_or_moderator' ),
			'comment_delete' => array( 'method' => 'DELETE', 'path' => '/comments/{id}', 'permission' => 'owner_or_moderator' ),
			'save_create' => array( 'method' => 'POST', 'path' => '/posts/{id}/save', 'permission' => 'authenticated_nonce' ),
			'save_delete' => array( 'method' => 'DELETE', 'path' => '/posts/{id}/save', 'permission' => 'authenticated_nonce' ),
			'my_saves' => array( 'method' => 'GET', 'path' => '/me/saves', 'permission' => 'current_user_only' ),
			'follow_create' => array( 'method' => 'POST', 'path' => '/users/{id}/follow', 'permission' => 'authenticated_nonce' ),
			'follow_delete' => array( 'method' => 'DELETE', 'path' => '/users/{id}/follow', 'permission' => 'authenticated_nonce' ),
			'my_following' => array( 'method' => 'GET', 'path' => '/me/following', 'permission' => 'current_user_only' ),
			'report_create' => array( 'method' => 'POST', 'path' => '/reports', 'permission' => 'authenticated_nonce' ),
			'poll_vote' => array( 'method' => 'POST', 'path' => '/polls/{id}/vote', 'permission' => 'authenticated_nonce' ),
			'poll_vote_delete' => array( 'method' => 'DELETE', 'path' => '/polls/{id}/vote', 'permission' => 'authenticated_nonce' ),
			'poll_results' => array( 'method' => 'GET', 'path' => '/polls/{id}/results', 'permission' => 'results_policy' ),
		);
	}

	/**
	 * Phase 3 reaction allow-list.
	 *
	 * @return array<int,string>
	 */
	public static function reaction_types() {
		return array( 'like', 'dislike' );
	}

	/**
	 * Phase 3 report reason allow-list.
	 *
	 * @return array<int,string>
	 */
	public static function report_reasons() {
		return array(
			'spam',
			'harassment',
			'hate-abuse',
			'misinformation',
			'medical-safety-risk',
			'patient-privacy',
			'copyright-source',
			'impersonation',
			'other',
		);
	}

	/**
	 * Phase 3 report states.
	 *
	 * @return array<int,string>
	 */
	public static function report_states() {
		return array( 'open', 'triaged', 'resolved', 'dismissed', 'duplicate' );
	}

	/**
	 * Phase 3 poll results policies.
	 *
	 * @return array<int,string>
	 */
	public static function poll_results_policies() {
		return array( 'after_vote', 'after_close', 'always' );
	}

	/**
	 * Clean a contract key without depending on a fully bootstrapped WordPress runtime.
	 *
	 * @param mixed $value Key value.
	 * @return string
	 */
	private static function clean_key( $value ) {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}
}
