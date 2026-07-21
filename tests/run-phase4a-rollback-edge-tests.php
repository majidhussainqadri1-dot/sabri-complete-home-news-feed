<?php
/**
 * Phase 4A exact rollback edge tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\NewsCapabilities;
use Sabri\HomeNewsFeed\Rollback;
use Sabri\HomeNewsFeed\Snapshot;

$failures = array();
function sabri_phase4a_rollback_edge_assert( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		$failures[] = $message;
	}
}

sabri_test_reset_state( true );
global $sabri_test_roles;
$sabri_test_roles = array(
	'administrator' => new Sabri_Test_Role( array( 'manage_options' => true, 'manage_news_settings' => true ) ),
	'reporter'      => new Sabri_Test_Role(),
);

$snapshot = Snapshot::capture_before_mutation( 'rollback-edge-baseline' );
// Simulate a role that did not exist in the immutable baseline but appears later.
unset( $snapshot['capability_roles']['reporter'] );
update_option( Snapshot::OPTION_NAME, $snapshot, false );

$sabri_test_roles['reporter']->add_cap( 'create_editorial_news' );
update_option(
	NewsCapabilities::MUTATION_OPTION,
	array(
		'managed_caps' => array(
			'reporter' => array( 'create_editorial_news' => true ),
		),
	),
	false
);

$report = Rollback::execute();
sabri_phase4a_rollback_edge_assert( empty( $sabri_test_roles['reporter']->capabilities['create_editorial_news'] ), 'Rollback did not remove a plugin-managed capability from a role absent at baseline.' );
sabri_phase4a_rollback_edge_assert( ! empty( $sabri_test_roles['administrator']->capabilities['manage_news_settings'] ), 'Rollback removed an administrator capability that existed at baseline.' );
sabri_phase4a_rollback_edge_assert(
	isset( $report['phase4_capabilities']['roles']['reporter']['create_editorial_news'] ) && 'removed_post_snapshot_role' === $report['phase4_capabilities']['roles']['reporter']['create_editorial_news'],
	'Rollback report did not record the post-snapshot role correction.'
);

if ( $failures ) {
	echo "FAILED\n";
	foreach ( $failures as $failure ) {
		echo '- ' . $failure . "\n";
	}
	exit( 1 );
}

echo "OK - Phase 4A post-snapshot role capabilities and pre-existing administrator authority rollback passed.\n";
