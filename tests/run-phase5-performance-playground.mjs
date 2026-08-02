import dns from 'node:dns';
import path from 'node:path';
import process from 'node:process';
import { runCLI } from '@wp-playground/cli';

dns.setDefaultResultOrder('ipv4first');

const phpVersion = process.env.SABRI_PLAYGROUND_PHP || '8.3';
const wpVersion = process.env.SABRI_PLAYGROUND_WP || 'latest';
const packagePath = process.env.SABRI_PLUGIN_ZIP || '';
const pluginPath = 'sabri-complete-home-news-feed/sabri-complete-home-news-feed.php';
const virtualZip = '/tmp/sabri-phase5-performance.zip';
let server;

async function php(code) {
	const response = await server.playground.run({ code: `<?php require '/wordpress/wp-load.php'; ${code}` });
	if (response.errors && String(response.errors).trim()) throw new Error(response.errors);
	return String(response.text || '').trim();
}

try {
	const options = { command: 'server', php: phpVersion, wp: wpVersion, debug: false, login: false };
	if (packagePath) {
		options.mount = [{ hostPath: path.resolve(packagePath), vfsPath: virtualZip }];
	} else {
		options.mount = [{ hostPath: path.resolve('.'), vfsPath: '/wordpress/wp-content/plugins/sabri-complete-home-news-feed' }];
		options.blueprint = { steps: [{ step: 'activatePlugin', pluginPath: `/wordpress/wp-content/plugins/${pluginPath}` }] };
	}
	server = await runCLI(options);
	if (packagePath) {
		const installed = JSON.parse(await php(`
			require_once ABSPATH.'wp-admin/includes/plugin.php';
			require_once ABSPATH.'wp-admin/includes/file.php';
			require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
			call_user_func('WP_'.'Filesystem');
			$u=new Plugin_Upgrader(new Automatic_Upgrader_Skin());
			$r=$u->install('${virtualZip}');
			$a=is_wp_error($r)?$r:activate_plugin('${pluginPath}');
			echo wp_json_encode(array('ok'=>!is_wp_error($a),'error'=>is_wp_error($a)?$a->get_error_message():''));
		`));
		if (!installed.ok) throw new Error(installed.error);
	}
	const result = JSON.parse(await php(String.raw`
		if ( ! class_exists( 'SMC_Contracts' ) ) {
			class SMC_Contracts {
				public static function assertions( $user_id ) {
					return array(
						'contract_version' => '1.1.2',
						'user_id' => (int) $user_id,
						'status' => 'approved',
						'approved' => true,
						'eligible' => true,
						'guardian_verified' => true,
						'two_factor_ready' => true,
						'session_two_factor' => true,
						'account_class' => 'administrator',
						'membership_type' => 'doctor',
						'professional_verified' => true,
						'public_profile_allowed' => true,
						'can_publish' => true,
						'suspended' => false,
					);
				}
			}
		}
		global $wpdb;
		$admin=username_exists('phase5perf');
		if(!$admin){
			$admin=wp_create_user('phase5perf','Phase5-Perf-123!','phase5perf@example.test');
			(new WP_User($admin))->set_role('administrator');
		}
		wp_set_current_user($admin);
		$phase4=\Sabri\HomeNewsFeed\NewsFeatureSettings::defaults();
		$phase4['editorial_news_enabled']=1;
		update_option(\Sabri\HomeNewsFeed\NewsFeatureSettings::OPTION_NAME,$phase4,false);
		$start=microtime(true);

		$seed_ids=array();
		for($i=0;$i<24;$i++){
			$id=wp_insert_post(array(
				'post_type'=>\Sabri\HomeNewsFeed\Phase4Contracts::POST_TYPE,
				'post_status'=>'publish',
				'post_author'=>$admin,
				'post_title'=>'Phase 5 Load Seed Story '.$i,
				'post_name'=>'phase5-load-seed-'.$i,
				'post_excerpt'=>'Load summary',
				'post_content'=>'Load body',
			));
			if(is_wp_error($id)){echo wp_json_encode(array('error'=>$id->get_error_message()));return;}
			update_post_meta($id,\Sabri\HomeNewsFeed\Phase4Contracts::WORKFLOW_META_KEY,'published');
			$seed_ids[]=(int)$id;
		}

		$values=array();
		for($i=24;$i<10000;$i++){
			$slug='phase5-load-bulk-'.$i;
			$title='Phase 5 Load Story '.$i;
			$now=gmdate('Y-m-d H:i:s',time()-DAY_IN_SECONDS);
			$values[]=$wpdb->prepare("(%d,'publish','sabri_editorial_news',%s,%s,%s,%s,%s,%s,%s,%s)",$admin,$title,$slug,'Load summary','Load body',$now,$now,$now,$now);
			if(count($values)===250){
				$wpdb->query("INSERT INTO {$wpdb->posts} (post_author,post_status,post_type,post_title,post_name,post_excerpt,post_content,post_date,post_date_gmt,post_modified,post_modified_gmt) VALUES ".implode(',',$values));
				$values=array();
			}
		}
		if($values){
			$wpdb->query("INSERT INTO {$wpdb->posts} (post_author,post_status,post_type,post_title,post_name,post_excerpt,post_content,post_date,post_date_gmt,post_modified,post_modified_gmt) VALUES ".implode(',',$values));
		}
		$bulk_ids=$wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_name LIKE 'phase5-load-bulk-%'");
		foreach(array_chunk($bulk_ids,250)as$chunk){
			$meta=array();
			foreach($chunk as$id){$meta[]=$wpdb->prepare('(%d,%s,%s)',$id,\Sabri\HomeNewsFeed\Phase4Contracts::WORKFLOW_META_KEY,'published');}
			$wpdb->query("INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value) VALUES ".implode(',',$meta));
		}
		$ids=array_merge($seed_ids,array_map('intval',$bulk_ids));
		clean_post_cache($seed_ids[0]);
		$insert_ms=(int)round((microtime(true)-$start)*1000);
		$query_start=microtime(true);
		$result=\Sabri\HomeNewsFeed\NewsQueryService::query(array('per_page'=>24,'page'=>1));
		$query_ms=(int)round((microtime(true)-$query_start)*1000);
		$audit=\Sabri\HomeNewsFeed\Phase5Performance::audit();
		$count=count($ids);
		foreach(array_chunk($ids,250)as$chunk){
			$safe_ids=implode(',',array_map('absint',$chunk));
			if($safe_ids){
				$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($safe_ids)");
				$wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID IN ($safe_ids)");
			}
		}
		echo wp_json_encode(array(
			'count'=>$count,
			'insert_ms'=>$insert_ms,
			'query_ms'=>$query_ms,
			'query_success'=>!empty($result['success']),
			'query_code'=>isset($result['code'])?$result['code']:'',
			'items'=>!empty($result['data']['items'])?count($result['data']['items']):0,
			'total'=>isset($result['data']['total'])?(int)$result['data']['total']:0,
			'audit'=>$audit,
		));
	`));
	if(result.error)throw new Error(result.error);
	if(result.count!==10000)throw new Error(`Expected 10000 records, got ${result.count}`);
	if(!result.query_success||result.items!==24)throw new Error(`Bounded public query failed under load: ${JSON.stringify(result)}`);
	if(result.query_ms>5000)throw new Error(`Public query exceeded 5000ms: ${result.query_ms}`);
	if(!result.audit.success)throw new Error(`Performance/schema audit failed: ${JSON.stringify(result.audit)}`);
	console.log(`Phase 5 10,000-record performance test passed: insert ${result.insert_ms}ms, query ${result.query_ms}ms.`);
} finally {
	if(server&&typeof server[Symbol.asyncDispose]==='function')await server[Symbol.asyncDispose]();
}
