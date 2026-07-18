<?php
/**
 * Phase 3B reactions and saves tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\Database;
use Sabri\HomeNewsFeed\EngagementService;
use Sabri\HomeNewsFeed\InteractionQueryRepository;
use Sabri\HomeNewsFeed\Phase3FeatureSettings;
use Sabri\HomeNewsFeed\PostMetadata;
use Sabri\HomeNewsFeed\ReactionService;
use Sabri\HomeNewsFeed\RestFoundation;
use Sabri\HomeNewsFeed\RestInteractions;
use Sabri\HomeNewsFeed\SaveService;
use Sabri\HomeNewsFeed\SocialRuntime;

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) { unset( $action ); return 'rest-nonce'; }
}
if ( ! function_exists( 'wp_login_url' ) ) {
	function wp_login_url( $redirect = '' ) { return 'http://example.test/login?redirect=' . rawurlencode( (string) $redirect ); }
}

final class Sabri_Phase3B_Request {
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

class Sabri_Phase3B_WPDB extends Sabri_Test_WPDB {
	public $insert_id = 0;

	public function insert( $table, $data, $formats = null ) {
		global $sabri_test_rows;
		unset( $formats );
		$rows = isset( $sabri_test_rows[ $table ] ) ? $sabri_test_rows[ $table ] : array();
		$this->insert_id = count( $rows ) + 1;
		$data['id'] = $this->insert_id;
		$rows[] = $data;
		$sabri_test_rows[ $table ] = $rows;
		return 1;
	}

	public function update( $table, $data, $where, $formats = null, $where_formats = null ) {
		global $sabri_test_rows;
		unset( $formats, $where_formats );
		$affected = 0;
		$rows = isset( $sabri_test_rows[ $table ] ) ? $sabri_test_rows[ $table ] : array();
		foreach ( $rows as $index => $row ) {
			if ( $this->matches_where( $row, $where ) ) {
				$rows[ $index ] = array_merge( $row, $data );
				$affected++;
			}
		}
		$sabri_test_rows[ $table ] = $rows;
		return $affected;
	}

	public function delete( $table, $where, $formats = null ) {
		global $sabri_test_rows;
		unset( $formats );
		$affected = 0;
		$out = array();
		foreach ( isset( $sabri_test_rows[ $table ] ) ? $sabri_test_rows[ $table ] : array() as $row ) {
			if ( $this->matches_where( $row, $where ) ) { $affected++; continue; }
			$out[] = $row;
		}
		$sabri_test_rows[ $table ] = $out;
		return $affected;
	}

	public function get_row( $query, $output = null ) {
		global $sabri_test_rows;
		unset( $output );
		if ( ! preg_match( '/FROM `([^`]+)` WHERE (.+) ORDER BY id DESC LIMIT 1/', $query, $matches ) ) { return null; }
		$rows = array_reverse( isset( $sabri_test_rows[ $matches[1] ] ) ? $sabri_test_rows[ $matches[1] ] : array() );
		$where = $this->parse_where( $matches[2] );
		foreach ( $rows as $row ) { if ( $this->matches_where( $row, $where ) ) { return $row; } }
		return null;
	}

	public function get_results( $query, $output = null ) {
		global $sabri_test_rows;
		unset( $output );
		if ( preg_match( '/SELECT reaction_type, COUNT\(\*\) AS total FROM `([^`]+)` WHERE post_id = ([0-9]+) AND status = \'([^\']+)\' GROUP BY reaction_type/', $query, $matches ) ) {
			$counts = array();
			foreach ( isset( $sabri_test_rows[ $matches[1] ] ) ? $sabri_test_rows[ $matches[1] ] : array() as $row ) {
				if ( (int) $row['post_id'] === (int) $matches[2] && $row['status'] === $matches[3] ) {
					$type = $row['reaction_type'];
					$counts[ $type ] = isset( $counts[ $type ] ) ? $counts[ $type ] + 1 : 1;
				}
			}
			$out = array();
			foreach ( $counts as $type => $total ) { $out[] = array( 'reaction_type' => $type, 'total' => $total ); }
			return $out;
		}
		return parent::get_results( $query, ARRAY_A );
	}

	public function get_col( $query ) {
		global $sabri_test_rows;
		if ( ! preg_match( '/SELECT post_id FROM `([^`]+)` WHERE user_id = ([0-9]+) AND status = \'([^\']+)\' ORDER BY updated_at DESC, id DESC LIMIT ([0-9]+)/', $query, $matches ) ) { return array(); }
		$rows = array_reverse( isset( $sabri_test_rows[ $matches[1] ] ) ? $sabri_test_rows[ $matches[1] ] : array() );
		$out = array();
		foreach ( $rows as $row ) {
			if ( (int) $row['user_id'] === (int) $matches[2] && $row['status'] === $matches[3] ) { $out[] = (int) $row['post_id']; }
			if ( count( $out ) >= (int) $matches[4] ) { break; }
		}
		return $out;
	}

	private function parse_where( $sql ) {
		$where = array();
		foreach ( preg_split( '/\s+AND\s+/i', $sql ) as $clause ) {
			if ( preg_match( '/([a-z_]+) = ([0-9]+)/i', $clause, $matches ) ) { $where[ $matches[1] ] = (int) $matches[2]; }
			elseif ( preg_match( "/([a-z_]+) = '([^']*)'/i", $clause, $matches ) ) { $where[ $matches[1] ] = stripslashes( $matches[2] ); }
		}
		return $where;
	}

	private function matches_where( array $row, array $where ) {
		foreach ( $where as $key => $value ) {
			if ( ! array_key_exists( $key, $row ) || (string) $row[ $key ] !== (string) $value ) { return false; }
		}
		return true;
	}
}

$failures = array();
function sabri_phase3b_assert( $condition, $message ) { global $failures; if ( ! $condition ) { $failures[] = $message; } }

sabri_test_reset_state( true );
global $wpdb, $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_rows, $sabri_test_rest_routes;
$wpdb = new Sabri_Phase3B_WPDB();
Database::install();

$public_post = sabri_test_add_post(
	array( 'post_author' => 2, 'post_status' => 'publish', 'post_title' => 'Public approved post' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);
$second_post = sabri_test_add_post(
	array( 'post_author' => 3, 'post_status' => 'publish', 'post_title' => 'Second approved post' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);
$pending_post = sabri_test_add_post(
	array( 'post_author' => 4, 'post_status' => 'publish', 'post_title' => 'Pending post' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'pending', PostMetadata::META_TYPE => 'standard-post' )
);

sabri_phase3b_assert( Phase3FeatureSettings::enabled( 'reactions_enabled' ), 'Implemented reactions must be enabled by Phase 3B defaults.' );
sabri_phase3b_assert( Phase3FeatureSettings::enabled( 'saves_enabled' ), 'Implemented saves must be enabled by Phase 3B defaults.' );
sabri_phase3b_assert( ! Phase3FeatureSettings::enabled( 'comments_enabled' ), 'Unimplemented comments must remain disabled.' );

$sabri_test_current_user_id = 7;
$sabri_test_current_caps = array();
$like = ReactionService::set( $public_post, 'like', 'rest-nonce', 7 );
sabri_phase3b_assert( ! empty( $like['ok'] ) && 'like' === $like['data']['current_reaction'] && 1 === $like['data']['like_count'], 'First Like must create one active reaction.' );
$toggle = ReactionService::set( $public_post, 'like', 'rest-nonce', 7 );
sabri_phase3b_assert( ! empty( $toggle['ok'] ) && '' === $toggle['data']['current_reaction'] && 0 === $toggle['data']['like_count'], 'Selecting the active reaction must remove it.' );
ReactionService::set( $public_post, 'like', 'rest-nonce', 7 );
$switch = ReactionService::set( $public_post, 'dislike', 'rest-nonce', 7 );
sabri_phase3b_assert( ! empty( $switch['ok'] ) && 'dislike' === $switch['data']['current_reaction'] && 0 === $switch['data']['like_count'] && 1 === $switch['data']['dislike_count'], 'Like to Dislike switching must update one active row.' );

$sabri_test_current_user_id = 6;
$other_like = ReactionService::set( $public_post, 'like', 'rest-nonce', 6 );
sabri_phase3b_assert( ! empty( $other_like['ok'] ) && 1 === $other_like['data']['like_count'] && 1 === $other_like['data']['dislike_count'], 'Two users must have isolated reactions with aggregate counts.' );
sabri_phase3b_assert( 'like' === EngagementService::summary( $public_post, 6 )['current_reaction'], 'Current reaction must remain private to the requesting user.' );

$invalid_reaction = ReactionService::set( $public_post, 'angry', 'rest-nonce', 6 );
sabri_phase3b_assert( empty( $invalid_reaction['ok'] ) && 'invalid_reaction' === $invalid_reaction['code'], 'Unknown reaction types must fail closed.' );
$pending_reaction = ReactionService::set( $pending_post, 'like', 'rest-nonce', 6 );
sabri_phase3b_assert( empty( $pending_reaction['ok'] ) && 'post_unavailable' === $pending_reaction['code'], 'Pending posts must reject reactions.' );
$forged_reaction = ReactionService::set( $public_post, 'like', 'rest-nonce', 7 );
sabri_phase3b_assert( empty( $forged_reaction['ok'] ) && 'authentication_required' === $forged_reaction['code'], 'Reaction identity must match the current session.' );

$sabri_test_current_user_id = 7;
$saved = SaveService::save( $public_post, 'rest-nonce', 7 );
sabri_phase3b_assert( ! empty( $saved['ok'] ) && true === $saved['data']['saved'], 'Save must create private active state.' );
$saved_again = SaveService::save( $public_post, 'rest-nonce', 7 );
sabri_phase3b_assert( ! empty( $saved_again['ok'] ) && true === $saved_again['data']['saved'], 'Repeated Save must be idempotent.' );
SaveService::save( $second_post, 'rest-nonce', 7 );
$list = SaveService::saved_posts( 'rest-nonce', 7, 100 );
sabri_phase3b_assert( ! empty( $list['ok'] ) && 2 === $list['data']['count'], 'Saved Posts list must return the current user’s visible items.' );
$unsaved = SaveService::unsave( $public_post, 'rest-nonce', 7 );
sabri_phase3b_assert( ! empty( $unsaved['ok'] ) && false === $unsaved['data']['saved'], 'Unsave must clear private state.' );
$list_after = SaveService::saved_posts( 'rest-nonce', 7, 100 );
sabri_phase3b_assert( 1 === $list_after['data']['count'] && $second_post === $list_after['data']['items'][0]['id'], 'Unsave must remove only the selected post.' );

$sabri_test_current_user_id = 6;
$other_list = SaveService::saved_posts( 'rest-nonce', 6, 100 );
sabri_phase3b_assert( 0 === $other_list['data']['count'], 'Saved Posts must not leak between users.' );
$pending_save = SaveService::save( $pending_post, 'rest-nonce', 6 );
sabri_phase3b_assert( empty( $pending_save['ok'] ) && 'post_unavailable' === $pending_save['code'], 'Pending posts must reject saves.' );

$sabri_test_current_user_id = 7;
$bar = SocialRuntime::render_action_bar( $second_post );
sabri_phase3b_assert( false !== strpos( $bar, 'data-sabri-interactions' ) && false !== strpos( $bar, 'data-nonce="rest-nonce"' ), 'Logged-in action bar must contain bounded URLs and REST nonce.' );
sabri_phase3b_assert( false !== strpos( $bar, 'aria-pressed="true"' ) && false !== strpos( $bar, '>Saved<' ), 'Action bar must render current save state accessibly.' );

$sabri_test_current_user_id = 0;
$visitor_bar = SocialRuntime::render_action_bar( $public_post );
sabri_phase3b_assert( false !== strpos( $visitor_bar, 'data-logged-in="0"' ) && false !== strpos( $visitor_bar, 'data-login-url=' ), 'Visitor actions must direct to login.' );
sabri_phase3b_assert( false === strpos( $visitor_bar, 'data-nonce="rest-nonce"' ), 'Visitor markup must not expose an authenticated nonce.' );

$sabri_test_rest_routes = array();
RestInteractions::register_routes();
sabri_phase3b_assert( isset( $sabri_test_rest_routes[ RestFoundation::NAMESPACE . '/posts/(?P<id>\d+)/engagement' ] ), 'Engagement route must register.' );
sabri_phase3b_assert( isset( $sabri_test_rest_routes[ RestFoundation::NAMESPACE . '/posts/(?P<id>\d+)/reaction' ] ), 'Reaction route must register.' );
sabri_phase3b_assert( isset( $sabri_test_rest_routes[ RestFoundation::NAMESPACE . '/posts/(?P<id>\d+)/save' ] ), 'Save route must register.' );
sabri_phase3b_assert( isset( $sabri_test_rest_routes[ RestFoundation::NAMESPACE . '/me/saves' ] ), 'Private Saved Posts route must register.' );

$sabri_test_current_user_id = 7;
$request = new Sabri_Phase3B_Request( array( 'id' => $public_post, 'reaction_type' => 'like' ), array( 'X-WP-Nonce' => 'rest-nonce' ) );
sabri_phase3b_assert( RestInteractions::private_permission( $request ), 'Private REST route must require login and nonce.' );
$bad_request = new Sabri_Phase3B_Request( array( 'id' => $public_post ), array( 'X-WP-Nonce' => 'invalid' ) );
sabri_phase3b_assert( ! RestInteractions::private_permission( $bad_request ), 'Invalid REST nonce must fail permission.' );

$reaction_table = $wpdb->prefix . 'sabri_feed_reactions';
$active_user6 = array_filter(
	isset( $sabri_test_rows[ $reaction_table ] ) ? $sabri_test_rows[ $reaction_table ] : array(),
	static function ( $row ) use ( $public_post ) { return (int) $row['user_id'] === 6 && (int) $row['post_id'] === $public_post && 'active' === $row['status']; }
);
sabri_phase3b_assert( 1 === count( $active_user6 ), 'Sequential duplicate requests must not create duplicate active reaction rows.' );

if ( $failures ) {
	echo "FAILED\n";
	foreach ( $failures as $failure ) { echo '- ' . $failure . "\n"; }
	exit( 1 );
}

echo "OK - Phase 3B reactions and saves tests passed.\n";
