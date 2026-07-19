<?php
/**
 * Phase 3G direct single-post view runtime.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Connects the view service to eligible front-end single-post requests. */
final class ViewRuntime {
	/** Register after single-post visibility enforcement. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'record_single_post_view' ), 20 );
		}
	}

	/** Record one eligible request without changing the response. */
	public static function record_single_post_view() {
		if ( ! Phase3FeatureSettings::enabled( 'view_logging_enabled' ) || ! HomeIntegration::is_single_post_request() ) {
			return;
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return;
		}
		foreach ( array( 'is_preview', 'is_feed', 'is_robots', 'is_trackback' ) as $conditional ) {
			if ( function_exists( $conditional ) && call_user_func( $conditional ) ) {
				return;
			}
		}

		$post_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		if ( $post_id <= 0 ) {
			return;
		}

		ViewService::record( $post_id );
	}
}
