<?php
/**
 * File 21 -> File 24 assurance manifest contract regression.
 *
 * @package SabriCompleteHomeNewsFeed
 */

$root = dirname( __DIR__ );
$integration = file_get_contents( $root . '/includes/class-security-assurance-integration.php' );
$plugin = file_get_contents( $root . '/includes/class-plugin.php' );
$builder = file_get_contents( $root . '/tools/build-release.py' );

$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) { $failures[] = $message; }
};

$assert( false !== $integration, 'File 24 assurance integration must be readable.' );
$assert( false !== $plugin, 'Plugin coordinator must be readable.' );
$assert( false !== $builder, 'Release builder must be readable.' );

if ( false !== $integration ) {
	foreach ( array(
		"MANIFEST_CONTRACT_VERSION = '1.2.0'",
		"'spcrc/module_manifests'",
		"'spcrc/module_registry_ready'",
		"'module_key' => 'file21-home-news-feed'",
		"'owner' => 'File 21'",
		"'canonical_data_owner' => 'File 21'",
		"'canonical_action_owner' => 'File 21 native publication authorization'",
		"'verification_level' => 'not-applicable'",
		"'privacy_operations' => array( 'export', 'erase', 'retention' )",
		"'release_gate'",
		"'degraded_behavior'",
		"'last_security_test' => $last_test",
	) as $needle ) {
		$assert( false !== strpos( $integration, $needle ), 'Missing File 24 assurance invariant: ' . $needle );
	}
	$assert(
		false !== strpos( $integration, "'posture' => '' !== trim( $last_test ) ? 'foundation' : 'unassessed'"),
		'File 21 must not claim assessed File 24 posture without environment/security-test evidence.'
	);
}

if ( false !== $plugin ) {
	$assert( false !== strpos( $plugin, 'SecurityAssuranceIntegration::class' ), 'File 24 assurance module is not registered.' );
}
if ( false !== $builder ) {
	$assert( false !== strpos( $builder, '"includes/class-security-assurance-integration.php"' ), 'File 24 assurance runtime is not package-required.' );
}

if ( $failures ) {
	fwrite( STDERR, "File 21/File 24 assurance contract regression FAILED:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}
echo "File 21/File 24 assurance contract regression: PASS\n";
