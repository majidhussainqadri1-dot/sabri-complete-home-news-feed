<?php
/**
 * Runtime contract of File21's own monotonic File26 event sequence.
 * Deliberately executes the actual private method via Reflection under a
 * deterministic WP-DB stub; cross-plugin SQL acceptance remains separate.
 */
define('ABSPATH',__DIR__ . '/');
$GLOBALS['file21_meta']=array();
$GLOBALS['file21_write_ok']=true;
function get_post_meta($id,$key,$single=true) { return $GLOBALS['file21_meta'][$id][$key]??''; }
function update_post_meta($id,$key,$value) {
    if(!$GLOBALS['file21_write_ok']){return false;}
    $GLOBALS['file21_meta'][$id][$key]=$value;
    return true;
}
class File21FakeDB {
    public $last_error='';
    public $grant=true;
    public $release_count=0;
    public function prepare($query,$arg) { return $query; }
    public function get_var($sql) {
        if(strpos($sql,'GET_LOCK')!==false){return $this->grant?'1':'0';}
        if(strpos($sql,'RELEASE_LOCK')!==false){$this->release_count++;return '1';}
        throw new RuntimeException('Unknown SQL: '.$sql);
    }
}
$wpdb=new File21FakeDB();
require_once dirname(__DIR__) . '/includes/class-search-provider-registry.php';
$klass='Sabri\\HomeNewsFeed\\SearchProviderRegistry';
$advance=new ReflectionMethod($klass,'file26_advance_object_version');
$advance->setAccessible(true);
$read=new ReflectionMethod($klass,'file26_object_version');
$read->setAccessible(true);
$post=(object)array('ID'=>187,'post_modified_gmt'=>'2026-10-01 08:09:10','post_date_gmt'=>'2026-09-30 08:09:10');
function file21_assert($condition,$what) {
    if(!$condition){fwrite(STDERR,"FAIL: $what\n");exit(1);}
    echo "PASS: $what\n";
}
$base=$read->invoke(null,$post);
file21_assert($base>0,'Initial source version derives from canonical WP post timestamp');
$v1=$advance->invoke(null,$post);
file21_assert(is_int($v1)&&$v1>$base,'First metadata event creates a durable higher version');
file21_assert((int)get_post_meta(187,'_sabri_file21_file26_event_version',true)===$v1,'Source version stored at owner');
$v2=$advance->invoke(null,$post);
file21_assert($v2===$v1+1,'Second metadata event increments version without modifying post_modified_gmt');
file21_assert($read->invoke(null,$post)===$v2,'Read path does not increment or change current owner version');
file21_assert($wpdb->release_count===2,'Every acquired per-post advisory lock is released');
$GLOBALS['file21_write_ok']=false;
$bad=$advance->invoke(null,$post);
file21_assert(null===$bad,'Failed post-meta write cannot generate a transient event version');
file21_assert((int)get_post_meta(187,'_sabri_file21_file26_event_version',true)===$v2,'Failed write leaves durable owner version unchanged');
file21_assert($wpdb->release_count===3,'Persist failure still releases the lock');
$GLOBALS['file21_write_ok']=true;
$wpdb->grant=false;
$denied=$advance->invoke(null,$post);
file21_assert(null===$denied,'Concurrent/denied lock fails closed and does not emit a version');
file21_assert($wpdb->release_count===3,'Denied lock never releases another worker lock');

// Regression: worker B may advance the durable version while worker A is still
// building a public projection. Worker A must emit its own reserved version,
// never B's version attached to A's potentially stale payload.
class File21ProjectionPhase4 { const POST_TYPE = 'sabri_news'; }
class_alias( File21ProjectionPhase4::class, 'Sabri\\HomeNewsFeed\\Phase4Contracts' );
class File21ProjectionMetadata {
    const META_LANGUAGE = '_sabri_feed_language';
    const META_REGION = '_sabri_feed_region';
    public static function visibility( $id ) { return 'public'; }
    public static function review_state_publicly_visible( $id ) { return true; }
    public static function user_can_view( $id, $viewer ) { return true; }
    public static function feed_type( $id ) { return 'research'; }
}
class_alias( File21ProjectionMetadata::class, 'Sabri\\HomeNewsFeed\\PostMetadata' );
function get_the_title( $id ) {
    if ( !empty( $GLOBALS['file21_interleave_version'] ) ) {
        // Simulate a competing worker succeeding after A has reserved a version.
        $GLOBALS['file21_meta'][$id]['_sabri_file21_file26_event_version'] = $GLOBALS['file21_interleave_version'];
    }
    return 'Visible public article';
}
function get_permalink( $id ) { return 'https://example.test/article/' . $id; }
function get_the_excerpt( $id ) { return 'Public evidence summary'; }
function do_action( $event, $payload ) {
    if ( 'sabri_file26_source_upsert' === $event ) {
        $GLOBALS['file21_emitted'][] = $payload;
    }
}
$GLOBALS['file21_emitted'] = array();
$GLOBALS['file21_interleave_version'] = $v2 + 25;
$wpdb->grant = true;
$public_post = (object) array_merge( (array) $post, array(
    'post_type' => 'post',
    'post_status' => 'publish',
    'post_author' => 0,
    'post_title' => 'Visible public article',
) );
$sync = new ReflectionMethod( $klass, 'file26_sync_post' );
$sync->setAccessible( true );
$sync->invoke( null, 187, $public_post );
file21_assert( 1 === count( $GLOBALS['file21_emitted'] ), 'Public metadata event is delivered once' );
$emitted = $GLOBALS['file21_emitted'][0];
file21_assert( $v2 + 1 === $emitted['object_version'], 'Interleaved projection retains its own reserved event version' );
file21_assert( $emitted['object_version'] === $emitted['source_event_sequence'], 'Object and source-event versions never diverge' );
file21_assert( $GLOBALS['file21_interleave_version'] === (int) get_post_meta( 187, '_sabri_file21_file26_event_version', true ), 'Concurrent later owner version remains distinct from the earlier delivered snapshot' );
$doc = new ReflectionMethod( $klass, 'file26_document' );
$doc->setAccessible( true );
$shadow = $doc->invoke( null, $public_post );
file21_assert( $GLOBALS['file21_interleave_version'] === $shadow['object_version'], 'Read-only shadow snapshot uses the latest durable owner version' );

echo "PASS: File21 versioned public-source event runtime regression\n";
