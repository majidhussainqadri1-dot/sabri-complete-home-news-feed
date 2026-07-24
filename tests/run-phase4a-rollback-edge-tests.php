<?php
/**
 * Phase 4A exact rollback edge tests.
 *
 * The assertions are exposed as an in-process function so the broader security
 * contract suite can reuse them without invoking a shell or a child process.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\NewsCapabilities;
use Sabri\HomeNewsFeed\Rollback;
use Sabri\HomeNewsFeed\Snapshot;

/**
 * Run the exact post-snapshot-role rollback edge assertions.
 *
 * @return string[] Failure messages; an empty array means the edge test passed.
 */
function sabri_phase4a_collect_rollback_edge_failures() {
	global $sabri_test_roles;

	$edge_failures = array();
	$assert = static function ( $condition, $message ) use ( &$edge_failures ) {
		if ( ! $condition ) {
			$edge_failures[] = $message;
		}
	};

	sabri_test_reset_state( true );
	try {
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
		$assert( empty( $sabri_test_roles['reporter']->capabilities['create_editorial_news'] ), 'Rollback did not remove a plugin-managed capability from a role absent at baseline.' );
		$assert( ! empty( $sabri_test_roles['administrator']->capabilities['manage_news_settings'] ), 'Rollback removed an administrator capability that existed at baseline.' );
		$assert(
			isset( $report['phase4_capabilities']['roles']['reporter']['create_editorial_news'] ) && 'removed_post_snapshot_role' === $report['phase4_capabilities']['roles']['reporter']['create_editorial_news'],
			'Rollback report did not record the post-snapshot role correction.'
		);
	} finally {
		sabri_test_reset_state( true );
	}

	return $edge_failures;
}

$script_filename = isset( $_SERVER['SCRIPT_FILENAME'] ) ? realpath( (string) $_SERVER['SCRIPT_FILENAME'] ) : false;
if ( false !== $script_filename && realpath( __FILE__ ) === $script_filename ) {
	$failures = sabri_phase4a_collect_rollback_edge_failures();
	if ( $failures ) {
		echo "FAILED\n";
		foreach ( $failures as $failure ) {
			echo '- ' . $failure . "\n";
		}
		exit( 1 );
	}

	echo "OK - Phase 4A post-snapshot role capabilities and pre-existing administrator authority rollback passed.\n";
}
