import path from 'node:path';
import process from 'node:process';
import { runCLI } from '@wp-playground/cli';

const phpVersion = process.env.SABRI_PLAYGROUND_PHP || '8.3';
const wpVersion = process.env.SABRI_PLAYGROUND_WP || 'latest';
const pluginPath = 'sabri-complete-home-news-feed/sabri-complete-home-news-feed.php';
let server;

function assert(condition, message) {
	if (!condition) throw new Error(message);
}

async function php(code) {
	const response = await server.playground.run({ code: `<?php require '/wordpress/wp-load.php'; ${code}` });
	if (response.errors && String(response.errors).trim()) throw new Error(`PHP error: ${response.errors}`);
	return String(response.text || '').trim();
}

function header(headers, name) {
	const key = Object.keys(headers || {}).find((item) => item.toLowerCase() === name.toLowerCase());
	const value = key ? headers[key] : '';
	return Array.isArray(value) ? String(value[0] || '') : String(value || '');
}

function redirectPath(location, current) {
	const url = new URL(location, new URL(current, 'http://playground.test'));
	return `${url.pathname}${url.search}`;
}

async function get(url, remaining = 8, redirects = []) {
	const response = await server.playground.request({ url, method: 'GET' });
	const status = Number(response.httpStatusCode || 0);
	const location = header(response.headers, 'location');
	const chain = [...redirects, { url, status, location }];
	if (status >= 300 && status < 400 && location) {
		assert(remaining > 0, `Redirect limit exceeded: ${JSON.stringify(chain)}`);
		const next = redirectPath(location, url);
		assert(next !== url, `Self redirect: ${JSON.stringify(chain)}`);
		return get(next, remaining - 1, chain);
	}
	if (response.errors && String(response.errors).trim()) throw new Error(`Frontend PHP error at ${url}: ${response.errors}`);
	return { status, url, body: String(response.text || ''), redirects: chain };
}

function summary(response) {
	const title = (response.body.match(/<title[^>]*>([^<]*)<\/title>/i) || [])[1] || '';
	const text = response.body.replace(/<script[\s\S]*?<\/script>/gi, ' ').replace(/<style[\s\S]*?<\/style>/gi, ' ').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
	return { status: response.status, title, snippet: text.slice(0, 220), redirects: response.redirects };
}

async function lastQueryDiagnostic() {
	const raw = await php(`echo wp_json_encode(get_option('sabri_hnf_test_last_query', array()));`);
	try { return JSON.parse(raw || '{}'); } catch { return { raw }; }
}

async function page(url, sentinel, label) {
	const response = await get(url);
	assert(response.status === 200, `${label} HTTP ${response.status}: ${JSON.stringify(summary(response))}`);
	assert(response.body.includes(sentinel), `${label} missing sentinel: ${JSON.stringify(summary(response))}`);
	assert(!/page not found/i.test(response.body), `${label} rendered 404 body.`);
	return response;
}

async function enableFeaturesAndScheduleRewriteRepair() {
	const output = await php(`
		$features = \\Sabri\\HomeNewsFeed\\Phase3FeatureSettings::defaults();
		foreach ( $features as $key => $unused ) { $features[ $key ] = 1; }
		update_option( \\Sabri\\HomeNewsFeed\\Phase3FeatureSettings::OPTION_NAME, $features, false );
		$settings = \\Sabri\\HomeNewsFeed\\Settings::get();
		$settings['general']['enabled'] = 1;
		$settings['feed']['enabled'] = 1;
		$settings['advanced']['emergency_disabled'] = 0;
		update_option( \\Sabri\\HomeNewsFeed\\Settings::OPTION_NAME, $settings, false );
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( \\Sabri\\HomeNewsFeed\\RewriteRules::FLUSH_OPTION, 1, false );
		echo 'scheduled';
	`);
	assert(output.includes('scheduled'), 'Feature enable and rewrite scheduling step failed.');
}

async function runRewriteRepairRequest(label) {
	const response = await get('/');
	assert(response.status === 200, `${label} bootstrap request failed: ${JSON.stringify(summary(response))}`);
	const state = JSON.parse(await php(`
		$rules = get_option( 'rewrite_rules', array() );
		echo wp_json_encode( array(
			'flush_pending' => (int) get_option( \\Sabri\\HomeNewsFeed\\RewriteRules::FLUSH_OPTION, 0 ),
			'rule_count' => is_array( $rules ) ? count( $rules ) : 0,
			'has_post_rule' => is_array( $rules ) && array_key_exists( '([^/]+)(?:/([0-9]+))?/?$', $rules ),
		) );
	`));
	assert(state.flush_pending === 0, `${label} did not clear the scheduled rewrite flag: ${JSON.stringify(state)}`);
	assert(state.rule_count > 0 && state.has_post_rule, `${label} did not create a complete pretty-permalink map: ${JSON.stringify(state)}`);
}

