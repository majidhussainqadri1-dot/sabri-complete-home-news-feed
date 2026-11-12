<?php
/**
 * Executable File21 Issue #65: viewer-scoped Feed card != world-public share.
 *
 * Runs with the repository's WordPress test stubs, not the Live website.
 *
 * @package SabriCompleteHomeNewsFeed
 */
// WordPress defines these constants; the lean PHP harness does not.
if ( ! defined( 'HOUR_IN_SECONDS' ) ) { define( 'HOUR_IN_SECONDS', 3600 ); }
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
if ( ! defined( 'WEEK_IN_SECONDS' ) ) { define( 'WEEK_IN_SECONDS', 604800 ); }
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) { define( 'MINUTE_IN_SECONDS', 60 ); }

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\FeedRenderer;
use Sabri\HomeNewsFeed\FeedContext;
use Sabri\HomeNewsFeed\NextGenerationFeed;
use Sabri\HomeNewsFeed\PostMetadata;
use Sabri\HomeNewsFeed\Settings;

sabri_test_reset_state( true );
$GLOBALS['issue65_failures'] = array();
$assert65 = static function ( $actual, $message ) {
	if ( ! $actual ) {
		$GLOBALS['issue65_failures'][] = $message;
	}
};

$seed = static function ( $id, $visibility, $review = 'approved', $status = 'publish', $type = 'post', $author = 3 ) {
	return sabri_test_add_post(
		array(
			'ID'           => $id,
			'post_author'  => $author,
			'post_type'    => $type,
			'post_status'  => $status,
			'post_title'   => 'Issue 65 scoped content ' . $id,
			'post_excerpt' => 'Audience-specific publication content.',
		),
		array(
			PostMetadata::META_VISIBILITY   => $visibility,
			PostMetadata::META_REVIEW_STATE => $review,
		)
	);
};
$settings = array(
	'feed' => array(
		'show_author_details' => 0,
		'show_post_type'      => 0,
		'show_media'          => 0,
		'show_disclaimer'     => 0,
	),
);
$public = $seed( 501, 'public' );
$member = $seed( 502, 'members' );
$student = $seed( 503, 'students' );
$private = $seed( 504, 'private', 'approved', 'publish', 'post', 1 );
$pending = $seed( 505, 'public', 'pending' );
$draft = $seed( 506, 'public', 'approved', 'draft' );
$page = $seed( 507, 'public', 'approved', 'publish', 'page' );
$news = $seed( 508, 'public', 'approved', 'publish', 'sabri_news' );
$blocked = $seed( 509, 'public', 'approved', 'publish', 'post', 4 );

$GLOBALS['sabri_test_current_user_id'] = 0;
$assert65( true === FeedRenderer::viewer_can_render_post( $public ), 'Anonymous must see an approved public core post.' );
$assert65( false === FeedRenderer::viewer_can_render_post( $member ), 'Anonymous must not see members-only content.' );
$assert65( false === FeedRenderer::viewer_can_render_post( $student ), 'Anonymous must not see students-only content.' );
$assert65( false === FeedRenderer::viewer_can_render_post( $private ), 'Anonymous must not see owner-private content.' );
$assert65( '' === FeedRenderer::render_card( $member, $settings ), 'Anonymous restricted Feed card must not render.' );
$assert65( '' === FeedRenderer::render_card( $pending, $settings ), 'Pending-review Feed card must not render.' );
$assert65( '' === FeedRenderer::render_card( $draft, $settings ), 'Draft Feed card must not render.' );
$assert65( '' === FeedRenderer::render_card( $page, $settings ), 'WordPress Page cannot be rendered as a Feed card.' );
$assert65( '' === FeedRenderer::render_card( $news, $settings ), 'Editorial News cannot bypass its source-specific rendering contract.' );

$GLOBALS['sabri_test_current_user_id'] = 7; // Ordinary confirmed member.
$assert65( in_array( 'members', FeedContext::visible_feed_scopes_for_user( 7 ), true ), 'Authenticated member Feed must include members candidate scope.' );
$assert65( true === PostMetadata::user_can_view( $member, 7 ), 'Canonical membership read must authorize the members post.' );
$assert65( false === NextGenerationFeed::strict_public_item( $member ), 'Members-only post must never be world-public shareable.' );
$assert65( true === FeedRenderer::viewer_can_render_post( $member ), 'Viewer-owned member-scoped Feed card must be authorized.' );
$html = FeedRenderer::render_card( $member, $settings );
$assert65( is_string( $html ) && false !== strpos( $html, 'Issue 65 scoped content 502' ), 'Legitimately authorized member-scoped content must render in the actual Feed card template.' );
$assert65( false === strpos( $html, 'sabri-hnf-ng-card' ), 'Public-only next-generation context cannot leak into a limited-audience card.' );
$assert65( false === FeedRenderer::viewer_can_render_post( $student ), 'Non-student member must not see students-only content.' );
$assert65( true === FeedRenderer::viewer_can_render_post( $public ), 'Member must also see public content.' );
$assert65( false === FeedRenderer::viewer_can_render_post( $private ), 'One member must not see another author private draft-like scope.' );

$GLOBALS['sabri_test_current_user_id'] = 5;
$assert65( true === FeedRenderer::viewer_can_render_post( $student ), 'Eligible student viewer must see a reviewed students-scope post.' );
$assert65( '' !== FeedRenderer::render_card( $student, $settings ), 'The actual student-scoped card template must render for a student.' );

$GLOBALS['sabri_test_current_user_id'] = 1;
$assert65( false === FeedRenderer::viewer_can_render_post( $private ), 'Private posts do not enter Home Feed even when their author can edit them.' );
$assert65( false === FeedRenderer::viewer_can_render_post( $pending ), 'Founder/admin privilege must not publish pending content in Feed.' );
$assert65( false === FeedRenderer::viewer_can_render_post( $draft ), 'Founder/admin privilege must not publish drafts in Feed.' );

$GLOBALS['sabri_test_current_user_id'] = 7;
$GLOBALS['sabri_test_filter_overrides']['sabri_hnf_network_author_allowed'] = false;
$assert65( false === FeedRenderer::viewer_can_render_post( $blocked ), 'Current File17-style block/restriction must override a public Feed card.' );
$assert65( '' === FeedRenderer::render_card( $blocked, $settings ), 'Blocked author cannot be re-exposed by rendering.' );
unset( $GLOBALS['sabri_test_filter_overrides']['sabri_hnf_network_author_allowed'] );
$assert65( true === FeedRenderer::viewer_can_render_post( $blocked ), 'Authorized post returns after active block policy is lifted.' );

$GLOBALS['sabri_test_current_user_id'] = 0;
$assert65( false === FeedRenderer::viewer_can_render_post( 999999 ), 'Nonexistent publication must fail closed.' );
$assert65( false === FeedRenderer::viewer_can_render_post( 0 ), 'Zero publication ID must fail closed.' );
$assert65( true === NextGenerationFeed::strict_public_item( $public ), 'Original strict public-sharing rule remains operational for approved public source.' );
$assert65( false === NextGenerationFeed::strict_public_item( $member ), 'Original strict public-sharing rule still rejects members-only source.' );

if ( ! empty( $GLOBALS['issue65_failures'] ) ) {
	fwrite( STDERR, "File 21 Issue #65 runtime regression failures:\n- " . implode( "\n- ", $GLOBALS['issue65_failures'] ) . "\n" );
	exit( 1 );
}
echo "File 21 viewer-scoped Feed cards / strict public-share boundary: PASS\n";
