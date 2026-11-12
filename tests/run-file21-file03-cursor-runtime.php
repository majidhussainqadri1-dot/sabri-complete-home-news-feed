<?php
/**
 * File21 -> File03 signed timeline cursor: actual provider method runtime test.
 * Pure deterministic fake WordPress store, separate from real DB/WP staging.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );

class WP_Error {
	private $code;
	public function __construct( $code, $message = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $v ) { return abs( (int) $v ); }
function __( $v, $domain = '' ) { return $v; }

class File21ProfileTestSettings { public static function enabled( $key ) { return 'profile_timeline_enabled' === $key; } }
class_alias( File21ProfileTestSettings::class, 'Sabri\HomeNewsFeed\CorrectivePublicSettings' );
class File21ProfileTestSafeMode { public static function public_features_disabled() { return false; } }
class_alias( File21ProfileTestSafeMode::class, 'Sabri\HomeNewsFeed\SafeMode' );
class File21ProfileTestMetadata {
	public static function review_state_publicly_visible( $id ) { return empty( $GLOBALS['denied_posts'][$id] ); }
	public static function user_can_view( $id, $viewer = 0 ) { return empty( $GLOBALS['private_posts'][$id] ) && empty( $GLOBALS['denied_posts'][$id] ); }
	public static function feed_type( $id ) { return 'research'; }
	public static function visibility( $id ) { return empty( $GLOBALS['private_posts'][$id] ) ? 'public' : 'private'; }
}
class_alias( File21ProfileTestMetadata::class, 'Sabri\HomeNewsFeed\PostMetadata' );

class File21ProfileDB {
	public $posts = 'wp_posts';
	public $last_error = '';
	public $entries = array();
	public $queries = array();
	public $force_error = false;
	public $force_reverse = false;

	public function prepare( $sql, ...$args ) {
		$parts = array_map( static function ( $arg ) {
			return is_int( $arg ) ? (string) $arg : "'" . str_replace( "'", "''", (string) $arg ) . "'";
		}, $args );
		return vsprintf( $sql, $parts );
	}
	public function get_results( $sql, $format = null ) {
		$this->queries[] = $sql;
		if ( $this->force_error ) {
			$this->last_error = 'simulated_read_error';
			return null;
		}
		$this->last_error = '';
		preg_match( '/post_author = ([0-9]+)/', $sql, $author );
		preg_match( "/post_date_gmt (?:<=|<) '([^']{19})'/", $sql, $cutoff );
		preg_match( '/LIMIT ([0-9]+)/', $sql, $limit );
		if ( !isset( $author[1], $cutoff[1], $limit[1] ) || !str_contains( $sql, "post_status = 'publish'" ) ) {
			$this->last_error = 'unexpected_query';
			return null;
		}
		$inclusive = str_contains( $sql, 'post_date_gmt <= ' );
		$tie = '';
		if ( preg_match( '/CAST\(ID AS CHAR\) < \'([0-9]+)\'/', $sql, $match ) ) {
			$tie = $match[1];
		}
		$rows = array();
		foreach ( $this->entries as $post ) {
			if ( (int) $post->post_author !== (int) $author[1] || $post->post_status !== 'publish' || $post->post_type !== 'post' ) {
				continue;
			}
			$time = (string) $post->post_date_gmt;
			$include = $time < $cutoff[1] ||
				( $time === $cutoff[1] && ( $inclusive || ( '' !== $tie && strcmp( (string) $post->ID, $tie ) < 0 ) ) );
			if ( $include ) {
				$rows[] = array( 'ID' => $post->ID, 'post_date_gmt' => $time );
			}
		}
		usort( $rows, static function ( $a, $b ) {
			$cmp = strcmp( $b['post_date_gmt'], $a['post_date_gmt'] );
			return 0 !== $cmp ? $cmp : strcmp( (string) $b['ID'], (string) $a['ID'] );
		} );
		if ( $this->force_reverse ) { $rows = array_reverse( $rows ); }
		return array_slice( $rows, 0, (int) $limit[1] );
	}
}
function get_post( $id ) { global $wpdb; return $wpdb->entries[$id] ?? null; }
function get_post_field( $field, $id ) { $p = get_post( $id ); return $p ? ( $p->$field ?? '' ) : ''; }
function get_the_title( $id ) { return 'Public publication ' . $id; }
function get_the_excerpt( $id ) { return 'Safe public abstract'; }
function get_permalink( $id ) { return 'https://example.test/publication/' . $id; }
function get_post_time( $format, $gmt, $id ) {
	$p = get_post( $id );
	return $p ? gmdate( $format, strtotime( $p->post_date_gmt . ' UTC' ) ) : '';
}
function get_the_date( $format, $id ) { return ''; }
function get_post_modified_time( $format, $gmt, $id ) { return strtotime( get_post($id)->post_modified_gmt . ' UTC' ); }
function get_the_post_thumbnail_url( $id, $size ) { return ''; }

require_once dirname( __DIR__ ) . '/includes/class-profile-timeline.php';

function add_record( $id, $time = '2026-10-10 10:00:00', $author = 2 ) {
	$GLOBALS['wpdb']->entries[$id] = (object) array(
		'ID' => $id, 'post_type' => 'post', 'post_status' => 'publish',
		'post_author' => $author, 'post_date_gmt' => $time,
		'post_modified_gmt' => '2026-10-12 10:00:00', 'post_content' => 'public text',
	);
}
function assert_file03( $truth, $what ) {
	if ( !$truth ) { fwrite( STDERR, "FAIL: $what\n" ); exit( 1 ); }
	echo "PASS: $what\n";
}
function ids( $items ) { return array_map( static function ( $v ) { return (int) substr( $v['canonical_id'], strlen( 'file21-post:' ) ); }, $items ); }
function cursor_from( $item ) {
	return array( 't' => gmdate( 'Y-m-d H:i:s', strtotime( $item['published_at'] ) ),
		'i' => 'file21:' . $item['canonical_id'] );
}
$wpdb = new File21ProfileDB();
for ( $i = 1; $i <= 65; ++$i ) { add_record( $i ); }
add_record( 100, '2026-10-10 10:00:00', 99 ); // foreign author never included.
$klass = 'Sabri\HomeNewsFeed\ProfileTimeline';
$expected = range( 1, 65 );
usort( $expected, static function ( $a, $b ) { return strcmp( (string) $b, (string) $a ); } );
$first = $klass::file03_provider_items( array(), 2, array( 'limit' => 21, 'viewer_id' => 0 ) );
assert_file03( is_array($first) && ids($first) === array_slice( $expected, 0, 21 ), 'First 21 items honor File03 lexical same-time order.' );
// File03 displays 20 and uses the 20th as the signed next-page cursor.
$second = $klass::file03_provider_items( array(), 2, array( 'limit' => 21, 'viewer_id' => 0, 'cursor' => cursor_from( $first[19] ) ) );
assert_file03( is_array($second) && ids($second) === array_slice( $expected, 20, 21 ), 'Second page actually fetches posts older than File03 signed cursor.' );
$repeat = $klass::file03_provider_items( array(), 2, array( 'limit' => 21, 'viewer_id' => 0, 'cursor' => cursor_from( $first[19] ) ) );
assert_file03( ids($repeat) === ids($second), 'Cursor restart replays the same authorized window.' );
$third = $klass::file03_provider_items( array(), 2, array( 'limit' => 21, 'viewer_id' => 0, 'cursor' => cursor_from( $second[19] ) ) );
assert_file03( is_array($third) && ids($third) === array_slice( $expected, 40, 21 ), 'Third signed-cursor page reaches older posts without a gap.' );

$other_before = $klass::file03_provider_items( array(), 2, array( 'limit' => 21, 'cursor' => array( 't'=>'2026-10-10 10:00:00', 'i'=>'file10:video:99' ) ) );
assert_file03( is_array( $other_before ) && !$other_before, 'Earlier lexical provider cursor excludes equal-time File21 items.' );
$other_after = $klass::file03_provider_items( array(), 2, array( 'limit' => 21, 'cursor' => array( 't'=>'2026-10-10 10:00:00', 'i'=>'file25:post:99' ) ) );
assert_file03( ids( $other_after ) === array_slice( $expected, 0, 21 ), 'Later lexical provider cursor includes equal-time File21 items.' );

$GLOBALS['private_posts'][65] = true;
$GLOBALS['denied_posts'][64] = true;
$visible = $klass::file03_provider_items( array(), 2, array( 'limit' => 20 ) );
assert_file03( is_array($visible) && !in_array(65,ids($visible),true) && !in_array(64,ids($visible),true), 'Private/review-blocked objects omitted after SQL query.' );
unset( $GLOBALS['private_posts'][65], $GLOBALS['denied_posts'][64] );
$wpdb->entries[65]->post_status = 'draft';
$after_withdrawal = $klass::file03_provider_items( array(), 2, array( 'limit' => 20 ) );
assert_file03( is_array( $after_withdrawal ) && !in_array(65,ids($after_withdrawal),true), 'Unpublished objects cannot enter federated profile timeline.' );

foreach( array(
	array( 't'=>'garbage', 'i'=>'file21:file21-post:2' ),
	array( 't'=>'2026-10-10 10:00:00', 'i'=>'file21:file21-post:0' ),
	array( 't'=>'2026-10-10 10:00:00', 'i'=>'wrong cursor' ),
	array( 't'=>'2026-10-10 10:00:00' ),
) as $bad ) {
	$failure = $klass::file03_provider_items( array(), 2, array( 'cursor'=>$bad ) );
	assert_file03( is_wp_error($failure) && 'file21_file03_cursor_invalid' === $failure->get_error_code(), 'Malformed File03 cursor fails closed.' );
}
$wpdb->force_error = true;
$failure = $klass::file03_provider_items( array(), 2, array( 'limit'=>10 ) );
assert_file03( is_wp_error($failure) && 'file21_file03_query_failed' === $failure->get_error_code(), 'DB read failure never becomes a false empty page.' );
$wpdb->force_error = false;
$wpdb->force_reverse = true;
$failure = $klass::file03_provider_items( array(), 2, array( 'limit'=>10 ) );
assert_file03( is_wp_error($failure) && 'file21_file03_source_order_invalid' === $failure->get_error_code(), 'Out-of-order source rows fail closed.' );
$wpdb->force_reverse = false;

// A cap must not masquerade as a complete empty page when all 500 most recent
// candidates are restricted and older public content still exists.
$wpdb->entries = array();
for ($i=1;$i<=510;$i++) { add_record($i); $GLOBALS['private_posts'][$i] = true; }
unset($GLOBALS['private_posts'][1]);
$saturated = $klass::file03_provider_items( array(), 2, array( 'limit'=>20 ) );
assert_file03( is_wp_error($saturated) && 'file21_file03_scan_limit' === $saturated->get_error_code(), 'Saturated restricted source scan degrades instead of reporting a false empty timeline.' );

echo "PASS: File21/File03 current signed-cursor runtime regression\n";
