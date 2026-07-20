<?php
/**
 * Phase 3 checkpoint 3A infrastructure tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\Database;
use Sabri\HomeNewsFeed\InteractionPermissions;
use Sabri\HomeNewsFeed\InteractionRateLimiter;
use Sabri\HomeNewsFeed\InteractionRepository;
use Sabri\HomeNewsFeed\Migrations;
use Sabri\HomeNewsFeed\Phase3SchemaAudit;
use Sabri\HomeNewsFeed\Plugin;
use Sabri\HomeNewsFeed\PostMetadata;
use Sabri\HomeNewsFeed\SafeMode;
use Sabri\HomeNewsFeed\Settings;

$phase3a_failures = array();

/**
 * Record a checkpoint 3A failure.
 *
 * @param bool   $condition Assertion condition.
 * @param string $message Failure message.
 * @return void
 */
function sabri_phase3a_assert( $condition, $message ) {
	global $phase3a_failures;
	if ( ! $condition ) {
		$phase3a_failures[] = $message;
	}
}

sabri_test_reset_state( true );
Settings::ensure_defaults();

$public_post = sabri_test_add_post(
	array(
		'post_author' => 2,
		'post_status' => 'publish',
		'post_title'  => 'Phase 3A public post',
	),
	array(
		PostMetadata::META_VISIBILITY   => 'public',
		PostMetadata::META_REVIEW_STATE => 'approved',
		PostMetadata::META_TYPE         => 'standard-post',
	)
);
$pending_post = sabri_test_add_post(
	array(
		'post_author' => 4,
		'post_status' => 'publish',
		'post_title'  => 'Phase 3A pending post',
	),
	array(
		PostMetadata::META_VISIBILITY   => 'public',
		PostMetadata::META_REVIEW_STATE => 'pending',
		PostMetadata::META_TYPE         => 'standard-post',
	)
);

// Shared authentication, nonce, visibility, and ownership boundaries.
global $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_transients, $sabri_test_rest_routes;
$sabri_test_current_user_id = 0;
$sabri_test_current_caps    = array();
sabri_phase3a_assert( ! InteractionPermissions::can_interact_with_post( $public_post ), 'Visitors must not perform social writes.' );
sabri_phase3a_assert( ! InteractionPermissions::nonce_valid( '' ), 'Missing nonce must fail closed.' );
sabri_phase3a_assert( ! InteractionPermissions::nonce_valid( 'invalid' ), 'Invalid nonce must fail closed.' );
sabri_phase3a_assert( InteractionPermissions::nonce_valid( 'rest-nonce' ), 'Valid WordPress REST nonce must pass.' );

$sabri_test_current_user_id = 7;
sabri_phase3a_assert( InteractionPermissions::can_interact_with_post( $public_post, 7 ), 'Authenticated members may interact with visible approved posts.' );
sabri_phase3a_assert( ! InteractionPermissions::can_interact_with_post( $pending_post, 7 ), 'Pending posts must reject social writes.' );
$authorized = InteractionPermissions::authorize_post_write( $public_post, 'rest-nonce', 7 );
sabri_phase3a_assert( ! empty( $authorized['ok'] ) && 'authorized' === $authorized['code'], 'Post write authorization must combine login, nonce, and visibility.' );
$denied_nonce = InteractionPermissions::authorize_post_write( $public_post, 'invalid', 7 );
sabri_phase3a_assert( empty( $denied_nonce['ok'] ) && 'invalid_nonce' === $denied_nonce['code'], 'Invalid write nonce must be rejected before mutation.' );
$forged_user = InteractionPermissions::authorize_post_write( $public_post, 'rest-nonce', 6 );
sabri_phase3a_assert( empty( $forged_user['ok'] ) && 'authentication_required' === $forged_user['code'], 'Request data must not select a different existing user identity.' );
sabri_phase3a_assert( InteractionPermissions::owns_private_resource( 7, 7 ), 'Private resource owner must be recognized.' );
sabri_phase3a_assert( ! InteractionPermissions::owns_private_resource( 6, 7 ), 'Private resource IDOR must fail closed.' );

$sabri_test_current_user_id = 1;
$sabri_test_current_caps    = array( 'sabri_feed_manage_reports' => true );
sabri_phase3a_assert( InteractionPermissions::can_manage_reports( 1 ), 'Authorized report managers must pass.' );
sabri_phase3a_assert( ! InteractionPermissions::can_manage_reports( 7 ), 'Capabilities must not be evaluated for an arbitrary non-current user.' );

