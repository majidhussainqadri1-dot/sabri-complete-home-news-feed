<?php
/**
 * Exact-source File 04 migration-completeness contract gate.
 *
 * This is intentionally source-local: File 04 separately pins this exact
 * companion commit and verifies the cross-repository surface.
 */
$root = getenv( 'FILE21_ROOT' ) ?: dirname( __DIR__ );
$source = file_get_contents( $root . '/includes/class-legacy-publication-migration.php' );
if ( ! is_string( $source ) ) {
	fwrite( STDERR, "File 21 legacy migration source unavailable.\n" );
	exit( 1 );
}
$fail = array();
$check = static function ( $condition, $message ) use ( &$fail ) {
	if ( ! $condition ) { $fail[] = $message; }
};

foreach ( array(
	'sabri_file21_legacy_media_preflight_v1',
	'sabri_file21_verify_migrated_legacy_media_v1',
	'sabri_file21_legacy_metadata_preflight_v1',
	'sabri_file21_verify_migrated_legacy_metadata_v1',
	'legacy_metadata_context',
	'resolve_legacy_metadata_context',
	'file04_metadata_preflight',
	'file04_verify_migrated_metadata',
	'_sabri_hnf_legacy_metadata_v1',
	'_snp_tags',
	'_snp_language',
	'_snp_featured',
	'_snp_pinned',
	'_snp_video_url',
	"PostMetadata::META_LANGUAGE",
	"PostMetadata::META_FEATURED",
	"PostMetadata::META_PINNED",
	"'relations'",
	"'featured'",
	"'child'",
) as $needle ) {
	$check( false !== strpos( $source, $needle ), 'Missing File04 migration-completeness invariant: ' . $needle );
}

$check( false !== strpos( $source, "absint( get_post_meta( $legacy_id, '_thumbnail_id', true ) ) === $attachment_id" ), 'Featured-media preflight must bind to the legacy thumbnail relation.' );
$check( false !== strpos( $source, "absint( get_post_meta( $target_id, '_thumbnail_id', true ) ) !== self::positive_id" ), 'Post-migration verification must bind the canonical featured-media relation.' );
$check( false !== strpos( $source, "array_keys( $normalized ) !== array_keys( $current )" ), 'Unsafe or partially mappable legacy metadata must fail closed.' );

if ( $fail ) {
	fwrite( STDERR, "File21/File04 migration completeness FAILED:\n- " . implode( "\n- ", $fail ) . "\n" );
	exit( 1 );
}
echo "File21/File04 migration completeness PASS.\n";
