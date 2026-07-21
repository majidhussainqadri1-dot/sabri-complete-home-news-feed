<?php
/**
 * Phase 4A security-hardening contract tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\EditorialNewsPostType;
use Sabri\HomeNewsFeed\NewsCapabilities;
use Sabri\HomeNewsFeed\NewsFeatureSettings;
use Sabri\HomeNewsFeed\NewsStatuses;
use Sabri\HomeNewsFeed\Phase4Contracts;
use Sabri\HomeNewsFeed\Snapshot;

$root = dirname( __DIR__ );
$failures = array();

function sabri_phase4a_security_assert( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		$failures[] = $message;
	}
}

$required_files = array(
	'PHASE-4-CONTRACTS-ADDENDUM-3-SECURITY-HARDENING.md',
	'MANDATORY-SECOND-QA-POLICY.md',
	'PHASE-4A-SECOND-QA-PROTOCOL.md',
	'tests/run-phase4a-playground-tests.mjs',
	'tests/run-phase4a-second-one-hour-qa.sh',
	'.github/workflows/phase4a-content-model-tests.yml',
	'.github/workflows/phase4a-second-one-hour-qa.yml',
);
$contents = array();
foreach ( $required_files as $file ) {
	$path = $root . '/' . $file;
	sabri_phase4a_security_assert( is_file( $path ), 'Missing Phase 4A security file: ' . $file );
	if ( is_file( $path ) ) {
		$contents[ $file ] = (string) file_get_contents( $path );
		sabri_phase4a_security_assert( '' !== trim( $contents[ $file ] ), 'Empty Phase 4A security file: ' . $file );
		sabri_phase4a_security_assert( false === strpos( $contents[ $file ], '<<<<<<<' ), 'Merge-conflict marker found: ' . $file );
	}
}

$addendum = isset( $contents['PHASE-4-CONTRACTS-ADDENDUM-3-SECURITY-HARDENING.md'] ) ? $contents['PHASE-4-CONTRACTS-ADDENDUM-3-SECURITY-HARDENING.md'] : '';
foreach ( array(
	'exact lowercase values exactly',
	'object-policy service',
	'unknown meta keys fail closed',
	'cannot self-assign',
	'term-version and Phase 4 contract-version markers advance together',
	'value and existence',
	'3,900-second second QA',
) as $phrase ) {
	sabri_phase4a_security_assert( false !== strpos( $addendum, $phrase ), 'Missing security addendum invariant: ' . $phrase );
}

$enabled = array( 'editorial_news_enabled' => 1 );
sabri_phase4a_security_assert( Phase4Contracts::feature_enabled( 'editorial_news_enabled', $enabled ), 'Exact enabled feature contract must pass.' );
sabri_phase4a_security_assert( ! Phase4Contracts::feature_enabled( 'editorial_news_enabled!', $enabled ), 'Malformed feature contract identifier must fail closed.' );
sabri_phase4a_security_assert( ! Phase4Contracts::feature_enabled( 'Editorial_news_enabled', $enabled ), 'Uppercase feature alias must fail closed.' );
sabri_phase4a_security_assert( ! Phase4Contracts::feature_enabled( 'editorial_news_enabled', array( 'editorial_news_enabled' => '1evil' ) ), 'Numeric-prefix feature value must fail closed.' );
sabri_phase4a_security_assert( ! Phase4Contracts::feature_enabled( 'editorial_news_enabled', array( 'editorial_news_enabled' => 1.0 ) ), 'Float feature value must fail closed.' );

sabri_phase4a_security_assert( '' === NewsStatuses::sanitize_state( 'published!!!' ), 'Malformed workflow state must not be repaired.' );
sabri_phase4a_security_assert( '' === NewsStatuses::sanitize_state( ' Published' ), 'Whitespace/case workflow state must fail closed.' );
sabri_phase4a_security_assert( 'published' === NewsStatuses::sanitize_state( 'published' ), 'Exact workflow state must pass.' );

$cap_map = EditorialNewsPostType::capability_map();
sabri_phase4a_security_assert( true === EditorialNewsPostType::definition()['map_meta_cap'], 'Ownership-aware map_meta_cap must remain enabled.' );
sabri_phase4a_security_assert( 'delete_editorial_news' === $cap_map['delete_post'], 'Singular deletion must use the unique denied meta capability.' );
foreach ( array( 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts' ) as $key ) {
	sabri_phase4a_security_assert( 'do_not_allow' === $cap_map[ $key ], 'Destructive deletion primitive must remain denied: ' . $key );
}

sabri_phase4a_security_assert( 'publish_editorial_news' === EditorialNewsPostType::meta_capability( Phase4Contracts::WORKFLOW_META_KEY ), 'Workflow metadata must require publication authority.' );
sabri_phase4a_security_assert( 'retract_editorial_news' === EditorialNewsPostType::meta_capability( '_sabri_news_retraction_status' ), 'Retraction metadata must require retraction authority.' );
sabri_phase4a_security_assert( 'review_editorial_news' === EditorialNewsPostType::meta_capability( '_sabri_news_medical_reviewer_id' ), 'Reviewer assignment must require review authority.' );
sabri_phase4a_security_assert( 'do_not_allow' === EditorialNewsPostType::meta_capability( '_unknown_news_meta' ), 'Unknown metadata must fail closed.' );

$roles = NewsCapabilities::default_role_map();
foreach ( array( 'section_editor', 'medical_reviewer', 'reporter', 'verified_doctor', 'translator' ) as $role ) {
	sabri_phase4a_security_assert( ! in_array( 'manage_news_sources', $roles[ $role ], true ), 'Scoped role received global source-management authority: ' . $role );
}
sabri_phase4a_security_assert( ! in_array( 'edit_others_editorial_news', $roles['section_editor'], true ), 'Section Editor received global edit-others authority before section policy.' );

sabri_phase4a_security_assert( 2 === Snapshot::FORMAT_VERSION, 'Snapshot format version must remain security-hardened version 2.' );
sabri_phase4a_security_assert( 0 === NewsFeatureSettings::sanitize( array( 'editorial_news_enabled' => '1evil' ) )['editorial_news_enabled'], 'Strict settings sanitizer regressed.' );

$protocol = isset( $contents['PHASE-4A-SECOND-QA-PROTOCOL.md'] ) ? $contents['PHASE-4A-SECOND-QA-PROTOCOL.md'] : '';
foreach ( array(
	'PHASE-4-CONTRACTS-ADDENDUM-3-SECURITY-HARDENING.md',
	'tests/run-phase4a-playground-tests.mjs',
	'packaged Phase 4A security test',
	'3900 seconds',
	'13 completed cycles',
) as $phrase ) {
	sabri_phase4a_security_assert( false !== strpos( $protocol, $phrase ), 'Second-QA protocol missing security requirement: ' . $phrase );
}

$workflow = isset( $contents['.github/workflows/phase4a-second-one-hour-qa.yml'] ) ? $contents['.github/workflows/phase4a-second-one-hour-qa.yml'] : '';
sabri_phase4a_security_assert( false !== strpos( $workflow, 'node tests/run-phase4a-playground-tests.mjs' ), 'Second-QA workflow does not run the Phase 4A WordPress security test.' );
sabri_phase4a_security_assert( false !== strpos( $workflow, 'SABRI_PLUGIN_ZIP' ), 'Second-QA workflow does not bind the packaged security test.' );
sabri_phase4a_security_assert( false !== strpos( $workflow, 'github.ref_name' ), 'Second-QA concurrency is not normalized to the branch name.' );

if ( $failures ) {
	echo "FAILED\n";
	foreach ( $failures as $failure ) {
		echo '- ' . $failure . "\n";
	}
	exit( 1 );
}

echo "OK - Phase 4A security-hardening contracts, exact identifiers, scoped roles, protected metadata, rollback format, and second-QA bindings passed.\n";
