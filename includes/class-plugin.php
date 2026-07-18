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
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether hooks are registered.
	 *
	 * @var bool
	 */
	private $registered = false;

	/**
	 * Return singleton.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register plugin hooks.
	 *
	 * @return void
	 */
	public function register() {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;

		Settings::register();
		Phase3FeatureSettings::register();
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
		SocialRuntime::register();
		Shortcodes::register();
		Composer::register();
		RestFeed::register();
		RestComposer::register();
		RestInteractions::register();

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			Admin::register();
		}
	}

	/**
	 * Return a concise identity payload.
	 *
	 * @return array<string,string>
	 */
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
