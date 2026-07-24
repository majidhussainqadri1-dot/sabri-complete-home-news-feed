<?php
/**
 * Phase 4B newsroom UI completeness contracts.
 *
 * @package SabriCompleteHomeNewsFeed
 */

$root = dirname( __DIR__ );
$failures = array();

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$admin = file_get_contents( $root . '/admin/class-newsroom-admin.php' );
$script = file_get_contents( $root . '/assets/js/newsroom-editor.js' );
$style = file_get_contents( $root . '/assets/css/newsroom-admin.css' );

$assert( false !== strpos( $admin, "add_action( 'admin_enqueue_scripts'" ), 'Newsroom assets are not conditionally registered.' );
$assert( false !== strpos( $admin, 'wp_enqueue_media();' ), 'WordPress Media Library is not loaded for the composer.' );
$assert( false !== strpos( $admin, 'Select from Media Library' ), 'Composer does not expose the Media Library selector.' );
$assert( false !== strpos( $admin, 'store_input( $input )' ), 'Failed submissions do not preserve entered composer values.' );
$assert( false !== strpos( $admin, 'consume_input()' ), 'Preserved composer values are not restored.' );
$assert( false !== strpos( $admin, 'render_notice();' ), 'Composer validation results are not rendered.' );
$assert( false !== strpos( $admin, 'Private editorial preview' ), 'Capability-safe private preview is missing.' );
$assert( false !== strpos( $admin, 'site_timezone_label()' ), 'Effective site timezone is not displayed.' );
$assert( false !== strpos( $script, 'window.wp.media' ), 'Media Library JavaScript integration is missing.' );
$assert( false !== strpos( $script, 'toISOString()' ), 'Client-side UTC normalization preview is missing.' );
$assert( false !== strpos( $style, 'prefers-reduced-motion' ), 'Reduced-motion styling is missing.' );
$assert( false !== strpos( $style, 'outline-offset' ), 'Visible keyboard-focus styling is missing.' );

if ( $failures ) {
	fwrite( STDERR, "Phase 4B UI completeness failures:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "OK - Phase 4B newsroom UI completeness contracts passed.\n";
