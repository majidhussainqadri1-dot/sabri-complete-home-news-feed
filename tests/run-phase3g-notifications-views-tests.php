<?php
/**
 * Phase 3G Notification Bridge and Views tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/comment-stubs.php';

use Sabri\HomeNewsFeed\CommentPolicy;
use Sabri\HomeNewsFeed\DataRetention;
use Sabri\HomeNewsFeed\Database;
use Sabri\HomeNewsFeed\EngagementService;
use Sabri\HomeNewsFeed\NotificationBridge;
use Sabri\HomeNewsFeed\Phase3FeatureSettings;
use Sabri\HomeNewsFeed\PostMetadata;
use Sabri\HomeNewsFeed\ReactionService;
use Sabri\HomeNewsFeed\Settings;
use Sabri\HomeNewsFeed\SocialRuntime;
use Sabri\HomeNewsFeed\ViewRuntime;
use Sabri\HomeNewsFeed\ViewService;

if ( ! function_exists( 'is_preview' ) ) {
	function is_preview() { global $sabri_phase3g_preview; return ! empty( $sabri_phase3g_preview ); }
}
if ( ! function_exists( 'is_feed' ) ) {
	function is_feed() { global $sabri_phase3g_feed; return ! empty( $sabri_phase3g_feed ); }
}
if ( ! function_exists( 'is_robots' ) ) {
	function is_robots() { global $sabri_phase3g_robots; return ! empty( $sabri_phase3g_robots ); }
}
if ( ! function_exists( 'is_trackback' ) ) {
	function is_trackback() { global $sabri_phase3g_trackback; return ! empty( $sabri_phase3g_trackback ); }
}

$phase3g_payloads = array();
function sabri_phase3g_notification_callback( $payload ) {
	global $phase3g_payloads;
	$phase3g_payloads[] = $payload;
}
function sabri_phase3g_throwing_callback( $payload ) {
	unset( $payload );
	throw new RuntimeException( 'Private connector failure detail.' );
}

final class Sabri_Phase3G_WPDB extends Sabri_Test_WPDB {
	public $insert_id = 0;
	public $race_view = false;

	public function insert( $table, $data, $formats = null ) {
		global $sabri_test_rows;
		unset( $formats );
		$rows = isset( $sabri_test_rows[ $table ] ) ? array_values( $sabri_test_rows[ $table ] ) : array();
		$this->insert_id = count( $rows ) + 1;
		$data['id'] = $this->insert_id;
		$rows[] = $data;
		$sabri_test_rows[ $table ] = $rows;
		if ( $this->race_view && false !== strpos( $table, 'sabri_feed_views' ) ) {
			$this->race_view = false;
			return false;
		}
		return 1;
	}

	public function update( $table, $data, $where, $formats = null, $where_formats = null ) {
		global $sabri_test_rows;
		unset( $formats, $where_formats );
		$affected = 0;
		foreach ( isset( $sabri_test_rows[ $table ] ) ? $sabri_test_rows[ $table ] : array() as $index => $row ) {
			if ( $this->matches_where( $row, $where ) ) {
				$sabri_test_rows[ $table ][ $index ] = array_merge( $row, $data );
				$affected++;
			}
		}
		return $affected;
	}

	public function delete( $table, $where, $where_formats = null ) {
		global $sabri_test_rows;
		unset( $where_formats );
		$kept = array();
		$deleted = 0;
		foreach ( isset( $sabri_test_rows[ $table ] ) ? $sabri_test_rows[ $table ] : array() as $row ) {
			if ( $this->matches_where( $row, $where ) ) {
				$deleted++;
			} else {
				$kept[] = $row;
			}
		}
		$sabri_test_rows[ $table ] = $kept;
		return $deleted;
	}

	public function get_row( $query, $output = null ) {
		global $sabri_test_rows;
		unset( $output );
		if ( ! preg_match( '/FROM `([^`]+)`/', $query, $table_match ) ) {
			return null;
		}
		$rows = array_reverse( isset( $sabri_test_rows[ $table_match[1] ] ) ? $sabri_test_rows[ $table_match[1] ] : array() );
		foreach ( $rows as $row ) {
			if ( $this->matches_sql( $row, $query ) ) {
				return $row;
			}
		}
		return null;
	}

	public function get_var( $query ) {
		global $sabri_test_rows;
		if ( preg_match( '/SELECT COALESCE\(SUM\(view_count\), 0\) FROM `([^`]+)`/', $query, $table_match ) ) {
			$total = 0;
			foreach ( isset( $sabri_test_rows[ $table_match[1] ] ) ? $sabri_test_rows[ $table_match[1] ] : array() as $row ) {
				if ( $this->matches_sql( $row, $query ) ) {
					$total += isset( $row['view_count'] ) ? (int) $row['view_count'] : 0;
				}
			}
			return $total;
		}
		return parent::get_var( $query );
	}

	public function get_results( $query, $output = null ) {
		global $sabri_test_rows;
		if ( false !== strpos( $query, 'SHOW INDEX' ) ) {
			return parent::get_results( $query, $output );
		}
		unset( $output );
		if ( preg_match( '/SELECT \* FROM `([^`]+)` WHERE `([^`]+)` = ([0-9]+) ORDER BY id ASC/', $query, $match ) ) {
			$rows = array_values( array_filter( isset( $sabri_test_rows[ $match[1] ] ) ? $sabri_test_rows[ $match[1] ] : array(), static function ( $row ) use ( $match ) {
				return isset( $row[ $match[2] ] ) && (int) $row[ $match[2] ] === (int) $match[3];
			} ) );
			usort( $rows, static function ( $a, $b ) { return (int) $a['id'] <=> (int) $b['id']; } );
			return $rows;
		}
		if ( preg_match( '/SELECT id, post_id, view_date FROM `([^`]+)` WHERE user_id = ([0-9]+) ORDER BY id ASC/', $query, $match ) ) {
			$rows = array_values( array_filter( isset( $sabri_test_rows[ $match[1] ] ) ? $sabri_test_rows[ $match[1] ] : array(), static function ( $row ) use ( $match ) {
				return isset( $row['user_id'] ) && (int) $row['user_id'] === (int) $match[2];
			} ) );
			usort( $rows, static function ( $a, $b ) { return (int) $a['id'] <=> (int) $b['id']; } );
			return array_map( static function ( $row ) { return array( 'id' => $row['id'], 'post_id' => $row['post_id'], 'view_date' => $row['view_date'] ); }, $rows );
		}
		if ( preg_match( '/SELECT reaction_type, COUNT\(\*\) AS total FROM `([^`]+)`/', $query, $match ) ) {
			$counts = array();
			foreach ( isset( $sabri_test_rows[ $match[1] ] ) ? $sabri_test_rows[ $match[1] ] : array() as $row ) {
				if ( $this->matches_sql( $row, $query ) ) {
					$type = isset( $row['reaction_type'] ) ? $row['reaction_type'] : '';
					$counts[ $type ] = isset( $counts[ $type ] ) ? $counts[ $type ] + 1 : 1;
				}
			}
			$out = array();
			foreach ( $counts as $type => $total ) {
				$out[] = array( 'reaction_type' => $type, 'total' => $total );
			}
			return $out;
		}
		return parent::get_results( $query, ARRAY_A );
	}

	private function matches_sql( array $row, $query ) {
		if ( preg_match_all( '/\b([a-z_]+) = ([0-9]+)/i', $query, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				if ( ! array_key_exists( $match[1], $row ) || (int) $row[ $match[1] ] !== (int) $match[2] ) {
					return false;
				}
			}
		}
		if ( preg_match_all( "/\\b([a-z_]+) = '([^']*)'/i", $query, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				if ( ! array_key_exists( $match[1], $row ) || (string) $row[ $match[1] ] !== stripslashes( $match[2] ) ) {
					return false;
				}
			}
		}
		if ( preg_match( "/view_date >= '([^']+)'/", $query, $match ) && (string) $row['view_date'] < $match[1] ) {
			return false;
		}
		if ( preg_match( "/view_date <= '([^']+)'/", $query, $match ) && (string) $row['view_date'] > $match[1] ) {
			return false;
		}
		return true;
	}

	private function matches_where( array $row, array $where ) {
		foreach ( $where as $key => $value ) {
			if ( ! array_key_exists( $key, $row ) || (string) $row[ $key ] !== (string) $value ) {
				return false;
			}
		}
		return true;
	}
}

$phase3g_failures = array();
function sabri_phase3g_assert( $condition, $message ) {
	global $phase3g_failures;
	if ( ! $condition ) {
		$phase3g_failures[] = $message;
	}
}
function sabri_phase3g_features( $notifications, $views, $follows = false ) {
	$features = Phase3FeatureSettings::defaults();
	$features['notification_bridge_enabled'] = $notifications ? 1 : 0;
	$features['view_logging_enabled'] = $views ? 1 : 0;
	$features['follows_enabled'] = $follows ? 1 : 0;
	update_option( Phase3FeatureSettings::OPTION_NAME, $features, false );
}
function sabri_phase3g_settings( $callback = 'sabri_phase3g_notification_callback', $dedupe_days = 1 ) {
	$settings = Settings::defaults();
	$settings['integrations']['functions']['notifications'] = $callback;
	$settings['performance']['view_deduplication_days'] = $dedupe_days;
	$settings['privacy']['anonymize_views'] = 1;
	update_option( Settings::OPTION_NAME, $settings, false );
}
function sabri_phase3g_comment( $post_id, $user_id, $parent_id = 0, $approved = '1', $content = 'Private comment body must not enter notification payload.' ) {
	$user = get_userdata( $user_id );
	return wp_insert_comment(
		array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => $user ? $user->display_name : 'Member',
			'comment_author_email' => $user ? $user->user_email : '',
			'comment_content'      => $content,
			'comment_type'         => CommentPolicy::COMMENT_TYPE,
			'comment_parent'       => $parent_id,
			'user_id'              => $user_id,
			'comment_approved'     => $approved,
		)
	);
}

sabri_test_reset_state( true );
sabri_test_reset_comments();
global $wpdb, $sabri_test_rows, $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_filter_overrides, $sabri_test_transients;
global $sabri_test_is_singular, $sabri_test_singular_post_type, $sabri_test_current_post_id, $phase3g_payloads;
$wpdb = new Sabri_Phase3G_WPDB();
Database::install();
$view_table = $wpdb->prefix . 'sabri_feed_views';
$reaction_table = $wpdb->prefix . 'sabri_feed_reactions';
$sabri_test_rows[ $view_table ] = array();
$sabri_test_rows[ $reaction_table ] = array();

sabri_phase3g_assert( 0 === Phase3FeatureSettings::defaults()['notification_bridge_enabled'], 'Notification bridge must remain disabled by default.' );
sabri_phase3g_assert( 0 === Phase3FeatureSettings::defaults()['view_logging_enabled'], 'View logging must remain disabled by default.' );

$public_post = sabri_test_add_post(
	array( 'post_author' => 3, 'post_status' => 'publish', 'post_title' => 'Visible Phase 3G post' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);
$pending_post = sabri_test_add_post(
	array( 'post_author' => 3, 'post_status' => 'publish', 'post_title' => 'Pending Phase 3G post' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'pending', PostMetadata::META_TYPE => 'standard-post' )
);

sabri_phase3g_settings();
$disabled = NotificationBridge::post_event( 'post_reaction', 7, $public_post );
sabri_phase3g_assert( ! empty( $disabled['ok'] ) && 'notification_bridge_disabled' === $disabled['code'] && empty( $phase3g_payloads ), 'Disabled bridge must emit no notification payload.' );

sabri_phase3g_features( true, true );
$sent = NotificationBridge::post_event( 'post_reaction', 7, $public_post, array( 'state_key' => 'like', 'comment_content' => 'must be dropped' ) );
sabri_phase3g_assert( ! empty( $sent['ok'] ) && ! empty( $sent['data']['dispatched'] ) && 1 === count( $phase3g_payloads ), 'Enabled bridge must dispatch one post-author event.' );
$payload = $phase3g_payloads[0];
$payload_json = wp_json_encode( $payload );
sabri_phase3g_assert( 3 === (int) $payload['recipient_user_id'] && 7 === (int) $payload['actor_user_id'] && $public_post === (int) $payload['post_id'], 'Notification payload must contain the bounded actor, recipient, and post identity.' );
sabri_phase3g_assert( false === strpos( $payload_json, '@example.com' ) && false === strpos( $payload_json, 'comment_content' ) && false === strpos( $payload_json, 'option_key' ) && false === strpos( $payload_json, 'REMOTE_ADDR' ), 'Notification payload must exclude account, content, poll-choice, and request identifiers.' );
$duplicate = NotificationBridge::post_event( 'post_reaction', 7, $public_post, array( 'state_key' => 'like' ) );
sabri_phase3g_assert( 'notification_duplicate_suppressed' === $duplicate['code'] && 1 === count( $phase3g_payloads ), 'Duplicate notification events must be suppressed inside the bounded window.' );
$self = NotificationBridge::post_event( 'post_reaction', 3, $public_post );
sabri_phase3g_assert( 'self_notification_suppressed' === $self['code'] && 1 === count( $phase3g_payloads ), 'Self notifications must be suppressed.' );
$invalid = NotificationBridge::dispatch( 'unknown_event', 7, 3, 'post', $public_post );
sabri_phase3g_assert( empty( $invalid['ok'] ) && 'invalid_notification_event' === $invalid['code'], 'Unknown notification events must fail closed.' );

// Verify a real interaction service invokes the bridge only after a successful state write.
$sabri_test_current_user_id = 6;
delete_transient();
$phase3g_payloads = array();
$reaction = ReactionService::set( $public_post, 'like', 'rest-nonce', 6 );
sabri_phase3g_assert( ! empty( $reaction['ok'] ) && 1 === count( $phase3g_payloads ) && 'post_reaction' === $phase3g_payloads[0]['event'], 'Successful ReactionService writes must invoke the bridge.' );

// Approved top-level comments and replies use bounded recipients and no comment body.
delete_transient();
$phase3g_payloads = array();
$top_comment = sabri_phase3g_comment( $public_post, 5 );
NotificationBridge::handle_comment_created( $top_comment, '1' );
$reply = sabri_phase3g_comment( $public_post, 6, $top_comment );
NotificationBridge::handle_comment_created( $reply, '1' );
sabri_phase3g_assert( 2 === count( $phase3g_payloads ), 'Approved top-level comments and replies must each emit one event.' );
sabri_phase3g_assert( 3 === (int) $phase3g_payloads[0]['recipient_user_id'] && 'post_comment' === $phase3g_payloads[0]['event'], 'Top-level comment must notify the post author.' );
sabri_phase3g_assert( 5 === (int) $phase3g_payloads[1]['recipient_user_id'] && 'comment_reply' === $phase3g_payloads[1]['event'], 'Reply must notify the parent comment author.' );
sabri_phase3g_assert( false === strpos( wp_json_encode( $phase3g_payloads ), 'Private comment body' ), 'Comment content must never enter notification payloads.' );
$pending_comment = sabri_phase3g_comment( $public_post, 7, 0, '0' );
NotificationBridge::handle_comment_created( $pending_comment, '0' );
sabri_phase3g_assert( 2 === count( $phase3g_payloads ), 'Pending comments must not notify before approval.' );
NotificationBridge::handle_comment_status_transition( 'approved', 'unapproved', get_comment( $pending_comment ) );
sabri_phase3g_assert( 3 === count( $phase3g_payloads ), 'A pending plugin comment must notify when it becomes approved.' );

// Connector exceptions are isolated and expose no private exception details.
delete_transient();
sabri_phase3g_settings( 'sabri_phase3g_throwing_callback' );
$failed_bridge = NotificationBridge::follow_event( 7, 4 );
sabri_phase3g_assert( empty( $failed_bridge['ok'] ) && 'notification_bridge_failed' === $failed_bridge['code'], 'Connector exceptions must return a safe bridge error.' );
sabri_phase3g_assert( false === strpos( wp_json_encode( $failed_bridge ), 'Private connector failure detail' ), 'Connector exception details must never be exposed.' );
sabri_phase3g_settings();

// Deterministic view tests start on 2026-07-19 UTC.
$sabri_test_filter_overrides['sabri_feed_view_now'] = strtotime( '2026-07-19 12:00:00 UTC' );
$browser = array( 'REMOTE_ADDR' => '203.0.113.10', 'HTTP_USER_AGENT' => 'Mozilla/5.0 Phase3G Browser' );
$sabri_test_current_user_id = 7;
$first_view = ViewService::record( $public_post, 7, $browser );
$duplicate_view = ViewService::record( $public_post, 7, $browser );
sabri_phase3g_assert( ! empty( $first_view['ok'] ) && ! empty( $first_view['data']['counted'] ), 'First authenticated direct view must be counted.' );
sabri_phase3g_assert( 'view_already_counted' === $duplicate_view['code'] && 1 === count( $sabri_test_rows[ $view_table ] ), 'Repeated authenticated view inside the window must not create another row.' );
$forged = ViewService::record( $public_post, 6, $browser );
sabri_phase3g_assert( empty( $forged['ok'] ) && 'view_identity_mismatch' === $forged['code'], 'Explicit view identity must not select another account.' );

$sabri_test_current_user_id = 6;
ViewService::record( $public_post, 6, $browser );
$sabri_test_current_user_id = 0;
$guest = ViewService::record( $public_post, 0, $browser );
$guest_duplicate = ViewService::record( $public_post, 0, $browser );
sabri_phase3g_assert( ! empty( $guest['data']['counted'] ) && 'view_already_counted' === $guest_duplicate['code'], 'Guest HMAC identity must count once inside the window.' );
$guest_rows = array_values( array_filter( $sabri_test_rows[ $view_table ], static function ( $row ) { return 0 === (int) $row['user_id']; } ) );
sabri_phase3g_assert( 1 === count( $guest_rows ) && preg_match( '/^[a-f0-9]{64}$/', $guest_rows[0]['anonymous_hash'] ), 'Guest row must contain only a one-way HMAC identity.' );
sabri_phase3g_assert( false === strpos( wp_json_encode( $guest_rows ), '203.0.113.10' ) && false === strpos( wp_json_encode( $guest_rows ), 'Mozilla' ), 'Raw IP address and user agent must not be stored.' );
$dnt = ViewService::record( $public_post, 0, array( 'REMOTE_ADDR' => '203.0.113.11', 'HTTP_USER_AGENT' => 'Mozilla/5.0', 'HTTP_DNT' => '1' ) );
$bot = ViewService::record( $public_post, 0, array( 'REMOTE_ADDR' => '203.0.113.12', 'HTTP_USER_AGENT' => 'ExampleCrawlerBot/1.0' ) );
sabri_phase3g_assert( 'view_ignored' === $dnt['code'] && 'view_ignored' === $bot['code'] && 3 === count( $sabri_test_rows[ $view_table ] ), 'DNT and obvious automated requests must not be counted.' );
$hidden_view = ViewService::record( $pending_post, 0, $browser );
sabri_phase3g_assert( empty( $hidden_view['ok'] ) && 'post_unavailable' === $hidden_view['code'], 'Pending or hidden posts must not accept view rows.' );
sabri_phase3g_assert( 3 === ViewService::count( $public_post ), 'Public view count must be aggregate-only.' );

$summary = EngagementService::summary( $public_post );
sabri_phase3g_assert( 3 === (int) $summary['view_count'], 'Engagement summary must include aggregate view_count.' );
$action_bar = SocialRuntime::render_action_bar( $public_post );
sabri_phase3g_assert( false !== strpos( $action_bar, 'Views' ) && false !== strpos( $action_bar, 'data-count="views"' ), 'Visible social surface must render an accessible aggregate Views label.' );

// Two-day window suppresses next-day repeats; the third day creates a fresh row.
sabri_phase3g_settings( 'sabri_phase3g_notification_callback', 2 );
$sabri_test_current_user_id = 7;
$sabri_test_filter_overrides['sabri_feed_view_now'] = strtotime( '2026-07-20 12:00:00 UTC' );
$next_day = ViewService::record( $public_post, 7, $browser );
sabri_phase3g_assert( 'view_already_counted' === $next_day['code'], 'Configured two-day window must suppress the next-day repeat.' );
$sabri_test_filter_overrides['sabri_feed_view_now'] = strtotime( '2026-07-21 12:00:00 UTC' );
$third_day = ViewService::record( $public_post, 7, $browser );
sabri_phase3g_assert( ! empty( $third_day['data']['counted'] ) && 4 === ViewService::count( $public_post ), 'A view outside the deduplication window must create a fresh aggregate count.' );

// Concurrent unique insert recovery must retain one new row.
$sabri_test_current_user_id = 5;
$wpdb->race_view = true;
$before_race = count( $sabri_test_rows[ $view_table ] );
$race = ViewService::record( $public_post, 5, $browser );
sabri_phase3g_assert( 'view_already_counted' === $race['code'] && $before_race + 1 === count( $sabri_test_rows[ $view_table ] ), 'Concurrent view insert must recover through a safe identity re-read.' );

// Authenticated views are exportable only to their account owner and erasure removes the user identity.
$export = DataRetention::exporter( 'subscriber@example.com', 1 );
$export_json = wp_json_encode( $export );
sabri_phase3g_assert( false !== strpos( $export_json, 'Viewed post ID' ) && false !== strpos( $export_json, (string) $public_post ), 'Privacy export must include the requesting user’s view history.' );
sabri_phase3g_assert( false === strpos( $export_json, 'anonymous_hash' ) && false === strpos( $export_json, '203.0.113.10' ), 'View export must not expose anonymous hashes or request identifiers.' );
DataRetention::eraser( 'subscriber@example.com', 1 );
$erased_rows = array_values( array_filter( $sabri_test_rows[ $view_table ], static function ( $row ) { return isset( $row['anonymous_hash'] ) && 0 === (int) $row['user_id'] && '' !== $row['anonymous_hash']; } ) );
$erased_hashes = array_column( $erased_rows, 'anonymous_hash' );
sabri_phase3g_assert( count( $erased_hashes ) === count( array_unique( $erased_hashes ) ), 'Erased authenticated view rows must receive collision-resistant per-row hashes.' );

// Direct single-post runtime records server-side without a public write endpoint.
$sabri_test_filter_overrides['sabri_feed_view_now'] = strtotime( '2026-07-22 12:00:00 UTC' );
$sabri_test_current_user_id = 6;
$sabri_test_is_singular = true;
$sabri_test_singular_post_type = 'post';
$sabri_test_current_post_id = $public_post;
$_SERVER['REMOTE_ADDR'] = '203.0.113.20';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Runtime Browser';
unset( $_SERVER['HTTP_DNT'] );
$before_runtime = count( $sabri_test_rows[ $view_table ] );
ViewRuntime::record_single_post_view();
sabri_phase3g_assert( $before_runtime + 1 === count( $sabri_test_rows[ $view_table ] ), 'Direct visible single-post runtime must record one server-side view.' );

sabri_phase3g_assert( '1.0.3' === SABRI_HNF_VERSION && '1.0.0' === SABRI_HNF_SCHEMA_VERSION, 'Checkpoint 3G must not change the accepted plugin or schema version.' );

if ( ! empty( $phase3g_failures ) ) {
	fwrite( STDERR, "Phase 3G Notification Bridge and Views failures:\n- " . implode( "\n- ", $phase3g_failures ) . "\n" );
	exit( 1 );
}

echo "Phase 3G Notification Bridge and Views tests passed.\n";
