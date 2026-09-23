<?php
/**
 * File 24 security/privacy assurance manifest bridge.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publishes a bounded, versioned File 21 manifest to File 24 without moving
 * native authorization, content ownership, privacy handlers, or enforcement.
 */
final class File24SecurityManifestBridge {
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'spcrc/module_manifests', array( __CLASS__, 'append_manifest' ), 20 );
		}
	}

	/** @param mixed $manifests @return array<int,array<string,mixed>> */
	public static function append_manifest( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		$manifests[] = self::manifest();
		return array_values( $manifests );
	}

	/** @return array<string,mixed> */
	public static function manifest() {
		$capabilities = array();
		foreach ( array( Capabilities::class, NewsCapabilities::class, Phase5Contracts::class ) as $class_name ) {
			if ( class_exists( $class_name ) && is_callable( array( $class_name, 'capabilities' ) ) ) {
				$values = call_user_func( array( $class_name, 'capabilities' ) );
				if ( is_array( $values ) ) {
					$capabilities = array_merge( $capabilities, $values );
				}
			}
		}
		$capabilities = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_key', $capabilities ) ) ) ), 0, 100 );

		return array(
			'module_key'              => 'file21_home_news_feed',
			'name'                    => 'Sabri Complete Home and News Feed',
			'version'                 => defined( 'SABRI_HNF_PACKAGE_VERSION' ) ? (string) SABRI_HNF_PACKAGE_VERSION : '1.0.5',
			'owner'                   => 'File 21',
			'posture'                 => 'foundation',
			'data_classes'            => array(
				'public_content',
				'publishing_workflow',
				'editorial_evidence',
				'interaction_state',
				'private_user_preferences',
			),
			'public_routes'           => array( '/', '/news/' ),
			'private_routes'          => array( '/create-post/' ),
			'capabilities'            => $capabilities,
			'external_vendors'        => array(),
			'privacy_operations'      => array( 'export', 'erase', 'retention' ),
			'last_security_test'      => '',
			'contract_version'        => '1.0.0',
			'canonical_data_owner'    => 'File 21 Home and News Feed',
			'canonical_action_owner'  => 'File 21 native policy and authorization',
			'evidence_source'         => 'file21:repository_contracts',
			'degraded_behavior'       => 'Fail closed for writes; preserve bounded public reads and native fallback.',
			'release_gate'            => 'Repository validation only; staging and live acceptance require separate evidence.',
		);
	}
}
