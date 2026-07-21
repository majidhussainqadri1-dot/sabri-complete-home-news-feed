<?php
/**
 * Phase 4A content model, taxonomy, status, setting, capability, and rollback tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\Activator;
use Sabri\HomeNewsFeed\EditorialNewsPostType;
use Sabri\HomeNewsFeed\NewsCapabilities;
use Sabri\HomeNewsFeed\NewsFeatureSettings;
use Sabri\HomeNewsFeed\NewsStatuses;
use Sabri\HomeNewsFeed\NewsTaxonomies;
use Sabri\HomeNewsFeed\Phase4Contracts;
use Sabri\HomeNewsFeed\Plugin;
use Sabri\HomeNewsFeed\Rollback;
use Sabri\HomeNewsFeed\SafeMode;
use Sabri\HomeNewsFeed\Settings;
use Sabri\HomeNewsFeed\Snapshot;

$phase4a_failures = array();

function sabri_phase4a_assert( $condition, $message ) {
	global $phase4a_failures;
	if ( ! $condition ) {
		$phase4a_failures[] = $message;
	}
}

function sabri_phase4a_reset_roles() {
	global $sabri_test_roles;
	$sabri_test_roles = array(
		'administrator'    => new Sabri_Test_Role( array( 'manage_options' => true ) ),
		'editor'           => new Sabri_Test_Role( array( 'edit_posts' => true ) ),
		'founder'          => new Sabri_Test_Role(),
		'editor_in_chief'  => new Sabri_Test_Role(),
		'managing_editor'  => new Sabri_Test_Role(),
		'section_editor'   => new Sabri_Test_Role(),
		'medical_reviewer' => new Sabri_Test_Role(),
		'reporter'         => new Sabri_Test_Role(),
		'verified_doctor'  => new Sabri_Test_Role(),
		'translator'       => new Sabri_Test_Role(),
		'student'          => new Sabri_Test_Role(),
		'patient'          => new Sabri_Test_Role(),
		'subscriber'       => new Sabri_Test_Role( array( 'read' => true ) ),
	);
}

sabri_test_reset_state( true );
sabri_phase4a_reset_roles();

$identity = Plugin::identity();
sabri_phase4a_assert( '1.0.0' === $identity['version'], 'Phase 4A must not promote the plugin version.' );
sabri_phase4a_assert( '1.0.0' === $identity['schema_version'], 'Phase 4A must not advance the accepted schema version.' );
sabri_phase4a_assert( '1.2.0' === Phase4Contracts::TARGET_VERSION, 'Phase 4 target version must remain 1.2.0.' );
sabri_phase4a_assert( '4A' === Phase4Contracts::CHECKPOINT, 'Executable contract checkpoint must remain 4A.' );
sabri_phase4a_assert( 'sabri_news' === Phase4Contracts::POST_TYPE, 'Editorial News post type identifier changed.' );

$flags = Phase4Contracts::feature_flags();
sabri_phase4a_assert( 8 === count( $flags ), 'Phase 4 must expose exactly eight initial feature gates.' );
foreach ( $flags as $flag => $default ) {
	sabri_phase4a_assert( 0 === $default, 'Phase 4 feature gate must default disabled: ' . $flag );
	sabri_phase4a_assert( ! Phase4Contracts::feature_enabled( $flag ), 'Default feature check must fail closed: ' . $flag );
}
sabri_phase4a_assert( ! Phase4Contracts::feature_enabled( 'unknown_phase4_gate', array( 'unknown_phase4_gate' => 1 ) ), 'Unknown Phase 4 gates must fail closed.' );

$clean = NewsFeatureSettings::sanitize(
	array(
		'editorial_news_enabled' => 1,
		'breaking_news_enabled'  => '1',
		'news_rss_enabled'       => true,
		'news_schema_enabled'    => '1evil',
		'news_notifications_enabled' => ' 1',
		'news_corrections_enabled' => 1.0,
		'unknown_gate'           => 1,
	)
);
sabri_phase4a_assert( 1 === $clean['editorial_news_enabled'], 'Exact integer one must enable a recognized gate.' );
sabri_phase4a_assert( 1 === $clean['breaking_news_enabled'], 'Exact string one must enable a recognized gate.' );
sabri_phase4a_assert( 1 === $clean['news_rss_enabled'], 'Exact boolean true must enable a recognized gate.' );
sabri_phase4a_assert( 0 === $clean['news_schema_enabled'], 'Numeric-prefix strings must fail closed.' );
sabri_phase4a_assert( 0 === $clean['news_notifications_enabled'], 'Whitespace-padded values must fail closed.' );
sabri_phase4a_assert( 0 === $clean['news_corrections_enabled'], 'Floats must fail closed.' );
sabri_phase4a_assert( ! isset( $clean['unknown_gate'] ), 'Unknown Phase 4 feature options must be rejected.' );

NewsFeatureSettings::ensure_defaults();
sabri_phase4a_assert( $flags === NewsFeatureSettings::get(), 'Fresh Phase 4 option must contain disabled frozen defaults.' );

$disabled_definition = EditorialNewsPostType::definition();
$caps                = $disabled_definition['capabilities'];
sabri_phase4a_assert( false === $disabled_definition['publicly_queryable'], 'Editorial News must remain private while disabled.' );
sabri_phase4a_assert( false === $disabled_definition['rewrite'], 'Editorial News rewrites must remain closed while disabled.' );
sabri_phase4a_assert( false === $disabled_definition['show_in_rest'], 'Native REST exposure must remain disabled.' );
sabri_phase4a_assert( true === $disabled_definition['map_meta_cap'], 'Editorial News must use ownership-aware meta capability mapping.' );
sabri_phase4a_assert( 'edit_editorial_news' === $caps['edit_post'], 'Singular edit checks must use a distinct object meta capability.' );
sabri_phase4a_assert( 'edit_own_editorial_news' === $caps['edit_posts'], 'Own-article primitive capability changed.' );
sabri_phase4a_assert( 'edit_others_editorial_news' === $caps['edit_others_posts'], 'Foreign-article primitive capability changed.' );
sabri_phase4a_assert( 'do_not_allow' === $caps['delete_post'], 'Core single-article deletion must be denied.' );
foreach ( array( 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts' ) as $delete_key ) {
	sabri_phase4a_assert( 'do_not_allow' === $caps[ $delete_key ], 'Core destructive deletion must be denied: ' . $delete_key );
}

NewsFeatureSettings::update( array( 'editorial_news_enabled' => 1 ) );
$enabled_definition = EditorialNewsPostType::definition();
sabri_phase4a_assert( true === $enabled_definition['publicly_queryable'], 'Accepted master News gate must enable canonical queryability.' );
sabri_phase4a_assert( 'news' === $enabled_definition['rewrite']['slug'], 'Enabled Editorial News rewrite must use /news/.' );

SafeMode::set_emergency_disabled( true );
sabri_phase4a_assert( ! NewsFeatureSettings::enabled( 'editorial_news_enabled' ), 'Emergency Disable must override an enabled Phase 4 gate.' );
SafeMode::set_emergency_disabled( false );

$taxonomy_definitions = NewsTaxonomies::definitions();
sabri_phase4a_assert( Phase4Contracts::taxonomies() === array_keys( $taxonomy_definitions ), 'Phase 4 taxonomy identifiers changed.' );
global $sabri_test_actions;
$sabri_test_actions = array();
NewsTaxonomies::register();
$runtime_term_writer = false;
foreach ( $sabri_test_actions as $action ) {
	if ( is_array( $action['callback'] ) && 'ensure_default_terms' === $action['callback'][1] ) {
		$runtime_term_writer = true;
	}
}
sabri_phase4a_assert( ! $runtime_term_writer, 'Default taxonomy terms must not be checked or written on every runtime init request.' );

sabri_test_reset_state( true );
$term_report = NewsTaxonomies::ensure_default_terms();
$term_count  = count( Phase4Contracts::sections() ) + count( Phase4Contracts::article_types() );
sabri_phase4a_assert( $term_count === count( $term_report['created'] ), 'Activation must create every frozen section and article type once.' );
sabri_phase4a_assert( '1.2.0-4A' === get_option( NewsTaxonomies::TERM_VERSION_OPTION ), 'Activation term creation must record its contract version.' );
$second_term_report = NewsTaxonomies::ensure_default_terms();
sabri_phase4a_assert( 0 === count( $second_term_report['created'] ), 'Default term creation must be idempotent.' );
sabri_phase4a_assert( $term_count === count( $second_term_report['skipped'] ), 'Idempotent term pass must report all terms as existing.' );

$states = NewsStatuses::states();
sabri_phase4a_assert( Phase4Contracts::editorial_states() === $states, 'News status model must expose every frozen state.' );
sabri_phase4a_assert( 'pending' === NewsStatuses::wordpress_status( 'ready-for-publication' ), 'Long workflow states must map to a safe core status.' );
sabri_phase4a_assert( 'ready-for-publication' === NewsStatuses::sanitize_state( 'ready-for-publication' ), 'Full domain state must remain intact.' );
sabri_phase4a_assert( '' === NewsStatuses::sanitize_state( 'invented-state' ), 'Unknown editorial state must fail closed.' );
foreach ( Phase4Contracts::wordpress_status_map() as $domain_state => $core_status ) {
	sabri_phase4a_assert( in_array( $domain_state, $states, true ), 'Status map contains an unknown domain state: ' . $domain_state );
	sabri_phase4a_assert( strlen( $core_status ) <= 20, 'Mapped WordPress status exceeds storage length: ' . $domain_state );
}

$meta = EditorialNewsPostType::meta_definitions();
sabri_phase4a_assert( isset( $meta[ Phase4Contracts::WORKFLOW_META_KEY ] ), 'Workflow source-of-truth metadata is missing.' );
sabri_phase4a_assert( 'en-US' === EditorialNewsPostType::sanitize_language( 'invalid language!' ), 'Invalid language tags must fail to en-US.' );

sabri_phase4a_reset_roles();
$role_map = NewsCapabilities::default_role_map();
sabri_phase4a_assert( NewsCapabilities::capabilities() === Phase4Contracts::capabilities(), 'Capability implementation must match the frozen list.' );
sabri_phase4a_assert( NewsCapabilities::role_can_publish( 'administrator' ), 'Administrator must retain publication authority.' );
sabri_phase4a_assert( NewsCapabilities::role_can_publish( 'founder' ), 'Founder must retain publication authority.' );
sabri_phase4a_assert( NewsCapabilities::role_can_publish( 'editor_in_chief' ), 'Editor-in-Chief must receive publication authority.' );
foreach ( array( 'editor', 'reporter', 'verified_doctor', 'translator' ) as $non_publisher ) {
	sabri_phase4a_assert( ! NewsCapabilities::role_can_publish( $non_publisher ), $non_publisher . ' must not self-publish.' );
}
sabri_phase4a_assert( ! in_array( 'manage_news_sources', $role_map['verified_doctor'], true ), 'Verified Doctor must not receive unrestricted source authority.' );

$mutations = NewsCapabilities::apply_default_policy();
global $sabri_test_roles;
sabri_phase4a_assert( ! empty( $sabri_test_roles['administrator']->capabilities['manage_news_settings'] ), 'Administrator must receive Phase 4 settings authority.' );
sabri_phase4a_assert( ! empty( $sabri_test_roles['editor_in_chief']->capabilities['publish_editorial_news'] ), 'Editor-in-Chief must receive publication capability.' );
sabri_phase4a_assert( empty( $sabri_test_roles['reporter']->capabilities['publish_editorial_news'] ), 'Reporter must remain unable to publish.' );
sabri_phase4a_assert( isset( $mutations['roles']['administrator'] ), 'Capability mutations must record affected roles.' );

$allcaps = array_fill_keys( NewsCapabilities::capabilities(), true );
Settings::ensure_defaults();
SafeMode::set_emergency_disabled( true );
$closed_caps = NewsCapabilities::respect_emergency_disable( $allcaps, array(), array(), null );
sabri_phase4a_assert( true === $closed_caps['read_editorial_news'], 'Emergency Disable must preserve read authority.' );
foreach ( NewsCapabilities::capabilities() as $capability ) {
	if ( 'read_editorial_news' !== $capability ) {
		sabri_phase4a_assert( false === $closed_caps[ $capability ], 'Emergency Disable must close: ' . $capability );
	}
}
SafeMode::set_emergency_disabled( false );

sabri_test_reset_state( true );
sabri_phase4a_reset_roles();
$result   = Activator::activate();
$snapshot = Snapshot::latest();
sabri_phase4a_assert( 0 === array_sum( $result['phase4_settings'] ), 'Activation must leave all Phase 4 gates disabled.' );
sabri_phase4a_assert( $term_count === count( $result['phase4_terms']['created'] ), 'Activation must create the complete Phase 4 term set.' );
sabri_phase4a_assert( '1.2.0-4A' === get_option( 'sabri_feed_phase4_contract_version' ), 'Activation must record the Phase 4A contract identity.' );
sabri_phase4a_assert( isset( $snapshot['phase4_settings'], $snapshot['capability_roles']['administrator']['manage_news_settings'] ), 'Activation snapshot must include Phase 4 state.' );
sabri_phase4a_assert( false === $snapshot['capability_roles']['administrator']['manage_news_settings'], 'Baseline must record absence of newly added capability.' );
$baseline_created_at = $snapshot['created_at'];
Activator::activate();
sabri_phase4a_assert( $baseline_created_at === Snapshot::latest()['created_at'], 'Reactivation must not overwrite the immutable same-version rollback baseline.' );

NewsFeatureSettings::update( array_fill_keys( array_keys( Phase4Contracts::feature_flags() ), 1 ) );
update_option( 'sabri_feed_phase4_contract_version', 'mutated-contract', false );
update_option( NewsTaxonomies::TERM_VERSION_OPTION, 'mutated-terms', false );
update_option( NewsCapabilities::MUTATION_OPTION, array( 'mutated' => true ), false );
$rollback = Rollback::execute();
sabri_phase4a_assert( 0 === array_sum( NewsFeatureSettings::get() ), 'Rollback must restore pre-activation Phase 4 gates.' );
sabri_phase4a_assert( '' === get_option( 'sabri_feed_phase4_contract_version' ), 'Rollback must restore the prior Phase 4 contract version option.' );
sabri_phase4a_assert( '' === get_option( NewsTaxonomies::TERM_VERSION_OPTION ), 'Rollback must restore the prior Phase 4 term version option.' );
sabri_phase4a_assert( array() === get_option( NewsCapabilities::MUTATION_OPTION ), 'Rollback must restore the prior capability mutation record.' );
sabri_phase4a_assert( empty( $sabri_test_roles['administrator']->capabilities['manage_news_settings'] ), 'Rollback must remove Phase 4 capability absent from baseline.' );
sabri_phase4a_assert( in_array( 'Editorial News content and metadata', $rollback['preserved'], true ), 'Rollback must preserve Editorial News content and metadata.' );

sabri_phase4a_assert( 20 === count( Phase4Contracts::acceptance_keys() ), 'Phase 4 must retain all 20 Hostinger acceptance keys.' );
sabri_phase4a_assert( 10 === count( Phase4Contracts::public_routes() ), 'Phase 4 public route contract must remain complete.' );
sabri_phase4a_assert( 14 === count( Phase4Contracts::rest_routes() ), 'Phase 4 REST route intention map must remain complete.' );

if ( $phase4a_failures ) {
	echo "FAILED\n";
	foreach ( $phase4a_failures as $failure ) {
		echo '- ' . $failure . "\n";
	}
	exit( 1 );
}

echo "OK - Phase 4A content model, ownership, non-destructive deletion, gates, taxonomies, capabilities, snapshots, and rollback passed.\n";
