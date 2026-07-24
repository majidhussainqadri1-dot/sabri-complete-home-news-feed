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

	/** Create, replace, or repair one exact article schedule. */
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
			NewsAudit::record( $post_id, 'schedule_failed', array( 'reason' => 'schedule_must_be_future', 'scheduled_at_utc' => $utc ) );
			return self::result( false, 'schedule_must_be_future' );
		}

		$current_state = function_exists( 'get_post_meta' ) ? NewsStatuses::sanitize_state( get_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, true ) ) : '';
		$current_state = $current_state ? $current_state : 'draft';
		if ( 'scheduled' !== $current_state && ! NewsWorkflow::can_transition( $current_state, 'scheduled', $post_id ) ) {
			NewsAudit::record( $post_id, 'schedule_failed', array( 'reason' => 'schedule_transition_denied', 'from_state' => $current_state ) );
			return self::result( false, 'schedule_transition_denied', array( 'from' => $current_state ) );
		}

		$existing_utc = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $post_id, self::META_KEY, true ) : '';
		$existing_event = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( self::HOOK, array( $post_id ) ) : false;
		if ( $existing_utc === $utc && false !== $existing_event && (int) $existing_event === (int) $timestamp ) {
			return self::result( true, 'schedule_unchanged', array( 'post_id' => $post_id, 'scheduled_at_utc' => $utc, 'timestamp' => $timestamp ) );
		}

		$operation = '' === $existing_utc ? 'schedule_created' : ( $existing_utc === $utc ? 'schedule_repaired' : 'schedule_updated' );
		if ( false !== $existing_event && function_exists( 'wp_unschedule_event' ) ) {
			$unscheduled = wp_unschedule_event( $existing_event, self::HOOK, array( $post_id ), true );
			if ( false === $unscheduled || ( function_exists( 'is_wp_error' ) && is_wp_error( $unscheduled ) ) ) {
				NewsAudit::record( $post_id, 'schedule_failed', array( 'reason' => 'old_schedule_unschedule_failed', 'existing_timestamp' => (int) $existing_event ) );
				return self::result( false, 'old_schedule_unschedule_failed' );
			}
		}

		if ( ! function_exists( 'wp_schedule_single_event' ) ) {
			NewsAudit::record( $post_id, 'schedule_failed', array( 'reason' => 'schedule_api_unavailable' ) );
			return self::result( false, 'schedule_api_unavailable' );
		}
		$scheduled_result = wp_schedule_single_event( $timestamp, self::HOOK, array( $post_id ), true );
		if ( false === $scheduled_result || ( function_exists( 'is_wp_error' ) && is_wp_error( $scheduled_result ) ) ) {
			$message = function_exists( 'is_wp_error' ) && is_wp_error( $scheduled_result ) ? $scheduled_result->get_error_message() : '';
			NewsAudit::record( $post_id, 'schedule_failed', array( 'reason' => 'schedule_event_failed', 'message' => $message ) );
			return self::result( false, 'schedule_event_failed', array( 'message' => $message ) );
		}

		$update_result = true;
		if ( function_exists( 'wp_update_post' ) ) {
			$update_result = wp_update_post(
				array(
					'ID' => $post_id,
					'post_status' => 'future',
					'post_date_gmt' => $utc,
					'post_date' => function_exists( 'get_date_from_gmt' ) ? get_date_from_gmt( $utc ) : $utc,
				),
				true
			);
		}
		if ( false === $update_result || ( function_exists( 'is_wp_error' ) && is_wp_error( $update_result ) ) ) {
			if ( function_exists( 'wp_unschedule_event' ) ) {
				wp_unschedule_event( $timestamp, self::HOOK, array( $post_id ), true );
			}
			$message = function_exists( 'is_wp_error' ) && is_wp_error( $update_result ) ? $update_result->get_error_message() : '';
			NewsAudit::record( $post_id, 'schedule_failed', array( 'reason' => 'scheduled_post_update_failed', 'message' => $message ) );
			return self::result( false, 'scheduled_post_update_failed', array( 'message' => $message ) );
		}

		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, self::META_KEY, $utc );
			update_post_meta( $post_id, self::DUE_META_KEY, 0 );
			update_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, 'scheduled' );
		}
		NewsAudit::record( $post_id, $operation, array( 'scheduled_at_utc' => $utc, 'timestamp' => $timestamp ) );
		return self::result( true, $operation, array( 'post_id' => $post_id, 'scheduled_at_utc' => $utc, 'timestamp' => $timestamp ) );
	}

	/** Repair a missing cron event from canonical UTC metadata. */
	public static function repair_missing_event( $post_id ) {
		$post_id = function_exists( 'absint' ) ? absint( $post_id ) : max( 0, (int) $post_id );
		$utc = $post_id > 0 && function_exists( 'get_post_meta' ) ? (string) get_post_meta( $post_id, self::META_KEY, true ) : '';
		if ( '' === $utc ) {
			return self::result( false, 'schedule_metadata_missing' );
		}
		$event = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( self::HOOK, array( $post_id ) ) : false;
		if ( false !== $event ) {
			return self::result( true, 'schedule_event_present', array( 'timestamp' => (int) $event ) );
		}
		return self::schedule( $post_id, str_replace( ' ', 'T', $utc ) . 'Z' );
	}

	/** Cancel one schedule and return the item to publication-ready state. */
	public static function cancel( $post_id ) {
		$post_id = function_exists( 'absint' ) ? absint( $post_id ) : max( 0, (int) $post_id );
		if ( $post_id < 1 || ! NewsPolicy::can_edit( $post_id ) || ! function_exists( 'current_user_can' ) || ! current_user_can( 'schedule_editorial_news' ) ) {
			return self::result( false, 'schedule_cancel_denied' );
		}
		$event = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( self::HOOK, array( $post_id ) ) : false;
		if ( false !== $event && function_exists( 'wp_unschedule_event' ) ) {
			$unscheduled = wp_unschedule_event( $event, self::HOOK, array( $post_id ), true );
			if ( false === $unscheduled || ( function_exists( 'is_wp_error' ) && is_wp_error( $unscheduled ) ) ) {
				NewsAudit::record( $post_id, 'schedule_failed', array( 'reason' => 'schedule_cancel_unschedule_failed' ) );
				return self::result( false, 'schedule_cancel_unschedule_failed' );
			}
		}
		if ( function_exists( 'delete_post_meta' ) ) {
			delete_post_meta( $post_id, self::META_KEY );
			delete_post_meta( $post_id, self::DUE_META_KEY );
		}
		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, 'ready-for-publication' );
		}
		if ( function_exists( 'wp_update_post' ) ) {
			$updated = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'pending' ), true );
			if ( false === $updated || ( function_exists( 'is_wp_error' ) && is_wp_error( $updated ) ) ) {
				NewsAudit::record( $post_id, 'schedule_failed', array( 'reason' => 'schedule_cancel_post_update_failed' ) );
				return self::result( false, 'schedule_cancel_post_update_failed' );
			}
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
		$state = function_exists( 'get_post_meta' ) ? NewsStatuses::sanitize_state( get_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, true ) ) : '';
		if ( 'scheduled' !== $state ) {
			NewsAudit::record( $post_id, 'schedule_failed', array( 'reason' => 'due_event_wrong_state', 'state' => $state ) );
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
		$due = $post_id > 0 && function_exists( 'get_post_meta' ) ? (bool) get_post_meta( $post_id, self::DUE_META_KEY, true ) : false;
		$canonical_timestamp = '' !== $utc ? strtotime( $utc . ' UTC' ) : false;
		return array(
			'post_id' => $post_id,
			'scheduled_at_utc' => $utc,
			'event_timestamp' => false !== $event ? (int) $event : 0,
			'event_missing' => '' !== $utc && false === $event,
			'missed' => false !== $canonical_timestamp && $canonical_timestamp < time() && ! $due,
			'due' => $due,
			'auto_publish_enabled' => false,
		);
	}

	/** Stable service result. */
	private static function result( $success, $code, array $data = array() ) {
		return array( 'success' => (bool) $success, 'code' => (string) $code, 'data' => $data );
	}
}
