<?php
/**
 * Duplicate plugin-folder compatibility test.
 *
 * Simulates an older active copy loaded from a different folder before the
 * corrected staging candidate is activated.
 *
 * @package SabriCompleteHomeNewsFeed
 */

define( 'ABSPATH', __DIR__ . '/wp/' );
$wp_version = '7.0.1';

$sabri_duplicate_activation_hooks = array();
$sabri_duplicate_actions          = array();
$sabri_duplicate_deactivated      = array();
$sabri_duplicate_options          = array();

function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://example.test/' . basename( dirname( $file ) ) . '/'; }
function plugin_basename( $file ) {
	$file = str_replace( '\\', '/', (string) $file );
	if ( substr( $file, -strlen( '/sabri-complete-home-news-feed.php' ) ) === '/sabri-complete-home-news-feed.php' && false === strpos( $file, '/legacy-sabri-feed/' ) ) {
		return 'sabri-complete-home-news-feed/sabri-complete-home-news-feed.php';
	}
	return basename( dirname( $file ) ) . '/' . basename( $file );
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	global $sabri_duplicate_actions;
	$sabri_duplicate_actions[] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
}
function register_activation_hook( $file, $callback ) {
	global $sabri_duplicate_activation_hooks;
	$sabri_duplicate_activation_hooks[ str_replace( '\\', '/', $file ) ] = $callback;
}
function register_deactivation_hook() {}
function load_plugin_textdomain() { return true; }
function __( $text ) { return $text; }
function esc_html( $text ) { return $text; }
function get_plugins() {
	return array(
		'legacy-sabri-feed/sabri-complete-home-news-feed.php' => array(
			'Name'       => 'Sabri Complete Home and News Feed',
			'TextDomain' => 'sabri-complete-home-news-feed',
			'Version'    => '1.0.0',
		),
		'sabri-complete-home-news-feed/sabri-complete-home-news-feed.php' => array(
			'Name'       => 'Sabri Complete Home and News Feed',
			'TextDomain' => 'sabri-complete-home-news-feed',
			'Version'    => '1.0.5',
		),
	);
}
function deactivate_plugins( $plugin, $silent = false ) {
	global $sabri_duplicate_deactivated;
	unset( $silent );
	$sabri_duplicate_deactivated[] = $plugin;
}
function update_option( $name, $value, $autoload = null ) {
	global $sabri_duplicate_options;
	unset( $autoload );
	$sabri_duplicate_options[ $name ] = $value;
	return true;
}

/* Simulate an older copy's already-declared global bootstrap surface. */
define( 'SABRI_HNF_FILE', __DIR__ . '/legacy-sabri-feed/sabri-complete-home-news-feed.php' );
define( 'SABRI_HNF_VERSION', '1.0.0' );
function sabri_hnf_escape_html( $value ) { return $value; }
function sabri_hnf_php_supported() { return true; }
function sabri_hnf_wp_supported() { return true; }
function sabri_hnf_php_notice() {}
function sabri_hnf_wp_notice() {}
function sabri_hnf_register_safe_boot_notice() {}
function sabri_hnf_activate() {}
function sabri_hnf_deactivate() {}
function sabri_hnf_bootstrap() {}

$plugin_file = str_replace( '\\', '/', dirname( __DIR__ ) . '/sabri-complete-home-news-feed.php' );
require $plugin_file;

if ( empty( $sabri_duplicate_activation_hooks[ $plugin_file ] ) || ! is_callable( $sabri_duplicate_activation_hooks[ $plugin_file ] ) ) {
	fwrite( STDERR, "FAIL: corrected copy did not register duplicate-resolution activation callback.\n" );
	exit( 1 );
}

call_user_func( $sabri_duplicate_activation_hooks[ $plugin_file ] );

$expected_legacy = 'legacy-sabri-feed/sabri-complete-home-news-feed.php';
if ( array( $expected_legacy ) !== $sabri_duplicate_deactivated ) {
	fwrite( STDERR, 'FAIL: wrong duplicate copy was deactivated: ' . json_encode( $sabri_duplicate_deactivated ) . "\n" );
	exit( 1 );
}

$resolution = isset( $sabri_duplicate_options['sabri_hnf_duplicate_plugin_resolution'] ) ? $sabri_duplicate_options['sabri_hnf_duplicate_plugin_resolution'] : array();
if ( empty( $resolution['resolved'] ) || array( $expected_legacy ) !== $resolution['previous_copies'] ) {
	fwrite( STDERR, "FAIL: duplicate resolution record was not stored correctly.\n" );
	exit( 1 );
}

echo "Duplicate plugin-folder compatibility tests passed.\n";
