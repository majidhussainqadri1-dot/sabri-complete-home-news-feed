<?php
/**
 * Privacy lifecycle for File 21 next-generation private user state.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Exports and erases private topic/reading/data-saver state owned by File 21. */
final class NextGenerationPrivacy {
	/** Register WordPress privacy callbacks. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
			add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		}
	}

	/** Register exporter. */
	public static function register_exporter( $exporters ) {
		$exporters = is_array( $exporters ) ? $exporters : array();
		$exporters['sabri-hnf-next-generation-state'] = array(
			'exporter_friendly_name' => __( 'Sabri Home and News Feed private reading preferences', 'sabri-complete-home-news-feed' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/** Register eraser. */
	public static function register_eraser( $erasers ) {
		$erasers = is_array( $erasers ) ? $erasers : array();
		$erasers['sabri-hnf-next-generation-state'] = array(
			'eraser_friendly_name' => __( 'Sabri Home and News Feed private reading preferences', 'sabri-complete-home-news-feed' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/** Export the requesting account's bounded private state without unrelated profile data. */
	public static function export( $email_address, $page = 1 ) {
		unset( $page );
		$user = function_exists( 'get_user_by' ) ? get_user_by( 'email', sanitize_email( $email_address ) ) : false;
		if ( ! $user || empty( $user->ID ) ) {
			return array( 'data' => array(), 'done' => true );
		}

		$user_id   = absint( $user->ID );
		$has_state = false;
		if ( function_exists( 'metadata_exists' ) ) {
			$has_state = metadata_exists( 'user', $user_id, NextGenerationFeed::USER_META );
		} elseif ( function_exists( 'get_user_meta' ) ) {
			$raw_state = get_user_meta( $user_id, NextGenerationFeed::USER_META, true );
			$has_state = is_array( $raw_state ) && ! empty( $raw_state );
		}
		if ( ! $has_state ) {
			return array( 'data' => array(), 'done' => true );
		}

		$state = NextGenerationFeed::user_state( $user_id );
		$data  = array(
			array( 'name' => __( 'Followed topics', 'sabri-complete-home-news-feed' ), 'value' => implode( ', ', (array) $state['topics'] ) ),
			array( 'name' => __( 'Reading progress', 'sabri-complete-home-news-feed' ), 'value' => self::json( $state['progress'] ) ),
			array( 'name' => __( 'Read later post IDs', 'sabri-complete-home-news-feed' ), 'value' => implode( ', ', array_map( 'absint', (array) $state['queue'] ) ) ),
			array( 'name' => __( 'Offline pack post IDs', 'sabri-complete-home-news-feed' ), 'value' => implode( ', ', array_map( 'absint', (array) $state['offline'] ) ) ),
			array( 'name' => __( 'Low-bandwidth preference', 'sabri-complete-home-news-feed' ), 'value' => ! empty( $state['low_bandwidth'] ) ? '1' : '0' ),
			array( 'name' => __( 'Data Saver preference', 'sabri-complete-home-news-feed' ), 'value' => ! empty( $state['data_saver'] ) ? '1' : '0' ),
			array( 'name' => __( 'Last catch-up time (UTC)', 'sabri-complete-home-news-feed' ), 'value' => ! empty( $state['last_catch_up'] ) ? gmdate( 'c', absint( $state['last_catch_up'] ) ) : '' ),
			array( 'name' => __( 'Personal Feed Recipe', 'sabri-complete-home-news-feed' ), 'value' => self::json( $state['recipe'] ) ),
		);
		return array(
			'data' => array(
				array(
					'group_id'    => 'sabri-hnf-next-generation-state',
					'group_label' => __( 'Home and News Feed private reading preferences', 'sabri-complete-home-news-feed' ),
					'item_id'     => 'user-' . $user_id,
					'data'        => $data,
				),
			),
			'done' => true,
		);
	}

	/** Erase File 21-owned private preference/progress state for the requesting account. */
	public static function erase( $email_address, $page = 1 ) {
		unset( $page );
		$user = function_exists( 'get_user_by' ) ? get_user_by( 'email', sanitize_email( $email_address ) ) : false;
		if ( ! $user || empty( $user->ID ) ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$had_state = function_exists( 'metadata_exists' ) ? metadata_exists( 'user', absint( $user->ID ), NextGenerationFeed::USER_META ) : true;
		$removed   = function_exists( 'delete_user_meta' ) ? delete_user_meta( absint( $user->ID ), NextGenerationFeed::USER_META ) : false;
		return array(
			'items_removed'  => $had_state ? (bool) $removed : false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/** Stable JSON for privacy exports. */
	private static function json( $value ) {
		return function_exists( 'wp_json_encode' ) ? (string) wp_json_encode( $value ) : (string) json_encode( $value );
	}
}
