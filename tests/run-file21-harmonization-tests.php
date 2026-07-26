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
	'includes/class-viral-ranking-signals.php',
	'includes/class-home-composition-registry.php',
	'includes/class-legacy-publication-migration.php',
	'includes/class-harmonized-settings.php',
	'includes/class-search-provider-registry.php',
	'FILE-21-HARMONIZATION-COMPLETION-PLAN.md',
) as $file ) {
	$assert( is_file( $root . '/' . $file ), 'Missing harmonization file: ' . $file );
}

$plugin = file_get_contents( $root . '/includes/class-plugin.php' );
foreach ( array(
	'CanonicalIdentityAdapter::class',
	'CompanionIntegrationRegistry::class',
	'SearchProviderRegistry::class',
	'ViralRankingSignals::class',
	'HomeCompositionRegistry::class',
	'LegacyPublicationMigration::class',
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

$viral = file_get_contents( $root . '/includes/class-viral-ranking-signals.php' );
foreach ( array( "'views'", "'reactions'", "'comments'", "'saves'", "'shares'", "'watch_seconds'", "'reports'", "'quality'", 'log(' ) as $needle ) {
	$assert( false !== strpos( $viral, $needle ), 'Viral ranking signal missing: ' . $needle );
}

$migration = file_get_contents( $root . '/includes/class-legacy-publication-migration.php' );
foreach ( array( 'snp_publication', 'preview(', 'migrate_selected(', 'copy_comments(', 'MAPPING_OPTION', 'wp_safe_redirect', '301', 'Snapshot::capture_before_mutation', 'automatic' ) as $needle ) {
	$assert( false !== strpos( $migration, $needle ), 'Legacy File 04 migration safeguard missing: ' . $needle );
}
$assert( false === stripos( $migration, 'DELETE FROM' ), 'Legacy migration contains destructive SQL.' );
$assert( false === stripos( $migration, 'wp_delete_post' ), 'Legacy migration deletes source posts.' );

$registry = file_get_contents( $root . '/includes/class-companion-integration-registry.php' );
foreach ( array( 'sabri_network', 'swc_request_appointment', 'sabri_marketplace', 'sun_notify', 'snp_publication', 'slc_learning_home', 'svw_video_wall', 'srl_reels', 'spl_library' ) as $needle ) {
	$assert( false !== strpos( $registry, $needle ), 'Actual companion contract missing: ' . $needle );
}

$search = file_get_contents( $root . '/includes/class-search-provider-registry.php' );
foreach ( array( 'sabri_search_providers', 'sabri_shell_search_providers', 'PostMetadata::user_can_view', 'NewsPolicy::public_reads_allowed', 'MAX_RESULTS_PER_PROVIDER' ) as $needle ) {
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

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}
echo "File 21 comprehensive harmonization contract tests passed.\n";