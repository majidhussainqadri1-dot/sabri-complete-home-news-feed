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

try {
	server = await runCLI({
		command: 'server',
		php: phpVersion,
		wp: wpVersion,
		debug: true,
		login: false,
		mount: [{ hostPath: path.resolve('.'), vfsPath: '/wordpress/wp-content/plugins/sabri-complete-home-news-feed' }],
		blueprint: { steps: [{ step: 'activatePlugin', pluginPath: `/wordpress/wp-content/plugins/${pluginPath}` }] },
	});

	const result = JSON.parse(await php(`
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$owner_id = wp_create_user( 'phase4_owner', wp_generate_password( 24 ), 'phase4-owner@example.test' );
		$other_id = wp_create_user( 'phase4_other', wp_generate_password( 24 ), 'phase4-other@example.test' );
		$editor_id = wp_create_user( 'phase4_editor', wp_generate_password( 24 ), 'phase4-editor@example.test' );
		foreach ( array( $owner_id, $other_id, $editor_id ) as $user_id ) {
			if ( is_wp_error( $user_id ) ) {
				echo wp_json_encode( array( 'error' => $user_id->get_error_message() ) );
				return;
			}
		}

		$owner = new WP_User( $owner_id );
		$other = new WP_User( $other_id );
		$editor = new WP_User( $editor_id );
		foreach ( array( $owner, $other ) as $user ) {
			foreach ( array( 'read_editorial_news', 'create_editorial_news', 'edit_own_editorial_news', 'submit_editorial_news' ) as $cap ) {
				$user->add_cap( $cap );
			}
		}
		foreach ( array( 'read_editorial_news', 'create_editorial_news', 'edit_own_editorial_news', 'edit_others_editorial_news', 'review_editorial_news', 'manage_news_corrections', 'schedule_editorial_news' ) as $cap ) {
			$editor->add_cap( $cap );
		}

		$own_draft = wp_insert_post( array(
			'post_type' => \\Sabri\\HomeNewsFeed\\Phase4Contracts::POST_TYPE,
			'post_status' => 'draft',
			'post_title' => 'Owner Draft',
			'post_author' => $owner_id,
		) );
		$other_draft = wp_insert_post( array(
			'post_type' => \\Sabri\\HomeNewsFeed\\Phase4Contracts::POST_TYPE,
			'post_status' => 'draft',
			'post_title' => 'Other Draft',
			'post_author' => $other_id,
		) );
		$own_published = wp_insert_post( array(
			'post_type' => \\Sabri\\HomeNewsFeed\\Phase4Contracts::POST_TYPE,
			'post_status' => 'publish',
			'post_title' => 'Owner Published',
			'post_author' => $owner_id,
		) );

		wp_set_current_user( $owner_id );
		$owner_own_edit = current_user_can( 'edit_editorial_news', $own_draft );
		$owner_other_edit = current_user_can( 'edit_editorial_news', $other_draft );
		$owner_published_edit = current_user_can( 'edit_editorial_news', $own_published );
		$owner_delete = current_user_can( 'delete_post', $own_draft );
		$owner_meta = \\Sabri\\HomeNewsFeed\\EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_summary', $own_draft, $owner_id );
		$owner_other_meta = \\Sabri\\HomeNewsFeed\\EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_summary', $other_draft, $owner_id );

		wp_set_current_user( $editor_id );
		$editor_other_edit = current_user_can( 'edit_editorial_news', $other_draft );
		$editor_published_edit = current_user_can( 'edit_editorial_news', $own_published );
		$editor_delete = current_user_can( 'delete_post', $other_draft );
		$editor_other_meta = \\Sabri\\HomeNewsFeed\\EditorialNewsPostType::meta_auth_callback( false, '_sabri_news_summary', $other_draft, $editor_id );

		\\Sabri\\HomeNewsFeed\\SafeMode::set_emergency_disabled( true );
		$emergency_write = current_user_can( 'edit_editorial_news', $other_draft );
		$emergency_read = current_user_can( 'read_editorial_news' );
		\\Sabri\\HomeNewsFeed\\SafeMode::set_emergency_disabled( false );

		$definition_disabled = \\Sabri\\HomeNewsFeed\\EditorialNewsPostType::definition();
		$phase4_features = \\Sabri\\HomeNewsFeed\\NewsFeatureSettings::defaults();
		$phase4_features['editorial_news_enabled'] = 1;
		\\Sabri\\HomeNewsFeed\\NewsFeatureSettings::update( $phase4_features );
		$definition_enabled = \\Sabri\\HomeNewsFeed\\EditorialNewsPostType::definition();

		$snapshot_before = \\Sabri\\HomeNewsFeed\\Snapshot::latest();
		deactivate_plugins( '${pluginPath}', true );
		$reactivation = activate_plugin( '${pluginPath}', '', false, false );
		$snapshot_after = \\Sabri\\HomeNewsFeed\\Snapshot::latest();

		echo wp_json_encode( array(
			'active' => is_plugin_active( '${pluginPath}' ),
			'reactivation_error' => is_wp_error( $reactivation ) ? $reactivation->get_error_message() : '',
			'owner_own_edit' => $owner_own_edit,
			'owner_other_edit' => $owner_other_edit,
			'owner_published_edit' => $owner_published_edit,
			'owner_delete' => $owner_delete,
			'owner_meta' => $owner_meta,
			'owner_other_meta' => $owner_other_meta,
			'editor_other_edit' => $editor_other_edit,
			'editor_published_edit' => $editor_published_edit,
			'editor_delete' => $editor_delete,
			'editor_other_meta' => $editor_other_meta,
			'emergency_write' => $emergency_write,
			'emergency_read' => $emergency_read,
			'disabled_public' => (bool) $definition_disabled['publicly_queryable'],
			'enabled_public' => (bool) $definition_enabled['publicly_queryable'],
			'map_meta_cap' => (bool) $definition_enabled['map_meta_cap'],
			'delete_cap' => $definition_enabled['capabilities']['delete_post'],
			'contract_version' => get_option( 'sabri_feed_phase4_contract_version', '' ),
			'terms_version' => get_option( \\Sabri\\HomeNewsFeed\\NewsTaxonomies::TERM_VERSION_OPTION, '' ),
			'snapshot_created_same' => isset( $snapshot_before['created_at'], $snapshot_after['created_at'] ) && $snapshot_before['created_at'] === $snapshot_after['created_at'],
			'snapshot_caps_same' => isset( $snapshot_before['capability_roles'], $snapshot_after['capability_roles'] ) && $snapshot_before['capability_roles'] === $snapshot_after['capability_roles'],
		) );
	`));

	assert(!result.error, `Setup failed: ${JSON.stringify(result)}`);
	assert(result.active && !result.reactivation_error, `Plugin reactivation failed: ${JSON.stringify(result)}`);
	assert(result.owner_own_edit === true, 'Owner cannot edit own draft.');
	assert(result.owner_other_edit === false, 'Owner can edit another author draft.');
	assert(result.owner_published_edit === false, 'Owner without correction authority can edit a published article.');
	assert(result.owner_delete === false && result.editor_delete === false, 'Core destructive deletion was not denied.');
	assert(result.owner_meta === true && result.owner_other_meta === false, 'Metadata ownership boundary failed.');
	assert(result.editor_other_edit === true && result.editor_published_edit === true && result.editor_other_meta === true, 'Editorial authority mapping failed.');
	assert(result.emergency_write === false && result.emergency_read === true, 'Emergency Disable did not close writes while preserving reads.');
	assert(result.disabled_public === false && result.enabled_public === true, 'Master Editorial News gate did not control public routing.');
	assert(result.map_meta_cap === true && result.delete_cap === 'do_not_allow', 'Post type capability boundaries are incorrect.');
	assert(result.contract_version === '1.2.0-4A' && result.terms_version === '1.2.0-4A', 'Verified activation markers are incomplete.');
	assert(result.snapshot_created_same && result.snapshot_caps_same, 'Reactivation overwrote the immutable rollback baseline.');

	console.log(`Phase 4A WordPress capability tests passed on WordPress ${wpVersion} / PHP ${phpVersion}.`);
} finally {
	if (server && typeof server[Symbol.asyncDispose] === 'function') await server[Symbol.asyncDispose]();
}
