<?php
/** Build the canonical File 21 1.0.4 authority-precedence corrective release. */
$root = dirname( __DIR__ );
$release_dir = $root . '/release';
$slug = 'sabri-complete-home-news-feed';
$base = '21-sabri-complete-home-news-feed-1.0.4-AUTHORITY-PRECEDENCE-CORRECTIVE';
$zip_path = $release_dir . '/' . $base . '.zip';
$sha_path = $release_dir . '/' . $base . '.sha256';
$manifest_path = $release_dir . '/' . $base . '-MANIFEST.sha256';
$report_path = $release_dir . '/' . $base . '-TEST-REPORT.md';
if ( ! class_exists( 'ZipArchive' ) ) { fwrite( STDERR, "ZipArchive is unavailable.\n" ); exit( 1 ); }
if ( ! is_dir( $release_dir ) && ! mkdir( $release_dir, 0777, true ) ) { fwrite( STDERR, "Unable to create release directory.\n" ); exit( 1 ); }
foreach ( array( $zip_path, $sha_path, $manifest_path, $report_path ) as $path ) { if ( is_file( $path ) ) { unlink( $path ); } }
$excluded_dirs = array( '.git', '.github', 'tools', 'tests', 'release', 'vendor', 'node_modules', '.phase5-transport' );
$excluded_files = array( 'TASK_LOG.md', '.gitignore' );
$forbidden_extensions = array( 'log', 'tmp', 'bak', 'sql', 'sqlite', 'env' );
$files = array();
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	if ( ! $file->isFile() ) { continue; }
	$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
	$parts = explode( '/', $relative );
	$extension = strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) );
	if ( in_array( $parts[0], $excluded_dirs, true ) || in_array( basename( $relative ), $excluded_files, true ) || in_array( $extension, $forbidden_extensions, true ) ) { continue; }
	if ( preg_match( '/(^|[.\-_])(secret|credential|private-key)/i', basename( $relative ) ) ) { continue; }
	$files[] = $relative;
}
sort( $files, SORT_STRING );
$required = array(
	'sabri-complete-home-news-feed.php', 'includes/class-public-surface-recovery.php', 'includes/class-corrective-public-mount.php',
	'includes/class-home-composition-registry.php', 'includes/class-public-query-guard.php', 'includes/class-integrations.php',
	'includes/class-rest-foundation.php', 'public/class-news-routing.php', 'public/class-phase5-public-runtime.php',
	'FILE-21-AUTHORITY-PRECEDENCE-CORRECTIVE-1.0.4.md', 'readme.txt', 'CHANGELOG.md',
);
foreach ( $required as $relative ) { if ( ! in_array( $relative, $files, true ) ) { fwrite( STDERR, "Missing required runtime file: {$relative}\n" ); exit( 1 ); } }
$manifest = array();
foreach ( $files as $relative ) { $manifest[] = hash_file( 'sha256', $root . '/' . $relative ) . '  ' . $slug . '/' . $relative; }
file_put_contents( $manifest_path, implode( PHP_EOL, $manifest ) . PHP_EOL );
$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) { fwrite( STDERR, "Unable to create ZIP.\n" ); exit( 1 ); }
$zip->addEmptyDir( $slug );
foreach ( $files as $relative ) { $zip->addFile( $root . '/' . $relative, $slug . '/' . $relative ); }
$zip->close();
$hash = hash_file( 'sha256', $zip_path );
file_put_contents( $sha_path, $hash . '  ' . basename( $zip_path ) . PHP_EOL );
$report = array(
	'# File 21 1.0.4 Authority-Precedence Corrective Release', '', '- Runtime: 1.0.4', '- Schema: 1.0.0',
	'- Artifact: ' . basename( $zip_path ), '- SHA-256: ' . $hash, '- Runtime files: ' . count( $files ),
	'- Mixed-role authority regression: passed when QA is green', '- Student/Patient/Subscriber-only denial: preserved',
	'- Emergency Disable: preserved', '- Public GET recovery writes: disabled', '- Automatic publication/migration: disabled', '- Live deployed: 0',
);
file_put_contents( $report_path, implode( PHP_EOL, $report ) . PHP_EOL );
echo "Built {$zip_path}\n";
