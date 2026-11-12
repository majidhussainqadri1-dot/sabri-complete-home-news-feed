<?php
/**
 * File 21 + actual exact File 03 consumer (SPD_Timeline::query).
 * Uses deterministic native WP-boundary stubs, NOT live/staging WordPress.
 *
 * FILE03_ROOT must point to a checkout at the SHA pinned by the CI workflow.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'SPD_CONTRACT_VERSION', '1.4.0' );
define( 'MINUTE_IN_SECONDS', 60 );

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = array() ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
function __( $string, $domain = '' ) { return $string; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function esc_url_raw( $value ) { return (string) $value; }
function wp_kses_post( $value ) { return (string) $value; }
function wp_salt( $scheme = 'auth' ) { return 'file03-file21-integration-fixed-test-hmac-secret'; }
function get_transient( $key ) { return false; }
function set_transient( $key, $value, $expiration = 0 ) { return true; }

$GLOBALS['test_hooks'] = array();
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['test_hooks'][ $hook ][] = array( 'callback' => $callback, 'accepted_args' => $accepted_args );
	return true;
}
function apply_filters( $hook, $value, ...$arguments ) {
	foreach ( $GLOBALS['test_hooks'][ $hook ] ?? array() as $entry ) {
		$args = array_slice( array_merge( array( $value ), $arguments ), 0, $entry['accepted_args'] );
		$value = call_user_func_array( $entry['callback'], $args );
	}
	return $value;
}
function do_action( $hook, ...$args ) {}

class SPD_Profile_Repository {
	public static function instance() { return new self(); }
	public function find_by_user_id( $id, $ensure = false ) {
		return array( 'public_id' => '123e4567-e89b-42d3-a456-426614174000', 'user_id' => 2 );
	}
}
class SPD_Authorization {
	public static function profile_visibility_allows( $profile, $viewer ) { return true; }
	public static function audience_allows( $audience, $author_id, $viewer ) { return 'public' === $audience; }
	public static function normalize_audience( $audience ) { return $audience === 'public' ? 'public' : 'private'; }
}
class SPD_Helpers {
	public static function current_contract_claim( $claim, $min_version, $max_age ) {
		return is_array( $claim ) && ! empty( $claim['contract_version'] ) &&
			version_compare( (string) $claim['contract_version'], (string) $min_version, '>=' ) &&
			! empty( $claim['generated_at'] ) && ! empty( $claim['valid_until'] ) &&
			strtotime( $claim['valid_until'] ) >= time();
	}
	public static function same_origin_url( $url ) { return 0 === strpos( (string) $url, 'https://example.test/' ); }
	public static function json_encode( $value ) { return json_encode( $value ); }
}
class File21ConsumerTestSettings {
	public static function enabled( $key ) { return 'profile_timeline_enabled' === $key; }
}
class_alias( File21ConsumerTestSettings::class, 'Sabri\HomeNewsFeed\CorrectivePublicSettings' );
class File21ConsumerTestSafeMode {
	public static function public_features_disabled() { return false; }
}
class_alias( File21ConsumerTestSafeMode::class, 'Sabri\HomeNewsFeed\SafeMode' );
class File21ConsumerTestMetadata {
	public static function review_state_publicly_visible( $id ) { return empty( $GLOBALS['unapproved'][$id] ); }
	public static function user_can_view( $id, $viewer = 0 ) { return empty( $GLOBALS['unapproved'][$id] ) && empty( $GLOBALS['private'][$id] ); }
	public static function feed_type( $id ) { return 'research'; }
	public static function visibility( $id ) { return empty( $GLOBALS['private'][$id] ) ? 'public' : 'private'; }
}
class_alias( File21ConsumerTestMetadata::class, 'Sabri\HomeNewsFeed\PostMetadata' );

class File21ConsumerFakeDB {
	public $posts = 'wp_posts';
	public $last_error = '';
	public $source = array();
	public $break_reads = false;

	public function prepare( $sql, ...$args ) {
		$safe = array_map( static function ( $arg ) {
			return is_int( $arg ) ? (string) $arg : "'" . str_replace( "'", "''", (string) $arg ) . "'";
		}, $args );
		return vsprintf( $sql, $safe );
	}
	public function get_results( $sql, $format = ARRAY_A ) {
		if ( $this->break_reads ) { $this->last_error = 'simulated database error'; return null; }
		$this->last_error = '';
		if ( ! preg_match( '/post_author = (\d+)/', $sql, $auth ) ||
			! preg_match( "/post_date_gmt (?:<=|<) '([^']{19})'/", $sql, $start ) ||
			! preg_match( '/LIMIT (\d+)/', $sql, $max ) ||
			false === strpos( $sql, "post_status = 'publish'" ) ) {
			$this->last_error = 'malformed owner query'; return null;
		}
		$include_equals = false !== strpos( $sql, 'post_date_gmt <= ' );
		$tie_id = '';
		if ( preg_match( "/CAST\(ID AS CHAR\) < '([0-9]+)'/", $sql, $match ) ) {
			$tie_id = $match[1];
		}
		$items = array();
		foreach ( $this->source as $post ) {
			if ( (int) $post->post_author !== (int) $auth[1] ||
				'post' !== $post->post_type || 'publish' !== $post->post_status ) {
				continue;
			}
			$t = $post->post_date_gmt;
			$earlier = $t < $start[1] ||
				( $t === $start[1] && ( $include_equals || ( '' !== $tie_id && strcmp( (string) $post->ID, $tie_id ) < 0 ) ) );
			if ( $earlier ) {
				$items[] = array( 'ID' => $post->ID, 'post_date_gmt' => $t );
			}
		}
		usort( $items, static function ( $a, $b ) {
			$t = strcmp( $b['post_date_gmt'], $a['post_date_gmt'] );
			return $t ?: strcmp( (string) $b['ID'], (string) $a['ID'] );
		} );
		return array_slice( $items, 0, (int) $max[1] );
	}
}
$wpdb = new File21ConsumerFakeDB();
for ( $id = 1; $id <= 65; $id++ ) {
	$wpdb->source[$id] = (object) array(
		'ID' => $id,
		'post_type' => 'post',
		'post_status' => 'publish',
		'post_author' => 2,
		'post_date_gmt' => '2026-10-10 10:00:00',
		'post_modified_gmt' => '2026-10-11 10:00:00',
		'post_content' => 'Sample public article',
	);
}
$wpdb->source[101] = (object) array(
	'ID' => 101, 'post_type' => 'post', 'post_status' => 'publish',
	'post_author' => 88, 'post_date_gmt' => '2026-10-10 10:00:00',
	'post_modified_gmt' => '2026-10-11 10:00:00', 'post_content' => 'Other author',
);
function get_post( $id ) { global $wpdb; return $wpdb->source[$id] ?? null; }
function get_post_field( $field, $id ) {
	$post = get_post( $id );
	return $post->$field ?? '';
}
function get_the_title( $id ) { return 'Published Article ' . $id; }
function get_the_excerpt( $id ) { return 'Public evidence summary'; }
function get_permalink( $id ) { return 'https://example.test/post/' . $id; }
function get_post_time( $format, $gmt, $id ) {
	$post = get_post( $id );
	return gmdate( $format, strtotime( $post->post_date_gmt . ' UTC' ) );
}
function get_the_date( $format, $id ) { return ''; }
function get_post_modified_time( $format, $gmt, $id ) { return strtotime( get_post( $id )->post_modified_gmt . ' UTC' ); }
function get_the_post_thumbnail_url( $id, $size ) { return ''; }

require_once dirname( __DIR__ ) . '/includes/class-profile-timeline.php';
$external = getenv( 'FILE03_ROOT' );
if ( ! is_string( $external ) || ! is_file( $external . '/includes/class-spd-timeline.php' ) ) {
	fwrite( STDERR, "FAIL: Exact File 03 source checkout required.\n" );
	exit( 1 );
}
require_once $external . '/includes/class-spd-timeline.php';
\Sabri\HomeNewsFeed\ProfileTimeline::register();

function verify_consumer( $condition, $label ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $label\n" );
		exit( 1 );
	}
	echo "PASS: $label\n";
}
$expected = range( 1, 65 );
usort( $expected, static function ( $a, $b ) { return strcmp( (string) $b, (string) $a ); } );
$cursor = '';
$all = array();
for ( $page = 1; $page <= 4; ++$page ) {
	$result = \SPD_Timeline::query( 2, array( 'limit' => 20, 'provider' => 'file21', 'cursor' => $cursor ), 0 );
	verify_consumer( ! is_wp_error( $result ) && is_array( $result['items'] ), 'Real File03 consumer returns an authorized page ' . $page );
	verify_consumer( 'available' === ( $result['provider_health']['file21'] ?? '' ), 'Real File03 accepts healthy File21 owner provider on page ' . $page );
	$ids = array_map( static function ( $item ) {
		return (int) substr( $item['canonical_id'], strlen( 'file21-post:' ) );
	}, $result['items'] );
	$all = array_merge( $all, $ids );
	if ( $page < 4 ) {
		verify_consumer( count( $ids ) === 20 && $result['has_more'] && $result['next_cursor'] !== '', 'Actual HMAC-signed next-page cursor exists' );
		$cursor = $result['next_cursor'];
	} else {
		verify_consumer( count( $ids ) === 5 && ! $result['has_more'] && $result['next_cursor'] === '', 'Terminal owner page does not promise nonexistent content' );
	}
}
verify_consumer( $all === $expected, 'Actual File03 consumer sees all 65 owner posts in lexical order, no repeats, no missing items' );
$forged = substr( $cursor, 0, -1 ) . ( substr( $cursor, -1 ) === 'b' ? 'c' : 'b' );
$rejected = \SPD_Timeline::query( 2, array( 'limit' => 20, 'provider' => 'file21', 'cursor' => $forged ), 0 );
verify_consumer( is_wp_error( $rejected ) && 'spd_timeline_cursor_invalid' === $rejected->get_error_code(), 'File03 rejects a tampered signed cursor before owner callback' );

$GLOBALS['private'][65] = true;
$GLOBALS['unapproved'][64] = true;
$wpdb->source[63]->post_status = 'draft';
$restricted = \SPD_Timeline::query( 2, array( 'limit' => 20, 'provider' => 'file21' ), 0 );
$restricted_ids = array_map( static function ( $item ) {
	return (int) substr( $item['canonical_id'], strlen( 'file21-post:' ) );
}, $restricted['items'] );
verify_consumer( ! array_intersect( array( 65, 64, 63, 101 ), $restricted_ids ), 'File03 never receives withdrawn/review-denied/other-user source items' );

$wpdb->break_reads = true;
$fail = \SPD_Timeline::query( 2, array( 'limit' => 20, 'provider' => 'file21' ), 0 );
verify_consumer( ! is_wp_error( $fail ) && ( $fail['provider_health']['file21'] ?? '' ) === 'degraded' && $fail['partial'], 'Failed owner database read propagates as degraded provider, not silent empty success' );
echo "PASS: File21 owner + exact File03 signed federated timeline end-to-end source-contract regression.\n";
