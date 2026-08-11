<?php
namespace Sabri\HomeNewsFeed {
	final class HomeIntegration {}
	final class NewsRouting {}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	$GLOBALS['f21_options'] = array();
	function add_filter() { return true; }
	function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
	function absint( $value ) { return abs( (int) $value ); }
	function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['f21_options'] ) ? $GLOBALS['f21_options'][ $key ] : $default; }
	function update_option( $key, $value ) { $GLOBALS['f21_options'][ $key ] = $value; return true; }
	function wp_json_encode( $value ) { return json_encode( $value ); }
	require dirname( __DIR__ ) . '/includes/class-file01-reconciliation-adapter.php';

	$n=0;
	$ok=function($c,$m)use(&$n){$n++;if(!$c){fwrite(STDERR,"FAIL: $m\n");exit(1);}};
	$home=\Sabri\HomeNewsFeed\File01ReconciliationAdapter::plan(null,array('legacy_key'=>'home','page_id'=>162));
	$news=\Sabri\HomeNewsFeed\File01ReconciliationAdapter::plan(null,array('legacy_key'=>'news','page_id'=>163));
	$ok(is_array($home)&&true===$home['accepted']&&'file-21'===$home['owner_module'],'Home is acknowledged by File 21.');
	$ok(is_array($news)&&true===$news['accepted']&&'file-21'===$news['owner_module'],'News is acknowledged by File 21.');
	$ok(null===\Sabri\HomeNewsFeed\File01ReconciliationAdapter::plan(null,array('legacy_key'=>'founder','page_id'=>164)),'Founder route is not claimed by File 21.');
	$hash=str_repeat('b',64);$action=array('legacy_key'=>'home','page_id'=>162,'owner_plan'=>$home);
	$receipt=\Sabri\HomeNewsFeed\File01ReconciliationAdapter::execute(null,$action,$hash);
	$ok(is_array($receipt)&&true===$receipt['success'],'Execution returns a success receipt.');
	$ok('file-21'===$receipt['owner_module'],'Receipt is owner-bound.');
	$ok(64===strlen($receipt['state_hash']),'Receipt has a 64-hex state hash.');
	$again=\Sabri\HomeNewsFeed\File01ReconciliationAdapter::execute(null,$action,$hash);
	$ok($again['receipt_id']===$receipt['receipt_id'],'Execution is idempotent.');
	$rolled=\Sabri\HomeNewsFeed\File01ReconciliationAdapter::rollback(null,$receipt,$hash);
	$ok(is_array($rolled)&&true===$rolled['success'],'Rollback succeeds.');
	$replay=\Sabri\HomeNewsFeed\File01ReconciliationAdapter::rollback(null,$receipt,$hash);
	$ok(is_array($replay)&&true===$replay['success']&&!empty($replay['idempotent_replay']),'Rollback is idempotent.');
	echo "File 21 File01 reconciliation adapter assertions {$n}/{$n} PASS\n";
}
