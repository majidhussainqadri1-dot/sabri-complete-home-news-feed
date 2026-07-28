<?php
/** Static release contracts for the 1.0.3 production-rejection corrective line. */
$root = getenv( 'FILE21_ROOT' );
$root = $root ? rtrim( $root, '/\\' ) : dirname( __DIR__, 2 ) . '/file21fix';
$failures = array();
$read = static function ( $relative ) use ( $root, &$failures ) {
	$path = $root . '/' . $relative;
	if ( ! is_file( $path ) ) { $failures[] = 'Missing: ' . $relative; return ''; }
	return (string) file_get_contents( $path );
};
$assert = static function ( $condition, $message ) use ( &$failures ) { if ( ! $condition ) { $failures[] = $message; } };

$main = $read( 'sabri-complete-home-news-feed.php' );
$assert( false !== strpos( $main, 'Version: 1.0.3' ), 'Plugin header is not 1.0.3.' );
$assert( false !== strpos( $main, "SABRI_HNF_VERSION', '1.0.3" ), 'Runtime constant is not 1.0.3.' );
$assert( false !== strpos( $main, "SABRI_HNF_SCHEMA_VERSION', '1.0.0" ), 'Schema must remain 1.0.0.' );
$assert( false !== strpos( $main, 'sabri_hnf_duplicate_resolved=1' ), 'Duplicate-copy controlled reload is missing.' );
$assert( false !== strpos( $main, 'register_safe_boot_routes' ), 'Safe Boot REST registration is missing.' );

$recovery = $read( 'includes/class-public-surface-recovery.php' );
$assert( false === strpos( $recovery, "add_action( 'init', array( __CLASS__, 'maybe_recover'" ), 'Recovery still writes from init.' );
$assert( false !== strpos( $recovery, 'explicit_admin_action_required' ), 'Read-only recovery diagnostic is missing.' );
$assert( false !== strpos( $recovery, 'check_admin_referer' ), 'Explicit recovery nonce is missing.' );

$mount = $read( 'includes/class-corrective-public-mount.php' );
$assert( false === strpos( $mount, 'PublicSurfaceRecovery::maybe_recover()' ), 'Public rendering still invokes recovery writes.' );
foreach ( array( 'render_news_surface', 'is_public_news_context', '/sabri-news/', '/blog/', 'file-21-news' ) as $needle ) {
	$assert( false !== strpos( $mount, $needle ), 'News compatibility contract missing: ' . $needle );
}

$composition = $read( 'includes/class-home-composition-registry.php' );
$assert( false !== strpos( $composition, "add_action( 'sabri_shell_news_main'" ), 'File 21 does not register the News slot.' );
$assert( false !== strpos( $composition, 'data-sabri-home-row-count="10"' ), 'Ten-row evidence marker is missing.' );
$assert( false !== strpos( $composition, 'Content is not available from this module yet.' ), 'Unavailable row state is missing.' );
$assert( 10 === substr_count( $composition, "=> array( 'label' => __(" ) - 14, 'Home row definition count is not exactly ten.' );

$breaking = $read( 'public/class-phase5-public-runtime.php' );
foreach ( array( 'private static $breaking_rendered', 'public_home_or_news_context', 'is_main_query', 'reset_runtime_guards' ) as $needle ) {
	$assert( false !== strpos( $breaking, $needle ), 'Breaking News guard missing: ' . $needle );
}

$query = $read( 'includes/class-public-query-guard.php' );
$assert( false !== strpos( $query, "add_action( 'pre_get_posts'" ), 'Pre-query eligibility is not registered.' );
$assert( false !== strpos( $query, "'relation' => 'AND'" ), 'Pre-query meta clauses are not AND-bounded.' );
$assert( false !== strpos( $query, "add_filter( 'the_posts'" ), 'Object-level visibility defense is missing.' );
$assert( false !== strpos( $query, 'if ( ! empty( $existing ) )' ), 'Empty existing meta queries are not excluded from nested groups.' );

