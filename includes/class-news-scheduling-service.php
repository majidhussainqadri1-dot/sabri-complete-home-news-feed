<?php
/**
 * Phase 4B Editorial News scheduling foundations.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Creates retry-safe publication-preparation schedules without auto-publishing. */
final class NewsSchedulingService {
	const META_KEY = '_sabri_news_scheduled_at_utc';
	const DUE_META_KEY = '_sabri_news_schedule_due';
	const HOOK = 'sabri_hnf_news_publication_prepare';

	/** Register the non-publishing preparation hook. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( self::HOOK, array( __CLASS__, 'mark_due' ), 10, 1 );
		}
	}

	/** Normalize an explicitly zoned datetime to canonical UTC storage. */
	public static function normalize_utc( $value ) {
		if ( ! is_string( $value ) || '' === $value || strlen( $value ) > 35 ) {
			return '';
		}
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value ) ) {
			return '';
		}
		try {
			$date = new \DateTimeImmutable( $value );
			return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $error ) {
			unset( $error );
			return '';
		}
	}

	/** Create or replace one exact article schedule. */
	public static function schedule( $post_id, $value ) {
		$post_id = function_exists( 'absint' ) ? absint( $post_id ) : max( 0, (int) $post_id );
		$utc = self::normalize_utc( $value );
		if ( $post_id < 1 || '' === $utc || ! NewsPolicy::can_edit( $post_id ) || ! function_exists( 'current_user_can' ) || ! current_user_can( 'schedule_editorial_news' ) ) {
			return self::result( false, 'schedule_authorization_or_value_invalid' );
		}
		if ( function_exists( 'get_post_type' ) && Phase4Contracts::POST_TYPE !== get_post_type( $post_id ) ) {
			return self::result( false, 'schedule_wrong_post_type' );
		}
		$timestamp = strtotime( $utc . ' UTC' );
		if ( false === $timestamp || $timestamp <= time() + 60 ) {
			return self::result( false, 'schedule_must_be_future' );
		}
		$current_state = function_exists( 'get_post_meta' ) ? NewsStatuses::sanitize_state( get_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, true ) ) : '';
		$current_state = $current_state ? $current_state : 'draft';
		if ( 'scheduled' !== $current_state && ! NewsWorkflow::can_transition( $current_state, 'scheduled', $post_id ) ) {
			return self::result( false, 'schedule_transition_denied', array( 'from' => $current_state ) );
		}
		$existing_utc = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $post_id, self::META_KEY, true ) : '';
		$existing_event = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( self::HOOK, array( $post_id ) ) : false;
		if ( $existing_utc === $utc && ( false === $existing_event || (int) $existing_event === (int) $timestamp ) ) {
			return self::result( true, 'schedule_unchanged', array( 'post_id' => $post_id, 'scheduled_at_utc' => $utc ) );
		}
		if ( $existing_event && function_exists( 'wp_unschedule_event' ) ) {
			wp_unschedule_event( $existing_event, self::HOOK, array( $post_id ) );
		}
		if ( function_exists( 'wp_schedule_single_event' ) && ! wp_schedule_single_event( $timestamp, self::HOOK, array( $post_id ), true ) ) {
			return self::result( false, 'schedule_event_failed' );
		}
		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, self::META_KEY, $utc );
			update_post_meta( $post_id, self::DUE_META_KEY, 0 );
			update_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, 'scheduled' );
		}
		if ( function_exists( 'wp_update_post' ) ) {
			wp_update_post(
				array(
					'ID' => $post_id,
					'post_status' => 'future',
					'post_date_gmt' => $utc,
					'post_date' => function_exists( 'get_date_from_gmt' ) ? get_date_from_gmt( $utc ) : $utc,
				),
				true
			);
		}
		NewsAudit::record( $post_id, 'schedule_created', array( 'scheduled_at_utc' => $utc ) );
		return self::result( true, 'schedule_created', array( 'post_id' => $post_id, 'scheduled_at_utc' => $utc, 'timestamp' => $timestamp ) );
	}

	/** Cancel one schedule and return the item to publication-ready state. */
	public static function cancel( $post_id ) {
		$post_id = function_exists( 'absint' ) ? absint( $post_id ) : max( 0, (int) $post_id );
		if ( $post_id < 1 || ! NewsPolicy::can_edit( $post_id ) || ! function_exists( 'current_user_can' ) || ! current_user_can( 'schedule_editorial_news' ) ) {
			return self::result( false, 'schedule_cancel_denied' );
		}
		$event = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( self::HOOK, array( $post_id ) ) : false;
		if ( $event && function_exists( 'wp_unschedule_event' ) ) {
			wp_unschedule_event( $event, self::HOOK, array( $post_id ) );
		}
		if ( function_exists( 'delete_post_meta' ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			delete_post_meta( $post_id, self::DUE_META_KEY );
		}
		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, 'ready-for-publication' );
		}
		if ( function_exists( 'wp_update_post' ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'pending' ), true );
		}
		NewsAudit::record( $post_id, 'schedule_cancelled' );
		return self::result( true, 'schedule_cancelled', array( 'post_id' => $post_id ) );
	}

	/** Mark an event due; Phase 4B deliberately does not publish it. */
	public static function mark_due( $post_id ) {
		$post_id = function_exists( 'absint' ) ? absint( $post_id ) : max( 0, (int) $post_id );
		if ( $post_id < 1 || ( function_exists( 'get_post_type' ) && Phase4Contracts::POST_TYPE !== get_post_type( $post_id ) ) ) {
			return false;
		}
		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, self::DUE_META_KEY, 1 );
		}
		NewsAudit::record( $post_id, 'schedule_due', array( 'publication_performed' => false ) );
		return true;
	}

	/** Read-only schedule diagnostics for administrators. */
	public static function diagnostics( $post_id ) {
		$post_id = function_exists( 'absint' ) ? absint( $post_id ) : max( 0, (int) $post_id );
		$utc = $post_id > 0 && function_exists( 'get_post_meta' ) ? (string) get_post_meta( $post_id, self::META_KEY, true ) : '';
		$event = $post_id > 0 && function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( self::HOOK, array( $post_id ) ) : false;
		return array(
			'post_id' => $post_id,
			'scheduled_at_utc' => $utc,
			'event_timestamp' => $event ? (int) $event : 0,
			'due' => $post_id > 0 && function_exists( 'get_post_meta' ) ? (bool) get_post_meta( $post_id, self::DUE_META_KEY, true ) : false,
			'auto_publish_enabled' => false,
		);
	}

	/** Stable service result. */
	private static function result( $success, $code, array $data = array() ) {
		return array( 'success' => (bool) $success, 'code' => (string) $code, 'data' => $data );
	}
}
