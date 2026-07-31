<?php
/** Regression tests for File 21 File 22 retention and recovery maintenance. */

declare(strict_types=1);

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'ARRAY_A', 'ARRAY_A' );
	$GLOBALS['maintenance_options'] = array();
	$GLOBALS['maintenance_posts'] = array();
	$GLOBALS['maintenance_meta'] = array();

	function __( string $text, string $domain = '' ): string { unset( $domain ); return $text; }
	function sanitize_key( string $key ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? ''; }
	function absint( mixed $value ): int { return abs( (int) $value ); }
	function maybe_unserialize( mixed $value ): mixed { return is_string( $value ) ? ( @unserialize( $value ) ?: $value ) : $value; }
	function get_option( string $key, mixed $default = false ): mixed { return $GLOBALS['maintenance_options'][ $key ] ?? $default; }
	function update_option( string $key, mixed $value, bool $autoload = true ): bool { unset( $autoload ); $GLOBALS['maintenance_options'][ $key ] = $value; return true; }
	function delete_option( string $key ): bool { if ( ! array_key_exists( $key, $GLOBALS['maintenance_options'] ) ) { return false; } unset( $GLOBALS['maintenance_options'][ $key ] ); return true; }
	function get_post_status( int $post_id ): string|false { return $GLOBALS['maintenance_posts'][ $post_id ]['status'] ?? false; }
	function get_post_type( int $post_id ): string|false { return $GLOBALS['maintenance_posts'][ $post_id ]['type'] ?? false; }
	function get_post_field( string $field, int $post_id ): mixed { return 'post_author' === $field ? ( $GLOBALS['maintenance_posts'][ $post_id ]['author'] ?? 0 ) : ''; }
	function get_post_meta( int $post_id, string $key, bool $single = false ): mixed { $value = $GLOBALS['maintenance_meta'][ $post_id ][ $key ] ?? ''; return $single ? $value : ( '' === $value ? array() : array( $value ) ); }
	function update_post_meta( int $post_id, string $key, mixed $value ): int { $GLOBALS['maintenance_meta'][ $post_id ][ $key ] = $value; return 1; }
	function delete_post_meta( int $post_id, string $key, mixed $value = '' ): bool {
		if ( ! isset( $GLOBALS['maintenance_meta'][ $post_id ][ $key ] ) ) { return false; }
		if ( '' !== $value && $GLOBALS['maintenance_meta'][ $post_id ][ $key ] !== $value ) { return false; }
		unset( $GLOBALS['maintenance_meta'][ $post_id ][ $key ] ); return true;
	}
	function get_posts( array $args ): array {
		$found = array();
		foreach ( $GLOBALS['maintenance_posts'] as $post_id => $post ) {
			if ( (int) ( $post['author'] ?? 0 ) !== (int) ( $args['author'] ?? 0 ) ) { continue; }
			$matches = true;
			foreach ( (array) ( $args['meta_query'] ?? array() ) as $clause ) {
				if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
				if ( ( $GLOBALS['maintenance_meta'][ $post_id ][ $clause['key'] ] ?? null ) !== ( $clause['value'] ?? null ) ) { $matches = false; }
			}
			if ( $matches ) { $found[] = $post_id; }
		}
		return array_slice( $found, 0, 2 );
	}

	final class MaintenanceWpdb {
		public string $options = 'wp_options';
		public function esc_like( string $value ): string { return $value; }
		public function prepare( string $query, mixed ...$args ): string { unset( $args ); return $query; }
		public function get_results( string $query, string $format ): array {
			unset( $query, $format );
			$rows = array();
			foreach ( $GLOBALS['maintenance_options'] as $name => $value ) {
				if ( str_starts_with( $name, 'sabri_hnf_file22_idem_' ) ) {
					$rows[] = array( 'option_name' => $name, 'option_value' => serialize( $value ) );
				}
			}
			return $rows;
		}
	}
	$GLOBALS['wpdb'] = new MaintenanceWpdb();
}

namespace Sabri\HomeNewsFeed {
	final class ComposerPermissions {
		public static function user_can_edit_post( int $post_id, int $user_id = 0 ): bool { return (int) ( $GLOBALS['maintenance_posts'][ $post_id ]['author'] ?? 0 ) === $user_id; }
	}

