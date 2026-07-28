<?php
/**
 * File 21 corrective completion audit.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\CorrectiveActivationWizard;
use Sabri\HomeNewsFeed\CorrectivePublicMount;
use Sabri\HomeNewsFeed\CorrectivePublicSettings;
use Sabri\HomeNewsFeed\ProfileTimeline;
use Sabri\HomeNewsFeed\RestProfileTimeline;

$failures = array();

function sabri_file21_completion_assert( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$defaults = CorrectivePublicSettings::defaults();
sabri_file21_completion_assert( 1 === $defaults['home_surface_enabled'], 'The harmless read-only Home surface must be available without requiring a frontend write.' );
sabri_file21_completion_assert( 1 === $defaults['profile_timeline_enabled'], 'The harmless read-only Profile Timeline must be available without requiring a frontend write.' );
sabri_file21_completion_assert( 1 === $defaults['duplicate_feed_guard'], 'Duplicate Feed protection must default on.' );
sabri_file21_completion_assert( 0 === $defaults['replace_existing_feed_surface'], 'Existing Feed replacement must require explicit administrator selection or bounded recovery.' );
sabri_file21_completion_assert( 1 === $defaults['duplicate_navigation_guard'], 'Duplicate navigation diagnostics must default on.' );
sabri_file21_completion_assert( 0 === $defaults['wizard_completed'], 'Read-only visibility must not falsely mark the Activation Wizard complete.' );
sabri_file21_completion_assert( 0 === $defaults['read_only_surface_recovered'], 'Default visibility must not falsely claim that a database recovery already ran.' );

$steps = CorrectiveActivationWizard::steps();
foreach ( array( 'environment', 'identity-authority', 'existing-content', 'public-components', 'duplicate-protection', 'news-gates', 'preview-activate' ) as $step ) {
	sabri_file21_completion_assert( isset( $steps[ $step ] ), 'Activation Wizard is missing required step: ' . $step );
}
$components = CorrectiveActivationWizard::component_definitions();
sabri_file21_completion_assert( isset( $components['replace_existing_feed_surface'] ), 'Activation Wizard must expose controlled existing Feed replacement.' );

$gates = CorrectiveActivationWizard::gate_definitions();
sabri_file21_completion_assert( isset( $gates['phase4']['editorial_news_enabled'] ), 'Wizard must expose the Editorial News parent gate.' );
sabri_file21_completion_assert( isset( $gates['phase4']['breaking_news_enabled'] ), 'Wizard must expose Breaking News independently.' );
sabri_file21_completion_assert( isset( $gates['phase4']['news_corrections_enabled'] ), 'Wizard must expose corrections independently.' );
sabri_file21_completion_assert( isset( $gates['phase5']['news_sitemap_enabled'] ), 'Wizard must expose the News sitemap gate.' );

$known = CorrectivePublicMount::known_feed_shortcodes();
foreach ( array( 'sabri_complete_home_feed', 'sabri_news_feed', 'sabri_news_home', 'sabri_platform_home', 'sabri_shell_home_feed' ) as $shortcode ) {
	sabri_file21_completion_assert( in_array( $shortcode, $known, true ), 'Duplicate guard is missing known Feed shortcode: ' . $shortcode );
}
sabri_file21_completion_assert( 'sabri_news_feed' === CorrectivePublicMount::content_feed_shortcode( '[sabri_news_feed]' ), 'Existing File 04 Feed shortcode must be detected.' );
sabri_file21_completion_assert( '' === CorrectivePublicMount::content_feed_shortcode( '<p>No feed here.</p>' ), 'Normal content must not be reported as a duplicate Feed.' );
$replacement = CorrectivePublicMount::replace_known_feed_shortcodes( '<div>[sabri_news_feed][sabri_platform_home]</div>', '<section data-test="file21">New Feed</section>' );
sabri_file21_completion_assert( 1 === substr_count( $replacement, 'data-test="file21"' ), 'Controlled replacement must render exactly one File 21 Feed surface.' );
sabri_file21_completion_assert( false === strpos( $replacement, '[sabri_news_feed]' ) && false === strpos( $replacement, '[sabri_platform_home]' ), 'Controlled replacement must remove duplicate known Feed shortcodes from the current request.' );

sabri_file21_completion_assert( 20 === ProfileTimeline::MAX_PER_PAGE, 'Profile Timeline must remain bounded to 20 items per request.' );
sabri_file21_completion_assert( 500 === ProfileTimeline::MAX_SCAN, 'Profile Timeline candidate scan must remain bounded.' );
sabri_file21_completion_assert( RestProfileTimeline::validate_positive_int( '1' ), 'Timeline REST must accept strict positive integers.' );
sabri_file21_completion_assert( ! RestProfileTimeline::validate_positive_int( '0' ), 'Timeline REST must reject zero.' );
sabri_file21_completion_assert( ! RestProfileTimeline::validate_per_page( '21' ), 'Timeline REST must reject requests above the maximum page size.' );

$shortcodes = file_get_contents( dirname( __DIR__ ) . '/includes/class-shortcodes.php' );
$plugin     = file_get_contents( dirname( __DIR__ ) . '/includes/class-plugin.php' );
$timeline   = file_get_contents( dirname( __DIR__ ) . '/includes/class-profile-timeline.php' );
$mount      = file_get_contents( dirname( __DIR__ ) . '/includes/class-corrective-public-mount.php' );
$view       = file_get_contents( dirname( __DIR__ ) . '/admin/views/corrective-wizard.php' );
$checklist  = file_get_contents( dirname( __DIR__ ) . '/FILE-21-LIVE-VISUAL-ACCEPTANCE-CHECKLIST.md' );

sabri_file21_completion_assert( false !== strpos( $shortcodes, 'sabri_profile_timeline' ), 'Profile Timeline shortcode must be registered.' );
sabri_file21_completion_assert( false === strpos( $shortcodes, "'user_id'  => function_exists( 'get_queried_object_id'" ), 'Profile Timeline must not treat an arbitrary queried post ID as a user ID.' );
sabri_file21_completion_assert( false !== strpos( $timeline, "'no_found_rows'       => true" ), 'Profile Timeline must not expose WordPress found_posts for restricted content.' );
sabri_file21_completion_assert( false !== strpos( $timeline, 'PostMetadata::visibility_meta_clause()' ), 'Timeline candidate query must apply the visibility meta clause.' );
sabri_file21_completion_assert( false !== strpos( $timeline, 'PostMetadata::user_can_view' ), 'Timeline serialization must retain object-level visibility authorization.' );
sabri_file21_completion_assert( false !== strpos( $timeline, "'total_is_complete'" ), 'Timeline contract must disclose whether its bounded visible count is complete.' );
sabri_file21_completion_assert( false !== strpos( $timeline, "add_filter( 'do_shortcode_tag'" ) && false !== strpos( $timeline, "'sabri_founder_profile', 'sabri_member_profile'" ), 'Timeline must integrate with the existing File 03 Founder and Member profile shortcodes.' );
sabri_file21_completion_assert( false !== strpos( $mount, 'replace_existing_feed_surface' ) && false !== strpos( $mount, 'page content is not mutated' ), 'Existing Feed replacement must be explicit and non-destructive.' );
sabri_file21_completion_assert( false !== strpos( $plugin, 'CorrectivePublicMount::class' ) && false !== strpos( $plugin, 'RestProfileTimeline::class' ), 'Corrective public and Timeline modules must be registered.' );
sabri_file21_completion_assert( false !== strpos( $plugin, 'CorrectiveAdmin::class' ), 'Activation Wizard administration must be registered.' );
sabri_file21_completion_assert( false !== strpos( $view, 'Gate-by-Gate Public News Activation' ), 'Wizard must visibly expose gate-by-gate News activation.' );
sabri_file21_completion_assert( false !== strpos( $checklist, 'exact 40-character File 21 commit SHA' ) && false !== strpos( $checklist, 'ZIP SHA-256' ), 'Visual acceptance evidence must bind screenshots to immutable File 21 source and package identity.' );

if ( ! empty( $failures ) ) {
	fwrite( STDERR, "File 21 completion audit failed:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "File 21 corrective completion audit passed.\n";
