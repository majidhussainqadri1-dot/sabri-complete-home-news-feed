<?php
/**
 * Phase 4A content model, taxonomy, status, setting, and capability tests.
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
use Sabri\HomeNewsFeed\SafeMode;
use Sabri\HomeNewsFeed\Settings;

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
sabri_phase4a_assert( '1.2.0' === Phase4Contracts::TARGET_VERSION, 'Phase 4 target version must remain frozen at 1.2.0.' );
sabri_phase4a_assert( '4A' === Phase4Contracts::CHECKPOINT, 'Executable contract checkpoint must be 4A.' );
sabri_phase4a_assert( 'sabri_news' === Phase4Contracts::POST_TYPE, 'Editorial News post type identifier changed.' );
sabri_phase4a_assert( 'sabri-home-news-feed/v1' === Phase4Contracts::REST_NAMESPACE, 'Phase 4 must retain the compatible REST namespace.' );

$flags = Phase4Contracts::feature_flags();
sabri_phase4a_assert( 8 === count( $flags ), 'Phase 4 must expose exactly eight initial feature gates.' );
foreach ( $flags as $flag => $default ) {
	sabri_phase4a_assert( 0 === $default, 'Phase 4 feature gate must default disabled: ' . $flag );
	sabri_phase4a_assert( ! Phase4Contracts::feature_enabled( $flag ), 'Default feature check must fail closed: ' . $flag );
}
sabri_phase4a_assert( ! Phase4Contracts::feature_enabled( 'unknown_phase4_gate', array( 'unknown_phase4_gate' => 1 ) ), 'Unknown Phase 4 gates must fail closed.' );

$dirty = array(
	'editorial_news_enabled' => 1,
	'breaking_news_enabled'  => '1',
	'unknown_gate'           => 1,
);
$clean = NewsFeatureSettings::sanitize( $dirty );
sabri_phase4a_assert( 1 === $clean['editorial_news_enabled'], 'Recognized explicit feature value must sanitize to enabled.' );
sabri_phase4a_assert( 1 === $clean['breaking_news_enabled'], 'Recognized numeric-string checkbox must sanitize to enabled.' );
sabri_phase4a_assert( 0 === $clean['news_submissions_enabled'], 'Missing checkbox must sanitize to disabled.' );
sabri_phase4a_assert( ! isset( $clean['unknown_gate'] ), 'Unknown Phase 4 feature option must be rejected.' );

NewsFeatureSettings::ensure_defaults();
sabri_phase4a_assert( $flags === NewsFeatureSettings::get(), 'Fresh Phase 4 option must contain disabled frozen defaults.' );

$disabled_definition = EditorialNewsPostType::definition();
sabri_phase4a_assert( false === $disabled_definition['publicly_queryable'], 'Editorial News must not be publicly queryable while disabled.' );
sabri_phase4a_assert( false === $disabled_definition['rewrite'], 'Editorial News rewrite must remain closed while disabled.' );
sabri_phase4a_assert( false === $disabled_definition['show_in_rest'], 'Native REST exposure must remain disabled pending controlled Phase 4 REST implementation.' );
sabri_phase4a_assert( in_array( 'revisions', $disabled_definition['supports'], true ), 'Editorial News must support revisions.' );
sabri_phase4a_assert( 'publish_editorial_news' === $disabled_definition['capabilities']['publish_posts'], 'Post-type publication must use the frozen capability.' );
sabri_phase4a_assert( 'retract_editorial_news' === $disabled_definition['capabilities']['delete_post'], 'Post deletion must be governed by retraction authority.' );

NewsFeatureSettings::update( array( 'editorial_news_enabled' => 1 ) );
$enabled_definition = EditorialNewsPostType::definition();
sabri_phase4a_assert( true === $enabled_definition['publicly_queryable'], 'Accepted master News gate must enable canonical public queryability.' );
sabri_phase4a_assert( 'news' === $enabled_definition['rewrite']['slug'], 'Enabled Editorial News rewrite must use /news/.' );
sabri_phase4a_assert( 'news' === $enabled_definition['has_archive'], 'Enabled Editorial News archive must use /news/.' );

SafeMode::set_emergency_disabled( true );
sabri_phase4a_assert( ! NewsFeatureSettings::enabled( 'editorial_news_enabled' ), 'Emergency Disable must override an enabled Phase 4 gate.' );
SafeMode::set_emergency_disabled( false );

$taxonomy_definitions = NewsTaxonomies::definitions();
sabri_phase4a_assert( Phase4Contracts::taxonomies() === array_keys( $taxonomy_definitions ), 'Phase 4 taxonomy identifiers must match the frozen order and names.' );
foreach ( $taxonomy_definitions as $taxonomy => $definition ) {
	sabri_phase4a_assert( ! empty( $definition['singular'] ) && ! empty( $definition['plural'] ), 'Taxonomy labels must be complete: ' . $taxonomy );
}

sabri_test_reset_state( true );
$term_report = NewsTaxonomies::ensure_default_terms();
sabri_phase4a_assert( count( Phase4Contracts::sections() ) + count( Phase4Contracts::article_types() ) === count( $term_report['created'] ), 'Activation terms must create every frozen section and article type exactly once.' );
$second_term_report = NewsTaxonomies::ensure_default_terms();
sabri_phase4a_assert( 0 === count( $second_term_report['created'] ), 'Default term creation must be idempotent.' );
sabri_phase4a_assert( count( Phase4Contracts::sections() ) + count( Phase4Contracts::article_types() ) === count( $second_term_report['skipped'] ), 'Idempotent term pass must report all existing terms as skipped.' );

$states = NewsStatuses::states();
sabri_phase4a_assert( Phase4Contracts::editorial_states() === $states, 'News status model must expose every frozen editorial state.' );
sabri_phase4a_assert( 'pending' === NewsStatuses::wordpress_status( 'ready-for-publication' ), 'Long workflow states must map safely to a compatible WordPress core status.' );
sabri_phase4a_assert( 'ready-for-publication' === NewsStatuses::sanitize_state( 'ready-for-publication' ), 'Full domain state must remain intact in metadata.' );
sabri_phase4a_assert( '' === NewsStatuses::sanitize_state( 'invented-state' ), 'Unknown editorial state must fail closed.' );
sabri_phase4a_assert( Phase4Contracts::WORKFLOW_META_KEY === NewsStatuses::storage_contract()['domain_state_key'], 'Workflow metadata key must remain the source of truth.' );
foreach ( Phase4Contracts::wordpress_status_map() as $domain_state => $core_status ) {
	sabri_phase4a_assert( in_array( $domain_state, $states, true ), 'WordPress status map contains unknown domain state: ' . $domain_state );
	sabri_phase4a_assert( strlen( $core_status ) <= 20, 'Mapped WordPress status exceeds its storage limit: ' . $domain_state );
}

$meta = EditorialNewsPostType::meta_definitions();
sabri_phase4a_assert( isset( $meta[ Phase4Contracts::WORKFLOW_META_KEY ] ), 'Editorial News metadata must register the domain workflow source of truth.' );
sabri_phase4a_assert( false !== strpos( (string) $meta['_sabri_news_language']['default'], 'en-' ), 'Initial language metadata must default to American English.' );
sabri_phase4a_assert( 'en-US' === EditorialNewsPostType::sanitize_language( 'invalid language!' ), 'Invalid language tag must fail to the safe initial language.' );

sabri_phase4a_reset_roles();
$role_map = NewsCapabilities::default_role_map();
sabri_phase4a_assert( NewsCapabilities::capabilities() === Phase4Contracts::capabilities(), 'Capability implementation must match the frozen list.' );
sabri_phase4a_assert( NewsCapabilities::role_can_publish( 'administrator' ), 'Administrator must retain publication authority.' );
sabri_phase4a_assert( NewsCapabilities::role_can_publish( 'founder' ), 'Founder must retain explicit institutional publication authority.' );
sabri_phase4a_assert( NewsCapabilities::role_can_publish( 'editor_in_chief' ), 'Editor-in-Chief must receive publication authority.' );
sabri_phase4a_assert( ! NewsCapabilities::role_can_publish( 'editor' ), 'Managing/editor role must not self-acquire publication authority.' );
sabri_phase4a_assert( ! NewsCapabilities::role_can_publish( 'reporter' ), 'Reporter must not self-publish.' );
sabri_phase4a_assert( ! NewsCapabilities::role_can_publish( 'verified_doctor' ), 'Verified Doctor submitter must not self-publish.' );
sabri_phase4a_assert( ! NewsCapabilities::role_can_publish( 'translator' ), 'Translator must not self-publish.' );
sabri_phase4a_assert( ! in_array( 'manage_news_sources', $role_map['verified_doctor'], true ), 'Verified Doctor must not receive unrestricted source-management authority.' );

$mutations = NewsCapabilities::apply_default_policy();
global $sabri_test_roles;
sabri_phase4a_assert( ! empty( $sabri_test_roles['administrator']->capabilities['manage_news_settings'] ), 'Administrator must receive Phase 4 settings authority.' );
sabri_phase4a_assert( ! empty( $sabri_test_roles['editor_in_chief']->capabilities['publish_editorial_news'] ), 'Editor-in-Chief role must receive publication capability.' );
sabri_phase4a_assert( empty( $sabri_test_roles['reporter']->capabilities['publish_editorial_news'] ), 'Reporter role must remain unable to publish after policy application.' );
sabri_phase4a_assert( isset( $mutations['roles']['administrator'] ), 'Capability mutations must record affected existing roles.' );

$allcaps = array_fill_keys( NewsCapabilities::capabilities(), true );
Settings::ensure_defaults();
SafeMode::set_emergency_disabled( true );
$closed_caps = NewsCapabilities::respect_emergency_disable( $allcaps, array(), array(), null );
sabri_phase4a_assert( true === $closed_caps['read_editorial_news'], 'Emergency Disable must preserve read capability.' );
foreach ( NewsCapabilities::capabilities() as $capability ) {
	if ( 'read_editorial_news' !== $capability ) {
		sabri_phase4a_assert( false === $closed_caps[ $capability ], 'Emergency Disable must close editorial write capability: ' . $capability );
	}
}
SafeMode::set_emergency_disabled( false );

sabri_test_reset_state( true );
sabri_phase4a_reset_roles();
$result = Activator::activate();
sabri_phase4a_assert( 0 === array_sum( $result['phase4_settings'] ), 'Activation must leave all Phase 4 gates disabled.' );
sabri_phase4a_assert( count( Phase4Contracts::sections() ) + count( Phase4Contracts::article_types() ) === count( $result['phase4_terms']['created'] ), 'Activation must create the full frozen Phase 4 term set.' );
sabri_phase4a_assert( '1.2.0-4A' === get_option( 'sabri_feed_phase4_contract_version' ), 'Activation must record the Phase 4A contract identity without promoting plugin version.' );

sabri_phase4a_assert( 20 === count( Phase4Contracts::acceptance_keys() ), 'Phase 4 must retain all 20 Hostinger staging acceptance keys.' );
sabri_phase4a_assert( 10 === count( Phase4Contracts::public_routes() ), 'Phase 4 public route contract must remain complete.' );
sabri_phase4a_assert( 14 === count( Phase4Contracts::rest_routes() ), 'Phase 4 REST route intention map must remain complete.' );

if ( $phase4a_failures ) {
	echo "FAILED\n";
	foreach ( $phase4a_failures as $failure ) {
		echo '- ' . $failure . "\n";
	}
	exit( 1 );
}

echo "OK - Phase 4A content model, gates, statuses, taxonomies, and capabilities passed.\n";
