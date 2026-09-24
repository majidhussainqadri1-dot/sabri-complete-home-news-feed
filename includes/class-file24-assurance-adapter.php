<?php
/**
 * File 24 security/privacy assurance manifest adapter.
 *
 * @package SabriCompleteHomeNewsFeed
 */
namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Publishes a bounded, versioned module manifest to File 24 without transferring
 * native authorization, content, moderation, or data ownership.
 */
final class File24AssuranceAdapter {
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'spcrc/module_manifests', array( __CLASS__, 'append_manifest' ), 20 );
		}
	}

	public static function append_manifest( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		$manifests[] = self::manifest();
		return $manifests;
	}

	public static function manifest() {
		return array(
			'module_key'             => 'file21-home-news-feed',
			'name'                   => 'Sabri Complete Home and News Feed',
			'version'                => defined( 'SABRI_HNF_PACKAGE_VERSION' ) ? (string) SABRI_HNF_PACKAGE_VERSION : '1.0.5',
			'owner'                  => 'File 21',
			'posture'                => 'accepted',
			'data_classes'           => array( 'publications', 'editorial_news', 'comments', 'engagement', 'private_user_state' ),
			'public_routes'          => array( '/', '/news/', '/create-post/' ),
			'private_routes'         => array( '/wp-admin/' ),
			'capabilities'           => class_exists( __NAMESPACE__ . '\\Capabilities' ) ? Capabilities::capabilities() : array(),
			'external_vendors'       => array(),
			'privacy_operations'     => array( 'export', 'erase', 'retention' ),
			'last_security_test'     => '',
			'contract_version'       => '1.0.0',
			'canonical_data_owner'   => 'File 21',
			'canonical_action_owner' => 'File 21 native authorization and publication policy',
			'evidence_source'        => 'file21-release-evidence',
			'degraded_behavior'      => 'Public-safe reading may continue; privileged mutation fails closed when assurance dependencies are unavailable.',
			'release_gate'           => 'Repository QA is necessary but staging, live deployment, rollback and parity evidence remain separate gates.',
		);
	}
}