try {
	server = await runCLI({
		command: 'server', php: phpVersion, wp: wpVersion, debug: true, login: false,
		mount: [{ hostPath: path.resolve('.'), vfsPath: '/wordpress/wp-content/plugins/sabri-complete-home-news-feed' }],
		blueprint: { steps: [{ step: 'activatePlugin', pluginPath: `/wordpress/wp-content/plugins/${pluginPath}` }] },
	});

	await server.playground.mkdir('/wordpress/wp-content/mu-plugins');
	await server.playground.writeFile('/wordpress/wp-content/mu-plugins/sabri-hnf-query-diagnostic.php', `<?php
add_action( 'wp', static function () {
	global $wp, $wp_query;
	$posts = array();
	foreach ( (array) $wp_query->posts as $post ) {
		$posts[] = array(
			'ID' => isset( $post->ID ) ? (int) $post->ID : 0,
			'post_type' => isset( $post->post_type ) ? (string) $post->post_type : '',
			'post_name' => isset( $post->post_name ) ? (string) $post->post_name : '',
		);
	}
	update_option( 'sabri_hnf_test_last_query', array(
		'request' => isset( $wp->request ) ? (string) $wp->request : '',
		'matched_rule' => isset( $wp->matched_rule ) ? (string) $wp->matched_rule : '',
		'matched_query' => isset( $wp->matched_query ) ? (string) $wp->matched_query : '',
		'query_vars' => is_array( $wp_query->query_vars ) ? $wp_query->query_vars : array(),
		'is_404' => $wp_query->is_404(),
		'is_home' => $wp_query->is_home(),
		'is_single' => $wp_query->is_single(),
		'is_page' => $wp_query->is_page(),
		'posts' => $posts,
	), false );
}, 999 );
`);

	const setup = JSON.parse(await php(`
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$sample = get_page_by_path( 'sample-page', OBJECT, 'page' );
		$sample_id = $sample ? (int) $sample->ID : wp_insert_post( array( 'post_type'=>'page', 'post_status'=>'publish', 'post_title'=>'Sample Page', 'post_name'=>'sample-page' ) );
		wp_update_post( array( 'ID'=>$sample_id, 'post_status'=>'publish', 'post_content'=>'SABRI_SAMPLE_PAGE_ROUTE_OK' ) );
		$short = get_page_by_path( 'phase-3-playground-test', OBJECT, 'page' );
		$short_id = $short ? (int) $short->ID : wp_insert_post( array( 'post_type'=>'page', 'post_status'=>'publish', 'post_title'=>'Phase 3 Playground Test', 'post_name'=>'phase-3-playground-test' ) );
		wp_update_post( array( 'ID'=>$short_id, 'post_status'=>'publish', 'post_content'=>'[sabri_complete_home_feed]' ) );
		$post = get_page_by_path( 'sabri-direct-post-test', OBJECT, 'post' );
		$post_id = $post ? (int) $post->ID : wp_insert_post( array( 'post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'Sabri Direct Post Test', 'post_name'=>'sabri-direct-post-test', 'post_author'=>1 ) );
		wp_update_post( array( 'ID'=>$post_id, 'post_status'=>'publish', 'post_content'=>'SABRI_DIRECT_POST_ROUTE_OK' ) );
		\\Sabri\\HomeNewsFeed\\PostMetadata::save_for_post( $post_id, array( 'feed_type'=>'standard-post', 'visibility'=>'public', 'review_state'=>'approved', 'comments_enabled'=>1 ) );
		echo wp_json_encode( array(
			'sample_id'=>$sample_id,
			'active'=>is_plugin_active('${pluginPath}'),
			'legacy_pre_query'=>has_action('pre_get_posts', array(\\Sabri\\HomeNewsFeed\\PostMetadata::class,'filter_public_queries')),
			'followers_pre_query'=>has_action('pre_get_posts', array(\\Sabri\\HomeNewsFeed\\FollowersVisibility::class,'extend_post_queries')),
			'public_pre_query'=>has_action('pre_get_posts', array(\\Sabri\\HomeNewsFeed\\PublicQueryGuard::class,'filter_public_queries')),
			'result_filter'=>has_filter('the_posts', array(\\Sabri\\HomeNewsFeed\\PublicQueryGuard::class,'filter_public_post_results')),
			'rewrite_repair'=>has_action('init', array(\\Sabri\\HomeNewsFeed\\RewriteRules::class,'flush_scheduled'))
		) );
	`));
	assert(setup.active, 'Plugin was not active after Blueprint activation.');
	assert(setup.legacy_pre_query === false, `Legacy pre_get_posts hook remains: ${JSON.stringify(setup)}`);
	assert(setup.followers_pre_query === false, `Followers pre_get_posts hook remains: ${JSON.stringify(setup)}`);
	assert(setup.public_pre_query === false, `Replacement pre_get_posts hook remains: ${JSON.stringify(setup)}`);
	assert(setup.result_filter !== false, `Resolved-result filter missing: ${JSON.stringify(setup)}`);
	assert(setup.rewrite_repair !== false, `Late-init rewrite repair hook missing: ${JSON.stringify(setup)}`);

	await enableFeaturesAndScheduleRewriteRepair();
	await runRewriteRepairRequest('Initial activation');
	await page('/sample-page/', 'SABRI_SAMPLE_PAGE_ROUTE_OK', 'Pretty Sample Page');
	await page(`/?page_id=${setup.sample_id}`, 'SABRI_SAMPLE_PAGE_ROUTE_OK', 'Plain Sample Page');
	const shortcode = await page('/phase-3-playground-test/', 'Home Feed', 'Shortcode Page');
	assert(shortcode.body.includes('class="sabri-hnf-feed"') && !shortcode.body.includes('[sabri_complete_home_feed]'), 'Shortcode did not render correctly.');
	const direct = await page('/sabri-direct-post-test/', 'SABRI_DIRECT_POST_ROUTE_OK', 'Direct Post');
	assert(!direct.body.includes('class="sabri-hnf-feed"'), 'Direct Post rendered Home Feed.');
	await php(`delete_option('sabri_hnf_test_last_query');`);
	const missingActive = await get('/sabri-route-that-must-not-exist/');
	const activeDiagnostic = await lastQueryDiagnostic();

	assert((await php(`require_once ABSPATH.'wp-admin/includes/plugin.php'; deactivate_plugins('${pluginPath}',true); echo is_plugin_active('${pluginPath}')?'active':'inactive';`)).includes('inactive'), 'Deactivation failed.');
	await page('/sample-page/', 'SABRI_SAMPLE_PAGE_ROUTE_OK', 'Sample Page after deactivation');
	const inactiveShort = await page('/phase-3-playground-test/', '[sabri_complete_home_feed]', 'Shortcode after deactivation');
	assert(!inactiveShort.body.includes('class="sabri-hnf-feed"'), 'Deactivated plugin still rendered feed.');
	await php(`delete_option('sabri_hnf_test_last_query');`);
	const missingInactive = await get('/sabri-route-that-must-not-exist/');
	const inactiveDiagnostic = await lastQueryDiagnostic();
	const diagnostic = `active=${JSON.stringify(summary(missingActive))}, active_query=${JSON.stringify(activeDiagnostic)}, inactive=${JSON.stringify(summary(missingInactive))}, inactive_query=${JSON.stringify(inactiveDiagnostic)}`;
	assert(missingActive.status === missingInactive.status, `Unknown-route status changed: ${diagnostic}`);
	assert(/page not found|not found|nothing here/i.test(missingActive.body) === /page not found|not found|nothing here/i.test(missingInactive.body), `Unknown-route body semantics changed: ${diagnostic}`);

	const reactivation = await php(`require_once ABSPATH.'wp-admin/includes/plugin.php'; $r=activate_plugin('${pluginPath}','','',true); echo is_wp_error($r)?$r->get_error_message():(is_plugin_active('${pluginPath}')?'active':'inactive');`);
	assert(reactivation.includes('active'), `Reactivation failed: ${reactivation}`);
	await enableFeaturesAndScheduleRewriteRepair();
	await runRewriteRepairRequest('Reactivation');
	await page('/sample-page/', 'SABRI_SAMPLE_PAGE_ROUTE_OK', 'Sample Page after reactivation');
	const activeAgain = await page('/phase-3-playground-test/', 'Home Feed', 'Shortcode after reactivation');
	assert(activeAgain.body.includes('class="sabri-hnf-feed"'), 'Reactivated feed missing.');
	await page('/sabri-direct-post-test/', 'SABRI_DIRECT_POST_ROUTE_OK', 'Direct Post after reactivation');
	const missingAgain = await get('/sabri-route-that-must-not-exist/');
	assert(missingAgain.status === missingInactive.status, `Reactivation changed unknown route: ${JSON.stringify(summary(missingAgain))}`);

	console.log(`Playground integration tests passed on WordPress ${wpVersion} / PHP ${phpVersion}.`);
} finally {
	if (server && typeof server[Symbol.asyncDispose] === 'function') await server[Symbol.asyncDispose]();
}
