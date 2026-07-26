<?php
/** File 21 comprehensive architecture-harmonization contract tests. */
require_once __DIR__ . '/bootstrap.php';

$root = dirname( __DIR__ );
$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

foreach ( array(
	'includes/class-canonical-identity-adapter.php',
	'includes/class-companion-integration-registry.php',
	'includes/class-companion-home-row-adapters.php',
	'includes/class-viral-ranking-signals.php',
	'includes/class-home-composition-registry.php',
	'includes/class-legacy-interaction-migration-adapter.php',
	'includes/class-legacy-publication-migration.php',
	'includes/class-legacy-publication-rollback.php',
	'includes/class-harmonization-diagnostics.php',
	'includes/class-harmonized-settings.php',
	'includes/class-search-provider-registry.php',
	'assets/css/home-composition.css',
	'FILE-21-HARMONIZATION-COMPLETION-PLAN.md',
) as $file ) {
	$assert( is_file( $root . '/' . $file ), 'Missing harmonization file: ' . $file );
}

$bootstrap = file_get_contents( $root . '/sabri-complete-home-news-feed.php' );
foreach ( array( 'Version: 1.0.1', "define( 'SABRI_HNF_VERSION', '1.0.1' );", "define( 'SABRI_HNF_SCHEMA_VERSION', '1.0.0' );", 'sabri_hnf_activate', 'sabri_hnf_deactivate', 'sabri_hnf_bootstrap' ) as $needle ) {
	$assert( false !== strpos( $bootstrap, $needle ), 'Complete 1.0.1 bootstrap contract missing: ' . $needle );
}

$plugin = file_get_contents( $root . '/includes/class-plugin.php' );
foreach ( array(
	'CanonicalIdentityAdapter::class',
	'CompanionIntegrationRegistry::class',
	'CompanionHomeRowAdapters::class',
	'SearchProviderRegistry::class',
	'ViralRankingSignals::class',
	'HomeCompositionRegistry::class',
	'LegacyInteractionMigrationAdapter::class',
	'LegacyPublicationMigration::class',
	'LegacyPublicationRollback::class',
	'HarmonizationDiagnostics::class',
	'HarmonizedSettings::class',
) as $needle ) {
	$assert( false !== strpos( $plugin, $needle ), 'Runtime module is not registered: ' . $needle );
}

$identity = file_get_contents( $root . '/includes/class-canonical-identity-adapter.php' );
foreach ( array( 'sabri_verified_doctor', 'sabri_doctor_pending', '_smc_doctor_verified', '_smc_trusted_publisher', '_spd_verification_status', 'verified_doctor_ids', 'public_projection' ) as $needle ) {
	$assert( false !== strpos( $identity, $needle ), 'Canonical identity contract missing: ' . $needle );
}

$context = file_get_contents( $root . '/includes/class-feed-context.php' );
foreach ( array( "'most-viral'", "'doctors-posts'", "'remedies'", "'diseases'", "'homeopathy-philosophy'", "'principles-of-hygiene'" ) as $needle ) {
	$assert( false !== strpos( $context, $needle ), 'Home Feed mode or approved topic missing: ' . $needle );
}

$home = file_get_contents( $root . '/includes/class-home-composition-registry.php' );
foreach ( array( 'Most Viral', 'Founder Posts', 'Doctors Posts', 'Videos', 'Reels', 'PDF Books', 'Clinics', 'Marketplace', 'sabri_shell_home_main', 'sabri_hnf_home_row_items_' ) as $needle ) {
	$assert( false !== strpos( $home, $needle ), 'Home composition contract missing: ' . $needle );
}

$home_css = file_get_contents( $root . '/assets/css/home-composition.css' );
foreach ( array( '.sabri-hnf-home-control', '.sabri-hnf-home-row__items', '@media (max-width: 900px)', '@media (max-width: 600px)', ':focus-visible', 'prefers-reduced-motion' ) as $needle ) {
	$assert( false !== strpos( $home_css, $needle ), 'Responsive/accessibility Home CSS missing: ' . $needle );
}

