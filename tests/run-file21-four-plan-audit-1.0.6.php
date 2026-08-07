<?php
/** File 21 1.0.6 four-plan current-wave completion audit. */

$root = getenv( 'FILE21_ROOT' );
$root = $root ? rtrim( $root, '/\\' ) : dirname( __DIR__ );
$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};
$read = static function ( $relative ) use ( $root, $assert ) {
	$path = $root . '/' . $relative;
	$assert( is_file( $path ), 'Missing four-plan audit file: ' . $relative );
	return is_file( $path ) ? file_get_contents( $path ) : '';
};

foreach ( array(
	'includes/class-feed-user-agency.php',
	'includes/class-network-relationship-bridge.php',
	'includes/class-saved-collection-service.php',
	'includes/class-comment-experience.php',
	'docs/FILE-21-FOUR-PLAN-AUDIT-AND-CORRECTION-2026-08-07.md',
) as $relative ) {
	$assert( is_file( $root . '/' . $relative ), 'Four-plan deliverable missing: ' . $relative );
}

$bootstrap = $read( 'sabri-complete-home-news-feed.php' );
foreach ( array(
	'* Version: 1.0.6',
	"define( 'SABRI_HNF_PACKAGE_VERSION', '1.0.6' );",
	"define( 'SABRI_HNF_VERSION', '1.0.3' );",
	"define( 'SABRI_HNF_SCHEMA_VERSION', '1.0.0' );",
) as $needle ) {
	$assert( false !== strpos( $bootstrap, $needle ), 'Release identity mismatch: ' . $needle );
}

$home = $read( 'includes/class-home-composition-registry.php' );
$assert( false !== strpos( $home, 'data-sabri-home-control-count="14"' ), 'Canonical Home control bar is not frozen at exactly 14 controls.' );
foreach ( array( 'For You', 'Most Viral', 'Latest', 'Founder Posts', 'Doctors Posts', 'Classical Learning', 'Remedies', 'Diseases', 'Clinical Cases', 'Videos', 'Reels', 'PDF Books', 'Clinics', 'Marketplace' ) as $label ) {
	$assert( false !== strpos( $home, $label ), 'Canonical Home control missing: ' . $label );
}
$assert( false === strpos( $home, "array( 'following', 'Following'" ), 'Following must remain an auxiliary user-choice mode, not a fifteenth canonical Home control.' );

$agency = $read( 'includes/class-feed-user-agency.php' );
foreach ( array( 'following', 'Why am I seeing this?', 'Not interested', 'Snooze author for 7 days', 'Snooze this topic for 7 days', 'Use less personalization', 'Reset Feed preferences', 'reduced_personalization' ) as $needle ) {
	$assert( false !== strpos( $agency, $needle ), 'Feed user-agency capability missing: ' . $needle );
}
$assert( false !== strpos( $agency, 'NetworkRelationshipBridge::following_user_ids' ), 'Following Feed is not consuming the canonical relationship bridge.' );

$network = $read( 'includes/class-network-relationship-bridge.php' );
foreach ( array( 'SN_Relationships', "'state'", "'lists'", "'follow'", "'unfollow'", 'call_user_func', 'posts_results', 'author_allowed' ) as $needle ) {
	$assert( false !== strpos( $network, $needle ), 'File 17 relationship bridge contract missing: ' . $needle );
}
foreach ( array( 'SN_DB', 'wp_sn_', 'sn_follows', 'sn_blocks', '$wpdb' ) as $forbidden ) {
	$assert( false === strpos( $network, $forbidden ), 'File 21 must not query or write File 17 storage directly: ' . $forbidden );
}

$follow = $read( 'includes/class-follow-service.php' );
$assert( false !== strpos( $follow, 'NetworkRelationshipBridge::native_available()' ), 'File 21 follow facade does not prefer File 17 native ownership.' );
$assert( false !== strpos( $follow, 'legacy_fallback' ), 'Historical File 21 follow store is not explicitly isolated as compatibility fallback.' );

$query = $read( 'includes/class-feed-query.php' );
$assert( false !== strpos( $query, 'Authenticated Feed output is intentionally no-store' ), 'Authenticated Feed cache privacy boundary is undocumented or missing.' );
$assert( false !== strpos( $query, 'if ( (int) $user_id > 0 ) { return 0; }' ), 'Authenticated Feed output can still enter a transient cache.' );

$ranking = $read( 'includes/class-feed-ranking.php' );
foreach ( array( 'no Founder favoritism', 'donation', 'paid promotion', 'File 26 remains the canonical owner' ) as $needle ) {
	$assert( false !== strpos( $ranking, $needle ), 'Ranking constitution missing: ' . $needle );
}
$assert( false === strpos( $ranking, 'is_founder( $author_id )' ), 'Founder identity still creates an organic rank bonus.' );

