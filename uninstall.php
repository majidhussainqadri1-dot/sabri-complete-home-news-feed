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
