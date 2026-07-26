<?php
/**
 * Phase 3D Follow and Following tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\Database;
use Sabri\HomeNewsFeed\FollowService;
use Sabri\HomeNewsFeed\FollowingRuntime;
use Sabri\HomeNewsFeed\InteractionQueryRepository;
use Sabri\HomeNewsFeed\Phase3FeatureSettings;
use Sabri\HomeNewsFeed\Plugin;
use Sabri\HomeNewsFeed\PostMetadata;
use Sabri\HomeNewsFeed\ProfileLinkResolver;
use Sabri\HomeNewsFeed\RestFollows;
use Sabri\HomeNewsFeed\RestFoundation;
use Sabri\HomeNewsFeed\SocialRuntime;

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) { unset( $action ); return 'rest-nonce'; }
}
if ( ! function_exists( 'wp_login_url' ) ) {
	function wp_login_url( $redirect = '' ) { return 'http://example.test/login?redirect=' . rawurlencode( (string) $redirect ); }
}
if ( ! function_exists( 'get_author_posts_url' ) ) {
	function get_author_posts_url( $user_id ) { return 'http://example.test/author/' . (int) $user_id . '/'; }
}

final class Sabri_Phase3D_Request {
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

class Sabri_Phase3D_WPDB extends Sabri_Test_WPDB {
	public $insert_id = 0;
	public $race_follow = false;

	public function insert( $table, $data, $formats = null ) {
		global $sabri_test_rows;
		unset( $formats );
		$rows = isset( $sabri_test_rows[ $table ] ) ? $sabri_test_rows[ $table ] : array();
		$this->insert_id = count( $rows ) + 1;
		$data['id'] = $this->insert_id;
		$rows[] = $data;
		$sabri_test_rows[ $table ] = $rows;
		if ( $this->race_follow && false !== strpos( $table, 'follows' ) ) {
			$this->race_follow = false;
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
		if ( ! preg_match( '/FROM `([^`]+)` WHERE (.+) ORDER BY id DESC LIMIT 1/', $query, $matches ) ) {
			return null;
		}
		$where = $this->parse_where( $matches[2] );
		$rows = array_reverse( isset( $sabri_test_rows[ $matches[1] ] ) ? $sabri_test_rows[ $matches[1] ] : array() );
		foreach ( $rows as $row ) {
			if ( $this->matches( $row, $where ) ) { return $row; }
		}
		return null;
	}

	public function get_col( $query ) {
		global $sabri_test_rows;
		if ( ! preg_match( '/SELECT target_user_id FROM `([^`]+)` WHERE follower_user_id = ([0-9]+) AND target_type = \'([^\']+)\' AND status = \'([^\']+)\' ORDER BY updated_at DESC, id DESC LIMIT ([0-9]+)/', $query, $matches ) ) {
			return array();
		}
		$out = array();
		foreach ( array_reverse( isset( $sabri_test_rows[ $matches[1] ] ) ? $sabri_test_rows[ $matches[1] ] : array() ) as $row ) {
			if ( (int) $row['follower_user_id'] === (int) $matches[2] && $row['target_type'] === $matches[3] && $row['status'] === $matches[4] ) {
				$out[] = (int) $row['target_user_id'];
			}
			if ( count( $out ) >= (int) $matches[5] ) { break; }
		}
		return $out;
	}

	public function get_var( $query ) {
		global $sabri_test_rows;
		if ( preg_match( '/SELECT COUNT\(\*\) FROM `([^`]+)` WHERE target_user_id = ([0-9]+) AND target_type = \'([^\']+)\' AND status = \'([^\']+)\'/', $query, $matches ) ) {
			$count = 0;
			foreach ( isset( $sabri_test_rows[ $matches[1] ] ) ? $sabri_test_rows[ $matches[1] ] : array() as $row ) {
				if ( (int) $row['target_user_id'] === (int) $matches[2] && $row['target_type'] === $matches[3] && $row['status'] === $matches[4] ) { $count++; }
			}
			return $count;
		}
		return parent::get_var( $query );
	}

	private function parse_where( $sql ) {
		$where = array();
		foreach ( preg_split( '/\s+AND\s+/i', $sql ) as $clause ) {
			if ( preg_match( '/([a-z_]+) = ([0-9]+)/i', $clause, $part ) ) { $where[ $part[1] ] = (int) $part[2]; }
			elseif ( preg_match( "/([a-z_]+) = '([^']*)'/i", $clause, $part ) ) { $where[ $part[1] ] = stripslashes( $part[2] ); }
		}
		return $where;
	}

	private function matches( array $row, array $where ) {
		foreach ( $where as $key => $value ) {
			if ( ! array_key_exists( $key, $row ) || (string) $row[ $key ] !== (string) $value ) { return false; }
		}
		return true;
	}
}

$phase3d_failures = array();
function sabri_phase3d_assert( $condition, $message ) { global $phase3d_failures; if ( ! $condition ) { $phase3d_failures[] = $message; } }
function sabri_phase3d_enable_follows( $show_counts = true ) {
	$features = Phase3FeatureSettings::defaults();
	$features['follows_enabled'] = 1;
	$features['show_public_follower_counts'] = $show_counts ? 1 : 0;
	update_option( Phase3FeatureSettings::OPTION_NAME, $features, false );
}

sabri_test_reset_state( true );
global $wpdb, $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_rows, $sabri_test_transients, $sabri_test_rest_routes, $sabri_test_shortcodes, $sabri_test_filter_overrides;
$wpdb = new Sabri_Phase3D_WPDB();
Database::install();

sabri_phase3d_assert( 0 === Phase3FeatureSettings::defaults()['follows_enabled'], 'Follow runtime must remain gated by default until staging acceptance.' );
sabri_phase3d_assert( 0 === Phase3FeatureSettings::defaults()['show_public_follower_counts'], 'Public follower counts must remain private-by-default.' );
sabri_phase3d_enable_follows( true );

$target_post = sabri_test_add_post(
	array( 'post_author' => 3, 'post_status' => 'publish', 'post_title' => 'Verified doctor profile post' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);
$self_post = sabri_test_add_post(
	array( 'post_author' => 7, 'post_status' => 'publish', 'post_title' => 'Own post' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);

// Follow creation, idempotency, aggregate count, and current-user isolation.
$sabri_test_current_user_id = 7;
$sabri_test_current_caps = array();
$follow = FollowService::follow( 3, 'rest-nonce', 7 );
sabri_phase3d_assert( ! empty( $follow['ok'] ) && true === $follow['data']['following'] && 1 === $follow['data']['follower_count'], 'First Follow must create one active user relationship.' );
$follow_again = FollowService::follow( 3, 'rest-nonce', 7 );
sabri_phase3d_assert( ! empty( $follow_again['ok'] ) && true === $follow_again['data']['following'], 'Repeated Follow must be idempotent.' );

$follow_table = $wpdb->prefix . 'sabri_feed_follows';
$active_user7 = array_filter( $sabri_test_rows[ $follow_table ], static function ( $row ) { return 7 === (int) $row['follower_user_id'] && 3 === (int) $row['target_user_id'] && 'active' === $row['status']; } );
sabri_phase3d_assert( 1 === count( $active_user7 ), 'Sequential duplicate Follow requests must retain one natural-key row.' );

$sabri_test_current_user_id = 6;
$other_follow = FollowService::follow( 3, 'rest-nonce', 6 );
sabri_phase3d_assert( ! empty( $other_follow['ok'] ) && 2 === $other_follow['data']['follower_count'], 'Two users must produce an aggregate follower count without sharing private state.' );
sabri_phase3d_assert( true === FollowService::summary( 3, 6 )['following'], 'Current-user follow state must belong only to the requesting session.' );

$sabri_test_current_user_id = 7;
sabri_phase3d_assert( true === FollowService::summary( 3, 7 )['following'], 'First user must retain private follow state.' );
$self_follow = FollowService::follow( 7, 'rest-nonce', 7 );
sabri_phase3d_assert( empty( $self_follow['ok'] ) && 'self_follow_forbidden' === $self_follow['code'], 'Self-follow must fail closed.' );
$missing_user = FollowService::follow( 999, 'rest-nonce', 7 );
sabri_phase3d_assert( empty( $missing_user['ok'] ) && 'user_unavailable' === $missing_user['code'], 'Unknown users must not create relationship rows.' );
$forged_user = FollowService::follow( 4, 'rest-nonce', 6 );
sabri_phase3d_assert( empty( $forged_user['ok'] ) && 'authentication_required' === $forged_user['code'], 'Request data must not select another existing follower identity.' );

// Filter policy, blocked state, and concurrent unique-insert recovery.
$sabri_test_filter_overrides['sabri_feed_user_followable'] = false;
$not_followable = FollowService::follow( 4, 'rest-nonce', 7 );
sabri_phase3d_assert( empty( $not_followable['ok'] ) && 'user_not_followable' === $not_followable['code'], 'The followable-account policy filter must fail closed.' );
unset( $sabri_test_filter_overrides['sabri_feed_user_followable'] );

$wpdb->insert( $follow_table, array( 'follower_user_id' => 7, 'target_user_id' => 4, 'target_type' => 'user', 'status' => 'blocked', 'created_at' => gmdate( 'Y-m-d H:i:s' ), 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ) );
$blocked = FollowService::follow( 4, 'rest-nonce', 7 );
sabri_phase3d_assert( empty( $blocked['ok'] ) && 'relationship_blocked' === $blocked['code'], 'A blocked relationship must not be silently reactivated.' );

$wpdb->race_follow = true;
$race = FollowService::follow( 2, 'rest-nonce', 7 );
sabri_phase3d_assert( ! empty( $race['ok'] ) && true === $race['data']['following'], 'Concurrent unique insert must recover through a safe relationship re-read.' );
$race_rows = array_filter( $sabri_test_rows[ $follow_table ], static function ( $row ) { return 7 === (int) $row['follower_user_id'] && 2 === (int) $row['target_user_id']; } );
sabri_phase3d_assert( 1 === count( $race_rows ), 'Race recovery must leave one follow row.' );

// Private Following list, profile bridge, no private account fields, and Unfollow.
$sabri_test_filter_overrides['sabri_feed_profile_url'] = 'http://example.test/profiles/member/';
$list = FollowService::following( 'rest-nonce', 7, 100 );
sabri_phase3d_assert( ! empty( $list['ok'] ) && 2 === $list['data']['count'], 'Private Following list must include only active, available targets.' );
$profile_urls = array_column( $list['data']['items'], 'profile_url' );
$list_json = wp_json_encode( $list['data'] );
sabri_phase3d_assert( in_array( 'http://example.test/profiles/member/', $profile_urls, true ), 'Profile URL filter must integrate without modifying a Profiles repository.' );
sabri_phase3d_assert( false === strpos( $list_json, 'user_email' ) && false === strpos( $list_json, '@example.com' ) && false === strpos( $list_json, 'roles' ), 'Following serialization must not expose email addresses or role data.' );
unset( $sabri_test_filter_overrides['sabri_feed_profile_url'] );

$sabri_test_current_user_id = 6;
$other_list = FollowService::following( 'rest-nonce', 6, 100 );
sabri_phase3d_assert( 1 === $other_list['data']['count'] && 3 === $other_list['data']['items'][0]['id'], 'Following lists must remain isolated by current user.' );

$sabri_test_current_user_id = 7;
$unfollow = FollowService::unfollow( 3, 'rest-nonce', 7 );
sabri_phase3d_assert( ! empty( $unfollow['ok'] ) && false === $unfollow['data']['following'] && 1 === $unfollow['data']['follower_count'], 'Unfollow must mark only the selected relationship removed.' );
$removed_record = InteractionQueryRepository::follow_record( 7, 3 );
sabri_phase3d_assert( is_array( $removed_record ) && 'removed' === $removed_record['status'], 'Unfollow must retain the natural-key row in removed state.' );

// Public counts are policy-bounded.
sabri_phase3d_enable_follows( false );
$hidden_count = FollowService::summary( 3, 7 );
sabri_phase3d_assert( false === $hidden_count['count_visible'] && 0 === $hidden_count['follower_count'], 'Disabled public count policy must not leak the real follower count.' );
sabri_phase3d_enable_follows( true );

// Rate limiting is isolated by current user and target.
$sabri_test_transients = array();
$sabri_test_current_user_id = 5;
$last = array();
for ( $i = 0; $i < 31; $i++ ) {
	$last = FollowService::follow( 2, 'rest-nonce', 5 );
}
sabri_phase3d_assert( empty( $last['ok'] ) && 429 === $last['status'], 'The thirty-first Follow attempt in ten minutes must be rate-limited.' );

// REST routes and strict current-session nonce permission.
$sabri_test_rest_routes = array();
RestFollows::register_routes();
$follow_route = RestFoundation::NAMESPACE . '/users/(?P<id>\d+)/follow';
$following_route = RestFoundation::NAMESPACE . '/me/following';
sabri_phase3d_assert( isset( $sabri_test_rest_routes[ $follow_route ], $sabri_test_rest_routes[ $following_route ] ), 'Phase 3D REST routes must register under the frozen namespace.' );
sabri_phase3d_assert( RestFollows::validate_id( 3 ) && ! RestFollows::validate_id( '-1' ) && RestFollows::validate_limit( 200 ) && ! RestFollows::validate_limit( 201 ), 'REST IDs and private list limits must remain strictly bounded.' );
$sabri_test_current_user_id = 7;
$valid_request = new Sabri_Phase3D_Request( array( 'id' => 3 ), array( 'X-WP-Nonce' => 'rest-nonce' ) );
$invalid_request = new Sabri_Phase3D_Request( array( 'id' => 3 ), array( 'X-WP-Nonce' => 'invalid' ) );
sabri_phase3d_assert( RestFollows::private_permission( $valid_request ) && ! RestFollows::private_permission( $invalid_request ), 'Follow REST requests must require the current session and a valid nonce.' );

// Action bar and private shortcode output.
FollowService::follow( 3, 'rest-nonce', 7 );
$bar = SocialRuntime::render_action_bar( $target_post );
sabri_phase3d_assert( false !== strpos( $bar, 'data-sabri-action="follow"' ) && false !== strpos( $bar, '>Following<' ) && false !== strpos( $bar, '>View Profile<' ), 'Post action bar must expose accessible Follow state and the profile bridge.' );
$self_bar = SocialRuntime::render_action_bar( $self_post );
sabri_phase3d_assert( false === strpos( $self_bar, 'data-sabri-action="follow"' ), 'A user’s own post must not show a self-follow control.' );

$sabri_test_shortcodes = array();
FollowingRuntime::register();
sabri_phase3d_assert( isset( $sabri_test_shortcodes[ FollowingRuntime::SHORTCODE ] ), 'Private Following shortcode must register.' );
$following_html = FollowingRuntime::render( array( 'limit' => 20 ) );
sabri_phase3d_assert( false !== strpos( $following_html, 'Verified Doctor' ) && false === strpos( $following_html, 'patient@example.com' ), 'Server-rendered Following list must show public identity without private email.' );

$sabri_test_current_user_id = 0;
$visitor_bar = SocialRuntime::render_action_bar( $target_post );
sabri_phase3d_assert( false !== strpos( $visitor_bar, 'data-logged-in="0"' ) && false !== strpos( $visitor_bar, 'data-sabri-action="follow"' ), 'Visitors must see a login-gated Follow control.' );
sabri_phase3d_assert( false === strpos( $visitor_bar, 'data-nonce="rest-nonce"' ), 'Visitor markup must not expose an authenticated nonce.' );
$visitor_list = FollowingRuntime::render();
sabri_phase3d_assert( false !== strpos( $visitor_list, 'Sign in to view the people you follow' ), 'Visitor Following shortcode must show a login state without private relationships.' );

// Feature gate closes UI and REST permissions until staging acceptance.
$features = Phase3FeatureSettings::defaults();
update_option( Phase3FeatureSettings::OPTION_NAME, $features, false );
$sabri_test_current_user_id = 7;
sabri_phase3d_assert( ! RestFollows::private_permission( $valid_request ), 'Follow REST permissions must fail closed while the feature is disabled.' );
sabri_phase3d_assert( false === strpos( SocialRuntime::render_action_bar( $target_post ), 'data-sabri-action="follow"' ), 'Disabled Follow runtime must not render a Follow control.' );
sabri_phase3d_assert( false !== strpos( FollowingRuntime::render(), 'Following is currently unavailable' ), 'Disabled private Following surface must fail closed.' );

$identity = Plugin::identity();
sabri_phase3d_assert( '1.0.1' === $identity['version'] && '1.0.0' === $identity['schema_version'], 'Checkpoint 3D must preserve accepted plugin and schema version 1.0.0.' );

if ( $phase3d_failures ) {
	echo "FAILED\n";
	foreach ( $phase3d_failures as $failure ) { echo '- ' . $failure . "\n"; }
	exit( 1 );
}

echo "OK - Phase 3D Follow and Following tests passed.\n";
