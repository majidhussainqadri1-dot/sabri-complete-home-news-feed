<?php
/** File 21 production recovery and public-surface contract tests. */
require_once __DIR__ . '/bootstrap.php';

$root = dirname( __DIR__ );
$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) { $failures[] = $message; }
};

$required = array(
	'includes/class-public-surface-recovery.php',
	'includes/class-corrective-public-mount.php',
	'includes/class-corrective-public-settings.php',
	'includes/class-home-composition-registry.php',
	'includes/class-public-query-guard.php',
	'includes/class-integrations.php',
	'public/class-phase5-public-runtime.php',
	'assets/css/corrective-public.css',
);
foreach ( $required as $relative ) {
	$assert( is_file( $root . '/' . $relative ), 'Missing production-recovery runtime file: ' . $relative );
}

$plugin = file_get_contents( $root . '/includes/class-plugin.php' );
$assert( false !== strpos( $plugin, 'PublicSurfaceRecovery::class' ), 'PublicSurfaceRecovery is not registered.' );

$recovery = file_get_contents( $root . '/includes/class-public-surface-recovery.php' );
foreach ( array(
	'const VERSION = SABRI_HNF_VERSION',
	'NORMALIZATION_BATCH_SIZE',
	'home_surface_enabled',
	'profile_timeline_enabled',
	'replace_existing_feed_surface',
	'read_only_surface_recovered',
	'administrator_wizard_decision_preserved',
	'normalize_published_privileged_posts',
	"'post_status'            => 'publish'",
	"'has_password'           => false",
	"'news_gates_changed'      => false",
	"'publication_changed'     => false",
	"'legacy_migration_run'    => false",
	"'recovery_complete'",
	"'more_possible'",
	"'complete'",
	'safe_read_surfaces_recovery_continues',
	'administrator_recovery_continues',
	'if ( $normalization_complete )',
	'check_admin_referer',
	'wp_safe_redirect',
) as $needle ) {
	$assert( false !== strpos( $recovery, $needle ), 'Recovery safeguard missing: ' . $needle );
}
$assert( false === strpos( $recovery, "add_action( 'init', array( __CLASS__, 'maybe_recover'" ), 'Recovery still writes from ordinary init/public requests.' );
$assert( false !== strpos( $recovery, "'posts_per_page'         => self::NORMALIZATION_BATCH_SIZE" ), 'Recovery is not using its bounded batch constant.' );
$assert( false === stripos( $recovery, 'wp_publish_post' ), 'Recovery may not publish drafts.' );
$assert( false === stripos( $recovery, 'wp_delete_post' ), 'Recovery may not delete posts.' );
$assert( false === stripos( $recovery, 'DELETE FROM' ), 'Recovery contains destructive SQL.' );

$mount = file_get_contents( $root . '/includes/class-corrective-public-mount.php' );
foreach ( array(
	"add_filter( 'the_content'",
	"add_filter( 'pre_do_shortcode_tag'",
	"add_filter( 'render_block'",
	"add_action( 'loop_start'",
	'mount_on_news_page',
	'render_complete_surface',
	'render_news_surface',
	'replace_known_feed_shortcodes',
	'intercept_feed_shortcode',
	'intercept_shortcode_block',
	'effective_home_surface',
	'visibility_reason',
	'data-sabri-hnf-surface="file-21-corrective"',
	'data-sabri-hnf-surface="file-21-news"',
	'File 21 public surface is active',
	'data-sabri-hnf-mount-source',
	"is_page( 'sabri-news' )",
) as $needle ) {
	$assert( false !== strpos( $mount, $needle ), 'Observable mount contract missing: ' . $needle );
}
$assert( false === strpos( $mount, 'PublicSurfaceRecovery::maybe_recover()' ), 'Frontend rendering still invokes recovery/database writes.' );
$assert( false === strpos( $mount, "add_action( 'wp_footer'" ), 'File 21 must not use a footer-based public Feed fallback.' );

$home = file_get_contents( $root . '/includes/class-home-composition-registry.php' );
foreach ( array(
	'sabri_shell_home_main',
	'sabri_shell_news_main',
	'data-sabri-home-row-count',
	'render_empty_row',
	"'for-you'", "'most-viral'", "'founder-updates'", "'doctors-posts'", "'videos'", "'reels'", "'pdf-books'", "'clinics'", "'marketplace'",
	'Most Viral Now', 'Latest News', 'From the Founder', 'From Verified Doctors', 'Learn Sabri Classical Homeopathy', 'Worldwide Clinics',
) as $needle ) {
	$assert( false !== strpos( $home, $needle ), 'Master-plan Home/News contract missing: ' . $needle );
}
$assert( false === strpos( $home, 'if ( empty( $items ) ) { continue;' ), 'Empty providers still remove mandatory Home rows.' );

$query_guard = file_get_contents( $root . '/includes/class-public-query-guard.php' );
foreach ( array( "add_action( 'pre_get_posts'", 'PostMetadata::visibility_meta_clause()', 'PostMetadata::review_state_meta_clause()', 'FILTER_MARKER' ) as $needle ) {
	$assert( false !== strpos( $query_guard, $needle ), 'Query-time pagination guard missing: ' . $needle );
}

$phase5 = file_get_contents( $root . '/public/class-phase5-public-runtime.php' );
foreach ( array( '$breaking_rendered', 'is_main_home_or_news_context', "Phase5FeatureSettings::enabled( 'breaking_news_enabled' )", 'is_main_query', 'in_the_loop' ) as $needle ) {
	$assert( false !== strpos( $phase5, $needle ), 'Breaking News context/once guard missing: ' . $needle );
}

$integrations = file_get_contents( $root . '/includes/class-integrations.php' );
foreach ( array( 'sabri_shell_rendering_slots', 'shell_slot_status', "'status' => $status", "'Incomplete'", "'missing'" ) as $needle ) {
	$assert( false !== strpos( $integrations, $needle ), 'Truthful File 20 integration status missing: ' . $needle );
}
$assert( false === strpos( $integrations, "'status' => 'Connected'" ), 'System Check still hard-codes Connected.' );

$wizard = file_get_contents( $root . '/includes/class-corrective-activation-wizard.php' );
$assert( false !== strpos( $wizard, "'1.0.2' === SABRI_HNF_VERSION" ), 'Activation Wizard version contract is inconsistent.' );
$activator = file_get_contents( $root . '/includes/class-activator.php' );
$assert( false !== strpos( $activator, 'PublicSurfaceRecovery::maybe_recover()' ), 'Activation no longer runs bounded recovery.' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "File 21 production recovery and public-surface contracts passed.\n";
