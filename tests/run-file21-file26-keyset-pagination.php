<?php
/**
 * Native File21 -> File26 source keyset regression (no real WordPress/Live DB).
 * Verifies filtered, shrinking publishable sets never shift an offset cursor.
 */
namespace {
    define( 'ABSPATH', __DIR__ . '/' );
    class WP_Error {
        private $code;
        public function __construct( $code, $message = '' ) { $this->code = $code; }
        public function get_error_code() { return $this->code; }
    }
    $GLOBALS['file21_filters'] = array();
    $GLOBALS['file21_ids'] = array( 10, 20, 30, 40 );
    $GLOBALS['file21_query_error'] = false;
    $GLOBALS['wpdb'] = new class {
        public $posts = 'wp_posts';
        public $last_error = '';
        public function prepare( $sql, $value ) { return sprintf( $sql, $value ); }
    };
    function add_filter($hook,$callback,$priority=10,$accepted_args=1) { $GLOBALS['file21_filters'][$hook][] = $callback; }
    function remove_filter($hook,$callback,$priority=10) {
        if (!isset($GLOBALS['file21_filters'][$hook])) return;
        $GLOBALS['file21_filters'][$hook] = array_values(array_filter(
            $GLOBALS['file21_filters'][$hook],static function($cb)use($callback){return $cb !== $callback;}
        ));
    }
    class WP_Query {
        public $posts;
        private $args;
        public function __construct(array $args) {
            $this->args=$args;
            $where=' AND 1=1';
            foreach($GLOBALS['file21_filters']['posts_where']??array() as $callback) {
                $where=$callback($where,$this);
            }
            $after=0;
            if (preg_match('/wp_posts\.ID > ([0-9]+)/',$where,$match)) { $after=(int)$match[1]; }
            $ids = array_values(array_filter($GLOBALS['file21_ids'], static function($id)use($after){return $id>$after;}));
            sort($ids,SORT_NUMERIC);
            $ids=array_slice($ids,0,$args['posts_per_page']);
            $this->posts=array_map(static function($id){
                return (object)array(
                    'ID'=>$id,'post_type'=>'post','post_status'=>'publish',
                    'post_title'=>'Public post '.$id,'post_author'=>0,
                    'post_date_gmt'=>'2026-11-01 00:00:00','post_modified_gmt'=>'2026-11-01 00:00:00'
                );
            },$ids);
            if($GLOBALS['file21_query_error']) { $GLOBALS['wpdb']->last_error='simulated DB read error'; }
        }
        public function get($name) { return $this->args[$name]??null; }
    }
    function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/','',(string)$value)); }
    function get_post_meta($id,$key,$single=true) {
        if ($key==='_sabri_file21_file26_event_version') return 100 + (int)$id;
        return '';
    }
    function get_the_title($id) { return 'Public post '.(int)$id; }
    function get_permalink($id) { return 'https://example.test/public/'.(int)$id; }
    function get_the_excerpt($id) { return 'Approved educational fixture'; }
}
namespace Sabri\HomeNewsFeed {
    final class Phase4Contracts { const POST_TYPE='sabri_news'; }
    final class PostMetadata {
        const META_LANGUAGE = '_test_lang';
        const META_REGION = '_test_region';
        public static function visibility($id) { return 'public'; }
        public static function review_state_publicly_visible($id) { return true; }
        public static function user_can_view($id,$user_id) { return true; }
        public static function feed_type($id) { return 'post'; }
    }
    final class CanonicalIdentityAdapter {
        public static function is_verified_doctor($id) { return false; }
        public static function is_founder($id) { return false; }
        public static function is_administrator($id) { return false; }
    }
    require_once dirname(__DIR__).'/includes/class-search-provider-registry.php';
}
namespace {
    $pass=static function($condition,$label) {
        if (!$condition) { fwrite(STDERR, "FAIL: ".$label."\n"); exit(1); }
        echo "PASS: ".$label."\n";
    };
    $owner='Sabri\\HomeNewsFeed\\SearchProviderRegistry';
    $first=$owner::file26_list_batch('',2);
    $pass(is_array($first) && $first['done']===false,'First keyset page remains nonfinal');
    $pass(array_column($first['items'],'object_id')===array('10','20'),'First page returns source post IDs 10 and 20');
    $pass($first['next_cursor']==='f21-id-v1:20','Opaque versioned cursor points to last scanned post ID');
    $pass(empty($GLOBALS['file21_filters']['posts_where']),'Temporary posts_where guard unregistered after query');

    $GLOBALS['file21_ids']=array(20,30,40); // post 10 removed or restricted.
    $second=$owner::file26_list_batch($first['next_cursor'],2);
    $pass(array_column($second['items'],'object_id')===array('30','40'),'Mutating owner set does not skip post 30');
    $pass($second['done']===false && $second['next_cursor']==='f21-id-v1:40','Second full batch advances exact ID checkpoint');
    $third=$owner::file26_list_batch($second['next_cursor'],2);
    $pass($third['done']===true && $third['items']===array(),'Final empty batch explicitly attests completion');

    $GLOBALS['file21_ids']=array(20,30,40,50); // later publication.
    $later=$owner::file26_list_batch('f21-id-v1:40',2);
    $pass(array_column($later['items'],'object_id')===array('50'),'New post above checkpoint is not missed');

    foreach (array('2','0','f21-id-v0:20','f21-id-v1:0','f21-id-v1:-5','f21-id-v1:9999999999999999999999') as $cursor) {
        $invalid=$owner::file26_list_batch($cursor,2);
        $pass($invalid instanceof \WP_Error,'Incompatible or overflowing owner cursor rejected, never reinterpreted as offset');
    }
    $GLOBALS['file21_ids']=array('18446744073709551615');
    $overflow=$owner::file26_list_batch('',1);
    $pass($overflow instanceof \WP_Error && $overflow->get_error_code()==='file21_file26_source_post_id_overflow','Oversize unsigned source post-ID cannot silently saturate cursor');
    $GLOBALS['file21_ids']=array(2.5);
    $malformed=$owner::file26_list_batch('',1);
    $pass($malformed instanceof \WP_Error && $malformed->get_error_code()==='file21_file26_source_post_id_invalid','Fractional native source post-ID rejected as malformed');
    $GLOBALS['file21_ids']=array(20,30,40);
    $GLOBALS['file21_query_error']=true;
    $denied=$owner::file26_list_batch('',2);
    $pass($denied instanceof \WP_Error && $denied->get_error_code()==='file21_file26_source_query_failed','Source DB error never yields false empty completed snapshot');
    $GLOBALS['file21_query_error']=false;
    $pass(empty($GLOBALS['file21_filters']['posts_where']),'Query error still releases temporary SQL filter');
    echo "PASS: File21 native File26 keyset pagination, shrinking source and cursor-version safety\n";
}
