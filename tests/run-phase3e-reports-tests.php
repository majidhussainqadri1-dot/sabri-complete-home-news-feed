<?php
/**
 * Phase 3E Reports and Moderation Queue tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/comment-stubs.php';

use Sabri\HomeNewsFeed\CommentPolicy;
use Sabri\HomeNewsFeed\Phase3FeatureSettings;
use Sabri\HomeNewsFeed\Plugin;
use Sabri\HomeNewsFeed\PostMetadata;
use Sabri\HomeNewsFeed\ReportPolicy;
use Sabri\HomeNewsFeed\ReportRuntime;
use Sabri\HomeNewsFeed\ReportService;
use Sabri\HomeNewsFeed\RestFoundation;
use Sabri\HomeNewsFeed\RestReports;
use Sabri\HomeNewsFeed\SocialRuntime;

if ( ! function_exists( 'get_comment_link' ) ) {
	function get_comment_link( $comment_id ) { return home_url( '?comment=' . (int) $comment_id . '#comment-' . (int) $comment_id ); }
}

final class Sabri_Phase3E_Request {
	private $params;
	private $headers;
	public function __construct( array $params = array(), array $headers = array() ) { $this->params = $params; $this->headers = $headers; }
	public function get_param( $key ) { return array_key_exists( $key, $this->params ) ? $this->params[ $key ] : null; }
	public function get_header( $key ) {
		foreach ( $this->headers as $name => $value ) {
			if ( strtolower( $name ) === strtolower( $key ) ) { return $value; }
		}
		return '';
	}
}

final class Sabri_Phase3E_WPDB {
	public $prefix = 'wp_';
	public $insert_id = 0;
	public $race_report = false;

	public function prepare( $query, ...$args ) {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }
		foreach ( $args as $value ) {
			$position_d = strpos( $query, '%d' );
			$position_s = strpos( $query, '%s' );
			if ( false === $position_d && false === $position_s ) { break; }
			if ( false !== $position_d && ( false === $position_s || $position_d < $position_s ) ) {
				$query = substr_replace( $query, (string) (int) $value, $position_d, 2 );
			} else {
				$replacement = "'" . str_replace( "'", "\\'", (string) $value ) . "'";
				$query = substr_replace( $query, $replacement, $position_s, 2 );
			}
		}
		return $query;
	}

	public function insert( $table, $data, $formats = null ) {
		global $sabri_test_rows;
		unset( $formats );
		$rows = isset( $sabri_test_rows[ $table ] ) ? $sabri_test_rows[ $table ] : array();
		$this->insert_id = count( $rows ) + 1;
		$data['id'] = $this->insert_id;
		$rows[] = $data;
		$sabri_test_rows[ $table ] = $rows;
		if ( $this->race_report && false !== strpos( $table, 'sabri_feed_reports' ) ) {
			$this->race_report = false;
			return false;
		}
		return 1;
	}

	public function update( $table, $data, $where, $formats = null, $where_formats = null ) {
		global $sabri_test_rows;
		unset( $formats, $where_formats );
		$affected = 0;
		foreach ( isset( $sabri_test_rows[ $table ] ) ? $sabri_test_rows[ $table ] : array() as $index => $row ) {
			if ( $this->matches( $row, $where ) ) {
				$sabri_test_rows[ $table ][ $index ] = array_merge( $row, $data );
				$affected++;
			}
		}
		return $affected;
	}

	public function get_row( $query, $output = null ) {
		global $sabri_test_rows;
		unset( $output );
		if ( ! preg_match( '/FROM `([^`]+)`/', $query, $table_match ) ) { return null; }
		$rows = array_reverse( isset( $sabri_test_rows[ $table_match[1] ] ) ? $sabri_test_rows[ $table_match[1] ] : array() );
		if ( preg_match( '/WHERE id = ([0-9]+)/', $query, $id_match ) ) {
			foreach ( $rows as $row ) { if ( (int) $row['id'] === (int) $id_match[1] ) { return $row; } }
			return null;
		}
		if ( preg_match( "/reporter_user_id = ([0-9]+) AND object_type = '([^']+)' AND object_id = ([0-9]+) AND duplicate_hash = '([a-f0-9]{64})'/", $query, $match ) ) {
			foreach ( $rows as $row ) {
				if ( (int) $row['reporter_user_id'] === (int) $match[1] && $row['object_type'] === $match[2] && (int) $row['object_id'] === (int) $match[3] && $row['duplicate_hash'] === $match[4] ) { return $row; }
			}
		}
		return null;
	}

	public function get_results( $query, $output = null ) {
		global $sabri_test_rows;
		unset( $output );
		if ( ! preg_match( '/FROM `([^`]+)`/', $query, $table_match ) ) { return array(); }
		$rows = isset( $sabri_test_rows[ $table_match[1] ] ) ? array_values( $sabri_test_rows[ $table_match[1] ] ) : array();
		$rows = array_values( array_filter( $rows, function ( $row ) use ( $query ) { return $this->row_matches_query( $row, $query ); } ) );
		usort( $rows, static function ( $a, $b ) { return (int) $b['id'] <=> (int) $a['id']; } );
		$limit = preg_match( '/LIMIT ([0-9]+)/', $query, $match ) ? (int) $match[1] : count( $rows );
		$offset = preg_match( '/OFFSET ([0-9]+)/', $query, $match ) ? (int) $match[1] : 0;
		return array_slice( $rows, $offset, $limit );
	}

	public function get_var( $query ) {
		global $sabri_test_rows;
		if ( ! preg_match( '/FROM `([^`]+)`/', $query, $table_match ) ) { return 0; }
		$rows = isset( $sabri_test_rows[ $table_match[1] ] ) ? $sabri_test_rows[ $table_match[1] ] : array();
		return count( array_filter( $rows, function ( $row ) use ( $query ) { return $this->row_matches_query( $row, $query ); } ) );
	}

	private function row_matches_query( array $row, $query ) {
		foreach ( array( 'status', 'reason', 'object_type' ) as $column ) {
			if ( preg_match( "/{$column} = '([^']+)'/", $query, $match ) && (string) $row[ $column ] !== (string) $match[1] ) { return false; }
		}
		return true;
	}

	private function matches( array $row, array $where ) {
		foreach ( $where as $key => $value ) {
			if ( ! array_key_exists( $key, $row ) || (string) $row[ $key ] !== (string) $value ) { return false; }
		}
		return true;
	}
}

$phase3e_failures = array();
function sabri_phase3e_assert( $condition, $message ) { global $phase3e_failures; if ( ! $condition ) { $phase3e_failures[] = $message; } }
function sabri_phase3e_enable_reports() {
	$features = Phase3FeatureSettings::defaults();
	$features['reports_enabled'] = 1;
	update_option( Phase3FeatureSettings::OPTION_NAME, $features, false );
}
function sabri_phase3e_add_comment( $post_id, $user_id, $approved = '1', $type = CommentPolicy::COMMENT_TYPE, $content = 'Approved reportable comment.' ) {
	$user = get_userdata( $user_id );
	return wp_insert_comment(
		array(
			'comment_post_ID' => $post_id,
			'comment_author' => $user ? $user->display_name : 'Member',
			'comment_author_email' => $user ? $user->user_email : '',
			'comment_content' => $content,
			'comment_type' => $type,
			'comment_parent' => 0,
			'user_id' => $user_id,
			'comment_approved' => $approved,
			'comment_date' => gmdate( 'Y-m-d H:i:s' ),
			'comment_date_gmt' => gmdate( 'Y-m-d H:i:s' ),
		)
	);
}

sabri_test_reset_state( true );
sabri_test_reset_comments();
global $wpdb, $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_rows, $sabri_test_transients, $sabri_test_rest_routes;
$wpdb = new Sabri_Phase3E_WPDB();
$report_table = $wpdb->prefix . 'sabri_feed_reports';
$audit_table = $wpdb->prefix . 'sabri_feed_audit_log';
$sabri_test_rows[ $report_table ] = array();
$sabri_test_rows[ $audit_table ] = array();

sabri_phase3e_assert( 0 === Phase3FeatureSettings::defaults()['reports_enabled'], 'Report submissions must remain gated by default.' );

$public_post = sabri_test_add_post(
	array( 'post_author' => 3, 'post_status' => 'publish', 'post_title' => 'Reportable doctor post' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);
$second_post = sabri_test_add_post(
	array( 'post_author' => 2, 'post_status' => 'publish', 'post_title' => 'Second reportable post' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);
$own_post = sabri_test_add_post(
	array( 'post_author' => 7, 'post_status' => 'publish', 'post_title' => 'Own post' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);
$pending_post = sabri_test_add_post(
	array( 'post_author' => 4, 'post_status' => 'publish', 'post_title' => 'Pending post' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'pending', PostMetadata::META_TYPE => 'standard-post' )
);

$approved_comment = sabri_phase3e_add_comment( $public_post, 3, '1' );
$pending_comment = sabri_phase3e_add_comment( $public_post, 3, '0', CommentPolicy::COMMENT_TYPE, 'Pending private comment.' );
$own_comment = sabri_phase3e_add_comment( $public_post, 7, '1', CommentPolicy::COMMENT_TYPE, 'Own comment.' );
$foreign_comment = sabri_phase3e_add_comment( $public_post, 3, '1', 'comment', 'Foreign native comment.' );
$deleted_comment = sabri_phase3e_add_comment( $public_post, 3, '1', CommentPolicy::COMMENT_TYPE, 'Deleted plugin comment.' );
update_comment_meta( $deleted_comment, CommentPolicy::META_DELETED, 1 );

$sabri_test_current_user_id = 7;
$sabri_test_current_caps = array();
$disabled = ReportService::create( 'post', $public_post, 'spam', '', 'rest-nonce', 7 );
sabri_phase3e_assert( empty( $disabled['ok'] ) && 'reports_disabled' === $disabled['code'], 'Disabled report runtime must reject writes.' );
sabri_phase3e_assert( '' === ReportRuntime::render_control( 'post', $public_post, 3 ), 'Disabled report runtime must not render a public control.' );

sabri_phase3e_enable_reports();
$created = ReportService::create( 'post', $public_post, 'spam', 'Repeated promotional content.', 'rest-nonce', 7 );
sabri_phase3e_assert( ! empty( $created['ok'] ) && 'report_submitted' === $created['code'] && true === $created['data']['submitted'], 'Authenticated member must submit a post report.' );
$public_json = wp_json_encode( $created );
sabri_phase3e_assert( false === strpos( $public_json, 'reporter_user_id' ) && false === strpos( $public_json, 'duplicate_hash' ) && false === strpos( $public_json, 'Repeated promotional content' ), 'Public report response must not expose confidential record data.' );

$duplicate = ReportService::create( 'post', $public_post, 'spam', 'Changed duplicate detail.', 'rest-nonce', 7 );
sabri_phase3e_assert( ! empty( $duplicate['ok'] ) && 1 === count( $sabri_test_rows[ $report_table ] ), 'Repeated identical report must be idempotent and keep one row.' );
$other_short = ReportService::create( 'post', $public_post, 'other', 'too short', 'rest-nonce', 7 );
sabri_phase3e_assert( empty( $other_short['ok'] ) && 'report_note_required' === $other_short['code'], 'Other reason must require a meaningful confidential note.' );
sabri_phase3e_assert( empty( ReportService::create( 'post', $public_post, 'unknown', '', 'rest-nonce', 7 )['ok'] ), 'Unknown report reasons must fail closed.' );
sabri_phase3e_assert( empty( ReportService::create( 'video', $public_post, 'spam', '', 'rest-nonce', 7 )['ok'] ), 'Unknown object types must fail closed.' );
sabri_phase3e_assert( empty( ReportService::create( 'post', $own_post, 'spam', '', 'rest-nonce', 7 )['ok'] ), 'Self-reporting a post must be forbidden.' );
sabri_phase3e_assert( empty( ReportService::create( 'post', $pending_post, 'spam', '', 'rest-nonce', 7 )['ok'] ), 'Pending post must not be reportable by another member.' );
$forged = ReportService::create( 'post', $public_post, 'harassment', '', 'rest-nonce', 6 );
sabri_phase3e_assert( empty( $forged['ok'] ) && 'authentication_required' === $forged['code'], 'Request data must not select another reporter identity.' );

$comment_report = ReportService::create( 'comment', $approved_comment, 'harassment', 'Abusive reply.', 'rest-nonce', 7 );
sabri_phase3e_assert( ! empty( $comment_report['ok'] ), 'Approved plugin comment must be reportable.' );
sabri_phase3e_assert( empty( ReportService::create( 'comment', $pending_comment, 'spam', '', 'rest-nonce', 7 )['ok'] ), 'Another user pending comment must not be reportable.' );
sabri_phase3e_assert( empty( ReportService::create( 'comment', $foreign_comment, 'spam', '', 'rest-nonce', 7 )['ok'] ), 'Foreign native comment type must not enter the plugin report system.' );
sabri_phase3e_assert( empty( ReportService::create( 'comment', $deleted_comment, 'spam', '', 'rest-nonce', 7 )['ok'] ), 'Soft-deleted comment must not be reportable.' );
sabri_phase3e_assert( empty( ReportService::create( 'comment', $own_comment, 'spam', '', 'rest-nonce', 7 )['ok'] ), 'Self-reporting a comment must be forbidden.' );

$wpdb->race_report = true;
$race = ReportService::create( 'post', $second_post, 'impersonation', 'Concurrent submission.', 'rest-nonce', 7 );
sabri_phase3e_assert( ! empty( $race['ok'] ), 'Concurrent unique report insert must recover through a safe re-read.' );
$race_rows = array_filter( $sabri_test_rows[ $report_table ], static function ( $row ) use ( $second_post ) { return 7 === (int) $row['reporter_user_id'] && $second_post === (int) $row['object_id'] && 'impersonation' === $row['reason']; } );
sabri_phase3e_assert( 1 === count( $race_rows ), 'Race recovery must leave one confidential report row.' );

$sabri_test_transients = array();
$sabri_test_current_user_id = 5;
$last = array();
for ( $i = 0; $i < 6; $i++ ) { $last = ReportService::create( 'post', $second_post, 'misinformation', 'Rate test detail.', 'rest-nonce', 5 ); }
sabri_phase3e_assert( empty( $last['ok'] ) && 429 === $last['status'], 'Sixth report attempt for one user/object within an hour must be rate-limited.' );

$sabri_test_current_user_id = 6;
$sabri_test_current_caps = array();
$denied_queue = ReportService::queue();
sabri_phase3e_assert( empty( $denied_queue['ok'] ) && 'report_permission_denied' === $denied_queue['code'], 'Ordinary members must not access the report queue.' );

$sabri_test_current_user_id = 1;
$sabri_test_current_caps = array( 'sabri_feed_manage_reports' => true );
$queue = ReportService::queue( array( 'status' => 'open', 'object_type' => 'post', 'page' => 1, 'per_page' => 100 ) );
sabri_phase3e_assert( ! empty( $queue['ok'] ) && $queue['data']['total'] >= 1, 'Authorized moderator must load the filtered confidential queue.' );
$queue_json = wp_json_encode( $queue['data'] );
sabri_phase3e_assert( false === strpos( $queue_json, 'duplicate_hash' ) && false === strpos( $queue_json, 'user_email' ) && false === strpos( $queue_json, '@example.com' ), 'Moderator serialization must exclude duplicate hashes and private account fields.' );

$report_id = (int) $sabri_test_rows[ $report_table ][0]['id'];
$triaged = ReportService::moderate( $report_id, 'triaged', 'Initial clinical review complete.', 1 );
sabri_phase3e_assert( ! empty( $triaged['ok'] ) && 'triaged' === $triaged['data']['report']['status'] && 'Initial clinical review complete.' === $triaged['data']['report']['moderator_note'], 'Moderator must triage a report with a private note.' );
$resolved = ReportService::moderate( $report_id, 'resolved', 'Action completed.', 1 );
sabri_phase3e_assert( ! empty( $resolved['ok'] ) && 'resolved' === $resolved['data']['report']['status'], 'Triaged report must allow resolution.' );
$invalid_transition = ReportService::moderate( $report_id, 'open', '', 1 );
sabri_phase3e_assert( empty( $invalid_transition['ok'] ) && 'invalid_report_transition' === $invalid_transition['code'], 'Resolved report must not reopen directly without triage.' );

$sabri_test_current_user_id = 6;
$sabri_test_current_caps = array();
$cross_user_update = ReportService::moderate( $report_id, 'triaged', 'Unauthorized note.', 6 );
sabri_phase3e_assert( empty( $cross_user_update['ok'] ) && 'report_permission_denied' === $cross_user_update['code'], 'Non-moderator must not update confidential reports.' );

// Existing reports remain manageable while public submission is disabled.
$features = Phase3FeatureSettings::defaults();
update_option( Phase3FeatureSettings::OPTION_NAME, $features, false );
$sabri_test_current_user_id = 1;
$sabri_test_current_caps = array( 'sabri_feed_manage_reports' => true );
sabri_phase3e_assert( ! empty( ReportService::queue()['ok'] ), 'Moderators must retain access to existing reports when new submissions are disabled.' );
sabri_phase3e_assert( empty( ReportService::create( 'post', $public_post, 'spam', '', 'rest-nonce', 1 )['ok'] ), 'Disabled submission flag must continue to reject new reports.' );

// REST permissions and contracts.
$sabri_test_rest_routes = array();
RestReports::register_routes();
$create_route = RestFoundation::NAMESPACE . '/reports';
$queue_route = RestFoundation::NAMESPACE . '/moderation/reports';
$update_route = RestFoundation::NAMESPACE . '/moderation/reports/(?P<id>\d+)';
sabri_phase3e_assert( isset( $sabri_test_rest_routes[ $create_route ], $sabri_test_rest_routes[ $queue_route ], $sabri_test_rest_routes[ $update_route ] ), 'Phase 3E REST routes must register under the frozen namespace.' );
sabri_phase3e_assert( RestReports::validate_id( 2 ) && ! RestReports::validate_id( '-1' ) && RestReports::validate_limit( 100 ) && ! RestReports::validate_limit( 101 ), 'Report REST identifiers and list sizes must remain strictly bounded.' );
sabri_phase3e_assert( RestReports::validate_reason( 'patient-privacy' ) && ! RestReports::validate_reason( 'secret' ), 'REST report reasons must use the frozen allow-list.' );

sabri_phase3e_enable_reports();
$sabri_test_current_user_id = 7;
$sabri_test_current_caps = array();
$valid_request = new Sabri_Phase3E_Request( array(), array( 'X-WP-Nonce' => 'rest-nonce' ) );
$invalid_request = new Sabri_Phase3E_Request( array(), array( 'X-WP-Nonce' => 'invalid' ) );
sabri_phase3e_assert( RestReports::create_permission( $valid_request ) && ! RestReports::create_permission( $invalid_request ), 'Public report REST write must require login, feature flag, and valid nonce.' );
sabri_phase3e_assert( ! RestReports::moderator_permission( $valid_request ), 'Ordinary member must not access moderator REST routes.' );
$sabri_test_current_user_id = 1;
$sabri_test_current_caps = array( 'sabri_feed_manage_reports' => true );
sabri_phase3e_assert( RestReports::moderator_permission( $valid_request ) && ! RestReports::moderator_permission( $invalid_request ), 'Moderator REST routes must require capability and valid nonce.' );

// Accessible UI and self-report exclusion.
sabri_phase3e_enable_reports();
$sabri_test_current_user_id = 7;
$sabri_test_current_caps = array();
$bar = SocialRuntime::render_action_bar( $public_post );
sabri_phase3e_assert( false !== strpos( $bar, 'data-sabri-report-form' ) && false !== strpos( $bar, 'Report' ), 'Visible post action bar must expose the confidential Report control when enabled.' );
$own_bar = SocialRuntime::render_action_bar( $own_post );
sabri_phase3e_assert( false === strpos( $own_bar, 'data-sabri-report-form' ), 'Own post must not render a self-report form.' );
$comment_control = ReportRuntime::render_control( 'comment', $approved_comment, 3 );
sabri_phase3e_assert( false !== strpos( $comment_control, 'data-object-type="comment"' ), 'Approved comment must render a comment-specific report form.' );
$sabri_test_current_user_id = 0;
$visitor_bar = SocialRuntime::render_action_bar( $public_post );
sabri_phase3e_assert( false !== strpos( $visitor_bar, 'Sign in to submit a confidential report' ) && false === strpos( $visitor_bar, 'data-nonce="rest-nonce"' ), 'Visitor report UI must show login state without an authenticated nonce.' );

$features = Phase3FeatureSettings::defaults();
update_option( Phase3FeatureSettings::OPTION_NAME, $features, false );
sabri_phase3e_assert( false === strpos( SocialRuntime::render_action_bar( $public_post ), 'data-sabri-report-form' ), 'Disabled report flag must remove the public report form.' );

$identity = Plugin::identity();
sabri_phase3e_assert( '1.0.1' === $identity['version'] && '1.0.0' === $identity['schema_version'], 'Checkpoint 3E must preserve accepted plugin and schema version 1.0.0.' );
sabri_phase3e_assert( ! empty( $sabri_test_rows[ $audit_table ] ), 'Report creation and moderation must produce plugin-owned audit records.' );

if ( $phase3e_failures ) {
	echo "FAILED\n";
	foreach ( $phase3e_failures as $failure ) { echo '- ' . $failure . "\n"; }
	exit( 1 );
}

echo "OK - Phase 3E reports and moderation tests passed.\n";
