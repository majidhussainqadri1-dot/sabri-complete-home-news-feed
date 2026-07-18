<?php
/**
 * Minimal WordPress stubs for behavior tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

$wp_version = '6.6';
$sabri_test_options = array();
$sabri_test_actions = array();
$sabri_test_filters = array();
$sabri_test_update_log = array();
$sabri_test_terms = array();
$sabri_test_current_caps = array( 'manage_options' => true, 'sabri_feed_manage_settings' => true );

function __( $text, $domain = null ) { unset( $domain ); return $text; }
function esc_html__( $text, $domain = null ) { unset( $domain ); return $text; }
function esc_html_e( $text, $domain = null ) { echo esc_html__( $text, $domain ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return (string) $text; }
function esc_url_raw( $text ) { return filter_var( (string) $text, FILTER_VALIDATE_URL ) ? (string) $text : ''; }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) ); }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return $value; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_salt( $scheme = 'auth' ) { return 'test-salt-' . $scheme; }
function plugin_dir_path( $file ) { return dirname( $file ) . DIRECTORY_SEPARATOR; }
function plugin_dir_url( $file ) { unset( $file ); return 'http://example.test/wp-content/plugins/sabri-complete-home-news-feed/'; }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function load_plugin_textdomain() { return true; }
function register_activation_hook() {}
function register_deactivation_hook() {}
function is_admin() { return true; }
function current_user_can( $capability ) { global $sabri_test_current_caps; return ! empty( $sabri_test_current_caps[ $capability ] ); }
function get_current_user_id() { return 1; }
function add_action( $hook, $callback = null, $priority = 10, $args = 1 ) { global $sabri_test_actions; $sabri_test_actions[] = compact( 'hook', 'callback', 'priority', 'args' ); }
function add_filter( $hook, $callback = null, $priority = 10, $args = 1 ) { global $sabri_test_filters; $sabri_test_filters[] = compact( 'hook', 'callback', 'priority', 'args' ); }
function register_setting() {}
function register_post_meta() {}
function register_taxonomy() {}
function term_exists( $slug, $taxonomy ) { global $sabri_test_terms; return isset( $sabri_test_terms[ $taxonomy ][ $slug ] ); }
function wp_insert_term( $label, $taxonomy, $args = array() ) { global $sabri_test_terms; $slug = $args['slug']; $sabri_test_terms[ $taxonomy ][ $slug ] = $label; return array( 'term_id' => count( $sabri_test_terms[ $taxonomy ] ) ); }
function is_wp_error( $value ) { return false; }
function shortcode_exists( $shortcode ) { unset( $shortcode ); return false; }
function post_type_exists( $post_type ) { return in_array( $post_type, array( 'post', 'product' ), true ); }
function update_option( $name, $value, $autoload = null ) { global $sabri_test_options, $sabri_test_update_log; unset( $autoload ); $sabri_test_options[ $name ] = $value; $sabri_test_update_log[] = $name; return true; }
function get_option( $name, $default = false ) { global $sabri_test_options; return array_key_exists( $name, $sabri_test_options ) ? $sabri_test_options[ $name ] : $default; }
function delete_option( $name ) { global $sabri_test_options; unset( $sabri_test_options[ $name ] ); return true; }
function delete_transient() { return true; }
function flush_rewrite_rules() { return true; }
function register_rest_route( $namespace, $route, $args ) { global $sabri_test_rest_routes; $sabri_test_rest_routes[ $namespace . $route ] = $args; }
function wp_upload_dir() { return array( 'basedir' => sys_get_temp_dir(), 'error' => false ); }

class Sabri_Test_Role {
	public $capabilities = array();
	public function __construct( $capabilities = array() ) { $this->capabilities = $capabilities; }
	public function add_cap( $capability ) { $this->capabilities[ $capability ] = true; }
	public function remove_cap( $capability ) { unset( $this->capabilities[ $capability ] ); }
}

$sabri_test_roles = array(
	'administrator'   => new Sabri_Test_Role( array( 'manage_options' => true ) ),
	'editor'          => new Sabri_Test_Role( array( 'edit_posts' => true ) ),
	'founder'         => new Sabri_Test_Role(),
	'verified_doctor' => new Sabri_Test_Role(),
	'doctor'          => new Sabri_Test_Role(),
	'student'         => new Sabri_Test_Role(),
	'patient'         => new Sabri_Test_Role(),
	'subscriber'      => new Sabri_Test_Role(),
);

function get_role( $role_slug ) { global $sabri_test_roles; return isset( $sabri_test_roles[ $role_slug ] ) ? $sabri_test_roles[ $role_slug ] : null; }
function wp_roles() {
	global $sabri_test_roles;
	$out = new stdClass();
	$out->roles = array();
	foreach ( $sabri_test_roles as $slug => $role ) {
		$out->roles[ $slug ] = array( 'capabilities' => $role->capabilities );
	}
	return $out;
}

class Sabri_Test_WPDB {
	public $prefix = 'wp_';
	public $posts = 'wp_posts';
	public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'; }
	public function prepare( $query, ...$args ) {
		foreach ( $args as $arg ) {
			$replacement = is_numeric( $arg ) ? (string) $arg : "'" . addslashes( (string) $arg ) . "'";
			$query = preg_replace( '/%d|%s/', $replacement, $query, 1 );
		}
		return $query;
	}
	public function get_var( $query ) { unset( $query ); return 0; }
	public function get_results( $query, $output = null ) { unset( $query, $output ); return array(); }
	public function insert( $table, $data, $formats = null ) { unset( $table, $data, $formats ); return true; }
	public function update( $table, $data, $where, $formats = null, $where_formats = null ) { unset( $table, $data, $where, $formats, $where_formats ); return true; }
}

$wpdb = new Sabri_Test_WPDB();

function dbDelta( $sql ) { return array( $sql => 'created' ); }
