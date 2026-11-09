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
echo "PASS: File21 versioned public-source event runtime regression\n";
