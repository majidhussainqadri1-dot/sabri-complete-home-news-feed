<?php
/**
 * Phase 3F Polls tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\Composer;
use Sabri\HomeNewsFeed\DataRetention;
use Sabri\HomeNewsFeed\Database;
use Sabri\HomeNewsFeed\Phase3FeatureSettings;
use Sabri\HomeNewsFeed\Plugin;
use Sabri\HomeNewsFeed\PollComposerIntegration;
use Sabri\HomeNewsFeed\PollPolicy;
use Sabri\HomeNewsFeed\PollRuntime;
use Sabri\HomeNewsFeed\PollService;
use Sabri\HomeNewsFeed\PostMetadata;
use Sabri\HomeNewsFeed\RestFoundation;
use Sabri\HomeNewsFeed\RestPolls;

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) { unset( $action ); return 'rest-nonce'; }
}
if ( ! function_exists( 'wp_login_url' ) ) {
	function wp_login_url( $redirect = '' ) { return 'http://example.test/login?redirect=' . rawurlencode( (string) $redirect ); }
}

/** Minimal request object. */
final class Sabri_Phase3F_Request {
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

/** In-memory database adapter for Poll vote, aggregate, privacy, and race tests. */
final class Sabri_Phase3F_WPDB extends Sabri_Test_WPDB {
	public $insert_id = 0;
	public $race_poll_vote = false;

