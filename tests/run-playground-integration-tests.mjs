import path from 'node:path';
import process from 'node:process';
import { runCLI } from '@wp-playground/cli';

const phpVersion = process.env.SABRI_PLAYGROUND_PHP || '8.3';
const wpVersion = process.env.SABRI_PLAYGROUND_WP || 'latest';
const pluginPath = 'sabri-complete-home-news-feed/sabri-complete-home-news-feed.php';
let cliServer;

function assert(condition, message) {
	if (!condition) {
		throw new Error(message);
	}
}

async function runWordPressPhp(code) {
	const result = await cliServer.playground.run({
		code: `<?php require_once '/wordpress/wp-load.php'; ${code}`,
	});
	const text = typeof result.text === 'string' ? result.text : '';
	if (result.errors && String(result.errors).trim()) {
		throw new Error(`PHP runtime error: ${result.errors}`);
	}
	return text.trim();
}

function firstHeader(headers, name) {
	if (!headers || typeof headers !== 'object') {
		return '';
	}
	const key = Object.keys(headers).find((candidate) => candidate.toLowerCase() === name.toLowerCase());
	if (!key) {
		return '';
	}
	const value = headers[key];
	return Array.isArray(value) ? String(value[0] || '') : String(value || '');
}

function internalPath(location, currentPath) {
	try {
		const resolved = new URL(location, new URL(currentPath, 'http://playground.test'));
		return `${resolved.pathname}${resolved.search}`;
	} catch {
		return location;
	}
}

async function request(relativePath, redirectsRemaining = 8) {
	const response = await cliServer.playground.request({
		url: relativePath,
		method: 'GET',
		headers: { 'User-Agent': 'Sabri-Home-News-Feed-CI' },
	});
	const status = Number(response.httpStatusCode || 0);
	const location = firstHeader(response.headers, 'location');
	if (status >= 300 && status < 400 && location) {
		assert(redirectsRemaining > 0, `Internal redirect limit exceeded at ${relativePath} -> ${location}`);
		const nextPath = internalPath(location, relativePath);
		assert(nextPath !== relativePath, `Self-redirect detected at ${relativePath}`);
		return request(nextPath, redirectsRemaining - 1);
	}
	if (response.errors && String(response.errors).trim()) {
		throw new Error(`Frontend PHP runtime error at ${relativePath}: ${response.errors}`);
	}
	return {
		status,
		url: relativePath,
		body: typeof response.text === 'string' ? response.text : '',
		headers: response.headers || {},
	};
}

async function assertPage(relativePath, expectedText, label) {
	const response = await request(relativePath);
	assert(response.status === 200, `${label} returned HTTP ${response.status} at ${response.url}`);
	assert(response.body.includes(expectedText), `${label} did not contain its sentinel text.`);
	assert(!response.body.includes('Page not found'), `${label} rendered the theme 404 message.`);
	return response;
}

async function enableAllFeaturesAndFlush() {
	const output = await runWordPressPhp(`
		$features = \\Sabri\\HomeNewsFeed\\Phase3FeatureSettings::defaults();
		foreach ( $features as $key => $value ) { $features[ $key ] = 1; }
		update_option( \\Sabri\\HomeNewsFeed\\Phase3FeatureSettings::OPTION_NAME, $features, false );
		$settings = \\Sabri\\HomeNewsFeed\\Settings::get();
		$settings['general']['enabled'] = 1;
		$settings['feed']['enabled'] = 1;
		$settings['advanced']['emergency_disabled'] = 0;
		update_option( \\Sabri\\HomeNewsFeed\\Settings::OPTION_NAME, $settings, false );
		update_option( 'permalink_structure', '/%postname%/' );
		flush_rewrite_rules( false );
		echo 'features-enabled';
	`);
	assert(output.includes('features-enabled'), 'Feature activation PHP step did not complete.');
}

