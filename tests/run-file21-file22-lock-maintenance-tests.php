<?php
/** Regression tests for File 22 execution-lock retention. */

declare(strict_types=1);

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'ARRAY_A', 'ARRAY_A' );
	$GLOBALS['lock_options'] = array(
		'sabri_hnf_file22_exec_1_expired' => array( 'token' => 'expired-token', 'expires_at' => time() - 5 ),
		'sabri_hnf_file22_exec_1_active' => array( 'token' => 'active-token', 'expires_at' => time() + 120 ),
		'sabri_hnf_file22_exec_1_invalid' => 'not-an-array',
		'unrelated_option' => array( 'token' => 'unrelated', 'expires_at' => time() - 5 ),
	);

	function maybe_unserialize( mixed $value ): mixed { return is_string( $value ) ? ( @unserialize( $value ) ?: $value ) : $value; }
	function delete_option( string $key ): bool { if ( ! array_key_exists( $key, $GLOBALS['lock_options'] ) ) { return false; } unset( $GLOBALS['lock_options'][ $key ] ); return true; }

	final class LockWpdb {
		public string $options = 'wp_options';
		public function esc_like( string $value ): string { return $value; }
		public function prepare( string $query, mixed ...$args ): string { unset( $args ); return $query; }
		public function get_results( string $query, string $format ): array {
			unset( $query, $format );
			$rows = array();
			foreach ( $GLOBALS['lock_options'] as $name => $value ) {
				if ( str_starts_with( $name, 'sabri_hnf_file22_exec_' ) ) {
					$rows[] = array( 'option_name' => $name, 'option_value' => serialize( $value ) );
				}
			}
			return $rows;
		}
	}
	$GLOBALS['wpdb'] = new LockWpdb();
}

namespace Sabri\HomeNewsFeed {
	require_once dirname( __DIR__ ) . '/includes/class-universal-composer-execution-lock-maintenance.php';

	$deleted = UniversalComposerExecutionLockMaintenance::cleanup_expired( 100 );
	$failures = array();
	$assert = static function ( bool $condition, string $message ) use ( &$failures ): void { if ( ! $condition ) { $failures[] = $message; } };
	$assert( 2 === $deleted, 'Expired and malformed lock deletion count is incorrect.' );
	$assert( ! isset( $GLOBALS['lock_options']['sabri_hnf_file22_exec_1_expired'] ), 'Expired execution lock was retained.' );
	$assert( ! isset( $GLOBALS['lock_options']['sabri_hnf_file22_exec_1_invalid'] ), 'Malformed execution lock was retained.' );
	$assert( isset( $GLOBALS['lock_options']['sabri_hnf_file22_exec_1_active'] ), 'Active execution lock was deleted.' );
	$assert( isset( $GLOBALS['lock_options']['unrelated_option'] ), 'Unrelated option was deleted.' );

	if ( $failures ) { fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL ); exit( 1 ); }
	echo "File 21 File 22 execution-lock maintenance contracts passed.\n";
}
