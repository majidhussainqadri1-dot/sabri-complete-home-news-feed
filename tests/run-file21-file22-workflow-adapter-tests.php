<?php
/** Runtime contracts for the corrected File 21 native File 22 adapter. */

declare(strict_types=1);

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'SABRI_HNF_VERSION', '1.0.3' );
	define( 'SABRI_HNF_SLUG', 'sabri-complete-home-news-feed' );
	define( 'AUTH_SALT', 'file21-test-auth-salt' );

	$GLOBALS['file21_test_options']        = array();
	$GLOBALS['file21_test_posts']          = array();
	$GLOBALS['file21_test_post_meta']      = array();
	$GLOBALS['file21_test_next_post_id']   = 100;
	$GLOBALS['file21_test_current_user']   = 1;
	$GLOBALS['file21_test_composer_calls'] = 0;
	$GLOBALS['file21_test_update_fail']    = false;
	$GLOBALS['file21_test_deleted_posts']  = array();

	final class WP_Error {
		public function __construct( private string $code = '', private string $message = '' ) {}
		public function get_error_code(): string { return $this->code; }
		public function get_error_message(): string { return $this->message; }
	}

	function __( string $text, string $domain = '' ): string { unset( $domain ); return $text; }
	function esc_html__( string $text, string $domain = '' ): string { unset( $domain ); return $text; }
	function esc_html( string $text ): string { return $text; }
	function esc_url( string $url ): string { return $url; }
	function esc_attr( string $text ): string { return $text; }
	function sanitize_text_field( string $text ): string { return trim( preg_replace( '/[\r\n\t]+/', ' ', $text ) ?? '' ); }
	function sanitize_key( string $key ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? ''; }
	function absint( mixed $value ): int { return abs( (int) $value ); }
	function wp_unslash( mixed $value ): mixed { return $value; }
	function home_url( string $path = '' ): string { return 'https://example.test/' . ltrim( $path, '/' ); }
	function admin_url( string $path = '' ): string { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
	function get_current_user_id(): int { return (int) $GLOBALS['file21_test_current_user']; }
	function wp_json_encode( mixed $value ): string|false { return json_encode( $value ); }
	function maybe_unserialize( mixed $value ): mixed { return is_string( $value ) ? @unserialize( $value ) ?: $value : $value; }
	function wp_salt( string $scheme = 'auth' ): string { return AUTH_SALT . '|' . $scheme; }
	function add_query_arg( array $args, string $url ): string { return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args ); }
	function get_option( string $key, mixed $default = false ): mixed { return $GLOBALS['file21_test_options'][ $key ] ?? $default; }
	function add_option( string $key, mixed $value, string $deprecated = '', bool|string $autoload = true ): bool {
		unset( $deprecated, $autoload );
		if ( array_key_exists( $key, $GLOBALS['file21_test_options'] ) ) { return false; }
		$GLOBALS['file21_test_options'][ $key ] = $value;
		return true;
	}
	function update_option( string $key, mixed $value, bool $autoload = true ): bool {
		unset( $autoload );
		if ( $GLOBALS['file21_test_update_fail'] ) { return false; }
		$GLOBALS['file21_test_options'][ $key ] = $value;
		return true;
	}
	function delete_option( string $key ): bool {
		if ( ! array_key_exists( $key, $GLOBALS['file21_test_options'] ) ) { return false; }
		unset( $GLOBALS['file21_test_options'][ $key ] );
		return true;
	}
	function get_post_type( int $post_id ): string|false { return $GLOBALS['file21_test_posts'][ $post_id ]['type'] ?? false; }
	function get_post_status( int $post_id ): string|false { return $GLOBALS['file21_test_posts'][ $post_id ]['status'] ?? false; }
	function get_post_field( string $field, int $post_id ): mixed { return 'post_author' === $field ? ( $GLOBALS['file21_test_posts'][ $post_id ]['author'] ?? 0 ) : ''; }
	function get_permalink( int $post_id ): string|false { return $GLOBALS['file21_test_posts'][ $post_id ]['permalink'] ?? false; }
	function get_preview_post_link( int $post_id ): string { return 'https://example.test/?p=' . $post_id . '&preview=true'; }
	function get_post_meta( int $post_id, string $key, bool $single = false ): mixed {
		$value = $GLOBALS['file21_test_post_meta'][ $post_id ][ $key ] ?? '';
		return $single ? $value : ( '' === $value ? array() : array( $value ) );
	}
	function update_post_meta( int $post_id, string $key, mixed $value ): int|bool {
		$GLOBALS['file21_test_post_meta'][ $post_id ][ $key ] = $value;
		return 1;
	}
	function delete_post_meta( int $post_id, string $key, mixed $value = '' ): bool {
		if ( ! isset( $GLOBALS['file21_test_post_meta'][ $post_id ][ $key ] ) ) { return false; }
		if ( '' !== $value && $GLOBALS['file21_test_post_meta'][ $post_id ][ $key ] !== $value ) { return false; }
		unset( $GLOBALS['file21_test_post_meta'][ $post_id ][ $key ] );
		return true;
	}
	function get_posts( array $args ): array {
		$out = array();
		foreach ( $GLOBALS['file21_test_posts'] as $post_id => $post ) {
			if ( 'post' !== ( $post['type'] ?? '' ) || (int) ( $post['author'] ?? 0 ) !== (int) ( $args['author'] ?? 0 ) ) { continue; }
			$meta = $GLOBALS['file21_test_post_meta'][ $post_id ] ?? array();
			$match = true;
			foreach ( (array) ( $args['meta_query'] ?? array() ) as $clause ) {
				if ( ! is_array( $clause ) || ! isset( $clause['key'] ) ) { continue; }
				if ( ( $meta[ $clause['key'] ] ?? null ) !== ( $clause['value'] ?? null ) ) { $match = false; }
			}
			if ( $match ) { $out[] = $post_id; }
		}
		return array_slice( $out, 0, (int) ( $args['posts_per_page'] ?? 2 ) );
	}
	function wp_delete_post( int $post_id, bool $force = false ): bool { unset( $force ); $GLOBALS['file21_test_deleted_posts'][] = $post_id; unset( $GLOBALS['file21_test_posts'][ $post_id ], $GLOBALS['file21_test_post_meta'][ $post_id ] ); return true; }
}

