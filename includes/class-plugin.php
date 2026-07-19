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

	/** Register plugin hooks. */
	public function register() {
		if ( $this->registered ) {
			return;
		}
		$this->registered = true;

		Settings::register();
		Phase3FeatureSettings::register();
		PollComposerIntegration::register();
		Capabilities::register();
		PostTypes::register();
		Taxonomies::register();
		Integrations::register();
		SafeMode::register();
		RestFoundation::register();
		DataRetention::register();
		Assets::register();
		PostMetadata::register();
		MediaHandler::register();
		FeedQuery::register();
		HomeIntegration::register();
		PollRuntime::register();
		SocialRuntime::register();
		CommentRuntime::register();
		SavedPostsRuntime::register();
		FollowingRuntime::register();
		Shortcodes::register();
		Composer::register();
		RestFeed::register();
		RestComposer::register();
		RestInteractions::register();
		RestComments::register();
		RestFollows::register();
		RestReports::register();
		RestPolls::register();

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			Admin::register();
			ReportAdmin::register();
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
