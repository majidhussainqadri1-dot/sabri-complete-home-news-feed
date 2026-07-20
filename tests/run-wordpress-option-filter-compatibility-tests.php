<?php
/**
 * WordPress dynamic option-filter compatibility regression tests.
 *
 * This standalone harness intentionally models WordPress get_option() behavior
 * more closely than the general lean test stubs. It protects against recursive
 * option_{$option} and default_option_{$option} filters that can exhaust PHP
 * memory on a real WordPress request.
 *
 * @package SabriCompleteHomeNewsFeed
 */

error_reporting( E_ALL );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['sabri_wp_option_test_options'] = array();
$GLOBALS['sabri_wp_option_test_filters'] = array();
$GLOBALS['sabri_wp_option_test_depth']   = 0;
$GLOBALS['sabri_wp_option_test_max_depth'] = 0;

function __( $text, $domain = null ) { unset( $domain ); return $text; }
function esc_html__( $text, $domain = null ) { unset( $domain ); return $text; }
function plugin_dir_path( $file ) { return dirname( $file ) . DIRECTORY_SEPARATOR; }
function plugin_dir_url( $file ) { unset( $file ); return 'http://example.test/wp-content/plugins/sabri-complete-home-news-feed/'; }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function register_activation_hook() {}
function register_deactivation_hook() {}
function load_plugin_textdomain() { return true; }
function add_action() {}
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function is_user_logged_in() { return false; }
function current_user_can() { return false; }

function add_filter( $hook, $callback = null, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['sabri_wp_option_test_filters'][ $hook ][] = array(
		'callback'      => $callback,
		'priority'      => (int) $priority,
		'accepted_args' => max( 1, (int) $accepted_args ),
	);
	usort(
		$GLOBALS['sabri_wp_option_test_filters'][ $hook ],
		static function ( $left, $right ) {
			return $left['priority'] <=> $right['priority'];
		}
	);
}

function apply_filters( $hook, $value, ...$args ) {
	foreach ( isset( $GLOBALS['sabri_wp_option_test_filters'][ $hook ] ) ? $GLOBALS['sabri_wp_option_test_filters'][ $hook ] : array() as $filter ) {
		$parameters = array_merge( array( $value ), array_slice( $args, 0, $filter['accepted_args'] - 1 ) );
		$value = call_user_func_array( $filter['callback'], $parameters );
	}
	return $value;
}

function get_option( $name, $default = false ) {
	$GLOBALS['sabri_wp_option_test_depth']++;
	$GLOBALS['sabri_wp_option_test_max_depth'] = max( $GLOBALS['sabri_wp_option_test_max_depth'], $GLOBALS['sabri_wp_option_test_depth'] );
	if ( $GLOBALS['sabri_wp_option_test_depth'] > 12 ) {
		throw new RuntimeException( 'Recursive WordPress get_option() filter detected for ' . $name );
	}

	try {
		if ( array_key_exists( $name, $GLOBALS['sabri_wp_option_test_options'] ) ) {
			return apply_filters( 'option_' . $name, $GLOBALS['sabri_wp_option_test_options'][ $name ], $name );
		}
		return apply_filters( 'default_option_' . $name, $default, $name, true );
	} finally {
		$GLOBALS['sabri_wp_option_test_depth']--;
	}
}

require_once dirname( __DIR__ ) . '/sabri-complete-home-news-feed.php';

use Sabri\HomeNewsFeed\Phase3FeatureSettings;
use Sabri\HomeNewsFeed\PollComposerIntegration;
use Sabri\HomeNewsFeed\Settings;

$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

PollComposerIntegration::register();

$GLOBALS['sabri_wp_option_test_options'][ Phase3FeatureSettings::OPTION_NAME ] = array( 'polls_enabled' => 1 );
$GLOBALS['sabri_wp_option_test_options'][ Settings::OPTION_NAME ] = Settings::defaults();

$stored = get_option( Settings::OPTION_NAME, array() );
$assert( in_array( 'poll', $stored['composer']['allowed_feed_types'], true ), 'Stored settings option must include Poll when configured.' );
$assert( Phase3FeatureSettings::enabled( 'polls_enabled' ), 'Full feature check must remain usable without recursively re-entering Settings option filters.' );

unset( $GLOBALS['sabri_wp_option_test_options'][ Settings::OPTION_NAME ] );
$defaults = get_option( Settings::OPTION_NAME, Settings::defaults() );
$assert( in_array( 'poll', $defaults['composer']['allowed_feed_types'], true ), 'Default settings option filter must include Poll without recursion.' );

$GLOBALS['sabri_wp_option_test_options'][ Phase3FeatureSettings::OPTION_NAME ] = array( 'polls_enabled' => 0 );
$GLOBALS['sabri_wp_option_test_options'][ Settings::OPTION_NAME ] = Settings::defaults();
$disabled = get_option( Settings::OPTION_NAME, array() );
$assert( ! in_array( 'poll', $disabled['composer']['allowed_feed_types'], true ), 'Poll must be removed when the isolated feature flag is disabled.' );
$assert( $GLOBALS['sabri_wp_option_test_max_depth'] <= 3, 'Dynamic option filters must stay bounded and non-recursive.' );

if ( $failures ) {
	fwrite( STDERR, "WordPress option-filter compatibility failures:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "WordPress option-filter compatibility tests passed.\n";
