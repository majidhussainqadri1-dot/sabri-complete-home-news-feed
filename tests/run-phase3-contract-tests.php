<?php
/**
 * Phase 3 checkpoint 3.0 contract tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\InteractionResult;
use Sabri\HomeNewsFeed\Phase3Contracts;
use Sabri\HomeNewsFeed\Plugin;

$phase3_failures = array();

/**
 * Record a Phase 3 contract failure.
 *
 * @param bool   $condition Assertion condition.
 * @param string $message Failure message.
 * @return void
 */
function sabri_phase3_assert( $condition, $message ) {
	global $phase3_failures;
	if ( ! $condition ) {
		$phase3_failures[] = $message;
	}
}

$identity = Plugin::identity();
sabri_phase3_assert( '1.0.0' === $identity['version'], 'Checkpoint 3.0 must not change the accepted Phase 2 plugin version.' );
sabri_phase3_assert( '1.0.0' === $identity['schema_version'], 'Checkpoint 3.0 must not advance the schema version.' );
sabri_phase3_assert( '1.1.0' === Phase3Contracts::TARGET_VERSION, 'Phase 3 target version must be frozen at 1.1.0.' );
sabri_phase3_assert( '3.0' === Phase3Contracts::CHECKPOINT, 'Contract checkpoint must be 3.0.' );
sabri_phase3_assert( 'sabri-home-news-feed/v1' === Phase3Contracts::REST_NAMESPACE, 'REST namespace must remain compatible with Phase 2.' );

$expected_flags = array(
	'reactions_enabled',
	'dislikes_enabled',
	'comments_enabled',
	'saves_enabled',
	'follows_enabled',
	'followers_visibility_enabled',
	'reports_enabled',
	'polls_enabled',
	'notification_bridge_enabled',
	'view_logging_enabled',
);
$flags = Phase3Contracts::feature_flags();
foreach ( $expected_flags as $flag ) {
	sabri_phase3_assert( array_key_exists( $flag, $flags ), 'Missing Phase 3 feature flag: ' . $flag );
	sabri_phase3_assert( 0 === (int) $flags[ $flag ], 'Checkpoint 3.0 feature flag must default off: ' . $flag );
	sabri_phase3_assert( ! Phase3Contracts::feature_enabled( $flag ), 'Feature check must fail closed before settings integration: ' . $flag );
}
sabri_phase3_assert( ! Phase3Contracts::feature_enabled( 'unknown_future_feature', array( 'social' => array( 'unknown_future_feature' => 1 ) ) ), 'Unknown feature keys must fail closed.' );

$settings = Phase3Contracts::settings_defaults();
sabri_phase3_assert( isset( $settings['social'], $settings['moderation'], $settings['performance'], $settings['privacy'] ), 'Phase 3 settings namespaces must be frozen.' );
sabri_phase3_assert( 2000 === $settings['social']['max_comment_length'], 'Comment length contract must default to 2,000 characters.' );
sabri_phase3_assert( 3 === $settings['social']['max_reply_depth'], 'Reply depth contract must default to three.' );
sabri_phase3_assert( 15 === $settings['social']['comment_edit_minutes'], 'Comment edit window contract must default to 15 minutes.' );
sabri_phase3_assert( 1 === $settings['moderation']['clinical_comment_privacy_scan'], 'Clinical comment privacy scan must default on.' );
sabri_phase3_assert( 'hold' === $settings['moderation']['new_comment_policy'], 'New comment policy must fail closed at contract freeze.' );
sabri_phase3_assert( 0 === $settings['performance']['log_views'], 'View logging must remain off by default.' );
sabri_phase3_assert( 1 === $settings['privacy']['retain_reports_for_accountability'], 'Report accountability retention contract must be explicit.' );

