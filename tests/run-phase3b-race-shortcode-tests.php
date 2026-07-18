<?php
/**
 * Phase 3B race recovery and Saved Posts shortcode tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\Database;
use Sabri\HomeNewsFeed\PostMetadata;
use Sabri\HomeNewsFeed\ReactionService;
use Sabri\HomeNewsFeed\SaveService;
use Sabri\HomeNewsFeed\SavedPostsRuntime;

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) { unset( $action ); return 'rest-nonce'; }
}
if ( ! function_exists( 'wp_login_url' ) ) {
	function wp_login_url( $redirect = '' ) { return 'http://example.test/login?redirect=' . rawurlencode( (string) $redirect ); }
}

class Sabri_Phase3B_Race_WPDB extends Sabri_Test_WPDB {
	public $race_reaction = false;
	public $race_save = false;

	public function insert( $table, $data, $formats = null ) {
		global $sabri_test_rows;
		unset( $formats );
		$rows = isset( $sabri_test_rows[ $table ] ) ? $sabri_test_rows[ $table ] : array();
		$data['id'] = count( $rows ) + 1;
		$rows[] = $data;
		$sabri_test_rows[ $table ] = $rows;
		if ( $this->race_reaction && false !== strpos( $table, 'reactions' ) ) { $this->race_reaction = false; return false; }
		if ( $this->race_save && false !== strpos( $table, 'saves' ) ) { $this->race_save = false; return false; }
		return 1;
	}

	public function update( $table, $data, $where, $formats = null, $where_formats = null ) {
		global $sabri_test_rows;
		unset( $formats, $where_formats );
		$affected = 0;
		foreach ( isset( $sabri_test_rows[ $table ] ) ? $sabri_test_rows[ $table ] : array() as $index => $row ) {
			if ( $this->matches( $row, $where ) ) { $sabri_test_rows[ $table ][ $index ] = array_merge( $row, $data ); $affected++; }
		}
		return $affected;
	}

	public function delete( $table, $where, $formats = null ) {
		global $sabri_test_rows;
		unset( $formats );
		$out = array();
		$affected = 0;
		foreach ( isset( $sabri_test_rows[ $table ] ) ? $sabri_test_rows[ $table ] : array() as $row ) {
			if ( $this->matches( $row, $where ) ) { $affected++; } else { $out[] = $row; }
		}
		$sabri_test_rows[ $table ] = $out;
		return $affected;
	}

	public function get_row( $query, $output = null ) {
		global $sabri_test_rows;
		unset( $output );
		if ( ! preg_match( '/FROM `([^`]+)` WHERE (.+) ORDER BY id DESC LIMIT 1/', $query, $matches ) ) { return null; }
		$where = array();
		foreach ( preg_split( '/\s+AND\s+/i', $matches[2] ) as $clause ) {
			if ( preg_match( '/([a-z_]+) = ([0-9]+)/i', $clause, $part ) ) { $where[ $part[1] ] = (int) $part[2]; }
			elseif ( preg_match( "/([a-z_]+) = '([^']*)'/i", $clause, $part ) ) { $where[ $part[1] ] = stripslashes( $part[2] ); }
		}
		$rows = array_reverse( isset( $sabri_test_rows[ $matches[1] ] ) ? $sabri_test_rows[ $matches[1] ] : array() );
		foreach ( $rows as $row ) { if ( $this->matches( $row, $where ) ) { return $row; } }
		return null;
	}

	public function get_results( $query, $output = null ) {
		global $sabri_test_rows;
		unset( $output );
		if ( preg_match( '/SELECT reaction_type, COUNT\(\*\) AS total FROM `([^`]+)` WHERE post_id = ([0-9]+) AND status = \'([^\']+)\' GROUP BY reaction_type/', $query, $matches ) ) {
			$counts = array();
			foreach ( isset( $sabri_test_rows[ $matches[1] ] ) ? $sabri_test_rows[ $matches[1] ] : array() as $row ) {
				if ( (int) $row['post_id'] === (int) $matches[2] && $row['status'] === $matches[3] ) { $counts[ $row['reaction_type'] ] = isset( $counts[ $row['reaction_type'] ] ) ? $counts[ $row['reaction_type'] ] + 1 : 1; }
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
		$out = array();
		foreach ( array_reverse( isset( $sabri_test_rows[ $matches[1] ] ) ? $sabri_test_rows[ $matches[1] ] : array() ) as $row ) {
			if ( (int) $row['user_id'] === (int) $matches[2] && $row['status'] === $matches[3] ) { $out[] = (int) $row['post_id']; }
		}
		return array_slice( $out, 0, (int) $matches[4] );
	}

	private function matches( array $row, array $where ) {
		foreach ( $where as $key => $value ) { if ( ! isset( $row[ $key ] ) || (string) $row[ $key ] !== (string) $value ) { return false; } }
		return true;
	}
}

$failures = array();
function sabri_phase3b_race_assert( $condition, $message ) { global $failures; if ( ! $condition ) { $failures[] = $message; } }

sabri_test_reset_state( true );
global $wpdb, $sabri_test_current_user_id, $sabri_test_rows, $sabri_test_shortcodes;
$wpdb = new Sabri_Phase3B_Race_WPDB();
Database::install();
$post_id = sabri_test_add_post(
	array( 'post_author' => 2, 'post_status' => 'publish', 'post_title' => 'Race-safe saved post' ),
	array( PostMetadata::META_VISIBILITY => 'public', PostMetadata::META_REVIEW_STATE => 'approved', PostMetadata::META_TYPE => 'standard-post' )
);

$sabri_test_current_user_id = 7;
$wpdb->race_reaction = true;
$reaction = ReactionService::set( $post_id, 'like', 'rest-nonce', 7 );
sabri_phase3b_race_assert( ! empty( $reaction['ok'] ) && 'like' === $reaction['data']['current_reaction'], 'Concurrent reaction insert must recover through the unique-row re-read.' );

$wpdb->race_save = true;
$save = SaveService::save( $post_id, 'rest-nonce', 7 );
sabri_phase3b_race_assert( ! empty( $save['ok'] ) && true === $save['data']['saved'], 'Concurrent save insert must recover through the unique-row re-read.' );

$sabri_test_shortcodes = array();
SavedPostsRuntime::register();
sabri_phase3b_race_assert( isset( $sabri_test_shortcodes[ SavedPostsRuntime::SHORTCODE ] ), 'Private Saved Posts shortcode must register.' );
$private_html = SavedPostsRuntime::render( array( 'limit' => 20 ) );
sabri_phase3b_race_assert( false !== strpos( $private_html, 'Race-safe saved post' ), 'Current user must see the saved post in server-rendered private list.' );

$sabri_test_current_user_id = 6;
$other_html = SavedPostsRuntime::render();
sabri_phase3b_race_assert( false === strpos( $other_html, 'Race-safe saved post' ) && false !== strpos( $other_html, 'not saved any visible posts' ), 'Another user must not see private saved items.' );

$sabri_test_current_user_id = 0;
$visitor_html = SavedPostsRuntime::render();
sabri_phase3b_race_assert( false !== strpos( $visitor_html, 'Sign in to view your private saved posts' ) && false === strpos( $visitor_html, 'Race-safe saved post' ), 'Visitor shortcode output must show login state without private content.' );

$reaction_table = $wpdb->prefix . 'sabri_feed_reactions';
$save_table = $wpdb->prefix . 'sabri_feed_saves';
sabri_phase3b_race_assert( 1 === count( isset( $sabri_test_rows[ $reaction_table ] ) ? $sabri_test_rows[ $reaction_table ] : array() ), 'Race recovery must leave one reaction row.' );
sabri_phase3b_race_assert( 1 === count( isset( $sabri_test_rows[ $save_table ] ) ? $sabri_test_rows[ $save_table ] : array() ), 'Race recovery must leave one save row.' );

if ( $failures ) {
	echo "FAILED\n";
	foreach ( $failures as $failure ) { echo '- ' . $failure . "\n"; }
	exit( 1 );
}

echo "OK - Phase 3B race recovery and Saved Posts shortcode tests passed.\n";
