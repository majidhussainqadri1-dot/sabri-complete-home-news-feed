import dns from 'node:dns';
import path from 'node:path';
import process from 'node:process';
import { runCLI } from '@wp-playground/cli';

dns.setDefaultResultOrder('ipv4first');

const phpVersion = process.env.SABRI_PLAYGROUND_PHP || '8.3';
const wpVersion = process.env.SABRI_PLAYGROUND_WP || 'latest';
const packagePath = process.env.SABRI_PLUGIN_ZIP || '';
const pluginPath = 'sabri-complete-home-news-feed/sabri-complete-home-news-feed.php';
const virtualZip = '/tmp/sabri-phase4c-candidate.zip';
let server;

function assert(condition, message) {
	if (!condition) throw new Error(message);
}
function delay(milliseconds) {
	return new Promise((resolve) => setTimeout(resolve, milliseconds));
}
async function startPlayground(options, attempts = 3) {
	let lastError;
	for (let attempt = 1; attempt <= attempts; attempt += 1) {
		try {
			return await runCLI(structuredClone(options));
		} catch (error) {
			lastError = error;
			if (attempt === attempts) break;
			await delay(attempt * 5000);
		}
	}
	throw lastError;
}
async function php(code) {
	const response = await server.playground.run({ code: `<?php require '/wordpress/wp-load.php'; ${code}` });
	if (response.errors && String(response.errors).trim()) throw new Error(`PHP error: ${response.errors}`);
	return String(response.text || '').trim();
}