$harmonized = $read( 'includes/class-harmonized-settings.php' );
$assert( false !== strpos( $harmonized, "admin_accent_hex'] = '#1f7a55'" ), 'Current green brand is not enforced in effective settings.' );
$assert( false !== strpos( $harmonized, "founder_priority'] = 0" ), 'Founder priority is not neutralized in effective settings.' );

$home_css = strtolower( $read( 'assets/css/home-composition.css' ) );
$assert( false === strpos( $home_css, '#ff8a1f' ) && false === strpos( $home_css, '#f26100' ), 'Superseded orange brand token remains in Home composition CSS.' );
$assert( false !== strpos( $home_css, '#1f7a55' ), 'Current green Home accent is missing.' );

$rest_feed = $read( 'includes/class-rest-feed.php' );
foreach ( array( '/feed/preferences', 'private_write_permission', 'InteractionPermissions::nonce_valid', 'self::request_nonce( $request )' ) as $needle ) {
	$assert( false !== strpos( $rest_feed, $needle ), 'Feed preference security contract missing: ' . $needle );
}

$collections = $read( 'includes/class-saved-collection-service.php' );
foreach ( array( 'collection_key', 'note', 'tags', 'saved_collections_export', 'update_user_meta', 'PostMetadata::user_can_view' ) as $needle ) {
	$assert( false !== strpos( $collections, $needle ), 'Saved collection capability missing: ' . $needle );
}
$rest_interactions = $read( 'includes/class-rest-interactions.php' );
foreach ( array( '/save-collection', '/me/save-collections', '/me/saves/export', 'Cache-Control', 'no-store, private' ) as $needle ) {
	$assert( false !== strpos( $rest_interactions, $needle ), 'Saved collection REST contract missing: ' . $needle );
}

$comments = $read( 'includes/class-comment-experience.php' );
foreach ( array( "'oldest'", "'newest'", 'mention_tokens', 'quote_context', 'already-visible thread items' ) as $needle ) {
	$assert( false !== strpos( $comments, $needle ), 'Comment experience capability missing: ' . $needle );
}
$comment_template = $read( 'templates/comment-item.php' );
$assert( false !== strpos( $comment_template, '<blockquote' ), 'Visible reply context is not rendered.' );
$assert( false !== strpos( $comment_template, '<details class="sabri-hnf-comment__children-toggle"' ), 'Nested reply collapse control is missing.' );
$comment_thread = $read( 'templates/comment-thread.php' );
$assert( false !== strpos( $comment_thread, 'sabri_comment_sort' ), 'No-JS comment sorting control is missing.' );

$feed_css = $read( 'assets/css/feed.css' );
$comments_css = $read( 'assets/css/comments.css' );
$assert( substr_count( $feed_css, 'min-height: 44px' ) >= 3, 'Feed controls do not demonstrate the 44px minimum touch-target contract.' );
$assert( substr_count( $comments_css, 'min-height: 44px' ) >= 3, 'Comment controls do not demonstrate the 44px minimum touch-target contract.' );

$feed_template = $read( 'templates/feed.php' );
$assert( false !== strpos( $feed_template, 'natural stopping point' ), 'Healthy-use natural session boundary is missing.' );

$search = $read( 'includes/class-search-provider-registry.php' );
foreach ( array( 'FILE26_CONNECTOR_SLUG', "'status' => 'proposed'", "'global_search_owner' => '26'" ) as $needle ) {
	$assert( false !== strpos( $search, $needle ), 'File 26 canonical search/ranking boundary missing: ' . $needle );
}
$assert( false === strpos( $search, "'status' => 'active'" ), 'File 21 must not self-activate its File 26 connector.' );

$file23 = $read( 'includes/class-file23-publishing-dashboard-bridge.php' );
$file23_runtime = $read( 'includes/class-file23-publishing-dashboard-adapter-runtime.php' );
$assert( false !== strpos( $file23, 'spdb/register_adapters' ), 'File 23 provider registration contract regressed.' );
$assert( false !== strpos( $file23_runtime, 'public function get_operation_definitions(): array' ) && false !== strpos( $file23_runtime, 'return array();' ), 'File 23 direct File 21 writes are no longer explicitly fail-closed.' );
$assert( false !== strpos( $file23_runtime, 'file21_spdb_write_not_accepted' ), 'File 23 write attempts must fail closed with the accepted File 21 error contract.' );

$build = $read( 'tools/build-release.py' );
foreach ( array( 'PACKAGE_VERSION = "1.0.6"', 'Hostinger staging accepted: NO', 'Live deployed: NO', 'Repost/Quote future NEXT/P1 feature falsely claimed current: NO' ) as $needle ) {
	$assert( false !== strpos( $build, $needle ), 'Release-truth/build contract missing: ' . $needle );
}

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "File 21 1.0.6 four-plan current-wave audit contracts passed.\n";