$viral = file_get_contents( $root . '/includes/class-viral-ranking-signals.php' );
foreach ( array( "'views'", "'reactions'", "'comments'", "'saves'", "'shares'", "'watch_seconds'", "'reports'", "'quality'", 'log(' ) as $needle ) {
	$assert( false !== strpos( $viral, $needle ), 'Viral ranking signal missing: ' . $needle );
}

$migration = file_get_contents( $root . '/includes/class-legacy-publication-migration.php' );
foreach ( array( 'snp_publication', 'preview(', 'migrate_selected(', 'copy_comments(', 'interaction_report(', 'interaction_provider_requested', 'MAPPING_OPTION', 'wp_safe_redirect', '301', 'Snapshot::capture_before_mutation', 'automatic' ) as $needle ) {
	$assert( false !== strpos( $migration, $needle ), 'Legacy File 04 migration safeguard missing: ' . $needle );
}
$assert( false === stripos( $migration, 'DELETE FROM' ), 'Legacy migration contains destructive SQL.' );
$assert( false === stripos( $migration, 'wp_delete_post' ), 'Legacy migration deletes source posts.' );

$interaction = file_get_contents( $root . '/includes/class-legacy-interaction-migration-adapter.php' );
foreach ( array( 'MAX_PROVIDER_RECORDS', 'source_schema', 'supports_rollback', 'target_provenance_failed', 'source_deleted', 'unavailable', 'normalize_report' ) as $needle ) {
	$assert( false !== strpos( $interaction, $needle ), 'Interaction migration provider contract missing: ' . $needle );
}

$rollback = file_get_contents( $root . '/includes/class-legacy-publication-rollback.php' );
foreach ( array( 'rollback_selected', "'post_status' => 'private'", "'status'] = 'rolled_back'", 'target_belongs_to_legacy', 'destructive' ) as $needle ) {
	$assert( false !== strpos( $rollback, $needle ), 'Non-destructive rollback contract missing: ' . $needle );
}

$registry = file_get_contents( $root . '/includes/class-companion-integration-registry.php' );
foreach ( array( 'sabri_network', 'swc_request_appointment', 'sabri_marketplace', 'sun_notify', 'snp_publication', 'slc_learning_home', 'svw_video_wall', 'srl_reels', 'spl_library' ) as $needle ) {
	$assert( false !== strpos( $registry, $needle ), 'Actual companion contract missing: ' . $needle );
}

$search = file_get_contents( $root . '/includes/class-search-provider-registry.php' );
foreach ( array( 'sabri_search_providers', 'sabri_shell_search_providers', 'PostMetadata::user_can_view', 'NewsPolicy::public_reads_allowed', 'MAX_RESULTS_PER_PROVIDER', "array( 'q' => $query" ) as $needle ) {
	$assert( false !== strpos( $search, $needle ), 'Search-provider safeguard missing: ' . $needle );
}

$card = file_get_contents( $root . '/templates/feed-card.php' );
foreach ( array( 'profile_url', 'specialty', 'country', 'clinic_name' ) as $needle ) {
	$assert( false !== strpos( $card, $needle ), 'Feed-card public author field missing: ' . $needle );
}

$integrations = file_get_contents( $root . '/includes/class-integrations.php' );
foreach ( array( 'CompanionIntegrationRegistry::all', 'sabri_shell_home_main', 'File 20 owns the global Shell' ) as $needle ) {
	$assert( false !== strpos( $integrations, $needle ), 'File 20/File 21 integration contract missing: ' . $needle );
}

$diagnostics = file_get_contents( $root . '/includes/class-harmonization-diagnostics.php' );
foreach ( array( 'File 21 release identity', 'Plugin bootstrap completeness', 'Home composition runtime', 'File 04 migration and rollback', 'File 20 rendering slots', 'code_ready_for_exact_head_qa', 'live_release_ready' ) as $needle ) {
	$assert( false !== strpos( $diagnostics, $needle ), 'Harmonization diagnostic/readiness contract missing: ' . $needle );
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}
echo "File 21 comprehensive harmonization contract tests passed.\n";
