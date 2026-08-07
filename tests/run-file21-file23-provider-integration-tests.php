<?php
/**
 * Focused static/contract gates for File 21 -> File 23 integration.
 *
 * These checks deliberately reject a duplicate dashboard backend, guessed
 * destinations, provider self-acceptance, and direct File 23 writes.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$files = array(
	'bridge'  => $root . '/includes/class-file23-publishing-dashboard-bridge.php',
	'adapter' => $root . '/includes/class-file23-publishing-dashboard-adapter-runtime.php',
	'plugin'  => $root . '/includes/class-plugin.php',
);

$passed = 0;
$failed = 0;
$assert = static function ( bool $condition, string $message ) use ( &$passed, &$failed ): void {
	if ( $condition ) {
		++$passed;
		echo "PASS: {$message}\n";
		return;
	}
	++$failed;
	fwrite( STDERR, "FAIL: {$message}\n" );
};

foreach ( $files as $label => $path ) {
	$assert( is_file( $path ), "{$label} source exists" );
}

$bridge  = file_get_contents( $files['bridge'] ) ?: '';
$adapter = file_get_contents( $files['adapter'] ) ?: '';
$plugin  = file_get_contents( $files['plugin'] ) ?: '';

$assert( str_contains( $bridge, "add_action( 'spdb/register_adapters'" ), 'registers on the exact File 23 hook' );
$assert( str_contains( $bridge, "'SPDB_Provider_Adapter'" ) && str_contains( $bridge, 'interface_exists( $interface )' ), 'load-order guards the base File 23 interface' );
$assert( str_contains( $bridge, "'SPDB_Workspace_Provider_Adapter'" ) && str_contains( $bridge, 'interface_exists( $interface )' ), 'load-order guards workspace projection interface' );
$assert( str_contains( $bridge, "'SPDB_Review_Calendar_Provider_Adapter'" ) && str_contains( $bridge, 'interface_exists( $interface )' ), 'load-order guards review/calendar interface' );
$assert( str_contains( $bridge, "record_error( 'sabri_home_news_feed'" ), 'missing runtime file is observable without a fatal' );
$assert( str_contains( $plugin, 'File23PublishingDashboardBridge::class' ), 'bridge participates in the canonical module graph' );

$assert( str_contains( $adapter, 'implements \\SPDB_Provider_Adapter, \\SPDB_Workspace_Provider_Adapter, \\SPDB_Review_Calendar_Provider_Adapter' ), 'adapter implements all required projection contracts' );
$assert( str_contains( $adapter, "private const PROVIDER_KEY = 'sabri_home_news_feed'" ), 'provider key is immutable and canonical' );
$assert( str_contains( $adapter, "return 'review_capable';" ), 'declares technical review capability only' );
$assert( str_contains( $adapter, "return array();\n\t}\n\n\tpublic function health_check" ), 'operation definitions remain empty and fail closed' );
$assert( str_contains( $adapter, 'file21_spdb_write_not_accepted' ), 'direct File 23 writes are explicitly denied' );
$assert( ! str_contains( $adapter, 'production_accepted' ), 'provider never self-declares production acceptance' );
$assert( ! str_contains( $adapter, 'staging_accepted' ), 'provider never self-declares staging acceptance' );
$assert( ! str_contains( $adapter, 'wp_update_post(' ) && ! str_contains( $adapter, 'update_post_meta(' ), 'adapter does not mutate native posts directly' );
$assert( ! str_contains( $adapter, 'wp_insert_post' ), 'adapter does not create duplicate native objects' );
$assert( ! str_contains( $adapter, '$wpdb->' ), 'adapter does not query companion tables directly' );
$assert( str_contains( $adapter, "'posts_per_page'         => \$per_page" ), 'inventory queries are bounded' );
$assert( str_contains( $adapter, "'author'] = (int) \$authorization['user_id']" ), 'own scope is author constrained' );
$assert( str_contains( $adapter, "current_user_can( 'spdb_view_review_queue' )" ), 'review projection rechecks File 23 capability' );
$assert( str_contains( $adapter, 'get_edit_post_link' ), 'native owner supplies edit/review destinations' );
$assert( str_contains( $adapter, 'get_preview_post_link' ), 'native owner supplies preview destinations' );
$assert( str_contains( $adapter, 'get_permalink' ), 'native owner supplies public destinations' );
$assert( str_contains( $adapter, "'post_status'            => array( 'future' )" ), 'calendar reads canonical scheduled objects' );
$assert( str_contains( $adapter, "'post_status'            => array( 'draft', 'pending' )" ), 'review queue reads canonical draft/pending objects' );
$assert( ! preg_match( '/patient|prescription|clinical_record|message_body|identity_document/i', $adapter ), 'projection does not introduce prohibited sensitive domains' );

printf( "File 21 -> File 23 provider integration: %d passed, %d failed.\n", $passed, $failed );
exit( 0 === $failed ? 0 : 1 );
