<?php
/** Loads the real File 22 interfaces and Workflow Coordinator against File 21. */

declare(strict_types=1);

namespace {
	$file22_root = getenv( 'FILE22_ROOT' );
	if ( ! is_string( $file22_root ) || '' === $file22_root ) { fwrite( STDERR, "FILE22_ROOT is required.\n" ); exit( 1 ); }
	$file22_root = rtrim( $file22_root, '/\\' );
	foreach ( array( 'includes/contracts/interface-adapter.php', 'includes/contracts/interface-diagnostic-adapter.php', 'includes/contracts/interface-workflow-adapter.php', 'includes/core/class-workflow-coordinator.php' ) as $relative ) {
		if ( ! is_file( $file22_root . '/' . $relative ) ) { fwrite( STDERR, 'Missing File 22 source: ' . $relative . PHP_EOL ); exit( 1 ); }
	}

	define( 'ABSPATH', __DIR__ . '/' );
	define( 'SABRI_HNF_VERSION', '1.0.3' );
	define( 'SABRI_HNF_SLUG', 'sabri-complete-home-news-feed' );
	define( 'SUPC_WORKFLOW_API_VERSION', '1.0.0' );
	define( 'AUTH_SALT', 'real-contract-test-salt' );
	$GLOBALS['real_options'] = array(); $GLOBALS['real_posts'] = array(); $GLOBALS['real_meta'] = array(); $GLOBALS['real_next_id'] = 400; $GLOBALS['real_current_user'] = 1;

	final class WP_Error {
		public function __construct( private string $code = '', private string $message = '', private mixed $data = null ) {}
		public function get_error_code(): string { return $this->code; }
		public function get_error_message(): string { return $this->message; }
		public function get_error_data(): mixed { return $this->data; }
	}
	function __( string $text, string $domain = '' ): string { unset( $domain ); return $text; }
	function sanitize_key( string $key ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? ''; }
	function wp_json_encode( mixed $value ): string|false { return json_encode( $value ); }
	function home_url( string $path = '' ): string { return 'https://example.test/' . ltrim( $path, '/' ); }
	function wp_validate_redirect( string $url, string $fallback = '' ): string { return str_starts_with( $url, 'https://example.test/' ) || str_starts_with( $url, '/' ) ? $url : $fallback; }
	function wp_parse_url( string $url ): array|false { return parse_url( $url ); }
	function wp_generate_uuid4(): string { static $i = 0; ++$i; return sprintf( '00000000-0000-4000-8000-%012d', $i ); }
	function do_action( string $hook, mixed ...$args ): void { unset( $hook, $args ); }
	function get_current_user_id(): int { return (int) $GLOBALS['real_current_user']; }
	function wp_salt( string $scheme = 'auth' ): string { return AUTH_SALT . '|' . $scheme; }
	function add_query_arg( array $args, string $url ): string { return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args ); }
	function get_option( string $key, mixed $default = false ): mixed { return $GLOBALS['real_options'][ $key ] ?? $default; }
	function add_option( string $key, mixed $value, string $deprecated = '', bool|string $autoload = true ): bool { unset( $deprecated, $autoload ); if ( isset( $GLOBALS['real_options'][ $key ] ) ) { return false; } $GLOBALS['real_options'][ $key ] = $value; return true; }
	function update_option( string $key, mixed $value, bool $autoload = true ): bool { unset( $autoload ); $GLOBALS['real_options'][ $key ] = $value; return true; }
	function delete_option( string $key ): bool { unset( $GLOBALS['real_options'][ $key ] ); return true; }
	function get_post_type( int $id ): string|false { return $GLOBALS['real_posts'][ $id ]['type'] ?? false; }
	function get_post_status( int $id ): string|false { return $GLOBALS['real_posts'][ $id ]['status'] ?? false; }
	function get_post_field( string $field, int $id ): mixed { return 'post_author' === $field ? ( $GLOBALS['real_posts'][ $id ]['author'] ?? 0 ) : ''; }
	function get_permalink( int $id ): string|false { return $GLOBALS['real_posts'][ $id ]['url'] ?? false; }
	function get_preview_post_link( int $id ): string { return 'https://example.test/?p=' . $id . '&preview=true'; }
	function get_post_meta( int $id, string $key, bool $single = false ): mixed { $v = $GLOBALS['real_meta'][ $id ][ $key ] ?? ''; return $single ? $v : ( '' === $v ? array() : array( $v ) ); }
	function update_post_meta( int $id, string $key, mixed $value ): int { $GLOBALS['real_meta'][ $id ][ $key ] = $value; return 1; }
	function delete_post_meta( int $id, string $key, mixed $value = '' ): bool { unset( $value, $GLOBALS['real_meta'][ $id ][ $key ] ); return true; }
	function get_posts( array $args ): array { $ids = array(); foreach ( $GLOBALS['real_posts'] as $id => $post ) { if ( (int) $post['author'] !== (int) ( $args['author'] ?? 0 ) ) { continue; } $match = true; foreach ( (array) ( $args['meta_query'] ?? array() ) as $clause ) { if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; } if ( ( $GLOBALS['real_meta'][ $id ][ $clause['key'] ] ?? null ) !== ( $clause['value'] ?? null ) ) { $match = false; } } if ( $match ) { $ids[] = $id; } } return array_slice( $ids, 0, 2 ); }
	function absint( mixed $value ): int { return abs( (int) $value ); }
}

