<?php
/** Runtime contract tests for the File 21 native File 22 Workflow Adapter. */

declare(strict_types=1);

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'SABRI_HNF_VERSION', '1.0.3' );
	define( 'SABRI_HNF_SLUG', 'sabri-complete-home-news-feed' );

	$GLOBALS['file21_test_options']        = array();
	$GLOBALS['file21_test_posts']          = array();
	$GLOBALS['file21_test_next_post_id']   = 100;
	$GLOBALS['file21_test_current_user']   = 1;
	$GLOBALS['file21_test_composer_calls'] = 0;
	$GLOBALS['file21_test_update_fail']    = false;

	final class WP_Error {
		public function __construct( private string $code = '', private string $message = '' ) {}
		public function get_error_code(): string { return $this->code; }
		public function get_error_message(): string { return $this->message; }
	}

	function __( string $text, string $domain = '' ): string { unset( $domain ); return $text; }
	function sanitize_key( string $key ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? ''; }
	function home_url( string $path = '' ): string { return 'https://example.test/' . ltrim( $path, '/' ); }
	function get_current_user_id(): int { return (int) $GLOBALS['file21_test_current_user']; }
	function wp_json_encode( mixed $value ): string|false { return json_encode( $value ); }
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
	function get_post_field( string $field, int $post_id ): mixed {
		if ( 'post_author' === $field ) { return $GLOBALS['file21_test_posts'][ $post_id ]['author'] ?? 0; }
		return '';
	}
	function get_permalink( int $post_id ): string|false { return $GLOBALS['file21_test_posts'][ $post_id ]['permalink'] ?? false; }
	function get_preview_post_link( int $post_id ): string { return 'https://example.test/?p=' . $post_id . '&preview=true'; }
}

namespace Sabri\UniversalComposer\Contracts {
	interface Adapter {
		public function api_version(): string;
		public function key(): string;
		public function label(): string;
		public function description(): string;
		public function group(): string;
		public function icon(): string;
		public function priority(): int;
		public function native_module(): string;
		public function minimum_native_version(): string;
		public function required_capability(): string;
		public function privacy_classification(): string;
		public function is_available(): bool;
		public function can_create( int $user_id ): bool;
		public function start_url( int $user_id ): string;
	}
	interface Diagnostic_Adapter extends Adapter { public function health_report(): array; }
	interface Workflow_Adapter extends Adapter {
		public function workflow_api_version(): string;
		public function schema_version(): string;
		public function supports_native_drafts(): bool;
		public function schema(): array;
		public function create_draft( int $user_id, ?string $native_reference, array $payload );
		public function validate( int $user_id, array $payload );
		public function preview( int $user_id, array $payload );
		public function submit( int $user_id, string $idempotency_key, array $payload );
		public function status( int $user_id, string $native_reference );
		public function canonical_url( int $user_id, string $native_reference ): string;
	}
}

