<?php
/** Executable regressions for File 21 fourth fresh ten-round review. */

declare(strict_types=1);

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['f21_status'] = array( 101 => 'publish', 102 => 'publish', 103 => 'draft', 104 => 'publish' );
	$GLOBALS['f21_type']   = array( 101 => 'post', 102 => 'post', 103 => 'post', 104 => 'sabri_news' );
	$GLOBALS['f21_author'] = array( 501 => 1 );
	$GLOBALS['f21_raw_coauthors'] = array( 501 => array( 5, 6 ) );

	final class WP_Error {
		public function __construct( public string $code = '', public string $message = '', public mixed $data = null ) {}
		public function get_error_code(): string { return $this->code; }
	}

	function __( string $text, string $domain = '' ): string { unset( $domain ); return $text; }
	function absint( mixed $value ): int { return abs( (int) $value ); }
	function sanitize_key( mixed $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?? ''; }
	function get_post_status( int $post_id ): string { return $GLOBALS['f21_status'][ $post_id ] ?? 'publish'; }
	function get_post_type( int $post_id ): string { return $GLOBALS['f21_type'][ $post_id ] ?? 'post'; }
	function get_post_field( string $field, int $post_id ): mixed { return 'post_author' === $field ? ( $GLOBALS['f21_author'][ $post_id ] ?? 0 ) : ''; }
	function get_metadata_raw( string $type, int $object_id, string $key, bool $single ): mixed { unset( $type, $key, $single ); return $GLOBALS['f21_raw_coauthors'][ $object_id ] ?? array(); }
}

namespace Sabri\HomeNewsFeed {
	final class NextGenerationFeed {
		public const META_COAUTHORS = '_sabri_ng_coauthors';
		public const META_STORY_EXPIRES = '_sabri_ng_story_expires';
	}
	final class PostMetadata {
		public static function visibility( int $post_id ): string { return 102 === $post_id ? 'private' : 'public'; }
		public static function review_state_publicly_visible( int $post_id ): bool { return 103 !== $post_id; }
	}
	final class InteractionPermissions {
		public static function can_view_post( int $post_id, int $user_id = 0 ): bool { unset( $user_id ); return 102 !== $post_id; }
	}
	final class NewsPolicy {
		public static function can_public_read( int $post_id, string $context ): bool { return 104 === $post_id && 'single' === $context; }
	}
	final class CanonicalIdentityAdapter {
		public static function subject_is_active( int $user_id ): bool { return 3 !== $user_id && in_array( $user_id, array( 1, 2, 5, 6 ), true ); }
		public static function public_projection( int $user_id ): array {
			if ( in_array( $user_id, array( 1, 2, 5 ), true ) ) {
				return array( 'id' => $user_id, 'name' => 'User ' . $user_id, 'profile_url' => '/profile/' . $user_id );
			}
			return array();
		}
		public static function is_founder( int $user_id ): bool { return 1 === $user_id; }
		public static function is_verified_doctor( int $user_id ): bool { return 5 === $user_id; }
		public static function is_trusted_publisher( int $user_id ): bool { return false; }
	}

	final class TestRequest {
		public function __construct( private array $params, private array $json = array() ) {}
		public function get_route(): string { return '/sabri-home-news-feed/v1/next-generation/action'; }
		public function get_method(): string { return 'POST'; }
		public function get_param( string $key ): mixed { return $this->params[ $key ] ?? null; }
		public function get_json_params(): array { return $this->json; }
	}
	final class TestQuery {
		public function __construct( private array $meta ) {}
		public function get( string $key ): mixed { return 'meta_query' === $key ? $this->meta : null; }
	}
}

namespace {
	require_once dirname( __DIR__ ) . '/includes/class-fourth-fresh-review-hardening.php';

	$failures = array();
	$assert = static function ( bool $condition, string $message ) use ( &$failures ): void {
		if ( ! $condition ) { $failures[] = $message; fwrite( STDERR, "FAIL: {$message}\n" ); return; }
		echo "PASS: {$message}\n";
	};

	use Sabri\HomeNewsFeed\FourthFreshReviewHardening as H;
	use Sabri\HomeNewsFeed\TestQuery;
	use Sabri\HomeNewsFeed\TestRequest;

	// Round 2 — Repost/Quote may never turn a viewer-visible private/draft item into a public source relation.
	$assert( H::public_source_is_shareable( 101 ), 'approved public social source is shareable' );
	$assert( ! H::public_source_is_shareable( 102 ), 'private social source is not shareable' );
	$assert( ! H::public_source_is_shareable( 103 ), 'draft social source is not shareable' );
	$assert( H::public_source_is_shareable( 104 ), 'public Editorial News source follows native NewsPolicy' );
	$blocked = H::before_callbacks( null, null, new TestRequest( array( 'action' => 'repost', 'post_id' => 102 ) ) );
	$assert( $blocked instanceof WP_Error && 'ng30_source_not_public' === $blocked->get_error_code(), 'REST repost rejects non-public source before native write' );

	// Round 3 — Stories must revalidate current public professional authority on every query.
	$assert( H::story_author_is_eligible( 1 ), 'current Founder remains Story-eligible' );
	$assert( ! H::story_author_is_eligible( 2 ), 'ordinary public identity is not Story-eligible' );
	$assert( ! H::story_author_is_eligible( 3 ), 'revoked identity is not Story-eligible' );
	$story_query = new TestQuery( array( array( 'key' => '_sabri_ng_story_expires', 'value' => time(), 'compare' => '>' ) ) );
	$story_posts = array( (object) array( 'ID' => 201, 'post_author' => 1 ), (object) array( 'ID' => 202, 'post_author' => 2 ) );
	$story_posts = H::filter_story_results( $story_posts, $story_query );
	$assert( 1 === count( $story_posts ) && 201 === $story_posts[0]->ID, 'Story read projection removes currently ineligible author' );

	// Round 4 — coauthors remain current canonical public identity projections.
	$coauthors = H::filter_coauthor_metadata( null, 501, '_sabri_ng_coauthors', true, 'post' );
	$assert( array( 5 ) === $coauthors, 'stored coauthor projection suppresses revoked/non-public identity' );
	$valid_coauthors = H::before_callbacks( null, null, new TestRequest( array( 'action' => 'editor-update', 'post_id' => 501 ), array( 'fields' => array( 'coauthors' => array( 5 ) ) ) ) );
	$assert( null === $valid_coauthors, 'canonical public coauthor mutation is allowed to continue' );
	$invalid_coauthors = H::before_callbacks( null, null, new TestRequest( array( 'action' => 'editor-update', 'post_id' => 501 ), array( 'fields' => array( 'coauthors' => array( 6 ) ) ) ) );
	$assert( $invalid_coauthors instanceof WP_Error && 'ng30_coauthor_not_public' === $invalid_coauthors->get_error_code(), 'non-public coauthor mutation fails closed' );

	printf( "File 21 fourth fresh review runtime regressions: %d failure(s).\n", count( $failures ) );
	exit( $failures ? 1 : 0 );
}
