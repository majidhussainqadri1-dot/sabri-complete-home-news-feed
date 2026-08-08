<?php
/**
 * Regression contract for File 21 ten-round NG30 hardening review.
 *
 * @package SabriCompleteHomeNewsFeed
 */

$root = getenv( 'FILE21_ROOT' );
$root = $root ? rtrim( $root, '/\\' ) : dirname( __DIR__ );
$failures = array();

$read = static function ( $relative ) use ( $root, &$failures ) {
	$path = $root . '/' . ltrim( $relative, '/' );
	if ( ! is_file( $path ) ) {
		$failures[] = 'Missing file: ' . $relative;
		return '';
	}
	$value = file_get_contents( $path );
	if ( false === $value ) {
		$failures[] = 'Unreadable file: ' . $relative;
		return '';
	}
	return $value;
};

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$main_js = $read( 'assets/js/next-generation.js' );
$share_js = $read( 'assets/js/next-generation-share.js' );
$a11y_js = $read( 'assets/js/next-generation-accessibility.js' );
$hardening = $read( 'includes/class-next-generation-hardening.php' );
$plugin = $read( 'includes/class-plugin.php' );

$assert( false !== strpos( $main_js, 'a[href*="/next-generation/offline-pack"]' ), 'Offline pack link is not intercepted for nonce-bearing authenticated export.' );
$assert( false !== strpos( $main_js, "headers: { 'X-WP-Nonce': config.nonce || '' }" ), 'Offline export fetch does not send the WordPress REST nonce.' );
$assert( false !== strpos( $main_js, 'initCompareMode' ) && false !== strpos( $main_js, "'/compare'" ), 'News Compare user interface is not wired to the compare contract.' );
$assert( false !== strpos( $main_js, 'selected.length < 2 || selected.length > 4' ), 'News Compare does not enforce the 2-4 item bound.' );

$assert( false !== strpos( $share_js, 'navigator.share' ), 'Shareable knowledge card does not use native sharing when available.' );
$assert( false !== strpos( $share_js, 'navigator.clipboard' ) && false !== strpos( $share_js, '/next-generation/share-card/' ), 'Shareable knowledge card lacks clipboard fallback or REST integration.' );

$assert( false !== strpos( $a11y_js, "textActions = ['quote', 'qna-question', 'qna-answer', 'expert-context']" ), 'Accessible text-action interception does not cover all prompt-based NG30 actions.' );
$assert( false !== strpos( $a11y_js, 'showModal' ) && false !== strpos( $a11y_js, 'stopImmediatePropagation' ), 'Accessible dialog/focus path is incomplete.' );
$assert( false === strpos( $a11y_js, 'window.prompt' ), 'Accessibility correction must not introduce another prompt-only path.' );

foreach ( array(
	"Phase5RateLimiter::allow( 'ng-action', 60, 60, $user_id )",
	"in_array( $action, array( 'follow-topic', 'unfollow-topic' ), true )",
	"term_exists( $slug, 'sabri_feed_topic' )",
	"'Cache-Control', 'no-store, private, max-age=0'",
	"'/next-generation/digest/dispatch'",
	"'preview_only'     => true",
	'InteractionPermissions::nonce_valid( $nonce )',
	"Phase5RateLimiter::allow( 'ng-digest-dispatch', 4, HOUR_IN_SECONDS, $user_id )",
) as $needle ) {
	$assert( false !== strpos( $hardening, $needle ), 'Hardening contract missing: ' . $needle );
}

$assert( false !== strpos( $plugin, 'NextGenerationHardening::class' ), 'Plugin coordinator does not register NextGenerationHardening.' );
$assert( false !== strpos( $hardening, "assets/js/next-generation-share.js" ), 'Share-card enhancement is not enqueued.' );
$assert( false !== strpos( $hardening, "assets/js/next-generation-accessibility.js" ), 'Accessibility enhancement is not enqueued.' );

if ( $failures ) {
	fwrite( STDERR, "File 21 ten-review hardening failures:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "File 21 ten-review hardening contract: PASS\n";