namespace Sabri\HomeNewsFeed {
	final class UniversalComposerBridge {
		public const ADAPTER_API_VERSION  = '1.0.0';
		public const WORKFLOW_API_VERSION = '1.0.0';
		public const ADAPTER_KEY          = 'social_publication';
	}
	final class Settings {
		public static function get(): array {
			return array(
				'composer' => array(
					'public_composer_enabled' => 1,
					'drafts_enabled' => 1,
					'scheduling_enabled' => 1,
					'comments_metadata_enabled' => 1,
				),
			);
		}
	}
	final class SafeMode { public static function feature_enabled( string $feature ): bool { return 'composer' === $feature; } }
	final class PublicComposerSurface {}
	final class FeedContext {
		public static function allowed_composer_visibility( ?array $settings = null, bool $include_private = true ): array {
			unset( $settings );
			return $include_private ? array( 'public', 'members', 'private' ) : array( 'public', 'members' );
		}
	}
	final class ComposerPermissions {
		public static function user_can_create( int $user_id, ?array $settings = null ): bool { unset( $settings ); return in_array( $user_id, array( 1, 2, 99 ), true ); }
		public static function user_can_edit_post( int $post_id, int $user_id = 0 ): bool {
			$author = (int) ( $GLOBALS['file21_test_posts'][ $post_id ]['author'] ?? 0 );
			return 99 === $user_id || ( $author > 0 && $author === $user_id );
		}
	}
	final class ComposerValidation {
		public static function validate( array $input, int $user_id = 0, ?array $settings = null ): array {
			unset( $user_id, $settings );
			$errors = array();
			if ( ! in_array( (string) ( $input['feed_type'] ?? '' ), array( 'standard-post', 'founder-update', 'nutrition' ), true ) ) {
				$errors[] = array( 'code' => 'invalid_feed_type' );
			}
			if ( 'draft' !== (string) ( $input['composer_action'] ?? '' ) && '' === trim( (string) ( $input['content'] ?? '' ) ) ) {
				$errors[] = array( 'code' => 'content_required' );
			}
			return array( 'valid' => array() === $errors, 'errors' => $errors, 'data' => $input );
		}
	}
	final class Composer {
		public static function create_or_update_from_request( array $input, array $files = array(), int $user_id = 0 ): array {
			unset( $files );
			++$GLOBALS['file21_test_composer_calls'];
			$validation = ComposerValidation::validate( $input, $user_id, Settings::get() );
			if ( empty( $validation['valid'] ) ) {
				return array( 'ok' => false, 'code' => 'validation_failed' );
			}
			$post_id = (int) ( $input['post_id'] ?? 0 );
			if ( $post_id > 0 && ! ComposerPermissions::user_can_edit_post( $post_id, $user_id ) ) {
				return array( 'ok' => false, 'code' => 'edit_denied' );
			}
			if ( $post_id <= 0 ) { $post_id = ++$GLOBALS['file21_test_next_post_id']; }
			$action = (string) ( $input['composer_action'] ?? 'submit' );
			$status = array( 'draft' => 'draft', 'publish' => 'publish', 'schedule' => 'future', 'submit' => 'pending' )[ $action ] ?? 'pending';
			$GLOBALS['file21_test_posts'][ $post_id ] = array(
				'type'       => 'post',
				'status'     => $status,
				'author'     => $user_id,
				'content'    => (string) ( $input['content'] ?? '' ),
				'visibility' => (string) ( $input['visibility'] ?? 'public' ),
				'permalink'  => 'https://example.test/post/' . $post_id . '/',
			);
			return array( 'ok' => true, 'post_id' => $post_id, 'status' => $status, 'permalink' => $GLOBALS['file21_test_posts'][ $post_id ]['permalink'] );
		}
	}
	final class PostMetadata {
		public static function user_can_view( int $post_id, int $user_id = 0 ): bool {
			$post = $GLOBALS['file21_test_posts'][ $post_id ] ?? array();
			if ( 'publish' !== ( $post['status'] ?? '' ) ) { return false; }
			return 'private' !== ( $post['visibility'] ?? 'public' ) || (int) ( $post['author'] ?? 0 ) === $user_id;
		}
	}

	require_once dirname( __DIR__ ) . '/includes/class-universal-composer-publication-adapter.php';

	$failures = array();
	$assert = static function ( bool $condition, string $message ) use ( &$failures ): void {
		if ( ! $condition ) { $failures[] = $message; }
	};
	$error_code = static function ( mixed $result ): string { return $result instanceof \WP_Error ? $result->get_error_code() : ''; };
	$uuid_key = '00000000-0000-4000-8000-000000000001:00000000-0000-4000-8000-000000000002';
	$adapter = new UniversalComposerPublicationAdapter();

	$assert( $adapter instanceof \Sabri\UniversalComposer\Contracts\Workflow_Adapter, 'Adapter does not implement Workflow_Adapter.' );
	$assert( '1.0.0' === $adapter->workflow_api_version(), 'Workflow API version mismatch.' );
	$assert( true === $adapter->supports_native_drafts(), 'Native draft support is not declared.' );
	$schema = $adapter->schema();
	$assert( isset( $schema['fields']['content']['type'] ) && 'textarea' === $schema['fields']['content']['type'], 'Strict content schema is missing.' );
	$assert( isset( $schema['fields']['feed_type']['choices']['standard_post'] ), 'Canonical feed choice is missing.' );
	$assert( ! isset( $schema['fields']['feed_type']['choices']['clinical_case'] ), 'Structured Clinical Case must remain on the native route.' );
	$assert( ! isset( $schema['fields']['feed_type']['choices']['research'] ), 'Structured Research must remain on the native route.' );
	$assert( ! isset( $schema['fields']['feed_type']['choices']['poll'] ), 'Poll must remain on the native route.' );

