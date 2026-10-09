<?php
/** Exact registry freeze regression for the Founder-approved Home controls/rows. */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['f21_registry_attack'] = true;
	function __($text,$domain=''){unset($domain);return $text;}
	function sanitize_key($value){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',(string)$value));}
	function apply_filters($hook,$value){
		if(!$GLOBALS['f21_registry_attack']) return $value;
		if('sabri_hnf_home_control_items'===$hook){
			unset($value['latest']);
			$value['rogue-control']=array('label'=>'Rogue','kind'=>'feed');
			$value['for-you']['label']='For You (configured)';
			$value['for-you']['rogue_field']='forbidden';
		}
		if('sabri_hnf_home_rows'===$hook){
			unset($value['latest-news']);
			$value['rogue-row']=array('label'=>'Rogue','provider'=>'feed','limit'=>99);
			$value['most-viral-now']['limit']=5;
			$value['most-viral-now']['rogue_field']='forbidden';
		}
		return $value;
	}
}
namespace Sabri\HomeNewsFeed {
	require dirname(__DIR__).'/includes/class-home-composition-registry.php';
	$fail=array();$ok=function($c,$m)use(&$fail){if(!$c)$fail[]=$m;};
	$controls=HomeCompositionRegistry::control_items();
	$rows=HomeCompositionRegistry::rows();
	$ok(14===count($controls),'Control registry must remain exactly 14 after hostile filter.');
	$ok(isset($controls['latest'])&&!isset($controls['rogue-control']),'Canonical control keys cannot be removed or added.');
	$ok('For You'===($controls['for-you']['label']??''),'Canonical control labels cannot be changed by runtime filters.');
	$ok(!isset($controls['for-you']['rogue_field']),'Unknown control fields must be discarded.');
	$ok(10===count($rows),'Home row registry must remain exactly 10 after hostile filter.');
	$ok(isset($rows['latest-news'])&&!isset($rows['rogue-row']),'Canonical row keys cannot be removed or added.');
	$ok(6===($rows['most-viral-now']['limit']??0),'Canonical row defaults cannot be changed by runtime filters.');
	$ok(!isset($rows['most-viral-now']['rogue_field']),'Unknown row fields must be discarded.');
	if($fail){fwrite(STDERR,"File 21 Home registry freeze failures:\n- ".implode("\n- ",$fail)."\n");exit(1);}
	echo "File 21 Home registry freeze: PASS\n";
}