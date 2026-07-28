<?php
/** Build the canonical File 21 corrective release artifact. */

$root = dirname( __DIR__ );
$release_dir = $root . DIRECTORY_SEPARATOR . 'release';
$slug = 'sabri-complete-home-news-feed';
$bootstrap = file_get_contents( $root . DIRECTORY_SEPARATOR . 'sabri-complete-home-news-feed.php' );
if ( ! is_string( $bootstrap ) || ! preg_match( '/^\s*\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/mi', $bootstrap, $match ) ) {
	fwrite( STDERR, "Unable to resolve the plugin version.\n" );
	exit( 1 );
}
$version = $match[1];
$base = '21-sabri-complete-home-news-feed-' . $version . '-PUBLIC-VISIBILITY-CANDIDATE';
$zip_path = $release_dir . DIRECTORY_SEPARATOR . $base . '.zip';
$sha_path = $release_dir . DIRECTORY_SEPARATOR . $base . '.sha256';
$report_path = $release_dir . DIRECTORY_SEPARATOR . $base . '-TEST-REPORT.md';

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "ZipArchive is unavailable.\n" );
	exit( 1 );
}
if ( ! is_dir( $release_dir ) && ! mkdir( $release_dir, 0777, true ) && ! is_dir( $release_dir ) ) {
	fwrite( STDERR, "Unable to create the release directory.\n" );
	exit( 1 );
}
foreach ( array( $zip_path, $sha_path, $report_path ) as $path ) {
	if ( is_file( $path ) ) { unlink( $path ); }
}

$excluded_dirs = array( '.git', '.github', 'tools', 'tests', 'release', 'vendor', 'node_modules' );
$excluded_files = array( 'TASK_LOG.md', '.gitignore' );
$files = array();
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() ) { continue; }
	$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
	$parts = explode( '/', $relative );
	if ( in_array( $parts[0], $excluded_dirs, true ) || in_array( basename( $relative ), $excluded_files, true ) ) { continue; }
	$files[] = $relative;
}
sort( $files );

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
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
$report = "# Sabri Complete Home and News Feed {$version} Test Report\n\n";
$report .= "- Version: {$version}\n";
$report .= "- Schema: 1.0.0\n";
$report .= '- Artifact: ' . basename( $zip_path ) . "\n";
$report .= '- SHA-256: ' . $hash . "\n";
$report .= '- Top-level ZIP folder: ' . $slug . "/\n";
$report .= '- Runtime files included: ' . count( $files ) . "\n";
$report .= "- Release status: exact-head corrective candidate; staging and live acceptance remain separate.\n";
file_put_contents( $report_path, $report );
echo "Built {$zip_path}\n";
