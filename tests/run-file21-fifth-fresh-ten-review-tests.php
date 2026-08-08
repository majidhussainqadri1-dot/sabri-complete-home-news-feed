<?php
/** Executable regressions for File 21 fifth fresh ten-round review. */

declare(strict_types=1);

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['f21_status'] = array( 101 => 'publish', 102 => 'publish', 103 => 'draft' );
	$GLOBALS['f21_type']   = array( 101 => 'post', 102 => 'sabri_news', 103 => 'post' );
	$GLOBALS['f21_author'] = array( 501 => 1, 502 => 2, 503 => 3, 504 => 4 );

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
}

namespace Sabri\HomeNewsFeed {
	final class NextGenerationFeed {
		public const META_STORY_EXPIRES = '_sabri_ng_story_expires';
	}

	final class FourthFreshReviewHardening {
		public static function public_source_is_shareable( int $post_id ): bool { return 101 === $post_id; }
	}

	final class CanonicalIdentityAdapter {
		private static array $assertions = array(
			1 => array( 'contract_version' => '1.2.0', 'user_id' => 1, 'approved' => 1, 'eligible' => 1, 'guardian_verified' => 1, 'account_class' => 'founder', 'membership_type' => '', 'can_publish' => 1, 'public_profile_allowed' => 1 ),
			2 => array( 'contract_version' => '1.2.0', 'user_id' => 2, 'approved' => 1, 'eligible' => 1, 'guardian_verified' => 1, 'account_class' => 'member', 'membership_type' => 'member', 'can_publish' => 1, 'public_profile_allowed' => 1 ),
			3 => array( 'contract_version' => '1.2.0', 'user_id' => 3, 'approved' => 1, 'eligible' => 1, 'guardian_verified' => 1, 'account_class' => 'member', 'membership_type' => 'doctor', 'professional_verified' => 0, 'public_profile_allowed' => 1 ),
			4 => array( 'contract_version' => '1.2.0', 'user_id' => 4, 'approved' => 1, 'eligible' => 1, 'guardian_verified' => 1, 'account_class' => 'member', 'membership_type' => 'doctor', 'professional_verified' => 1, 'public_profile_allowed' => 1, 'can_publish' => 1 ),
			5 => array( 'contract_version' => '1.2.0', 'user_id' => 5, 'approved' => 1, 'eligible' => 1, 'guardian_verified' => 1, 'account_class' => 'administrator', 'membership_type' => '', 'public_profile_allowed' => 1 ),
		);

		public static function membership_assertions( int $user_id ): array { return self::$assertions[ $user_id ] ?? array( '_contract_error' => true ); }
		public static function subject_is_active( int $user_id ): bool {
			$a = self::membership_assertions( $user_id );
			return empty( $a['_contract_error'] ) && ! empty( $a['approved'] ) && ! empty( $a['eligible'] ) && ( ! array_key_exists( 'guardian_verified', $a ) || ! empty( $a['guardian_verified'] ) );
		}
		public static function public_projection( int $user_id ): array {
			if ( ! self::subject_is_active( $user_id ) || 3 === $user_id ) { return array(); }
			return array( 'id' => $user_id, 'name' => 'User ' . $user_id, 'profile_url' => '/profile/' . $user_id );
		}
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
	require_once dirname( __DIR__ ) . '/includes/class-fifth-fresh-review-hardening.php';

	use Sabri\HomeNewsFeed\FifthFreshReviewHardening as H;
	use Sabri\HomeNewsFeed\TestQuery;
	use Sabri\HomeNewsFeed\TestRequest;

	$failures = array();
	$assert = static function ( bool $condition, string $message ) use ( &$failures ): void {
		if ( ! $condition ) { $failures[] = $message; fwrite( STDERR, "FAIL: {$message}\n" ); return; }
		echo "PASS: {$message}\n";
	};

	// Round 2 — stale/mis-issued publication assertions cannot turn a general member into a social publisher.
	$general = \Sabri\HomeNewsFeed\CanonicalIdentityAdapter::membership_assertions( 2 );
	$assert( ! H::subject_is_allowed_social_creator( 2, $general ), 'general member stays outside the social creator class even when can_publish is stale/mis-issued' );
	$assert( ! H::subject_is_allowed_public_social_publisher( 2, $general ), 'general member cannot become a public social publisher from can_publish alone' );
	$user = (object) array( 'ID' => 2 );
	$guarded = H::guard_social_publication_capabilities(
		array( 'sabri_feed_create_posts' => true, 'sabri_feed_submit_for_review' => true, 'sabri_feed_publish_posts' => true ),
		array( 'sabri_feed_publish_posts' ),
		array(),
		$user
	);
	$assert( empty( $guarded['sabri_feed_publish_posts'] ), 'runtime capability guard strips public social publish power from a general member' );

	$unverified = \Sabri\HomeNewsFeed\CanonicalIdentityAdapter::membership_assertions( 3 );
	$assert( H::subject_is_allowed_social_creator( 3, $unverified ), 'unverified doctor may enter the moderated social creation path' );
	$assert( ! H::subject_is_allowed_public_social_publisher( 3, $unverified ), 'unverified doctor cannot publish publicly' );
	$verified = \Sabri\HomeNewsFeed\CanonicalIdentityAdapter::membership_assertions( 4 );
	$assert( H::subject_is_allowed_public_social_publisher( 4, $verified ), 'verified public doctor remains an eligible public publisher class' );
	$founder = \Sabri\HomeNewsFeed\CanonicalIdentityAdapter::membership_assertions( 1 );
	$assert( H::subject_is_allowed_public_social_publisher( 1, $founder ), 'Founder remains an eligible public publisher class' );

	// Round 3 — Editorial News has no permissive Repost/Quote fallback if native NewsPolicy is unavailable.
	$assert( H::strict_public_source_is_shareable( 101 ), 'approved public social post remains shareable through prior exact checks' );
	$assert( ! H::strict_public_source_is_shareable( 102 ), 'Editorial News fails closed when native NewsPolicy is unavailable' );
	$blocked = H::before_callbacks( null, null, new TestRequest( array( 'action' => 'quote', 'post_id' => 102 ) ) );
	$assert( $blocked instanceof WP_Error && 'ng30_source_policy_unavailable' === $blocked->get_error_code(), 'REST Quote rejects Editorial News when native public-read policy cannot be proven' );

	// Round 4 — public professional Stories cannot inherit a generic trusted-publisher/can_publish assertion.
	$assert( ! H::professional_story_author_is_eligible( 2 ), 'general public identity with stale can_publish is not Story-eligible' );
	$assert( H::professional_story_author_is_eligible( 4 ), 'verified doctor with canonical public projection is Story-eligible' );
	$query = new TestQuery( array( array( 'key' => '_sabri_ng_story_expires', 'value' => time(), 'compare' => '>' ) ) );
	$posts = array( (object) array( 'ID' => 201, 'post_author' => 2 ), (object) array( 'ID' => 202, 'post_author' => 4 ) );
	$filtered = H::filter_story_results( $posts, $query );
	$assert( 1 === count( $filtered ) && 202 === $filtered[0]->ID, 'Story read projection removes non-professional stale-authority records' );

	printf( "File 21 fifth fresh review runtime regressions: %d failure(s).\n", count( $failures ) );
	exit( $failures ? 1 : 0 );
}
