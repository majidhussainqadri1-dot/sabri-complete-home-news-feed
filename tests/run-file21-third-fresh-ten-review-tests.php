<?php
/**
 * Executable evidence for the third fresh ten-round File 21 review.
 *
 * Historical round classifications remain tied to the third-review report,
 * while continuing exact-companion assertions intentionally follow the
 * currently approved immutable pins maintained by the latest companion gate.
 *
 * @package SabriCompleteHomeNewsFeed
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$files = array(
	'plugin'     => $root . '/includes/class-plugin.php',
	'hardening'  => $root . '/includes/class-third-fresh-review-hardening.php',
	'rest'       => $root . '/includes/class-rest-next-generation.php',
	'integrate'  => $root . '/includes/class-next-generation-integrations.php',
	'file22'     => $root . '/.github/workflows/file21-file22-real-contract.yml',
	'companions' => $root . '/.github/workflows/file21-latest-companion-exact-contracts.yml',
	'workflow'   => $root . '/.github/workflows/file21-third-fresh-ten-review.yml',
	'report'     => $root . '/docs/FILE21-THIRD-FRESH-TEN-ROUND-REVIEW-2026-08-08.md',
	'main'       => $root . '/sabri-complete-home-news-feed.php',
);

$passed = 0;
$failed = 0;
$assert = static function ( bool $condition, string $message ) use ( &$passed, &$failed ): void {
	if ( $condition ) {
		++$passed;
		echo "PASS: {$message}\n";
		return;
	}
	++$failed;
	fwrite( STDERR, "FAIL: {$message}\n" );
};

foreach ( $files as $label => $path ) {
	$assert( is_file( $path ), "{$label} evidence exists" );
}

$read = static function ( string $path ): string {
	return is_file( $path ) ? ( file_get_contents( $path ) ?: '' ) : '';
};

$plugin     = $read( $files['plugin'] );
$hardening  = $read( $files['hardening'] );
$rest       = $read( $files['rest'] );
$integrate  = $read( $files['integrate'] );
$file22     = $read( $files['file22'] );
$companions = $read( $files['companions'] );
$workflow   = $read( $files['workflow'] );
$report     = $read( $files['report'] );
$main       = $read( $files['main'] );

// Round 1 — governed package/runtime/schema identity remains intentionally stable.
$assert( str_contains( $main, "define( 'SABRI_HNF_PACKAGE_VERSION', '1.0.5' )" ), 'package stays 1.0.5' );
$assert( str_contains( $main, "define( 'SABRI_HNF_VERSION', '1.0.3' )" ), 'runtime/API stays 1.0.3' );
$assert( str_contains( $main, "define( 'SABRI_HNF_SCHEMA_VERSION', '1.0.0' )" ), 'schema stays 1.0.0' );

// Round 2 — current File 00 and File 02 exact-head compatibility is permanently pinned.
$assert( str_contains( $companions, 'FILE00_SHA: 3a84c32a6ddad151f2ed09d244fa8aa536a58108' ), 'current File 00 exact head is pinned' );
$assert( str_contains( $companions, 'FILE02_SHA: e352aab7e3bd32bbbe82fc26424a3623b9c71a56' ), 'current File 02 exact head is pinned' );
$assert( str_contains( $companions, 'class-smc-contracts.php' ) && str_contains( $companions, 'class-sa-membership-adapter.php' ), 'identity/auth owner boundary is executable' );

// Round 3 — safe GET digest cannot reach the existing File 19 ingestion callback.
$assert( str_contains( $rest, "'/next-generation/digest'" ) && str_contains( $rest, "'methods'             => 'GET'" ), 'digest remains a GET read surface' );
$assert( str_contains( $integrate, 'sun_ingest_domain_event' ), 'explicit File 19 ingestion remains available for owner-controlled command paths' );
$assert( str_contains( $hardening, "private const DIGEST_ROUTE = '/sabri-home-news-feed/v1/next-generation/digest'" ), 'hardening targets exact digest route' );
$assert( str_contains( $hardening, "self::DIGEST_ROUTE === \$route && 'GET' === \$method" ) && str_contains( $hardening, 'digest_preview( $request )' ), 'GET is short-circuited to read-only preview before callback' );
$assert( str_contains( $hardening, "'preview_only'       => true" ) && str_contains( $hardening, "'delivery_scheduled' => false" ), 'digest preview truthfully declares no delivery side effect' );
$assert( ! str_contains( $hardening, 'sun_ingest_domain_event' ), 'read hardening never invokes File 19 ingestion' );

// Round 4 — the continuing current File 20 pin and five-slot boundary remain executable.
$assert( str_contains( $companions, 'FILE20_SHA: 3e9c65373d88332e050628f27f0801092d417da2' ), 'current File 20 exact head is pinned' );
$assert( str_contains( $companions, 'five-exact-slots-fallback-suppressed' ), 'File 20 five-slot File 21 boundary is tested' );

// Round 5 — current File 22 replaces the stale compatibility pin.
$assert( str_contains( $file22, 'FILE22_RUNTIME_SHA: 1274e380268c2ab235c66fd21906cf4b1bcadf9a' ), 'File 22 exact pin is current' );
$assert( ! str_contains( $file22, '4d4f17ff11810d3048c7f6d5c8fd10a5ac506385' ), 'stale File 22 pin is removed' );
$assert( str_contains( $file22, "- main\n      - 'file21-**'" ), 'File 22 contract reruns on main and review branches' );

// Round 6 — the continuing current File 04 migration-only contract remains exact-pinned.
$assert( str_contains( $companions, 'FILE04_SHA: 54253e6de2dc68c2c57f7e0d4fd474bd0622de8e' ), 'current File 04 exact head is pinned' );
$assert( str_contains( $companions, 'read.only|write.*disable|migration|cutover|legacy_writes.*forbidden' ), 'legacy migration/write-disable boundary is asserted' );

// Round 7 — current File 23 native-owner/write-acceptance contract is exact-pinned.
$assert( str_contains( $companions, 'FILE23_SHA: a8a8c805f4730998ccb44bd95c87591836561759' ), 'current File 23 exact head is pinned' );
$assert( str_contains( $companions, 'Native data and native state remain authoritative' ), 'File 23 native-owner invariant is asserted' );
$assert( str_contains( $companions, 'Production writes require `production_accepted`' ), 'File 23 production write gate is asserted' );

// Round 8 — shared-meta REST mutations fail with conflict instead of silent concurrent overwrite.
$assert( str_contains( $hardening, "private const ACTION_ROUTE = '/sabri-home-news-feed/v1/next-generation/action'" ), 'hardening targets exact mutation route' );
$assert( str_contains( $hardening, "'ng30_mutation_conflict'" ) && str_contains( $hardening, "array( 'status' => 409 )" ), 'concurrent mutation produces explicit 409 conflict' );
$assert( str_contains( $hardening, 'add_option( $key, $value' ) && str_contains( $hardening, 'hash_equals' ), 'cross-request lock uses atomic option creation and owner-token release' );
$assert( str_contains( $hardening, "return 'post-' . \$post_id" ) && str_contains( $hardening, "return 'user-' . \$user_id" ), 'post and user shared-meta scopes are independently serialized' );

// Round 9 — the continuing current File 24 assurance boundary remains exact-pinned.
$assert( str_contains( $companions, 'FILE24_SHA: 0dbd461a7a78328c0d134b711ef7a538023028ea' ), 'current File 24 exact head is pinned' );
$assert( str_contains( $companions, 'native enforcement|native module|native authorization|Native modules own.*object-level authorization' ), 'File 24 assurance/native-enforcement boundary is checked' );

// Round 10 — this historical cycle remains an executable permanent release gate.
$assert( str_contains( $plugin, 'ThirdFreshReviewHardening::class' ), 'third-review runtime hardening is registered' );
$assert( str_contains( $workflow, 'run-file21-third-fresh-ten-review-tests.php' ), 'third-review executable test is wired into CI' );
$assert( str_contains( $workflow, 'python3 tools/build-release.py --source-sha' ), 'third-review CI includes deterministic package regression' );

$rounds = array();
if ( preg_match_all( '/^\|\s*(\d{1,2})\s*\|/m', $report, $matches ) ) {
	$rounds = array_values( array_unique( array_map( 'intval', $matches[1] ) ) );
}
$assert( $rounds === range( 1, 10 ), 'review report contains exactly rounds 1 through 10' );

$expected_defect_rounds = array( 2, 3, 4, 5, 6, 7, 8, 10 );
foreach ( $expected_defect_rounds as $round ) {
	$assert( (bool) preg_match( '/^\|\s*' . $round . '\s*\|.*\|\s*DEFECT\s*\|/mi', $report ), "round {$round} is truthfully marked DEFECT" );
}
foreach ( array( 1, 9 ) as $round ) {
	$assert( (bool) preg_match( '/^\|\s*' . $round . '\s*\|.*\|\s*NO DEFECT\s*\|/mi', $report ), "round {$round} is truthfully marked NO DEFECT" );
}

printf( "File 21 third fresh ten-round review: %d passed, %d failed.\n", $passed, $failed );
exit( 0 === $failed ? 0 : 1 );
