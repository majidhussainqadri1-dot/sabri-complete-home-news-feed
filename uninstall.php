<?php
/**
 * Uninstall boundary.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$sabri_hnf_capabilities = array(
	'sabri_feed_create_posts',
	'sabri_feed_publish_posts',
	'sabri_feed_submit_for_review',
	'sabri_feed_moderate_posts',
	'sabri_feed_manage_news',
	'sabri_feed_manage_breaking_news',
	'sabri_feed_manage_settings',
	'sabri_feed_view_analytics',
	'sabri_feed_manage_reports',
	'sabri_feed_run_repairs',
	'sabri_feed_run_migrations',
	'sabri_feed_run_rollbacks',
	'manage_news_sources',
	'verify_news_sources',
	'review_editorial_news',
	'fact_check_editorial_news',
	'medical_review_editorial_news',
	'translate_editorial_news',
	'submit_editorial_news',
	'manage_news_submissions',
	'manage_breaking_news',
	'manage_news_corrections',
	'retract_editorial_news',
	'manage_news_privacy',
	'manage_news_release',
	'view_news_audit',
	'view_news_diagnostics',
);

if ( function_exists( 'wp_roles' ) && function_exists( 'get_role' ) ) {
	$roles = wp_roles();
	if ( $roles && ! empty( $roles->roles ) && is_array( $roles->roles ) ) {
		foreach ( array_keys( $roles->roles ) as $role_slug ) {
			$role = get_role( $role_slug );
			if ( ! $role || ! method_exists( $role, 'remove_cap' ) ) {
				continue;
			}
			foreach ( $sabri_hnf_capabilities as $capability ) {
				$role->remove_cap( $capability );
			}
		}
	}
}

/* Scheduled code must never survive plugin removal, even when data is retained. */
if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
	wp_clear_scheduled_hook( 'sabri_hnf_file22_idempotency_cleanup' );
}

$settings = function_exists( 'get_option' ) ? get_option( 'sabri_feed_settings', array() ) : array();
$retain   = true;
if ( is_array( $settings ) && isset( $settings['privacy']['retain_data_on_uninstall'] ) ) {
	$retain = ! empty( $settings['privacy']['retain_data_on_uninstall'] );
}
if ( $retain ) {
	return;
}

if ( function_exists( 'delete_option' ) ) {
	delete_option( 'sabri_feed_settings' );
}

/* File 21-owned private NG30 preferences are not Phase 5 accountability records. */
if ( function_exists( 'delete_metadata' ) ) {
	delete_metadata( 'user', 0, '_sabri_hnf_ng_user_v1', '', true );
}

/* Phase 5 accountability data requires a second explicit destructive confirmation. */
$phase5_destructive = function_exists( 'get_option' ) ? get_option( 'sabri_feed_phase5_destructive_uninstall_confirmation', '' ) : '';
if ( 'DELETE-PHASE5-EDITORIAL-DATA' !== $phase5_destructive ) {
	return;
}

$options = array(
	'sabri_feed_settings',
	'sabri_feed_schema_version',
	'sabri_feed_activation_snapshot',
	'sabri_feed_capability_mutations',
	'sabri_feed_flush_rewrite_rules',
	'sabri_feed_taxonomy_version',
	'sabri_feed_last_repair_report',
	'sabri_feed_last_migration_report',
	'sabri_feed_last_rollback_report',
	'sabri_feed_phase5_features',
	'sabri_feed_phase5_migration_state',
	'sabri_feed_phase5_migration_lock',
	'sabri_feed_phase5_migration_report',
	'sabri_feed_phase5_schema_install_result',
	'sabri_feed_phase5_capability_mutations',
	'sabri_feed_phase5_destructive_uninstall_confirmation',
	'sabri_hnf_file22_recovery_last_report',
);
foreach ( $options as $option ) {
	if ( function_exists( 'delete_option' ) ) {
		delete_option( $option );
	}
}

if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'query' ) ) {
	$prefix = ! empty( $wpdb->prefix ) ? $wpdb->prefix : 'wp_';
	$tables = array(
		$prefix . 'sabri_feed_reactions',
		$prefix . 'sabri_feed_follows',
		$prefix . 'sabri_feed_saves',
		$prefix . 'sabri_feed_reports',
		$prefix . 'sabri_feed_views',
		$prefix . 'sabri_feed_poll_votes',
		$prefix . 'sabri_feed_audit_log',
		$prefix . 'sabri_news_sources',
		$prefix . 'sabri_news_reviews',
		$prefix . 'sabri_news_submissions',
		$prefix . 'sabri_news_submission_files',
		$prefix . 'sabri_news_corrections',
		$prefix . 'sabri_news_breaking',
		$prefix . 'sabri_news_translations',
		$prefix . 'sabri_news_preview_tokens',
		$prefix . 'sabri_news_rate_limits',
		$prefix . 'sabri_news_audit_integrity',
	);
	foreach ( $tables as $table ) {
		$wpdb->query( 'DROP TABLE IF EXISTS `' . str_replace( '`', '', $table ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	if ( method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'esc_like' ) ) {
		$idempotency_like = $wpdb->esc_like( 'sabri_hnf_file22_idem_' ) . '%';
		$execution_like   = $wpdb->esc_like( 'sabri_hnf_file22_exec_' ) . '%';
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $idempotency_like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $execution_like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s)", '_sabri_hnf_file22_idempotency_hash', '_sabri_hnf_file22_payload_fingerprint' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
