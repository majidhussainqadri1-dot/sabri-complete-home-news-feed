<?php
/**
 * Composer authority precedence regressions.
 *
 * @package SabriCompleteHomeNewsFeed
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\ComposerPermissions;
use Sabri\HomeNewsFeed\Settings;

$failures = array();
$assert = static function ( bool $condition, string $message ) use ( &$failures ): void {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$set_actor = static function ( int $user_id, array $roles, array $caps = array() ): void {
	global $sabri_test_current_user_id, $sabri_test_user_roles, $sabri_test_current_caps;
	$sabri_test_current_user_id       = $user_id;
	$sabri_test_user_roles[ $user_id ] = $roles;
	$sabri_test_current_caps           = array_fill_keys( $caps, true );
};

sabri_test_reset_state( true );
$settings = Settings::defaults();

$set_actor( 1, array( 'administrator', 'subscriber' ), array( 'manage_options', 'sabri_feed_create_posts', 'sabri_feed_publish_posts' ) );
$assert( ComposerPermissions::user_can_create( 1, $settings ), 'Administrator plus Subscriber must retain Create authority.' );
$assert( ComposerPermissions::user_can_publish( 1, $settings ), 'Administrator plus Subscriber must retain immediate publish authority.' );

$set_actor( 2, array( 'founder', 'patient' ) );
$assert( ComposerPermissions::user_can_create( 2, $settings ), 'Founder plus Patient must retain Create authority.' );
$assert( ComposerPermissions::user_can_publish( 2, $settings ), 'Founder plus Patient must retain immediate publish authority.' );

$set_actor( 3, array( 'verified_doctor', 'subscriber' ) );
$assert( ComposerPermissions::user_can_create( 3, $settings ), 'Verified Doctor plus Subscriber must retain Create authority.' );
$assert( ComposerPermissions::user_can_submit_for_review( 3, $settings ), 'Verified Doctor plus Subscriber must retain the configured review path.' );

$set_actor( 4, array( 'doctor', 'subscriber' ) );
$assert( ComposerPermissions::user_can_create( 4, $settings ), 'Unverified Doctor plus Subscriber must retain Create authority.' );
$assert( ComposerPermissions::user_can_submit_for_review( 4, $settings ), 'Unverified Doctor plus Subscriber must retain submission-for-review authority.' );

$set_actor( 5, array( 'student' ) );
$assert( ! ComposerPermissions::user_can_create( 5, $settings ), 'Student must remain denied.' );

$set_actor( 6, array( 'patient' ) );
$assert( ! ComposerPermissions::user_can_create( 6, $settings ), 'Patient must remain denied.' );

$set_actor( 7, array( 'subscriber' ) );
$assert( ! ComposerPermissions::user_can_create( 7, $settings ), 'Subscriber-only account must remain denied.' );

$set_actor( 1, array( 'administrator', 'subscriber' ), array( 'manage_options', 'sabri_feed_create_posts' ) );
$settings['advanced']['emergency_disabled'] = 1;
update_option( Settings::OPTION_NAME, $settings, false );
$assert( ! ComposerPermissions::user_can_create( 1 ), 'Emergency Disable must still override Administrator authority.' );

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo "Composer authority precedence tests passed.\n";
