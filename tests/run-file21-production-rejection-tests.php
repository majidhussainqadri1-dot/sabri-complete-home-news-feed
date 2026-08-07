<?php
/** Static release contracts for the 1.0.3 runtime/API production-rejection corrective line inside package 1.0.5. */
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
$assert( false !== strpos( $main, 'Version: 1.0.5' ), 'Plugin package header is not 1.0.5.' );
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
$permissions = $read( 'includes/class-composer-permissions.php' );
$publication = $read( 'admin/class-editorial-news-publication-bridge.php' );
$publication_js = $read( 'assets/js/news-publication-controls.js' );
$composer_recovery = $read( 'admin/class-news-composer-access-recovery.php' );
$public_composer = $read( 'includes/class-public-composer-surface.php' );
$public_composer_css = $read( 'assets/css/public-composer-surface.css' );
$file22_bridge = $read( 'includes/class-universal-composer-bridge.php' );
$file22_legacy = $read( 'includes/class-universal-composer-publication-adapter.php' );
$file22_adapter = $read( 'includes/class-universal-composer-workflow-adapter.php' );
$file22_store = $read( 'includes/class-universal-composer-workflow-store.php' );
$file22_maintenance = $read( 'includes/class-universal-composer-workflow-maintenance.php' );
$file22_lock_maintenance = $read( 'includes/class-universal-composer-execution-lock-maintenance.php' );
$uninstall = $read( 'uninstall.php' );
$assert( false !== strpos( $plugin, 'EditorialNewsPublicationBridge::class' ), 'Editorial News publication bridge is not registered.' );
$assert( false !== strpos( $plugin, 'NewsComposerAccessRecovery::class' ), 'News Composer access recovery is not registered.' );
$assert( false !== strpos( $plugin, 'UniversalComposerBridge::class' ), 'File 22 integration bridge is not registered.' );
$assert( false !== strpos( $plugin, 'PublicComposerSurface::class' ), 'Public Composer surface is not registered.' );
foreach ( array(
	'CanonicalIdentityAdapter::current_action_ready',
	'CanonicalIdentityAdapter::can_create_social_content',
	'CanonicalIdentityAdapter::can_publish_immediately',
	"current_user_can_any( array( 'sabri_feed_create_posts', 'manage_options' ) )",
	"current_user_can_any( array( 'sabri_feed_publish_posts', 'manage_options' ) )",
	'self::current_actor_matches',
) as $needle ) {
	$assert( false !== strpos( $permissions, $needle ), 'Subject-bound File 00 authority contract missing: ' . $needle );
}
foreach ( array( "admin_post_' . NewsroomAdmin::SAVE_ACTION", "admin_post_' . NewsroomAdmin::BULK_ACTION", 'check_admin_referer', "current_user_can( 'publish_editorial_news'", 'CanonicalIdentityAdapter::can_publish_immediately', "post_status' => 'publish'", "WORKFLOW_META_KEY, 'published'", 'NewsPublicSnapshot::capture', "editorial_news_enabled' => 1", 'NewsCache::purge_owned', 'publication_snapshot_failed' ) as $needle ) {
	$assert( false !== strpos( $publication, $needle ), 'Editorial News publication contract missing: ' . $needle );
}
$assert( false === strpos( $publication, "add_action( 'init'" ), 'Editorial News publication must not mutate from public init.' );
$assert( false !== strpos( $publication_js, "option.value = 'published'" ) && false !== strpos( $publication_js, 'document.createElement' ), 'Trusted publication controls are missing.' );
$assert( false === strpos( $publication_js, 'innerHTML' ) && false === strpos( $publication_js, 'ev' . 'al(' ), 'Publication controls must not inject arbitrary HTML or dynamic code.' );
foreach ( array( "add_action( 'admin_init'", 'Capabilities::apply_default_policy()', 'NewsCapabilities::apply_default_policy()', 'sabri_feed_create_posts', 'NewsroomAdmin::COMPOSER_PAGE', 'Create Editorial News', "current_user_can( 'manage_options'" ) as $needle ) {
	$assert( false !== strpos( $composer_recovery, $needle ), 'News/Social Composer access recovery contract missing: ' . $needle );
}
$assert( false === strpos( $composer_recovery, "add_action( 'init'" ), 'Composer access recovery must remain administration-only.' );
foreach ( array( 'create-post', "add_filter( 'sabri_shell_create_url'", "add_filter( 'the_content'", "add_action( 'sabri_shell_home_before_main'", "add_action( 'sabri_shell_news_main'", 'data-sabri-hnf-public-composer-action', 'ComposerPermissions::user_can_create', 'wp_login_url', 'REWRITE_POLICY_VERSION', 'public-composer-surface.css' ) as $needle ) {
	$assert( false !== strpos( $public_composer, $needle ), 'Public Composer surface contract missing: ' . $needle );
}
$render_pos = strpos( $public_composer, '$composer_html = Composer::render();' );
$header_pos = strpos( $public_composer, 'get_header();' );
$assert( false !== $render_pos && false !== $header_pos && $render_pos < $header_pos, 'Composer assets must be enqueued before the theme header is printed.' );
$assert( false !== strpos( $public_composer_css, '.sabri-hnf-public-composer-cta' ) && false !== strpos( $public_composer_css, '.sabri-hnf-public-composer-page' ), 'Public Composer CTA/page styles are missing.' );