try {
	const options = { command: 'server', php: phpVersion, wp: wpVersion, debug: true, login: false };
	if (packagePath) {
		options.mount = [{ hostPath: path.resolve(packagePath), vfsPath: virtualZip }];
	} else {
		options.mount = [{ hostPath: path.resolve('.'), vfsPath: '/wordpress/wp-content/plugins/sabri-complete-home-news-feed' }];
		options.blueprint = { steps: [{ step: 'activatePlugin', pluginPath: `/wordpress/wp-content/plugins/${pluginPath}` }] };
	}
	server = await startPlayground(options);

	if (packagePath) {
		const install = JSON.parse(await php(`
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			call_user_func( 'WP_' . 'Filesystem' );
			$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
			$result = $upgrader->install( '${virtualZip}' );
			if ( is_wp_error( $result ) ) { echo wp_json_encode( array( 'error'=>$result->get_error_message() ) ); return; }
			$activation = activate_plugin( '${pluginPath}', '', false, false );
			echo wp_json_encode( array( 'error'=>is_wp_error($activation)?$activation->get_error_message():'', 'active'=>is_plugin_active('${pluginPath}') ) );
		`));
		assert(!install.error && install.active, `Packaged Phase 4C activation failed: ${JSON.stringify(install)}`);
	}

	const result = JSON.parse(await php(String.raw`
		$phase4 = \Sabri\HomeNewsFeed\NewsFeatureSettings::defaults();
		$phase4['editorial_news_enabled'] = 1;
		update_option( \Sabri\HomeNewsFeed\NewsFeatureSettings::OPTION_NAME, $phase4, false );

		foreach ( \Sabri\HomeNewsFeed\Phase4Contracts::sections() as $slug => $label ) {
			if ( ! term_exists( $slug, 'sabri_news_section' ) ) { wp_insert_term( $label, 'sabri_news_section', array( 'slug'=>$slug ) ); }
		}
		foreach ( \Sabri\HomeNewsFeed\Phase4Contracts::article_types() as $slug => $label ) {
			if ( ! term_exists( $slug, 'sabri_news_type' ) ) { wp_insert_term( $label, 'sabri_news_type', array( 'slug'=>$slug ) ); }
		}

		$published_id = wp_insert_post( array(
			'post_type'=>\Sabri\HomeNewsFeed\Phase4Contracts::POST_TYPE,
			'post_status'=>'publish',
			'post_name'=>'phase4c-public-story',
			'post_title'=>'Phase 4C Public Story',
			'post_excerpt'=>'A public archive summary.',
			'post_content'=>'<p>Public WordPress integration body.</p>',
		) );
		update_post_meta( $published_id, \Sabri\HomeNewsFeed\Phase4Contracts::WORKFLOW_META_KEY, 'published' );
		update_post_meta( $published_id, '_sabri_news_summary', 'A public archive summary.' );
		wp_set_object_terms( $published_id, array( 'platform-news' ), 'sabri_news_section', false );
		wp_set_object_terms( $published_id, array( 'standard-news' ), 'sabri_news_type', false );

		$draft_id = wp_insert_post( array(
			'post_type'=>\Sabri\HomeNewsFeed\Phase4Contracts::POST_TYPE,
			'post_status'=>'draft',
			'post_name'=>'phase4c-private-draft',
			'post_title'=>'Phase 4C Private Draft',
			'post_content'=>'Private body',
		) );
		update_post_meta( $draft_id, \Sabri\HomeNewsFeed\Phase4Contracts::WORKFLOW_META_KEY, 'draft' );

		$retracted_id = wp_insert_post( array(
			'post_type'=>\Sabri\HomeNewsFeed\Phase4Contracts::POST_TYPE,
			'post_status'=>'private',
			'post_name'=>'phase4c-retracted-story',
			'post_title'=>'Phase 4C Retracted Story',
			'post_content'=>'Hidden original body',
		) );
		update_post_meta( $retracted_id, \Sabri\HomeNewsFeed\Phase4Contracts::WORKFLOW_META_KEY, 'retracted' );
		update_post_meta( $retracted_id, '_sabri_news_retraction_notice', 'Retracted after verification failed.' );

		$collection = \Sabri\HomeNewsFeed\NewsQueryService::query( array( 'per_page'=>12 ) );
		$single = \Sabri\HomeNewsFeed\NewsQueryService::single( 'phase4c-public-story' );
		$private_single = \Sabri\HomeNewsFeed\NewsQueryService::single( $draft_id );
		$retracted = \Sabri\HomeNewsFeed\NewsQueryService::query( array( 'retracted'=>1 ) );
		$invalid = \Sabri\HomeNewsFeed\NewsQueryService::query( array( 'date_from'=>'2027-02-31' ) );
		$definition = \Sabri\HomeNewsFeed\EditorialNewsPostType::definition();
		$context = \Sabri\HomeNewsFeed\NewsFeedIntegration::pagination_context( 'latest', 1, 10 );
		$integrated = \Sabri\HomeNewsFeed\NewsFeedIntegration::integrate_result(
			array( 'posts'=>array(), 'total'=>0, 'max_pages'=>0, 'has_more'=>false ),
			$context
		);
		\Sabri\HomeNewsFeed\RestNews::register_routes();
		\Sabri\HomeNewsFeed\NewsRouting::rewrite_rules();
		global $wp_rewrite;
		$rules = $wp_rewrite->wp_rewrite_rules();

		echo wp_json_encode( array(
			'published_id'=>(int)$published_id,
			'collection'=>$collection,
			'single'=>$single,
			'private_single'=>$private_single,
			'retracted'=>$retracted,
			'invalid'=>$invalid,
			'definition'=>$definition,
			'integrated'=>$integrated,
			'has_archive_rule'=>isset($rules['news/?$']),
			'has_single_rule'=>isset($rules['news/([a-z0-9]+(?:-[a-z0-9]+)*)/?$']),
			'rest_routes'=>array_keys(rest_get_server()->get_routes()),
			'version'=>SABRI_HNF_VERSION,
			'schema'=>SABRI_HNF_SCHEMA_VERSION,
			'checkpoint'=>\Sabri\HomeNewsFeed\Phase4Contracts::CHECKPOINT,
		) );
	`));

	assert(result.version === '1.0.0' && result.schema === '1.0.0' && result.checkpoint === '4A', 'Phase 4C changed frozen version/checkpoint boundaries.');
	assert(result.definition.publicly_queryable === true && result.definition.rewrite.slug === 'news', 'Public CPT definition did not open only through the explicit gate.');
	assert(result.collection.success && result.collection.data.items.length === 1, 'Public collection did not isolate one published article.');
	assert(result.single.success && result.single.data.canonical_url.endsWith('/news/phase4c-public-story/'), 'Canonical public single lookup failed.');
	assert(!result.private_single.success && result.private_single.status === 404, 'Private draft became enumerable.');
	assert(result.retracted.success && result.retracted.data.items.length === 1 && result.retracted.data.items[0].body_html === '', 'Retraction accountability projection failed.');
	assert(!result.invalid.success && result.invalid.field === 'date_from', 'Strict public date validation failed.');
	assert(result.integrated.posts.length === 1 && result.integrated.posts[0].global_key === `news:${result.published_id}`, 'Home Feed News normalization failed.');
	assert(result.has_archive_rule && result.has_single_rule, 'Canonical public rewrite rules were not registered.');
	assert(result.rest_routes.includes('/sabri-home-news-feed/v1/news') && result.rest_routes.includes('/sabri-home-news-feed/v1/news/(?P<id>[1-9][0-9]*)'), 'Public read-only REST routes were not registered.');

	console.log(`Phase 4C ${packagePath ? 'packaged' : 'source'} WordPress tests passed on WordPress ${wpVersion} / PHP ${phpVersion}.`);
} finally {
	if (server && typeof server[Symbol.asyncDispose] === 'function') await server[Symbol.asyncDispose]();
}
