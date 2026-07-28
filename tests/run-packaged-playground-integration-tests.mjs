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

function header(headers, name) {
	const key = Object.keys(headers || {}).find((item) => item.toLowerCase() === name.toLowerCase());
	const value = key ? headers[key] : '';
	return Array.isArray(value) ? String(value[0] || '') : String(value || '');
}

async function get(url, remaining = 8) {
	const response = await server.playground.request({ url, method: 'GET' });
	const status = Number(response.httpStatusCode || 0);
	const location = header(response.headers, 'location');
	if (status >= 300 && status < 400 && location) {
		assert(remaining > 0, `Redirect limit exceeded at ${url}`);
		const next = new URL(location, new URL(url, 'http://playground.test'));
		return get(`${next.pathname}${next.search}`, remaining - 1);
	}
	if (response.errors && String(response.errors).trim()) throw new Error(`Frontend PHP error at ${url}: ${response.errors}`);
	return { status, body: String(response.text || '') };
}

async function page(url, sentinel, label) {
	const response = await get(url);
	assert(response.status === 200, `${label} returned HTTP ${response.status}`);
	assert(response.body.includes(sentinel), `${label} did not contain ${sentinel}`);
	assert(!/page not found/i.test(response.body), `${label} rendered a 404 body`);
	return response;
}

function assertCompleteSocialSurface(response, label) {
	[
		'sabri-hnf-action--like',
		'sabri-hnf-action--dislike',
		'sabri-hnf-action--comment',
		'sabri-hnf-action--save',
		'sabri-hnf-action--share',
		'data-sabri-share',
		'sabri-hnf-action--views',
		'class="sabri-hnf-comments"',
		'assets/js/share.js'
	].forEach((marker) => assert(response.body.includes(marker), `${label} missing ${marker}`));
}

async function scheduleAndRunRewriteRepair(label) {
	await php(`
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( \\Sabri\\HomeNewsFeed\\RewriteRules::FLUSH_OPTION, 1, false );
		echo 'scheduled';
	`);
	const bootstrap = await get('/');
	assert(bootstrap.status === 200, `${label} bootstrap request failed`);
	const state = JSON.parse(await php(`
		$rules = get_option( 'rewrite_rules', array() );
		echo wp_json_encode( array(
			'pending' => (int) get_option( \\Sabri\\HomeNewsFeed\\RewriteRules::FLUSH_OPTION, 0 ),
			'count' => is_array( $rules ) ? count( $rules ) : 0,
			'has_post_rule' => is_array( $rules ) && array_key_exists( '([^/]+)(?:/([0-9]+))?/?$', $rules ),
		) );
	`));
	assert(state.pending === 0 && state.count > 0 && state.has_post_rule, `${label} rewrite repair failed: ${JSON.stringify(state)}`);
}

assert(packagePath, 'SABRI_PLUGIN_ZIP was not provided.');

