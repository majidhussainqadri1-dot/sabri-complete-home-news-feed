<?php
/**
 * Main plugin coordinator.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates the plugin runtime.
 */
final class Plugin {
	/** @var Plugin|null */
	private static $instance = null;
	/** @var bool */
	private $registered = false;

	/** Return singleton. */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Register plugin hooks through isolated compatibility boundaries. */
	public function register() {
		if ( $this->registered ) {
			return;
		}
		$this->registered = true;

		$modules = array(
			Settings::class,
			Phase3FeatureSettings::class,
			PollComposerIntegration::class,
			Capabilities::class,
			PostTypes::class,
			Taxonomies::class,
			Integrations::class,
			ReleaseReadiness::class,
			SafeMode::class,
			RestFoundation::class,
			DataRetention::class,
			NotificationBridge::class,
			Assets::class,
			PostMetadata::class,
			FollowersVisibility::class,
			MediaHandler::class,
			FeedQuery::class,
			HomeIntegration::class,
			ViewRuntime::class,
			PollRuntime::class,
			SocialRuntime::class,
			CommentRuntime::class,
			SavedPostsRuntime::class,
			FollowingRuntime::class,
			Shortcodes::class,
			Composer::class,
			RestFeed::class,
			RestComposer::class,
			RestInteractions::class,
			RestComments::class,
			RestFollows::class,
			RestReports::class,
			RestPolls::class,
		);

		foreach ( $modules as $module ) {
			if ( ! SafeBoot::register_module( $module ) ) {
				return;
			}
		}

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			foreach ( array( Admin::class, ReportAdmin::class ) as $module ) {
				if ( ! SafeBoot::register_module( $module ) ) {
					return;
				}
			}
		}
	}

	/** Return a concise identity payload. */
	public static function identity() {
		return array(
			'name'           => 'Sabri Complete Home and News Feed',
			'version'        => SABRI_HNF_VERSION,
			'slug'           => SABRI_HNF_SLUG,
			'text_domain'    => SABRI_HNF_TEXT_DOMAIN,
			'schema_version' => SABRI_HNF_SCHEMA_VERSION,
		);
	}
}
