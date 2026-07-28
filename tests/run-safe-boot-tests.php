<?php
/**
 * Safe-boot compatibility tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\SafeBoot;

final class Sabri_SafeBoot_Good_Module {
	public static $registered = false;
	public static function register() {
		self::$registered = true;
	}
}

final class Sabri_SafeBoot_Failing_Module {
	public static function register() {
		throw new RuntimeException( 'Simulated Hostinger compatibility failure.' );
	}
}

$safe_boot_failures = array();
function sabri_safe_boot_assert( $condition, $message ) {
	global $safe_boot_failures;
	if ( ! $condition ) {
		$safe_boot_failures[] = $message;
	}
}

sabri_test_reset_state( true );
global $sabri_test_current_user_id, $sabri_test_current_caps;

SafeBoot::clear();
sabri_safe_boot_assert( ! SafeBoot::is_blocked(), 'Safe Boot must be clear by default.' );

$good = SafeBoot::register_module( Sabri_SafeBoot_Good_Module::class );
sabri_safe_boot_assert( $good && Sabri_SafeBoot_Good_Module::$registered, 'A healthy runtime module must register normally.' );
sabri_safe_boot_assert( ! SafeBoot::is_blocked(), 'A healthy module must not pause the plugin.' );

$failed = SafeBoot::register_module( Sabri_SafeBoot_Failing_Module::class );
$state  = SafeBoot::state();
sabri_safe_boot_assert( false === $failed, 'A throwing module must be contained instead of escaping.' );
sabri_safe_boot_assert( SafeBoot::is_blocked(), 'A contained module failure must pause later plugin bootstraps.' );
sabri_safe_boot_assert( ! empty( $state['fingerprint'] ) && 64 === strlen( $state['fingerprint'] ), 'Safe Boot must create a stable diagnostic fingerprint.' );
sabri_safe_boot_assert( isset( $state['module'] ) && 'sabri_safeboot_failing_module' === $state['module'], 'Safe Boot must identify the failing component.' );
sabri_safe_boot_assert( isset( $state['file'] ) && false === strpos( $state['file'], ABSPATH ), 'Stored diagnostics must not expose an absolute WordPress path.' );
sabri_safe_boot_assert( false !== strpos( $state['message'], 'Simulated Hostinger compatibility failure' ), 'Administrator diagnostic must retain a bounded useful error message.' );

$sabri_test_current_user_id = 1;
$sabri_test_current_caps    = array( 'manage_options' => true );
ob_start();
SafeBoot::admin_notice();
$notice = ob_get_clean();
sabri_safe_boot_assert( false !== strpos( $notice, 'Safe Boot is active' ), 'Administrator must receive a clear Safe Boot notice.' );
sabri_safe_boot_assert( false !== strpos( $notice, substr( $state['fingerprint'], 0, 12 ) ), 'Administrator notice must include the short diagnostic code.' );
sabri_safe_boot_assert( false === strpos( $notice, ABSPATH ), 'Administrator notice must not expose the absolute WordPress path.' );

SafeBoot::clear();
sabri_safe_boot_assert( ! SafeBoot::is_blocked(), 'Safe Boot reset must restore a clean retry state.' );

$missing = SafeBoot::register_module( 'Sabri\HomeNewsFeed\ClassThatDoesNotExist' );
sabri_safe_boot_assert( false === $missing && SafeBoot::is_blocked(), 'A missing packaged class must fail safely instead of causing a site-wide fatal.' );
SafeBoot::clear();

sabri_safe_boot_assert( '1.0.3' === SABRI_HNF_VERSION && '1.0.0' === SABRI_HNF_SCHEMA_VERSION, 'Safe Boot hardening must preserve runtime 1.0.1 and schema 1.0.0.' );

if ( ! empty( $safe_boot_failures ) ) {
	fwrite( STDERR, "Safe Boot tests failed:\n- " . implode( "\n- ", $safe_boot_failures ) . "\n" );
	exit( 1 );
}

echo "Safe Boot compatibility tests passed.\n";
