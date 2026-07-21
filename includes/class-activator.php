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

/** Runs non-destructive activation work. */
final class Activator {
	/** Activate the plugin. */
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
		$phase4_ready = ! empty( $phase4_terms['success'] );
		$contract     = Phase4Contracts::TARGET_VERSION . '-' . Phase4Contracts::CHECKPOINT;

		if ( function_exists( 'update_option' ) ) {
			update_option( 'sabri_feed_flush_rewrite_rules', 1, false );
			update_option( 'sabri_feed_taxonomy_version', SABRI_HNF_VERSION, false );
			if ( $phase4_ready ) {
				update_option( NewsTaxonomies::TERM_VERSION_OPTION, $contract, false );
				update_option( 'sabri_feed_phase4_contract_version', $contract, false );
			}
		}

		AuditLog::record(
			'activation',
			array(
				'schema_version'       => SABRI_HNF_SCHEMA_VERSION,
				'phase'                => 'phase_4a_content_model',
				'phase4_gates'         => array_sum( $phase4_settings ),
				'phase4_terms_success' => $phase4_ready ? 1 : 0,
				'phase4_terms_failed'  => isset( $phase4_terms['failed'] ) && is_array( $phase4_terms['failed'] ) ? count( $phase4_terms['failed'] ) : 0,
			)
		);

		return array(
			'snapshot'            => $snapshot,
			'capabilities'        => $capabilities,
			'phase4_capabilities' => $phase4_caps,
			'schema'              => $schema,
			'terms'               => $terms,
			'phase4_terms'        => $phase4_terms,
			'phase4_ready'        => $phase4_ready,
			'phase4_settings'     => $phase4_settings,
		);
	}
}
