<?php
/**
 * Minimal WordPress stubs for behavior tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

$wp_version = '6.6';
$sabri_test_options = array();
$sabri_test_actions = array();
$sabri_test_filters = array();
$sabri_test_filter_overrides = array();
$sabri_test_shortcodes = array();
$sabri_test_rest_routes = array();
$sabri_test_transients = array();
$sabri_test_enqueued_styles = array();
$sabri_test_enqueued_scripts = array();
$sabri_test_update_log = array();
$sabri_test_terms = array();
$sabri_test_post_terms = array();
$sabri_test_current_user_id = 0;
$sabri_test_current_caps = array();
$sabri_test_user_roles = array(
	1 => array( 'administrator' ),
	2 => array( 'founder' ),
	3 => array( 'verified_doctor' ),
	4 => array( 'doctor' ),
	5 => array( 'student' ),
	6 => array( 'patient' ),
	7 => array( 'subscriber' ),
);
$sabri_test_users_by_id = array(
	1 => array( 'ID' => 1, 'user_email' => 'admin@example.com', 'display_name' => 'Admin User' ),
	2 => array( 'ID' => 2, 'user_email' => 'founder@example.com', 'display_name' => 'Founder User' ),
	3 => array( 'ID' => 3, 'user_email' => 'verified@example.com', 'display_name' => 'Verified Doctor' ),
	4 => array( 'ID' => 4, 'user_email' => 'doctor@example.com', 'display_name' => 'Unverified Doctor' ),
	5 => array( 'ID' => 5, 'user_email' => 'student@example.com', 'display_name' => 'Student User' ),
	6 => array( 'ID' => 6, 'user_email' => 'patient@example.com', 'display_name' => 'Patient User' ),
	7 => array( 'ID' => 7, 'user_email' => 'subscriber@example.com', 'display_name' => 'Subscriber User' ),
);
$sabri_test_tables = array();
$sabri_test_indexes = array();
$sabri_test_dbdelta_skip_table = '';
$sabri_test_dbdelta_skip_index = '';
$sabri_test_rows = array();
$sabri_test_posts = array();
$sabri_test_post_meta = array();
$sabri_test_filetype_override = null;
$sabri_test_next_post_id = 100;
$sabri_test_users = array(
	'user@example.com' => 42,
);
$sabri_test_is_admin = false;
$sabri_test_is_attachment = false;
$sabri_test_is_front_page = false;
$sabri_test_is_home = false;
$sabri_test_is_singular = false;
$sabri_test_singular_post_type = '';
$sabri_test_insert_post_error = false;
$sabri_test_insert_attachment_error = false;
$sabri_test_deleted_attachments = array();
$sabri_test_deleted_files = array();

function sabri_test_default_user_roles() {
	return array(
		1 => array( 'administrator' ),
		2 => array( 'founder' ),
		3 => array( 'verified_doctor' ),
		4 => array( 'doctor' ),
		5 => array( 'student' ),
		6 => array( 'patient' ),
		7 => array( 'subscriber' ),
	);
}

function sabri_test_reset_state( $reset_data = false ) {
	global $sabri_test_options, $sabri_test_update_log, $sabri_test_terms, $sabri_test_filter_overrides, $sabri_test_tables, $sabri_test_indexes, $sabri_test_dbdelta_skip_table, $sabri_test_dbdelta_skip_index, $sabri_test_rows, $sabri_test_posts, $sabri_test_post_meta, $sabri_test_post_terms, $sabri_test_filetype_override, $sabri_test_next_post_id, $sabri_test_transients, $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_user_roles, $sabri_test_is_admin, $sabri_test_is_attachment, $sabri_test_is_front_page, $sabri_test_is_home, $sabri_test_rest_routes, $sabri_test_enqueued_styles, $sabri_test_enqueued_scripts, $sabri_test_current_post_id;
	global $sabri_test_is_singular, $sabri_test_singular_post_type;
	global $sabri_test_insert_post_error, $sabri_test_insert_attachment_error, $sabri_test_deleted_attachments, $sabri_test_deleted_files;

	$sabri_test_current_user_id = 0;
	$sabri_test_current_caps = array();
	$sabri_test_user_roles = sabri_test_default_user_roles();
	$sabri_test_is_admin = false;
	$sabri_test_is_attachment = false;
	$sabri_test_is_front_page = false;
	$sabri_test_is_home = false;
	$sabri_test_is_singular = false;
	$sabri_test_singular_post_type = '';
	$sabri_test_filter_overrides = array();
	$sabri_test_rest_routes = array();
	$sabri_test_enqueued_styles = array();
	$sabri_test_enqueued_scripts = array();
	$sabri_test_current_post_id = 0;
	$sabri_test_filetype_override = null;
	$sabri_test_insert_post_error = false;
	$sabri_test_insert_attachment_error = false;
	$sabri_test_deleted_attachments = array();
	$sabri_test_deleted_files = array();

	$_GET = array();
	$_POST = array();
	$_REQUEST = array();
	$_FILES = array();
	unset( $_SERVER['HTTP_X_WP_NONCE'] );

	if ( $reset_data ) {
		$sabri_test_options = array();
		$sabri_test_update_log = array();
		$sabri_test_terms = array();
		$sabri_test_tables = array();
		$sabri_test_indexes = array();
		$sabri_test_dbdelta_skip_table = '';
		$sabri_test_dbdelta_skip_index = '';
		$sabri_test_rows = array();
		$sabri_test_posts = array();
		$sabri_test_post_meta = array();
		$sabri_test_post_terms = array();
		$sabri_test_next_post_id = 100;
		$sabri_test_transients = array();
	}

	if ( class_exists( 'Sabri\\HomeNewsFeed\\HomeIntegration' ) ) {
		\Sabri\HomeNewsFeed\HomeIntegration::reset_runtime_guards();
	}
}

function __( $text, $domain = null ) { unset( $domain ); return $text; }
function esc_html__( $text, $domain = null ) { unset( $domain ); return $text; }
function esc_attr__( $text, $domain = null ) { unset( $domain ); return $text; }
function esc_html_e( $text, $domain = null ) { echo esc_html__( $text, $domain ); }
function esc_attr_e( $text, $domain = null ) { echo esc_attr__( $text, $domain ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return (string) $text; }
function esc_url_raw( $text ) { return filter_var( (string) $text, FILTER_VALIDATE_URL ) ? (string) $text : ''; }
function is_email( $text ) { return false !== filter_var( (string) $text, FILTER_VALIDATE_EMAIL ); }
function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key ) ); }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_textarea_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function sanitize_file_name( $name ) { return basename( preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $name ) ); }
function wp_strip_all_tags( $text ) { return strip_tags( (string) $text ); }
function wp_kses_post( $text ) { return strip_tags( (string) $text, '<p><br><strong><em><b><i><ul><ol><li><a><blockquote><code><pre>' ); }
function wpautop( $text ) { return '<p>' . str_replace( "\n\n", '</p><p>', trim( (string) $text ) ) . '</p>'; }
function wp_trim_words( $text, $num_words = 55, $more = null ) { $words = preg_split( '/\s+/', trim( (string) $text ) ); return implode( ' ', array_slice( $words, 0, $num_words ) ) . ( count( $words ) > $num_words ? ( null === $more ? '...' : $more ) : '' ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_unslash( $value ) { return $value; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_salt( $scheme = 'auth' ) { return 'test-salt-' . $scheme; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function plugin_dir_path( $file ) { return dirname( $file ) . DIRECTORY_SEPARATOR; }
function plugin_dir_url( $file ) { unset( $file ); return 'http://example.test/wp-content/plugins/sabri-complete-home-news-feed/'; }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function load_plugin_textdomain() { return true; }
function register_activation_hook() {}
function register_deactivation_hook() {}
function is_admin() { global $sabri_test_is_admin; return isset( $sabri_test_is_admin ) ? (bool) $sabri_test_is_admin : true; }
function is_front_page() { global $sabri_test_is_front_page; return (bool) $sabri_test_is_front_page; }
function is_home() { global $sabri_test_is_home; return (bool) $sabri_test_is_home; }
function current_user_can( $capability ) { global $sabri_test_current_caps, $sabri_test_current_user_id, $sabri_test_user_roles, $sabri_test_roles; if ( (int) $sabri_test_current_user_id <= 0 ) { return false; } if ( ! empty( $sabri_test_current_caps[ $capability ] ) ) { return true; } foreach ( isset( $sabri_test_user_roles[ $sabri_test_current_user_id ] ) ? $sabri_test_user_roles[ $sabri_test_current_user_id ] : array() as $role_slug ) { if ( ! empty( $sabri_test_roles[ $role_slug ]->capabilities[ $capability ] ) ) { return true; } } return false; }
function get_current_user_id() { global $sabri_test_current_user_id; return (int) $sabri_test_current_user_id; }
function is_user_logged_in() { return get_current_user_id() > 0; }
function wp_get_current_user() { return get_userdata( get_current_user_id() ); }
function get_userdata( $user_id ) { global $sabri_test_users_by_id, $sabri_test_user_roles; if ( empty( $sabri_test_users_by_id[ $user_id ] ) ) { return false; } $user = (object) $sabri_test_users_by_id[ $user_id ]; $user->roles = isset( $sabri_test_user_roles[ $user_id ] ) ? $sabri_test_user_roles[ $user_id ] : array(); return $user; }
function get_user_by( $field, $value ) { global $sabri_test_users, $sabri_test_users_by_id; if ( 'email' === $field && isset( $sabri_test_users[ $value ] ) ) { return (object) array( 'ID' => $sabri_test_users[ $value ], 'user_email' => $value ); } foreach ( $sabri_test_users_by_id as $user ) { if ( isset( $user[ $field ] ) && $user[ $field ] === $value ) { return (object) $user; } } return false; }
function add_action( $hook, $callback = null, $priority = 10, $args = 1 ) { global $sabri_test_actions; $sabri_test_actions[] = compact( 'hook', 'callback', 'priority', 'args' ); }
function add_filter( $hook, $callback = null, $priority = 10, $args = 1 ) { global $sabri_test_filters; $sabri_test_filters[] = compact( 'hook', 'callback', 'priority', 'args' ); }
function apply_filters( $hook, $value, ...$args ) { global $sabri_test_filter_overrides, $sabri_test_filters; $value = array_key_exists( $hook, $sabri_test_filter_overrides ) ? $sabri_test_filter_overrides[ $hook ] : $value; foreach ( $sabri_test_filters as $filter ) { if ( $filter['hook'] === $hook && is_callable( $filter['callback'] ) ) { $value = call_user_func_array( $filter['callback'], array_merge( array( $value ), array_slice( $args, 0, max( 0, (int) $filter['args'] - 1 ) ) ) ); } } return $value; }
function do_action( $hook, ...$args ) { global $sabri_test_actions; foreach ( $sabri_test_actions as $action ) { if ( $action['hook'] === $hook && is_callable( $action['callback'] ) ) { call_user_func_array( $action['callback'], array_slice( $args, 0, (int) $action['args'] ) ); } } }
function register_setting() {}
function register_post_meta() {}
function register_taxonomy() {}
function term_exists( $slug, $taxonomy ) { global $sabri_test_terms; return isset( $sabri_test_terms[ $taxonomy ][ $slug ] ); }
function wp_insert_term( $label, $taxonomy, $args = array() ) { global $sabri_test_terms; $slug = $args['slug']; $sabri_test_terms[ $taxonomy ][ $slug ] = $label; return array( 'term_id' => count( $sabri_test_terms[ $taxonomy ] ) ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function shortcode_exists( $shortcode ) { global $sabri_test_shortcodes; return isset( $sabri_test_shortcodes[ $shortcode ] ); }
function add_shortcode( $shortcode, $callback ) { global $sabri_test_shortcodes; $sabri_test_shortcodes[ $shortcode ] = $callback; }
function has_shortcode( $content, $shortcode ) { return false !== strpos( (string) $content, '[' . $shortcode ); }
function shortcode_atts( $pairs, $atts ) { return array_merge( $pairs, is_array( $atts ) ? $atts : array() ); }
function post_type_exists( $post_type ) { return in_array( $post_type, array( 'post', 'product' ), true ); }
function update_option( $name, $value, $autoload = null ) { global $sabri_test_options, $sabri_test_update_log; unset( $autoload ); $old = array_key_exists( $name, $sabri_test_options ) ? $sabri_test_options[ $name ] : false; $sabri_test_options[ $name ] = $value; $sabri_test_update_log[] = $name; do_action( 'updated_option', $name, $old, $value ); return true; }
function get_option( $name, $default = false ) { global $sabri_test_options; return array_key_exists( $name, $sabri_test_options ) ? $sabri_test_options[ $name ] : $default; }
function delete_option( $name ) { global $sabri_test_options; unset( $sabri_test_options[ $name ] ); return true; }
function set_transient( $name, $value, $expiration = 0 ) { global $sabri_test_transients; unset( $expiration ); $sabri_test_transients[ $name ] = $value; return true; }
function get_transient( $name ) { global $sabri_test_transients; return array_key_exists( $name, $sabri_test_transients ) ? $sabri_test_transients[ $name ] : false; }
function delete_transient( $name = null ) { global $sabri_test_transients; if ( null === $name ) { $sabri_test_transients = array(); } else { unset( $sabri_test_transients[ $name ] ); } return true; }
function flush_rewrite_rules() { return true; }
function register_rest_route( $namespace, $route, $args ) { global $sabri_test_rest_routes; $sabri_test_rest_routes[ $namespace . $route ] = $args; }
function wp_upload_dir() { return array( 'basedir' => sys_get_temp_dir(), 'error' => false ); }
function wp_register_style() {}
function wp_register_script() {}
function wp_enqueue_style( $handle ) { global $sabri_test_enqueued_styles; $sabri_test_enqueued_styles[] = $handle; }
function wp_enqueue_script( $handle ) { global $sabri_test_enqueued_scripts; $sabri_test_enqueued_scripts[] = $handle; }
function wp_nonce_field() { echo '<input type="hidden" name="_wpnonce" value="nonce" />'; }
function wp_verify_nonce( $nonce, $action = -1 ) { unset( $action ); return 'nonce' === $nonce || 'rest-nonce' === $nonce; }
function check_admin_referer() { return true; }
function admin_url( $path = '' ) { return 'http://example.test/wp-admin/' . ltrim( $path, '/' ); }
function home_url( $path = '' ) { return 'http://example.test/' . ltrim( $path, '/' ); }
function rest_url( $path = '' ) { return 'http://example.test/wp-json/' . ltrim( $path, '/' ); }
function get_pagenum_link( $page = 1 ) { return add_query_arg( array( 'paged' => $page ), home_url( '/' ) ); }
function add_query_arg( $args, $url = '' ) { $parts = parse_url( $url ); $query = array(); if ( ! empty( $parts['query'] ) ) { parse_str( $parts['query'], $query ); } foreach ( (array) $args as $key => $value ) { $query[ $key ] = $value; } $base = ( isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '' ) . ( isset( $parts['host'] ) ? $parts['host'] : '' ) . ( isset( $parts['path'] ) ? $parts['path'] : '' ); return $base . ( $query ? '?' . http_build_query( $query ) : '' ); }
function paginate_links() { return '<ul><li><span>1</span></li></ul>'; }
function checked( $checked ) { echo $checked ? 'checked="checked"' : ''; }
function selected( $selected, $current ) { echo (string) $selected === (string) $current ? 'selected="selected"' : ''; }
function submit_button( $text ) { echo '<button type="submit">' . esc_html( $text ) . '</button>'; }
function wp_safe_redirect() { return true; }
function wp_die( $message = '' ) { throw new Exception( (string) $message ); }
function status_header() {}
function nocache_headers() {}
function is_attachment() { global $sabri_test_is_attachment; return (bool) $sabri_test_is_attachment; }
function is_singular( $post_type = '' ) { global $sabri_test_is_singular, $sabri_test_singular_post_type; if ( ! $sabri_test_is_singular ) { return false; } return '' === $post_type || $post_type === $sabri_test_singular_post_type; }
function in_the_loop() { return true; }
function is_main_query() { return true; }
function get_queried_object_id() { global $sabri_test_current_post_id; return isset( $sabri_test_current_post_id ) ? (int) $sabri_test_current_post_id : 0; }
function get_queried_object() { return get_post( get_queried_object_id() ); }

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
	'subscriber'      => new Sabri_Test_Role( array( 'read' => true ) ),
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

class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

class WP_REST_Response {
	private $data;
	private $status;
	public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; }
	public function set_data( $data ) { $this->data = $data; }
	public function get_data() { return $this->data; }
	public function get_status() { return $this->status; }
}

function sabri_test_add_post( $args = array(), $meta = array(), $terms = array() ) {
	global $sabri_test_posts, $sabri_test_post_meta, $sabri_test_post_terms, $sabri_test_next_post_id;
	$id = isset( $args['ID'] ) ? (int) $args['ID'] : $sabri_test_next_post_id++;
	$defaults = array(
		'ID'                => $id,
		'post_author'       => 1,
		'post_status'       => 'publish',
		'post_type'         => 'post',
		'post_title'        => 'Post ' . $id,
		'post_content'      => 'Post content ' . $id,
		'post_excerpt'      => '',
		'post_date'         => gmdate( 'Y-m-d H:i:s', time() - ( $id * 60 ) ),
		'post_modified'     => gmdate( 'Y-m-d H:i:s', time() - ( $id * 30 ) ),
		'post_mime_type'    => '',
		'post_parent'       => 0,
	);
	$sabri_test_posts[ $id ] = (object) array_merge( $defaults, $args, array( 'ID' => $id ) );
	$sabri_test_post_meta[ $id ] = isset( $sabri_test_post_meta[ $id ] ) ? $sabri_test_post_meta[ $id ] : array();
	foreach ( $meta as $key => $value ) {
		$sabri_test_post_meta[ $id ][ $key ] = $value;
	}
	foreach ( $terms as $taxonomy => $values ) {
		$sabri_test_post_terms[ $id ][ $taxonomy ] = (array) $values;
	}
	return $id;
}

function get_post( $post_id ) { global $sabri_test_posts; return isset( $sabri_test_posts[ (int) $post_id ] ) ? $sabri_test_posts[ (int) $post_id ] : null; }
function get_post_field( $field, $post_id ) { $post = get_post( $post_id ); return $post && isset( $post->$field ) ? $post->$field : ''; }
function get_post_status( $post_id ) { return get_post_field( 'post_status', $post_id ); }
function get_post_meta( $post_id, $key = '', $single = false ) { global $sabri_test_post_meta; $post_id = (int) $post_id; if ( '' === $key ) { return isset( $sabri_test_post_meta[ $post_id ] ) ? $sabri_test_post_meta[ $post_id ] : array(); } if ( ! isset( $sabri_test_post_meta[ $post_id ][ $key ] ) ) { return $single ? '' : array(); } return $single ? $sabri_test_post_meta[ $post_id ][ $key ] : array( $sabri_test_post_meta[ $post_id ][ $key ] ); }
function update_post_meta( $post_id, $key, $value ) { global $sabri_test_post_meta; $sabri_test_post_meta[ (int) $post_id ][ $key ] = $value; return true; }
function delete_post_meta( $post_id, $key ) { global $sabri_test_post_meta; unset( $sabri_test_post_meta[ (int) $post_id ][ $key ] ); return true; }
function wp_insert_post( $postarr, $wp_error = false ) { global $sabri_test_insert_post_error; unset( $wp_error ); if ( $sabri_test_insert_post_error ) { return new WP_Error( 'insert_failed', 'Insert failed' ); } return sabri_test_add_post( $postarr ); }
function wp_update_post( $postarr, $wp_error = false ) { global $sabri_test_posts; unset( $wp_error ); if ( empty( $postarr['ID'] ) || empty( $sabri_test_posts[ (int) $postarr['ID'] ] ) ) { return new WP_Error( 'missing_post', 'Missing post' ); } $id = (int) $postarr['ID']; foreach ( $postarr as $key => $value ) { $sabri_test_posts[ $id ]->$key = $value; } return $id; }
function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) { global $sabri_test_post_terms; unset( $append ); $sabri_test_post_terms[ (int) $object_id ][ $taxonomy ] = (array) $terms; return true; }
function get_the_terms( $post_id, $taxonomy ) { global $sabri_test_post_terms; $terms = isset( $sabri_test_post_terms[ (int) $post_id ][ $taxonomy ] ) ? $sabri_test_post_terms[ (int) $post_id ][ $taxonomy ] : array(); return array_map( static function ( $term ) { return (object) array( 'slug' => sanitize_key( $term ), 'name' => ucwords( str_replace( '-', ' ', $term ) ) ); }, $terms ); }
function has_term( $term, $taxonomy, $post_id ) { global $sabri_test_post_terms; return in_array( $term, isset( $sabri_test_post_terms[ (int) $post_id ][ $taxonomy ] ) ? $sabri_test_post_terms[ (int) $post_id ][ $taxonomy ] : array(), true ); }
function get_the_ID() { global $sabri_test_current_post_id; return isset( $sabri_test_current_post_id ) ? (int) $sabri_test_current_post_id : 0; }
function get_permalink( $post_id ) { return home_url( '?p=' . (int) $post_id ); }
function get_the_title( $post_id ) { return get_post_field( 'post_title', $post_id ); }
function get_the_excerpt( $post_id ) { $excerpt = get_post_field( 'post_excerpt', $post_id ); return $excerpt ? $excerpt : wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 32, '' ); }
function get_the_date( $format = '', $post_id = 0 ) { unset( $format ); return substr( get_post_field( 'post_date', $post_id ), 0, 10 ); }
function get_the_time( $format = '', $post_id = 0 ) { unset( $format ); return substr( get_post_field( 'post_date', $post_id ), 11, 5 ); }
function get_post_time( $format = 'U', $gmt = false, $post_id = 0 ) { unset( $format, $gmt ); return strtotime( get_post_field( 'post_date', $post_id ) ); }
function get_post_modified_time( $format = 'U', $gmt = false, $post_id = 0 ) { unset( $format, $gmt ); return strtotime( get_post_field( 'post_modified', $post_id ) ); }
function get_avatar( $user_id ) { return '<img alt="" class="avatar" src="avatar-' . (int) $user_id . '.png" />'; }
function get_the_author_meta( $field, $user_id ) { $user = get_userdata( $user_id ); return $user && isset( $user->$field ) ? $user->$field : ''; }
function has_post_thumbnail( $post_id ) { return (bool) get_post_meta( $post_id, '_thumbnail_id', true ); }
function get_the_post_thumbnail( $post_id ) { return '<img alt="" src="featured-' . (int) $post_id . '.jpg" />'; }
function wp_get_attachment_image( $attachment_id ) { return '<img alt="" src="attachment-' . (int) $attachment_id . '.jpg" />'; }
function wp_get_attachment_url( $attachment_id ) { return home_url( 'uploads/attachment-' . (int) $attachment_id ); }
function get_post_mime_type( $post_id ) { return get_post_field( 'post_mime_type', $post_id ); }
function wp_check_filetype_and_ext( $file, $filename, $mimes = array() ) { global $sabri_test_filetype_override; unset( $file ); if ( is_array( $sabri_test_filetype_override ) ) { return $sabri_test_filetype_override; } $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ); foreach ( $mimes as $pattern => $mime ) { if ( in_array( $ext, explode( '|', $pattern ), true ) ) { return array( 'ext' => $ext, 'type' => $mime ); } } return array( 'ext' => $ext, 'type' => '' ); }
function wp_handle_upload( $file, $overrides = array() ) { unset( $overrides ); return array( 'file' => isset( $file['tmp_name'] ) ? $file['tmp_name'] : sys_get_temp_dir() . '/' . $file['name'], 'url' => home_url( 'uploads/' . $file['name'] ), 'type' => $file['type'] ); }
function wp_insert_attachment( $args, $file = '' ) { global $sabri_test_insert_attachment_error; unset( $file ); if ( $sabri_test_insert_attachment_error ) { return new WP_Error( 'attachment_failed', 'Attachment failed' ); } return sabri_test_add_post( array_merge( $args, array( 'post_type' => 'attachment' ) ) ); }
function wp_generate_attachment_metadata() { return array(); }
function wp_update_attachment_metadata() { return true; }
function wp_delete_attachment( $attachment_id, $force_delete = false ) { global $sabri_test_posts, $sabri_test_post_meta, $sabri_test_post_terms, $sabri_test_deleted_attachments; unset( $force_delete ); $attachment_id = (int) $attachment_id; if ( empty( $sabri_test_posts[ $attachment_id ] ) ) { return false; } unset( $sabri_test_posts[ $attachment_id ], $sabri_test_post_meta[ $attachment_id ], $sabri_test_post_terms[ $attachment_id ] ); $sabri_test_deleted_attachments[] = $attachment_id; return true; }
function wp_delete_file( $path ) { global $sabri_test_deleted_files; $sabri_test_deleted_files[] = (string) $path; return true; }

class WP_Query {
	public $posts = array();
	public $found_posts = 0;
	public $max_num_pages = 0;
	private $query_vars = array();
	public function __construct( $args = array() ) {
		global $sabri_test_posts;
		$this->query_vars = $args;
		$posts = array_values( $sabri_test_posts );
		$posts = array_values( array_filter( $posts, array( $this, 'matches' ) ) );
		usort( $posts, static function ( $a, $b ) { return strtotime( $b->post_date ) <=> strtotime( $a->post_date ); } );
		$this->found_posts = count( $posts );
		$per_page = isset( $args['posts_per_page'] ) ? max( 1, (int) $args['posts_per_page'] ) : 10;
		$page = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$this->max_num_pages = (int) ceil( $this->found_posts / $per_page );
		$this->posts = array_slice( $posts, ( $page - 1 ) * $per_page, $per_page );
	}
	public function is_main_query() { return true; }
	public function get( $key ) { return isset( $this->query_vars[ $key ] ) ? $this->query_vars[ $key ] : null; }
	public function set( $key, $value ) { $this->query_vars[ $key ] = $value; }
	private function matches( $post ) {
		$args = $this->query_vars;
		if ( isset( $args['p'] ) && (int) $post->ID !== (int) $args['p'] ) { return false; }
		if ( isset( $args['name'] ) && (string) get_post_field( 'post_name', $post->ID ) !== (string) $args['name'] ) { return false; }
		if ( isset( $args['post_type'] ) ) {
			$allowed_types = (array) $args['post_type'];
			if ( ! in_array( $post->post_type, $allowed_types, true ) ) { return false; }
		}
		if ( isset( $args['post__not_in'] ) && in_array( (int) $post->ID, array_map( 'intval', (array) $args['post__not_in'] ), true ) ) { return false; }
		if ( isset( $args['post_status'] ) ) { $allowed_statuses = (array) $args['post_status']; if ( ! in_array( 'any', $allowed_statuses, true ) && ! in_array( $post->post_status, $allowed_statuses, true ) ) { return false; } }
		if ( isset( $args['meta_query'] ) && ! $this->matches_meta_query( $post->ID, $args['meta_query'] ) ) { return false; }
		if ( isset( $args['tax_query'] ) && ! $this->matches_tax_query( $post->ID, $args['tax_query'] ) ) { return false; }
		return true;
	}
	private function matches_meta_query( $post_id, $query ) {
		$relation = isset( $query['relation'] ) ? strtoupper( $query['relation'] ) : 'AND';
		$results = array();
		foreach ( $query as $clause ) {
			if ( ! is_array( $clause ) ) { continue; }
			if ( isset( $clause['key'] ) ) {
				$value = get_post_meta( $post_id, $clause['key'], true );
				$exists = '' !== $value;
				$compare = isset( $clause['compare'] ) ? strtoupper( $clause['compare'] ) : '=';
				if ( 'NOT EXISTS' === $compare ) { $results[] = ! $exists; }
				elseif ( 'IN' === $compare ) { $results[] = in_array( $value, (array) $clause['value'], true ); }
				elseif ( 'NOT IN' === $compare ) { $results[] = ! in_array( $value, (array) $clause['value'], true ); }
				else { $results[] = $value === $clause['value']; }
			} else {
				$results[] = $this->matches_meta_query( $post_id, $clause );
			}
		}
		return 'OR' === $relation ? in_array( true, $results, true ) : ! in_array( false, $results, true );
	}
	private function matches_tax_query( $post_id, $query ) {
		foreach ( $query as $clause ) {
			if ( ! is_array( $clause ) || empty( $clause['taxonomy'] ) || empty( $clause['terms'] ) ) { continue; }
			foreach ( (array) $clause['terms'] as $term ) {
				if ( has_term( $term, $clause['taxonomy'], $post_id ) ) { return true; }
			}
			return false;
		}
		return true;
	}
}

class Sabri_Test_WPDB {
	public $prefix = 'wp_';
	public $posts = 'wp_posts';
	public $insert_id = 0;
	public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'; }
	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }
		foreach ( $args as $arg ) {
			$replacement = is_numeric( $arg ) ? (string) $arg : "'" . addslashes( (string) $arg ) . "'";
			$query = preg_replace( '/%d|%s/', $replacement, $query, 1 );
		}
		return $query;
	}
	public function get_var( $query ) {
		global $sabri_test_tables;
		if ( preg_match( "/SHOW TABLES LIKE '([^']+)'/", $query, $matches ) ) {
			return ! empty( $sabri_test_tables[ $matches[1] ] ) ? $matches[1] : null;
		}
		return 0;
	}
	public function get_results( $query, $output = null ) {
		global $sabri_test_indexes, $sabri_test_rows;
		unset( $output );
		if ( preg_match( '/SHOW INDEX FROM `([^`]+)`/', $query, $matches ) ) {
			return isset( $sabri_test_indexes[ $matches[1] ] ) ? array_values( $sabri_test_indexes[ $matches[1] ] ) : array();
		}
		if ( preg_match( '/SELECT \* FROM `([^`]+)` WHERE `([^`]+)` = ([0-9]+) ORDER BY id ASC/', $query, $matches ) ) {
			$table = $matches[1];
			$column = $matches[2];
			$user_id = (int) $matches[3];
			$rows = isset( $sabri_test_rows[ $table ] ) ? $sabri_test_rows[ $table ] : array();
			$rows = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $column, $user_id ) {
						return isset( $row[ $column ] ) && (int) $row[ $column ] === $user_id;
					}
				)
			);
			usort(
				$rows,
				static function ( $a, $b ) {
					return (int) $a['id'] <=> (int) $b['id'];
				}
			);
			return $rows;
		}
		return array();
	}
	public function insert( $table, $data, $formats = null ) { unset( $table, $data, $formats ); $this->insert_id++; return true; }
	public function update( $table, $data, $where, $formats = null, $where_formats = null ) { unset( $table, $data, $where, $formats, $where_formats ); return true; }
	public function query( $query ) {
		global $sabri_test_tables, $sabri_test_indexes;
		if ( preg_match( '/DROP TABLE IF EXISTS `([^`]+)`/', $query, $matches ) ) {
			unset( $sabri_test_tables[ $matches[1] ], $sabri_test_indexes[ $matches[1] ] );
		}
		return true;
	}
}

$wpdb = new Sabri_Test_WPDB();

function dbDelta( $sql ) {
	global $sabri_test_tables, $sabri_test_indexes, $sabri_test_dbdelta_skip_table, $sabri_test_dbdelta_skip_index;

	if ( ! preg_match( '/CREATE TABLE\s+`?([^`\s(]+)`?/i', $sql, $matches ) ) {
		return array();
	}

	$table = $matches[1];
	if ( $table === $sabri_test_dbdelta_skip_table ) {
		return array( $table => 'skipped' );
	}

	$sabri_test_tables[ $table ] = true;
	$sabri_test_indexes[ $table ] = array();

	foreach ( preg_split( '/\R/', $sql ) as $line ) {
		$line = trim( $line, " \t\n\r\0\x0B," );
		if ( preg_match( '/^PRIMARY KEY/i', $line ) ) {
			if ( 'PRIMARY' !== $sabri_test_dbdelta_skip_index ) {
				$sabri_test_indexes[ $table ]['PRIMARY'] = array( 'Key_name' => 'PRIMARY', 'Non_unique' => 0 );
			}
		} elseif ( preg_match( '/^UNIQUE KEY\s+`?([^\s`(]+)/i', $line, $index_matches ) ) {
			if ( $index_matches[1] !== $sabri_test_dbdelta_skip_index ) {
				$sabri_test_indexes[ $table ][ $index_matches[1] ] = array( 'Key_name' => $index_matches[1], 'Non_unique' => 0 );
			}
		} elseif ( preg_match( '/^KEY\s+`?([^\s`(]+)/i', $line, $index_matches ) ) {
			if ( $index_matches[1] !== $sabri_test_dbdelta_skip_index ) {
				$sabri_test_indexes[ $table ][ $index_matches[1] ] = array( 'Key_name' => $index_matches[1], 'Non_unique' => 1 );
			}
		}
	}

	return array( $table => 'created' );
}
