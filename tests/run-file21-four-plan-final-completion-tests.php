<?php
/**
 * File 21 final governing-plan reconciliation regression.
 *
 * This source-contract suite protects the cross-file boundaries most likely to
 * regress during package replacement: File 20 shell ownership, File 22 create
 * orchestration, File 23 dashboard projections, File 26 global search ownership,
 * green public presentation and truthful release status separation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

$root = dirname( __DIR__ );
$files = array(
	'bootstrap' => 'sabri-complete-home-news-feed.php',
	'plugin' => 'includes/class-plugin.php',
	'file22' => 'includes/class-universal-composer-subject-schema-adapter.php',
	'file23_bridge' => 'includes/class-file23-publishing-dashboard-bridge.php',
	'file23_runtime' => 'includes/class-file23-publishing-dashboard-adapter-runtime.php',
	'file26' => 'includes/class-search-provider-registry.php',
	'feed_css' => 'assets/css/feed.css',
	'builder' => 'tools/build-release.py',
);

$sources = array();
$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

foreach ( $files as $key => $relative ) {
	$path = $root . '/' . $relative;
	$sources[ $key ] = is_file( $path ) ? file_get_contents( $path ) : false;
	$assert( false !== $sources[ $key ], 'Required source is missing or unreadable: ' . $relative );
}

if ( false !== $sources['plugin'] ) {
	foreach ( array( 'SearchProviderRegistry::class', 'UniversalComposerBridge::class', 'File23PublishingDashboardBridge::class', 'HomeIntegration::class' ) as $needle ) {
		$assert( false !== strpos( $sources['plugin'], $needle ), 'Plugin coordinator is missing required integration: ' . $needle );
	}
}

if ( false !== $sources['file22'] ) {
	$assert( false !== strpos( $sources['file22'], "required_capability(): string { return 'read'; }" ), 'File 22 registry gate must stay coarse.' );
	$assert( false !== strpos( $sources['file22'], 'return $this->delegate->can_create( $user_id );' ), 'File 22 final authorization must stay native to File 21.' );
}

if ( false !== $sources['file23_bridge'] ) {
	$assert( false !== strpos( $sources['file23_bridge'], "spdb/register_adapters" ), 'File 23 exact adapter-registration hook is required.' );
}
if ( false !== $sources['file23_runtime'] ) {
	$assert( false !== strpos( $sources['file23_runtime'], 'public function get_operation_definitions(): array' ), 'File 23 provider must explicitly declare operations.' );
	$assert( false !== strpos( $sources['file23_runtime'], 'return array();' ), 'File 23 direct operation definitions must remain empty until a separately accepted write adapter exists.' );
	$assert( false !== strpos( $sources['file23_runtime'], 'file21_spdb_write_not_accepted' ), 'File 23 direct writes must fail closed.' );
}

if ( false !== $sources['file26'] ) {
	$assert( false !== strpos( $sources['file26'], "'global_search_owner' => '26'" ), 'File 26 must be declared as the global search owner.' );
	$assert( false !== strpos( $sources['file26'], "'status' => 'proposed'" ), 'File 21 must register File 26 connector in proposed state only.' );
	$assert( false === strpos( $sources['file26'], "'status' => 'active'" ), 'File 21 must not self-activate File 26.' );
}

if ( false !== $sources['feed_css'] ) {
	$assert( false === stripos( $sources['feed_css'], '#ff8a1f' ), 'Deprecated orange primary token must not appear in File 21 feed CSS.' );
	$assert( false !== stripos( $sources['feed_css'], '#1f7a55' ), 'Green primary action/accent token is required in File 21 feed CSS.' );
}

if ( false !== $sources['bootstrap'] && false !== $sources['builder'] ) {
	$assert( false !== strpos( $sources['bootstrap'], "define( 'SABRI_HNF_VERSION', '1.0.3' );" ), 'Stable runtime/API contract must remain 1.0.3 for this package correction.' );
	$assert( false !== strpos( $sources['bootstrap'], "define( 'SABRI_HNF_SCHEMA_VERSION', '1.0.0' );" ), 'Schema must remain 1.0.0; this reconciliation has no DB migration.' );
	$assert( false !== strpos( $sources['builder'], 'Hostinger staging accepted: NO' ), 'Release evidence must keep Hostinger staging separate from code/package completion.' );
	$assert( false !== strpos( $sources['builder'], 'Live deployed: NO' ), 'Release evidence must not claim live deployment.' );
}

if ( $failures ) {
	fwrite( STDERR, "File 21 final governing-plan reconciliation FAILED:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "File 21 final governing-plan reconciliation: PASS\n";
