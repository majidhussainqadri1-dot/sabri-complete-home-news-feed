import dns from 'node:dns';
import path from 'node:path';
import process from 'node:process';
import { runCLI } from '@wp-playground/cli';

dns.setDefaultResultOrder('ipv4first');
const phpVersion = process.env.SABRI_PLAYGROUND_PHP || '8.3';
const wpVersion = process.env.SABRI_PLAYGROUND_WP || 'latest';
const packagePath = process.env.SABRI_PLUGIN_ZIP || '';
const pluginPath = 'sabri-complete-home-news-feed/sabri-complete-home-news-feed.php';
const virtualZip = '/tmp/sabri-phase5-candidate.zip';
let server;
function assert(condition, message) { if (!condition) throw new Error(message); }
async function php(code) {
	const response = await server.playground.run({ code: `<?php require '/wordpress/wp-load.php'; ${code}` });
	if (response.errors && String(response.errors).trim()) throw new Error(`PHP error: ${response.errors}`);
	return String(response.text || '').trim();
}
try {
	const options = { command: 'server', php: phpVersion, wp: wpVersion, debug: true, login: false };
	if (packagePath) options.mount = [{ hostPath: path.resolve(packagePath), vfsPath: virtualZip }];
	else {
		options.mount = [{ hostPath: path.resolve('.'), vfsPath: '/wordpress/wp-content/plugins/sabri-complete-home-news-feed' }];
		options.blueprint = { steps: [{ step: 'activatePlugin', pluginPath: `/wordpress/wp-content/plugins/${pluginPath}` }] };
	}
	server = await runCLI(options);
	if (packagePath) {
		const installed = JSON.parse(await php(`
			require_once ABSPATH . 'wp-admin/includes/plugin.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			call_user_func('WP_' . 'Filesystem'); $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin()); $result = $upgrader->install('${virtualZip}');
			if (is_wp_error($result)) { echo wp_json_encode(array('error'=>$result->get_error_message())); return; }
			$activation=activate_plugin('${pluginPath}','','',false); echo wp_json_encode(array('error'=>is_wp_error($activation)?$activation->get_error_message():'','active'=>is_plugin_active('${pluginPath}')));
		`));
		assert(!installed.error && installed.active, `Packaged activation failed: ${JSON.stringify(installed)}`);
	}
	const result = JSON.parse(await php(String.raw`
		$admin_id = username_exists('phase5admin');
		if (!$admin_id) { $admin_id = wp_create_user('phase5admin','Phase5-Strong-Pass-123!','phase5admin@example.test'); $user = new WP_User($admin_id); $user->set_role('administrator'); }
		wp_set_current_user($admin_id);
		\Sabri\HomeNewsFeed\NewsCapabilities::apply_default_policy();
		\Sabri\HomeNewsFeed\Phase5Capabilities::apply_default_policy();
		$phase4 = \Sabri\HomeNewsFeed\NewsFeatureSettings::defaults(); $phase4['editorial_news_enabled']=1; update_option(\Sabri\HomeNewsFeed\NewsFeatureSettings::OPTION_NAME,$phase4,false);
		$phase5 = \Sabri\HomeNewsFeed\Phase5FeatureSettings::defaults(); foreach(array_keys($phase5) as $key){$phase5[$key]=1;} update_option(\Sabri\HomeNewsFeed\Phase5FeatureSettings::OPTION_NAME,$phase5,false);
		$migration = \Sabri\HomeNewsFeed\Phase5Migrations::migrate(true);
		$article_id = wp_insert_post(array('post_type'=>\Sabri\HomeNewsFeed\Phase4Contracts::POST_TYPE,'post_status'=>'publish','post_title'=>'Phase 5 Integrated Story','post_name'=>'phase5-integrated-story','post_excerpt'=>'Phase 5 summary','post_content'=>'<p>Integrated public article body.</p>','post_author'=>$admin_id));
		update_post_meta($article_id,\Sabri\HomeNewsFeed\Phase4Contracts::WORKFLOW_META_KEY,'published');
		\Sabri\HomeNewsFeed\NewsPublicSnapshot::capture($article_id,true);
		$source = \Sabri\HomeNewsFeed\SourceRegistry::create($article_id,array('source_type'=>'official-dataset','evidence_class'=>'primary','title'=>'Official Phase 5 Dataset','publisher'=>'Sabri Test Institution','public_url'=>'https://example.test/dataset','public_citation'=>'Official Phase 5 Dataset.'));
		$source_verify = !empty($source['success']) ? \Sabri\HomeNewsFeed\SourceRegistry::verify($source['data']['id'],'verified') : array('success'=>false);
		$editorial = \Sabri\HomeNewsFeed\ReviewLedger::assign($article_id,0,'editorial',$admin_id);
		$fact = \Sabri\HomeNewsFeed\ReviewLedger::assign($article_id,0,'fact-check',$admin_id);
		$editorial_decision = !empty($editorial['success']) ? \Sabri\HomeNewsFeed\ReviewLedger::decide($editorial['data']['id'],'approved') : array('success'=>false);
		$fact_decision = !empty($fact['success']) ? \Sabri\HomeNewsFeed\ReviewLedger::decide($fact['data']['id'],'approved') : array('success'=>false);
		$eligible = \Sabri\HomeNewsFeed\Phase5PublicationPolicy::eligible($article_id,'published');
		$submission = \Sabri\HomeNewsFeed\SubmissionService::create(array('title'=>'Doctor submission','summary'=>'Submission summary','body'=>str_repeat('Safe editorial submission body ',5),'source_urls'=>array('https://example.test/source'),'declarations'=>array('owns_text'=>1,'patient_identifiers_absent'=>1,'conflicts_declared'=>1)));
		$submitted = !empty($submission['success']) ? \Sabri\HomeNewsFeed\SubmissionService::transition($submission['data']['id'],'submitted') : array('success'=>false);
		$assessment = !empty($submitted['success']) ? \Sabri\HomeNewsFeed\SubmissionService::transition($submission['data']['id'],'under-assessment') : array('success'=>false);
		$accepted = !empty($assessment['success']) ? \Sabri\HomeNewsFeed\SubmissionService::transition($submission['data']['id'],'accepted') : array('success'=>false);
		$converted = !empty($accepted['success']) ? \Sabri\HomeNewsFeed\SubmissionService::convert_to_article($submission['data']['id']) : array('success'=>false);
		$start=gmdate('Y-m-d H:i:s'); $end=gmdate('Y-m-d H:i:s',time()+600); $breaking=\Sabri\HomeNewsFeed\BreakingNewsService::schedule($article_id,$start,$end,10); $breaking_public=\Sabri\HomeNewsFeed\BreakingNewsService::active_public();
		$correction=\Sabri\HomeNewsFeed\CorrectionLedger::request($article_id,'clarification',array('private_reason'=>'Clarify a test statement.','affected_claim'=>'Test statement.'));
		$approved=!empty($correction['success'])?\Sabri\HomeNewsFeed\CorrectionLedger::approve($correction['data']['id'],array('public_note'=>'A test clarification was added.')):array('success'=>false);
		$published=!empty($approved['success'])?\Sabri\HomeNewsFeed\CorrectionLedger::publish($correction['data']['id']):array('success'=>false);
		$history=\Sabri\HomeNewsFeed\CorrectionLedger::public_history($article_id);
		$translation_id=wp_insert_post(array('post_type'=>\Sabri\HomeNewsFeed\Phase4Contracts::POST_TYPE,'post_status'=>'draft','post_title'=>'Phase 5 Urdu Translation','post_name'=>'phase5-urdu-translation','post_content'=>'<p>Translation draft.</p>','post_author'=>$admin_id));
		$translation=\Sabri\HomeNewsFeed\TranslationService::link($translation_id,$article_id,'ur-PK',$admin_id,0);
		$preview=\Sabri\HomeNewsFeed\PreviewTokenService::issue($article_id,600);
		$preview_valid=!empty($preview['success'])?\Sabri\HomeNewsFeed\PreviewTokenService::validate($article_id,$preview['data']['token']):false;
		\Sabri\HomeNewsFeed\NewsDistribution::rewrite_rules(); flush_rewrite_rules(false); global $wp_rewrite; $rules=$wp_rewrite->wp_rewrite_rules();
		$diagnostics=\Sabri\HomeNewsFeed\Phase5Diagnostics::report();
		$before=get_post($article_id)?1:0; deactivate_plugins('${pluginPath}',true); $after=get_post($article_id)?1:0; $reactivation=activate_plugin('${pluginPath}','','',false); $after_reactivation=get_post($article_id)?1:0;
		echo wp_json_encode(array('migration'=>$migration,'source'=>$source,'source_verify'=>$source_verify,'editorial_decision'=>$editorial_decision,'fact_decision'=>$fact_decision,'eligible'=>$eligible,'submission'=>$submission,'converted'=>$converted,'breaking'=>$breaking,'breaking_public_count'=>count($breaking_public),'published_correction'=>$published,'history_count'=>count($history),'translation'=>$translation,'preview_valid'=>$preview_valid,'has_feed'=>isset($rules['^news/feed/?$']),'has_section_feed'=>isset($rules['^news/section/([a-z0-9]+(?:-[a-z0-9]+)*)/feed/?$']),'has_sitemap'=>isset($rules['^news-sitemap\.xml$']),'diagnostics'=>$diagnostics,'before'=>$before,'after'=>$after,'after_reactivation'=>$after_reactivation,'reactivation_error'=>is_wp_error($reactivation)?$reactivation->get_error_message():'','version'=>SABRI_HNF_VERSION,'schema'=>SABRI_HNF_SCHEMA_VERSION,'checkpoint'=>\Sabri\HomeNewsFeed\Phase4Contracts::CHECKPOINT));
	`));
	assert(result.version === '1.0.0' && result.schema === '1.0.0' && result.checkpoint === '4A', 'Frozen versions/checkpoint changed.');
	assert(result.migration.success, 'Phase 5 migration failed.');
	assert(result.source.success && result.source_verify.success, 'Source registry lifecycle failed.');
	assert(result.editorial_decision.success && result.fact_decision.success && result.eligible === true, 'Review/publication prerequisite lifecycle failed.');
	assert(result.submission.success && result.converted.success && result.converted.data.article_id > 0, 'Submission lifecycle failed.');
	assert(result.breaking.success && result.breaking_public_count === 1, 'Breaking News lifecycle failed.');
	assert(result.published_correction.success && result.history_count === 1, 'Correction lifecycle failed.');
	assert(result.translation.success, 'Translation relationship failed.');
	assert(result.preview_valid, 'Preview token validation failed.');
	assert(result.has_feed && result.has_section_feed && result.has_sitemap, 'Distribution rewrite routes failed.');
	assert(result.diagnostics.schema.missing_tables.length === 0 && result.diagnostics.schema.missing_indexes.length === 0, 'Diagnostics found schema defects.');
	assert(result.before === 1 && result.after === 1 && result.after_reactivation === 1 && !result.reactivation_error, 'Deactivation/reactivation did not preserve data.');
	console.log(`Phase 5 ${packagePath ? 'packaged' : 'source'} WordPress tests passed on WordPress ${wpVersion} / PHP ${phpVersion}.`);
} finally {
	if (server && typeof server[Symbol.asyncDispose] === 'function') await server[Symbol.asyncDispose]();
}
