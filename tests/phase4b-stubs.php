<?php
/** Phase 4B-only WordPress behavior shims for lean contract tests. */

global $sabri_test_current_caps;
foreach ( array(
	'create_editorial_news', 'edit_editorial_news', 'edit_own_editorial_news', 'edit_others_editorial_news',
	'submit_editorial_news', 'review_editorial_news', 'fact_check_editorial_news', 'medical_review_editorial_news',
	'publish_editorial_news', 'schedule_editorial_news', 'manage_breaking_news', 'manage_news_sources',
	'manage_news_corrections', 'retract_editorial_news', 'translate_editorial_news', 'manage_news_taxonomies',
	'manage_news_settings', 'read_editorial_news', 'read_editorial_news_item', 'upload_files',
) as $sabri_phase4b_capability ) {
	$sabri_test_current_caps[ $sabri_phase4b_capability ] = true;
}

if ( ! function_exists( 'user_can' ) ) {
	function user_can( $user_id, $capability ) {
		global $sabri_test_user_roles, $sabri_test_roles;
		foreach ( isset( $sabri_test_user_roles[ (int) $user_id ] ) ? $sabri_test_user_roles[ (int) $user_id ] : array() as $role_slug ) {
			if ( ! empty( $sabri_test_roles[ $role_slug ]->capabilities[ $capability ] ) ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! isset( $sabri_test_scheduled_events ) ) {
	$sabri_test_scheduled_events = array();
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = array() ) {
		global $sabri_test_scheduled_events;
		$key = $hook . '|' . md5( serialize( $args ) );
		return isset( $sabri_test_scheduled_events[ $key ] ) ? $sabri_test_scheduled_events[ $key ] : false;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( $timestamp, $hook, $args = array(), $wp_error = false ) {
		global $sabri_test_scheduled_events;
		unset( $wp_error );
		$key = $hook . '|' . md5( serialize( $args ) );
		$sabri_test_scheduled_events[ $key ] = (int) $timestamp;
		return true;
	}
}

if ( ! function_exists( 'wp_unschedule_event' ) ) {
	function wp_unschedule_event( $timestamp, $hook, $args = array(), $wp_error = false ) {
		global $sabri_test_scheduled_events;
		unset( $timestamp, $wp_error );
		$key = $hook . '|' . md5( serialize( $args ) );
		unset( $sabri_test_scheduled_events[ $key ] );
		return true;
	}
}

if ( ! function_exists( 'set_post_thumbnail' ) ) {
	function set_post_thumbnail( $post_id, $attachment_id ) {
		return update_post_meta( $post_id, '_thumbnail_id', (int) $attachment_id );
	}
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	function get_post_thumbnail_id( $post_id ) {
		return (int) get_post_meta( $post_id, '_thumbnail_id', true );
	}
}

if ( ! function_exists( 'get_date_from_gmt' ) ) {
	function get_date_from_gmt( $date ) { return $date; }
}