namespace Sabri\UniversalComposer\Contracts {
	interface Adapter {
		public function api_version(): string; public function key(): string; public function label(): string; public function description(): string; public function group(): string; public function icon(): string; public function priority(): int; public function native_module(): string; public function minimum_native_version(): string; public function required_capability(): string; public function privacy_classification(): string; public function is_available(): bool; public function can_create( int $user_id ): bool; public function start_url( int $user_id ): string;
	}
	interface Diagnostic_Adapter extends Adapter { public function health_report(): array; }
	interface Workflow_Adapter extends Adapter {
		public function workflow_api_version(): string; public function schema_version(): string; public function supports_native_drafts(): bool; public function schema(): array; public function create_draft( int $user_id, ?string $native_reference, array $payload ); public function validate( int $user_id, array $payload ); public function preview( int $user_id, array $payload ); public function submit( int $user_id, string $idempotency_key, array $payload ); public function status( int $user_id, string $native_reference ); public function canonical_url( int $user_id, string $native_reference ): string;
	}
}

namespace Sabri\HomeNewsFeed {
	final class UniversalComposerBridge { public const ADAPTER_API_VERSION = '1.0.0'; public const WORKFLOW_API_VERSION = '1.0.0'; public const ADAPTER_KEY = 'social_publication'; }
	final class Settings {
		public static function get(): array { return array( 'composer' => array( 'public_composer_enabled' => 1, 'drafts_enabled' => 1, 'previews_enabled' => 1, 'scheduling_enabled' => 1, 'allowed_feed_types' => array( 'standard-post', 'founder-update', 'nutrition', 'platform-news' ) ) ); }
	}
	final class SafeMode { public static function feature_enabled( string $feature ): bool { return 'composer' === $feature; } }
	final class PublicComposerSurface {}
	final class CanonicalIdentityAdapter { public static function is_founder( int $user_id ): bool { return 1 === $user_id; } public static function is_administrator( int $user_id ): bool { return 99 === $user_id; } }
	final class FeedContext { public static function allowed_composer_visibility( ?array $settings = null, bool $include_private = true ): array { unset( $settings ); return $include_private ? array( 'public', 'members', 'private' ) : array( 'public', 'members' ); } }
	final class ComposerPermissions {
		public static function user_can_create( int $user_id, ?array $settings = null ): bool { unset( $settings ); return in_array( $user_id, array( 1, 2, 99 ), true ); }
		public static function user_can_edit_post( int $post_id, int $user_id = 0 ): bool { $author = (int) ( $GLOBALS['file21_test_posts'][ $post_id ]['author'] ?? 0 ); return 99 === $user_id || ( $author > 0 && $author === $user_id ); }
	}
	final class ComposerValidation {
		public static function validate( array $input, int $user_id = 0, ?array $settings = null ): array { unset( $user_id, $settings ); $errors = array(); if ( ! in_array( (string) ( $input['feed_type'] ?? '' ), array( 'standard-post', 'founder-update', 'nutrition', 'platform-news' ), true ) ) { $errors[] = array( 'code' => 'invalid_feed_type' ); } if ( 'draft' !== (string) ( $input['composer_action'] ?? '' ) && '' === trim( (string) ( $input['content'] ?? '' ) ) ) { $errors[] = array( 'code' => 'content_required' ); } return array( 'valid' => array() === $errors, 'errors' => $errors, 'data' => $input ); }
	}
	final class Composer {
		public static function create_or_update_from_request( array $input, array $files = array(), int $user_id = 0 ): array {
			unset( $files ); ++$GLOBALS['file21_test_composer_calls']; $validation = ComposerValidation::validate( $input, $user_id, Settings::get() ); if ( empty( $validation['valid'] ) ) { return array( 'ok' => false, 'code' => 'validation_failed' ); }
			$post_id = (int) ( $input['post_id'] ?? 0 ); if ( $post_id > 0 && ! ComposerPermissions::user_can_edit_post( $post_id, $user_id ) ) { return array( 'ok' => false, 'code' => 'edit_denied' ); } if ( $post_id <= 0 ) { $post_id = ++$GLOBALS['file21_test_next_post_id']; }
			$action = (string) ( $input['composer_action'] ?? 'submit' ); $status = array( 'draft' => 'draft', 'publish' => 'publish', 'schedule' => 'future', 'submit' => 'pending' )[ $action ] ?? 'pending';
			$GLOBALS['file21_test_posts'][ $post_id ] = array( 'type' => 'post', 'status' => $status, 'author' => $user_id, 'content' => (string) ( $input['content'] ?? '' ), 'visibility' => (string) ( $input['visibility'] ?? 'public' ), 'permalink' => 'https://example.test/post/' . $post_id . '/' );
			return array( 'ok' => true, 'post_id' => $post_id, 'status' => $status, 'permalink' => $GLOBALS['file21_test_posts'][ $post_id ]['permalink'] );
		}
	}
	final class PostMetadata { public static function user_can_view( int $post_id, int $user_id = 0 ): bool { $post = $GLOBALS['file21_test_posts'][ $post_id ] ?? array(); return 'publish' === ( $post['status'] ?? '' ) && ( 'private' !== ( $post['visibility'] ?? 'public' ) || (int) ( $post['author'] ?? 0 ) === $user_id ); } }

