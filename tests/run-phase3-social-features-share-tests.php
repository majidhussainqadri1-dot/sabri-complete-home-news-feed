<?php
/**
 * Phase 3 social feature settings and Share regression tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\Phase3FeatureSettings;

$failures = array();
function sabri_social_features_assert( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		$failures[] = $message;
	}
}

sabri_test_reset_state( true );
$defaults = Phase3FeatureSettings::defaults();
sabri_social_features_assert( isset( $defaults['share_enabled'] ) && 1 === (int) $defaults['share_enabled'], 'Share must be enabled by the safe default.' );
sabri_social_features_assert( 0 === (int) $defaults['comments_enabled'], 'Comments must remain gated by default.' );
sabri_social_features_assert( 0 === (int) $defaults['view_logging_enabled'], 'View logging must remain gated by default.' );

$catalog = Phase3FeatureSettings::catalog();
foreach ( array_keys( $defaults ) as $feature ) {
	sabri_social_features_assert( isset( $catalog[ $feature ]['label'], $catalog[ $feature ]['description'] ), 'Administrator catalogue is missing ' . $feature . '.' );
}

$updated = Phase3FeatureSettings::update_from_admin(
	array(
		'share_enabled'               => 1,
		'comments_enabled'            => 1,
		'view_logging_enabled'        => 1,
		'dislikes_enabled'            => 1,
		'show_public_reaction_counts' => 1,
		'show_public_follower_counts' => 1,
		'followers_visibility_enabled'=> 1,
	)
);
sabri_social_features_assert( 1 === (int) $updated['share_enabled'], 'Share administrator setting was not saved.' );
sabri_social_features_assert( 1 === (int) $updated['comments_enabled'], 'Comments administrator setting was not saved.' );
sabri_social_features_assert( 1 === (int) $updated['view_logging_enabled'], 'View logging administrator setting was not saved.' );
sabri_social_features_assert( 0 === (int) $updated['dislikes_enabled'], 'Dislikes must fail closed when reactions are disabled.' );
sabri_social_features_assert( 0 === (int) $updated['show_public_reaction_counts'], 'Reaction counts must fail closed when reactions are disabled.' );
sabri_social_features_assert( 0 === (int) $updated['show_public_follower_counts'], 'Follower counts must fail closed when follows are disabled.' );
sabri_social_features_assert( 0 === (int) $updated['followers_visibility_enabled'], 'Followers-only visibility must fail closed when follows are disabled.' );
sabri_social_features_assert( $updated === get_option( Phase3FeatureSettings::OPTION_NAME ), 'Complete administrator checkbox state was not persisted.' );

$disabled = Phase3FeatureSettings::update_from_admin( array() );
foreach ( $disabled as $feature => $state ) {
	sabri_social_features_assert( 0 === (int) $state, 'Unchecked administrator checkbox did not disable ' . $feature . '.' );
}

$root       = dirname( __DIR__ );
$action_bar = file_get_contents( $root . '/templates/action-bar.php' );
$share_js   = file_get_contents( $root . '/assets/js/share.js' );
$admin_view = file_get_contents( $root . '/admin/views/social-features.php' );
$admin      = file_get_contents( $root . '/admin/class-admin.php' );
$overview   = file_get_contents( $root . '/admin/views/overview.php' );
$assets     = file_get_contents( $root . '/includes/class-assets.php' );

sabri_social_features_assert( false !== strpos( $action_bar, 'data-sabri-share' ), 'Action bar is missing the independent Share control.' );
sabri_social_features_assert( false !== strpos( $action_bar, "esc_html_e( 'Share'" ), 'Action bar is missing the visible Share label.' );
sabri_social_features_assert( false !== strpos( $action_bar, 'data-share-url' ) && false !== strpos( $action_bar, 'data-share-title' ), 'Action bar is missing safe Share payload attributes.' );
sabri_social_features_assert( false !== strpos( $share_js, 'navigator.share' ), 'Share runtime is missing the Web Share API.' );
sabri_social_features_assert( false !== strpos( $share_js, 'navigator.clipboard.writeText' ), 'Share runtime is missing the Clipboard API fallback.' );
sabri_social_features_assert( false !== strpos( $share_js, "execCommand('copy')" ), 'Share runtime is missing the legacy copy-link fallback.' );
sabri_social_features_assert( false !== strpos( $assets, 'sabri-hnf-share' ), 'Share runtime is not registered and enqueued.' );
sabri_social_features_assert( false !== strpos( $admin, 'sabri_feed_save_phase3_features' ), 'Secure Phase 3 feature save handler is missing.' );
sabri_social_features_assert( false !== strpos( $admin, "'social-features'" ), 'Social Features admin tab is missing.' );
foreach ( array_keys( $defaults ) as $feature ) {
	sabri_social_features_assert( false !== strpos( $admin_view, 'features[' . $feature . ']' ) || false !== strpos( $admin_view, 'foreach ( $items as $key' ), 'Social Features screen cannot render ' . $feature . '.' );
}
sabri_social_features_assert( false === strpos( $overview, 'Next implementation phase:' ), 'Overview still claims that Phase 3 social interactions are a future phase.' );
sabri_social_features_assert( false !== strpos( $overview, 'Phase 3 social interactions' ), 'Overview does not show the current Phase 3 acceptance state.' );

if ( ! empty( $failures ) ) {
	fwrite( STDERR, "Phase 3 social feature and Share tests failed:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "Phase 3 social feature and Share tests passed.\n";