$sabri_test_current_user_id = 7;
$sabri_test_current_caps    = array();
SafeMode::set_emergency_disabled( true );
sabri_phase3a_assert( ! InteractionPermissions::can_interact_with_post( $public_post, 7 ), 'Emergency Disable must close Phase 3 write authorization.' );
SafeMode::set_emergency_disabled( false );

// Per-user/per-action/per-object bounded rate limiting.
$sabri_test_transients = array();
$unknown_limit = InteractionRateLimiter::attempt( 'unknown', 7, $public_post );
sabri_phase3a_assert( empty( $unknown_limit['ok'] ) && 'unknown_rate_limit_action' === $unknown_limit['code'], 'Unknown rate-limit actions must fail closed.' );
$anonymous_limit = InteractionRateLimiter::attempt( 'reactions', 0, $public_post );
sabri_phase3a_assert( empty( $anonymous_limit['ok'] ) && 'authentication_required' === $anonymous_limit['code'], 'Anonymous rate-limit identities must be rejected.' );

$attempt_one   = InteractionRateLimiter::attempt( 'reactions', 7, $public_post, 2, 60 );
$attempt_two   = InteractionRateLimiter::attempt( 'reactions', 7, $public_post, 2, 60 );
$attempt_three = InteractionRateLimiter::attempt( 'reactions', 7, $public_post, 2, 60 );
sabri_phase3a_assert( ! empty( $attempt_one['ok'] ) && 1 === $attempt_one['data']['remaining'], 'First bounded attempt must be allowed.' );
sabri_phase3a_assert( ! empty( $attempt_two['ok'] ) && 0 === $attempt_two['data']['remaining'], 'Second bounded attempt must consume the final allowance.' );
sabri_phase3a_assert( empty( $attempt_three['ok'] ) && 429 === $attempt_three['status'], 'Excess attempt must receive a controlled rate limit.' );
$other_object = InteractionRateLimiter::attempt( 'reactions', 7, $pending_post, 2, 60 );
sabri_phase3a_assert( ! empty( $other_object['ok'] ), 'Rate-limit buckets must be isolated by object.' );
sabri_phase3a_assert( InteractionRateLimiter::reset( 'reactions', 7, $public_post ), 'Controlled limiter reset must remove only its own bucket.' );
$after_reset = InteractionRateLimiter::attempt( 'reactions', 7, $public_post, 2, 60 );
sabri_phase3a_assert( ! empty( $after_reset['ok'] ), 'Reset bucket must accept a new bounded attempt.' );

// Existing schema definition versus runtime installation state.
sabri_test_reset_state( true );
Settings::ensure_defaults();
$before_install = Phase3SchemaAudit::audit();
sabri_phase3a_assert( empty( $before_install['schema_change_required'] ), 'Existing SQL contract should not require a Phase 3 schema version bump.' );
sabri_phase3a_assert( ! empty( $before_install['runtime_repair_required'] ), 'Missing runtime tables must be reported as repair/install state.' );
sabri_phase3a_assert( false === $before_install['destructive_cleanup'] && true === $before_install['content_preserved'], 'Schema audit must be explicitly non-destructive.' );

$install = Database::install();
sabri_phase3a_assert( ! empty( $install['success'] ), 'Existing plugin-owned social schema must install and verify.' );
$after_install = Phase3SchemaAudit::audit();
sabri_phase3a_assert( ! empty( $after_install['ok'] ), 'Installed social schema must satisfy the Phase 3A audit.' );
sabri_phase3a_assert( empty( $after_install['schema_change_required'] ), 'Checkpoint 3A must not advance schema when current tables are sufficient.' );
sabri_phase3a_assert( SABRI_HNF_SCHEMA_VERSION === get_option( Migrations::SCHEMA_OPTION_NAME ), 'Schema version must remain the accepted Phase 2 value.' );

