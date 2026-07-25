<?php
require __DIR__ . '/phase5-stubs.php';
$root=dirname(__DIR__);foreach(array('class-phase5-contracts.php','class-phase5-database.php','class-phase5-repository.php','class-phase5-migrations.php')as$file)require_once$root.'/includes/'.$file;
use Sabri\HomeNewsFeed\Phase5Database;use Sabri\HomeNewsFeed\Phase5Migrations;
$failures=array();$assert=static function($c,$m)use(&$failures){if(!$c)$failures[]=$m;};
$schema=Phase5Database::schema('wp_');$indexes=Phase5Database::expected_indexes();$assert(count($schema)===10,'Schema table count mismatch.');$assert(count($indexes)===10,'Index manifest count mismatch.');foreach($schema as$slug=>$sql){$assert(isset($indexes[$slug]),'Missing index manifest '.$slug);$assert(false!==strpos($sql,'PRIMARY KEY'),'Missing primary key '.$slug);$assert(false===stripos($sql,'DROP TABLE'),'Destructive SQL in '.$slug);$assert(false===stripos($sql,'TRUNCATE'),'Truncate SQL in '.$slug);}
$source=file_get_contents($root.'/includes/class-phase5-migrations.php');foreach(array('LOCK_TTL','try','finally','Phase5Database::install','Phase5Database::verify','update_option( self::STATE_OPTION')as$needle)$assert(false!==strpos($source,$needle),'Migration safety missing '.$needle);
$database_source=file_get_contents($root.'/includes/class-phase5-database.php');$assert(false!==strpos($database_source,"method_exists( \$wpdb, 'esc_like' )"),'Database verification esc_like compatibility fallback missing.');
$uninstall=file_get_contents($root.'/uninstall.php');$assert(false!==strpos($uninstall,'DELETE-PHASE5-EDITORIAL-DATA'),'Second destructive uninstall confirmation missing.');
if($failures){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}echo "Phase 5 migration and lifecycle tests passed.\n";
