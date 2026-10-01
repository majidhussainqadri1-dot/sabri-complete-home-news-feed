<?php
/**
 * File 24 security/privacy assurance integration for File 21.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Publishes a bounded File 24 manifest while keeping all native File 21
 * authorization, publication, privacy and data ownership inside File 21.
 */
final class SecurityAssuranceIntegration {
	const MANIFEST_CONTRACT_VERSION = '1.2.0';

	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'spcrc/module_manifests', array( __CLASS__, 'append_manifest' ), 20 );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'spcrc/module_registry_ready', array( __CLASS__, 'register_late' ), 20, 1 );
		}
	}

	/** @param mixed $manifests @return array<int,array<string,mixed>> */
	public static function append_manifest( $manifests ) {
		$manifests = is_array( $manifests ) ? $manifests : array();
		$manifests[] = self::manifest();
		return $manifests;
	}

	/** Register after collection only for unusual plugin load orders. */
	public static function register_late( $registry ) {
		if ( is_object( $registry ) && method_exists( $registry, 'has' ) && method_exists( $registry, 'register' ) ) {
			if ( $registry->has( 'file21-home-news-feed' ) ) {
				return true;
			}
			return (bool) $registry->register( self::manifest() );
		}
		return false;
	}

	/** Current File 24 manifest schema. */
	public static function manifest() {
		$last_test = '';
		if ( function_exists( 'apply_filters' ) ) {
			$last_test = (string) apply_filters( 'sabri_hnf_file24_last_security_test', '' );
		}
		return array(
			'module_key' => 'file21-home-news-feed',
			'name' => 'Sabri Complete Home and News Feed',
			'version' => defined( 'SABRI_HNF_PACKAGE_VERSION' ) ? (string) SABRI_HNF_PACKAGE_VERSION : '1.0.5',
			'owner' => 'File 21',
			'posture' => '' !== trim( $last_test ) ? 'foundation' : 'unassessed',
			'data_classes' => array(
				'C1 Internal',
				'C2 Personal Metadata',
				'C3 User Content',
				'C4 Editorial and Clinical Context',
				'C5 Security Evidence References',
			),
			'public_routes' => array( '/', '/news/', '/create-post/' ),
			'private_routes' => array(
				'/wp-json/sabri-home-news-feed/v1/status',
				'/wp-json/sabri-home-news-feed/v1/schema',
			),
			'tables' => array(
				'sabri_feed_reactions',
				'sabri_feed_follows',
				'sabri_feed_saves',
				'sabri_feed_reports',
				'sabri_feed_views',
				'sabri_feed_poll_votes',
				'sabri_feed_audit_log',
				'sabri_news_sources',
				'sabri_news_reviews',
				'sabri_news_submissions',
				'sabri_news_submission_files',
				'sabri_news_corrections',
				'sabri_news_breaking',
				'sabri_news_translations',
				'sabri_news_preview_tokens',
				'sabri_news_rate_limits',
				'sabri_news_audit_integrity',
			),
			'files' => array( 'file21-runtime', 'file21-public-assets', 'file21-release-evidence' ),
			'capabilities' => class_exists( __NAMESPACE__ . '\\Capabilities' ) ? Capabilities::capabilities() : array(),
			'external_vendors' => array(),
			'secret_classes' => array( 'wordpress-nonce-metadata-only', 'preview-token-hash-metadata-only' ),
			'privacy_operations' => array( 'export', 'erase', 'retention' ),
			'exporters' => array( 'file21-next-generation-export', 'file21-editorial-submission-export' ),
			'erasers' => array( 'file21-next-generation-erase', 'file21-editorial-submission-erase' ),
			'emergency_callbacks' => array( 'file21-safe-mode', 'file21-duplicate-copy-recovery' ),
			'last_security_test' => $last_test,
			'verification_level' => 'not-applicable',
			'contract_version' => self::MANIFEST_CONTRACT_VERSION,
			'canonical_data_owner' => 'File 21',
			'canonical_action_owner' => 'File 21 native publication authorization',
			'evidence_source' => 'repository:file21-security-assurance-manifest',
			'degraded_behavior' => 'Native controls remain authoritative; privileged publication and mutation fail closed when required authority is unavailable.',
			'release_gate' => 'Repository quality evidence is not staging or live acceptance; deployment parity and environment verification remain required.',
		);
	}
}
