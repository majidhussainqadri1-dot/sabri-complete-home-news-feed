<?php
/**
 * File 24 Security/Privacy Assurance manifest bridge.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Publishes a bounded, versioned File 21 module manifest to File 24 without
 * transferring native authorization, privacy or publication ownership.
 */
final class SecurityAssuranceIntegration {
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'spcrc/module_manifests', array( __CLASS__, 'append_manifest' ), 20 );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'spcrc/module_registry_ready', array( __CLASS__, 'register_with_registry' ), 20, 1 );
		}
	}

	/** @param mixed $manifests @return array<int,array<string,mixed>> */
	public static function append_manifest( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		$manifests[] = self::manifest();
		return $manifests;
	}

	/** Late-load compatibility for File 24 registries that expose register(). */
	public static function register_with_registry( $registry ) {
		if ( is_object( $registry ) && method_exists( $registry, 'register' ) ) {
			return (bool) $registry->register( self::manifest() );
		}
		return false;
	}

	/** Public-safe manifest accepted by the current File 24 Module Registry. */
	public static function manifest() {
		return array(
			'module_key' => 'file21-home-news-feed',
			'name' => 'Sabri Complete Home and News Feed',
			'version' => defined( 'SABRI_HNF_PACKAGE_VERSION' ) ? (string) SABRI_HNF_PACKAGE_VERSION : '1.0.5',
			'owner' => 'File 21',
			'posture' => 'foundation',
			'data_classes' => array(
				'publications',
				'editorial-news',
				'comments-interactions',
				'user-feed-preferences',
				'publication-audit',
			),
			'public_routes' => array( '/', '/news/', '/create-post/' ),
			'private_routes' => array(),
			'capabilities' => Capabilities::capabilities(),
			'external_vendors' => array(),
			'privacy_operations' => array( 'export', 'erase', 'retention' ),
			'last_security_test' => '',
			'contract_version' => '1.0.0',
			'canonical_data_owner' => 'File 21',
			'canonical_action_owner' => 'File 21 native publication policies',
			'evidence_source' => 'file21-repository-qa',
			'degraded_behavior' => 'Public safe reads remain bounded; privileged publishing and mutation paths fail closed when required authority is unavailable.',
			'release_gate' => 'Repository QA does not establish staging or live acceptance; deployment parity and environment evidence remain required.',
		);
	}
}
