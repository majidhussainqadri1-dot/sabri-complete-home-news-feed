<?php
/**
 * File 21 -> File 26 source keyset / shadow reindex regression.
 *
 * Runs the actual source connector method with a deterministic WPDB simulation.
 * Staging MySQL and installed File 26 acceptance remain independent gates.
 */
define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	public $code;
	public function __construct( $code, $message = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
class File21TestPhase4 { const POST_TYPE = 'sabri_news'; }
class_alias( File21TestPhase4::class, 'Sabri\HomeNewsFeed\Phase4Contracts' );

class File21TestMetadata {
	const META_LANGUAGE = '_sabri_feed_language';
	const META_REGION   = '_sabri_feed_region';
	public static function visibility( $id ) { return empty( $GLOBALS['private_post_ids'][ $id ] ) ? 'public' : 'private'; }
	public static function review_state_publicly_visible( $id ) { return empty( $GLOBALS['unapproved_post_ids'][ $id ] ); }
	public static function user_can_view( $id, $viewer ) { return empty( $GLOBALS['private_post_ids'][ $id ] ) && empty( $GLOBALS['unapproved_post_ids'][ $id ] ); }
	public static function feed_type( $id ) { return 'research'; }
}
class_alias( File21TestMetadata::class, 'Sabri\HomeNewsFeed\PostMetadata' );

class File21TestNewsProjector {
	public static function card( $post ) {
		return empty( $GLOBALS['unapproved_post_ids'][ $post->ID ] ) ? array(
			'headline' => $post->post_title, 'summary' => 'Verified public article',
			'canonical_url' => 'https://example.test/news/' . $post->ID,
			'public_label' => 'News',
		) : array();
	}
}
class_alias( File21TestNewsProjector::class, 'Sabri\HomeNewsFeed\NewsPublicProjector' );

class File21KeysetFakeDB {
	public $posts = 'wp_posts';
	public $last_error = '';
	public $records = array();
	public $queries = array();
	public $fail_next = false;
	public $force_unordered = false;

	public function prepare( $query, ...$args ) {
		$values = array_map( static function ( $arg ) {
			return is_int( $arg ) ? $arg : "'" . str_replace( "'", "''", (string) $arg ) . "'";
		}, $args );
		return vsprintf( $query, $values );
	}
	public function get_col( $sql ) {
		$this->queries[] = $sql;
		if ( $this->fail_next ) {
			$this->fail_next = false;
			$this->last_error = 'simulated_query_failure';
			return false;
		}
		if ( false !== stripos( $sql, ' OFFSET ' ) ||
			! preg_match( '/WHERE ID > ([0-9]+)/', $sql, $after ) ||
			! preg_match( '/LIMIT ([0-9]+)/', $sql, $max ) ||
			false === strpos( $sql, "post_status = 'publish'" ) ) {
			$this->last_error = 'unsafe_query_shape';
			return false;
		}
		$ids = array_keys( $this->records );
		sort( $ids, SORT_NUMERIC );
		$matched = array();
		foreach ( $ids as $id ) {
			$post = $this->records[ $id ];
			if ( $id > (int) $after[1] && 'publish' === $post->post_status &&
				in_array( $post->post_type, array( 'post', 'sabri_news' ), true ) ) {
				$matched[] = (string) $id;
			}
		}
		$matched = array_slice( $matched, 0, (int) $max[1] );
		if ( $this->force_unordered && count( $matched ) > 1 ) {
			$matched = array_reverse( $matched );
		}
		return $matched;
	}
}
function absint( $v ) { return abs( (int) $v ); }
function get_post( $id ) { global $wpdb; return $wpdb->records[ $id ] ?? null; }
function get_post_meta( $id, $key, $single = true ) { return ''; }
function get_the_title( $id ) { return get_post( $id )->post_title; }
function get_permalink( $id ) { return 'https://example.test/post/' . $id; }
function get_the_excerpt( $id ) { return 'Public summary of ' . $id; }
function get_the_terms( $id, $taxonomy ) { return array(); }
function get_bloginfo( $key ) { return 'en-US'; }

require_once dirname( __DIR__ ) . '/includes/class-search-provider-registry.php';

use Sabri\HomeNewsFeed\SearchProviderRegistry as Registry;

function check( $predicate, $message ) {
	if ( ! $predicate ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
	echo 'PASS: ' . $message . PHP_EOL;
}
function record( $id, $status = 'publish', $type = 'post' ) {
	return (object) array(
		'ID' => $id, 'post_status' => $status, 'post_type' => $type,
		'post_author' => 0, 'post_title' => 'Post ' . $id,
		'post_modified_gmt' => '2026-10-01 08:00:00',
		'post_date_gmt' => '2026-09-01 08:00:00',
	);
}
function keys( $batch ) { return array_map( static function ( $item ) { return (int) $item['object_id']; }, $batch['items'] ); }

$wpdb = new File21KeysetFakeDB();
foreach ( array( 10, 20, 30, 40 ) as $id ) { $wpdb->records[ $id ] = record( $id ); }
$first = Registry::file26_list_batch( '', 2, array( 'connector' => Registry::FILE26_CONNECTOR_SLUG ) );
check( is_array( $first ) && keys( $first ) === array( 10, 20 ) && 'v1:id:20' === $first['next_cursor'] && !$first['done'], 'First batch uses canonical keyset ID cursor.' );

// The old offset=2 query would now return [40], silently skipping ID=30.
$wpdb->records[10]->post_status = 'draft';
$second = Registry::file26_list_batch( $first['next_cursor'], 2 );
check( is_array( $second ) && keys( $second ) === array( 30, 40 ) && 'v1:id:40' === $second['next_cursor'], 'Withdrawal before cursor never skips a later published ID.' );

$end = Registry::file26_list_batch( $second['next_cursor'], 2 );
check( is_array( $end ) && !$end['items'] && $end['done'], 'Completed scan reports a safe empty terminal batch.' );
check( count( $wpdb->queries ) === 3 && false === stripos( implode( ' ', $wpdb->queries ), ' OFFSET ' ), 'Every batch uses ID > cursor instead of SQL OFFSET.' );

foreach ( array( '2', '0', '-1', 'id:0', 'id:020', 'id:20', 'v1:id:0', 'v1:id:020', 'v1:id:99999999999999999999999', 'v1:id:20junk', array( 1 ) ) as $old ) {
	$bad = Registry::file26_list_batch( $old, 2 );
	check( $bad instanceof WP_Error && 'file21_file26_legacy_cursor_rejected' === $bad->get_error_code(), 'Legacy/malformed/overflow cursor fails closed.' );
}
$partial = Registry::file26_list_batch( '', 2, array( 'connector' => Registry::FILE26_CONNECTOR_SLUG, 'object_id' => '20' ) );
check( $partial instanceof WP_Error && 'file21_file26_unsupported_scope' === $partial->get_error_code(), 'Partial-object scope never silently scans the whole source.' );
$foreign = Registry::file26_list_batch( '', 2, array( 'connector' => 'other-file' ) );
check( $foreign instanceof WP_Error && 'file21_file26_unsupported_scope' === $foreign->get_error_code(), 'Foreign connector scope denied.' );

$wpdb->records[10]->post_status = 'publish';
$GLOBALS['private_post_ids'][20] = true;
$visible = Registry::file26_list_batch( '', 3 );
check( is_array( $visible ) && keys( $visible ) === array( 10, 30 ) && 'id:30' === $visible['next_cursor'], 'Private source is omitted without stalling cursor progress.' );
unset( $GLOBALS['private_post_ids'][20] );

$wpdb->records[ 25 ] = record( 25, 'draft' );
$again = Registry::file26_list_batch( 'v1:id:20', 2 );
check( keys( $again ) === array( 30, 40 ), 'Unpublished intermediate ID never alters subsequent page identity.' );
$wpdb->records[ 50 ] = record( 50 );
$after = Registry::file26_list_batch( 'v1:id:40', 2 );
check( keys( $after ) === array( 50 ) && $after['done'], 'New higher-ID post is included on the next keyset scan.' );

$wpdb->force_unordered = true;
$out_of_order = Registry::file26_list_batch( '', 2 );
check( $out_of_order instanceof WP_Error && 'file21_file26_cursor_not_monotonic' === $out_of_order->get_error_code(), 'Out-of-order source query fails closed.' );
$wpdb->force_unordered = false;
$wpdb->fail_next = true;
$query_failure = Registry::file26_list_batch( '', 2 );
check( $query_failure instanceof WP_Error && 'file21_file26_query_failed' === $query_failure->get_error_code(), 'Database read failure never returns a false completed scan.' );

echo "PASS: File 21 keyset shadow source cursor, privacy, failure and concurrency regressions" . PHP_EOL;
