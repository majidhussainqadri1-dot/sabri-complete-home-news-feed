<?php
/**
 * Phase 3H cross-feature hardening and release-readiness tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\ComposerValidation;
use Sabri\HomeNewsFeed\Database;
use Sabri\HomeNewsFeed\FeedContext;
use Sabri\HomeNewsFeed\FollowersVisibility;
use Sabri\HomeNewsFeed\InteractionQueryRepository;
use Sabri\HomeNewsFeed\Phase3Contracts;
use Sabri\HomeNewsFeed\Phase3FeatureSettings;
use Sabri\HomeNewsFeed\PostMetadata;
use Sabri\HomeNewsFeed\ReleaseReadiness;
use Sabri\HomeNewsFeed\SafeMode;
use Sabri\HomeNewsFeed\Settings;

final class Sabri_Phase3H_Query {
	private $vars = array();
	public function __construct( array $vars = array() ) { $this->vars = $vars; }
	public function get( $key ) { return isset( $this->vars[ $key ] ) ? $this->vars[ $key ] : null; }
	public function set( $key, $value ) { $this->vars[ $key ] = $value; }
}

$phase3h_failures = array();
function sabri_phase3h_assert( $condition, $message ) {
	global $phase3h_failures;
	if ( ! $condition ) {
		$phase3h_failures[] = $message;
	}
}

function sabri_phase3h_set_features( $followers_visibility ) {
	$features = Phase3FeatureSettings::defaults();
	$features['follows_enabled'] = $followers_visibility ? 1 : 0;
	$features['followers_visibility_enabled'] = $followers_visibility ? 1 : 0;
	update_option( Phase3FeatureSettings::OPTION_NAME, $features, false );
}

sabri_test_reset_state( true );
global $wpdb, $sabri_test_rows, $sabri_test_current_user_id, $sabri_test_current_caps;
$wpdb = new Sabri_Test_WPDB();
Database::install();
update_option( Settings::OPTION_NAME, Settings::defaults(), false );

sabri_phase3h_assert( 0 === Phase3FeatureSettings::defaults()['followers_visibility_enabled'], 'Followers-only visibility must remain disabled by default.' );
sabri_phase3h_assert( ! in_array( 'followers', FeedContext::allowed_composer_visibility( Settings::defaults(), true ), true ), 'Disabled followers visibility must not appear in Composer choices.' );

$disabled_validation = ComposerValidation::validate(
	array(
		'composer_action' => 'draft',
		'feed_type'       => 'standard-post',
		'visibility'      => 'followers',
		'content'         => 'Followers-only draft.',
	),
	3,
	Settings::defaults()
);
sabri_phase3h_assert( empty( $disabled_validation['valid'] ), 'A forged followers-only submission must fail while the feature is disabled.' );

sabri_phase3h_set_features( true );
$sabri_test_current_user_id = 7;
sabri_phase3h_assert( in_array( 'followers', FeedContext::allowed_composer_visibility( Settings::defaults(), true ), true ), 'Enabled followers visibility must appear in Composer choices.' );
sabri_phase3h_assert( in_array( 'followers', FeedContext::visible_feed_scopes_for_user( 7, Settings::defaults() ), true ), 'Authenticated viewers must receive the guarded followers candidate scope.' );

$enabled_validation = ComposerValidation::validate(
	array(
		'composer_action' => 'draft',
		'feed_type'       => 'standard-post',
		'visibility'      => 'followers',
		'content'         => 'Followers-only draft.',
	),
	3,
	Settings::defaults()
);
sabri_phase3h_assert( ! empty( $enabled_validation['valid'] ) && 'followers' === $enabled_validation['data']['visibility'], 'Enabled followers-only visibility must validate without weakening other Composer checks.' );

$follow_table = $wpdb->prefix . 'sabri_feed_follows';
$sabri_test_rows[ $follow_table ] = array(
	array(
		'id'               => 1,
		'follower_user_id' => 7,
		'target_user_id'   => 3,
		'target_type'      => 'user',
		'status'           => 'active',
		'created_at'       => '2026-07-19 00:00:00',
		'updated_at'       => '2026-07-19 00:00:00',
	),
	array(
		'id'               => 2,
		'follower_user_id' => 6,
		'target_user_id'   => 3,
		'target_type'      => 'user',
		'status'           => 'removed',
		'created_at'       => '2026-07-19 00:00:00',
		'updated_at'       => '2026-07-19 00:00:00',
	),
);
sabri_phase3h_assert( FollowersVisibility::relationship_active( 7, 3 ), 'An active follower relationship must be recognized.' );
sabri_phase3h_assert( ! FollowersVisibility::relationship_active( 6, 3 ), 'Removed relationships must not authorize followers-only content.' );
sabri_phase3h_assert( ! FollowersVisibility::relationship_active( 3, 3 ), 'Self relationships must not be treated as follower rows.' );

$query = new Sabri_Phase3H_Query(
	array(
		'post_type'  => 'post',
		'meta_query' => array( PostMetadata::visibility_meta_clause(), PostMetadata::review_state_meta_clause() ),
	)
);
FollowersVisibility::extend_post_queries( $query );
sabri_phase3h_assert( 7 === (int) $query->get( FollowersVisibility::QUERY_VIEWER_KEY ), 'Followers query guard must bind to the current session user.' );
$guarded_where = FollowersVisibility::filter_posts_where( ' WHERE 1=1', $query );
sabri_phase3h_assert( false !== strpos( $guarded_where, 'sabri_feed_follows' ) && false !== strpos( $guarded_where, 'follower_user_id = 7' ), 'Followers query SQL must require the active current-user relationship.' );
sabri_phase3h_assert( false !== strpos( $guarded_where, '_sabri_feed_visibility' ) && false !== strpos( $guarded_where, 'followers' ), 'Followers query SQL must isolate only followers-tagged posts.' );

$checklist = ReleaseReadiness::checklist_items();
sabri_phase3h_assert( count( $checklist ) >= 20 && preg_match( '/^[a-f0-9]{64}$/', ReleaseReadiness::checklist_hash() ), 'Release checklist must be frozen and cryptographically identifiable.' );
$readiness = ReleaseReadiness::report();
sabri_phase3h_assert( ! empty( $readiness['code_ready_for_staging'] ), 'Verified code and schema must be eligible for staging review.' );
sabri_phase3h_assert( empty( $readiness['release_ready'] ) && in_array( 'staging_acceptance_missing_or_invalid', $readiness['blocked_reasons'], true ), 'Automated checks alone must never promote a release.' );
sabri_phase3h_assert( in_array( 'plugin_version_not_promoted', $readiness['blocked_reasons'], true ), 'Plugin version promotion must remain blocked before explicit staging acceptance.' );
sabri_phase3h_assert( false === $readiness['automatic_merge'] && false === $readiness['automatic_deployment'], 'Release readiness must never merge or deploy automatically.' );

$settings = Settings::defaults();
$settings['advanced']['emergency_disabled'] = 1;
update_option( Settings::OPTION_NAME, $settings, false );
sabri_phase3h_assert( SafeMode::public_features_disabled(), 'Emergency Disable must close all public Phase 3 surfaces.' );
sabri_phase3h_assert( ! Phase3FeatureSettings::enabled( 'followers_visibility_enabled' ), 'Emergency Disable must override an enabled followers visibility flag.' );
$settings['advanced']['emergency_disabled'] = 0;
update_option( Settings::OPTION_NAME, $settings, false );

$templates = array(
	'action-bar.php'     => array( 'aria-label=', 'aria-pressed=', 'aria-live="polite"' ),
	'comment-thread.php' => array( 'aria-labelledby=', 'maxlength=', 'aria-live="polite"' ),
	'poll.php'           => array( '<fieldset', '<legend', 'screen-reader-text', 'aria-live="polite"' ),
	'report-control.php' => array( '<details', '<summary', 'maxlength=', 'aria-live="polite"' ),
	'composer.php'       => array( 'aria-live="polite"', '<label', 'maxlength=' ),
);
foreach ( $templates as $file => $required_tokens ) {
	$path = dirname( __DIR__ ) . '/templates/' . $file;
	$source = is_readable( $path ) ? file_get_contents( $path ) : '';
	sabri_phase3h_assert( '' !== $source, 'Accessibility audit could not read template ' . $file . '.' );
	foreach ( $required_tokens as $token ) {
		sabri_phase3h_assert( false !== strpos( $source, $token ), 'Template ' . $file . ' is missing accessibility token ' . $token . '.' );
	}
}

$contracts = Phase3Contracts::feature_flags();
foreach ( array( 'comments_enabled', 'follows_enabled', 'followers_visibility_enabled', 'reports_enabled', 'polls_enabled', 'notification_bridge_enabled', 'view_logging_enabled' ) as $flag ) {
	sabri_phase3h_assert( array_key_exists( $flag, $contracts ) && 0 === (int) $contracts[ $flag ], 'Frozen contract must default ' . $flag . ' to disabled.' );
}
sabri_phase3h_assert( '1.0.0' === SABRI_HNF_VERSION && '1.0.0' === SABRI_HNF_SCHEMA_VERSION && '1.1.0' === Phase3Contracts::TARGET_VERSION, '3H must preserve accepted versions until staging acceptance.' );

if ( ! empty( $phase3h_failures ) ) {
	fwrite( STDERR, "Phase 3H hardening tests failed:\n- " . implode( "\n- ", $phase3h_failures ) . "\n" );
	exit( 1 );
}

echo "Phase 3H hardening tests passed.\n";
