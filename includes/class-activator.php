<?php
/**
 * Activation boundary.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs non-destructive activation work.
 */
final class Activator {
	/**
	 * Activate the plugin.
	 *
	 * @return array<string,mixed>
	 */
	public static function activate() {
		$snapshot = Snapshot::capture_before_mutation( 'activation' );

		Settings::ensure_defaults();
		$phase4_settings = NewsFeatureSettings::ensure_defaults();
		$capabilities    = Capabilities::apply_default_policy();
		$phase4_caps     = NewsCapabilities::apply_default_policy();
		$schema          = Migrations::migrate( false );

		Taxonomies::register_taxonomies();
		$terms = Taxonomies::ensure_default_terms();

		EditorialNewsPostType::register_post_type();
		NewsTaxonomies::register_taxonomies();
		$phase4_terms = NewsTaxonomies::ensure_default_terms();

		if ( function_exists( 'update_option' ) ) {
			update_option( 'sabri_feed_flush_rewrite_rules', 1, false );
			update_option( 'sabri_feed_taxonomy_version', SABRI_HNF_VERSION, false );
			update_option( 'sabri_feed_phase4_contract_version', Phase4Contracts::TARGET_VERSION . '-' . Phase4Contracts::CHECKPOINT, false );
		}

		AuditLog::record(
			'activation',
			array(
				'schema_version' => SABRI_HNF_SCHEMA_VERSION,
				'phase'          => 'phase_4a_content_model',
				'phase4_gates'   => array_sum( $phase4_settings ),
			)
		);

		return array(
			'snapshot'            => $snapshot,
			'capabilities'        => $capabilities,
			'phase4_capabilities' => $phase4_caps,
			'schema'              => $schema,
			'terms'               => $terms,
			'phase4_terms'        => $phase4_terms,
			'phase4_settings'     => $phase4_settings,
		);
	}
}
