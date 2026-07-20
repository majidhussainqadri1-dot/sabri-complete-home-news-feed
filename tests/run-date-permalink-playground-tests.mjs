import path from 'node:path';
import process from 'node:process';
import { runCLI } from '@wp-playground/cli';

const phpVersion = process.env.SABRI_PLAYGROUND_PHP || '8.3';
const wpVersion = process.env.SABRI_PLAYGROUND_WP || 'latest';
const packagePath = process.env.SABRI_PLUGIN_ZIP;
const virtualZip = '/tmp/sabri-home-news-feed-candidate.zip';
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

function firstHeader(headers, name) {
	const key = Object.keys(headers || {}).find((item) => item.toLowerCase() === name.toLowerCase());
	const value = key ? headers[key] : '';
	return Array.isArray(value) ? String(value[0] || '') : String(value || '');
}

async function request(url, remaining = 8) {
	const response = await server.playground.request({ url, method: 'GET' });
	const status = Number(response.httpStatusCode || 0);
	const location = firstHeader(response.headers, 'location');
	if (status >= 300 && status < 400 && location) {
		assert(remaining > 0, `Redirect limit exceeded at ${url}`);
		const next = new URL(location, new URL(url, 'http://playground.test'));
		return request(`${next.pathname}${next.search}`, remaining - 1);
	}
	if (response.errors && String(response.errors).trim()) throw new Error(`Frontend PHP error at ${url}: ${response.errors}`);
	return { status, body: String(response.text || '') };
}

async function assertPage(url, sentinel, label) {
	const response = await request(url);
	assert(response.status === 200, `${label} returned HTTP ${response.status}`);
	assert(response.body.includes(sentinel), `${label} did not contain ${sentinel}`);
	assert(!/page not found/i.test(response.body), `${label} rendered the theme 404 page`);
	return response;
}

assert(packagePath, 'SABRI_PLUGIN_ZIP was not provided.');

