<?php
/**
 * Phase 3C comments, replies, moderation, privacy, and UI tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/comment-stubs.php';

use Sabri\HomeNewsFeed\CommentPolicy;
use Sabri\HomeNewsFeed\CommentPrivacyScanner;
use Sabri\HomeNewsFeed\CommentRuntime;
use Sabri\HomeNewsFeed\CommentService;
use Sabri\HomeNewsFeed\Phase3FeatureSettings;
use Sabri\HomeNewsFeed\Plugin;
use Sabri\HomeNewsFeed\PostMetadata;
use Sabri\HomeNewsFeed\RestComments;
use Sabri\HomeNewsFeed\RestFoundation;
use Sabri\HomeNewsFeed\SocialRuntime;

final class Sabri_Phase3C_Request {
	private $params;
	private $headers;

	public function __construct( array $params = array(), array $headers = array() ) {
		$this->params  = $params;
		$this->headers = $headers;
	}

	public function get_param( $key ) {
		return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null;
	}

	public function get_header( $key ) {
		foreach ( $this->headers as $name => $value ) {
			if ( strtolower( $name ) === strtolower( $key ) ) {
				return $value;
			}
		}
		return '';
	}
}

$phase3c_failures = array();

function sabri_phase3c_assert( $condition, $message ) {
	global $phase3c_failures;
	if ( ! $condition ) {
		$phase3c_failures[] = $message;
	}
}

function sabri_phase3c_enable_comments() {
	$features = Phase3FeatureSettings::defaults();
	$features['comments_enabled'] = 1;
	update_option( Phase3FeatureSettings::OPTION_NAME, $features, false );
}

sabri_test_reset_state( true );
sabri_test_reset_comments();
sabri_phase3c_enable_comments();

global $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_filter_overrides, $sabri_test_transients, $sabri_test_rest_routes;

$public_post = sabri_test_add_post(
	array( 'post_author' => 2, 'post_status' => 'publish', 'post_title' => 'Comments public post', 'comment_status' => 'open' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);
$second_post = sabri_test_add_post(
	array( 'post_author' => 3, 'post_status' => 'publish', 'post_title' => 'Comments second post', 'comment_status' => 'open' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);
$clinical_post = sabri_test_add_post(
	array( 'post_author' => 3, 'post_status' => 'publish', 'post_title' => 'Clinical case', 'comment_status' => 'open' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'clinical-case' )
);
$closed_post = sabri_test_add_post(
	array( 'post_author' => 2, 'post_status' => 'publish', 'post_title' => 'Closed comments', 'comment_status' => 'closed' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);
$pending_post = sabri_test_add_post(
	array( 'post_author' => 4, 'post_status' => 'publish', 'post_title' => 'Pending post', 'comment_status' => 'open' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'pending', PostMetadata::META_TYPE => 'standard-post' )
);
$depth_post = sabri_test_add_post(
	array( 'post_author' => 2, 'post_status' => 'publish', 'post_title' => 'Reply depth post', 'comment_status' => 'open' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);
$rate_post = sabri_test_add_post(
	array( 'post_author' => 2, 'post_status' => 'publish', 'post_title' => 'Rate limit post', 'comment_status' => 'open' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);

sabri_phase3c_assert( 0 === Phase3FeatureSettings::defaults()['comments_enabled'], 'Comments must remain gated by default until staging acceptance.' );
sabri_phase3c_assert( Phase3FeatureSettings::enabled( 'comments_enabled' ), 'Isolated Checkpoint 3C tests must explicitly enable comments.' );
sabri_phase3c_assert( 2000 === CommentPolicy::max_length() && 3 === CommentPolicy::max_reply_depth() && 15 === CommentPolicy::edit_minutes(), 'Frozen comment limits must remain 2,000 characters, depth three, and 15 edit minutes.' );

$sabri_test_current_user_id = 7;
$sabri_test_current_caps    = array();
$pending = CommentService::create( $public_post, 'This is a useful patient-safe comment.', 0, 'rest-nonce', 7 );
sabri_phase3c_assert( ! empty( $pending['ok'] ) && 'pending' === $pending['data']['status'], 'Ordinary member comments must default to pending review.' );
$pending_id = isset( $pending['data']['comment']['id'] ) ? (int) $pending['data']['comment']['id'] : 0;
$pending_row = get_comment( $pending_id );
sabri_phase3c_assert( $pending_row && CommentPolicy::COMMENT_TYPE === $pending_row->comment_type, 'Comments must use a plugin-specific WordPress-native comment type.' );
sabri_phase3c_assert( '' === $pending_row->comment_author_IP && '' === $pending_row->comment_agent, 'Comment storage must not retain IP addresses or user-agent strings.' );
sabri_phase3c_assert( 0 === CommentService::approved_count( $public_post ), 'Pending comments must not increase the public count.' );
$owner_thread = CommentService::thread( $public_post, 7 );
sabri_phase3c_assert( 1 === count( $owner_thread['data']['items'] ) && 'pending' === $owner_thread['data']['items'][0]['status'], 'The owner must see their own pending comment.' );

$sabri_test_current_user_id = 6;
$other_thread = CommentService::thread( $public_post, 6 );
sabri_phase3c_assert( 0 === count( $other_thread['data']['items'] ), 'Another member must not see someone else’s pending comment.' );

$sabri_test_current_user_id = 1;
$sabri_test_current_caps    = array( 'sabri_feed_moderate_posts' => true );
$approved = CommentService::create( $public_post, 'Moderator-approved public comment.', 0, 'rest-nonce', 1 );
sabri_phase3c_assert( ! empty( $approved['ok'] ) && 'approved' === $approved['data']['status'], 'A comment moderator may publish immediately.' );
$approved_id = (int) $approved['data']['comment']['id'];
sabri_phase3c_assert( 1 === CommentService::approved_count( $public_post ), 'Only approved plugin comments must count publicly.' );
$moderator_thread = CommentService::thread( $public_post, 1 );
sabri_phase3c_assert( 2 === count( $moderator_thread['data']['items'] ), 'A moderator must see approved and pending comments.' );

$sabri_test_current_user_id = 6;
$sabri_test_current_caps    = array();
$public_thread = CommentService::thread( $public_post, 6 );
sabri_phase3c_assert( 1 === count( $public_thread['data']['items'] ) && $approved_id === $public_thread['data']['items'][0]['id'], 'Ordinary readers must receive approved comments only.' );
$serialized = wp_json_encode( $public_thread['data'] );
sabri_phase3c_assert( false === strpos( $serialized, 'user_email' ) && false === strpos( $serialized, 'comment_author_IP' ) && false === strpos( $serialized, 'comment_agent' ), 'Serialized comments must not expose email, IP, or user-agent data.' );

$scan = CommentPrivacyScanner::scan( 'Patient name: Ali. WhatsApp number: 03001234567.' );
sabri_phase3c_assert( empty( $scan['safe'] ) && in_array( 'phone-number', $scan['risks'], true ), 'Privacy scanner must identify obvious contact information without returning its value.' );
$clinical_risk = CommentService::create( $clinical_post, 'Patient name: Ali and phone: 03001234567.', 0, 'rest-nonce', 6 );
sabri_phase3c_assert( empty( $clinical_risk['ok'] ) && 'patient_privacy_risk' === $clinical_risk['code'] && 422 === $clinical_risk['status'], 'Clinical-case comments with patient identifiers must be rejected.' );
sabri_phase3c_assert( false === strpos( wp_json_encode( $clinical_risk['data'] ), '03001234567' ), 'Privacy error responses must never echo the detected identifier.' );
$standard_contact = CommentService::create( $second_post, 'General platform contact discussion 03001234567.', 0, 'rest-nonce', 6 );
sabri_phase3c_assert( ! empty( $standard_contact['ok'] ), 'Clinical privacy blocking must not silently become a universal phone-number ban.' );
$too_short = CommentService::create( $second_post, 'x', 0, 'rest-nonce', 6 );
sabri_phase3c_assert( empty( $too_short['ok'] ) && 'comment_too_short' === $too_short['code'], 'Meaningless one-character comments must be rejected.' );
$too_long = CommentService::create( $second_post, str_repeat( 'a', CommentPolicy::max_length() + 1 ), 0, 'rest-nonce', 6 );
sabri_phase3c_assert( empty( $too_long['ok'] ) && 'comment_too_long' === $too_long['code'], 'Overlong comments must be rejected before storage.' );
$closed = CommentService::create( $closed_post, 'This should not be stored.', 0, 'rest-nonce', 6 );
sabri_phase3c_assert( empty( $closed['ok'] ) && 'comments_closed' === $closed['code'], 'Closed posts must reject new comments.' );
$pending_post_result = CommentService::create( $pending_post, 'This should not be stored.', 0, 'rest-nonce', 6 );
sabri_phase3c_assert( empty( $pending_post_result['ok'] ) && 'post_unavailable' === $pending_post_result['code'], 'Pending posts must reject comments.' );
sabri_phase3c_assert( ! RestComments::validate_non_negative_id( -1 ) && 0 === RestComments::sanitize_non_negative_id( -1 ), 'Negative reply-parent IDs must fail REST validation instead of becoming valid parents.' );

$sabri_test_transients = array();
$sabri_test_current_user_id = 7;
$reply = CommentService::create( $public_post, 'Reply to my pending comment.', $pending_id, 'rest-nonce', 7 );
sabri_phase3c_assert( ! empty( $reply['ok'] ) && $pending_id === $reply['data']['comment']['parent_id'], 'A reply must retain its validated parent ID.' );
$reply_id = (int) $reply['data']['comment']['id'];
$cross_post = CommentService::create( $second_post, 'Cross-post reply attempt.', $pending_id, 'rest-nonce', 7 );
sabri_phase3c_assert( empty( $cross_post['ok'] ) && 'invalid_comment_parent' === $cross_post['code'], 'A comment from another post must not be accepted as a reply parent.' );
$foreign_parent_id = wp_insert_comment(
	array(
		'comment_post_ID' => $public_post,
		'comment_content' => 'Core comment type',
		'comment_type' => 'comment',
		'comment_approved' => '1',
		'user_id' => 7,
	)
);
$foreign_type = CommentService::create( $public_post, 'Reply to foreign type.', $foreign_parent_id, 'rest-nonce', 7 );
sabri_phase3c_assert( empty( $foreign_type['ok'] ) && 'invalid_comment_parent' === $foreign_type['code'], 'Only plugin-owned comment types may be reply parents.' );

$sabri_test_transients = array();
$sabri_test_current_user_id = 1;
$sabri_test_current_caps = array( 'sabri_feed_moderate_posts' => true );
$depth0 = CommentService::create( $depth_post, 'Depth zero.', 0, 'rest-nonce', 1 );
$depth1 = CommentService::create( $depth_post, 'Depth one.', $depth0['data']['comment']['id'], 'rest-nonce', 1 );
$depth2 = CommentService::create( $depth_post, 'Depth two.', $depth1['data']['comment']['id'], 'rest-nonce', 1 );
$depth3 = CommentService::create( $depth_post, 'Depth three.', $depth2['data']['comment']['id'], 'rest-nonce', 1 );
$depth4 = CommentService::create( $depth_post, 'Depth four is forbidden.', $depth3['data']['comment']['id'], 'rest-nonce', 1 );
sabri_phase3c_assert( ! empty( $depth3['ok'] ) && empty( $depth4['ok'] ) && 'reply_depth_exceeded' === $depth4['code'], 'Reply nesting must stop after the configured depth of three.' );

$sabri_test_current_user_id = 7;
$sabri_test_current_caps    = array();
$edited = CommentService::update( $pending_id, 'Edited safely within the owner window.', 'rest-nonce', 7 );
sabri_phase3c_assert( ! empty( $edited['ok'] ) && 'Edited safely within the owner window.' === get_comment( $pending_id )->comment_content, 'The owner must be able to edit inside the 15-minute window.' );
$created_time = strtotime( get_comment( $pending_id )->comment_date_gmt . ' GMT' );
$sabri_test_filter_overrides['sabri_feed_comment_now'] = $created_time + ( 16 * MINUTE_IN_SECONDS );
$late_edit = CommentService::update( $pending_id, 'Late owner edit.', 'rest-nonce', 7 );
sabri_phase3c_assert( empty( $late_edit['ok'] ) && 'comment_permission_denied' === $late_edit['code'], 'Owner edits after the configured window must be denied.' );
$sabri_test_current_user_id = 6;
$forged_edit = CommentService::update( $pending_id, 'Forged edit.', 'rest-nonce', 7 );
sabri_phase3c_assert( empty( $forged_edit['ok'] ) && 'authentication_required' === $forged_edit['code'], 'An explicit foreign user ID must not override the authenticated session.' );
$other_delete = CommentService::delete( $pending_id, 'rest-nonce', 6 );
sabri_phase3c_assert( empty( $other_delete['ok'] ) && 'comment_permission_denied' === $other_delete['code'], 'Another member must not delete a comment they do not own.' );
$sabri_test_current_user_id = 1;
$sabri_test_current_caps = array( 'sabri_feed_moderate_posts' => true );
$moderator_edit = CommentService::update( $pending_id, 'Moderator-reviewed edit after window.', 'rest-nonce', 1 );
sabri_phase3c_assert( ! empty( $moderator_edit['ok'] ), 'A moderator may edit after the owner edit window.' );
$sabri_test_current_user_id = 7;
$sabri_test_current_caps = array();
$removed = CommentService::delete( $pending_id, 'rest-nonce', 7 );
sabri_phase3c_assert( ! empty( $removed['ok'] ) && 1 === (int) get_comment_meta( $pending_id, CommentPolicy::META_DELETED, true ), 'Owner deletion must be a soft-delete marker.' );
sabri_phase3c_assert( '[Comment removed]' === get_comment( $pending_id )->comment_content && null !== get_comment( $reply_id ), 'Soft deletion must remove old content while retaining replies and the native row.' );
$reply_to_deleted = CommentService::create( $public_post, 'Reply after deletion.', $pending_id, 'rest-nonce', 7 );
sabri_phase3c_assert( empty( $reply_to_deleted['ok'] ) && 'invalid_comment_parent' === $reply_to_deleted['code'], 'A soft-deleted comment must not accept new replies.' );

$sabri_test_transients = array();
$sabri_test_current_user_id = 6;
for ( $i = 1; $i <= 10; $i++ ) {
	$result = CommentService::create( $rate_post, 'Rate-limited comment number ' . $i . '.', 0, 'rest-nonce', 6 );
	sabri_phase3c_assert( ! empty( $result['ok'] ), 'Each of the first ten bounded comment attempts must pass.' );
}
$rate_limited = CommentService::create( $rate_post, 'Eleventh comment attempt.', 0, 'rest-nonce', 6 );
sabri_phase3c_assert( empty( $rate_limited['ok'] ) && 429 === $rate_limited['status'], 'The eleventh comment attempt in ten minutes must be rate-limited.' );

$sabri_test_rest_routes = array();
RestComments::register_routes();
$comments_route = RestFoundation::NAMESPACE . '/posts/(?P<id>\d+)/comments';
$comment_route  = RestFoundation::NAMESPACE . '/comments/(?P<id>\d+)';
sabri_phase3c_assert( isset( $sabri_test_rest_routes[ $comments_route ], $sabri_test_rest_routes[ $comment_route ] ), 'Checkpoint 3C REST routes must be registered under the frozen namespace.' );
sabri_phase3c_assert( RestComments::validate_id( $public_post ) && ! RestComments::validate_id( '-1' ), 'REST comment IDs must be strict positive integers.' );
$sabri_test_current_user_id = 6;
$valid_request = new Sabri_Phase3C_Request( array( 'id' => $second_post, 'content' => 'REST comment body.', 'parent_id' => 0 ), array( 'X-WP-Nonce' => 'rest-nonce' ) );
$invalid_request = new Sabri_Phase3C_Request( array( 'id' => $second_post ), array( 'X-WP-Nonce' => 'invalid' ) );
sabri_phase3c_assert( RestComments::private_permission( $valid_request ) && ! RestComments::private_permission( $invalid_request ), 'Comment writes must require the current session and a valid REST nonce.' );

$sabri_test_current_user_id = 1;
$sabri_test_current_caps = array( 'sabri_feed_moderate_posts' => true );
$bar = SocialRuntime::render_action_bar( $public_post );
sabri_phase3c_assert( false !== strpos( $bar, '#sabri-hnf-comments-' . $public_post ) && false !== strpos( $bar, 'data-count="comment"' ), 'Feed action bars must link to the direct post comment thread and show approved count.' );

$sabri_test_current_user_id = 0;
$sabri_test_current_caps = array();
$visitor_html = CommentRuntime::render_thread( $public_post );
sabri_phase3c_assert( false !== strpos( $visitor_html, 'Sign in to comment or reply.' ) && false !== strpos( $visitor_html, 'data-nonce=""' ), 'Visitors must receive a login state without a REST nonce.' );
sabri_phase3c_assert( false === strpos( $visitor_html, 'Pending review' ) && false !== strpos( $visitor_html, 'Moderator-approved public comment.' ), 'Visitor HTML must show approved comments only.' );
sabri_phase3c_assert( '' === CommentRuntime::render_thread( $pending_post ), 'Pending posts must not expose a direct comment thread.' );

$sabri_test_current_user_id = 7;
$sabri_test_current_caps = array();
global $sabri_test_is_singular, $sabri_test_singular_post_type, $sabri_test_current_post_id;
$sabri_test_is_singular = true;
$sabri_test_singular_post_type = 'post';
$sabri_test_current_post_id = $public_post;
CommentRuntime::reset_runtime_guards();
$single_once = CommentRuntime::append_single_comments( 'Body' );
$single_twice = CommentRuntime::append_single_comments( $single_once );
sabri_phase3c_assert( 1 === substr_count( $single_once, 'data-sabri-comments' ) && 1 === substr_count( $single_twice, 'data-sabri-comments' ), 'Direct single-post comments must append exactly once.' );

$features = Phase3FeatureSettings::get();
$features['comments_enabled'] = 0;
update_option( Phase3FeatureSettings::OPTION_NAME, $features, false );
sabri_phase3c_assert( '' === CommentRuntime::render_thread( $public_post ), 'Disabling the comments feature flag must remove the public runtime.' );
$disabled_create = CommentService::create( $public_post, 'Disabled comment.', 0, 'rest-nonce', 7 );
sabri_phase3c_assert( empty( $disabled_create['ok'] ) && 'comments_disabled' === $disabled_create['code'], 'Disabled comments must fail closed at the service layer.' );

$identity = Plugin::identity();
sabri_phase3c_assert( '1.0.3' === $identity['version'] && '1.0.0' === $identity['schema_version'], 'Checkpoint 3C must preserve the accepted plugin and schema versions.' );

if ( $phase3c_failures ) {
	echo "FAILED\n";
	foreach ( $phase3c_failures as $failure ) {
		echo '- ' . $failure . "\n";
	}
	exit( 1 );
}

echo "OK - Phase 3 checkpoint 3C comments and replies tests passed.\n";
