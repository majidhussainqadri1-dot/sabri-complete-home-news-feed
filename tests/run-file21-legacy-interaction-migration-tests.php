<?php
/**
 * Behavioral tests for the File 04 interaction migration provider boundary.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\LegacyInteractionMigrationAdapter;
use Sabri\HomeNewsFeed\LegacyPublicationMigration;

$interaction_migration_failures = array();
$interaction_migration_assert = static function ( $condition, $message ) use ( &$interaction_migration_failures ) {
	if ( ! $condition ) {
		$interaction_migration_failures[] = $message;
	}
};

sabri_test_reset_state( true );
global $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_filter_overrides;

$legacy_id = 44;
$target_id = sabri_test_add_post(
	array(
		'post_author' => 2,
		'post_status' => 'publish',
		'post_title' => 'Migrated File 04 target',
	),
	array(
		'_sabri_hnf_legacy_source_id' => $legacy_id,
		'_sabri_hnf_legacy_source_type' => LegacyPublicationMigration::LEGACY_POST_TYPE,
	)
);
$wrong_target_id = sabri_test_add_post(
	array(
		'post_author' => 2,
		'post_status' => 'publish',
		'post_title' => 'Unrelated target',
	),
	array(
		'_sabri_hnf_legacy_source_id' => 999,
		'_sabri_hnf_legacy_source_type' => LegacyPublicationMigration::LEGACY_POST_TYPE,
	)
);

LegacyInteractionMigrationAdapter::register();

// The adapter must not borrow authority from a supplied actor ID.
$sabri_test_current_user_id = 0;
$sabri_test_current_caps = array();
$unauthorized = LegacyInteractionMigrationAdapter::migrate( $legacy_id, $target_id, 1, '' );
$interaction_migration_assert( 'permission_denied' === $unauthorized['status'], 'A supplied Administrator ID must not bypass current-session authorization.' );
$interaction_migration_assert( in_array( 'actor_authorization_failed', $unauthorized['errors'], true ), 'Unauthorized reports must expose only a controlled authorization error.' );
$interaction_migration_assert( false === $unauthorized['source_deleted'] && false === $unauthorized['automatic'], 'Unauthorized migration must remain non-destructive and manual.' );

$sabri_test_current_user_id = 1;
$sabri_test_current_caps = array( 'manage_options' => true );

// Provenance must be verified before a provider is called.
$invalid_target = LegacyInteractionMigrationAdapter::migrate( $legacy_id, $wrong_target_id, 1, '' );
$interaction_migration_assert( 'invalid_target' === $invalid_target['status'], 'A target without matching File 04 provenance must be rejected.' );
$interaction_migration_assert( in_array( 'target_provenance_failed', $invalid_target['errors'], true ), 'Invalid provenance must return a controlled error code.' );

// No provider means unavailable, never fabricated zero-history success.
$sabri_test_filter_overrides['sabri_hnf_legacy_interaction_migration_providers'] = array();
$unavailable = LegacyInteractionMigrationAdapter::migrate( $legacy_id, $target_id, 1, '' );
$interaction_migration_assert( 'unavailable' === $unavailable['status'], 'Absent source-schema providers must be reported as unavailable.' );
$interaction_migration_assert( '' === $unavailable['provider'], 'An unavailable report must not invent a provider identity.' );
$interaction_migration_assert( 0 === $unavailable['migrated_records'] && array() === $unavailable['migrated_metrics'], 'Unavailable migration must not fabricate migrated records or metrics.' );

// One explicit schema provider may be auto-selected, but its untrusted report is bounded.
$received_context = array();
$sabri_test_filter_overrides['sabri_hnf_legacy_interaction_migration_providers'] = array(
	'file04-schema-v1' => array(
		'label' => '<b>File 04 Schema V1</b>',
		'source_schema' => 'snp_interactions_v1',
		'supports_rollback' => false,
		'callback' => static function ( $context ) use ( &$received_context ) {
			$received_context = $context;
			return array(
				'status' => 'migrated',
				'migrated_records' => 50000,
				'migrated_metrics' => array(
					'likes' => 50000,
					'saves' => -9,
					'unknown text' => 'not numeric',
				),
				'skipped_records' => 50001,
				'errors' => array_fill( 0, 60, '<b>bounded provider warning</b>' ),
				'source_deleted' => true,
				'automatic' => true,
			);
		},
	),
);
$providers = LegacyInteractionMigrationAdapter::providers();
$interaction_migration_assert( isset( $providers['file04-schema-v1'] ), 'A valid source-schema provider must appear in the descriptor registry.' );
$interaction_migration_assert( ! isset( $providers['file04-schema-v1']['callback'] ), 'Public provider descriptors must not expose executable callbacks.' );
$interaction_migration_assert( 'File 04 Schema V1' === $providers['file04-schema-v1']['label'], 'Provider labels must be sanitized.' );

$migrated = LegacyInteractionMigrationAdapter::migrate( $legacy_id, $target_id, 1, '' );
$interaction_migration_assert( 'migrated' === $migrated['status'] && 'file04-schema-v1' === $migrated['provider'], 'Exactly one verified provider may be selected and reported.' );
$interaction_migration_assert( LegacyInteractionMigrationAdapter::MAX_PROVIDER_RECORDS === $migrated['migrated_records'], 'Provider record totals must be capped.' );
$interaction_migration_assert( LegacyInteractionMigrationAdapter::MAX_PROVIDER_RECORDS === $migrated['migrated_metrics']['likes'], 'Provider aggregate metrics must be capped.' );
$interaction_migration_assert( 0 === $migrated['migrated_metrics']['saves'], 'Negative provider metrics must normalize to zero.' );
$interaction_migration_assert( ! isset( $migrated['migrated_metrics']['unknowntext'] ), 'Non-numeric provider metrics must be discarded.' );
$interaction_migration_assert( LegacyInteractionMigrationAdapter::MAX_PROVIDER_RECORDS === $migrated['skipped_records'], 'Skipped-record totals must be capped.' );
$interaction_migration_assert( 50 === count( $migrated['errors'] ) && 'bounded provider warning' === $migrated['errors'][0], 'Provider errors must be sanitized and capped to fifty.' );
$interaction_migration_assert( false === $migrated['source_deleted'] && false === $migrated['automatic'], 'A provider cannot override non-destructive/manual guarantees.' );
$interaction_migration_assert( false === $received_context['delete_source'] && false === $received_context['automatic'], 'Provider context must explicitly forbid source deletion and automation.' );
$interaction_migration_assert( LegacyInteractionMigrationAdapter::MAX_PROVIDER_RECORDS === $received_context['max_records'], 'Provider context must expose the fixed record ceiling.' );

// Provider exceptions must become controlled failed reports.
$sabri_test_filter_overrides['sabri_hnf_legacy_interaction_migration_providers'] = array(
	'broken-provider' => array(
		'label' => 'Broken provider',
		'source_schema' => 'broken',
		'callback' => static function () {
			throw new RuntimeException( 'Sensitive provider detail must not escape.' );
		},
	),
);
$failed = LegacyInteractionMigrationAdapter::migrate( $legacy_id, $target_id, 1, 'broken-provider' );
$interaction_migration_assert( 'failed' === $failed['status'], 'Provider exceptions must produce a controlled failed status.' );
$interaction_migration_assert( array( 'provider_exception:RuntimeException' ) === $failed['errors'], 'Exception reports may expose the exception class, not the sensitive message.' );
$interaction_migration_assert( false === $failed['source_deleted'] && false === $failed['automatic'], 'Provider exceptions must preserve non-destructive/manual guarantees.' );

if ( $interaction_migration_failures ) {
	fwrite( STDERR, "File 04 interaction migration tests failed:\n- " . implode( "\n- ", $interaction_migration_failures ) . "\n" );
	exit( 1 );
}

echo "File 04 interaction migration behavioral tests passed.\n";
