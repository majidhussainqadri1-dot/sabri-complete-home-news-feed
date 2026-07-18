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
		$capabilities = Capabilities::apply_default_policy();
		$schema       = Migrations::migrate( false );

		Taxonomies::register_taxonomies();
		$terms = Taxonomies::ensure_default_terms();

		if ( function_exists( 'update_option' ) ) {
			update_option( 'sabri_feed_flush_rewrite_rules', 1, false );
			update_option( 'sabri_feed_taxonomy_version', SABRI_HNF_VERSION, false );
		}

		AuditLog::record(
			'activation',
			array(
				'schema_version' => SABRI_HNF_SCHEMA_VERSION,
				'phase'          => 'phase_1_foundation',
			)
		);

		return array(
			'snapshot'     => $snapshot,
			'capabilities' => $capabilities,
			'schema'       => $schema,
			'terms'        => $terms,
		);
	}
}