try {
	server = await runCLI({
		command: 'server',
		php: phpVersion,
		wp: wpVersion,
		debug: true,
		login: false,
		mount: [{ hostPath: path.resolve(packagePath), vfsPath: virtualZip }],
	});

	const install = JSON.parse(await php(`
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		call_user_func( 'WP_' . 'Filesystem' );
		$skin = new Automatic_Upgrader_Skin();
		$upgrader = new Plugin_Upgrader( $skin );
		$result = $upgrader->install( '${virtualZip}' );
		if ( is_wp_error( $result ) ) {
			echo wp_json_encode( array( 'error' => $result->get_error_message() ) );
			return;
		}
		$activation = activate_plugin( '${pluginPath}', '', false, false );
		if ( is_wp_error( $activation ) ) {
			echo wp_json_encode( array( 'error' => $activation->get_error_message() ) );
			return;
		}
		$schema = get_option( \\Sabri\\HomeNewsFeed\\Database::INSTALL_RESULT_OPTION, array() );
		$catalog = \\Sabri\\HomeNewsFeed\\Phase3FeatureSettings::catalog();
		echo wp_json_encode( array(
			'installed' => file_exists( WP_PLUGIN_DIR . '/${pluginPath}' ),
			'active' => is_plugin_active( '${pluginPath}' ),
			'version' => defined( 'SABRI_HNF_VERSION' ) ? SABRI_HNF_VERSION : '',
			'schema_version' => defined( 'SABRI_HNF_SCHEMA_VERSION' ) ? SABRI_HNF_SCHEMA_VERSION : '',
			'schema_success' => is_array( $schema ) && ! empty( $schema['success'] ),
			'schema_status' => is_array( $schema ) && isset( $schema['status'] ) ? $schema['status'] : '',
			'missing_tables' => is_array( $schema ) && isset( $schema['missing_tables'] ) ? $schema['missing_tables'] : array(),
			'social_view' => file_exists( WP_PLUGIN_DIR . '/sabri-complete-home-news-feed/admin/views/social-features.php' ),
			'share_script' => file_exists( WP_PLUGIN_DIR . '/sabri-complete-home-news-feed/assets/js/share.js' ),
			'catalog_complete' => isset( $catalog['share_enabled'], $catalog['comments_enabled'], $catalog['view_logging_enabled'], $catalog['reports_enabled'], $catalog['polls_enabled'] ),
		) );
	`));
	assert(!install.error && install.installed && install.active, `Packaged ZIP installation failed: ${JSON.stringify(install)}`);
	assert(install.version === '1.0.3', `Unexpected packaged plugin version: ${install.version}`);
	assert(install.schema_version === '1.0.0', `Unexpected packaged schema version: ${install.schema_version}`);
	assert(install.schema_success && install.schema_status === 'verified' && install.missing_tables.length === 0, `Packaged activation did not install and verify the schema: ${JSON.stringify(install)}`);
	assert(install.social_view && install.share_script && install.catalog_complete, `Packaged social controls are incomplete: ${JSON.stringify(install)}`);

	const setup = JSON.parse(await php(`
		$features = \\Sabri\\HomeNewsFeed\\Phase3FeatureSettings::defaults();
		foreach ( $features as $key => $unused ) { $features[ $key ] = 1; }
		update_option( \\Sabri\\HomeNewsFeed\\Phase3FeatureSettings::OPTION_NAME, $features, false );
		$settings = \\Sabri\\HomeNewsFeed\\Settings::get();
		$settings['general']['enabled'] = 1;
		$settings['feed']['enabled'] = 1;
		$settings['advanced']['emergency_disabled'] = 0;
		update_option( \\Sabri\\HomeNewsFeed\\Settings::OPTION_NAME, $settings, false );

		$sample_id = wp_insert_post( array( 'post_type'=>'page', 'post_status'=>'publish', 'post_title'=>'Package Sample Page', 'post_name'=>'package-sample-page', 'post_content'=>'SABRI_PACKAGE_SAMPLE_OK' ) );
		$shortcode_id = wp_insert_post( array( 'post_type'=>'page', 'post_status'=>'publish', 'post_title'=>'Package Shortcode Page', 'post_name'=>'package-shortcode-page', 'post_content'=>'[sabri_complete_home_feed]' ) );
		$post_id = wp_insert_post( array( 'post_type'=>'post', 'post_status'=>'publish', 'post_title'=>'Package Direct Post', 'post_name'=>'package-direct-post', 'post_content'=>'SABRI_PACKAGE_DIRECT_OK', 'post_author'=>1 ) );
		\\Sabri\\HomeNewsFeed\\PostMetadata::save_for_post( $post_id, array( 'feed_type'=>'standard-post', 'visibility'=>'public', 'review_state'=>'approved', 'comments_enabled'=>1 ) );
		echo wp_json_encode( array( 'sample_id'=>$sample_id, 'shortcode_id'=>$shortcode_id, 'post_id'=>$post_id ) );
	`));
	assert(setup.sample_id > 0 && setup.shortcode_id > 0 && setup.post_id > 0, `Package setup failed: ${JSON.stringify(setup)}`);

	await scheduleAndRunRewriteRepair('Packaged activation');
	await page('/package-sample-page/', 'SABRI_PACKAGE_SAMPLE_OK', 'Packaged pretty Page');
	await page(`/?page_id=${setup.sample_id}`, 'SABRI_PACKAGE_SAMPLE_OK', 'Packaged plain Page');
	const shortcode = await page('/package-shortcode-page/', 'Home Feed', 'Packaged shortcode Page');
	assert(shortcode.body.includes('class="sabri-hnf-feed"') && !shortcode.body.includes('[sabri_complete_home_feed]'), 'Packaged shortcode did not render the feed.');
	const direct = await page('/package-direct-post/', 'SABRI_PACKAGE_DIRECT_OK', 'Packaged direct Post');
	assert(!direct.body.includes('class="sabri-hnf-feed"'), 'Packaged direct Post rendered the Home Feed.');
	assertCompleteSocialSurface(direct, 'Packaged direct Post');
	const missingActive = await get('/packaged-route-must-not-exist/');
	assert(missingActive.status === 404 && /page not found|not found|nothing here/i.test(missingActive.body), 'Packaged plugin changed unknown-route semantics.');

	const deactivated = await php(`
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( '${pluginPath}', true );
		echo is_plugin_active( '${pluginPath}' ) ? 'active' : 'inactive';
	`);
	assert(deactivated.includes('inactive'), 'Packaged plugin deactivation failed.');
	await page('/package-sample-page/', 'SABRI_PACKAGE_SAMPLE_OK', 'Packaged Page after deactivation');
	const rawShortcode = await page('/package-shortcode-page/', '[sabri_complete_home_feed]', 'Packaged shortcode after deactivation');
	assert(!rawShortcode.body.includes('class="sabri-hnf-feed"'), 'Packaged plugin rendered after deactivation.');

	const reactivated = await php(`
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$result = activate_plugin( '${pluginPath}', '', false, false );
		echo is_wp_error( $result ) ? $result->get_error_message() : ( is_plugin_active( '${pluginPath}' ) ? 'active' : 'inactive' );
	`);
	assert(reactivated.includes('active'), `Packaged plugin reactivation failed: ${reactivated}`);
	await scheduleAndRunRewriteRepair('Packaged reactivation');
	await page('/package-sample-page/', 'SABRI_PACKAGE_SAMPLE_OK', 'Packaged Page after reactivation');
	const feedAgain = await page('/package-shortcode-page/', 'Home Feed', 'Packaged shortcode after reactivation');
	assert(feedAgain.body.includes('class="sabri-hnf-feed"'), 'Packaged feed missing after reactivation.');
	const directAgain = await page('/package-direct-post/', 'SABRI_PACKAGE_DIRECT_OK', 'Packaged direct Post after reactivation');
	assertCompleteSocialSurface(directAgain, 'Packaged direct Post after reactivation');
	assert((await get('/packaged-route-must-not-exist/')).status === 404, 'Packaged reactivation changed unknown-route status.');

	console.log(`Packaged ZIP Playground tests passed on WordPress ${wpVersion} / PHP ${phpVersion}.`);
} finally {
	if (server && typeof server[Symbol.asyncDispose] === 'function') await server[Symbol.asyncDispose]();
}