	public function insert( $table, $data, $formats = null ) {
		global $sabri_test_rows;
		unset( $formats );
		$rows = isset( $sabri_test_rows[ $table ] ) ? $sabri_test_rows[ $table ] : array();
		$this->insert_id = count( $rows ) + 1;
		$data['id'] = $this->insert_id;
		$rows[] = $data;
		$sabri_test_rows[ $table ] = $rows;
		if ( $this->race_poll_vote && false !== strpos( $table, 'sabri_feed_poll_votes' ) ) {
			$this->race_poll_vote = false;
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
		if ( preg_match( "/poll_post_id = ([0-9]+) AND user_id = ([0-9]+) AND anonymous_hash = '([^']*)' AND vote_group_key = '([^']+)'/", $query, $match ) ) {
			foreach ( $rows as $row ) {
				if ( (int) $row['poll_post_id'] === (int) $match[1] && (int) $row['user_id'] === (int) $match[2] && (string) $row['anonymous_hash'] === stripslashes( $match[3] ) && (string) $row['vote_group_key'] === stripslashes( $match[4] ) ) {
					return $row;
				}
			}
		}
		return null;
	}

	public function get_results( $query, $output = null ) {
		global $sabri_test_rows;
		if ( false !== strpos( $query, 'SHOW INDEX' ) || false !== strpos( $query, 'SELECT * FROM' ) ) {
			return parent::get_results( $query, $output );
		}
		unset( $output );
		if ( preg_match( "/SELECT option_key, COUNT\(\*\) AS total FROM `([^`]+)` WHERE poll_post_id = ([0-9]+) AND vote_group_key = '([^']+)' AND status = '([^']+)' GROUP BY option_key/", $query, $match ) ) {
			$counts = array();
			foreach ( isset( $sabri_test_rows[ $match[1] ] ) ? $sabri_test_rows[ $match[1] ] : array() as $row ) {
				if ( (int) $row['poll_post_id'] === (int) $match[2] && (string) $row['vote_group_key'] === stripslashes( $match[3] ) && (string) $row['status'] === stripslashes( $match[4] ) ) {
					$key = (string) $row['option_key'];
					$counts[ $key ] = isset( $counts[ $key ] ) ? $counts[ $key ] + 1 : 1;
				}
			}
			$out = array();
			foreach ( $counts as $key => $total ) { $out[] = array( 'option_key' => $key, 'total' => $total ); }
			return $out;
		}
		return array();
	}

	private function matches( array $row, array $where ) {
		foreach ( $where as $key => $value ) {
			if ( ! array_key_exists( $key, $row ) || (string) $row[ $key ] !== (string) $value ) { return false; }
		}
		return true;
	}
}

$phase3f_failures = array();
function sabri_phase3f_assert( $condition, $message ) { global $phase3f_failures; if ( ! $condition ) { $phase3f_failures[] = $message; } }
function sabri_phase3f_enable_polls() {
	$features = Phase3FeatureSettings::defaults();
	$features['polls_enabled'] = 1;
	update_option( Phase3FeatureSettings::OPTION_NAME, $features, false );
}
function sabri_phase3f_definition( $policy = 'after_vote', $allow_change = true, $closes_at = '' ) {
	return array(
		'question'       => 'Which option should the community choose?',
		'options'        => array( 'First choice', 'Second choice', 'Third choice' ),
		'results_policy' => $policy,
		'closes_at'      => $closes_at,
		'allow_change'   => $allow_change ? 1 : 0,
	);
}
function sabri_phase3f_add_poll( $definition, $author = 3, $review_state = 'approved', $visibility = 'public' ) {
	$post_id = sabri_test_add_post(
		array( 'post_author' => $author, 'post_status' => 'publish', 'post_title' => 'Poll post' ),
		array(
			PostMetadata::META_VISIBILITY   => $visibility,
			PostMetadata::META_REVIEW_STATE => $review_state,
			PostMetadata::META_TYPE         => 'poll',
		)
	);
	PollPolicy::save_definition( $post_id, $definition );
	return $post_id;
}

sabri_test_reset_state( true );
global $wpdb, $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_rows, $sabri_test_transients, $sabri_test_rest_routes, $sabri_test_filter_overrides;
$wpdb = new Sabri_Phase3F_WPDB();
Database::install();
$vote_table = $wpdb->prefix . 'sabri_feed_poll_votes';
$audit_table = $wpdb->prefix . 'sabri_feed_audit_log';
$sabri_test_rows[ $vote_table ] = array();
$sabri_test_rows[ $audit_table ] = array();

// Feature gate and definition validation.
sabri_phase3f_assert( 0 === Phase3FeatureSettings::defaults()['polls_enabled'], 'Poll runtime must remain disabled by default until staging acceptance.' );
sabri_phase3f_assert( ! PollPolicy::validate_definition( array( 'question' => 'Q', 'options' => array( 'Only one' ) ) )['valid'], 'Polls must require at least two options.' );
sabri_phase3f_assert( ! PollPolicy::validate_definition( array( 'question' => 'Q', 'options' => array( 'Same', 'Same' ) ) )['valid'], 'Duplicate Poll options must fail closed.' );
sabri_phase3f_assert( ! PollPolicy::validate_definition( array( 'question' => 'Q', 'options' => range( 1, 9 ) ) )['valid'], 'More than eight Poll options must fail closed before truncation.' );
$sabri_test_filter_overrides['sabri_feed_poll_now'] = strtotime( '2026-07-19 00:00:00 UTC' );
$past_close = PollPolicy::validate_definition( sabri_phase3f_definition( 'after_close', true, '2026-07-18 23:00:00' ), true );
sabri_phase3f_assert( empty( $past_close['valid'] ), 'New Poll close time must be in the future.' );
unset( $sabri_test_filter_overrides['sabri_feed_poll_now'] );

// Composer gate and persisted definition.
$sabri_test_current_user_id = 2;
$sabri_test_current_caps = array( 'sabri_feed_create_posts' => true, 'sabri_feed_publish_posts' => true );
$composer_input = array(
	'composer_action' => 'publish',
	'title'           => 'Community Poll',
	'content'         => 'Please choose one option.',
	'feed_type'       => 'poll',
	'visibility'      => 'public',
	'comments_enabled'=> 1,
	'poll'            => sabri_phase3f_definition( 'after_vote', true, gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) ),
);
$disabled_create = Composer::create_or_update_from_request( $composer_input, array(), 2 );
sabri_phase3f_assert( empty( $disabled_create['ok'] ) && 'polls_disabled' === $disabled_create['code'], 'Composer must reject Poll creation while the feature gate is disabled.' );

sabri_phase3f_enable_polls();
$filtered_settings = PollComposerIntegration::filter_settings( array( 'composer' => array( 'allowed_feed_types' => array( 'standard-post' ) ) ) );
sabri_phase3f_assert( in_array( 'poll', $filtered_settings['composer']['allowed_feed_types'], true ), 'Enabled Poll feature must add Poll to the Composer allow-list.' );
$created_poll = Composer::create_or_update_from_request( $composer_input, array(), 2 );
sabri_phase3f_assert( ! empty( $created_poll['ok'] ) && ! empty( $created_poll['post_id'] ), 'Authorized Composer must save a valid Poll post.' );
$composer_poll_id = ! empty( $created_poll['post_id'] ) ? (int) $created_poll['post_id'] : 0;
sabri_phase3f_assert( $composer_poll_id > 0 && PollPolicy::is_poll( $composer_poll_id ), 'Composer must persist the bounded Poll definition and Poll feed type.' );

// Main voting, idempotency, two-user aggregate privacy, and replacement.
$poll_id = sabri_phase3f_add_poll( sabri_phase3f_definition() );
$sabri_test_current_user_id = 7;
$sabri_test_current_caps = array();
$vote = PollService::vote( $poll_id, 'option-1', 'rest-nonce', 7 );
sabri_phase3f_assert( ! empty( $vote['ok'] ) && 'option-1' === $vote['data']['current_option'] && 1 === $vote['data']['total_votes'], 'First authenticated vote must persist and reveal aggregate results to the voter.' );
$vote_again = PollService::vote( $poll_id, 'option-1', 'rest-nonce', 7 );
sabri_phase3f_assert( ! empty( $vote_again['ok'] ), 'Repeated identical vote must be idempotent.' );
$rows_user7 = array_filter( $sabri_test_rows[ $vote_table ], static function ( $row ) use ( $poll_id ) { return (int) $row['poll_post_id'] === $poll_id && 7 === (int) $row['user_id']; } );
sabri_phase3f_assert( 1 === count( $rows_user7 ), 'Repeated vote must retain one natural-key row.' );

$sabri_test_current_user_id = 6;
$vote_two = PollService::vote( $poll_id, 'option-2', 'rest-nonce', 6 );
sabri_phase3f_assert( ! empty( $vote_two['ok'] ) && 2 === $vote_two['data']['total_votes'], 'Two users must produce aggregate counts.' );
$aggregate_json = wp_json_encode( $vote_two['data'] );
sabri_phase3f_assert( false === strpos( $aggregate_json, 'user_id' ) && false === strpos( $aggregate_json, 'anonymous_hash' ) && false === strpos( $aggregate_json, '@example.com' ), 'Poll results must never expose voter identities or account fields.' );

$sabri_test_current_user_id = 7;
$replace = PollService::vote( $poll_id, 'option-3', 'rest-nonce', 7 );
sabri_phase3f_assert( ! empty( $replace['ok'] ) && 'option-3' === $replace['data']['current_option'], 'Open Poll with allow-change must replace the existing vote in place.' );
$remove = PollService::remove_vote( $poll_id, 'rest-nonce', 7 );
sabri_phase3f_assert( ! empty( $remove['ok'] ) && false === $remove['data']['has_voted'], 'Open Poll with allow-change must permit vote removal.' );

// Final-vote policy blocks replacement and removal.
$final_poll = sabri_phase3f_add_poll( sabri_phase3f_definition( 'after_vote', false ) );
$final_vote = PollService::vote( $final_poll, 'option-1', 'rest-nonce', 7 );
sabri_phase3f_assert( ! empty( $final_vote['ok'] ) && false === $final_vote['data']['can_remove'], 'Final-vote Poll must hide removal state after voting.' );
$final_replace = PollService::vote( $final_poll, 'option-2', 'rest-nonce', 7 );
sabri_phase3f_assert( empty( $final_replace['ok'] ) && 'poll_vote_change_disabled' === $final_replace['code'], 'Final-vote Poll must reject vote replacement.' );
$final_remove = PollService::remove_vote( $final_poll, 'rest-nonce', 7 );
sabri_phase3f_assert( empty( $final_remove['ok'] ) && 'poll_vote_change_disabled' === $final_remove['code'], 'Final-vote Poll must reject vote removal.' );

// Results policies and closing rules.
$always_poll = sabri_phase3f_add_poll( sabri_phase3f_definition( 'always' ) );
$sabri_test_current_user_id = 0;
$always_results = PollService::results( $always_poll );
sabri_phase3f_assert( ! empty( $always_results['ok'] ) && true === $always_results['data']['results_visible'] && 0 === $always_results['data']['total_votes'], 'Always policy must expose zero-safe aggregate results to visitors.' );

$after_vote_poll = sabri_phase3f_add_poll( sabri_phase3f_definition( 'after_vote' ) );
$visitor_hidden = PollService::results( $after_vote_poll );
sabri_phase3f_assert( ! empty( $visitor_hidden['ok'] ) && false === $visitor_hidden['data']['results_visible'] && null === $visitor_hidden['data']['total_votes'], 'After-vote policy must hide counts from non-voters.' );

$closed_poll = sabri_phase3f_add_poll( sabri_phase3f_definition( 'after_close', true, '2026-07-18 23:00:00' ) );
$sabri_test_filter_overrides['sabri_feed_poll_now'] = strtotime( '2026-07-19 00:00:00 UTC' );
$closed_results = PollService::results( $closed_poll );
sabri_phase3f_assert( ! empty( $closed_results['ok'] ) && true === $closed_results['data']['closed'] && true === $closed_results['data']['results_visible'], 'After-close policy must expose aggregate results only after closing.' );
$sabri_test_current_user_id = 7;
$closed_vote = PollService::vote( $closed_poll, 'option-1', 'rest-nonce', 7 );
sabri_phase3f_assert( empty( $closed_vote['ok'] ) && 'poll_closed' === $closed_vote['code'], 'Closed Poll must reject vote mutations.' );
unset( $sabri_test_filter_overrides['sabri_feed_poll_now'] );

// Invalid, pending, non-poll, and forged identity boundaries.
$pending_poll = sabri_phase3f_add_poll( sabri_phase3f_definition(), 3, 'pending' );
$standard_post = sabri_test_add_post(
	array( 'post_author' => 3, 'post_status' => 'publish' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);
sabri_phase3f_assert( empty( PollService::vote( $poll_id, 'unknown-option', 'rest-nonce', 7 )['ok'] ), 'Unknown Poll option must fail closed.' );
sabri_phase3f_assert( empty( PollService::vote( $pending_poll, 'option-1', 'rest-nonce', 7 )['ok'] ), 'Pending Poll must reject social writes.' );
sabri_phase3f_assert( empty( PollService::vote( $standard_post, 'option-1', 'rest-nonce', 7 )['ok'] ), 'Non-poll post must reject Poll voting.' );
$forged = PollService::vote( $poll_id, 'option-1', 'rest-nonce', 6 );
sabri_phase3f_assert( empty( $forged['ok'] ) && 'authentication_required' === $forged['code'], 'Request data must not select another voter identity.' );

// Concurrent insert recovery.
$race_poll = sabri_phase3f_add_poll( sabri_phase3f_definition() );
$wpdb->race_poll_vote = true;
$race = PollService::vote( $race_poll, 'option-2', 'rest-nonce', 7 );
sabri_phase3f_assert( ! empty( $race['ok'] ) && 'option-2' === $race['data']['current_option'], 'Concurrent unique insert must recover through a safe natural-key re-read.' );
$race_rows = array_filter( $sabri_test_rows[ $vote_table ], static function ( $row ) use ( $race_poll ) { return (int) $row['poll_post_id'] === $race_poll && 7 === (int) $row['user_id']; } );
sabri_phase3f_assert( 1 === count( $race_rows ), 'Race recovery must retain one Poll vote row.' );

// Rate limiting is isolated by user and Poll object.
$sabri_test_transients = array();
$rate_poll = sabri_phase3f_add_poll( sabri_phase3f_definition() );
$limited = null;
for ( $attempt = 1; $attempt <= 21; $attempt++ ) {
	$limited = PollService::vote( $rate_poll, 'option-1', 'rest-nonce', 7 );
}
sabri_phase3f_assert( empty( $limited['ok'] ) && 429 === $limited['status'], 'Poll vote actions must enforce the bounded per-user/per-poll rate limit.' );
$sabri_test_current_user_id = 6;
$other_user_after_limit = PollService::vote( $rate_poll, 'option-2', 'rest-nonce', 6 );
sabri_phase3f_assert( ! empty( $other_user_after_limit['ok'] ), 'Rate-limit buckets must remain isolated by user.' );

// Definition lock after voting starts and closed-definition edit rejection.
$sabri_test_current_user_id = 7;
PollService::vote( $composer_poll_id, 'option-1', 'rest-nonce', 7 );
$sabri_test_current_user_id = 2;
$sabri_test_current_caps = array( 'sabri_feed_create_posts' => true, 'sabri_feed_publish_posts' => true, 'edit_post' => true );
$changed_input = $composer_input;
$changed_input['post_id'] = $composer_poll_id;
$changed_input['poll']['options'] = array( 'Changed A', 'Changed B' );
$locked_edit = Composer::create_or_update_from_request( $changed_input, array(), 2 );
sabri_phase3f_assert( empty( $locked_edit['ok'] ) && 'poll_definition_locked' === $locked_edit['code'], 'Poll definition must lock after the first active vote.' );

$closed_author_poll = sabri_phase3f_add_poll( sabri_phase3f_definition( 'after_close', true, '2026-07-18 23:00:00' ), 2 );
$sabri_test_filter_overrides['sabri_feed_poll_now'] = strtotime( '2026-07-19 00:00:00 UTC' );
$closed_edit_input = $composer_input;
$closed_edit_input['post_id'] = $closed_author_poll;
$closed_edit = Composer::create_or_update_from_request( $closed_edit_input, array(), 2 );
sabri_phase3f_assert( empty( $closed_edit['ok'] ) && 'poll_closed_edit_forbidden' === $closed_edit['code'], 'Closed Poll definition must be immutable.' );
unset( $sabri_test_filter_overrides['sabri_feed_poll_now'] );

// REST contracts and permissions.
$sabri_test_rest_routes = array();
RestPolls::register_routes();
$vote_route = RestFoundation::NAMESPACE . '/polls/(?P<id>\d+)/vote';
$results_route = RestFoundation::NAMESPACE . '/polls/(?P<id>\d+)/results';
sabri_phase3f_assert( isset( $sabri_test_rest_routes[ $vote_route ], $sabri_test_rest_routes[ $results_route ] ), 'Poll REST vote and results routes must be registered.' );
$sabri_test_current_user_id = 7;
$sabri_test_current_caps = array();
$valid_request = new Sabri_Phase3F_Request( array( 'id' => $poll_id, 'option_key' => 'option-1' ), array( 'X-WP-Nonce' => 'rest-nonce' ) );
$bad_nonce_request = new Sabri_Phase3F_Request( array( 'id' => $poll_id, 'option_key' => 'option-1' ), array( 'X-WP-Nonce' => 'bad' ) );
sabri_phase3f_assert( RestPolls::private_permission( $valid_request ), 'Authenticated Poll mutation with valid nonce must pass route permission.' );
sabri_phase3f_assert( ! RestPolls::private_permission( $bad_nonce_request ), 'Invalid Poll REST nonce must fail closed.' );
sabri_phase3f_assert( RestPolls::validate_id( '12' ) && ! RestPolls::validate_id( '-12' ), 'Poll REST IDs must be strict positive integers.' );
sabri_phase3f_assert( RestPolls::validate_option_key( 'option-1' ) && ! RestPolls::validate_option_key( 'bad option!' ), 'Poll option keys must use the bounded route format.' );

// Accessible rendering, visitor nonce privacy, and direct/feed integration.
$sabri_test_current_user_id = 0;
$visitor_html = PollRuntime::render_poll( $always_poll );
sabri_phase3f_assert( false !== strpos( $visitor_html, 'data-sabri-poll' ) && false !== strpos( $visitor_html, 'Sign in to vote' ), 'Visitor Poll markup must retain a readable sign-in state.' );
sabri_phase3f_assert( false === strpos( $visitor_html, 'rest-nonce' ) && false === strpos( $visitor_html, '@example.com' ), 'Visitor Poll markup must not leak nonce or account data.' );
$feed_template = file_get_contents( dirname( __DIR__ ) . '/templates/feed-card.php' );
sabri_phase3f_assert( false !== strpos( $feed_template, 'PollRuntime::render_poll' ), 'Feed cards must render Polls before social actions.' );

// Privacy export and erasure.
$sabri_test_current_user_id = 6;
$privacy_poll = sabri_phase3f_add_poll( sabri_phase3f_definition( 'always' ) );
PollService::vote( $privacy_poll, 'option-2', 'rest-nonce', 6 );
$export = DataRetention::exporter( 'patient@example.com', 1 );
$export_json = wp_json_encode( $export );
sabri_phase3f_assert( false !== strpos( $export_json, 'Poll post ID' ) && false !== strpos( $export_json, 'option-2' ), 'Personal data export must include the requesting user’s Poll choice.' );
DataRetention::eraser( 'patient@example.com', 1 );
$erased_rows = array_values( array_filter( $sabri_test_rows[ $vote_table ], static function ( $row ) use ( $privacy_poll ) { return (int) $row['poll_post_id'] === $privacy_poll; } ) );
$erased = ! empty( $erased_rows ) ? end( $erased_rows ) : array();
sabri_phase3f_assert( isset( $erased['user_id'], $erased['status'], $erased['anonymous_hash'] ) && 0 === (int) $erased['user_id'] && 'removed' === $erased['status'] && 64 === strlen( $erased['anonymous_hash'] ), 'Account erasure must remove active Poll identity and retain only a bounded hash.' );

$identity = Plugin::identity();
sabri_phase3f_assert( '1.0.3' === $identity['version'] && '1.0.0' === $identity['schema_version'], 'Checkpoint 3F must preserve accepted plugin and schema version 1.0.0.' );

if ( $phase3f_failures ) {
	echo "FAILED\n";
	foreach ( $phase3f_failures as $failure ) { echo '- ' . $failure . "\n"; }
	exit( 1 );
}

echo "OK - Phase 3F Polls tests passed.\n";