try {
	server = await runCLI({
		command: 'server', php: phpVersion, wp: wpVersion, debug: true, login: false,
		mount: [{ hostPath: path.resolve(packagePath), vfsPath: virtualZip }],
	});

	const install = JSON.parse(await php(`
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		call_user_func( 'WP_' . 'Filesystem' );
		$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
		$result = $upgrader->install( '${virtualZip}' );
		if ( is_wp_error( $result ) ) { echo wp_json_encode( array( 'error' => $result->get_error_message() ) ); return; }
		$activation = activate_plugin( '${pluginPath}', '', false, false );
		if ( is_wp_error( $activation ) ) { echo wp_json_encode( array( 'error' => $activation->get_error_message() ) ); return; }
		echo wp_json_encode( array( 'active' => is_plugin_active( '${pluginPath}' ) ) );
	`));
	assert(!install.error && install.active, `Installation or activation failed: ${JSON.stringify(install)}`);

	const setup = JSON.parse(await php(`
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
		$features = \\Sabri\\HomeNewsFeed\\Phase3FeatureSettings::defaults();
		foreach ( $features as $key => $unused ) { $features[ $key ] = 1; }
		update_option( \\Sabri\\HomeNewsFeed\\Phase3FeatureSettings::OPTION_NAME, $features, false );
		$settings = \\Sabri\\HomeNewsFeed\\Settings::get();
		$settings['general']['enabled'] = 1;
		$settings['feed']['enabled'] = 1;
		$settings['advanced']['emergency_disabled'] = 0;
		update_option( \\Sabri\\HomeNewsFeed\\Settings::OPTION_NAME, $settings, false );
		$post = get_page_by_path( 'hello-world', OBJECT, 'post' );
		$post_id = $post ? (int) $post->ID : wp_insert_post( array( 'post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'Hello world!', 'post_name'=>'hello-world', 'post_author'=>1 ) );
		wp_update_post( array(
			'ID'=>$post_id,
			'post_status'=>'publish',
			'post_date'=>'2026-07-09 12:00:00',
			'post_date_gmt'=>'2026-07-09 12:00:00',
			'post_content'=>'SABRI_DATE_PERMALINK_POST_OK'
		) );
		\\Sabri\\HomeNewsFeed\\PostMetadata::save_for_post( $post_id, array( 'feed_type'=>'standard-post', 'visibility'=>'public', 'review_state'=>'approved', 'comments_enabled'=>1 ) );
		$page_id = wp_insert_post( array( 'post_type'=>'page', 'post_status'=>'publish', 'post_title'=>'Date Permalink Sample Page', 'post_name'=>'date-permalink-sample-page', 'post_content'=>'SABRI_DATE_PERMALINK_PAGE_OK' ) );
		update_option( \\Sabri\\HomeNewsFeed\\RewriteRules::FLUSH_OPTION, 1, false );
		echo wp_json_encode( array( 'post_id'=>$post_id, 'page_id'=>$page_id ) );
	`));
	assert(setup.post_id > 0 && setup.page_id > 0, `Fixture creation failed: ${JSON.stringify(setup)}`);

	const bootstrap = await request('/');
	assert(bootstrap.status === 200, 'Late-init rewrite repair bootstrap failed.');
	const rewriteState = JSON.parse(await php(`
		$rules = get_option( 'rewrite_rules', array() );
		$found = false;
		foreach ( array_keys( is_array( $rules ) ? $rules : array() ) as $rule ) {
			if ( false !== strpos( $rule, '([0-9]{4})' ) && false !== strpos( $rule, '([0-9]{1,2})' ) ) { $found = true; break; }
		}
		echo wp_json_encode( array( 'pending'=>(int)get_option( \\Sabri\\HomeNewsFeed\\RewriteRules::FLUSH_OPTION, 0 ), 'count'=>is_array($rules)?count($rules):0, 'has_date_rule'=>$found ) );
	`));
	assert(rewriteState.pending === 0 && rewriteState.count > 0 && rewriteState.has_date_rule, `Date rewrite map was not built: ${JSON.stringify(rewriteState)}`);

	await assertPage('/2026/07/09/hello-world/', 'SABRI_DATE_PERMALINK_POST_OK', 'Date-based Hello World permalink');
	await assertPage('/date-permalink-sample-page/', 'SABRI_DATE_PERMALINK_PAGE_OK', 'Page under date permalink structure');
	await assertPage(`/?page_id=${setup.page_id}`, 'SABRI_DATE_PERMALINK_PAGE_OK', 'Plain Page under date permalink structure');
	const missing = await request('/2026/07/09/route-that-does-not-exist/');
	assert(missing.status === 404 && /page not found|not found|nothing here/i.test(missing.body), 'Missing dated route did not remain 404.');

	const deactivated = await php(`require_once ABSPATH.'wp-admin/includes/plugin.php'; deactivate_plugins('${pluginPath}',true); echo is_plugin_active('${pluginPath}')?'active':'inactive';`);
	assert(deactivated.includes('inactive'), 'Deactivation failed.');
	await assertPage('/2026/07/09/hello-world/', 'SABRI_DATE_PERMALINK_POST_OK', 'Date-based permalink after deactivation');
	await assertPage('/date-permalink-sample-page/', 'SABRI_DATE_PERMALINK_PAGE_OK', 'Page after deactivation');

	const reactivated = await php(`require_once ABSPATH.'wp-admin/includes/plugin.php'; $r=activate_plugin('${pluginPath}','','',false); echo is_wp_error($r)?$r->get_error_message():(is_plugin_active('${pluginPath}')?'active':'inactive');`);
	assert(reactivated.includes('active'), `Reactivation failed: ${reactivated}`);
	const bootstrapAgain = await request('/');
	assert(bootstrapAgain.status === 200, 'Reactivation bootstrap failed.');
	await assertPage('/2026/07/09/hello-world/', 'SABRI_DATE_PERMALINK_POST_OK', 'Date-based permalink after reactivation');
	await assertPage('/date-permalink-sample-page/', 'SABRI_DATE_PERMALINK_PAGE_OK', 'Page after reactivation');

	console.log(`Date permalink packaged test passed on WordPress ${wpVersion} / PHP ${phpVersion}.`);
} finally {
	if (server && typeof server[Symbol.asyncDispose] === 'function') await server[Symbol.asyncDispose]();
}
