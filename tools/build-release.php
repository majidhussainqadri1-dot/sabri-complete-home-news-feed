<?php
/**
 * Build a Phase 1 development release artifact.
 *
 * @package SabriCompleteHomeNewsFeed
 */

$root = dirname( __DIR__ );
$release_dir = $root . DIRECTORY_SEPARATOR . 'release';
$slug = 'sabri-complete-home-news-feed';
$base = '21-sabri-complete-home-news-feed-1.0.0-PHASE-1';
$zip_path = $release_dir . DIRECTORY_SEPARATOR . $base . '.zip';
$sha_path = $release_dir . DIRECTORY_SEPARATOR . $base . '.sha256';
$report_path = $release_dir . DIRECTORY_SEPARATOR . $base . '-TEST-REPORT.md';

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "ZipArchive is unavailable.\n" );
	exit( 1 );
}

if ( ! is_dir( $release_dir ) ) {
	mkdir( $release_dir, 0777, true );
}

foreach ( array( $zip_path, $sha_path, $report_path ) as $path ) {
	if ( is_file( $path ) ) {
		unlink( $path );
	}
}

$excluded_dirs = array( '.git', '.github', 'tools', 'tests', 'release', 'vendor', 'node_modules' );
$excluded_files = array( 'TASK_LOG.md', '.gitignore' );
$files = array();

$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() ) {
		continue;
	}

	$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
	$parts = explode( '/', $relative );

	if ( in_array( $parts[0], $excluded_dirs, true ) || in_array( basename( $relative ), $excluded_files, true ) ) {
		continue;
	}

	$files[] = $relative;
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "Unable to create ZIP.\n" );
	exit( 1 );
}

$zip->addEmptyDir( $slug );
foreach ( $files as $relative ) {
	$zip->addFile( $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative ), $slug . '/' . $relative );
}
$zip->close();

$hash = hash_file( 'sha256', $zip_path );
file_put_contents( $sha_path, $hash . '  ' . basename( $zip_path ) . PHP_EOL );

$report = "# Sabri Complete Home and News Feed Phase 1 Test Report\n\n";
$report .= "- Version: 1.0.0\n";
$report .= "- Artifact: " . basename( $zip_path ) . "\n";
$report .= "- SHA-256: " . $hash . "\n";
$report .= "- Top-level ZIP folder: " . $slug . "/\n";
$report .= "- Runtime files included: " . count( $files ) . "\n";
$report .= "- Excluded development paths: " . implode( ', ', array_merge( $excluded_dirs, $excluded_files ) ) . "\n";
$report .= "- Release status: Phase 1 development artifact only; not the final complete plugin release.\n";
file_put_contents( $report_path, $report );

echo "Built {$zip_path}\n";
