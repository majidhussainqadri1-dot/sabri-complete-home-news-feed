<?php
/**
 * V12.3: execute the real File 21 NextGenerationFeed::feature_manifest().
 * This verifies manifest identity/ownership only, NOT each user flow.
 */
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );
require dirname( __DIR__ ) . '/includes/class-next-generation-feed.php';

$manifest = \Sabri\HomeNewsFeed\NextGenerationFeed::feature_manifest();
$errors = array();
$assert = static function ( bool $ok, string $why ) use ( &$errors ): void {
    if ( ! $ok ) { $errors[] = $why; }
};
$assert( is_array( $manifest ) && count( $manifest ) === 30, 'Exactly 30 runtime feature entries are required' );
$expectedIds = array();
for ( $id = 1; $id <= 30; $id++ ) {
    $expectedIds[] = sprintf( 'F21-NG-%02d', $id );
}
$assert( array_keys( $manifest ) === $expectedIds, 'Manifest keys must be ordered exact IDs F21-NG-01..30, no omissions/aliases' );
$externalOwners = array(
    'F21-NG-12' => 'file-16',
    'F21-NG-13' => 'file-16',
    'F21-NG-14' => 'file-16',
    'F21-NG-25' => 'file-26',
    'F21-NG-26' => 'file-26',
    'F21-NG-28' => 'file-25',
    'F21-NG-30' => 'file-19',
);
$seenSlugs = array();
foreach ( $manifest as $id => $data ) {
    $assert( is_array( $data ), $id . ': entry must be an array' );
    if ( ! is_array( $data ) ) { continue; }
    $slug = isset( $data['slug'] ) ? (string) $data['slug'] : '';
    $label = isset( $data['label'] ) ? (string) $data['label'] : '';
    $owner = isset( $data['owner'] ) ? (string) $data['owner'] : '';
    $assert( (bool) preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug ), $id . ': invalid slug' );
    $assert( $label !== '', $id . ': missing label' );
    $assert( ! isset( $seenSlugs[$slug] ), $id . ': duplicate slug ' . $slug );
    $seenSlugs[$slug] = true;
    $expectedOwner = $externalOwners[$id] ?? 'file-21';
    $assert( $owner === $expectedOwner, $id . ': incorrect exact canonical owner ' . $owner );
    if ( $expectedOwner !== 'file-21' ) {
        $assert( ! empty( $data['file21_role'] ), $id . ': cross-owner File21 presentation role must be explicit' );
    }
}
if ( $errors ) {
    fwrite( STDERR, "V12.3 runtime NG30 manifest FAILED:\n- " . implode( "\n- ", $errors ) . "\n" );
    exit( 1 );
}
echo "V12.3 runtime File21 NG30: 30 unique IDs/slugs, labels, owners and cross-owner roles: PASS (manifest only)\n";