namespace Sabri\UniversalComposer\Core {
	final class Registry {
		public function __construct( private object $adapter ) {}
		public function get( string $key ): ?object { return 'social_publication' === $key ? $this->adapter : null; }
		public function workflow_contract( string $key ): ?array { return 'social_publication' === $key ? array( 'workflow_api_version' => '1.0.0', 'required_capability' => 'sabri_feed_create_posts', 'supports_native_drafts' => true ) : null; }
	}
	final class Permission_Resolver { public function account_is_eligible( int $user_id ): bool { return $user_id > 0; } public function can_use_capability( int $user_id, string $capability ): bool { return $user_id > 0 && 'sabri_feed_create_posts' === $capability; } }
	final class Safe_Mode { public static function disabled(): bool { return false; } }
}

namespace Sabri\HomeNewsFeed {
	final class UniversalComposerBridge { public const ADAPTER_API_VERSION = '1.0.0'; public const WORKFLOW_API_VERSION = '1.0.0'; public const ADAPTER_KEY = 'social_publication'; }
	final class Settings { public static function get(): array { return array( 'composer' => array( 'public_composer_enabled' => 1, 'drafts_enabled' => 1, 'previews_enabled' => 1, 'scheduling_enabled' => 1, 'allowed_feed_types' => array( 'standard-post', 'founder-update' ) ) ); } }
	final class SafeMode { public static function feature_enabled( string $feature ): bool { return 'composer' === $feature; } }
	final class PublicComposerSurface {}
	final class CanonicalIdentityAdapter { public static function current_action_ready( int $id = 0 ): bool { return in_array( $id, array( 1, 2, 99 ), true ); } public static function is_founder( int $id ): bool { return 1 === $id; } public static function is_administrator( int $id ): bool { return 99 === $id; } }
	final class FeedContext { public static function allowed_composer_visibility( ?array $settings = null, bool $private = true ): array { unset( $settings ); return $private ? array( 'public', 'private' ) : array( 'public' ); } }
	final class ComposerPermissions { public static function user_can_create( int $id, ?array $settings = null ): bool { unset( $settings ); return $id > 0; } public static function user_can_edit_post( int $post_id, int $user_id = 0 ): bool { return (int) ( $GLOBALS['real_posts'][ $post_id ]['author'] ?? 0 ) === $user_id; } }
	final class ComposerValidation { public static function validate( array $input, int $user_id = 0, ?array $settings = null ): array { unset( $user_id, $settings ); $valid = '' !== trim( (string) ( $input['content'] ?? '' ) ); return array( 'valid' => $valid, 'errors' => $valid ? array() : array( array( 'code' => 'content_required' ) ), 'data' => $input ); } }
	final class Composer { public static function create_or_update_from_request( array $input, array $files = array(), int $user_id = 0 ): array { unset( $files ); $id = (int) ( $input['post_id'] ?? 0 ); if ( $id <= 0 ) { $id = ++$GLOBALS['real_next_id']; } $action = (string) ( $input['composer_action'] ?? 'submit' ); $status = array( 'draft' => 'draft', 'submit' => 'pending', 'publish' => 'publish', 'schedule' => 'future' )[ $action ] ?? 'pending'; $GLOBALS['real_posts'][ $id ] = array( 'type' => 'post', 'status' => $status, 'author' => $user_id, 'visibility' => (string) ( $input['visibility'] ?? 'public' ), 'url' => 'https://example.test/post/' . $id . '/' ); return array( 'ok' => true, 'post_id' => $id, 'status' => $status ); } }
	final class PostMetadata { public static function user_can_view( int $post_id, int $user_id = 0 ): bool { $post = $GLOBALS['real_posts'][ $post_id ] ?? array(); return 'publish' === ( $post['status'] ?? '' ) && ( 'private' !== ( $post['visibility'] ?? 'public' ) || (int) ( $post['author'] ?? 0 ) === $user_id ); } }
}