	$base_payload = array(
		'title' => 'Test',
		'content' => 'Native File 21 content',
		'feed_type' => 'standard_post',
		'visibility' => 'public',
		'publication_action' => 'publish',
	);
	$draft = $adapter->create_draft( 1, null, $base_payload );
	$assert( is_array( $draft ) && 'draft' === ( $draft['status'] ?? '' ), 'Draft creation failed.' );
	$draft_reference = is_array( $draft ) ? (string) ( $draft['native_reference'] ?? '' ) : '';
	$preview = $adapter->preview( 1, array_merge( $base_payload, array( 'native_reference' => $draft_reference ) ) );
	$assert( is_array( $preview ) && str_contains( (string) ( $preview['preview_url'] ?? '' ), 'preview=true' ), 'Private preview failed.' );
	$assert( is_array( $preview ) && (int) ( $preview['expires_at'] ?? 0 ) <= time() + 600, 'Preview TTL is not bounded.' );

	$validation = $adapter->validate( 1, $base_payload );
	$assert( is_array( $validation ) && true === ( $validation['valid'] ?? false ), 'Valid payload was rejected.' );
	$invalid_validation = $adapter->validate( 1, array_merge( $base_payload, array( 'feed_type' => 'clinical_case' ) ) );
	$assert( is_array( $invalid_validation ) && false === ( $invalid_validation['valid'] ?? true ), 'Unsupported structured workflow was accepted.' );

	$before_submit = (int) $GLOBALS['file21_test_composer_calls'];
	$submitted = $adapter->submit( 1, $uuid_key, $base_payload );
	$assert( is_array( $submitted ) && 'published' === ( $submitted['status'] ?? '' ), 'Publication submission failed.' );
	$submitted_reference = is_array( $submitted ) ? (string) ( $submitted['native_reference'] ?? '' ) : '';
	$replayed = $adapter->submit( 1, $uuid_key, $base_payload );
	$assert( $submitted === $replayed, 'Idempotent replay did not return the existing native result.' );
	$assert( $before_submit + 1 === (int) $GLOBALS['file21_test_composer_calls'], 'Idempotent replay created another native record.' );
	$conflict = $adapter->submit( 1, $uuid_key, array_merge( $base_payload, array( 'content' => 'Conflicting content' ) ) );
	$assert( 'conflict' === $error_code( $conflict ), 'Conflicting idempotency payload did not fail closed.' );

	$status = $adapter->status( 1, $submitted_reference );
	$assert( is_array( $status ) && 'published' === ( $status['status'] ?? '' ), 'Owner status lookup failed.' );
	$denied_status = $adapter->status( 2, $submitted_reference );
	$assert( 'permission_denied' === $error_code( $denied_status ), 'Cross-user status lookup was not denied.' );
	$assert( '' !== $adapter->canonical_url( 2, $submitted_reference ), 'Public canonical URL should be visible to an authorized viewer.' );

	$private_key = '00000000-0000-4000-8000-000000000003:00000000-0000-4000-8000-000000000004';
	$private_submission = $adapter->submit( 1, $private_key, array_merge( $base_payload, array( 'visibility' => 'private' ) ) );
	$private_reference = is_array( $private_submission ) ? (string) ( $private_submission['native_reference'] ?? '' ) : '';
	$assert( '' !== $adapter->canonical_url( 1, $private_reference ), 'Owner cannot resolve the private canonical URL.' );
	$assert( '' === $adapter->canonical_url( 2, $private_reference ), 'Cross-user private canonical URL leaked.' );

	$GLOBALS['file21_test_update_fail'] = true;
	$failure_key = '00000000-0000-4000-8000-000000000005:00000000-0000-4000-8000-000000000006';
	$before_failure = (int) $GLOBALS['file21_test_composer_calls'];
	$failed_persist = $adapter->submit( 1, $failure_key, $base_payload );
	$assert( 'temporarily_unavailable' === $error_code( $failed_persist ), 'Completion persistence failure did not fail closed.' );
	$retry_persist = $adapter->submit( 1, $failure_key, $base_payload );
	$assert( 'temporarily_unavailable' === $error_code( $retry_persist ), 'Processing lock was not preserved after persistence failure.' );
	$assert( $before_failure + 1 === (int) $GLOBALS['file21_test_composer_calls'], 'Persistence retry created a duplicate native record.' );
	$GLOBALS['file21_test_update_fail'] = false;

	$health = $adapter->health_report();
	$serialized_health = serialize( $health );
	$assert( '1.0.0' === ( $health['workflow_api_version'] ?? '' ), 'Workflow health version is missing.' );
	$assert( ! str_contains( $serialized_health, 'Native File 21 content' ) && ! str_contains( $serialized_health, $uuid_key ), 'Health report leaked content or idempotency material.' );

	if ( $failures ) {
		fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
		exit( 1 );
	}
	echo "File 21 File 22 workflow adapter runtime contracts passed.\n";
}
