<?php
/**
 * Activation boundary.
 *
 * @package SabriCompleteHomeNewsFeed
 */
namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Runs non-destructive activation work. */
final class Activator {
	public static function activate() {
		$snapshot = Snapshot::capture_before_mutation( 'activation' );
		Settings::ensure_defaults();
		$phase4_settings = NewsFeatureSettings::ensure_defaults();
		$capabilities = Capabilities::apply_default_policy();
		$phase4_caps = NewsCapabilities::apply_default_policy();
		$phase5_settings = Phase5FeatureSettings::ensure_defaults();
		$phase5_caps = Phase5Capabilities::apply_default_policy();
		$schema = Migrations::migrate( false );
		$phase5_schema = Phase5Migrations::migrate( false );
		$public_surface_recovery = PublicSurfaceRecovery::maybe_recover();

		Taxonomies::register_taxonomies();
		$terms = Taxonomies::ensure_default_terms();
		EditorialNewsPostType::register_post_type();
		NewsTaxonomies::register_taxonomies();
		$phase4_terms = NewsTaxonomies::ensure_default_terms();
		$phase4_ready = ! empty( $phase4_terms['success'] );
		$contract = Phase4Contracts::TARGET_VERSION . '-' . Phase4Contracts::CHECKPOINT;

		if ( function_exists( 'update_option' ) ) {
			update_option( 'sabri_feed_flush_rewrite_rules', 1, false );
			update_option( 'sabri_feed_taxonomy_version', SABRI_HNF_VERSION, false );
			if ( $phase4_ready ) {
				update_option( NewsTaxonomies::TERM_VERSION_OPTION, $contract, false );
				update_option( 'sabri_feed_phase4_contract_version', $contract, false );
			}
		}
		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_event' ) && ! wp_next_scheduled( 'sabri_hnf_phase5_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'sabri_hnf_phase5_cleanup' );
		}
		AuditLog::record(
			'activation',
			array(
				'schema_version' => SABRI_HNF_SCHEMA_VERSION,
				'phase' => 'phase_4a_content_model',
				'phase4_gates' => array_sum( $phase4_settings ),
				'phase4_terms_success' => $phase4_ready ? 1 : 0,
				'phase4_terms_failed' => isset( $phase4_terms['failed'] ) && is_array( $phase4_terms['failed'] ) ? count( $phase4_terms['failed'] ) : 0,
				'public_surface_recovered' => ! empty( $public_surface_recovery['changed'] ) ? 1 : 0,
			)
		);
		return array(
			'snapshot' => $snapshot,
			'capabilities' => $capabilities,
			'phase4_capabilities' => $phase4_caps,
			'schema' => $schema,
			'terms' => $terms,
			'phase4_terms' => $phase4_terms,
			'phase4_ready' => $phase4_ready,
			'phase4_settings' => $phase4_settings,
			'phase5_settings' => $phase5_settings,
			'phase5_capabilities' => $phase5_caps,
			'phase5_schema' => $phase5_schema,
			'public_surface_recovery' => $public_surface_recovery,
		);
	}
}
