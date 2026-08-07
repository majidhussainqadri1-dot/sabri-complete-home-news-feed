<?php
/** File 21 forty-round sequential review closure contract. */
$root = getenv( 'FILE21_ROOT' );
$root = $root ? rtrim( $root, '/\\' ) : dirname( __DIR__ );
$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) { $failures[] = $message; }
};
$read = static function ( $relative ) use ( $root, $assert ) {
	$path = $root . '/' . $relative;
	$assert( is_file( $path ), 'Missing forty-round evidence file: ' . $relative );
	return is_file( $path ) ? (string) file_get_contents( $path ) : '';
};

$ledger = $read( 'docs/FILE-21-FORTY-ROUND-REVIEW-LEDGER-2026-08-07.md' );
preg_match_all( '/^\|\s*(\d+)\s*\|.*?\|\s*(DEFECT|CLEAN)\s*\|/m', $ledger, $matches, PREG_SET_ORDER );
$rounds = array();
$defects = 0;
$clean = 0;
foreach ( $matches as $match ) {
	$rounds[] = (int) $match[1];
	if ( 'DEFECT' === $match[2] ) { ++$defects; } else { ++$clean; }
}
$assert( range( 1, 40 ) === $rounds, 'Review ledger must contain exactly one ordered row for every round 1..40.' );
$assert( 9 === $defects, 'Defect-bearing review count must be 9.' );
$assert( 31 === $clean, 'Clean review count must be 31.' );

$bootstrap = $read( 'sabri-complete-home-news-feed.php' );
$assert( false !== strpos( $bootstrap, '* Version: 1.0.6' ), 'Current package must be 1.0.6.' );
$assert( false !== strpos( $bootstrap, "SABRI_HNF_VERSION', '1.0.3'" ), 'Stable runtime/API must remain 1.0.3.' );
$assert( false !== strpos( $bootstrap, "SABRI_HNF_SCHEMA_VERSION', '1.0.0'" ), 'Schema must remain 1.0.0.' );

$harmonization = $read( 'tests/run-file21-harmonization-tests.php' );
$assert( false !== strpos( $harmonization, "'CompanionHomeRowAdapters::class'," ), 'Round-1 syntax correction is not preserved.' );

$network = $read( 'includes/class-network-relationship-bridge.php' );
foreach ( array( 'next_cursor', 'MAX_FOLLOWING', 'SN_Relationships', 'When File 17 is present it is authoritative' ) as $needle ) {
	$assert( false !== strpos( $network, $needle ), 'Round-4 File 17 correction missing: ' . $needle );
}
$assert( false === strpos( $network, '$wpdb' ), 'File 21 must not write/query File 17 storage.' );

$newsroom_js = $read( 'assets/js/newsroom-editor.js' );
$assert( false !== strpos( $newsroom_js, "document.createElement( 'img' )" ), 'Round-33 safe DOM image construction missing.' );
$assert( false === strpos( $newsroom_js, 'innerHTML' ), 'Round-33 dangerous Newsroom preview innerHTML sink remains.' );

$phpstan = $read( 'phpstan.neon.dist' );
$phpcs = $read( 'phpcs.xml.dist' );
foreach ( array( 'class-network-relationship-bridge.php', 'class-feed-user-agency.php', 'class-saved-collection-service.php', 'class-comment-experience.php' ) as $needle ) {
	$assert( false !== strpos( $phpstan, $needle ), 'PHPStan current-wave coverage missing: ' . $needle );
	$assert( false !== strpos( $phpcs, $needle ), 'WPCS current-wave coverage missing: ' . $needle );
}

$builder = $read( 'tools/build-release.py' );
$assert( false !== strpos( $builder, 'PACKAGE_VERSION = "1.0.6"' ), 'Builder package identity is not 1.0.6.' );
$assert( false !== strpos( $builder, 'Forty-round sequential review: 40/40 completed; defect-bearing 9; clean 31' ), 'Builder does not record forty-round closure.' );
$assert( false !== strpos( $builder, 'Hostinger staging accepted: NO' ), 'Staging truth separation missing.' );
$assert( false !== strpos( $builder, 'Live deployed: NO' ), 'Live truth separation missing.' );

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}
echo "File 21 forty-round sequential review closure: 40/40 PASS; 9 defect-bearing, 31 clean.\n";
