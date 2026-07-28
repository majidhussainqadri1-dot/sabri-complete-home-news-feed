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
		$gate_off = 0 === (int) $phase4['editorial_news_enabled'];
		$off_query = \Sabri\HomeNewsFeed\NewsQueryService::query();
		$phase4['editorial_news_enabled'] = 1;
		update_option( \Sabri\HomeNewsFeed\NewsFeatureSettings::OPTION_NAME, $phase4, false );

		foreach ( \Sabri\HomeNewsFeed\Phase4Contracts::sections() as $slug => $label ) {
			if ( ! term_exists( $slug, 'sabri_news_section' ) ) { wp_insert_term( $label, 'sabri_news_section', array( 'slug'=>$slug ) ); }
		}
		foreach ( \Sabri\HomeNewsFeed\Phase4Contracts::article_types() as $slug => $label ) {
			if ( ! term_exists( $slug, 'sabri_news_type' ) ) { wp_insert_term( $label, 'sabri_news_type', array( 'slug'=>$slug ) ); }
		}
		foreach ( array( 'phase4c-topic'=>'Phase 4C Topic' ) as $slug=>$label ) { if ( ! term_exists( $slug, 'sabri_news_topic' ) ) { wp_insert_term( $label, 'sabri_news_topic', array( 'slug'=>$slug ) ); } }
		foreach ( array( 'phase4c-country'=>'Phase 4C Country' ) as $slug=>$label ) { if ( ! term_exists( $slug, 'sabri_news_country' ) ) { wp_insert_term( $label, 'sabri_news_country', array( 'slug'=>$slug ) ); } }
		foreach ( array( 'phase4c-region'=>'Phase 4C Region' ) as $slug=>$label ) { if ( ! term_exists( $slug, 'sabri_news_region' ) ) { wp_insert_term( $label, 'sabri_news_region', array( 'slug'=>$slug ) ); } }

		$published_id = wp_insert_post( array(
			'post_type'=>\Sabri\HomeNewsFeed\Phase4Contracts::POST_TYPE,
			'post_status'=>'publish', 'post_name'=>'phase4c-public-story',
			'post_title'=>'Phase 4C Public Story', 'post_excerpt'=>'A public archive summary.',
			'post_content'=>'<p>Public WordPress integration body.</p><script>alert(1)</script>',
		) );
		update_post_meta( $published_id, \Sabri\HomeNewsFeed\Phase4Contracts::WORKFLOW_META_KEY, 'published' );
		update_post_meta( $published_id, '_sabri_news_summary', 'A public archive summary.' );
		update_post_meta( $published_id, '_sabri_news_public_institution_name', 'Phase 4C Institution' );
		update_post_meta( $published_id, '_sabri_news_featured', 1 );
		wp_set_object_terms( $published_id, array( 'platform-news' ), 'sabri_news_section', false );
		wp_set_object_terms( $published_id, array( 'standard-news' ), 'sabri_news_type', false );
		wp_set_object_terms( $published_id, array( 'phase4c-topic' ), 'sabri_news_topic', false );
		wp_set_object_terms( $published_id, array( 'phase4c-country' ), 'sabri_news_country', false );
		wp_set_object_terms( $published_id, array( 'phase4c-region' ), 'sabri_news_region', false );
		\Sabri\HomeNewsFeed\NewsPublicSnapshot::capture( $published_id, true );

		$draft_id = wp_insert_post( array(
			'post_type'=>\Sabri\HomeNewsFeed\Phase4Contracts::POST_TYPE,
			'post_status'=>'draft', 'post_name'=>'phase4c-private-draft',
			'post_title'=>'Phase 4C Private Draft', 'post_content'=>'Private body',
		) );
		update_post_meta( $draft_id, \Sabri\HomeNewsFeed\Phase4Contracts::WORKFLOW_META_KEY, 'draft' );

		$retracted_id = wp_insert_post( array(
			'post_type'=>\Sabri\HomeNewsFeed\Phase4Contracts::POST_TYPE,
			'post_status'=>'private', 'post_name'=>'phase4c-retracted-story',
			'post_title'=>'Phase 4C Retracted Story', 'post_content'=>'Hidden original body',
		) );
		update_post_meta( $retracted_id, \Sabri\HomeNewsFeed\Phase4Contracts::WORKFLOW_META_KEY, 'retracted' );
		update_post_meta( $retracted_id, '_sabri_news_retraction_notice', 'Retracted after verification failed.' );

		$collection = \Sabri\HomeNewsFeed\NewsQueryService::query( array( 'per_page'=>12 ) );
		$landing = \Sabri\HomeNewsFeed\NewsQueryService::landing();
		$single = \Sabri\HomeNewsFeed\NewsQueryService::single( 'phase4c-public-story' );
		$private_single = \Sabri\HomeNewsFeed\NewsQueryService::single( $draft_id );
		$retracted = \Sabri\HomeNewsFeed\NewsQueryService::query( array( 'retracted'=>1 ) );
		$taxonomy = \Sabri\HomeNewsFeed\NewsQueryService::query( array( 'topic'=>'phase4c-topic' ) );
		$unknown_taxonomy = \Sabri\HomeNewsFeed\NewsQueryService::query( array( 'topic'=>'missing-topic' ) );
		$invalid = \Sabri\HomeNewsFeed\NewsQueryService::query( array( 'date_from'=>'2027-02-31' ) );
		$context = \Sabri\HomeNewsFeed\NewsFeedIntegration::pagination_context( 'latest', 1, 10 );
		$integrated = \Sabri\HomeNewsFeed\NewsFeedIntegration::integrate_result( array( 'posts'=>array(), 'total'=>0, 'max_pages'=>0, 'has_more'=>false ), $context );
		\Sabri\HomeNewsFeed\RestNews::register_routes();
		\Sabri\HomeNewsFeed\NewsRouting::rewrite_rules();
		flush_rewrite_rules( false );
		$rules = $wp_rewrite->wp_rewrite_rules();

		$parse = static function ( $uri ) {
			global $wp, $wp_query;
			$old_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
			$old_path = isset( $_SERVER['PATH_INFO'] ) ? $_SERVER['PATH_INFO'] : null;
			$_SERVER['REQUEST_URI'] = $uri;
			$_SERVER['PATH_INFO'] = '';
			$wp = new WP();
			$wp->parse_request();
			$vars = $wp->query_vars;
			if ( null === $old_uri ) { unset( $_SERVER['REQUEST_URI'] ); } else { $_SERVER['REQUEST_URI'] = $old_uri; }
			if ( null === $old_path ) { unset( $_SERVER['PATH_INFO'] ); } else { $_SERVER['PATH_INFO'] = $old_path; }
			return $vars;
		};
		$archive_vars = $parse( '/news/' );
		$single_vars = $parse( '/news/phase4c-public-story/' );
		$taxonomy_vars = $parse( '/news/topic/phase4c-topic/' );

		$rest_server = rest_get_server();
		$rest_collection = $rest_server->dispatch( new WP_REST_Request( 'GET', '/sabri-home-news-feed/v1/news' ) );
		$rest_single = $rest_server->dispatch( new WP_REST_Request( 'GET', '/sabri-home-news-feed/v1/news/' . $published_id ) );
		$rest_write = $rest_server->dispatch( new WP_REST_Request( 'POST', '/sabri-home-news-feed/v1/news' ) );

		$before_deactivation = get_post( $published_id ) ? 1 : 0;
		deactivate_plugins( '${pluginPath}', true );
		$after_deactivation = get_post( $published_id ) ? 1 : 0;
		$reactivation = activate_plugin( '${pluginPath}', '', false, false );
		$after_reactivation = get_post( $published_id ) ? 1 : 0;
		$active_after = is_plugin_active( '${pluginPath}' );

		echo wp_json_encode( array(
			'gate_off'=>$gate_off, 'off_query'=>$off_query,
			'published_id'=>(int)$published_id, 'collection'=>$collection, 'landing'=>$landing,
			'single'=>$single, 'private_single'=>$private_single, 'retracted'=>$retracted,
			'taxonomy'=>$taxonomy, 'unknown_taxonomy'=>$unknown_taxonomy, 'invalid'=>$invalid,
			'integrated'=>$integrated,
			'has_archive_rule'=>isset($rules['^news/?$']),
			'has_single_rule'=>isset($rules['^news/([a-z0-9]+(?:-[a-z0-9]+)*)/?$']),
			'archive_vars'=>$archive_vars, 'single_vars'=>$single_vars, 'taxonomy_vars'=>$taxonomy_vars,
			'rest_collection_status'=>$rest_collection->get_status(),
			'rest_single_status'=>$rest_single->get_status(),
			'rest_write_status'=>$rest_write->get_status(),
			'rest_collection_data'=>$rest_collection->get_data(),
			'before_deactivation'=>$before_deactivation, 'after_deactivation'=>$after_deactivation,
			'after_reactivation'=>$after_reactivation, 'reactivation_error'=>is_wp_error($reactivation)?$reactivation->get_error_message():'', 'active_after'=>$active_after,
			'version'=>SABRI_HNF_VERSION, 'schema'=>SABRI_HNF_SCHEMA_VERSION,
			'checkpoint'=>\Sabri\HomeNewsFeed\Phase4Contracts::CHECKPOINT,
		) );
	`));

	assert(result.version === '1.0.3' && result.schema === '1.0.0' && result.checkpoint === '4A', 'Phase 4C runtime, schema, or checkpoint boundary is incorrect.');
	assert(result.gate_off && !result.off_query.success && result.off_query.code === 'editorial_news_disabled', 'Gate-off public News did not fail closed.');
	assert(result.collection.success && result.collection.data.items.length === 1, 'Public collection did not isolate one published article.');
	assert(result.landing.success && result.landing.data.components.length >= 11, 'Complete bounded News landing was not assembled.');
	assert(result.single.success && result.single.data.canonical_url.endsWith('/news/phase4c-public-story/'), 'Canonical public single lookup failed.');
	assert(!result.single.data.body_html.includes('<script'), 'Public article sanitization failed.');
	assert(!result.private_single.success && result.private_single.status === 404, 'Private draft became enumerable.');
	assert(result.retracted.success && result.retracted.data.items.length === 1 && result.retracted.data.items[0].body_html === '', 'Retraction accountability projection failed.');
	assert(result.taxonomy.success && result.taxonomy.data.items.length === 1, 'Controlled taxonomy query failed.');
	assert(!result.unknown_taxonomy.success && result.unknown_taxonomy.code === 'public_news_taxonomy_invalid', 'Unknown taxonomy term was accepted.');
	assert(!result.invalid.success && result.invalid.field === 'date_from', 'Strict public date validation failed.');
	assert(result.integrated.posts.length === 1 && result.integrated.posts[0].global_key === `news:${result.published_id}`, 'Home Feed News normalization failed.');
	assert(result.has_archive_rule && result.has_single_rule, 'Canonical public rewrite rules were not registered.');
	assert(String(result.archive_vars.sabri_news_public_archive) === '1', 'Actual /news/ request did not resolve to the archive query variable.');
	assert(result.single_vars.sabri_news_public_slug === 'phase4c-public-story', 'Actual single News request did not resolve its canonical slug.');
	assert(result.taxonomy_vars.sabri_news_public_taxonomy === 'sabri_news_topic' && result.taxonomy_vars.sabri_news_public_term === 'phase4c-topic', 'Actual taxonomy News request did not resolve safely.');
	assert(result.rest_collection_status === 200 && result.rest_single_status === 200, 'Public REST GET routes failed.');
	assert(result.rest_write_status === 404 || result.rest_write_status === 405, 'A public News write route was opened.');
	assert(result.before_deactivation === 1 && result.after_deactivation === 1 && result.after_reactivation === 1 && !result.reactivation_error && result.active_after, 'Deactivation/reactivation did not preserve Editorial News data.');

	console.log(`Phase 4C ${packagePath ? 'packaged' : 'source'} WordPress tests passed on WordPress ${wpVersion} / PHP ${phpVersion}.`);
} finally {
	if (server && typeof server[Symbol.asyncDispose] === 'function') await server[Symbol.asyncDispose]();
}