	require_once dirname( __DIR__ ) . '/includes/class-universal-composer-workflow-store.php';
	require_once dirname( __DIR__ ) . '/includes/class-universal-composer-workflow-adapter.php';
	require_once dirname( __DIR__ ) . '/includes/class-universal-composer-publication-adapter.php';

	$failures = array();
	$assert = static function ( bool $condition, string $message ) use ( &$failures ): void { if ( ! $condition ) { $failures[] = $message; } };
	$error_code = static function ( mixed $result ): string { return $result instanceof \WP_Error ? $result->get_error_code() : ''; };
	$adapter = new UniversalComposerPublicationAdapter();
	$key = '00000000-0000-4000-8000-000000000001:00000000-0000-4000-8000-000000000002';
	$payload = array( 'title' => 'Test', 'content' => 'Native File 21 content', 'feed_type' => 'standard_post', 'visibility' => 'public', 'publication_action' => 'publish' );

	$assert( '1.0.1' === $adapter->schema_version(), 'Corrected schema version is missing.' );
	$GLOBALS['file21_test_current_user'] = 2;
	$doctor_schema = $adapter->schema();
	$assert( ! isset( $doctor_schema['fields']['feed_type']['choices']['founder_update'] ), 'Doctor schema exposes Founder Update.' );
	$GLOBALS['file21_test_current_user'] = 1;
	$founder_schema = $adapter->schema();
	$assert( isset( $founder_schema['fields']['feed_type']['choices']['founder_update'] ), 'Founder schema omits Founder Update.' );

