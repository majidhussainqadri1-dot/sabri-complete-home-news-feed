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

function sabri_phase4a_authorize_admin() {
	global $sabri_test_current_user_id, $sabri_test_user_roles;
	$sabri_test_current_user_id = 1;
	$sabri_test_user_roles[1] = array( 'administrator' );
}

sabri_test_reset_state( true );
sabri_phase4a_reset_roles();

$identity = Plugin::identity();
sabri_phase4a_assert( '1.0.0' === $identity['version'], 'Phase 4A must not promote the plugin version.' );
sabri_phase4a_assert( '1.0.0' === $identity['schema_version'], 'Phase 4A must not advance the accepted schema version.' );
sabri_phase4a_assert( '1.2.0' === Phase4Contracts::TARGET_VERSION, 'Phase 4 target version must remain 1.2.0.' );
sabri_phase4a_assert( '4A' === Phase4Contracts::CHECKPOINT, 'Executable contract checkpoint must remain 4A.' );
sabri_phase4a_assert( 'sabri_news' === Phase4Contracts::POST_TYPE, 'Editorial News post type identifier changed.' );
sabri_phase4a_assert( 'sabri-home-news-feed/v1' === Phase4Contracts::REST_NAMESPACE, 'Phase 4 REST namespace changed.' );

$flags = Phase4Contracts::feature_flags();
sabri_phase4a_assert( 8 === count( $flags ), 'Phase 4 must expose exactly eight initial feature gates.' );
foreach ( $flags as $flag => $default ) {
	sabri_phase4a_assert( 0 === $default, 'Phase 4 feature gate must default disabled: ' . $flag );
	sabri_phase4a_assert( ! NewsFeatureSettings::enabled( $flag ), 'Default feature check must fail closed: ' . $flag );
}
sabri_phase4a_assert( ! NewsFeatureSettings::enabled( 'editorial_news_enabled!' ), 'Malformed feature identifiers must not alias a valid gate.' );
sabri_phase4a_assert( ! NewsFeatureSettings::enabled( 'Editorial_news_enabled' ), 'Uppercase feature aliases must fail closed.' );

$clean = NewsFeatureSettings::sanitize(
	array(
		'editorial_news_enabled'      => 1,
		'breaking_news_enabled'       => '1',
		'news_rss_enabled'            => true,
		'news_schema_enabled'         => '1evil',
		'news_notifications_enabled'  => ' 1',
		'news_corrections_enabled'    => 1.0,
		'news_submissions_enabled'    => array( 1 ),
		'unknown_gate'                => 1,
	)
);
sabri_phase4a_assert( 1 === $clean['editorial_news_enabled'], 'Exact integer one must enable a recognized gate.' );
sabri_phase4a_assert( 1 === $clean['breaking_news_enabled'], 'Exact string one must enable a recognized gate.' );
sabri_phase4a_assert( 1 === $clean['news_rss_enabled'], 'Exact boolean true must enable a recognized gate.' );
sabri_phase4a_assert( 0 === $clean['news_schema_enabled'], 'Numeric-prefix strings must fail closed.' );
sabri_phase4a_assert( 0 === $clean['news_notifications_enabled'], 'Whitespace-padded values must fail closed.' );
sabri_phase4a_assert( 0 === $clean['news_corrections_enabled'], 'Floats must fail closed.' );
sabri_phase4a_assert( 0 === $clean['news_submissions_enabled'], 'Arrays must fail closed.' );
sabri_phase4a_assert( ! isset( $clean['unknown_gate'] ), 'Unknown Phase 4 feature options must be rejected.' );

NewsFeatureSettings::ensure_defaults();
sabri_phase4a_assert( $flags === NewsFeatureSettings::get(), 'Fresh Phase 4 option must contain disabled frozen defaults.' );
update_option( 'sabri_feed_flush_rewrite_rules', 0, false );
NewsFeatureSettings::update( array( 'editorial_news_enabled' => 1 ) );
sabri_phase4a_assert( 1 === get_option( 'sabri_feed_flush_rewrite_rules' ), 'Changing a public Phase 4 gate must schedule rewrite repair.' );
update_option( 'sabri_feed_flush_rewrite_rules', 0, false );
NewsFeatureSettings::update( array( 'news_notifications_enabled' => 1 ) );
sabri_phase4a_assert( 0 === get_option( 'sabri_feed_flush_rewrite_rules' ), 'A non-routing gate must not schedule unnecessary rewrite repair.' );

