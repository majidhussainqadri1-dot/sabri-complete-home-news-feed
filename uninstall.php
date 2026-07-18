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

$settings = function_exists( 'get_option' ) ? get_option( 'sabri_feed_settings', array() ) : array();
$retain   = true;

if ( is_array( $settings ) && isset( $settings['privacy']['retain_data_on_uninstall'] ) ) {
	$retain = ! empty( $settings['privacy']['retain_data_on_uninstall'] );
}

if ( $retain ) {
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
	);

	foreach ( $tables as $table ) {
		$wpdb->query( 'DROP TABLE IF EXISTS `' . str_replace( '`', '', $table ) . '`' );
	}
}
