<?php
/**
 * File 23 Publishing Dashboard registration bridge.
 *
 * The bridge is loaded with File 21, but the actual adapter class is required
 * only after File 23 has loaded its versioned interfaces. This avoids plugin
 * load-order fatals while preserving File 21 as the native publication owner.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class File23PublishingDashboardBridge {
	/** Register the late File 23 adapter callback. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'spdb/register_adapters', array( __CLASS__, 'register_adapter' ), 10, 1 );
		}
	}

	/**
	 * Register the native File 21 adapter into File 23's exact registry.
	 *
	 * @param mixed $registry File 23 adapter registry.
	 * @return void
	 */
	public static function register_adapter( $registry ) {
		$requirements = array(
			'SPDB_Provider_Adapter',
			'SPDB_Workspace_Provider_Adapter',
			'SPDB_Review_Calendar_Provider_Adapter',
		);
		foreach ( $requirements as $interface ) {
			if ( ! interface_exists( $interface ) ) {
				return;
			}
		}
		if ( ! is_object( $registry ) || ! is_callable( array( $registry, 'register' ) ) ) {
			return;
		}

		$file = SABRI_HNF_PATH . 'includes/class-file23-publishing-dashboard-adapter-runtime.php';
		if ( ! is_readable( $file ) ) {
			if ( is_callable( array( $registry, 'record_error' ) ) && class_exists( '\\WP_Error' ) ) {
				$registry->record_error( 'sabri_home_news_feed', new \WP_Error( 'file21_spdb_adapter_file_missing', __( 'The File 21 Publishing Dashboard adapter file is missing.', 'sabri-complete-home-news-feed' ) ) );
			}
			return;
		}
		require_once $file;
		if ( ! class_exists( __NAMESPACE__ . '\\File23PublishingDashboardAdapterRuntime', false ) ) {
			return;
		}

		$result = $registry->register( new File23PublishingDashboardAdapterRuntime() );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			do_action( 'sabri_hnf_file23_adapter_registration_error', sanitize_key( (string) $result->get_error_code() ) );
		}
	}
}
