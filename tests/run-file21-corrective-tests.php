<?php
/**
 * File 21 corrective publishing regression tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\Composer;
use Sabri\HomeNewsFeed\ComposerPermissions;
use Sabri\HomeNewsFeed\HomeIntegration;
use Sabri\HomeNewsFeed\LegacyFounderPostMigration;
use Sabri\HomeNewsFeed\PostMetadata;
use Sabri\HomeNewsFeed\PrivilegedPublishingPolicy;
use Sabri\HomeNewsFeed\Settings;

$failures = array();

function sabri_corrective_assert( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		$failures[] = $message;
	}
}

sabri_test_reset_state( true );
Settings::ensure_defaults();

global $sabri_test_current_user_id, $sabri_test_current_caps;
$sabri_test_current_caps    = array();
$sabri_test_current_user_id = 2;

sabri_corrective_assert( ComposerPermissions::user_is_privileged_publisher( 2 ), 'Founder must be recognized as privileged.' );
sabri_corrective_assert( ! ComposerPermissions::user_can_submit_for_review( 2 ), 'Founder must not be offered review submission.' );
$founder_submit = ComposerPermissions::resolve_status_for_action( 'submit', 2 );
sabri_corrective_assert( ! empty( $founder_submit['allowed'] ) && 'publish' === $founder_submit['status'], 'Founder submit must normalize to publish.' );

HomeIntegration::reset_runtime_guards();
$founder_composer = Composer::render();
sabri_corrective_assert( false !== strpos( $founder_composer, 'value="publish"' ), 'Founder Composer must expose Publish.' );
sabri_corrective_assert( false === strpos( $founder_composer, 'value="submit"' ), 'Founder Composer must hide Submit for Review.' );

$sabri_test_current_user_id = 1;
$sabri_test_current_caps    = array( 'manage_options' => true );
sabri_corrective_assert( ComposerPermissions::user_is_privileged_publisher( 1 ), 'Administrator must be recognized as privileged.' );
$admin_submit = ComposerPermissions::resolve_status_for_action( 'submit', 1 );
sabri_corrective_assert( ! empty( $admin_submit['allowed'] ) && 'publish' === $admin_submit['status'], 'Administrator submit must normalize to publish.' );

$sabri_test_current_user_id = 4;
$sabri_test_current_caps    = array();
sabri_corrective_assert( ! ComposerPermissions::user_is_privileged_publisher( 4 ), 'Unverified doctor must not be privileged.' );
sabri_corrective_assert( ComposerPermissions::user_can_submit_for_review( 4 ), 'Unverified doctor must retain review submission.' );
$doctor_submit = ComposerPermissions::resolve_status_for_action( 'submit', 4 );
sabri_corrective_assert( ! empty( $doctor_submit['allowed'] ) && 'pending' === $doctor_submit['status'], 'Unverified doctor submit must remain pending.' );

$sabri_test_current_user_id = 2;
$normalized = PrivilegedPublishingPolicy::normalize_core_pending_submission(
	array( 'post_type' => 'post', 'post_status' => 'pending' ),
	array(),
	array(),
	false
);
sabri_corrective_assert( 'publish' === $normalized['post_status'], 'Core Founder pending submission must normalize to publish.' );

$privacy_held = PrivilegedPublishingPolicy::normalize_core_pending_submission(
	array( 'post_type' => 'post', 'post_status' => 'pending' ),
	array(),
	array( 'sabri_privacy_review_required' => 1 ),
	false
);
sabri_corrective_assert( 'pending' === $privacy_held['post_status'], 'Explicit privacy hold must override auto-publish.' );

$published = sabri_test_add_post(
	array( 'post_author' => 2, 'post_status' => 'publish' ),
	array( PostMetadata::META_REVIEW_STATE => 'pending' )
);
PrivilegedPublishingPolicy::sync_published_review_state( $published, get_post( $published ), false );
sabri_corrective_assert( 'approved' === PostMetadata::review_state( $published ), 'Published Founder post must synchronize to approved.' );

$protected = sabri_test_add_post(
	array( 'post_author' => 2, 'post_status' => 'publish' ),
	array( PostMetadata::META_REVIEW_STATE => 'rejected' )
);
PrivilegedPublishingPolicy::sync_published_review_state( $protected, get_post( $protected ), false );
sabri_corrective_assert( 'rejected' === PostMetadata::review_state( $protected ), 'Protected moderation state must remain unchanged.' );

sabri_test_reset_state( true );
Settings::ensure_defaults();
$sabri_test_current_user_id = 1;
$sabri_test_current_caps    = array( 'manage_options' => true );

$legacy_founder = sabri_test_add_post(
	array( 'post_author' => 2, 'post_status' => 'pending', 'post_title' => 'Legacy Founder Pending' ),
	array( PostMetadata::META_REVIEW_STATE => 'pending' )
);
$doctor_pending = sabri_test_add_post(
	array( 'post_author' => 4, 'post_status' => 'pending', 'post_title' => 'Doctor Pending' ),
	array( PostMetadata::META_REVIEW_STATE => 'pending' )
);
$founder_rejected = sabri_test_add_post(
	array( 'post_author' => 2, 'post_status' => 'pending', 'post_title' => 'Founder Rejected' ),
	array( PostMetadata::META_REVIEW_STATE => 'rejected' )
);

sabri_corrective_assert( LegacyFounderPostMigration::is_candidate( $legacy_founder ), 'Pending Founder post must be a bounded restoration candidate.' );
sabri_corrective_assert( ! LegacyFounderPostMigration::is_candidate( $doctor_pending ), 'Pending unverified Doctor post must not be a Founder restoration candidate.' );
sabri_corrective_assert( ! LegacyFounderPostMigration::is_candidate( $founder_rejected ), 'Rejected Founder post must remain protected.' );

$restore = LegacyFounderPostMigration::restore_selected( array( $legacy_founder, $doctor_pending, $founder_rejected ), 1 );
sabri_corrective_assert( in_array( $legacy_founder, $restore['restored'], true ), 'Selected valid Founder post must be restored.' );
sabri_corrective_assert( isset( $restore['skipped'][ $doctor_pending ] ) && isset( $restore['skipped'][ $founder_rejected ] ), 'Invalid or protected selections must be skipped.' );
sabri_corrective_assert( 'publish' === get_post_status( $legacy_founder ), 'Restored Founder post must use WordPress publish status.' );
sabri_corrective_assert( 'approved' === PostMetadata::review_state( $legacy_founder ), 'Restored Founder post must use approved review state.' );

$sabri_test_current_user_id = 4;
$sabri_test_current_caps    = array();
$denied_restore = LegacyFounderPostMigration::restore_selected( array( $doctor_pending ), 4 );
sabri_corrective_assert( empty( $denied_restore['success'] ) && 'permission_denied' === $denied_restore['error'], 'Unauthorized users must not run legacy restoration.' );

if ( $failures ) {
	echo "FAILED\n";
	foreach ( $failures as $failure ) {
		echo '- ' . $failure . "\n";
	}
	exit( 1 );
}

echo "OK - File 21 corrective privileged publishing tests passed.\n";