// Repository allow-lists and bounded writes.
$invalid_repository = InteractionRepository::insert_row( 'unknown_table', array( 'status' => 'active' ) );
sabri_phase3a_assert( empty( $invalid_repository['ok'] ) && 'invalid_repository' === $invalid_repository['code'], 'Unknown repositories must fail closed.' );
$invalid_column = InteractionRepository::insert_row(
	'reactions',
	array(
		'post_id'       => $public_post,
		'user_id'       => 7,
		'reaction_type' => 'like',
		'status'        => 'active',
		'private_email' => 'leak@example.test',
	)
);
sabri_phase3a_assert( empty( $invalid_column['ok'] ) && 'invalid_repository_column' === $invalid_column['code'], 'Unknown repository columns must be rejected.' );
$invalid_integer = InteractionRepository::insert_row(
	'reactions',
	array(
		'post_id'       => -1,
		'user_id'       => 7,
		'reaction_type' => 'like',
		'status'        => 'active',
	)
);
sabri_phase3a_assert( empty( $invalid_integer['ok'] ) && 'invalid_repository_integer' === $invalid_integer['code'], 'Negative identifiers must not be converted into a valid identity.' );
$invalid_status = InteractionRepository::insert_row(
	'reactions',
	array(
		'post_id'       => $public_post,
		'user_id'       => 7,
		'reaction_type' => 'like',
		'status'        => 'secret',
	)
);
sabri_phase3a_assert( empty( $invalid_status['ok'] ) && 'invalid_repository_status' === $invalid_status['code'], 'Unknown row statuses must be rejected.' );
$valid_insert = InteractionRepository::insert_row(
	'reactions',
	array(
		'post_id'       => $public_post,
		'user_id'       => 7,
		'reaction_type' => 'like',
		'status'        => 'active',
	)
);
sabri_phase3a_assert( ! empty( $valid_insert['ok'] ) && 'row_inserted' === $valid_insert['code'], 'Allow-listed repository insert must use the plugin-owned table.' );
$missing_where = InteractionRepository::update_rows( 'reactions', array( 'status' => 'removed' ), array() );
sabri_phase3a_assert( empty( $missing_where['ok'] ) && 'missing_update_condition' === $missing_where['code'], 'Empty repository update conditions must be rejected.' );
$status_only_where = InteractionRepository::update_rows( 'reactions', array( 'reaction_type' => 'dislike' ), array( 'status' => 'active' ) );
sabri_phase3a_assert( empty( $status_only_where['ok'] ) && 'unbounded_update_condition' === $status_only_where['code'], 'Status-only conditions must not update many interaction rows.' );
$valid_update = InteractionRepository::update_rows(
	'reactions',
	array( 'status' => 'removed' ),
	array(
		'post_id' => $public_post,
		'user_id' => 7,
	)
);
sabri_phase3a_assert( ! empty( $valid_update['ok'] ) && 'row_updated' === $valid_update['code'], 'Bounded allow-listed repository update must pass.' );
$append_only_update = InteractionRepository::update_rows( 'audit_log', array( 'action' => 'changed' ), array( 'id' => 1 ) );
sabri_phase3a_assert( empty( $append_only_update['ok'] ) && 'append_only_repository' === $append_only_update['code'], 'Audit log repository must remain append-only.' );
$invalid_hash = InteractionRepository::insert_row(
	'reports',
	array(
		'reporter_user_id' => 7,
		'object_type'      => 'post',
		'object_id'        => $public_post,
		'reason'           => 'spam',
		'status'           => 'open',
		'duplicate_hash'   => 'not-a-safe-hash',
	)
);
sabri_phase3a_assert( empty( $invalid_hash['ok'] ) && 'invalid_repository_hash' === $invalid_hash['code'], 'Repository hash identifiers must use the frozen privacy-safe format.' );

// Checkpoint 3A must not expose public routes or controls.
sabri_test_reset_state();
Plugin::instance()->register();
foreach ( array_keys( $sabri_test_rest_routes ) as $route ) {
	sabri_phase3a_assert( false === strpos( $route, '/reaction' ) && false === strpos( $route, '/comments' ) && false === strpos( $route, '/save' ) && false === strpos( $route, '/follow' ) && false === strpos( $route, '/reports' ) && false === strpos( $route, '/polls' ), 'Checkpoint 3A must not register public Phase 3 routes: ' . $route );
}

$identity = Plugin::identity();
sabri_phase3a_assert( '1.0.0' === $identity['version'] && '1.0.0' === $identity['schema_version'], 'Checkpoint 3A must preserve accepted Phase 2 identity and schema.' );

if ( $phase3a_failures ) {
	echo "FAILED\n";
	foreach ( $phase3a_failures as $failure ) {
		echo '- ' . $failure . "\n";
	}
	exit( 1 );
}

echo "OK - Phase 3 checkpoint 3A infrastructure tests passed.\n";