NewsFeatureSettings::update( array( 'editorial_news_enabled' => 1 ) );
$enabled_definition = EditorialNewsPostType::definition();
$caps               = $enabled_definition['capabilities'];
sabri_phase4a_assert( true === $enabled_definition['publicly_queryable'], 'Accepted master News gate must enable canonical queryability.' );
sabri_phase4a_assert( 'news' === $enabled_definition['rewrite']['slug'], 'Enabled Editorial News rewrite must use /news/.' );
sabri_phase4a_assert( false === $enabled_definition['show_in_rest'], 'Native REST exposure must remain disabled.' );
sabri_phase4a_assert( true === $enabled_definition['map_meta_cap'], 'Editorial News must use ownership-aware WordPress meta capability mapping.' );
sabri_phase4a_assert( 'edit_editorial_news' === $caps['edit_post'], 'Singular edit checks must use the object meta capability.' );
sabri_phase4a_assert( 'delete_editorial_news' === $caps['delete_post'], 'Singular delete checks must use a unique denied meta capability.' );
foreach ( array( 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts' ) as $delete_key ) {
	sabri_phase4a_assert( 'do_not_allow' === $caps[ $delete_key ], 'Core destructive deletion must be denied: ' . $delete_key );
}

SafeMode::set_emergency_disabled( true );
sabri_phase4a_assert( ! NewsFeatureSettings::enabled( 'editorial_news_enabled' ), 'Emergency Disable must override an enabled Phase 4 gate.' );
SafeMode::set_emergency_disabled( false );

$taxonomy_definitions = NewsTaxonomies::definitions();
sabri_phase4a_assert( Phase4Contracts::taxonomies() === array_keys( $taxonomy_definitions ), 'Phase 4 taxonomy identifiers changed.' );
global $sabri_test_actions;
$sabri_test_actions = array();
NewsTaxonomies::register();
$init_term_writer = false;
$admin_upgrade_check = false;
foreach ( $sabri_test_actions as $action ) {
	if ( is_array( $action['callback'] ) && 'ensure_default_terms' === $action['callback'][1] && 'init' === $action['hook'] ) {
		$init_term_writer = true;
	}
	if ( is_array( $action['callback'] ) && 'maybe_ensure_default_terms' === $action['callback'][1] && 'admin_init' === $action['hook'] ) {
		$admin_upgrade_check = true;
	}
}
sabri_phase4a_assert( ! $init_term_writer, 'Default taxonomy terms must not be written on every frontend init request.' );
sabri_phase4a_assert( $admin_upgrade_check, 'Active-plugin upgrades require one bounded admin-side term check.' );

sabri_test_reset_state( true );
sabri_phase4a_reset_roles();
$unauthorized_upgrade = NewsTaxonomies::maybe_ensure_default_terms();
sabri_phase4a_assert( ! $unauthorized_upgrade['success'] && isset( $unauthorized_upgrade['failed']['authorization'] ), 'An unauthenticated upgrade request must not write taxonomy terms.' );
sabri_phase4a_assert( empty( $sabri_test_terms ), 'Unauthorized taxonomy upgrade must leave term storage unchanged.' );

sabri_phase4a_authorize_admin();
$term_count  = count( Phase4Contracts::sections() ) + count( Phase4Contracts::article_types() );
$term_report = NewsTaxonomies::ensure_default_terms();
sabri_phase4a_assert( $term_report['success'], 'Normal Phase 4 term installation must report success.' );
sabri_phase4a_assert( $term_count === count( $term_report['created'] ), 'Activation must create every frozen section and article type once.' );
sabri_phase4a_assert( '' === get_option( NewsTaxonomies::TERM_VERSION_OPTION, '' ), 'Direct term creation must not claim a version before caller verification.' );
$upgrade_report = NewsTaxonomies::maybe_ensure_default_terms();
sabri_phase4a_assert( $upgrade_report['success'], 'Authorized bounded upgrade term verification must succeed.' );
sabri_phase4a_assert( '1.2.0-4A' === get_option( NewsTaxonomies::TERM_VERSION_OPTION ), 'Successful upgrade verification must record its exact term version.' );
sabri_phase4a_assert( '1.2.0-4A' === get_option( 'sabri_feed_phase4_contract_version' ), 'Successful upgrade verification must record its exact contract version.' );

sabri_test_reset_state( true );
sabri_phase4a_reset_roles();
sabri_phase4a_authorize_admin();
global $sabri_test_filter_overrides;
$sabri_test_filter_overrides['sabri_feed_phase4_insert_term_result'] = new WP_Error( 'forced_term_failure', 'Forced term failure' );
$failed_terms = NewsTaxonomies::maybe_ensure_default_terms();
sabri_phase4a_assert( ! $failed_terms['success'] && ! empty( $failed_terms['failed'] ), 'Term insertion errors must fail closed rather than being reported as skipped.' );
sabri_phase4a_assert( '' === get_option( NewsTaxonomies::TERM_VERSION_OPTION, '' ), 'Failed term installation must not advance the terms marker.' );
sabri_phase4a_assert( '' === get_option( 'sabri_feed_phase4_contract_version', '' ), 'Failed term installation must not advance the contract marker.' );
$failed_activation = Activator::activate();
sabri_phase4a_assert( empty( $failed_activation['phase4_ready'] ), 'Activation with failed Phase 4 terms must remain incomplete.' );
$sabri_test_filter_overrides = array();

sabri_test_reset_state( true );
sabri_phase4a_reset_roles();

$states = NewsStatuses::states();
sabri_phase4a_assert( Phase4Contracts::editorial_states() === $states, 'News status model must expose every frozen state.' );
sabri_phase4a_assert( 'pending' === NewsStatuses::wordpress_status( 'ready-for-publication' ), 'Long workflow states must map to a safe core status.' );
sabri_phase4a_assert( 'ready-for-publication' === NewsStatuses::sanitize_state( 'ready-for-publication' ), 'Full domain state must remain intact.' );
sabri_phase4a_assert( '' === NewsStatuses::sanitize_state( 'published!!!' ), 'Malformed workflow input must not be repaired into published.' );
sabri_phase4a_assert( '' === NewsStatuses::sanitize_state( ' Published' ), 'Whitespace/case workflow aliases must fail closed.' );
foreach ( Phase4Contracts::wordpress_status_map() as $domain_state => $core_status ) {
	sabri_phase4a_assert( in_array( $domain_state, $states, true ), 'Status map contains an unknown domain state: ' . $domain_state );
	sabri_phase4a_assert( strlen( $core_status ) <= 20, 'Mapped WordPress status exceeds storage length: ' . $domain_state );
}

$meta = EditorialNewsPostType::meta_definitions();
sabri_phase4a_assert( isset( $meta[ Phase4Contracts::WORKFLOW_META_KEY ] ), 'Workflow source-of-truth metadata is missing.' );
sabri_phase4a_assert( 'en-US' === EditorialNewsPostType::sanitize_language( 'invalid language!' ), 'Invalid language tags must fail to en-US.' );
sabri_phase4a_assert( 'ur-PK' === EditorialNewsPostType::sanitize_language( 'ur-PK' ), 'Valid bounded language tags must be preserved.' );
sabri_phase4a_assert( '' === EditorialNewsPostType::sanitize_token( 'passed!!!' ), 'Protected status tokens must not be repaired.' );
sabri_phase4a_assert( 100 === EditorialNewsPostType::sanitize_priority( '100' ), 'Bounded priority 100 must be accepted.' );
sabri_phase4a_assert( 0 === EditorialNewsPostType::sanitize_priority( 101 ), 'Priority above 100 must fail closed.' );
sabri_phase4a_assert( '' === EditorialNewsPostType::sanitize_datetime( 'tomorrow' ), 'Free-form dates must fail closed.' );

$post_id = sabri_test_add_post( array( 'post_type' => Phase4Contracts::POST_TYPE, 'post_author' => 1, 'post_status' => 'draft' ) );
global $sabri_test_current_user_id, $sabri_test_current_caps;
$sabri_test_current_user_id = 1;
$sabri_test_current_caps = array( 'edit_editorial_news' => true );
sabri_phase4a_assert( EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_summary', $post_id, 1 ), 'An object editor must be able to change basic summary metadata.' );
sabri_phase4a_assert( ! EditorialNewsPostType::meta_auth_callback( false, Phase4Contracts::WORKFLOW_META_KEY, $post_id, 1 ), 'An ordinary author must not change workflow source-of-truth metadata.' );
sabri_phase4a_assert( ! EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_retraction_status', $post_id, 1 ), 'An ordinary author must not mark an article retracted.' );
sabri_phase4a_assert( ! EditorialNewsPostType::meta_auth_callback( false, '_unknown_news_meta', $post_id, 1 ), 'Unknown Editorial News metadata must fail closed.' );
$sabri_test_current_caps['publish_editorial_news'] = true;
sabri_phase4a_assert( EditorialNewsPostType::meta_auth_callback( false, Phase4Contracts::WORKFLOW_META_KEY, $post_id, 1 ), 'A publishing authority with object edit rights must control workflow metadata.' );

$sabri_test_current_caps = array( 'medical_review_editorial_news' => true );
update_post_meta( $post_id, '_sabri_news_medical_reviewer_id', 1 );
sabri_phase4a_assert( EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_medical_review_status', $post_id, 1 ), 'The assigned Medical Reviewer must be able to change only the medical review record.' );
update_post_meta( $post_id, '_sabri_news_medical_reviewer_id', 2 );
sabri_phase4a_assert( ! EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_medical_review_status', $post_id, 1 ), 'An unassigned Medical Reviewer must not change another article review.' );
sabri_phase4a_assert( ! EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_medical_reviewer_id', $post_id, 1 ), 'A Medical Reviewer must not self-assign through reviewer metadata.' );

sabri_phase4a_reset_roles();
$role_map = NewsCapabilities::default_role_map();
sabri_phase4a_assert( NewsCapabilities::capabilities() === Phase4Contracts::capabilities(), 'Capability implementation must match the frozen list.' );
sabri_phase4a_assert( NewsCapabilities::role_can_publish( 'administrator' ), 'Administrator must retain publication authority.' );
sabri_phase4a_assert( NewsCapabilities::role_can_publish( 'founder' ), 'Founder must retain publication authority.' );
sabri_phase4a_assert( NewsCapabilities::role_can_publish( 'editor_in_chief' ), 'Editor-in-Chief must receive publication authority.' );
foreach ( array( 'editor', 'reporter', 'verified_doctor', 'translator' ) as $non_publisher ) {
	sabri_phase4a_assert( ! NewsCapabilities::role_can_publish( $non_publisher ), $non_publisher . ' must not self-publish.' );
}
foreach ( array( 'section_editor', 'medical_reviewer', 'reporter', 'verified_doctor', 'translator' ) as $scoped_role ) {
	sabri_phase4a_assert( ! in_array( 'manage_news_sources', $role_map[ $scoped_role ], true ), $scoped_role . ' must not receive a global source-management primitive before object policy exists.' );
}
sabri_phase4a_assert( ! in_array( 'edit_others_editorial_news', $role_map['section_editor'], true ), 'Section Editor must not receive global edit-others authority before section policy exists.' );

$mutations = NewsCapabilities::apply_default_policy();
global $sabri_test_roles;
sabri_phase4a_assert( ! empty( $sabri_test_roles['administrator']->capabilities['manage_news_settings'] ), 'Administrator must receive Phase 4 settings authority.' );
sabri_phase4a_assert( ! empty( $sabri_test_roles['editor_in_chief']->capabilities['publish_editorial_news'] ), 'Editor-in-Chief must receive publication capability.' );
sabri_phase4a_assert( empty( $sabri_test_roles['reporter']->capabilities['publish_editorial_news'] ), 'Reporter must remain unable to publish.' );
sabri_phase4a_assert( isset( $mutations['managed_caps']['administrator']['manage_news_settings'] ), 'New plugin-added capabilities must be recorded as managed.' );

$sabri_test_roles['verified_doctor']->add_cap( 'manage_news_sources' );
$legacy_mutations = get_option( NewsCapabilities::MUTATION_OPTION, array() );
$legacy_mutations['roles']['verified_doctor']['manage_news_sources'] = 'added';
unset( $legacy_mutations['managed_caps'] );
update_option( NewsCapabilities::MUTATION_OPTION, $legacy_mutations, false );
NewsCapabilities::apply_default_policy();
sabri_phase4a_assert( empty( $sabri_test_roles['verified_doctor']->capabilities['manage_news_sources'] ), 'A stale capability explicitly recorded as plugin-added must be removed.' );

sabri_phase4a_reset_roles();
$sabri_test_roles['administrator']->add_cap( 'manage_news_settings' );
delete_option( NewsCapabilities::MUTATION_OPTION );
NewsCapabilities::apply_default_policy();
sabri_phase4a_assert( ! empty( $sabri_test_roles['administrator']->capabilities['manage_news_settings'] ), 'A capability that predates plugin management must be preserved.' );

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

// Legacy same-version snapshot augmentation must preserve its original timestamp and baseline.
sabri_test_reset_state( true );
sabri_phase4a_reset_roles();
$sabri_test_roles['administrator']->add_cap( 'manage_news_settings' );
update_option(
	Snapshot::OPTION_NAME,
	array(
		'version'          => '1.0.0',
		'settings'         => array(),
		'capability_roles' => array( 'administrator' => array() ),
		'created_at'       => '2026-01-01 00:00:00',
	),
	false
);
update_option(
	NewsCapabilities::MUTATION_OPTION,
	array( 'managed_caps' => array( 'administrator' => array( 'manage_news_settings' => true ) ) ),
	false
);
$augmented = Snapshot::capture_before_mutation( 'legacy-upgrade' );
sabri_phase4a_assert( '2026-01-01 00:00:00' === $augmented['created_at'], 'Legacy snapshot augmentation must preserve original created_at.' );
sabri_phase4a_assert( 2 === $augmented['format_version'], 'Legacy snapshot must be upgraded to the current snapshot format.' );
sabri_phase4a_assert( false === $augmented['capability_roles']['administrator']['manage_news_settings'], 'A plugin-managed capability must not become a pre-existing rollback baseline during augmentation.' );

sabri_test_reset_state( true );
sabri_phase4a_reset_roles();
$result   = Activator::activate();
$snapshot = Snapshot::latest();
sabri_phase4a_assert( $result['phase4_ready'], 'Successful activation must verify Phase 4A readiness.' );
sabri_phase4a_assert( 0 === array_sum( $result['phase4_settings'] ), 'Activation must leave all Phase 4 gates disabled.' );
sabri_phase4a_assert( $term_count === count( $result['phase4_terms']['created'] ), 'Activation must create the complete Phase 4 term set.' );
sabri_phase4a_assert( '1.2.0-4A' === get_option( 'sabri_feed_phase4_contract_version' ), 'Successful activation must record the Phase 4A contract identity.' );
sabri_phase4a_assert( isset( $snapshot['option_exists']['phase4_settings'] ) && false === $snapshot['option_exists']['phase4_settings'], 'Snapshot must record that the Phase 4 settings option did not exist before activation.' );
$baseline_created_at = $snapshot['created_at'];
$baseline_caps       = $snapshot['capability_roles'];
Activator::activate();
sabri_phase4a_assert( $baseline_created_at === Snapshot::latest()['created_at'], 'Reactivation must not overwrite the immutable same-version rollback baseline.' );
sabri_phase4a_assert( $baseline_caps === Snapshot::latest()['capability_roles'], 'Reactivation must preserve the original capability baseline.' );

NewsFeatureSettings::update( array_fill_keys( array_keys( Phase4Contracts::feature_flags() ), 1 ) );
update_option( 'sabri_feed_phase4_contract_version', 'mutated-contract', false );
update_option( NewsTaxonomies::TERM_VERSION_OPTION, 'mutated-terms', false );
update_option( NewsCapabilities::MUTATION_OPTION, array( 'mutated' => true ), false );
$rollback = Rollback::execute();
sabri_phase4a_assert( 0 === array_sum( NewsFeatureSettings::get() ), 'Rollback projection must return disabled Phase 4 defaults.' );
sabri_phase4a_assert( '__missing__' === get_option( NewsFeatureSettings::OPTION_NAME, '__missing__' ), 'Rollback must delete the Phase 4 settings option when it was absent at baseline.' );
sabri_phase4a_assert( '__missing__' === get_option( 'sabri_feed_phase4_contract_version', '__missing__' ), 'Rollback must delete the contract marker when absent at baseline.' );
sabri_phase4a_assert( '__missing__' === get_option( NewsTaxonomies::TERM_VERSION_OPTION, '__missing__' ), 'Rollback must delete the term marker when absent at baseline.' );
sabri_phase4a_assert( '__missing__' === get_option( NewsCapabilities::MUTATION_OPTION, '__missing__' ), 'Rollback must delete the mutation record when absent at baseline.' );
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

echo "OK - Phase 4A strict gates, workflow states, ownership, assigned review metadata, scoped capabilities, authorized taxonomy upgrades, immutable snapshots, and exact rollback passed.\n";
