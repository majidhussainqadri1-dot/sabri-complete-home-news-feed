<?php
/**
 * Safe rewrite-rule lifecycle.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rebuilds rewrite rules only after WordPress has registered its route providers.
 */
final class RewriteRules {
	const FLUSH_OPTION = 'sabri_feed_flush_rewrite_rules';

	/** Register the one-shot late-init repair. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', array( __CLASS__, 'flush_scheduled' ), 99 );
		}
	}

	/**
	 * Flush a scheduled rewrite repair once, after core and plugin routes exist.
	 *
	 * Activation can run before a complete public request lifecycle, especially in
	 * WordPress Playground and some managed-host installers. Flushing during that
	 * incomplete lifecycle can save an empty rewrite map and make every pretty URL
	 * fall through to the posts index. The activation hook therefore schedules the
	 * operation, and this late init callback performs it on the next real request.
	 *
	 * @return void
	 */
	public static function flush_scheduled() {
		if ( ! function_exists( 'get_option' ) || ! get_option( self::FLUSH_OPTION, 0 ) ) {
			return;
		}

		if ( ! function_exists( 'flush_rewrite_rules' ) ) {
			return;
		}

		flush_rewrite_rules( false );

		if ( function_exists( 'update_option' ) ) {
			update_option( self::FLUSH_OPTION, 0, false );
		}
	}
}
