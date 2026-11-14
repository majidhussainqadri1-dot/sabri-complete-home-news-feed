<?php
/** V12.3 File21 direct-identifier traceability contract — source only. */
declare(strict_types=1);

$root = dirname( __DIR__ );
$csv = $root . '/docs/FILE21-V12.3-31-REQUIREMENTS-TRACEABILITY.csv';
$manifest = $root . '/includes/class-next-generation-feed.php';
$checks = array();
$errors = array();
$fail = static function ( $message ) use ( &$errors ) { $errors[] = $message; };

if ( ! is_file( $csv ) || ! is_readable( $csv ) ) {
    fwrite( STDERR, "V12.3 traceability CSV missing.\n" );
    exit( 1 );
}
$handle = fopen( $csv, 'rb' );
if ( ! $handle ) { fwrite( STDERR, "V12.3 traceability CSV unreadable.\n" ); exit( 1 ); }
$header = fgetcsv( $handle, 0, ',', '"', '\\' );
$expected_header = array(
    'requirement_id', 'feature', 'canonical_owner_or_boundary',
    'file21_source_path', 'repository_test_path', 'verified_source_presence',
    'production_acceptance', 'live_implementation'
);
if ( $header !== $expected_header ) { $fail( 'CSV header mismatch' ); }
$seen = array();
$lines = 1;
while ( ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) !== false ) {
    ++$lines;
    if ( count( $row ) !== count( $expected_header ) ) { $fail( 'Invalid field count at row ' . $lines ); continue; }
    $item = array_combine( $expected_header, $row );
    if ( ! $item ) { $fail( 'Invalid row ' . $lines ); continue; }
    $id = (string) $item['requirement_id'];
    if ( isset( $seen[ $id ] ) ) { $fail( 'Duplicate ID: ' . $id ); }
    $seen[ $id ] = true;
    foreach ( array( 'file21_source_path', 'repository_test_path' ) as $path_key ) {
        $path = (string) $item[ $path_key ];
        if ( ! preg_match( '@^(includes|tests)/[a-zA-Z0-9/_\.\-]+\.php$@D', $path ) || ! is_file( $root . '/' . $path ) ) {
            $fail( $id . ' lacks existing ' . $path_key . ': ' . $path );
        }
    }
    if ( 'EXISTS_IN_SOURCE' !== $item['verified_source_presence'] ) {
        $fail( $id . ' has unsupported source-presence marker' );
    }
    if ( 'UNVERIFIED_CURRENT_EXACT_HEAD' !== $item['production_acceptance'] || 'UNVERIFIED' !== $item['live_implementation'] ) {
        $fail( $id . ' falsely claims production/Live acceptance' );
    }
}
fclose( $handle );
$expected = array( 'F21-CEN-01' );
for ( $n = 1; $n <= 30; ++$n ) { $expected[] = 'F21-NG-' . str_pad( (string) $n, 2, '0', STR_PAD_LEFT ); }
sort( $expected );
$actual = array_keys( $seen );
sort( $actual );
if ( $expected !== $actual ) { $fail( 'Exactly 31 governing identifiers are required; missing/extra IDs detected.' ); }
if ( ! is_file( $manifest ) ) { $fail( 'NG30 source manifest missing' ); }
else {
    $source = file_get_contents( $manifest );
    foreach ( range( 1, 30 ) as $n ) {
        $needle = "'F21-NG-" . str_pad( (string) $n, 2, '0', STR_PAD_LEFT ) . "'";
        if ( 1 !== substr_count( (string) $source, $needle ) ) { $fail( $needle . ' must appear exactly once in source manifest' ); }
    }
}
if ( $errors ) {
    fwrite( STDERR, "V12.3 File21 traceability FAILED:\n- " . implode( "\n- ", $errors ) . "\n" );
    exit( 1 );
}
echo "V12.3 File21: exactly 31 source-level entries and paths, no false Live claims; PASS\n";
