<?php
/** File 21 v1.0.6 regression for File 04 migration and current companion parity. */
$root = dirname( __DIR__ );
$failures = array();
$read = static function ( $relative ) use ( $root, &$failures ) {
	$path = $root . '/' . $relative;
	if ( ! is_file( $path ) ) { $failures[] = 'Missing file: ' . $relative; return ''; }
	return file_get_contents( $path );
};
$check = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) { $failures[] = $message; }
};

$identity = $read( 'includes/class-canonical-identity-adapter.php' );
$contracts = $read( 'includes/class-file04-migration-contracts.php' );
$migration = $read( 'includes/class-legacy-publication-migration.php' );
$search = $read( 'includes/class-search-provider-registry.php' );
$plugin = $read( 'includes/class-plugin.php' );
$bootstrap = $read( 'sabri-complete-home-news-feed.php' );

$check( false !== strpos( $identity, "if ( empty( $assertions['mfa_required'] ) )" ), 'File 21 still resurrects retired File 00 MFA.' );
$check( false !== strpos( $contracts, 'sabri_file21_legacy_media_preflight_v1' ), 'File 04 media preflight provider is missing.' );
$check( false !== strpos( $contracts, 'sabri_file21_verify_migrated_legacy_media_v1' ), 'File 04 post-migration media verifier is missing.' );
$check( false !== strpos( $migration, "'require_file04_context' => false" ), 'File 21 File 04 context option is missing.' );
$check( false !== strpos( $migration, "'post_author' => (int) $author_context['user_id']" ), 'File 21 still copies the legacy author instead of the governed author context.' );
$check( false !== strpos( $migration, '_sabri_hnf_legacy_author_platform_uuid' ), 'Governed author UUID evidence is not persisted.' );
$check( false !== strpos( $migration, '_sabri_hnf_legacy_media_attestation_v1' ), 'Media attestation evidence is not persisted.' );
$check( false !== strpos( $plugin, 'File04MigrationContracts::class' ), 'File 04 migration contracts are not registered.' );
$check( false !== strpos( $search, "'owner_file' => 'File 21'" ), 'File 26 owner identity is not canonical.' );
$check( false !== strpos( $search, "'entity_types' => array( 'post', 'news', 'article' )" ), 'File 26 owner connector does not satisfy current required entity types.' );
$check( false !== strpos( $search, "'status' => 'proposed'" ), 'File 21 must not self-promote its File 26 connector.' );
$check( false !== strpos( $bootstrap, '* Version: 1.0.6' ), 'Package identity must be 1.0.6.' );
$check( false !== strpos( $bootstrap, "define( 'SABRI_HNF_VERSION', '1.0.4' );" ), 'Runtime/API identity must be 1.0.4.' );

if ( $failures ) { fwrite( STDERR, implode( "\n", $failures ) . "\n" ); exit( 1 ); }
echo "File 21/File 04 migration contract regression passed.\n";