	$draft = $adapter->create_draft( 1, null, $payload );
	$assert( is_array( $draft ) && 'draft' === ( $draft['status'] ?? '' ), 'Draft creation failed.' );
	$draft_ref = is_array( $draft ) ? (string) $draft['native_reference'] : '';
	$preview = $adapter->preview( 1, array_merge( $payload, array( 'native_reference' => $draft_ref ) ) );
	$assert( is_array( $preview ) && str_contains( (string) ( $preview['preview_url'] ?? '' ), 'sabri_file22_signature=' ), 'Signed preview URL is missing.' );
	parse_str( (string) parse_url( (string) $preview['preview_url'], PHP_URL_QUERY ), $preview_query );
	$post_id = UniversalComposerWorkflowStore::post_id_from_reference( $draft_ref );
	$assert( UniversalComposerWorkflowStore::preview_token_is_valid( $post_id, 1, (int) ( $preview_query['sabri_file22_expires'] ?? 0 ), (string) ( $preview_query['sabri_file22_signature'] ?? '' ) ), 'Fresh preview token is invalid.' );
	$assert( ! UniversalComposerWorkflowStore::preview_token_is_valid( $post_id, 1, time() - 1, (string) ( $preview_query['sabri_file22_signature'] ?? '' ) ), 'Expired preview token was accepted.' );

	$GLOBALS['file21_test_posts'][ $post_id ]['status'] = 'pending';
	$calls_before_pending = (int) $GLOBALS['file21_test_composer_calls'];
	$pending_preview = $adapter->preview( 1, array_merge( $payload, array( 'native_reference' => $draft_ref ) ) );
	$assert( 'conflict' === $error_code( $pending_preview ), 'Pending moderation post was accepted by preview.' );
	$assert( $calls_before_pending === (int) $GLOBALS['file21_test_composer_calls'], 'Pending moderation post was mutated.' );
	$GLOBALS['file21_test_posts'][ $post_id ]['status'] = 'draft';

	$before_submit = (int) $GLOBALS['file21_test_composer_calls'];
	$GLOBALS['file21_test_update_fail'] = true;
	$failed_completion = $adapter->submit( 1, $key, $payload );
	$assert( 'temporarily_unavailable' === $error_code( $failed_completion ), 'Completion persistence failure did not fail closed.' );
	$GLOBALS['file21_test_update_fail'] = false;
	$recovered = $adapter->submit( 1, $key, $payload );
	$assert( is_array( $recovered ) && 'published' === ( $recovered['status'] ?? '' ), 'Retry did not reconcile the native publication.' );
	$assert( $before_submit + 1 === (int) $GLOBALS['file21_test_composer_calls'], 'Recovery retry created another native record.' );
	$replayed = $adapter->submit( 1, $key, $payload );
	$assert( $recovered === $replayed, 'Completed replay did not return the native publication.' );
	$assert( $before_submit + 1 === (int) $GLOBALS['file21_test_composer_calls'], 'Completed replay invoked the Composer again.' );
	$conflict = $adapter->submit( 1, $key, array_merge( $payload, array( 'content' => 'Different content' ) ) );
	$assert( 'conflict' === $error_code( $conflict ), 'Same key with different payload did not conflict.' );

	$private_key = '00000000-0000-4000-8000-000000000003:00000000-0000-4000-8000-000000000004';
	$private = $adapter->submit( 1, $private_key, array_merge( $payload, array( 'visibility' => 'private' ) ) );
	$private_ref = is_array( $private ) ? (string) $private['native_reference'] : '';
	$assert( '' !== $adapter->canonical_url( 1, $private_ref ), 'Owner cannot resolve private canonical URL.' );
	$assert( '' === $adapter->canonical_url( 2, $private_ref ), 'Private canonical URL leaked cross-user.' );

	$health = serialize( $adapter->health_report() );
	$assert( str_contains( $health, 'preview_expiry_enforced' ) && str_contains( $health, 'idempotency_recovery_ready' ), 'Corrected health evidence is missing.' );
	$assert( ! str_contains( $health, 'Native File 21 content' ) && ! str_contains( $health, $key ), 'Health report leaked content or raw key.' );

	if ( $failures ) { fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL ); exit( 1 ); }
	echo "Corrected File 21 File 22 workflow adapter contracts passed.\n";
}
