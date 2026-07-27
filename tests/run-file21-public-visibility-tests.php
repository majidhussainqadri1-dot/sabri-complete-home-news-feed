<?php
/** File 21 public-surface visibility recovery contract tests. */
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
	'includes/class-home-integration.php',
	'includes/class-feed-query.php',
	'includes/class-canonical-identity-adapter.php',
	'assets/css/corrective-public.css',
);
foreach ( $required as $relative ) {
	$assert( is_file( $root . '/' . $relative ), 'Missing public-visibility runtime file: ' . $relative );
}

$plugin = file_get_contents( $root . '/includes/class-plugin.php' );
$assert( false !== strpos( $plugin, 'PublicSurfaceRecovery::class' ), 'PublicSurfaceRecovery is not registered.' );

$recovery = file_get_contents( $root . '/includes/class-public-surface-recovery.php' );
foreach ( array(
	"const VERSION = '1.0.2'",
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
$assert( false !== strpos( $recovery, "'posts_per_page'         => self::NORMALIZATION_BATCH_SIZE" ), 'Recovery is not using its bounded batch constant.' );
$assert( false === strpos( $recovery, "'posts_per_page'         => 200" ), 'Recovery still hard-codes a one-shot 200-post batch.' );
$assert( false === stripos( $recovery, 'wp_publish_post' ), 'Recovery may not publish drafts.' );
$assert( false === stripos( $recovery, 'wp_delete_post' ), 'Recovery may not delete posts.' );
$assert( false === stripos( $recovery, 'DELETE FROM' ), 'Recovery contains destructive SQL.' );
$assert( false === strpos( $recovery, 'LegacyPublicationMigration::migrate_selected' ), 'Recovery may not run File 04 migration.' );

$mount = file_get_contents( $root . '/includes/class-corrective-public-mount.php' );
foreach ( array(
	"add_filter( 'the_content'",
	"add_filter( 'pre_do_shortcode_tag'",
	"add_filter( 'render_block'",
	"add_action( 'loop_start'",
	'render_complete_surface',
	'replace_known_feed_shortcodes',
	'intercept_feed_shortcode',
	'intercept_shortcode_block',
	'PublicSurfaceRecovery::maybe_recover()',
	'effective_home_surface',
	'visibility_reason',
	'data-sabri-hnf-surface="file-21-corrective"',
	'File 21 public surface is active',
	'data-sabri-hnf-mount-source',
) as $needle ) {
	$assert( false !== strpos( $mount, $needle ), 'Observable mount contract missing: ' . $needle );
}
$assert( false === strpos( $mount, "add_action( 'wp_footer'" ), 'File 21 must not use a footer-based public Feed fallback.' );
$assert( false === strpos( $mount, 'footer_last_resort' ), 'Footer last-resort mounting must remain removed.' );

$home = file_get_contents( $root . '/includes/class-home-composition-registry.php' );
foreach ( array(
	'sabri_shell_home_main',
	"'for-you'", "'most-viral'", "'founder-updates'", "'doctors-posts'", "'videos'", "'reels'", "'pdf-books'", "'clinics'", "'marketplace'",
	'Most Viral Now', 'Latest News', 'From the Founder', 'From Verified Doctors', 'Learn Sabri Classical Homeopathy', 'Worldwide Clinics',
) as $needle ) {
	$assert( false !== strpos( $home, $needle ), 'Master-plan Home control/row missing: ' . $needle );
}

$renderer = file_get_contents( $root . '/includes/class-feed-renderer.php' );
$assert( false !== strpos( $renderer, 'HomeCompositionRegistry::render_control_bar' ), 'Feed renderer is not using the exact fourteen-item Home Control Bar.' );

$query = file_get_contents( $root . '/includes/class-feed-query.php' );
foreach ( array( "'has_password' => false", 'CanonicalIdentityAdapter::founder_ids()', "'founder-updates' === $mode", 'CanonicalIdentityAdapter::verified_doctor_ids()' ) as $needle ) {
	$assert( false !== strpos( $query, $needle ), 'Public query visibility/authority contract missing: ' . $needle );
}

$identity = file_get_contents( $root . '/includes/class-canonical-identity-adapter.php' );
$assert( false !== strpos( $identity, 'public static function founder_ids' ), 'Canonical Founder ID query is missing.' );

$wizard = file_get_contents( $root . '/includes/class-corrective-activation-wizard.php' );
foreach ( array( 'public_observability', 'auto_replacement_enabled', 'read_only_surface_recovered', "'blocked' => false", "'1.0.2' === SABRI_HNF_VERSION" ) as $needle ) {
	$assert( false !== strpos( $wizard, $needle ), 'Activation Wizard visibility recovery contract missing: ' . $needle );
}

$activator = file_get_contents( $root . '/includes/class-activator.php' );
$assert( false !== strpos( $activator, 'PublicSurfaceRecovery::maybe_recover()' ), 'Activation does not run bounded public-surface recovery.' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n", $failures ) . "\n" );
	exit( 1 );
}

echo "File 21 public-surface visibility recovery contract tests passed.\n";