	require_once dirname( __DIR__ ) . '/includes/class-universal-composer-workflow-store.php';
	require_once dirname( __DIR__ ) . '/includes/class-universal-composer-workflow-maintenance.php';

	$prefix = 'sabri_hnf_file22_idem_1_';
	$now = time();
	$states = array(
		'completed' => array( 'post_id' => 11, 'post_status' => 'publish', 'state' => 'completed', 'expires_at' => $now - 10 ),
		'recoverable' => array( 'post_id' => 12, 'post_status' => 'draft', 'state' => 'recoverable', 'expires_at' => $now - 10 ),
		'processing_final' => array( 'post_id' => 13, 'post_status' => 'publish', 'state' => 'processing', 'expires_at' => $now - 10 ),
		'processing_draft' => array( 'post_id' => 14, 'post_status' => 'draft', 'state' => 'processing', 'expires_at' => $now - 10 ),
		'active' => array( 'post_id' => 15, 'post_status' => 'draft', 'state' => 'processing', 'expires_at' => $now + 600 ),
	);
	foreach ( $states as $name => $definition ) {
		$key_hash = hash( 'sha256', 'key-' . $name );
		$fingerprint = hash( 'sha256', 'payload-' . $name );
		$post_id = $definition['post_id'];
		$GLOBALS['maintenance_posts'][ $post_id ] = array( 'type' => 'post', 'status' => $definition['post_status'], 'author' => 1 );
		$GLOBALS['maintenance_meta'][ $post_id ] = array(
			'_sabri_hnf_file22_idempotency_hash' => $key_hash,
			'_sabri_hnf_file22_payload_fingerprint' => $fingerprint,
		);
		$GLOBALS['maintenance_options'][ $prefix . $name ] = array(
			'state' => $definition['state'],
			'user_id' => 1,
			'key_hash' => $key_hash,
			'fingerprint' => $fingerprint,
			'native_reference' => 'post-' . $post_id,
			'created_at' => $now - 1000,
			'updated_at' => $now - 1000,
			'expires_at' => $definition['expires_at'],
		);
	}

	$report = UniversalComposerWorkflowMaintenance::reconcile_and_cleanup( 100 );
	$failures = array();
	$assert = static function ( bool $condition, string $message ) use ( &$failures ): void { if ( ! $condition ) { $failures[] = $message; } };
	$assert( ! isset( $GLOBALS['maintenance_options'][ $prefix . 'completed' ] ), 'Expired completed record was renewed instead of deleted.' );
	$assert( ! isset( $GLOBALS['maintenance_options'][ $prefix . 'recoverable' ] ), 'Expired recoverable record was renewed instead of deleted.' );
	$final_record = $GLOBALS['maintenance_options'][ $prefix . 'processing_final' ] ?? array();
	$assert( 'completed' === ( $final_record['state'] ?? '' ) && (int) ( $final_record['expires_at'] ?? 0 ) > $now, 'Expired processing final record was not reconciled.' );
	$draft_record = $GLOBALS['maintenance_options'][ $prefix . 'processing_draft' ] ?? array();
	$assert( 'recoverable' === ( $draft_record['state'] ?? '' ) && (int) ( $draft_record['expires_at'] ?? 0 ) > $now, 'Expired processing draft did not receive one recovery interval.' );
	$active_record = $GLOBALS['maintenance_options'][ $prefix . 'active' ] ?? array();
	$assert( 'processing' === ( $active_record['state'] ?? '' ), 'Active processing record was changed by maintenance.' );
	$assert( 2 === (int) ( $report['deleted'] ?? 0 ), 'Retention deletion count is incorrect.' );
	$assert( 1 === (int) ( $report['reconciled'] ?? 0 ), 'Reconciliation count is incorrect.' );
	$assert( 1 === (int) ( $report['recoverable'] ?? 0 ), 'Recoverable count is incorrect.' );

	if ( $failures ) { fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL ); exit( 1 ); }
	echo "File 21 File 22 maintenance retention contracts passed.\n";
}