$enabled_settings = $settings;
$enabled_settings['social']['reactions_enabled'] = 1;
$enabled_settings['moderation']['reports_enabled'] = 1;
$enabled_settings['performance']['log_views'] = 1;
sabri_phase3_assert( Phase3Contracts::feature_enabled( 'reactions_enabled', $enabled_settings ), 'Known social flag must read only an explicit enabled value.' );
sabri_phase3_assert( Phase3Contracts::feature_enabled( 'reports_enabled', $enabled_settings ), 'Reports flag must read the moderation namespace.' );
sabri_phase3_assert( Phase3Contracts::feature_enabled( 'view_logging_enabled', $enabled_settings ), 'View logging flag must map to performance.log_views.' );

$routes = Phase3Contracts::rest_routes();
$expected_routes = array(
	'engagement',
	'reaction_create',
	'reaction_delete',
	'comments',
	'comment_create',
	'comment_update',
	'comment_delete',
	'save_create',
	'save_delete',
	'my_saves',
	'follow_create',
	'follow_delete',
	'my_following',
	'report_create',
	'poll_vote',
	'poll_vote_delete',
	'poll_results',
);
foreach ( $expected_routes as $route_key ) {
	sabri_phase3_assert( isset( $routes[ $route_key ] ), 'Missing frozen REST route: ' . $route_key );
	if ( isset( $routes[ $route_key ] ) ) {
		sabri_phase3_assert( in_array( $routes[ $route_key ]['method'], array( 'GET', 'POST', 'PATCH', 'DELETE' ), true ), 'Unsupported REST method in contract: ' . $route_key );
		sabri_phase3_assert( 0 === strpos( $routes[ $route_key ]['path'], '/' ), 'REST contract path must begin with a slash: ' . $route_key );
		sabri_phase3_assert( '' !== $routes[ $route_key ]['permission'], 'REST contract must define permission intent: ' . $route_key );
	}
}

sabri_phase3_assert( array( 'like', 'dislike' ) === Phase3Contracts::reaction_types(), 'Phase 3 reaction allow-list must contain only like and dislike.' );
sabri_phase3_assert( in_array( 'patient-privacy', Phase3Contracts::report_reasons(), true ), 'Report reasons must include patient privacy.' );
sabri_phase3_assert( in_array( 'other', Phase3Contracts::report_reasons(), true ), 'Report reasons must include bounded other reason.' );
sabri_phase3_assert( array( 'open', 'triaged', 'resolved', 'dismissed', 'duplicate' ) === Phase3Contracts::report_states(), 'Report state contract must be stable.' );
sabri_phase3_assert( array( 'after_vote', 'after_close', 'always' ) === Phase3Contracts::poll_results_policies(), 'Poll results policies must be stable.' );

$success = InteractionResult::success( 'reaction_saved', array( 'post_id' => 12 ), 'Saved.', 201 );
$error   = InteractionResult::error( 'permission_denied', '<b>Denied.</b>', array(), 403 );
$invalid = InteractionResult::error( 'invalid status', '', array(), 999 );

sabri_phase3_assert( Phase3Contracts::response_keys() === array_keys( $success ), 'Success response keys must match the frozen service contract.' );
sabri_phase3_assert( true === $success['ok'] && 201 === $success['status'] && 12 === $success['data']['post_id'], 'Success result must preserve safe structured data.' );
sabri_phase3_assert( false === $error['ok'] && 403 === $error['status'] && 'permission_denied' === $error['code'], 'Error result must preserve status and sanitized code.' );
sabri_phase3_assert( 'Denied.' === $error['message'], 'Public result messages must be sanitized.' );
sabri_phase3_assert( 400 === $invalid['status'] && 'invalidstatus' === $invalid['code'], 'Invalid result status must fail to the safe error status.' );

if ( $phase3_failures ) {
	echo "FAILED\n";
	foreach ( $phase3_failures as $failure ) {
		echo '- ' . $failure . "\n";
	}
	exit( 1 );
}

echo "OK - Phase 3 checkpoint 3.0 contract tests passed.\n";
