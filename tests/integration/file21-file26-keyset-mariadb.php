<?php
/**
 * Real ephemeral MariaDB SQL regression for File21 -> File26 keyset source.
 * Runs against the actual SearchProviderRegistry::file26_list_batch method,
 * NOT a production site. Full WordPress/File26 deployment is a separate gate.
 */
define( 'ABSPATH', __DIR__ . '/' );

class WP_Error {
	private $code;
	public function __construct( $code, $message = '' ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
class File21SQLPhase4 { const POST_TYPE = 'sabri_news'; }
class_alias( File21SQLPhase4::class, 'Sabri\HomeNewsFeed\Phase4Contracts' );
class File21SQLMetadata {
	const META_LANGUAGE = '_sabri_feed_language';
	const META_REGION = '_sabri_feed_region';
	public static function visibility( $id ) { return !empty( $GLOBALS['private_ids'][$id] ) ? 'private' : 'public'; }
	public static function review_state_publicly_visible( $id ) { return empty( $GLOBALS['unapproved_ids'][$id] ); }
	public static function user_can_view( $id, $viewer ) { return empty( $GLOBALS['private_ids'][$id] ) && empty( $GLOBALS['unapproved_ids'][$id] ); }
	public static function feed_type( $id ) { return 'research'; }
}
class_alias( File21SQLMetadata::class, 'Sabri\HomeNewsFeed\PostMetadata' );
class File21SQLNewsProjector {
	public static function card( $post ) {
		if ( !empty( $GLOBALS['unapproved_ids'][(int) $post->ID] ) ) { return array(); }
		return array(
			'headline' => (string) $post->post_title,
			'summary' => 'Approved public report',
			'canonical_url' => 'https://example.test/news/' . $post->ID,
			'public_label' => 'News',
		);
	}
}
class_alias( File21SQLNewsProjector::class, 'Sabri\HomeNewsFeed\NewsPublicProjector' );

$host = getenv( 'FILE21_TEST_DB_HOST' ) ?: '127.0.0.1';
$name = getenv( 'FILE21_TEST_DB_NAME' ) ?: 'file21_test';
$user = getenv( 'FILE21_TEST_DB_USER' ) ?: 'root';
$pass = getenv( 'FILE21_TEST_DB_PASS' ) ?: 'test-root-password';
try {
	$pdo = new PDO( "mysql:host=$host;port=3306;dbname=$name;charset=utf8mb4", $user, $pass, array(
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	) );
} catch ( Throwable $e ) {
	fwrite( STDERR, 'FAIL: ephemeral MariaDB connection unavailable: ' . $e->getMessage() . PHP_EOL );
	exit( 1 );
}
$pdo->query( 'DROP TABLE IF EXISTS wp_posts' ); // isolated throwaway CI database ONLY
$pdo->query( "CREATE TABLE wp_posts (
	ID BIGINT UNSIGNED NOT NULL PRIMARY KEY,
	post_status VARCHAR(25) NOT NULL,
	post_type VARCHAR(32) NOT NULL,
	post_author BIGINT UNSIGNED NOT NULL DEFAULT 0,
	post_title VARCHAR(255) NOT NULL,
	post_date_gmt DATETIME NOT NULL,
	post_modified_gmt DATETIME NOT NULL,
	INDEX status_type (post_status, post_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" );

class File21SQLWPDB {
	public $posts = 'wp_posts';
	public $last_error = '';
	private $pdo;
	public function __construct( PDO $pdo ) { $this->pdo = $pdo; }
	public function prepare( $query, ...$args ) {
		$bound = array_map( function( $v ) {
			return is_int( $v ) ? $v : $this->pdo->quote( (string) $v );
		}, $args );
		return vsprintf( $query, $bound );
	}
	public function get_col( $query ) {
		try { $this->last_error = ''; return $this->pdo->query( $query )->fetchAll( PDO::FETCH_COLUMN ); }
		catch( Throwable $e ) { $this->last_error = $e->getMessage(); return null; }
	}
}
$wpdb = new File21SQLWPDB( $pdo );
function absint( $v ) { return abs( (int) $v ); }
function get_post( $id ) {
	$stmt = $GLOBALS['pdo']->prepare( 'SELECT * FROM wp_posts WHERE ID = ?' );
	$stmt->execute( array( (int) $id ) );
	$row = $stmt->fetch( PDO::FETCH_ASSOC );
	return $row ? (object) $row : null;
}
function get_post_meta( $id, $key, $single = true ) { return ''; }
function get_the_title( $id ) { $p = get_post( $id ); return $p ? $p->post_title : ''; }
function get_permalink( $id ) { return 'https://example.test/post/' . $id; }
function get_the_excerpt( $id ) { return 'Public source summary'; }
function get_the_terms( $id, $taxonomy ) { return array(); }
function get_bloginfo( $name ) { return 'en-US'; }
require_once dirname( __DIR__, 2 ) . '/includes/class-search-provider-registry.php';
use Sabri\HomeNewsFeed\SearchProviderRegistry as Registry;

function ensure( $condition, $message ) {
	if( !$condition ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
	echo "PASS: $message\n";
}
function insert_post( PDO $pdo, $id, $type = 'post', $status = 'publish' ) {
	$stmt = $pdo->prepare( 'INSERT INTO wp_posts (ID,post_status,post_type,post_author,post_title,post_date_gmt,post_modified_gmt) VALUES (?,?,?,?,?,?,?)' );
	$stmt->execute( array( $id, $status, $type, 0, 'Public record ' . $id, '2026-10-01 01:00:00', '2026-10-02 01:00:00' ) );
}
function ids( $batch ) {
	return array_map( static function ( $row ) { return (int) $row['object_id']; }, $batch['items'] );
}
foreach( array(10,20,30,40) as $id ) { insert_post( $pdo, $id ); }
$one = Registry::file26_list_batch( '', 2, array( 'connector' => Registry::FILE26_CONNECTOR_SLUG ) );
ensure( is_array( $one ) && ids( $one ) === array( 10,20 ) && $one['next_cursor'] === 'v1:id:20', 'Real MariaDB first page is ID based.' );

$pdo->query( "UPDATE wp_posts SET post_status='draft' WHERE ID=10" );
$two = Registry::file26_list_batch( $one['next_cursor'], 2 );
ensure( is_array( $two ) && ids( $two ) === array( 30,40 ) && $two['next_cursor'] === 'v1:id:40', 'Real MariaDB draft transition does not skip ID30.' );

$pdo->query( "UPDATE wp_posts SET post_status='publish' WHERE ID=10" );
$one = Registry::file26_list_batch( '', 2 );
$pdo->query( 'DELETE FROM wp_posts WHERE ID=10' );
$two = Registry::file26_list_batch( $one['next_cursor'], 2 );
ensure( ids( $two ) === array( 30,40 ), 'Real MariaDB physical deletion does not skip later ID.' );

insert_post( $pdo, 50, 'sabri_news' );
$GLOBALS['unapproved_ids'][50] = true;
$news = Registry::file26_list_batch( 'v1:id:40', 2 );
ensure( is_array( $news ) && !$news['items'] && $news['done'] && $news['next_cursor'] === 'v1:id:50', 'Unapproved News is not indexed but its keyset cursor advances.' );
unset( $GLOBALS['unapproved_ids'][50] );
$public_news = Registry::file26_list_batch( 'v1:id:40', 2 );
ensure( ids( $public_news ) === array( 50 ), 'Approved News becomes public through canonical projector.' );

insert_post( $pdo, 60, 'post' );
$GLOBALS['private_ids'][60] = true;
$restricted = Registry::file26_list_batch( 'v1:id:50', 2 );
ensure( !$restricted['items'] && $restricted['next_cursor'] === 'v1:id:60', 'Privacy change blocks projection without cursor reuse.' );
unset( $GLOBALS['private_ids'][60] );

$legacy = Registry::file26_list_batch( '2', 2 );
ensure( $legacy instanceof WP_Error && $legacy->get_error_code() === 'file21_file26_legacy_cursor_rejected', 'Legacy numeric offset is not mistaken for a source ID.' );
$partial = Registry::file26_list_batch( '', 2, array( 'object_id' => '30', 'connector' => Registry::FILE26_CONNECTOR_SLUG ) );
ensure( $partial instanceof WP_Error && $partial->get_error_code() === 'file21_file26_unsupported_scope', 'Unapproved partial staging is rejected.' );
$pdo->query( 'DROP TABLE wp_posts' );
$broken = Registry::file26_list_batch( '', 2 );
ensure( $broken instanceof WP_Error && $broken->get_error_code() === 'file21_file26_query_failed', 'Real MariaDB SQL error fails closed.' );

echo "PASS: isolated real MariaDB File21 keyset source SQL regression\n";
