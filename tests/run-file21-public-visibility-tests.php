<?php
/** File 21 public-surface visibility recovery contract tests. */
require_once __DIR__ . '/bootstrap.php';

$root = dirname( __DIR__ );
$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
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
	'home_surface_enabled',
	'profile_timeline_enabled',
	'replace_existing_feed_surface',
	'read_only_surface_recovered',
	'administrator_wizard_decision_preserved',
	'normalize_published_privileged_posts',
	"'post_status'            => 'publish'",
	"'has_password'           => false",
	"'news_gates_changed'   => false",
	"'publication_changed'  => false",
	"'legacy_migration_run' => false",
	'check_admin_referer',
	'wp_safe_redirect',
) as $needle ) {
	$assert( false !== strpos( $recovery, $needle ), 'Recovery safeguard missing: ' . $needle );
}
$assert( false === stripos( $recovery, 'wp_publish_post' ), 'Recovery may not publish drafts.' );
$assert( false === stripos( $recovery, 'wp_delete_post' ), 'Recovery may not delete posts.' );
$assert( false === stripos( $recovery, 'DELETE FROM' ), 'Recovery contains destructive SQL.' );
$assert( false === strpos( $recovery, 'LegacyPublicationMigration::migrate_selected' ), 'Recovery may not run File 04 migration.' );

$mount = file_get_contents( $root . '/includes/class-corrective-public-mount.php' );
foreach ( array(
	"add_filter( 'the_content'",
	"add_action( 'loop_start'",
	"add_action( 'wp_footer'",
	'render_complete_surface',
	'replace_known_feed_shortcodes',
	'effective_home_surface',
	'visibility_reason',
	'data-sabri-hnf-surface="file-21-corrective"',
	'File 21 public surface is active',
	'data-sabri-hnf-mount-source',
	'footer_last_resort',
) as $needle ) {
	$assert( false !== strpos( $mount, $needle ), 'Observable mount contract missing: ' . $needle );
}

$home = file_get_contents( $root . '/includes/class-home-composition-registry.php' );
foreach ( array(
	'sabri_shell_home_main',
	"'for-you'",
	"'most-viral'",
	"'founder-updates'",
	"'doctors-posts'",
	"'videos'",
	"'reels'",
	"'pdf-books'",
	"'clinics'",
	"'marketplace'",
	'Most Viral Now',
	'Latest News',
	'From the Founder',
	'From Verified Doctors',
	'Learn Sabri Classical Homeopathy',
	'Worldwide Clinics',
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