try {
	cliServer = await runCLI({
		command: 'server',
		php: phpVersion,
		wp: wpVersion,
		debug: true,
		login: false,
		mount: [
			{
				hostPath: path.resolve('.'),
				vfsPath: '/wordpress/wp-content/plugins/sabri-complete-home-news-feed',
			},
		],
		blueprint: {
			steps: [
				{
					step: 'activatePlugin',
					pluginPath: `/wordpress/wp-content/plugins/${pluginPath}`,
					pluginName: 'Sabri Complete Home and News Feed',
				},
			],
		},
	});

	const setupText = await runWordPressPhp(`
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( ! is_plugin_active( '${pluginPath}' ) ) {
			echo wp_json_encode( array( 'error' => 'plugin-not-active' ) );
			return;
		}

		$sample = get_page_by_path( 'sample-page', OBJECT, 'page' );
		$sample_id = $sample ? (int) $sample->ID : wp_insert_post(
			array(
				'post_type' => 'page',
				'post_status' => 'publish',
				'post_title' => 'Sample Page',
				'post_name' => 'sample-page',
				'post_content' => 'SABRI_SAMPLE_PAGE_ROUTE_OK',
			)
		);
		wp_update_post( array( 'ID' => $sample_id, 'post_status' => 'publish', 'post_content' => 'SABRI_SAMPLE_PAGE_ROUTE_OK' ) );

		$shortcode = get_page_by_path( 'phase-3-playground-test', OBJECT, 'page' );
		$shortcode_id = $shortcode ? (int) $shortcode->ID : wp_insert_post(
			array(
				'post_type' => 'page',
				'post_status' => 'publish',
				'post_title' => 'Phase 3 Playground Test',
				'post_name' => 'phase-3-playground-test',
				'post_content' => '[sabri_complete_home_feed]',
			)
		);
		wp_update_post( array( 'ID' => $shortcode_id, 'post_status' => 'publish', 'post_content' => '[sabri_complete_home_feed]' ) );

		$post = get_page_by_path( 'sabri-direct-post-test', OBJECT, 'post' );
		$post_id = $post ? (int) $post->ID : wp_insert_post(
			array(
				'post_type' => 'post',
				'post_status' => 'publish',
				'post_title' => 'Sabri Direct Post Test',
				'post_name' => 'sabri-direct-post-test',
				'post_content' => 'SABRI_DIRECT_POST_ROUTE_OK',
				'post_author' => 1,
			)
		);
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish', 'post_content' => 'SABRI_DIRECT_POST_ROUTE_OK' ) );
		\\Sabri\\HomeNewsFeed\\PostMetadata::save_for_post(
			$post_id,
			array(
				'feed_type' => 'standard-post',
				'visibility' => 'public',
				'review_state' => 'approved',
				'comments_enabled' => 1,
			)
		);

		echo wp_json_encode(
			array(
				'sample_id' => $sample_id,
				'shortcode_id' => $shortcode_id,
				'post_id' => $post_id,
			)
		);
	`);
	const ids = JSON.parse(setupText);
	assert(!ids.error, `Setup failed: ${ids.error || 'unknown error'}`);

	await enableAllFeaturesAndFlush();

	const home = await request('/');
	assert(home.status === 200, `Home page returned HTTP ${home.status}.`);

	await assertPage('/sample-page/', 'SABRI_SAMPLE_PAGE_ROUTE_OK', 'Pretty Sample Page');
	await assertPage(`/?page_id=${ids.sample_id}`, 'SABRI_SAMPLE_PAGE_ROUTE_OK', 'Plain Sample Page');

	const shortcodePage = await assertPage('/phase-3-playground-test/', 'Home Feed', 'Shortcode Page');
	assert(shortcodePage.body.includes('class="sabri-hnf-feed"'), 'Shortcode Page did not render the Home Feed component.');
	assert(!shortcodePage.body.includes('[sabri_complete_home_feed]'), 'Shortcode Page leaked the raw shortcode while the plugin was active.');

	const directPost = await assertPage('/sabri-direct-post-test/', 'SABRI_DIRECT_POST_ROUTE_OK', 'Direct Post');
	assert(!directPost.body.includes('class="sabri-hnf-feed"'), 'Direct Post incorrectly rendered the Home Feed instead of single-post content.');

	const missing = await request('/sabri-route-that-must-not-exist/');
	const missingLooksLike404 = missing.status === 404 || /page not found|not found|nothing here/i.test(missing.body);
	assert(missingLooksLike404, `Unknown route was incorrectly converted into normal content (HTTP ${missing.status}).`);
	assert(!missing.body.includes('SABRI_SAMPLE_PAGE_ROUTE_OK'), 'Unknown route leaked Sample Page content.');
	assert(!missing.body.includes('class="sabri-hnf-feed"'), 'Unknown route incorrectly rendered the Home Feed component.');

	const deactivateText = await runWordPressPhp(`
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		deactivate_plugins( '${pluginPath}', true );
		flush_rewrite_rules( false );
		echo is_plugin_active( '${pluginPath}' ) ? 'still-active' : 'deactivated';
	`);
	assert(deactivateText.includes('deactivated'), 'Plugin deactivation did not complete.');

	await assertPage('/sample-page/', 'SABRI_SAMPLE_PAGE_ROUTE_OK', 'Sample Page after deactivation');
	const inactiveShortcode = await assertPage('/phase-3-playground-test/', '[sabri_complete_home_feed]', 'Shortcode Page after deactivation');
	assert(!inactiveShortcode.body.includes('class="sabri-hnf-feed"'), 'Inactive plugin still rendered the Home Feed component.');

	const reactivateText = await runWordPressPhp(`
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$result = activate_plugin( '${pluginPath}', '', false, true );
		if ( is_wp_error( $result ) ) {
			echo 'activation-error:' . $result->get_error_message();
			return;
		}
		echo is_plugin_active( '${pluginPath}' ) ? 'reactivated' : 'not-active';
	`);
	assert(reactivateText.includes('reactivated'), `Plugin reactivation failed: ${reactivateText}`);
	await enableAllFeaturesAndFlush();

	await assertPage('/sample-page/', 'SABRI_SAMPLE_PAGE_ROUTE_OK', 'Sample Page after reactivation');
	const reactivatedShortcode = await assertPage('/phase-3-playground-test/', 'Home Feed', 'Shortcode Page after reactivation');
	assert(reactivatedShortcode.body.includes('class="sabri-hnf-feed"'), 'Reactivated plugin did not render the Home Feed component.');
	await assertPage('/sabri-direct-post-test/', 'SABRI_DIRECT_POST_ROUTE_OK', 'Direct Post after reactivation');

	console.log(`Playground integration tests passed on WordPress ${wpVersion} / PHP ${phpVersion}.`);
} finally {
	if (cliServer && typeof cliServer[Symbol.asyncDispose] === 'function') {
		await cliServer[Symbol.asyncDispose]();
	}
}
