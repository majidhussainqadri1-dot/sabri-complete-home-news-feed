import path from 'node:path';
import process from 'node:process';
import { runCLI } from '@wp-playground/cli';

const phpVersion = process.env.SABRI_PLAYGROUND_PHP || '8.3';
const wpVersion = process.env.SABRI_PLAYGROUND_WP || 'latest';
const packagePath = process.env.SABRI_PLUGIN_ZIP || '';
const pluginPath = 'sabri-complete-home-news-feed/sabri-complete-home-news-feed.php';
const virtualZip = '/tmp/file21-production-candidate.zip';
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

async function rawGet(url) {
	const response = await server.playground.request({ url, method: 'GET' });
	if (response.errors && String(response.errors).trim()) throw new Error(`Frontend PHP error at ${url}: ${response.errors}`);
	return {
		status: Number(response.httpStatusCode || 0),
		body: String(response.text || ''),
		location: header(response.headers, 'location'),
	};
}

try {
	const options = { command: 'server', php: phpVersion, wp: wpVersion, debug: true, login: false };
	if (packagePath) {
		options.mount = [{ hostPath: path.resolve(packagePath), vfsPath: virtualZip }];
	} else {
		options.mount = [{ hostPath: path.resolve('.'), vfsPath: '/wordpress/wp-content/plugins/sabri-complete-home-news-feed' }];
		options.blueprint = { steps: [{ step: 'activatePlugin', pluginPath: `/wordpress/wp-content/plugins/${pluginPath}` }] };
	}
	server = await runCLI(options);

	if (packagePath) {
		const installed = JSON.parse(await php(`
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			call_user_func( 'WP_' . 'Filesystem' );
			$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
			$result = $upgrader->install( '${virtualZip}' );
			if ( is_wp_error( $result ) ) { echo wp_json_encode( array( 'error' => $result->get_error_message() ) ); return; }
			$activation = activate_plugin( '${pluginPath}', '', false, false );
			echo wp_json_encode( array( 'error' => is_wp_error( $activation ) ? $activation->get_error_message() : '', 'active' => is_plugin_active( '${pluginPath}' ) ) );
		`));
		assert(!installed.error && installed.active, `Packaged activation failed: ${JSON.stringify(installed)}`);
	}

	const setup = JSON.parse(await php(String.raw`
		update_option( 'permalink_structure', '/%postname%/' );
		$approved = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$id = wp_insert_post( array(
				'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 1,
				'post_title' => 'File 21 Approved ' . $i, 'post_name' => 'file21-approved-' . $i,
				'post_content' => 'FILE21_APPROVED_' . $i,
			) );
			update_post_meta( $id, \Sabri\HomeNewsFeed\PostMetadata::META_REVIEW_STATE, 'approved' );
			update_post_meta( $id, \Sabri\HomeNewsFeed\PostMetadata::META_VISIBILITY, 'public' );
			update_post_meta( $id, \Sabri\HomeNewsFeed\PostMetadata::META_TYPE, 'standard-post' );
			$approved[] = (int) $id;
		}
		$pending = wp_insert_post( array( 'post_type'=>'post', 'post_status'=>'publish', 'post_author'=>1, 'post_title'=>'File 21 Hidden Pending', 'post_name'=>'file21-hidden-pending' ) );
		update_post_meta( $pending, \Sabri\HomeNewsFeed\PostMetadata::META_REVIEW_STATE, 'pending' );
		update_post_meta( $pending, \Sabri\HomeNewsFeed\PostMetadata::META_VISIBILITY, 'public' );
		$private = wp_insert_post( array( 'post_type'=>'post', 'post_status'=>'publish', 'post_author'=>1, 'post_title'=>'File 21 Hidden Private', 'post_name'=>'file21-hidden-private' ) );
		update_post_meta( $private, \Sabri\HomeNewsFeed\PostMetadata::META_REVIEW_STATE, 'approved' );
		update_post_meta( $private, \Sabri\HomeNewsFeed\PostMetadata::META_VISIBILITY, 'private' );

		$query_args = \Sabri\HomeNewsFeed\FeedQuery::wp_query_args(
			'latest', 1, 2, 0, \Sabri\HomeNewsFeed\Settings::get()
		);
		$query_args['orderby'] = 'ID';
		$query_args['order'] = 'ASC';
		$query_args['fields'] = 'ids';
		$page1 = new WP_Query( array_merge( $query_args, array( 'paged'=>1 ) ) );
		$page2 = new WP_Query( array_merge( $query_args, array( 'paged'=>2 ) ) );

		update_option( \Sabri\HomeNewsFeed\PublicSurfaceRecovery::VERSION_OPTION, 'production-test-sentinel', false );
		update_option( \Sabri\HomeNewsFeed\PublicSurfaceRecovery::REPORT_OPTION, array( 'sentinel'=>'unchanged' ), false );

		$phase4 = \Sabri\HomeNewsFeed\NewsFeatureSettings::defaults();
		$phase4['editorial_news_enabled'] = 1;
		update_option( \Sabri\HomeNewsFeed\NewsFeatureSettings::OPTION_NAME, $phase4, false );
		foreach ( \Sabri\HomeNewsFeed\Phase4Contracts::sections() as $slug => $label ) {
			if ( ! term_exists( $slug, 'sabri_news_section' ) ) { wp_insert_term( $label, 'sabri_news_section', array( 'slug'=>$slug ) ); }
		}
		foreach ( \Sabri\HomeNewsFeed\Phase4Contracts::article_types() as $slug => $label ) {
			if ( ! term_exists( $slug, 'sabri_news_type' ) ) { wp_insert_term( $label, 'sabri_news_type', array( 'slug'=>$slug ) ); }
		}
		$news_id = wp_insert_post( array(
			'post_type'=>\Sabri\HomeNewsFeed\Phase4Contracts::POST_TYPE, 'post_status'=>'publish',
			'post_title'=>'File 21 Production News Story', 'post_name'=>'file21-production-news-story',
			'post_excerpt'=>'Production route evidence.', 'post_content'=>'FILE21_PRODUCTION_NEWS_BODY',
		) );
		update_post_meta( $news_id, \Sabri\HomeNewsFeed\Phase4Contracts::WORKFLOW_META_KEY, 'published' );
		update_post_meta( $news_id, '_sabri_news_summary', 'Production route evidence.' );
		wp_set_object_terms( $news_id, array( 'platform-news' ), 'sabri_news_section', false );
		wp_set_object_terms( $news_id, array( 'standard-news' ), 'sabri_news_type', false );
		\Sabri\HomeNewsFeed\NewsPublicSnapshot::capture( $news_id, true );
		\Sabri\HomeNewsFeed\NewsRouting::rewrite_rules();
		flush_rewrite_rules( false );

		wp_set_current_user( 1 );
		\Sabri\HomeNewsFeed\RestFoundation::register_safe_boot_route_definitions();
		$server = rest_get_server();
		$status = $server->dispatch( new WP_REST_Request( 'GET', '/sabri-home-news-feed/v1/status' ) );
		$schema = $server->dispatch( new WP_REST_Request( 'GET', '/sabri-home-news-feed/v1/schema' ) );

		echo wp_json_encode( array(
			'version'=>SABRI_HNF_VERSION, 'schema_version'=>SABRI_HNF_SCHEMA_VERSION,
			'approved'=>$approved, 'pending'=>(int)$pending, 'private'=>(int)$private,
			'page1_ids'=>array_map('intval',$page1->posts), 'page1_found'=>(int)$page1->found_posts, 'page1_pages'=>(int)$page1->max_num_pages,
			'page2_ids'=>array_map('intval',$page2->posts), 'page2_found'=>(int)$page2->found_posts, 'page2_pages'=>(int)$page2->max_num_pages,
			'news_id'=>(int)$news_id,
			'safe_status'=>(int)$status->get_status(), 'safe_schema'=>(int)$schema->get_status(),
		) );
	`));

	assert(setup.version === '1.0.3' && setup.schema_version === '1.0.0', 'Runtime/schema identity mismatch.');
	assert(setup.page1_found === 3 && setup.page1_pages === 2 && setup.page1_ids.length === 2, `Page 1 pagination mismatch: ${JSON.stringify(setup)}`);
	assert(setup.page2_found === 3 && setup.page2_pages === 2 && setup.page2_ids.length === 1, `Page 2 pagination mismatch: ${JSON.stringify(setup)}`);
	assert(!setup.page1_ids.includes(setup.pending) && !setup.page1_ids.includes(setup.private), 'Hidden posts leaked into page 1.');
	assert(!setup.page2_ids.includes(setup.pending) && !setup.page2_ids.includes(setup.private), 'Hidden posts leaked into page 2.');
	assert(setup.safe_status === 403 && setup.safe_schema === 403, `Safe Boot diagnostics did not fail closed without File 00 assurance: ${JSON.stringify(setup)}`);

	const home = await rawGet('/');
	assert(home.status === 200, `Home returned ${home.status}.`);
	assert(home.body.includes('data-sabri-hnf-surface="file-21-corrective"'), 'File 21 Home surface marker is missing.');
	assert((home.body.match(/data-sabri-home-row="/g) || []).length === 10, 'Home did not render exactly ten rows.');

	const stateAfterGet = JSON.parse(await php(`echo wp_json_encode( array(
		'version'=>get_option( \\Sabri\\HomeNewsFeed\\PublicSurfaceRecovery::VERSION_OPTION, '' ),
		'report'=>get_option( \\Sabri\\HomeNewsFeed\\PublicSurfaceRecovery::REPORT_OPTION, array() ),
	) );`));
	assert(stateAfterGet.version === 'production-test-sentinel', `Public GET changed recovery version: ${JSON.stringify(stateAfterGet)}`);
	assert(stateAfterGet.report && stateAfterGet.report.sentinel === 'unchanged', `Public GET changed recovery report: ${JSON.stringify(stateAfterGet)}`);

	const news = await rawGet('/news/');
	assert(news.status === 200 && news.body.includes('File 21 Production News Story'), `Canonical /news/ failed: ${JSON.stringify({status:news.status,location:news.location})}`);
	const legacyNews = await rawGet('/sabri-news/');
	assert(legacyNews.status === 301 && /\/news\/?$/.test(legacyNews.location), `Legacy /sabri-news/ redirect failed: ${JSON.stringify(legacyNews)}`);
	const legacyBlog = await rawGet('/blog/');
	assert(legacyBlog.status === 301 && /\/news\/?$/.test(legacyBlog.location), `Legacy /blog/ redirect failed: ${JSON.stringify(legacyBlog)}`);

	console.log(`File 21 production WordPress tests passed on WordPress ${wpVersion} / PHP ${phpVersion}.`);
} finally {
	if (server && typeof server[Symbol.asyncDispose] === 'function') await server[Symbol.asyncDispose]();
}
