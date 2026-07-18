<?php
/**
 * Behavior test runner.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\Activator;
use Sabri\HomeNewsFeed\Capabilities;
use Sabri\HomeNewsFeed\Database;
use Sabri\HomeNewsFeed\DataRetention;
use Sabri\HomeNewsFeed\Migrations;
use Sabri\HomeNewsFeed\Plugin;
use Sabri\HomeNewsFeed\Repair;
use Sabri\HomeNewsFeed\RestFoundation;
use Sabri\HomeNewsFeed\Rollback;
use Sabri\HomeNewsFeed\SafeMode;
use Sabri\HomeNewsFeed\Settings;
use Sabri\HomeNewsFeed\Snapshot;
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
	global $sabri_test_options, $sabri_test_update_log, $sabri_test_terms, $sabri_test_filter_overrides, $sabri_test_tables, $sabri_test_indexes, $sabri_test_dbdelta_skip_table, $sabri_test_dbdelta_skip_index, $sabri_test_rows;
	$sabri_test_options = array();
	$sabri_test_update_log = array();
	$sabri_test_terms = array();
	$sabri_test_filter_overrides = array();
	$sabri_test_tables = array();
	$sabri_test_indexes = array();
	$sabri_test_dbdelta_skip_table = '';
	$sabri_test_dbdelta_skip_index = '';
	$sabri_test_rows = array();
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
	global $sabri_test_current_caps;
	sabri_reset_options();
	Settings::ensure_defaults();
	$_GET['sabri_feed_safe'] = '1';
	$sabri_test_current_caps = array();
	sabri_assert( ! SafeMode::query_safe_mode(), 'Safe Mode query must require an administrator.' );
	$sabri_test_current_caps = array( 'manage_options' => true, 'sabri_feed_manage_settings' => true );
	sabri_assert( SafeMode::query_safe_mode(), 'Administrators may use read-only Safe Mode query.' );
	SafeMode::set_emergency_disabled( true );
	sabri_assert( SafeMode::emergency_disabled(), 'Emergency Disable must persist.' );
	sabri_assert( ! SafeMode::feature_enabled( 'composer' ), 'Emergency Disable must close future public composer gate.' );
	unset( $_GET['sabri_feed_safe'] );
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
	global $sabri_test_current_caps;
	$sabri_test_current_caps = array();
	sabri_assert( ! RestFoundation::permission_callback(), 'REST diagnostics must deny unauthenticated users.' );
	$sabri_test_current_caps = array( 'sabri_feed_manage_settings' => true );
	sabri_assert( RestFoundation::permission_callback(), 'REST diagnostics must allow plugin administrators.' );
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

$tests = array(
	'sabri_test_identity',
	'sabri_test_bootstrap_no_wrappers',
	'sabri_test_activation_snapshot_order',
	'sabri_test_schema_install_failures_do_not_advance_version',
	'sabri_test_database_schema',
	'sabri_test_settings_isolation',
	'sabri_test_integration_function_settings_preservation',
	'sabri_test_capability_policy',
	'sabri_test_safe_mode_and_emergency',
	'sabri_test_rollback_and_repair_boundaries',
	'sabri_test_rest_permissions',
	'sabri_test_privacy_exporter_payload_structure',
	'sabri_test_uninstall_capability_cleanup',
	'sabri_test_taxonomies',
	'sabri_test_static_safety',
	'sabri_test_documentation_consistency',
);

foreach ( $tests as $test ) {
	$test();
}

if ( $failures ) {
	echo "FAILED\n";
	foreach ( $failures as $failure ) {
		echo '- ' . $failure . "\n";
	}
	exit( 1 );
}

echo 'OK - ' . count( $tests ) . " behavior test groups passed.\n";