namespace {
	require_once $file22_root . '/includes/contracts/interface-adapter.php';
	require_once $file22_root . '/includes/contracts/interface-diagnostic-adapter.php';
	require_once $file22_root . '/includes/contracts/interface-workflow-adapter.php';
	require_once $file22_root . '/includes/core/class-workflow-coordinator.php';
	require_once dirname( __DIR__ ) . '/includes/class-universal-composer-workflow-store.php';
	require_once dirname( __DIR__ ) . '/includes/class-universal-composer-workflow-adapter.php';
	require_once dirname( __DIR__ ) . '/includes/class-universal-composer-publication-adapter.php';
	require_once dirname( __DIR__ ) . '/includes/class-universal-composer-subject-schema-adapter.php';

	$adapter = new \Sabri\HomeNewsFeed\UniversalComposerSubjectSchemaAdapter();
	$coordinator = new \Sabri\UniversalComposer\Core\Workflow_Coordinator( new \Sabri\UniversalComposer\Core\Registry( $adapter ), new \Sabri\UniversalComposer\Core\Permission_Resolver() );
	$failures = array();
	$assert = static function ( bool $condition, string $message ) use ( &$failures ): void { if ( ! $condition ) { $failures[] = $message; } };

	$health = $coordinator->contract_health( 'social_publication' );
	$assert( 'pass' === ( $health['status'] ?? '' ) && 'yes' === ( $health['subject_schema_extension'] ?? '' ), 'Static File 21 schema contract was not role-neutral and subject-aware.' );
	$GLOBALS['real_current_user'] = 2;
	$doctor_schema = $coordinator->schema( 2, 'social_publication' );
	$doctor_choices = is_array( $doctor_schema ) ? (array) ( $doctor_schema['fields']['feed_type']['choices'] ?? array() ) : array();
	$assert( ! isset( $doctor_choices['founder_update'] ), 'Doctor schema exposed Founder-only feed type.' );
	$GLOBALS['real_current_user'] = 1;
	$founder_schema = $coordinator->schema( 1, 'social_publication' );
	$founder_choices = is_array( $founder_schema ) ? (array) ( $founder_schema['fields']['feed_type']['choices'] ?? array() ) : array();
	$assert( isset( $founder_choices['founder_update'] ), 'Founder schema omitted authorized institutional feed type.' );

	$payload = array( 'content' => 'Actual coordinator integration', 'feed_type' => 'standard_post', 'visibility' => 'public', 'publication_action' => 'publish' );
	$draft = $coordinator->create_draft( 1, 'social_publication', null, $payload );
	$assert( is_array( $draft ) && 'draft' === ( $draft['status'] ?? '' ), 'Real File 22 coordinator rejected draft creation.' );
	$reference = is_array( $draft ) ? (string) ( $draft['native_reference'] ?? '' ) : '';
	$workflow_payload = array_merge( $payload, array( 'native_reference' => $reference ) );
	$preview = $coordinator->preview( 1, 'social_publication', $workflow_payload );
	$assert( is_array( $preview ) && str_contains( (string) ( $preview['preview_url'] ?? '' ), 'sabri_file22_signature=' ), 'Real File 22 coordinator rejected the signed preview.' );
	$key = $coordinator->generate_idempotency_key();
	$submitted = $coordinator->submit( 1, 'social_publication', $key, $workflow_payload );
	$assert( is_array( $submitted ) && 'published' === ( $submitted['status'] ?? '' ), 'Real File 22 coordinator rejected draft-referenced submission.' );
	$submitted_ref = is_array( $submitted ) ? (string) ( $submitted['native_reference'] ?? '' ) : '';
	$status = $coordinator->status( 1, 'social_publication', $submitted_ref );
	$assert( is_array( $status ) && 'published' === ( $status['status'] ?? '' ), 'Real File 22 coordinator rejected status.' );
	$url = $coordinator->canonical_url( 1, 'social_publication', $submitted_ref );
	$assert( is_string( $url ) && str_starts_with( $url, 'https://example.test/' ), 'Real File 22 coordinator rejected canonical URL.' );

	if ( $failures ) { fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL ); exit( 1 ); }
	echo "Actual File 22 Coordinator and File 21 subject-aware adapter contracts passed.\n";
}
