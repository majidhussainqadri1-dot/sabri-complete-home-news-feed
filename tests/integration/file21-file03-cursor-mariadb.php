<?php
/**
 * Genuine isolated MariaDB test of File21->File03 signed timeline SQL ordering.
 * Never runs against staging, the user's live DB, or File03's own tables.
 */
define('ABSPATH',__DIR__.'/');
define('ARRAY_A','ARRAY_A');
class WP_Error {
	private $code;
	public function __construct($code,$message=''){$this->code=$code;}
	public function get_error_code(){return $this->code;}
}
function is_wp_error($x){return $x instanceof WP_Error;}
function absint($x){return abs((int)$x);}
function __($x,$domain=''){return $x;}
class File21SQLSettings {public static function enabled($key){return 'profile_timeline_enabled'===$key;}}
class_alias(File21SQLSettings::class,'Sabri\HomeNewsFeed\CorrectivePublicSettings');
class File21SQLSafe {public static function public_features_disabled(){return false;}}
class_alias(File21SQLSafe::class,'Sabri\HomeNewsFeed\SafeMode');
class File21SQLMeta {
	public static function review_state_publicly_visible($id){return empty($GLOBALS['denied'][$id]);}
	public static function user_can_view($id,$viewer=0){return empty($GLOBALS['denied'][$id])&&empty($GLOBALS['private'][$id]);}
	public static function visibility($id){return empty($GLOBALS['private'][$id])?'public':'private';}
	public static function feed_type($id){return 'research';}
}
class_alias(File21SQLMeta::class,'Sabri\HomeNewsFeed\PostMetadata');
$pdo=new PDO(
	'mysql:host='.(getenv('FILE21_DB_HOST')?:'127.0.0.1').';port=3306;dbname='.(getenv('FILE21_DB_NAME')?:'file21_test').';charset=utf8mb4',
	getenv('FILE21_DB_USER')?:'root',getenv('FILE21_DB_PASS')?:'test-root-password',
	array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC)
);
$pdo->query('DROP TABLE IF EXISTS wp_posts');
$pdo->query("CREATE TABLE wp_posts (
	ID BIGINT UNSIGNED NOT NULL PRIMARY KEY,
	post_author BIGINT UNSIGNED NOT NULL,
	post_type VARCHAR(20) NOT NULL,
	post_status VARCHAR(20) NOT NULL,
	post_date_gmt DATETIME NOT NULL,
	post_modified_gmt DATETIME NOT NULL,
	post_title VARCHAR(255) NOT NULL,
	INDEX author_date (post_author,post_date_gmt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
class File21MariaWPDB {
	public $posts='wp_posts';
	public $last_error='';
	private $db;
	public function __construct(PDO $db){$this->db=$db;}
	public function prepare($sql,...$args){
		$safe=array_map(function($v){return is_int($v)?(string)$v:$this->db->quote((string)$v);},$args);
		return vsprintf($sql,$safe);
	}
	public function get_results($query,$fmt=ARRAY_A){
		try{$this->last_error='';return $this->db->query($query)->fetchAll(PDO::FETCH_ASSOC);}
		catch(Throwable $e){$this->last_error=$e->getMessage();return null;}
	}
}
$wpdb=new File21MariaWPDB($pdo);
function get_post($id){
	global $pdo;$q=$pdo->prepare('SELECT * FROM wp_posts WHERE ID=?');$q->execute(array((int)$id));$x=$q->fetch();
	return $x?(object)$x:null;
}
function get_post_field($field,$id){$p=get_post($id);return $p?($p->$field??''):'';}
function get_the_title($id){return 'Publication '.(int)$id;}
function get_the_excerpt($id){return 'Safe public excerpt';}
function get_permalink($id){return 'https://example.test/article/'.(int)$id;}
function get_post_time($format,$gmt,$id){return gmdate($format,strtotime(get_post($id)->post_date_gmt.' UTC'));}
function get_the_date($format,$id){return '';}
function get_post_modified_time($format,$gmt,$id){return strtotime(get_post($id)->post_modified_gmt.' UTC');}
function get_the_post_thumbnail_url($id,$size){return '';}
require_once dirname(__DIR__,2).'/includes/class-profile-timeline.php';
function verify($value,$name){if(!$value){fwrite(STDERR,"FAIL: $name\n");exit(1);}echo "PASS: $name\n";}
function ids($items){return array_map(static function($x){return (int)substr($x['canonical_id'],12);},$items);}
function cursor($item){return array('t'=>gmdate('Y-m-d H:i:s',strtotime($item['published_at'])),'i'=>'file21:'.$item['canonical_id']);}
$ins=$pdo->prepare('INSERT INTO wp_posts VALUES(?,?,?,?,?,?,?)');
for($i=1;$i<=65;$i++){$ins->execute(array($i,2,'post','publish','2026-10-10 10:00:00','2026-10-12 10:00:00','Post '.$i));}
$ins->execute(array(90,3,'post','publish','2026-10-10 10:00:00','2026-10-12 10:00:00','Foreign author'));
$target='Sabri\HomeNewsFeed\ProfileTimeline';
$sorted=range(1,65);usort($sorted,static function($a,$b){return strcmp((string)$b,(string)$a);});
$a=$target::file03_provider_items(array(),2,array('limit'=>21));
verify(is_array($a)&&ids($a)===array_slice($sorted,0,21),'Real SQL first same-time group follows File03 lexical ordering');
$b=$target::file03_provider_items(array(),2,array('limit'=>21,'cursor'=>cursor($a[19])));
verify(is_array($b)&&ids($b)===array_slice($sorted,20,21),'Real SQL second federated page retrieves older records');
$c=$target::file03_provider_items(array(),2,array('limit'=>21,'cursor'=>cursor($b[19])));
verify(is_array($c)&&ids($c)===array_slice($sorted,40,21),'Real SQL third federated page retains order and complete membership');
$other=$target::file03_provider_items(array(),2,array('limit'=>21,'cursor'=>array('t'=>'2026-10-10 10:00:00','i'=>'file25:video:100')));
verify(is_array($other)&&ids($other)===array_slice($sorted,0,21),'Real SQL cross-provider tie-breaker includes File21 when lexically prior');
$pdo->query("UPDATE wp_posts SET post_status='draft' WHERE ID=".$sorted[0]);
$GLOBALS['private'][$sorted[1]]=true;
$visible=$target::file03_provider_items(array(),2,array('limit'=>21));
verify(is_array($visible)&&!in_array($sorted[0],ids($visible),true)&&!in_array($sorted[1],ids($visible),true),'Real SQL post withdrawal and current privacy recheck');
$pdo->query('DROP TABLE wp_posts');
$failure=$target::file03_provider_items(array(),2,array('limit'=>21));
verify(is_wp_error($failure)&&'file21_file03_query_failed'===$failure->get_error_code(),'Real SQL table failure degrades rather than reporting empty page');
echo "PASS: File21/File03 isolated MariaDB 11.4 signed cursor contract\n";
