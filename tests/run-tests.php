<?php
/**
 * Behavior test runner.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\Activator;
use Sabri\HomeNewsFeed\Assets;
use Sabri\HomeNewsFeed\Capabilities;
use Sabri\HomeNewsFeed\Database;
use Sabri\HomeNewsFeed\DataRetention;
use Sabri\HomeNewsFeed\Composer;
use Sabri\HomeNewsFeed\ComposerPermissions;
use Sabri\HomeNewsFeed\ComposerValidation;
use Sabri\HomeNewsFeed\FeedContext;
use Sabri\HomeNewsFeed\FeedQuery;
use Sabri\HomeNewsFeed\FeedRenderer;
use Sabri\HomeNewsFeed\HomeIntegration;
use Sabri\HomeNewsFeed\MediaHandler;
use Sabri\HomeNewsFeed\PostMetadata;
use Sabri\HomeNewsFeed\Migrations;
use Sabri\HomeNewsFeed\Plugin;
use Sabri\HomeNewsFeed\Repair;
use Sabri\HomeNewsFeed\RestFoundation;
use Sabri\HomeNewsFeed\RestFeed;
use Sabri\HomeNewsFeed\RestComposer;
use Sabri\HomeNewsFeed\Rollback;
use Sabri\HomeNewsFeed\SafeMode;
use Sabri\HomeNewsFeed\Settings;
use Sabri\HomeNewsFeed\Snapshot;
use Sabri\HomeNewsFeed\Shortcodes;
use Sabri\HomeNewsFeed\SystemCheck;
use Sabri\HomeNewsFeed\Taxonomies;

$failures = array();

function sabri_assert( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		$failures[] = $message;
	}
}

function sabri_reset_options() {
	sabri_test_reset_state( true );
	sabri_reset_roles();
}

function sabri_reset_roles() {
	global $sabri_test_roles;
	$sabri_test_roles = array(
		'administrator'   => new Sabri_Test_Role( array( 'manage_options' => true ) ),
		'editor'          => new Sabri_Test_Role( array( 'edit_posts' => true ) ),
		'founder'         => new Sabri_Test_Role(),
		'verified_doctor' => new Sabri_Test_Role(),
		'doctor'          => new Sabri_Test_Role(),
		'student'         => new Sabri_Test_Role(),
		'patient'         => new Sabri_Test_Role(),
		'subscriber'      => new Sabri_Test_Role( array( 'read' => true ) ),
	);
}

function sabri_rest_arg_valid( $route, $arg, $value ) {
	global $sabri_test_rest_routes;
	if ( empty( $sabri_test_rest_routes[ $route ]['args'][ $arg ]['validate_callback'] ) ) {
		return false;
	}

	return (bool) call_user_func( $sabri_test_rest_routes[ $route ]['args'][ $arg ]['validate_callback'], $value, null, $arg );
}

function sabri_rest_arg_sanitized( $route, $arg, $value ) {
	global $sabri_test_rest_routes;
	if ( empty( $sabri_test_rest_routes[ $route ]['args'][ $arg ]['sanitize_callback'] ) ) {
		return $value;
	}

	return call_user_func( $sabri_test_rest_routes[ $route ]['args'][ $arg ]['sanitize_callback'], $value, null, $arg );
}

function sabri_test_identity() {
	$identity = Plugin::identity();
	sabri_assert( '1.0.0' === $identity['version'], 'Plugin identity version must be 1.0.0.' );
	sabri_assert( SABRI_HNF_VERSION === $identity['version'], 'Constant and identity versions must match.' );
	sabri_assert( SABRI_HNF_SCHEMA_VERSION === $identity['schema_version'], 'Schema version constant and identity must match.' );
}

function sabri_test_bootstrap_no_wrappers() {
	global $sabri_test_actions;
	$before = ob_get_level();
	Plugin::instance()->register();
	$after = ob_get_level();
	$hooks = array_map(
		static function ( $row ) {
			return $row['hook'];
		},
		$sabri_test_actions
	);
	sabri_assert( $before === $after, 'Bootstrap must not open whole-page output buffers.' );
	sabri_assert( ! in_array( 'wp_body_open', $hooks, true ), 'Plugin must not wrap output from wp_body_open.' );
	sabri_assert( ! in_array( 'wp_footer', $hooks, true ), 'Plugin must not wrap output through wp_footer.' );
}

function sabri_test_admin_staging_preview_assets() {
	global $sabri_test_enqueued_styles, $sabri_test_enqueued_scripts;

	sabri_test_reset_state();
	Assets::enqueue_admin( 'home-news-feed_page_sabri-feed-staging-preview' );

	sabri_assert( in_array( 'sabri-feed-admin', $sabri_test_enqueued_styles, true ), 'Staging Preview must retain admin styles.' );
	sabri_assert( in_array( 'sabri-hnf-feed', $sabri_test_enqueued_styles, true ), 'Staging Preview must enqueue Home Feed styles.' );
	sabri_assert( in_array( 'sabri-hnf-composer', $sabri_test_enqueued_styles, true ), 'Staging Preview must enqueue Composer styles.' );
	sabri_assert( in_array( 'sabri-hnf-feed', $sabri_test_enqueued_scripts, true ), 'Staging Preview must enqueue Home Feed behavior.' );
	sabri_assert( in_array( 'sabri-hnf-composer', $sabri_test_enqueued_scripts, true ), 'Staging Preview must enqueue Composer behavior.' );

	$view_file = SABRI_HNF_PATH . 'admin/views/staging-preview.php';
	$view = is_readable( $view_file ) ? file_get_contents( $view_file ) : '';
	sabri_assert( '' !== $view, 'Administrator staging preview view must ship in the release.' );
	sabri_assert( false !== strpos( $view, 'Shortcodes::home_feed' ) && false !== strpos( $view, 'Shortcodes::composer' ), 'Staging Preview must render the real feed and composer runtimes.' );
}

function sabri_test_activation_snapshot_order() {
	global $sabri_test_update_log;
	sabri_reset_options();
	Activator::activate();
	sabri_assert( Snapshot::OPTION_NAME === $sabri_test_update_log[0], 'Activation snapshot must be saved before settings, schema, taxonomy, or capability mutations.' );
	sabri_assert( get_option( Migrations::SCHEMA_OPTION_NAME ) === SABRI_HNF_SCHEMA_VERSION, 'Activation must install schema version idempotently.' );
}

function sabri_test_schema_install_failures_do_not_advance_version() {
	global $sabri_test_filter_overrides, $sabri_test_dbdelta_skip_table, $sabri_test_dbdelta_skip_index;

	sabri_reset_options();
	update_option( Migrations::SCHEMA_OPTION_NAME, '0.9.0', false );
	$sabri_test_filter_overrides['sabri_feed_dbdelta_available'] = false;
	$result = Database::install();
	sabri_assert( false === $result['success'], 'Schema install must fail when dbDelta is unavailable.' );
	sabri_assert( '0.9.0' === get_option( Migrations::SCHEMA_OPTION_NAME ), 'Schema version must not advance when dbDelta is unavailable.' );
	sabri_assert( 'Installation failed' === SystemCheck::migration_status(), 'System Check must report failed schema installation.' );

	sabri_reset_options();
	update_option( Migrations::SCHEMA_OPTION_NAME, '0.9.0', false );
	$sabri_test_dbdelta_skip_table = 'wp_sabri_feed_saves';
	$result = Database::install();
	sabri_assert( false === $result['success'], 'Schema install must fail when a required table is missing.' );
	sabri_assert( in_array( 'saves', $result['missing_tables'], true ), 'Missing table report must include saves.' );
	sabri_assert( '0.9.0' === get_option( Migrations::SCHEMA_OPTION_NAME ), 'Schema version must not advance when a table is missing.' );

	sabri_reset_options();
	update_option( Migrations::SCHEMA_OPTION_NAME, '0.9.0', false );
	$sabri_test_dbdelta_skip_index = 'user_post_status';
	$result = Database::install();
	sabri_assert( false === $result['success'], 'Schema install must fail when a required index is missing.' );
	sabri_assert( in_array( 'reactions.user_post_status:Missing', $result['missing_indexes'], true ), 'Missing index report must include reactions.user_post_status.' );
	sabri_assert( '0.9.0' === get_option( Migrations::SCHEMA_OPTION_NAME ), 'Schema version must not advance when an index is missing.' );

	sabri_reset_options();
	update_option( Migrations::SCHEMA_OPTION_NAME, SABRI_HNF_SCHEMA_VERSION, false );
	sabri_assert( 'Schema version current but structure incomplete' === SystemCheck::migration_status(), 'System Check must distinguish current version with incomplete structure.' );
}

function sabri_test_database_schema() {
	$schema_a = Database::schema( 'wp_' );
	$schema_b = Database::schema( 'wp_' );
	sabri_assert( $schema_a === $schema_b, 'Database schema generation must be idempotent.' );
	foreach ( Database::table_slugs() as $slug ) {
		sabri_assert( isset( $schema_a[ $slug ] ), 'Missing SQL for table ' . $slug . '.' );
		foreach ( Database::expected_indexes()[ $slug ] as $index ) {
			sabri_assert( false !== strpos( $schema_a[ $slug ], $index ), 'Expected index ' . $index . ' missing from ' . $slug . ' SQL.' );
		}
	}
	sabri_assert( false !== strpos( $schema_a['reactions'], 'UNIQUE KEY user_post_status' ), 'Reactions must prevent duplicate user/post/status rows.' );
	sabri_assert( false !== strpos( $schema_a['saves'], 'UNIQUE KEY user_post_collection' ), 'Saves must prevent duplicate collection saves.' );
	sabri_assert( false !== strpos( $schema_a['follows'], 'UNIQUE KEY follower_target' ), 'Follows must prevent duplicate follow relations.' );
	sabri_assert( false !== strpos( $schema_a['poll_votes'], 'UNIQUE KEY vote_identity' ), 'Poll votes must enforce poll identity uniqueness.' );
	sabri_assert( false !== strpos( $schema_a['reports'], 'UNIQUE KEY duplicate_control' ), 'Reports must include duplicate-abuse controls.' );
}

function sabri_test_settings_isolation() {
	sabri_reset_options();
	$settings = Settings::defaults();
	$settings['feed']['enabled'] = 1;
	$settings['news']['source_url'] = 'https://example.test/news';
	$settings['future_namespace'] = array( 'future_key' => 'keep' );
	update_option( Settings::OPTION_NAME, $settings, false );

	Settings::update_tab(
		'feed',
		array(
			'default_count' => 999,
			'unknown_future_key' => 'still here',
		)
	);

	$updated = Settings::get();
	sabri_assert( 0 === $updated['feed']['enabled'], 'Missing checkbox input must turn a checked setting off.' );
	sabri_assert( 50 === $updated['feed']['default_count'], 'Integer settings must be clamped to range.' );
	sabri_assert( 'https://example.test/news' === $updated['news']['source_url'], 'Updating one tab must preserve other tabs.' );
	sabri_assert( 'keep' === $updated['future_namespace']['future_key'], 'Unknown future namespaces must be preserved.' );
	sabri_assert( 'still here' === $updated['feed']['unknown_future_key'], 'Unknown future keys in a tab must be preserved.' );
}

function sabri_test_integration_function_settings_preservation() {
	sabri_reset_options();
	$settings = Settings::defaults();
	$settings['integrations']['shell_required'] = 1;
	$settings['integrations']['functions']['messages'] = 'Existing_Message_Callback';
	$settings['integrations']['functions']['future_hook'] = 'Future\\Safe_Callback';
	update_option( Settings::OPTION_NAME, $settings, false );

	Settings::update_tab(
		'integrations',
		array(
			'functions' => array(
				'notifications' => 'New\\Notifications_Callback',
				'future_incoming' => 'unsafe!value',
			),
		)
	);

	$updated = Settings::get();
	sabri_assert( 0 === $updated['integrations']['shell_required'], 'Recognized integration checkbox must turn off when omitted.' );
	sabri_assert( 'New\\Notifications_Callback' === $updated['integrations']['functions']['notifications'], 'Recognized incoming function key must update.' );
	sabri_assert( 'Existing_Message_Callback' === $updated['integrations']['functions']['messages'], 'Unsubmitted recognized integration function key must be preserved.' );
	sabri_assert( 'Future\\Safe_Callback' === $updated['integrations']['functions']['future_hook'], 'Existing unknown future integration function key must be preserved.' );
	sabri_assert( ! isset( $updated['integrations']['functions']['future_incoming'] ), 'Unsafe incoming unknown integration function key must not be preserved.' );
}

function sabri_test_capability_policy() {
	global $sabri_test_roles;
	sabri_reset_options();
	Capabilities::apply_default_policy();
	sabri_assert( ! Capabilities::role_can_publish( 'student' ), 'Students must not receive publication rights by default.' );
	sabri_assert( ! Capabilities::role_can_publish( 'patient' ), 'Patients must not receive publication rights by default.' );
	sabri_assert( ! Capabilities::role_can_publish( 'subscriber' ), 'Subscribers/patients must not receive publication rights by default.' );
	sabri_assert( ! empty( $sabri_test_roles['administrator']->capabilities['sabri_feed_manage_settings'] ), 'Administrators must be able to manage plugin settings.' );

	$settings = Settings::defaults();
	$settings['capabilities']['verified_doctor_policy'] = 'publish';
	sabri_assert( Capabilities::role_can_publish( 'verified_doctor', $settings ), 'Verified doctor publishing must be configurable.' );
	$settings['capabilities']['verified_doctor_policy'] = 'submit';
	sabri_assert( ! Capabilities::role_can_publish( 'verified_doctor', $settings ), 'Verified doctor submit-only policy must be configurable.' );
	sabri_assert( Capabilities::role_can_publish( 'founder', $settings ), 'Configured founder roles may publish immediately when present.' );
}

function sabri_test_safe_mode_and_emergency() {
	global $sabri_test_current_caps, $sabri_test_current_user_id;
	sabri_reset_options();
	Settings::ensure_defaults();
	$_GET['sabri_feed_safe'] = '1';
	$sabri_test_current_user_id = 0;
	$sabri_test_current_caps = array();
	sabri_assert( ! SafeMode::query_safe_mode(), 'Safe Mode query must require an administrator.' );
	$sabri_test_current_user_id = 1;
	$sabri_test_current_caps = array( 'manage_options' => true, 'sabri_feed_manage_settings' => true );
	sabri_assert( SafeMode::query_safe_mode(), 'Administrators may use read-only Safe Mode query.' );
	SafeMode::set_emergency_disabled( true );
	sabri_assert( SafeMode::emergency_disabled(), 'Emergency Disable must persist.' );
	sabri_assert( ! SafeMode::feature_enabled( 'composer' ), 'Emergency Disable must close future public composer gate.' );
	unset( $_GET['sabri_feed_safe'] );
}

function sabri_test_safe_mode_security_matrix() {
	global $sabri_test_current_caps, $sabri_test_current_user_id;
	sabri_reset_options();
	Settings::ensure_defaults();

	sabri_test_reset_state();
	sabri_assert( ! SafeMode::query_safe_mode(), 'Safe Mode visitor without query must remain inactive.' );

	sabri_test_reset_state();
	Settings::ensure_defaults();
	$_GET['sabri_feed_safe'] = '1';
	$sabri_test_current_user_id = 0;
	$sabri_test_current_caps = array();
	sabri_assert( ! SafeMode::query_safe_mode(), 'Safe Mode logged-out query must be denied.' );

	sabri_test_reset_state();
	Settings::ensure_defaults();
	$_GET['sabri_feed_safe'] = '1';
	$sabri_test_current_user_id = 7;
	$sabri_test_current_caps = array( 'read' => true );
	sabri_assert( ! SafeMode::query_safe_mode(), 'Safe Mode logged-in non-admin must be denied.' );

	sabri_test_reset_state();
	Settings::ensure_defaults();
	$_GET['sabri_feed_safe'] = '1';
	$sabri_test_current_user_id = 4;
	$sabri_test_current_caps = array();
	sabri_assert( ! SafeMode::query_safe_mode(), 'Safe Mode doctor without administrator authority must be denied.' );

	sabri_test_reset_state();
	Settings::ensure_defaults();
	$_GET['sabri_feed_safe'] = '1';
	$sabri_test_current_user_id = 7;
	$sabri_test_current_caps = array( 'edit_posts' => true );
	sabri_assert( ! SafeMode::query_safe_mode(), 'Safe Mode must deny users with only ordinary post capabilities.' );

	sabri_test_reset_state();
	Settings::ensure_defaults();
	$_GET['sabri_feed_safe'] = '1';
	$sabri_test_current_user_id = 1;
	$sabri_test_current_caps = array( 'manage_options' => true );
	sabri_assert( SafeMode::query_safe_mode(), 'Safe Mode administrator query must be allowed.' );
}

function sabri_test_rollback_and_repair_boundaries() {
	sabri_reset_options();
	Activator::activate();
	$rollback = Rollback::preview();
	sabri_assert( false === $rollback['destructive'], 'Rollback preview must be non-destructive.' );
	sabri_assert( in_array( 'posts', $rollback['will_not_delete'], true ), 'Rollback must preserve posts.' );
	$executed = Rollback::execute();
	sabri_assert( false === $executed['deleted_content'], 'Rollback execution must not delete content.' );
	$repair = Repair::execute( 'orphan_preview' );
	sabri_assert( false === $repair['destructive'], 'Repair must be non-destructive.' );
}

function sabri_test_rest_permissions() {
	global $sabri_test_current_caps, $sabri_test_current_user_id;
	sabri_reset_options();
	$sabri_test_current_user_id = 0;
	$sabri_test_current_caps = array();
	sabri_assert( ! RestFoundation::permission_callback(), 'REST diagnostics must deny unauthenticated users.' );
	$sabri_test_current_user_id = 1;
	$sabri_test_current_caps = array( 'sabri_feed_manage_settings' => true );
	sabri_assert( RestFoundation::permission_callback(), 'REST diagnostics must allow plugin administrators.' );
}

function sabri_test_rest_diagnostics_security_matrix() {
	global $sabri_test_current_caps, $sabri_test_current_user_id;
	sabri_reset_options();

	sabri_test_reset_state();
	sabri_assert( ! RestFoundation::permission_callback(), 'REST diagnostics visitor request must be denied.' );

	sabri_test_reset_state();
	$sabri_test_current_user_id = 0;
	$sabri_test_current_caps = array( 'sabri_feed_manage_settings' => true );
	sabri_assert( ! RestFoundation::permission_callback(), 'REST diagnostics logged-out request must be denied even with a stale capability map.' );

	sabri_test_reset_state();
	$sabri_test_current_user_id = 7;
	$sabri_test_current_caps = array( 'read' => true );
	sabri_assert( ! RestFoundation::permission_callback(), 'REST diagnostics logged-in user without plugin capability must be denied.' );

	sabri_test_reset_state();
	$sabri_test_current_user_id = 1;
	$sabri_test_current_caps = array( 'manage_options' => true );
	sabri_assert( RestFoundation::permission_callback(), 'REST diagnostics administrator must be allowed.' );

	sabri_test_reset_state();
	$sabri_test_current_user_id = 7;
	$sabri_test_current_caps = array( 'sabri_feed_manage_settings' => true );
	sabri_assert( RestFoundation::permission_callback(), 'REST diagnostics authenticated plugin manager must be allowed.' );
}

function sabri_test_phase2_feed_context_and_query() {
	global $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_is_admin;
	sabri_reset_options();
	Settings::ensure_defaults();
	$sabri_test_is_admin = false;
	$sabri_test_current_user_id = 0;
	$sabri_test_current_caps = array();

	$public = sabri_test_add_post(
		array( 'post_author' => 2, 'post_title' => 'Public update' ),
		array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'founder-update' ),
		array( 'sabri_feed_type' => array( 'founder-update' ) )
	);
	$members = sabri_test_add_post(
		array( 'post_author' => 3, 'post_title' => 'Members update' ),
		array( PostMetadata::META_VISIBILITY => 'members', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' ),
		array( 'sabri_feed_type' => array( 'standard-post' ) )
	);
	$removed = sabri_test_add_post(
		array( 'post_author' => 3, 'post_title' => 'Removed update' ),
		array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'removed', PostMetadata::META_TYPE => 'standard-post' ),
		array( 'sabri_feed_type' => array( 'standard-post' ) )
	);
	$clinical = sabri_test_add_post(
		array( 'post_author' => 3, 'post_title' => 'Clinical update' ),
		array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'clinical-case' ),
		array( 'sabri_feed_type' => array( 'clinical-case' ) )
	);

	$result = FeedQuery::query( array( 'mode' => 'latest', 'page' => 1, 'per_page' => 50 ) );
	$ids = array_map(
		static function ( $post ) {
			return (int) $post->ID;
		},
		$result['posts']
	);
	sabri_assert( in_array( $public, $ids, true ), 'Visitor feed must include public posts.' );
	sabri_assert( in_array( $clinical, $ids, true ), 'Visitor feed must include visible clinical posts.' );
	sabri_assert( ! in_array( $members, $ids, true ), 'Visitor feed must exclude member-only posts.' );
	sabri_assert( ! in_array( $removed, $ids, true ), 'Visitor feed must exclude removed posts.' );
	sabri_assert( array( 'publish' ) === $result['query_args']['post_status'], 'Feed query must only request published posts for public feed output.' );

	$clinical_result = FeedQuery::query( array( 'mode' => 'clinical-cases', 'page' => 1, 'per_page' => 10 ) );
	sabri_assert( 1 === count( $clinical_result['posts'] ) && $clinical === (int) $clinical_result['posts'][0]->ID, 'Clinical Cases mode must use real feed type taxonomy filtering.' );

	$settings = Settings::get();
	$settings['feed']['enabled_filters'] = array( 'latest' );
	$settings['feed']['default_mode'] = 'latest';
	sabri_assert( 'latest' === FeedContext::normalize_mode( 'research', $settings ), 'Invalid or disabled feed filters must fall back safely.' );
	sabri_assert( 50 === FeedContext::per_page( 999, $settings ), 'Feed per-page values must be bounded.' );
}

function sabri_test_phase2_visibility_rules() {
	global $sabri_test_current_user_id, $sabri_test_current_caps;
	sabri_reset_options();
	Settings::ensure_defaults();
	$sabri_test_current_caps = array();

	$public = sabri_test_add_post( array( 'post_author' => 2 ), array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved' ) );
	$members = sabri_test_add_post( array( 'post_author' => 2 ), array( PostMetadata::META_VISIBILITY => 'members', PostMetadata::META_REVIEW_STATE => 'approved' ) );
	$doctors = sabri_test_add_post( array( 'post_author' => 3 ), array( PostMetadata::META_VISIBILITY => 'doctors', PostMetadata::META_REVIEW_STATE => 'approved' ) );
	$private = sabri_test_add_post( array( 'post_author' => 4, 'post_status' => 'draft' ), array( PostMetadata::META_VISIBILITY => 'private', PostMetadata::META_REVIEW_STATE => 'pending' ) );

	$sabri_test_current_user_id = 0;
	sabri_assert( PostMetadata::user_can_view( $public, 0 ), 'Visitors must be able to view public posts.' );
	sabri_assert( ! PostMetadata::user_can_view( $members, 0 ), 'Visitors must not view registered-member posts.' );
	sabri_assert( ! PostMetadata::user_can_view( $doctors, 0 ), 'Visitors must not view doctors-only posts.' );

	$sabri_test_current_user_id = 3;
	sabri_assert( PostMetadata::user_can_view( $members, 3 ), 'Authenticated members must view member posts.' );
	sabri_assert( PostMetadata::user_can_view( $doctors, 3 ), 'Verified doctors must view doctors-only posts.' );
	sabri_assert( ! PostMetadata::user_can_view( $private, 3 ), 'Users must not view another author private draft.' );

	$sabri_test_current_user_id = 4;
	sabri_assert( PostMetadata::user_can_view( $private, 4 ), 'Authors with composer rights may view their own private draft.' );
}

function sabri_test_phase2_composer_permissions_and_statuses() {
	global $sabri_test_current_user_id, $sabri_test_current_caps;
	sabri_reset_options();
	Settings::ensure_defaults();
	$sabri_test_current_caps = array();

	$sabri_test_current_user_id = 5;
	sabri_assert( ! ComposerPermissions::user_can_create(), 'Students must not access the general public composer.' );
	$sabri_test_current_user_id = 6;
	sabri_assert( ! ComposerPermissions::user_can_create(), 'Patients must not access the general public composer.' );

	$sabri_test_current_user_id = 4;
	sabri_assert( ComposerPermissions::user_can_create(), 'Unverified doctors may create posts for review.' );
	$submit = ComposerPermissions::resolve_status_for_action( 'submit' );
	sabri_assert( ! empty( $submit['allowed'] ) && 'pending' === $submit['status'], 'Unverified doctor submit action must create pending posts.' );
	$publish = ComposerPermissions::resolve_status_for_action( 'publish' );
	sabri_assert( empty( $publish['allowed'] ) && 'publish_denied' === $publish['code'], 'Unauthorized publish escalation must be rejected.' );

	$sabri_test_current_user_id = 2;
	sabri_assert( ComposerPermissions::user_can_publish(), 'Founder roles may publish immediately.' );
	$settings = Settings::get();
	$settings['composer']['scheduling_enabled'] = 1;
	$natural_language_schedule = ComposerPermissions::resolve_status_for_action( 'schedule', 2, $settings, 'tomorrow' );
	sabri_assert( empty( $natural_language_schedule['allowed'] ) && 'schedule_denied' === $natural_language_schedule['code'], 'Composer schedule action must reject permissive natural-language dates.' );

	$settings['capabilities']['verified_doctor_policy'] = 'publish';
	$sabri_test_current_user_id = 3;
	$verified_publish = ComposerPermissions::resolve_status_for_action( 'publish', 3, $settings );
	sabri_assert( ! empty( $verified_publish['allowed'] ) && 'publish' === $verified_publish['status'], 'Verified doctor publish policy must be configurable.' );
}

function sabri_test_phase2_composer_validation_structures() {
	sabri_reset_options();
	Settings::ensure_defaults();
	$settings = Settings::get();

	$clinical = ComposerValidation::validate(
		array(
			'content' => 'A de-identified case.',
			'feed_type' => 'clinical-case',
			'clinical_case' => array(
				'case_title' => 'Case',
				'patient_full_name' => 'Private Name',
			),
			'medical_disclaimer_confirmed' => 1,
		),
		4,
		$settings
	);
	$clinical_codes = array_column( $clinical['errors'], 'code' );
	sabri_assert( in_array( 'forbidden_patient_identifier', $clinical_codes, true ), 'Clinical Case validation must reject direct patient identifiers.' );
	sabri_assert( in_array( 'patient_privacy_required', $clinical_codes, true ), 'Clinical Case validation must require patient privacy confirmation when configured.' );

	$research = ComposerValidation::validate(
		array(
			'content' => 'Research content.',
			'feed_type' => 'research',
			'research' => array( 'evidence_level' => 'scientifically-proven' ),
			'medical_disclaimer_confirmed' => 1,
			'patient_privacy_confirmed' => 1,
		),
		3,
		$settings
	);
	$research_codes = array_column( $research['errors'], 'code' );
	sabri_assert( in_array( 'invalid_evidence_level', $research_codes, true ), 'Research evidence levels must use controlled terms.' );
	sabri_assert( 'unverified-claim' === $research['data']['evidence_level'], 'Invalid Research evidence must fall back to Unverified Claim.' );

	$followers = ComposerValidation::validate(
		array(
			'content' => 'Visibility test.',
			'visibility' => 'followers',
			'medical_disclaimer_confirmed' => 1,
			'patient_privacy_confirmed' => 1,
		),
		3,
		$settings
	);
	sabri_assert( in_array( 'followers_visibility_deferred', array_column( $followers['errors'], 'code' ), true ), 'Followers visibility must stay disabled until Phase 3.' );
}

function sabri_test_phase2_clinical_identifier_protection() {
	global $sabri_test_current_user_id;
	sabri_reset_options();
	Settings::ensure_defaults();
	$settings = Settings::get();
	$base = array(
		'content' => 'A de-identified clinical summary.',
		'feed_type' => 'clinical-case',
		'medical_disclaimer_confirmed' => 1,
		'patient_privacy_confirmed' => 1,
	);

	$phone = ComposerValidation::validate( array_merge( $base, array( 'clinical_case' => array( 'chief_complaints' => 'Mobile: 03001234567' ) ) ), 4, $settings );
	sabri_assert( ! $phone['valid'] && 'chief_complaints' === $phone['errors'][0]['field'], 'Clinical Case validation must reject phone numbers in allowed fields without echoing values.' );

	$cnic = ComposerValidation::validate( array_merge( $base, array( 'content' => 'CNIC: 12345-1234567-1' ) ), 4, $settings );
	sabri_assert( ! $cnic['valid'] && in_array( 'content', array_column( $cnic['errors'], 'field' ), true ), 'Clinical Case validation must reject CNIC values in content.' );

	$passport = ComposerValidation::validate( array_merge( $base, array( 'clinical_case' => array( 'investigation_notes' => 'Passport: AB123456' ) ) ), 4, $settings );
	sabri_assert( ! $passport['valid'] && in_array( 'investigation_notes', array_column( $passport['errors'], 'field' ), true ), 'Clinical Case validation must reject passport-labelled values in notes.' );

	$address = ComposerValidation::validate( array_merge( $base, array( 'clinical_case' => array( 'follow_up' => 'Address: House 2 Street 4 Lahore Pakistan' ) ) ), 4, $settings );
	sabri_assert( ! $address['valid'] && in_array( 'follow_up', array_column( $address['errors'], 'field' ), true ), 'Clinical Case validation must reject complete address-labelled values.' );

	$mrn = ComposerValidation::validate( array_merge( $base, array( 'clinical_case' => array( 'investigation_notes' => 'MRN: HOSP-99881' ) ) ), 4, $settings );
	sabri_assert( ! $mrn['valid'] && in_array( 'investigation_notes', array_column( $mrn['errors'], 'field' ), true ), 'Clinical Case validation must reject medical-record identifiers.' );

	$name = ComposerValidation::validate( array_merge( $base, array( 'clinical_case' => array( 'case_title' => 'Patient Name: Ali Khan' ) ) ), 4, $settings );
	sabri_assert( $name['valid'] && ! empty( $name['data']['privacy_review_required'] ), 'Ambiguous patient-name patterns must trigger privacy review without hard PHI rejection.' );

	$caption_name = ComposerValidation::validate( array_merge( $base, array( 'media_caption' => 'patient name: علی خان' ) ), 4, $settings );
	sabri_assert( $caption_name['valid'] && ! empty( $caption_name['data']['privacy_review_required'] ), 'Clinical Case media captions must trigger privacy review for patient-name labels regardless of case or script.' );

	$alt_full_name = ComposerValidation::validate( array_merge( $base, array( 'media_alt_text' => 'FULL NAME: سارہ احمد' ) ), 4, $settings );
	sabri_assert( $alt_full_name['valid'] && ! empty( $alt_full_name['data']['privacy_review_required'] ), 'Clinical Case media alt text must trigger privacy review for full-name labels regardless of case or script.' );

	$caption_phone = ComposerValidation::validate( array_merge( $base, array( 'media_caption' => 'Mobile: 03001234567' ) ), 4, $settings );
	sabri_assert( ! $caption_phone['valid'] && 'media_caption' === $caption_phone['errors'][0]['field'], 'Clinical Case media captions must reject deterministic identifiers without echoing values.' );
	sabri_assert( false === strpos( $caption_phone['errors'][0]['message'], '03001234567' ), 'Privacy errors must not echo deterministic identifier values.' );

	$ordinary = ComposerValidation::validate( array_merge( $base, array( 'clinical_case' => array( 'chief_complaints' => 'Intermittent headache after exertion without identifying details.' ) ) ), 4, $settings );
	sabri_assert( $ordinary['valid'] && empty( $ordinary['data']['privacy_review_required'] ), 'Ordinary clinical text must not be falsely rejected.' );

	$sabri_test_current_user_id = 2;
	$forced = Composer::create_or_update_from_request( array_merge( $base, array( 'composer_action' => 'publish', 'clinical_case' => array( 'case_title' => 'Patient Name: Ali Khan' ) ) ), array(), 2 );
	sabri_assert( ! empty( $forced['ok'] ) && 'pending' === $forced['status'], 'Ambiguous clinical name patterns must force publish attempts to pending privacy review.' );
}

function sabri_test_phase2_composer_write_and_edit_policy() {
	global $sabri_test_current_user_id, $sabri_test_current_caps;
	sabri_reset_options();
	Settings::ensure_defaults();
	$sabri_test_current_caps = array();

	$base_input = array(
		'content' => 'A real post body.',
		'feed_type' => 'standard-post',
		'visibility' => 'public',
		'medical_disclaimer_confirmed' => 1,
		'patient_privacy_confirmed' => 1,
	);

	$sabri_test_current_user_id = 4;
	$pending = Composer::create_or_update_from_request( array_merge( $base_input, array( 'composer_action' => 'submit' ) ), array(), 4 );
	sabri_assert( ! empty( $pending['ok'] ) && 'pending' === $pending['status'], 'Unverified doctor composer submit must save a pending post.' );
	sabri_assert( 'pending' === get_post_status( $pending['post_id'] ), 'Pending composer post must use WordPress pending status.' );

	$denied_publish = Composer::create_or_update_from_request( array_merge( $base_input, array( 'composer_action' => 'publish' ) ), array(), 4 );
	sabri_assert( empty( $denied_publish['ok'] ) && 'publish_denied' === $denied_publish['code'], 'Server-side composer must reject unauthorized publish escalation.' );

	$sabri_test_current_user_id = 5;
	$student = Composer::create_or_update_from_request( array_merge( $base_input, array( 'composer_action' => 'submit' ) ), array(), 5 );
	sabri_assert( empty( $student['ok'] ) && 'composer_denied' === $student['code'], 'Student composer writes must be denied server-side.' );

	$sabri_test_current_user_id = 2;
	$empty_browser_media = array(
		'name'     => array( '' ),
		'type'     => array( '' ),
		'tmp_name' => array( '' ),
		'error'    => array( UPLOAD_ERR_NO_FILE ),
		'size'     => array( 0 ),
	);
	$published = Composer::create_or_update_from_request( array_merge( $base_input, array( 'composer_action' => 'publish' ) ), $empty_browser_media, 2 );
	sabri_assert( ! empty( $published['ok'] ) && 'publish' === $published['status'], 'Founder composer publish must save a published post when the browser submits an empty optional media field.' );
	sabri_assert( array() === get_post_meta( $published['post_id'], PostMetadata::META_ATTACHMENTS, true ), 'Empty optional media fields must not create attachments or upload errors.' );

	$sabri_test_current_user_id = 4;
	sabri_assert( ComposerPermissions::user_can_edit_post( $pending['post_id'], 4 ), 'Authors may edit their own posts when permitted.' );
	$sabri_test_current_user_id = 3;
	sabri_assert( ! ComposerPermissions::user_can_edit_post( $pending['post_id'], 3 ), 'Users must not edit another user post without moderation capability.' );
	$sabri_test_current_user_id = 1;
	$sabri_test_current_caps = array( 'manage_options' => true );
	sabri_assert( ComposerPermissions::user_can_edit_post( $pending['post_id'], 1 ), 'Administrators may review/edit submitted content.' );
}

function sabri_test_phase2_review_state_allow_list_visibility() {
	global $sabri_test_current_user_id;
	sabri_reset_options();
	Settings::ensure_defaults();
	$sabri_test_current_user_id = 0;

	$approved = sabri_test_add_post( array( 'post_author' => 2, 'post_title' => 'Approved Visible' ), array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'clinical-case' ), array( 'sabri_feed_type' => array( 'clinical-case' ) ) );
	$pending = sabri_test_add_post( array( 'post_author' => 2, 'post_title' => 'Pending Hidden' ), array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'pending', PostMetadata::META_TYPE => 'clinical-case' ), array( 'sabri_feed_type' => array( 'clinical-case' ) ) );
	$unknown = sabri_test_add_post( array( 'post_author' => 2, 'post_title' => 'Unknown Hidden' ), array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'mystery', PostMetadata::META_TYPE => 'clinical-case' ), array( 'sabri_feed_type' => array( 'clinical-case' ) ) );
	$legacy = sabri_test_add_post( array( 'post_author' => 2, 'post_title' => 'Legacy Hidden' ), array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_TYPE => 'clinical-case' ), array( 'sabri_feed_type' => array( 'clinical-case' ) ) );

	sabri_assert( PostMetadata::user_can_view( $approved, 0 ), 'Approved published posts must remain visible.' );
	sabri_assert( ! PostMetadata::user_can_view( $legacy, 0 ), 'Legacy blank review-state posts must stay hidden unless an explicit migration policy is enabled.' );
	sabri_assert( ! PostMetadata::user_can_view( $pending, 0 ), 'Published pending posts must remain hidden.' );
	sabri_assert( ! PostMetadata::user_can_view( $unknown, 0 ), 'Published unknown review-state posts must remain hidden.' );

	$result = FeedQuery::query( array( 'mode' => 'clinical-cases', 'page' => 1, 'per_page' => 10 ) );
	$ids = array_map( static function ( $post ) { return (int) $post->ID; }, $result['posts'] );
	sabri_assert( in_array( $approved, $ids, true ), 'Feed query must include approved posts.' );
	sabri_assert( ! in_array( $legacy, $ids, true ) && ! in_array( $pending, $ids, true ) && ! in_array( $unknown, $ids, true ), 'Feed query must hide legacy blank, pending, and unknown review states by default.' );

	update_option( PostMetadata::LEGACY_BLANK_REVIEW_STATE_OPTION, true, false );
	FeedQuery::invalidate_cache();
	$result = FeedQuery::query( array( 'mode' => 'clinical-cases', 'page' => 1, 'per_page' => 10 ) );
	$ids = array_map( static function ( $post ) { return (int) $post->ID; }, $result['posts'] );
	sabri_assert( in_array( $approved, $ids, true ) && in_array( $legacy, $ids, true ), 'Explicit legacy migration policy must preserve approved posts and permit blank review-state posts.' );

	$rest = RestFeed::feed( array( 'mode' => 'clinical-cases', 'page' => 1, 'per_page' => 10 ) );
	$data = $rest instanceof WP_REST_Response ? $rest->get_data() : $rest;
	$html = $data['data']['html'];
	sabri_assert( false === strpos( $html, 'sabri-hnf-post-' . $pending ) && false === strpos( $html, 'Pending Hidden' ), 'REST feed must not expose published pending posts.' );
	sabri_assert( '' === \Sabri\HomeNewsFeed\FeedRenderer::render_card( $pending, Settings::get() ), 'Feed cards must not render restricted posts passed directly.' );

	$context = PostMetadata::render_single_context( $approved );
	sabri_assert( false === strpos( $context, 'Pending Hidden' ), 'Related posts must hide pending review-state posts.' );
	sabri_assert( ! PostMetadata::user_can_view( $pending, 0 ), 'Single-post visibility must block published pending posts before rendering.' );
}

function sabri_test_phase2_media_validation() {
	global $sabri_test_current_user_id, $sabri_test_current_caps;
	sabri_reset_options();
	Settings::ensure_defaults();
	$sabri_test_current_caps = array();
	$sabri_test_current_user_id = 4;

	$pdf = MediaHandler::validate_upload( array( 'name' => 'case.pdf', 'type' => 'application/pdf', 'size' => 1024, 'tmp_name' => 'case.pdf', 'error' => 0 ) );
	sabri_assert( ! empty( $pdf['valid'] ), 'Configured PDF upload must validate.' );
	$php = MediaHandler::validate_upload( array( 'name' => 'case.php', 'type' => 'application/x-php', 'size' => 1024, 'tmp_name' => 'case.php', 'error' => 0 ) );
	sabri_assert( empty( $php['valid'] ) && 'blocked_extension' === $php['code'], 'Executable-style upload extensions must be blocked.' );
	$large = MediaHandler::validate_upload( array( 'name' => 'large.pdf', 'type' => 'application/pdf', 'size' => 99 * 1024 * 1024, 'tmp_name' => 'large.pdf', 'error' => 0 ) );
	sabri_assert( empty( $large['valid'] ) && 'file_too_large' === $large['code'], 'File-size validation must enforce configured limits.' );

	$own_attachment = sabri_test_add_post( array( 'post_author' => 4, 'post_type' => 'attachment', 'post_mime_type' => 'application/pdf' ) );
	$other_attachment = sabri_test_add_post( array( 'post_author' => 3, 'post_type' => 'attachment', 'post_mime_type' => 'application/pdf' ) );
	sabri_assert( MediaHandler::validate_attachment_ownership( array( $own_attachment ), 4 ), 'Authors may attach their own media.' );
	sabri_assert( ! MediaHandler::validate_attachment_ownership( array( $other_attachment ), 4 ), 'Authors must not attach another user media without moderation capability.' );
}

function sabri_test_phase2_pending_media_visibility() {
	global $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_is_attachment, $sabri_test_current_post_id;
	sabri_reset_options();
	Settings::ensure_defaults();
	$sabri_test_current_user_id = 4;
	$sabri_test_current_caps = array();

	$created = Composer::create_or_update_from_request(
		array(
			'content' => 'A post with uploaded media.',
			'feed_type' => 'standard-post',
			'visibility' => 'public',
			'composer_action' => 'submit',
			'medical_disclaimer_confirmed' => 1,
			'patient_privacy_confirmed' => 1,
		),
		array( 'name' => 'case.pdf', 'type' => 'application/pdf', 'size' => 1024, 'tmp_name' => 'case.pdf', 'error' => 0 ),
		4
	);
	$attachment_id = get_post_meta( $created['post_id'], PostMetadata::META_ATTACHMENTS, true )[0];
	sabri_assert( ! empty( $created['ok'] ) && (int) get_post_field( 'post_parent', $attachment_id ) === (int) $created['post_id'], 'Uploaded attachments must be associated with the saved post.' );
	sabri_assert( ! MediaHandler::attachment_publicly_visible( $attachment_id, 0 ), 'Attachments for pending parent posts must not be publicly visible.' );

	foreach ( array( 'mystery', 'rejected', 'removed', 'archived', 'limited' ) as $state ) {
		$parent = sabri_test_add_post( array( 'post_author' => 2, 'post_status' => 'publish' ), array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => $state ) );
		$media = sabri_test_add_post( array( 'post_author' => 2, 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg', 'post_parent' => $parent ) );
		sabri_assert( ! MediaHandler::attachment_publicly_visible( $media, 0 ), 'Attachments must be hidden when parent review state is restricted: ' . $state );
	}

	$approved_parent = sabri_test_add_post( array( 'post_author' => 2, 'post_status' => 'publish' ), array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_ATTACHMENTS => array() ) );
	$approved_media = sabri_test_add_post( array( 'post_author' => 2, 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg', 'post_parent' => $approved_parent ) );
	update_post_meta( $approved_parent, PostMetadata::META_ATTACHMENTS, array( $approved_media, $attachment_id ) );
	sabri_assert( MediaHandler::visible_attachment_ids( array( $approved_media, $attachment_id ), 0 ) === array( $approved_media ), 'Plugin galleries must include only media from visible parent posts.' );
	sabri_assert( false !== strpos( FeedRenderer::render_card( $approved_parent, Settings::get() ), 'attachment-' . $approved_media ) && false === strpos( FeedRenderer::render_card( $approved_parent, Settings::get() ), 'attachment-' . $attachment_id ), 'Feed galleries must not expose hidden-parent media.' );

	$response = MediaHandler::filter_rest_attachment_response( new WP_REST_Response( array( 'id' => $attachment_id, 'source_url' => 'http://example.test/uploads/case.pdf' ), 200 ), get_post( $attachment_id ), null );
	$data = $response->get_data();
	sabri_assert( 'restricted' === $data['status'] && empty( $data['source_url'] ), 'Attachment REST output must be restricted for media whose parent post is not public.' );

	$sabri_test_is_attachment = true;
	$sabri_test_current_post_id = $attachment_id;
	$blocked = false;
	try {
		MediaHandler::enforce_attachment_visibility();
	} catch ( Exception $exception ) {
		$blocked = false !== strpos( $exception->getMessage(), 'unavailable' );
	}
	sabri_assert( $blocked, 'Public attachment pages must be blocked for media whose parent post is not public.' );
}

function sabri_test_phase2_meta_auth_idor_protection() {
	global $sabri_test_current_user_id, $sabri_test_current_caps;
	sabri_reset_options();
	Settings::ensure_defaults();
	$own = sabri_test_add_post( array( 'post_author' => 4 ), array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved' ) );
	$other = sabri_test_add_post( array( 'post_author' => 3 ), array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved' ) );

	$sabri_test_current_user_id = 4;
	$sabri_test_current_caps = array();
	sabri_assert( PostMetadata::meta_auth_callback( false, PostMetadata::META_VISIBILITY, $own, 4 ), 'Authors must be allowed to update their own plugin post metadata.' );
	sabri_assert( ! PostMetadata::meta_auth_callback( false, PostMetadata::META_VISIBILITY, $other, 4 ), 'Create permission must not allow mutation of another author metadata.' );
	sabri_assert( ! PostMetadata::meta_auth_callback( false, PostMetadata::META_REVIEW_STATE, $other, 4 ), 'Users must not mutate another author review state.' );
	sabri_assert( ! PostMetadata::meta_auth_callback( false, PostMetadata::META_ATTACHMENTS, $other, 4 ), 'Users must not mutate another author attachment metadata.' );
	sabri_assert( ! PostMetadata::meta_auth_callback( false, PostMetadata::META_CLINICAL_CASE, $other, 4 ), 'Users must not mutate another author Clinical Case metadata.' );
	sabri_assert( ! PostMetadata::meta_auth_callback( false, PostMetadata::META_RESEARCH, $other, 4 ), 'Users must not mutate another author Research metadata.' );

	$sabri_test_current_user_id = 1;
	$sabri_test_current_caps = array( 'sabri_feed_moderate_posts' => true );
	sabri_assert( PostMetadata::meta_auth_callback( false, PostMetadata::META_VISIBILITY, $other, 1 ), 'Moderators with documented capability may update plugin post metadata.' );

	$sabri_test_current_user_id = 4;
	$sabri_test_current_caps = array();
	sabri_assert( PostMetadata::meta_auth_callback( false, PostMetadata::META_VISIBILITY, 0, 4 ), 'Create permission may be used only for genuine new-post metadata context.' );
}

function sabri_test_phase2_composer_duplicate_render_guard() {
	global $sabri_test_current_user_id, $sabri_test_current_caps;
	sabri_reset_options();
	Settings::ensure_defaults();
	HomeIntegration::reset_runtime_guards();
	$sabri_test_current_user_id = 4;
	$sabri_test_current_caps = array();

	$first = Shortcodes::composer();
	$second = Shortcodes::composer();
	sabri_assert( false !== strpos( $first, 'data-sabri-composer' ), 'Composer shortcode must render the first form in a request.' );
	sabri_assert( '' === $second, 'Composer shortcode must not render duplicate forms in the same request.' );

	HomeIntegration::reset_runtime_guards();
	$next_request = Shortcodes::composer();
	sabri_assert( false !== strpos( $next_request, 'data-sabri-composer' ), 'Composer guard must reset for a later request.' );
}

function sabri_test_phase2_home_center_route_guard() {
	global $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_is_front_page, $sabri_test_is_home;
	global $sabri_test_current_post_id, $sabri_test_is_singular, $sabri_test_singular_post_type;

	sabri_reset_options();
	Settings::ensure_defaults();
	$sabri_test_current_user_id = 0;
	$sabri_test_current_caps = array();
	$feed_post_id = sabri_test_add_post(
		array( 'post_author' => 2, 'post_title' => 'Read More Target' ),
		array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' ),
		array( 'sabri_feed_type' => array( 'standard-post' ) )
	);
	$front_page_id = sabri_test_add_post(
		array( 'post_author' => 1, 'post_title' => 'Static Front Page', 'post_type' => 'page' )
	);
	update_option( 'show_on_front', 'page', false );
	update_option( 'page_on_front', $front_page_id, false );
	$sabri_test_current_post_id = $front_page_id;

	$sabri_test_is_front_page = false;
	$sabri_test_is_home = false;
	HomeIntegration::reset_runtime_guards();
	ob_start();
	HomeIntegration::render_home_center();
	$ordinary_output = ob_get_clean();
	sabri_assert( '' === $ordinary_output, 'Plugin-owned Home Feed hook must not replace ordinary pages.' );

	$sabri_test_is_front_page = true;
	HomeIntegration::reset_runtime_guards();
	ob_start();
	HomeIntegration::render_home_center();
	$front_output = ob_get_clean();
	$target_permalink = esc_url( get_permalink( $feed_post_id ) );
	sabri_assert( false !== strpos( $front_output, 'sabri-hnf-feed' ), 'Plugin-owned Home Feed hook must render on the configured static front page.' );
	sabri_assert( substr_count( $front_output, 'href="' . $target_permalink . '"' ) >= 2, 'Feed title and Read More must link to the selected post permalink.' );

	$sabri_test_current_post_id = $feed_post_id;
	$sabri_test_is_singular = true;
	$sabri_test_singular_post_type = 'post';
	$sabri_test_is_front_page = true;
	$sabri_test_is_home = false;
	HomeIntegration::reset_runtime_guards();
	ob_start();
	HomeIntegration::render_home_center();
	$single_hook_output = ob_get_clean();
	sabri_assert( '' === $single_hook_output, 'A single-post request must fail closed even if a theme or Shell reports it as the front page.' );

	HomeIntegration::reset_runtime_guards();
	$single_shortcode_output = Shortcodes::home_feed();
	sabri_assert( '' === $single_shortcode_output, 'Home Feed shortcode fallback must not replace single-post content.' );
	$single_content = HomeIntegration::append_single_post_context( 'Single post body.' );
	sabri_assert( 0 === strpos( $single_content, 'Single post body.' ) && false === strpos( $single_content, 'sabri-hnf-feed' ), 'Read More destination must preserve the single-post body instead of rendering Home Feed.' );

	$sabri_test_is_singular = false;
	$sabri_test_singular_post_type = '';
	$sabri_test_current_post_id = $front_page_id;
	$sabri_test_is_home = true;
	HomeIntegration::reset_runtime_guards();
	ob_start();
	HomeIntegration::render_home_center();
	$posts_index_output = ob_get_clean();
	sabri_assert( '' === $posts_index_output, 'Plugin-owned Home Feed hook must not replace the WordPress posts index.' );
}

function sabri_test_phase2_media_mime_fail_closed() {
	global $sabri_test_current_user_id, $sabri_test_filetype_override;
	sabri_reset_options();
	Settings::ensure_defaults();
	$sabri_test_current_user_id = 4;

	$spoofed = MediaHandler::validate_upload( array( 'name' => 'case.jpg', 'type' => 'application/pdf', 'size' => 1024, 'tmp_name' => 'case.jpg', 'error' => 0 ) );
	sabri_assert( empty( $spoofed['valid'] ) && 'mime_mismatch' === $spoofed['code'], 'Spoofed client MIME must fail closed.' );

	$sabri_test_filetype_override = array( 'ext' => '', 'type' => 'image/jpeg' );
	$empty_ext = MediaHandler::validate_upload( array( 'name' => 'case.jpg', 'type' => 'image/jpeg', 'size' => 1024, 'tmp_name' => 'case.jpg', 'error' => 0 ) );
	sabri_assert( empty( $empty_ext['valid'] ) && 'extension_not_detected' === $empty_ext['code'], 'Empty detected extension must fail closed.' );

	$sabri_test_filetype_override = array( 'ext' => 'jpg', 'type' => '' );
	$empty_mime = MediaHandler::validate_upload( array( 'name' => 'case.jpg', 'type' => 'image/jpeg', 'size' => 1024, 'tmp_name' => 'case.jpg', 'error' => 0 ) );
	sabri_assert( empty( $empty_mime['valid'] ) && 'mime_not_detected' === $empty_mime['code'], 'Empty detected MIME must fail closed.' );

	$sabri_test_filetype_override = array( 'ext' => 'png', 'type' => 'image/png' );
	$ext_mismatch = MediaHandler::validate_upload( array( 'name' => 'case.jpg', 'type' => 'image/png', 'size' => 1024, 'tmp_name' => 'case.jpg', 'error' => 0 ) );
	sabri_assert( empty( $ext_mismatch['valid'] ) && 'extension_mismatch' === $ext_mismatch['code'], 'Detected extension mismatch must fail closed.' );

	$sabri_test_filetype_override = null;
	$jpg = MediaHandler::validate_upload( array( 'name' => 'case.jpg', 'type' => 'image/jpeg', 'size' => 1024, 'tmp_name' => 'case.jpg', 'error' => 0 ) );
	$webp = MediaHandler::validate_upload( array( 'name' => 'case.webp', 'type' => 'image/webp', 'size' => 1024, 'tmp_name' => 'case.webp', 'error' => 0 ) );
	$pdf = MediaHandler::validate_upload( array( 'name' => 'case.pdf', 'type' => 'application/pdf', 'size' => 1024, 'tmp_name' => 'case.pdf', 'error' => 0 ) );
	sabri_assert( ! empty( $jpg['valid'] ) && ! empty( $webp['valid'] ) && ! empty( $pdf['valid'] ), 'Valid JPG, WebP, and PDF uploads must still validate.' );

	$sabri_test_filetype_override = array( 'ext' => 'php', 'type' => 'image/jpeg' );
	$renamed = MediaHandler::validate_upload( array( 'name' => 'payload.jpg', 'type' => 'image/jpeg', 'size' => 1024, 'tmp_name' => 'payload.jpg', 'error' => 0 ) );
	sabri_assert( empty( $renamed['valid'] ) && 'extension_mismatch' === $renamed['code'], 'Executable content renamed as an image must fail closed.' );
}

function sabri_test_phase2_research_doi_source_validation() {
	sabri_reset_options();
	Settings::ensure_defaults();
	$settings = Settings::get();
	$base = array( 'content' => 'Research content.', 'feed_type' => 'research', 'medical_disclaimer_confirmed' => 1, 'patient_privacy_confirmed' => 1 );

	$doi = ComposerValidation::validate( array_merge( $base, array( 'research' => array( 'evidence_level' => 'case-report', 'doi_source_url' => '10.1234/example.2026' ) ) ), 3, $settings );
	sabri_assert( $doi['valid'] && '10.1234/example.2026' === $doi['data']['research']['doi_source_url'], 'Valid DOI values must be accepted and stored normalized.' );

	$doi_url = ComposerValidation::validate( array_merge( $base, array( 'research' => array( 'evidence_level' => 'case-report', 'doi_source_url' => 'https://doi.org/10.1234/example' ) ) ), 3, $settings );
	$source = ComposerValidation::validate( array_merge( $base, array( 'research' => array( 'evidence_level' => 'case-report', 'doi_source_url' => 'https://example.org/source' ) ) ), 3, $settings );
	sabri_assert( $doi_url['valid'] && $source['valid'], 'Valid DOI URLs and safe HTTPS source URLs must be accepted.' );

	foreach ( array( '10.bad', 'javascript:alert(1)', 'data:text/plain,hello', 'plain invalid text' ) as $value ) {
		$result = ComposerValidation::validate( array_merge( $base, array( 'research' => array( 'evidence_level' => 'case-report', 'doi_source_url' => $value ) ) ), 3, $settings );
		sabri_assert( ! $result['valid'] && in_array( 'invalid_doi_source_url', array_column( $result['errors'], 'code' ), true ), 'Invalid DOI/source value must be rejected: ' . $value );
	}
}

function sabri_test_phase2_duplicate_rest_safe_mode_and_cache() {
	global $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_rest_routes, $sabri_test_transients;
	sabri_reset_options();
	Plugin::instance()->register();
	Settings::ensure_defaults();
	$sabri_test_current_user_id = 0;
	$sabri_test_current_caps = array();
	sabri_test_add_post( array( 'post_author' => 2, 'post_title' => 'Visible' ), array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' ), array( 'sabri_feed_type' => array( 'standard-post' ) ) );

	HomeIntegration::reset_runtime_guards();
	$first = \Sabri\HomeNewsFeed\Shortcodes::home_feed( array( 'mode' => 'latest' ) );
	$second = \Sabri\HomeNewsFeed\Shortcodes::home_feed( array( 'mode' => 'latest' ) );
	sabri_assert( '' !== $first, 'Home Feed shortcode must render on first use.' );
	sabri_assert( '' === $second, 'Home Feed shortcode must not duplicate output when rendered twice in one request.' );
	sabri_assert( false === stripos( $first, 'likes' ) && false === stripos( $first, 'comments' ) && false === stripos( $first, 'saves' ), 'Phase 2 feed must not show fake Phase 3 social action controls.' );

	RestFeed::register_routes();
	RestComposer::register_routes();
	foreach ( array( 'sabri-home-news-feed/v1/feed', 'sabri-home-news-feed/v1/composer/draft', 'sabri-home-news-feed/v1/composer/preview', 'sabri-home-news-feed/v1/composer/submit', 'sabri-home-news-feed/v1/composer/publish', 'sabri-home-news-feed/v1/composer/schedule' ) as $route ) {
		sabri_assert( isset( $sabri_test_rest_routes[ $route ] ) && is_callable( $sabri_test_rest_routes[ $route ]['permission_callback'] ), 'REST route must have a callable permission callback: ' . $route );
	}

	$_SERVER['HTTP_X_WP_NONCE'] = 'rest-nonce';
	$sabri_test_current_user_id = 4;
	sabri_assert( RestComposer::permission_callback(), 'Composer REST writes must allow authenticated authorized users with a nonce.' );
	unset( $_SERVER['HTTP_X_WP_NONCE'] );
	sabri_assert( ! RestComposer::permission_callback(), 'Composer REST writes must reject missing nonce.' );

	$before = FeedQuery::query( array( 'mode' => 'latest', 'page' => 1, 'per_page' => 1 ) );
	sabri_assert( ! empty( $sabri_test_transients ), 'Feed query must cache bounded query results.' );
	$version = get_option( FeedQuery::CACHE_VERSION_OPTION, 1 );
	FeedQuery::invalidate_cache();
	sabri_assert( $version + 1 === get_option( FeedQuery::CACHE_VERSION_OPTION, 1 ), 'Feed cache invalidation must bump cache version.' );

	SafeMode::set_emergency_disabled( true );
	$disabled = FeedQuery::query( array( 'mode' => 'latest', 'page' => 1, 'per_page' => 1 ) );
	sabri_assert( 'disabled' === $disabled['status'], 'Emergency Disable must stop custom feed runtime.' );
	sabri_assert( ! ComposerPermissions::user_can_create( 4 ), 'Emergency Disable must stop composer runtime.' );
	unset( $before );
}

function sabri_test_security_state_isolated_after_phase2_mutations() {
	global $sabri_test_current_caps, $sabri_test_current_user_id;

	sabri_test_phase2_composer_permissions_and_statuses();
	sabri_test_phase2_duplicate_rest_safe_mode_and_cache();

	sabri_test_reset_state( true );
	sabri_reset_roles();
	Settings::ensure_defaults();
	$_GET['sabri_feed_safe'] = '1';
	$sabri_test_current_user_id = 0;
	$sabri_test_current_caps = array( 'manage_options' => true, 'sabri_feed_manage_settings' => true );
	sabri_assert( ! SafeMode::query_safe_mode(), 'Safe Mode must stay denied for logged-out users after Phase 2 permission tests run first.' );
	sabri_assert( ! RestFoundation::permission_callback(), 'REST diagnostics must stay denied for logged-out users after feed/composer REST tests run first.' );

	$sabri_test_current_user_id = 1;
	$sabri_test_current_caps = array( 'manage_options' => true, 'sabri_feed_manage_settings' => true );
	sabri_assert( SafeMode::query_safe_mode(), 'Safe Mode administrator access must still work after Phase 2 permission tests run first.' );
	sabri_assert( RestFoundation::permission_callback(), 'REST diagnostics administrator access must still work after feed/composer REST tests run first.' );
}

function sabri_test_phase2_rest_route_validation_callbacks() {
	global $sabri_test_rest_routes;
	sabri_reset_options();
	Settings::ensure_defaults();

	RestComposer::register_routes();
	$composer_route = 'sabri-home-news-feed/v1/composer/draft';
	sabri_assert( sabri_rest_arg_valid( $composer_route, 'post_id', 10 ), 'Composer REST post_id validator must allow positive IDs.' );
	sabri_assert( ! sabri_rest_arg_valid( $composer_route, 'post_id', -1 ), 'Composer REST post_id validator must reject invalid IDs before mutation.' );
	sabri_assert( ! sabri_rest_arg_valid( $composer_route, 'action', 'delete' ), 'Composer REST action validator must reject unsupported actions.' );
	sabri_assert( ! sabri_rest_arg_valid( $composer_route, 'visibility', 'followers' ), 'Composer REST visibility validator must reject deferred visibility.' );
	sabri_assert( ! sabri_rest_arg_valid( $composer_route, 'post_status', 'trash' ), 'Composer REST post status validator must reject unsupported status values.' );
	sabri_assert( ! sabri_rest_arg_valid( $composer_route, 'attachments', array( 1, 1 ) ), 'Composer REST attachment validator must reject duplicate IDs.' );
	sabri_assert( ! sabri_rest_arg_valid( $composer_route, 'clinical_case', array( 'patient_full_name' => 'Blocked' ) ), 'Composer REST Clinical Case validator must reject unsupported structured fields.' );
	sabri_assert( ! sabri_rest_arg_valid( $composer_route, 'research', array( 'unsupported' => 'Blocked' ) ), 'Composer REST Research validator must reject unsupported structured fields.' );
	sabri_assert( sabri_rest_arg_valid( $composer_route, 'scheduled_date', gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) ), 'Composer REST scheduled date validator must allow accepted date/time format.' );
	sabri_assert( sabri_rest_arg_valid( $composer_route, 'scheduled_date', gmdate( 'Y-m-d\TH:i', time() + DAY_IN_SECONDS ) ), 'Composer REST scheduled date validator must allow date-time-local format.' );
	sabri_assert( ! sabri_rest_arg_valid( $composer_route, 'scheduled_date', 'tomorrow' ), 'Composer REST scheduled date validator must reject natural-language dates.' );
	sabri_assert( ! sabri_rest_arg_valid( $composer_route, 'scheduled_date', '2026-02-30 12:00:00' ), 'Composer REST scheduled date validator must reject invalid calendar dates.' );
	sabri_assert( 0 === RestComposer::sanitize_bool( false ) && 0 === RestComposer::sanitize_bool( 'false' ) && 0 === RestComposer::sanitize_bool( 0 ) && 0 === RestComposer::sanitize_bool( '0' ), 'Composer REST booleans must map false-like values to 0.' );
	sabri_assert( 1 === RestComposer::sanitize_bool( true ) && 1 === RestComposer::sanitize_bool( 'true' ) && 1 === RestComposer::sanitize_bool( 1 ) && 1 === RestComposer::sanitize_bool( '1' ), 'Composer REST booleans must map true-like values to 1.' );
	sabri_assert( 0 === sabri_rest_arg_sanitized( $composer_route, 'patient_privacy_confirmed', 'false' ), 'Composer REST patient_privacy_confirmed=false must sanitize to 0.' );

	RestFeed::register_routes();
	$feed_route = 'sabri-home-news-feed/v1/feed';
	sabri_assert( sabri_rest_arg_valid( $feed_route, 'mode', 'latest' ), 'Feed REST mode validator must allow enabled modes.' );
	sabri_assert( ! sabri_rest_arg_valid( $feed_route, 'mode', 'unsupported-mode' ), 'Feed REST mode validator must reject malformed modes.' );
	sabri_assert( sabri_rest_arg_valid( $feed_route, 'page', 1 ), 'Feed REST page validator must allow positive pages.' );
	sabri_assert( ! sabri_rest_arg_valid( $feed_route, 'page', 0 ), 'Feed REST page validator must reject non-positive pages.' );
	sabri_assert( ! sabri_rest_arg_valid( $feed_route, 'per_page', 99 ), 'Feed REST per_page validator must reject values beyond configured maximum.' );
}

function sabri_test_phase2_rest_nonce_fail_closed() {
	global $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_filter_overrides;
	sabri_reset_options();
	Settings::ensure_defaults();
	$sabri_test_current_user_id = 4;
	$sabri_test_current_caps = array();

	$_SERVER['HTTP_X_WP_NONCE'] = 'rest-nonce';
	$sabri_test_filter_overrides['sabri_hnf_rest_nonce_helpers_available'] = false;
	sabri_assert( ! RestComposer::permission_callback(), 'Composer REST nonce validation must fail closed when nonce helpers are unavailable.' );

	$sabri_test_filter_overrides = array();
	unset( $_SERVER['HTTP_X_WP_NONCE'] );
	sabri_assert( ! RestComposer::permission_callback(), 'Composer REST nonce validation must reject absent nonce.' );

	$_SERVER['HTTP_X_WP_NONCE'] = 'invalid';
	sabri_assert( ! RestComposer::permission_callback(), 'Composer REST nonce validation must reject invalid nonce.' );

	$_SERVER['HTTP_X_WP_NONCE'] = 'rest-nonce';
	sabri_assert( RestComposer::permission_callback(), 'Composer REST nonce validation must preserve valid authenticated nonce behavior.' );
}

function sabri_test_phase2_shell_status_accuracy() {
	sabri_reset_options();
	Settings::ensure_defaults();

	$rows = HomeIntegration::append_shell_report( array() );
	sabri_assert( 'Unknown' === $rows[0]['status'], 'Shell diagnostics must not report Connected without a confirmed runtime signal.' );

	$settings = Settings::get();
	$settings['integrations']['composer_page_url'] = 'https://example.test/composer';
	update_option( Settings::OPTION_NAME, $settings, false );
	$rows = HomeIntegration::append_shell_report( array() );
	sabri_assert( 'Configured' === $rows[0]['status'], 'Shell diagnostics must report Configured when URL settings exist but no runtime signal is loaded.' );

	SafeMode::set_emergency_disabled( true );
	$rows = HomeIntegration::append_shell_report( array() );
	sabri_assert( 'Disabled' === $rows[0]['status'], 'Shell diagnostics must report Disabled when public features are disabled.' );
}

function sabri_test_privacy_exporter_payload_structure() {
	global $sabri_test_rows;
	sabri_reset_options();
	$tables = Database::table_names();
	$user_id = 42;
	$sabri_test_rows[ $tables['saves'] ] = array();
	for ( $i = 1; $i <= 51; $i++ ) {
		$sabri_test_rows[ $tables['saves'] ][] = array(
			'id'             => $i,
			'user_id'        => $user_id,
			'post_id'        => 1000 + $i,
			'collection_key' => 'default',
			'status'         => 'active',
			'created_at'     => '2026-07-18 00:00:00',
		);
	}
	$sabri_test_rows[ $tables['follows'] ] = array(
		array(
			'id'               => 71,
			'follower_user_id' => $user_id,
			'target_user_id'   => 99,
			'target_type'      => 'user',
			'status'           => 'active',
			'created_at'       => '2026-07-18 00:00:00',
		),
	);
	$sabri_test_rows[ $tables['reports'] ] = array(
		array(
			'id'               => 81,
			'reporter_user_id' => $user_id,
			'object_type'      => 'post',
			'object_id'        => 123,
			'reason'           => 'spam',
			'status'           => 'open',
			'notes'            => 'confidential moderator note',
			'duplicate_hash'   => 'raw-secret-hash',
			'created_at'       => '2026-07-18 00:00:00',
		),
	);

	$page_one = DataRetention::exporter( 'user@example.com', 1 );
	sabri_assert( 50 === count( $page_one['data'] ), 'Privacy exporter page one must return the page size.' );
	sabri_assert( false === $page_one['done'], 'Privacy exporter page one must not be done when more rows exist.' );

	foreach ( $page_one['data'] as $item ) {
		sabri_assert( isset( $item['group_id'], $item['group_label'], $item['item_id'], $item['data'] ), 'Each export item must include group_id, group_label, item_id, and data.' );
		foreach ( $item['data'] as $entry ) {
			sabri_assert( isset( $entry['name'], $entry['value'] ), 'Each export data entry must be a flat name/value pair.' );
			sabri_assert( is_string( $entry['name'] ) && is_string( $entry['value'] ), 'Export entry name/value must be strings.' );
		}
	}

	$page_two = DataRetention::exporter( 'user@example.com', 2 );
	sabri_assert( true === $page_two['done'], 'Privacy exporter page two must be done for the test data set.' );
	$json = wp_json_encode( $page_two );
	sabri_assert( false === strpos( $json, 'confidential moderator note' ), 'Privacy exporter must not expose confidential moderation notes.' );
	sabri_assert( false === strpos( $json, 'raw-secret-hash' ), 'Privacy exporter must not expose duplicate hashes or raw internal secrets.' );
	sabri_assert( false === strpos( $json, 'target_user_id' ), 'Privacy exporter must not expose other users private raw column names.' );
}

function sabri_test_uninstall_capability_cleanup() {
	global $sabri_test_roles;
	sabri_reset_options();
	sabri_reset_roles();
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		define( 'WP_UNINSTALL_PLUGIN', 'sabri-complete-home-news-feed/sabri-complete-home-news-feed.php' );
	}

	foreach ( $sabri_test_roles as $role ) {
		$role->add_cap( 'sabri_feed_manage_settings' );
		$role->add_cap( 'sabri_feed_publish_posts' );
		$role->add_cap( 'unrelated_companion_capability' );
	}

	update_option( Settings::OPTION_NAME, Settings::defaults(), false );
	include dirname( __DIR__ ) . '/uninstall.php';

	foreach ( $sabri_test_roles as $role_slug => $role ) {
		sabri_assert( empty( $role->capabilities['sabri_feed_manage_settings'] ), 'Uninstall must remove plugin manage capability from ' . $role_slug . ' when retention is on.' );
		sabri_assert( empty( $role->capabilities['sabri_feed_publish_posts'] ), 'Uninstall must remove plugin publish capability from ' . $role_slug . ' when retention is on.' );
		sabri_assert( ! empty( $role->capabilities['unrelated_companion_capability'] ), 'Uninstall must preserve unrelated capabilities when retention is on.' );
	}
	sabri_assert( is_array( get_option( Settings::OPTION_NAME ) ), 'Retention-on uninstall must preserve plugin data.' );

	sabri_reset_options();
	sabri_reset_roles();
	foreach ( $sabri_test_roles as $role ) {
		$role->add_cap( 'sabri_feed_run_rollbacks' );
		$role->add_cap( 'read' );
	}
	$settings = Settings::defaults();
	$settings['privacy']['retain_data_on_uninstall'] = 0;
	update_option( Settings::OPTION_NAME, $settings, false );
	include dirname( __DIR__ ) . '/uninstall.php';

	foreach ( $sabri_test_roles as $role_slug => $role ) {
		sabri_assert( empty( $role->capabilities['sabri_feed_run_rollbacks'] ), 'Uninstall must remove plugin rollback capability from ' . $role_slug . ' when retention is off.' );
		sabri_assert( ! empty( $role->capabilities['read'] ), 'Uninstall must preserve unrelated WordPress capabilities when retention is off.' );
	}
	sabri_assert( false === get_option( Settings::OPTION_NAME, false ), 'Retention-off uninstall may remove plugin options.' );
}

function sabri_test_taxonomies() {
	$terms = Taxonomies::feed_type_terms();
	sabri_assert( 22 === count( $terms ), 'Default feed types must include the required 22 terms.' );
	sabri_assert( in_array( 'Islamic Spiritual Healing', $terms, true ), 'Required Islamic Spiritual Healing feed type missing.' );
}

function sabri_test_static_safety() {
	$root = dirname( __DIR__ );
	$rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	$forbidden = array( 'ev' . 'al(', 'base64_' . 'decode(', 'shell_' . 'ex' . 'ec(', 'ex' . 'ec(', 'passthru' . '(', 'system' . '(' );
	$remote = array( 'cd' . 'n.', 'fonts.google' . 'apis.com', 'fonts.g' . 'static.com', 'un' . 'pkg.com', 'jsdelivr' . '.net' );
	foreach ( $rii as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		$path = $file->getPathname();
		if ( preg_match( '#[\\\\/](\.git|release)[\\\\/]#', $path ) ) {
			continue;
		}
		$contents = file_get_contents( $path );
		foreach ( $forbidden as $needle ) {
			sabri_assert( false === strpos( $contents, $needle ), 'Forbidden PHP function found: ' . $needle . ' in ' . $path );
		}
		foreach ( $remote as $needle ) {
			sabri_assert( false === strpos( $contents, $needle ), 'Remote CDN/font dependency found: ' . $needle . ' in ' . $path );
		}
	}
}

function sabri_test_documentation_consistency() {
	$root = dirname( __DIR__ );
	foreach ( array( 'README.md', 'readme.txt', 'CHANGELOG.md' ) as $file ) {
		$contents = file_get_contents( $root . '/' . $file );
		sabri_assert( false !== strpos( $contents, '1.0.0' ), $file . ' must reference version 1.0.0.' );
	}
}


function sabri_test_phase2_full_audit_hardening() {
	global $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_users_by_id;
	global $sabri_test_insert_post_error, $sabri_test_insert_attachment_error, $sabri_test_deleted_attachments, $sabri_test_deleted_files;

	sabri_reset_options();
	Settings::ensure_defaults();
	$settings = Settings::get();
	$clinical_base = array(
		'content' => 'A de-identified clinical summary.',
		'feed_type' => 'clinical-case',
		'visibility' => 'public',
	);

	$false_flags = ComposerValidation::validate(
		array_merge(
			$clinical_base,
			array(
				'comments_enabled' => 'false',
				'medical_disclaimer_confirmed' => 'false',
				'patient_privacy_confirmed' => 'false',
			)
		),
		4,
		$settings
	);
	$false_codes = array_column( $false_flags['errors'], 'code' );
	sabri_assert( 0 === $false_flags['data']['comments_enabled'] && 0 === $false_flags['data']['medical_disclaimer_confirmed'] && 0 === $false_flags['data']['patient_privacy_confirmed'], 'String false values must remain false in direct composer validation.' );
	sabri_assert( in_array( 'medical_disclaimer_required', $false_codes, true ) && in_array( 'patient_privacy_required', $false_codes, true ), 'String false confirmations must not bypass Clinical Case requirements.' );

	$invalid_controls = ComposerValidation::validate(
		array(
			'content' => 'Invalid controls.',
			'feed_type' => 'future-secret-type',
			'visibility' => 'future-secret-scope',
		),
		4,
		$settings
	);
	$invalid_codes = array_column( $invalid_controls['errors'], 'code' );
	sabri_assert( in_array( 'invalid_feed_type', $invalid_codes, true ) && in_array( 'invalid_visibility', $invalid_codes, true ), 'Unknown type and visibility values must fail closed instead of widening to public defaults.' );

	$long_field = ComposerValidation::validate(
		array(
			'content' => str_repeat( 'a', 20001 ),
			'feed_type' => 'standard-post',
			'visibility' => 'public',
		),
		4,
		$settings
	);
	sabri_assert( in_array( 'field_too_long', array_column( $long_field['errors'], 'code' ), true ), 'Composer validation must reject oversized text fields.' );

	$disabled_comments = $settings;
	$disabled_comments['composer']['comments_metadata_enabled'] = 0;
	$comment_result = ComposerValidation::validate(
		array(
			'content' => 'Comments setting test.',
			'feed_type' => 'standard-post',
			'visibility' => 'public',
			'comments_enabled' => '1',
		),
		4,
		$disabled_comments
	);
	sabri_assert( 0 === $comment_result['data']['comments_enabled'], 'Disabled comments metadata setting must override submitted checkbox values.' );

	$privacy_media = sabri_test_add_post(
		array(
			'post_author' => 4,
			'post_type' => 'attachment',
			'post_mime_type' => 'image/jpeg',
			'post_excerpt' => 'Mobile: 03001234567',
		)
	);
	$privacy_media_result = ComposerValidation::validate(
		array_merge(
			$clinical_base,
			array(
				'attachments' => array( $privacy_media ),
				'medical_disclaimer_confirmed' => 1,
				'patient_privacy_confirmed' => 1,
			)
		),
		4,
		$settings
	);
	sabri_assert( in_array( 'forbidden_patient_identifier', array_column( $privacy_media_result['errors'], 'code' ), true ), 'Existing Clinical Case attachment captions and alt text must be privacy scanned.' );

	$other_parent = sabri_test_add_post( array( 'post_author' => 4, 'post_status' => 'draft' ) );
	$parented_media = sabri_test_add_post( array( 'post_author' => 4, 'post_type' => 'attachment', 'post_parent' => $other_parent ) );
	sabri_assert( ! MediaHandler::validate_attachment_ownership( array( $parented_media ), 4, 0 ), 'Media already attached to another post must not be silently reparented.' );
	sabri_assert( MediaHandler::validate_attachment_ownership( array( $parented_media ), 4, $other_parent ), 'Media may remain associated with its current edited post.' );

	$pending_media = sabri_test_add_post(
		array( 'post_author' => 4, 'post_type' => 'attachment', 'post_parent' => 0 ),
		array( MediaHandler::META_COMPOSER_PENDING => 1 )
	);
	sabri_assert( ! MediaHandler::attachment_publicly_visible( $pending_media, 0 ), 'Parentless composer media must stay private until post association succeeds.' );

	sabri_reset_options();
	Settings::ensure_defaults();
	$settings = Settings::get();
	$settings['feed']['allowed_types'] = array( 'standard-post' );
	update_option( Settings::OPTION_NAME, $settings, false );
	$standard = sabri_test_add_post(
		array( 'post_author' => 2, 'post_title' => 'Allowed Standard' ),
		array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' ),
		array( 'sabri_feed_type' => array( 'standard-post' ) )
	);
	$blocked_clinical = sabri_test_add_post(
		array( 'post_author' => 2, 'post_title' => 'Blocked Clinical' ),
		array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'clinical-case' ),
		array( 'sabri_feed_type' => array( 'clinical-case' ) )
	);
	$latest = FeedQuery::query( array( 'mode' => 'latest', 'page' => 1, 'per_page' => 10 ) );
	$latest_ids = array_map( static function ( $post ) { return (int) $post->ID; }, $latest['posts'] );
	sabri_assert( in_array( $standard, $latest_ids, true ) && ! in_array( $blocked_clinical, $latest_ids, true ), 'Feed allowed_types must filter broad feed modes.' );
	$clinical_feed = FeedQuery::query( array( 'mode' => 'clinical-cases', 'page' => 1, 'per_page' => 10 ) );
	sabri_assert( empty( $clinical_feed['posts'] ), 'A disabled feed type must remain empty even when its filter mode is requested.' );

	$old_display_name = $sabri_test_users_by_id[2]['display_name'];
	$sabri_test_users_by_id[2]['display_name'] = 'founder@example.com';
	$card = FeedRenderer::render_card( $standard, Settings::get() );
	$sabri_test_users_by_id[2]['display_name'] = $old_display_name;
	sabri_assert( false === strpos( $card, 'founder@example.com' ) && false !== strpos( $card, 'Sabri member' ), 'Email-shaped display names must not leak into public feed cards.' );

	$settings = Settings::get();
	$settings['general']['enabled'] = 0;
	update_option( Settings::OPTION_NAME, $settings, false );
	sabri_assert( ! SafeMode::feature_enabled( 'feed' ) && ! SafeMode::feature_enabled( 'composer' ), 'General master disable must gate feed and composer features.' );

	sabri_reset_options();
	Settings::ensure_defaults();
	$sabri_test_current_user_id = 1;
	$sabri_test_current_caps = array( 'manage_options' => true );
	$target = sabri_test_add_post( array( 'post_author' => 4, 'post_status' => 'pending', 'post_title' => 'Original Author' ) );
	$edited = Composer::create_or_update_from_request(
		array(
			'post_id' => $target,
			'content' => 'Moderated content.',
			'feed_type' => 'standard-post',
			'visibility' => 'public',
			'composer_action' => 'publish',
		),
		array(),
		1
	);
	sabri_assert( ! empty( $edited['ok'] ) && 4 === (int) get_post_field( 'post_author', $target ), 'Moderator edits must preserve the original post author.' );

	sabri_reset_options();
	Settings::ensure_defaults();
	$sabri_test_current_user_id = 4;
	$sabri_test_current_caps = array();
	$sabri_test_insert_post_error = true;
	$failed = Composer::create_or_update_from_request(
		array(
			'content' => 'Save failure cleanup.',
			'feed_type' => 'standard-post',
			'visibility' => 'public',
			'composer_action' => 'submit',
		),
		array( 'name' => 'cleanup.pdf', 'type' => 'application/pdf', 'size' => 1024, 'tmp_name' => 'cleanup.pdf', 'error' => 0 ),
		4
	);
	$sabri_test_insert_post_error = false;
	sabri_assert( empty( $failed['ok'] ) && 'save_failed' === $failed['code'] && 1 === count( $sabri_test_deleted_attachments ), 'Failed post saves must delete media created by that request.' );

	sabri_test_reset_state();
	$sabri_test_current_user_id = 4;
	$sabri_test_insert_attachment_error = true;
	$attachment_failure = MediaHandler::upload_files(
		array( 'name' => 'unregistered.pdf', 'type' => 'application/pdf', 'size' => 1024, 'tmp_name' => 'unregistered.pdf', 'error' => 0 ),
		4
	);
	$sabri_test_insert_attachment_error = false;
	sabri_assert( 'attachment_create_failed' === $attachment_failure['errors'][0]['code'] && in_array( 'unregistered.pdf', $sabri_test_deleted_files, true ), 'Attachment registration failures must remove the uploaded physical file.' );

	$template = file_get_contents( SABRI_HNF_PATH . 'templates/composer.php' );
	$script = file_get_contents( SABRI_HNF_PATH . 'assets/js/composer.js' );
	sabri_assert( false !== strpos( $template, 'data-sabri-medical-confirmation' ) && false !== strpos( $template, 'data-sabri-patient-confirmation' ), 'Composer confirmation controls must be type-aware.' );
	sabri_assert( false === strpos( $template, 'name="medical_disclaimer_confirmed" value="1" required' ) && false !== strpos( $script, 'updateConfirmations' ), 'Composer UI must not require medical confirmations for unrelated standard posts.' );
}

$tests = array(
	'sabri_test_identity',
	'sabri_test_bootstrap_no_wrappers',
	'sabri_test_admin_staging_preview_assets',
	'sabri_test_activation_snapshot_order',
	'sabri_test_schema_install_failures_do_not_advance_version',
	'sabri_test_database_schema',
	'sabri_test_settings_isolation',
	'sabri_test_integration_function_settings_preservation',
	'sabri_test_capability_policy',
	'sabri_test_safe_mode_and_emergency',
	'sabri_test_safe_mode_security_matrix',
	'sabri_test_rollback_and_repair_boundaries',
	'sabri_test_rest_permissions',
	'sabri_test_rest_diagnostics_security_matrix',
	'sabri_test_phase2_feed_context_and_query',
	'sabri_test_phase2_visibility_rules',
	'sabri_test_phase2_composer_permissions_and_statuses',
	'sabri_test_phase2_composer_validation_structures',
	'sabri_test_phase2_clinical_identifier_protection',
	'sabri_test_phase2_composer_write_and_edit_policy',
	'sabri_test_phase2_review_state_allow_list_visibility',
	'sabri_test_phase2_media_validation',
	'sabri_test_phase2_pending_media_visibility',
	'sabri_test_phase2_meta_auth_idor_protection',
	'sabri_test_phase2_composer_duplicate_render_guard',
	'sabri_test_phase2_home_center_route_guard',
	'sabri_test_phase2_full_audit_hardening',
	'sabri_test_phase2_media_mime_fail_closed',
	'sabri_test_phase2_research_doi_source_validation',
	'sabri_test_phase2_duplicate_rest_safe_mode_and_cache',
	'sabri_test_security_state_isolated_after_phase2_mutations',
	'sabri_test_phase2_rest_route_validation_callbacks',
	'sabri_test_phase2_rest_nonce_fail_closed',
	'sabri_test_phase2_shell_status_accuracy',
	'sabri_test_privacy_exporter_payload_structure',
	'sabri_test_uninstall_capability_cleanup',
	'sabri_test_taxonomies',
	'sabri_test_static_safety',
	'sabri_test_documentation_consistency',
);

foreach ( $tests as $test ) {
	sabri_test_reset_state();
	try {
		$test();
	} finally {
		sabri_test_reset_state();
	}
}

if ( $failures ) {
	echo "FAILED\n";
	foreach ( $failures as $failure ) {
		echo '- ' . $failure . "\n";
	}
	exit( 1 );
}

echo 'OK - ' . count( $tests ) . " behavior test groups passed.\n";