foreach ( array( 'const ADAPTER_API_VERSION', 'const WORKFLOW_API_VERSION', 'SUPC_WORKFLOW_API_VERSION', 'supc_register_adapter', 'supc_adapter_matches', 'UniversalComposerWorkflowStore::register', 'UniversalComposerWorkflowMaintenance::register', 'UniversalComposerExecutionLockMaintenance::register', 'SABRI_SHELL_CREATE_CONTRACT_VERSION', 'sabri_shell_create_contract_available', 'sabri_shell_create_visible_for_current_user', 'prefer_universal_create_url', 'harmonize_create_surfaces' ) as $needle ) {
	$assert( false !== strpos( $file22_bridge, $needle ), 'File 22 bridge contract missing: ' . $needle );
}
$assert( false === strpos( $file22_bridge, "'supc_duplicate_key' ===" ), 'A duplicate adapter key must not be treated as successful File 21 registration.' );
$assert( false === strpos( $file22_bridge, 'get_class( $error )' ), 'Adapter diagnostics must not expose exception class names.' );
foreach ( array( 'extends UniversalComposerWorkflowAdapter', 'EXECUTION_LOCK_PREFIX', 'acquire_execution_lock', 'release_execution_lock', 'record_acquired', "'processing' === \$state", 'finally', 'Final submission is idempotent only after File 22 has obtained' ) as $needle ) {
	$assert( false !== strpos( $file22_legacy, $needle ), 'Concurrency-hardened adapter contract missing: ' . $needle );
}
foreach ( array( 'implements Workflow_Adapter, Diagnostic_Adapter', "SCHEMA_VERSION               = '1.0.1'", 'create_draft', 'public function validate', 'public function preview', 'public function submit', 'public function status', 'canonical_url( int $user_id', "return '1.0.3'", "return 'sabri_feed_create_posts'", 'preview_expiry_enforced', 'idempotency_recovery_ready', 'UniversalComposerWorkflowStore::attach_native_marker', 'record_is_expired', "'draft' === (string) get_post_status", 'user_can_publish_institutional_type' ) as $needle ) {
	$assert( false !== strpos( $file22_adapter, $needle ), 'Corrected File 22 workflow adapter contract missing: ' . $needle );
}
foreach ( array( 'PREVIEW_SIGNATURE', 'preview_token_is_valid', 'enforce_preview_token', 'PROCESSING_TTL', 'COMPLETED_TTL', 'RECOVERABLE_TTL', 'attach_native_marker', 'find_native_post', 'check_admin_referer', 'wp_schedule_event', 'manage_options' ) as $needle ) {
	$assert( false !== strpos( $file22_store, $needle ), 'Workflow recovery store contract missing: ' . $needle );
}
foreach ( array( "array( 'completed', 'recoverable' )", "'processing' === \$state", 'UniversalComposerWorkflowStore::delete_record', 'RECOVERABLE_TTL', 'check_admin_referer' ) as $needle ) {
	$assert( false !== strpos( $file22_maintenance, $needle ), 'One-way retention contract missing: ' . $needle );
}
foreach ( array( 'LOCK_PREFIX', 'cleanup_expired', 'expires_at', 'delete_option' ) as $needle ) {
	$assert( false !== strpos( $file22_lock_maintenance, $needle ), 'Execution-lock retention contract missing: ' . $needle );
}
$assert( false !== strpos( $uninstall, 'sabri_hnf_file22_idem_' ) && false !== strpos( $uninstall, 'sabri_hnf_file22_exec_' ), 'Destructive uninstall does not cover both workflow option prefixes.' );
$assert( false === strpos( $file22_adapter, 'wp_insert_post' ), 'Adapter must use File 21 Composer rather than duplicate native writes.' );
$assert( false === strpos( $file22_adapter, "\n\t\t'clinical-case'," ), 'Structured Clinical Case must remain native-only.' );
$assert( false === strpos( $file22_adapter, "\n\t\t'research'," ), 'Structured Research must remain native-only.' );
$assert( false === strpos( $file22_adapter, "\n\t\t'poll'," ), 'Poll must remain native-only.' );

$workflow_test = $root . '/tests/run-file21-file22-workflow-adapter-tests.php';
$assert( is_file( $workflow_test ), 'Corrected File 21/File 22 runtime test is missing.' );
if ( is_file( $workflow_test ) ) {
	$workflow_runner = static function ( $test_file ) { require $test_file; };
	$workflow_runner( $workflow_test );
}
$authority_test = $root . '/tests/run-composer-authority-precedence-tests.php';
$assert( is_file( $authority_test ), 'Authority-precedence behavior test is missing.' );
// This behavior suite owns global test symbols and is intentionally executed
// as a separate process by the exact-head workflow. Requiring it here would
// create a false collision rather than test production behavior.
foreach ( array( 'tests/run-file21-file22-real-contract-tests.php', 'tests/run-file21-file22-maintenance-tests.php', 'tests/run-file21-file22-lock-maintenance-tests.php' ) as $test_file ) {
	$assert( is_file( $root . '/' . $test_file ), 'Missing File 22 corrective test: ' . $test_file );
}

$readme = $read( 'readme.txt' );
$change = $read( 'CHANGELOG.md' );
$assert( false !== strpos( $readme, 'Stable tag: 1.0.5' ), 'Stable tag is not 1.0.5.' );
$assert( false !== strpos( $change, '## 1.0.5' ), 'Changelog lacks package 1.0.5.' );
$assert( false !== strpos( $change, '## 1.0.3' ), 'Changelog lacks stable runtime history 1.0.3.' );
$assert( false !== strpos( $change, 'Restored secure public visibility' ), 'Changelog lacks Editorial News repair.' );
$assert( false !== strpos( $change, 'Universal Post Composer' ), 'Changelog lacks File 22 interoperability.' );
$assert( false !== strpos( $change, 'HMAC-signed' ) && false !== strpos( $change, '30-day completed retention' ), 'Changelog lacks current workflow corrections.' );

if ( $failures ) { fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL ); exit( 1 ); }
echo "File 21 production-rejection corrective contracts passed.\n";
