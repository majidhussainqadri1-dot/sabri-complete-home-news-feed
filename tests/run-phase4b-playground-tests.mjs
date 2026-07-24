import dns from 'node:dns';
import path from 'node:path';
import process from 'node:process';
import { runCLI } from '@wp-playground/cli';

dns.setDefaultResultOrder('ipv4first');

const phpVersion = process.env.SABRI_PLAYGROUND_PHP || '8.3';
const wpVersion = process.env.SABRI_PLAYGROUND_WP || 'latest';
const packagePath = process.env.SABRI_PLUGIN_ZIP || '';
const pluginPath = 'sabri-complete-home-news-feed/sabri-complete-home-news-feed.php';
const virtualZip = '/tmp/sabri-phase4b-candidate.zip';
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
		assert(!install.error && install.active, `Packaged Phase 4B activation failed: ${JSON.stringify(install)}`);
	}

	const result = JSON.parse(await php(String.raw`
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$editor_id = wp_create_user( 'phase4b_editor', wp_generate_password( 24 ), 'phase4b-editor@example.test' );
		if ( is_wp_error( $editor_id ) ) { echo wp_json_encode( array( 'error'=>$editor_id->get_error_message() ) ); return; }
		$editor = new WP_User( $editor_id );
		foreach ( \Sabri\HomeNewsFeed\Phase4Contracts::capabilities() as $cap ) { $editor->add_cap( $cap ); }
		foreach ( array( 'edit_editorial_news', 'read_editorial_news_item', 'upload_files' ) as $cap ) { $editor->add_cap( $cap ); }
		wp_set_current_user( $editor_id );

		$payload = array(
			'title'=>'Phase 4B Playground Article',
			'content'=>'<p>Actual WordPress newsroom content.</p>',
			'subtitle'=>'Playground validation',
			'summary'=>'A complete private editorial summary.',
			'language'=>'en-US',
			'priority'=>'20',
			'section'=>'platform-news',
			'article_type'=>'standard-news',
			'topics'=>array('platform'),
			'countries'=>array('pakistan'),
			'regions'=>array('south-asia'),
			'reviewing_editor_id'=>$editor_id,
			'medical_reviewer_id'=>$editor_id,
			'fact_check_required'=>1,
			'medical_review_required'=>1,
			'target_state'=>'draft',
		);
		$created = \Sabri\HomeNewsFeed\NewsService::save( 0, $payload, array( 'method'=>'POST', 'nonce_verified'=>true ) );
		if ( empty( $created['success'] ) ) { echo wp_json_encode( array( 'error'=>'create_failed', 'detail'=>$created ) ); return; }
		$post_id = (int) $created['data']['post_id'];
		$submitted = \Sabri\HomeNewsFeed\NewsService::transition( $post_id, 'editorial-review', array( 'method'=>'POST', 'nonce_verified'=>true ) );
		$approved = \Sabri\HomeNewsFeed\NewsService::transition( $post_id, 'ready-for-publication', array( 'method'=>'POST', 'nonce_verified'=>true ) );
		$schedule_input = gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 2 * DAY_IN_SECONDS ) );
		$scheduled = \Sabri\HomeNewsFeed\NewsSchedulingService::schedule( $post_id, $schedule_input );
		$queue = \Sabri\HomeNewsFeed\NewsQueueService::query( 'scheduled', 1, 20 );
		$definition = \Sabri\HomeNewsFeed\EditorialNewsPostType::definition();
		$next = wp_next_scheduled( \Sabri\HomeNewsFeed\NewsSchedulingService::HOOK, array( $post_id ) );
		\Sabri\HomeNewsFeed\NewsSchedulingService::mark_due( $post_id );
		$diagnostics = \Sabri\HomeNewsFeed\NewsSchedulingService::diagnostics( $post_id );
		$cancelled = \Sabri\HomeNewsFeed\NewsSchedulingService::cancel( $post_id );
		echo wp_json_encode( array(
			'post_id'=>$post_id,
			'created'=>$created,
			'submitted'=>$submitted,
			'approved'=>$approved,
			'scheduled'=>$scheduled,
			'queue_success'=>!empty($queue['success']),
			'queue_total'=>!empty($queue['data']['total'])?(int)$queue['data']['total']:0,
			'publicly_queryable'=>(bool)$definition['publicly_queryable'],
			'rest_exposed'=>(bool)$definition['show_in_rest'],
			'event_timestamp'=>$next?(int)$next:0,
			'diagnostics'=>$diagnostics,
			'cancelled'=>$cancelled,
			'final_state'=>get_post_meta($post_id, \Sabri\HomeNewsFeed\Phase4Contracts::WORKFLOW_META_KEY, true),
			'audit_count'=>count(get_post_meta($post_id, \Sabri\HomeNewsFeed\NewsAudit::META_KEY, false)),
		) );
	`));

	assert(!result.error, `Phase 4B setup failed: ${JSON.stringify(result)}`);
	assert(result.created.success && result.submitted.success && result.approved.success, 'Composer or workflow service failed.');
	assert(result.scheduled.success && result.event_timestamp > 0, 'Scheduling foundation failed.');
	assert(result.queue_success && result.queue_total >= 1, 'Scheduled private queue did not contain the article.');
	assert(result.publicly_queryable === false && result.rest_exposed === false, 'Phase 4B exposed public News runtime.');
	assert(result.diagnostics.due === true && result.diagnostics.auto_publish_enabled === false, 'Due scheduling did not remain preparation-only.');
	assert(result.cancelled.success && result.final_state === 'ready-for-publication', 'Schedule cancellation failed.');
	assert(result.audit_count >= 5, 'Append-only editorial audit evidence is incomplete.');

	console.log(`Phase 4B ${packagePath ? 'packaged' : 'source'} WordPress tests passed on WordPress ${wpVersion} / PHP ${phpVersion}.`);
} finally {
	if (server && typeof server[Symbol.asyncDispose] === 'function') await server[Symbol.asyncDispose]();
}
