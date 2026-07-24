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
$queue = file_get_contents( $root . '/includes/class-news-queue-service.php' );
$service = file_get_contents( $root . '/includes/class-news-service.php' );

$assert( false !== strpos( $admin, "add_action( 'admin_enqueue_scripts'" ), 'Newsroom assets are not conditionally registered.' );
$assert( false !== strpos( $admin, 'wp_enqueue_media();' ), 'WordPress Media Library is not loaded for the composer.' );
$assert( false !== strpos( $admin, 'Select from Media Library' ), 'Composer does not expose the Media Library selector.' );
$assert( false !== strpos( $admin, 'store_input( $input )' ), 'Failed submissions do not preserve entered composer values.' );
$assert( false !== strpos( $admin, 'consume_input()' ), 'Preserved composer values are not restored.' );
$assert( false !== strpos( $admin, 'render_notice();' ), 'Composer validation results are not rendered.' );
$assert( false !== strpos( $admin, 'NewsPolicy::can_preview' ), 'Capability-safe private preview is missing.' );
$assert( false !== strpos( $admin, 'Private editorial preview' ), 'Private preview rendering is missing.' );
$assert( false !== strpos( $admin, 'site_timezone_label()' ), 'Effective site timezone is not displayed.' );
$assert( false !== strpos( $admin, 'BULK_ACTION' ), 'Accessible bulk workflow action is missing.' );
$assert( false !== strpos( $admin, 'confirm_bulk' ), 'Bulk workflow confirmation is missing.' );
$assert( 1 === substr_count( $admin, 'self::render_bulk_controls();' ), 'Bulk controls must render exactly once to avoid duplicate IDs and ambiguous form values.' );
$assert( false !== strpos( $admin, 'transition_confirmed' ), 'Server-enforced composer transition confirmation is missing.' );
$assert( false !== strpos( $admin, 'workflow_options( $current_state )' ), 'Composer workflow choices are not capability filtered.' );
$assert( false !== strpos( $queue, "'assignment_meta' => '_sabri_news_reviewing_editor_id'" ), 'Fact-check assignments are not isolated.' );
$assert( false !== strpos( $queue, "'assignment_meta' => '_sabri_news_medical_reviewer_id'" ), 'Medical-review assignments are not isolated.' );
$assert( false !== strpos( $service, 'delete_post_thumbnail' ), 'Explicit featured-image removal is not implemented.' );
$assert( false !== strpos( $script, 'window.wp.media' ), 'Media Library JavaScript integration is missing.' );
$assert( false !== strpos( $script, 'toISOString()' ), 'Client-side UTC normalization preview is missing.' );
$assert( false !== strpos( $script, "confirmation.name = 'transition_confirmed'" ), 'Confirmed workflow changes are not submitted to the server.' );
$assert( false !== strpos( $style, 'prefers-reduced-motion' ), 'Reduced-motion styling is missing.' );
$assert( false !== strpos( $style, 'outline-offset' ), 'Visible keyboard-focus styling is missing.' );

if ( $failures ) {
	fwrite( STDERR, "Phase 4B UI completeness failures:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "OK - Phase 4B newsroom UI, privacy, confirmation, and accessibility contracts passed.\n";
