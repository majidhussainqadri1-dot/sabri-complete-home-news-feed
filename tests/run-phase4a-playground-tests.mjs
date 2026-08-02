import dns from 'node:dns';
import path from 'node:path';
import process from 'node:process';
import { runCLI } from '@wp-playground/cli';

dns.setDefaultResultOrder('ipv4first');

const phpVersion = process.env.SABRI_PLAYGROUND_PHP || '8.3';
const wpVersion = process.env.SABRI_PLAYGROUND_WP || 'latest';
const packagePath = process.env.SABRI_PLUGIN_ZIP || '';
const pluginPath = 'sabri-complete-home-news-feed/sabri-complete-home-news-feed.php';
const virtualZip = '/tmp/sabri-phase4a-candidate.zip';
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
			// runCLI mutates its options while preparing internal mounts. A fresh
			// clone prevents failed attempts from leaking stale temp paths into retries.
			return await runCLI(structuredClone(options));
		} catch (error) {
			lastError = error;
			if (attempt === attempts) break;
			const waitMilliseconds = attempt * 5000;
			console.warn(`WordPress Playground boot attempt ${attempt} failed; retrying in ${waitMilliseconds / 1000} seconds.`);
			await delay(waitMilliseconds);
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
	const options = {
		command: 'server', php: phpVersion, wp: wpVersion, debug: true, login: false,
	};
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
			if ( is_wp_error( $result ) ) { echo wp_json_encode( array( 'error' => $result->get_error_message() ) ); return; }
			$activation = activate_plugin( '${pluginPath}', '', false, false );
			echo wp_json_encode( array(
				'error' => is_wp_error( $activation ) ? $activation->get_error_message() : '',
				'active' => is_plugin_active( '${pluginPath}' ),
			) );
		`));
		assert(!install.error && install.active, `Packaged Phase 4A installation failed: ${JSON.stringify(install)}`);
	}

	// String.raw preserves PHP namespace separators inside the JavaScript template.
	const result = JSON.parse(await php(String.raw`
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
						'account_class' => 'member',
						'membership_type' => 'member',
						'professional_verified' => false,
						'public_profile_allowed' => true,
						'suspended' => false,
					);
				}
			}
		}
		$user_specs = array(
			'owner' => array( 'phase4_owner', 'phase4-owner@example.test' ),
			'other' => array( 'phase4_other', 'phase4-other@example.test' ),
			'editor' => array( 'phase4_editor', 'phase4-editor@example.test' ),
			'medical' => array( 'phase4_medical', 'phase4-medical@example.test' ),
		);
		$ids = array();
		foreach ( $user_specs as $key => $spec ) {
			$ids[ $key ] = wp_create_user( $spec[0], wp_generate_password( 24 ), $spec[1] );
			if ( is_wp_error( $ids[ $key ] ) ) { echo wp_json_encode( array( 'error' => $ids[ $key ]->get_error_message() ) ); return; }
		}

		$owner = new WP_User( $ids['owner'] );
		$other = new WP_User( $ids['other'] );
		$editor = new WP_User( $ids['editor'] );
		$medical = new WP_User( $ids['medical'] );
		foreach ( array( $owner, $other ) as $user ) {
			foreach ( array( 'read_editorial_news', 'create_editorial_news', 'edit_own_editorial_news', 'submit_editorial_news' ) as $cap ) { $user->add_cap( $cap ); }
		}
		foreach ( array(
			'read_editorial_news', 'create_editorial_news', 'edit_own_editorial_news', 'edit_others_editorial_news',
			'review_editorial_news', 'fact_check_editorial_news', 'medical_review_editorial_news',
			'publish_editorial_news', 'manage_news_corrections', 'manage_breaking_news',
			'retract_editorial_news', 'translate_editorial_news'
		) as $cap ) { $editor->add_cap( $cap ); }
		$medical->add_cap( 'read_editorial_news' );
		$medical->add_cap( 'medical_review_editorial_news' );

		$own_draft = wp_insert_post( array( 'post_type'=>\Sabri\HomeNewsFeed\Phase4Contracts::POST_TYPE, 'post_status'=>'draft', 'post_title'=>'Owner Draft', 'post_author'=>$ids['owner'] ) );
		$other_draft = wp_insert_post( array( 'post_type'=>\Sabri\HomeNewsFeed\Phase4Contracts::POST_TYPE, 'post_status'=>'draft', 'post_title'=>'Other Draft', 'post_author'=>$ids['other'] ) );
		$own_published = wp_insert_post( array( 'post_type'=>\Sabri\HomeNewsFeed\Phase4Contracts::POST_TYPE, 'post_status'=>'publish', 'post_title'=>'Owner Published', 'post_author'=>$ids['owner'] ) );
		foreach ( array( $own_draft, $other_draft, $own_published ) as $post_id ) {
			if ( is_wp_error( $post_id ) ) { echo wp_json_encode( array( 'error' => $post_id->get_error_message() ) ); return; }
		}
		update_post_meta( $other_draft, '_sabri_news_medical_reviewer_id', $ids['medical'] );

		wp_set_current_user( $ids['owner'] );
		$owner_own_edit = current_user_can( 'edit_editorial_news', $own_draft );
		$owner_other_edit = current_user_can( 'edit_editorial_news', $other_draft );
		$owner_published_edit = current_user_can( 'edit_editorial_news', $own_published );
		$owner_delete = current_user_can( 'delete_post', $own_draft );
		$owner_basic_meta = \Sabri\HomeNewsFeed\EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_summary', $own_draft, $ids['owner'] );
		$owner_other_meta = \Sabri\HomeNewsFeed\EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_summary', $other_draft, $ids['owner'] );
		$owner_workflow_meta = \Sabri\HomeNewsFeed\EditorialNewsPostType::meta_auth_callback( false, \Sabri\HomeNewsFeed\Phase4Contracts::WORKFLOW_META_KEY, $own_draft, $ids['owner'] );
		$owner_retraction_meta = \Sabri\HomeNewsFeed\EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_retraction_status', $own_draft, $ids['owner'] );
		$owner_unknown_meta = \Sabri\HomeNewsFeed\EditorialNewsPostType::meta_auth_callback( false, '_unknown_news_meta', $own_draft, $ids['owner'] );

		wp_set_current_user( $ids['editor'] );
		$editor_other_edit = current_user_can( 'edit_editorial_news', $other_draft );
		$editor_published_edit = current_user_can( 'edit_editorial_news', $own_published );
		$editor_delete = current_user_can( 'delete_post', $other_draft );
		$editor_basic_meta = \Sabri\HomeNewsFeed\EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_summary', $other_draft, $ids['editor'] );
		$editor_workflow_meta = \Sabri\HomeNewsFeed\EditorialNewsPostType::meta_auth_callback( false, \Sabri\HomeNewsFeed\Phase4Contracts::WORKFLOW_META_KEY, $other_draft, $ids['editor'] );
		$editor_reviewer_assignment = \Sabri\HomeNewsFeed\EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_medical_reviewer_id', $other_draft, $ids['editor'] );

		wp_set_current_user( $ids['medical'] );
		$medical_assigned = \Sabri\HomeNewsFeed\EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_medical_review_status', $other_draft, $ids['medical'] );
		$medical_unassigned = \Sabri\HomeNewsFeed\EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_medical_review_status', $own_draft, $ids['medical'] );
		$medical_self_assign = \Sabri\HomeNewsFeed\EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_medical_reviewer_id', $other_draft, $ids['medical'] );

		wp_set_current_user( $ids['editor'] );
		\Sabri\HomeNewsFeed\SafeMode::set_emergency_disabled( true );
		$emergency_write = current_user_can( 'edit_editorial_news', $other_draft );
		$emergency_read = current_user_can( 'read_editorial_news' );
		\Sabri\HomeNewsFeed\SafeMode::set_emergency_disabled( false );

		$definition_disabled = \Sabri\HomeNewsFeed\EditorialNewsPostType::definition();
		\Sabri\HomeNewsFeed\NewsFeatureSettings::update( array( 'editorial_news_enabled' => 1 ) );
		$definition_enabled = \Sabri\HomeNewsFeed\EditorialNewsPostType::definition();

		$snapshot_before = \Sabri\HomeNewsFeed\Snapshot::latest();
		deactivate_plugins( '${pluginPath}', true );
		$reactivation = activate_plugin( '${pluginPath}', '', false, false );
		$snapshot_after = \Sabri\HomeNewsFeed\Snapshot::latest();

		echo wp_json_encode( array(
			'active'=>is_plugin_active('${pluginPath}'), 'reactivation_error'=>is_wp_error($reactivation)?$reactivation->get_error_message():'',
			'owner_own_edit'=>$owner_own_edit, 'owner_other_edit'=>$owner_other_edit, 'owner_published_edit'=>$owner_published_edit,
			'owner_delete'=>$owner_delete, 'owner_basic_meta'=>$owner_basic_meta, 'owner_other_meta'=>$owner_other_meta,
			'owner_workflow_meta'=>$owner_workflow_meta, 'owner_retraction_meta'=>$owner_retraction_meta, 'owner_unknown_meta'=>$owner_unknown_meta,
			'editor_other_edit'=>$editor_other_edit, 'editor_published_edit'=>$editor_published_edit, 'editor_delete'=>$editor_delete,
			'editor_basic_meta'=>$editor_basic_meta, 'editor_workflow_meta'=>$editor_workflow_meta, 'editor_reviewer_assignment'=>$editor_reviewer_assignment,
			'medical_assigned'=>$medical_assigned, 'medical_unassigned'=>$medical_unassigned, 'medical_self_assign'=>$medical_self_assign,
			'emergency_write'=>$emergency_write, 'emergency_read'=>$emergency_read,
			'disabled_public'=>(bool)$definition_disabled['publicly_queryable'], 'enabled_public'=>(bool)$definition_enabled['publicly_queryable'],
			'map_meta_cap'=>(bool)$definition_enabled['map_meta_cap'], 'delete_cap'=>$definition_enabled['capabilities']['delete_post'],
			'contract_version'=>get_option('sabri_feed_phase4_contract_version',''),
			'terms_version'=>get_option(\Sabri\HomeNewsFeed\NewsTaxonomies::TERM_VERSION_OPTION,''),
			'snapshot_created_same'=>isset($snapshot_before['created_at'],$snapshot_after['created_at'])&&$snapshot_before['created_at']===$snapshot_after['created_at'],
			'snapshot_caps_same'=>isset($snapshot_before['capability_roles'],$snapshot_after['capability_roles'])&&$snapshot_before['capability_roles']===$snapshot_after['capability_roles'],
		) );
	`));

	assert(!result.error, `Setup failed: ${JSON.stringify(result)}`);
	assert(result.active && !result.reactivation_error, `Plugin reactivation failed: ${JSON.stringify(result)}`);
	assert(result.owner_own_edit === true && result.owner_other_edit === false, 'Own/other ownership mapping failed.');
	assert(result.owner_published_edit === false, 'Owner without correction authority can edit a published article.');
	assert(result.owner_delete === false && result.editor_delete === false, 'Core destructive deletion was not denied.');
	assert(result.owner_basic_meta === true && result.owner_other_meta === false, 'Basic metadata ownership boundary failed.');
	assert(result.owner_workflow_meta === false && result.owner_retraction_meta === false && result.owner_unknown_meta === false, 'Protected metadata was writable by an ordinary author.');
	assert(result.editor_other_edit === true && result.editor_published_edit === true, 'Editorial object authority mapping failed.');
	assert(result.editor_basic_meta === true && result.editor_workflow_meta === true && result.editor_reviewer_assignment === true, 'Editorial protected metadata authority failed.');
	assert(result.medical_assigned === true && result.medical_unassigned === false && result.medical_self_assign === false, 'Assigned Medical Reviewer boundary failed.');
	assert(result.emergency_write === false && result.emergency_read === true, 'Emergency Disable did not close writes while preserving reads.');
	assert(result.disabled_public === false && result.enabled_public === true, 'Master Editorial News gate did not control public routing.');
	assert(result.map_meta_cap === true && result.delete_cap === 'delete_editorial_news', 'Post type capability boundaries are incorrect.');
	assert(result.contract_version === '1.2.0-4A' && result.terms_version === '1.2.0-4A', 'Verified activation markers are incomplete.');
	assert(result.snapshot_created_same && result.snapshot_caps_same, 'Reactivation overwrote the immutable rollback baseline.');

	console.log(`Phase 4A ${packagePath ? 'packaged' : 'source'} WordPress security tests passed on WordPress ${wpVersion} / PHP ${phpVersion}.`);
} finally {
	if (server && typeof server[Symbol.asyncDispose] === 'function') await server[Symbol.asyncDispose]();
}