$integrations = $read( 'includes/class-integrations.php' );
$integrations_view = $read( 'admin/views/integrations.php' );
$assert( false !== strpos( $integrations, 'shell_slot_audit' ), 'Shell native-slot audit is missing.' );
$assert( false !== strpos( $integrations, 'Compatibility fallback' ), 'Truthful fallback status is missing.' );
$assert( false === strpos( $integrations, "'status' => 'Connected',\n\t\t\t'detail' => __( 'File 21 is the canonical" ), 'System Check still hardcodes Connected.' );
$assert( false !== strpos( $integrations, 'public static function proposed_future_integrations()' ), 'Integrations roadmap method required by the administration view is missing.' );
$assert( false !== strpos( $integrations_view, "is_callable( array( Integrations::class, 'proposed_future_integrations' ) )" ), 'Integrations administration view does not guard mixed-version method availability.' );
$assert( false !== strpos( $integrations_view, '$future_integrations' ), 'Integrations administration view does not use the guarded roadmap projection.' );

$routing = $read( 'public/class-news-routing.php' );
$assert( false !== strpos( $routing, 'redirect_legacy_pages' ), 'Legacy News redirect is missing.' );
$assert( false !== strpos( $routing, "home_url('/news/')" ), 'Canonical /news/ target is missing.' );

$rest = $read( 'includes/class-rest-foundation.php' );
foreach ( array( 'register_safe_boot_routes', 'safe_boot_status', 'safe_boot_schema' ) as $needle ) {
	$assert( false !== strpos( $rest, $needle ), 'Safe Boot REST contract missing: ' . $needle );
}

$plugin = $read( 'includes/class-plugin.php' );
$publication = $read( 'admin/class-editorial-news-publication-bridge.php' );
$publication_js = $read( 'assets/js/news-publication-controls.js' );
$composer_recovery = $read( 'admin/class-news-composer-access-recovery.php' );
$assert( false !== strpos( $plugin, 'EditorialNewsPublicationBridge::class' ), 'Editorial News publication bridge is not registered.' );
$assert( false !== strpos( $plugin, 'NewsComposerAccessRecovery::class' ), 'News Composer access recovery is not registered.' );
foreach ( array( "admin_post_' . NewsroomAdmin::SAVE_ACTION", "admin_post_' . NewsroomAdmin::BULK_ACTION", 'check_admin_referer', "current_user_can( 'publish_editorial_news'", 'CanonicalIdentityAdapter::can_publish_immediately', "post_status' => 'publish'", "WORKFLOW_META_KEY, 'published'", 'NewsPublicSnapshot::capture', "editorial_news_enabled' => 1", 'NewsCache::purge_owned', 'publication_snapshot_failed' ) as $needle ) {
	$assert( false !== strpos( $publication, $needle ), 'Editorial News publication contract missing: ' . $needle );
}
$assert( false === strpos( $publication, "add_action( 'init'" ), 'Editorial News publication must not mutate from public init.' );
$assert( false !== strpos( $publication_js, "option.value = 'published'" ) && false !== strpos( $publication_js, 'document.createElement' ), 'Trusted publication controls are missing.' );
$assert( false === strpos( $publication_js, 'innerHTML' ) && false === strpos( $publication_js, 'ev' . 'al(' ), 'Publication controls must not inject arbitrary HTML or dynamic code.' );
foreach ( array( "add_action( 'admin_init'", 'NewsCapabilities::apply_default_policy()', 'NewsroomAdmin::COMPOSER_PAGE', 'Create Editorial News', "current_user_can( 'manage_options'" ) as $needle ) {
	$assert( false !== strpos( $composer_recovery, $needle ), 'News Composer access recovery contract missing: ' . $needle );
}
$assert( false === strpos( $composer_recovery, "add_action( 'init'" ), 'News Composer access recovery must remain administration-only.' );

$readme = $read( 'readme.txt' );
$change = $read( 'CHANGELOG.md' );
$assert( false !== strpos( $readme, 'Stable tag: 1.0.3' ), 'Stable tag is not 1.0.3.' );
$assert( false !== strpos( $change, '## 1.0.3' ), 'Changelog lacks 1.0.3.' );
$assert( false !== strpos( $change, 'Restored secure public visibility' ), 'Changelog lacks the Editorial News visibility repair.' );
$assert( false !== strpos( $change, 'Integrations diagnostics Safe Boot fatal' ), 'Changelog lacks the Integrations Safe Boot correction.' );
$assert( false !== strpos( $change, 'News Composer posting option' ), 'Changelog lacks the News Composer access correction.' );

if ( $failures ) { fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL ); exit( 1 ); }
echo "File 21 production-rejection corrective contracts passed.\n";
