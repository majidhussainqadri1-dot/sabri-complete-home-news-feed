<?php
/**
 * Phase 4B Editorial News append-only audit records.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Records consequential editorial actions without public exposure. */
final class NewsAudit {
	const META_KEY = '_sabri_news_audit_event';

	/** Register audit foundations. */
	public static function register() {
		// Services record events explicitly after successful operations.
	}

	/** Append one bounded private audit event. */
	public static function record( $post_id, $action, array $context = array() ) {
		$post_id = function_exists( 'absint' ) ? absint( $post_id ) : max( 0, (int) $post_id );
		$action  = self::strict_token( $action );
		if ( $post_id < 1 || '' === $action || ( function_exists( 'get_post_type' ) && Phase4Contracts::POST_TYPE !== get_post_type( $post_id ) ) ) {
			return array( 'success' => false, 'code' => 'audit_event_rejected' );
		}
		$event = array(
			'id' => hash( 'sha256', $post_id . '|' . $action . '|' . microtime( true ) . '|' . ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( '', true ) ) ),
			'action' => $action,
			'user_id' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
			'created_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			'context' => self::sanitize_context( $context ),
		);

		if ( function_exists( 'add_post_meta' ) ) {
			$stored = add_post_meta( $post_id, self::META_KEY, $event, false );
		} else {
			$option = 'sabri_feed_news_audit_' . $post_id;
			$events = function_exists( 'get_option' ) ? get_option( $option, array() ) : array();
			$events = is_array( $events ) ? $events : array();
			$events[] = $event;
			$events = array_slice( $events, -100 );
			$stored = function_exists( 'update_option' ) ? update_option( $option, $events, false ) : false;
		}
		return array( 'success' => false !== $stored, 'code' => false !== $stored ? 'audit_event_recorded' : 'audit_event_failed', 'event' => $event );
	}

	/** Read a bounded newest-first private audit projection. */
	public static function events( $post_id, $limit = 50 ) {
		$post_id = function_exists( 'absint' ) ? absint( $post_id ) : max( 0, (int) $post_id );
		$limit = max( 1, min( 100, (int) $limit ) );
		if ( $post_id < 1 || ! function_exists( 'current_user_can' ) || ! current_user_can( 'review_editorial_news' ) ) {
			return array();
		}
		if ( function_exists( 'get_post_meta' ) && function_exists( 'add_post_meta' ) ) {
			$events = get_post_meta( $post_id, self::META_KEY, false );
		} else {
			$events = function_exists( 'get_option' ) ? get_option( 'sabri_feed_news_audit_' . $post_id, array() ) : array();
		}
		$events = is_array( $events ) ? array_values( array_filter( $events, 'is_array' ) ) : array();
		return array_reverse( array_slice( $events, -$limit ) );
	}

	/** Recursively retain bounded scalar context only. */
	private static function sanitize_context( array $context ) {
		$out = array();
		foreach ( array_slice( $context, 0, 20, true ) as $key => $value ) {
			$key = function_exists( 'sanitize_key' ) ? sanitize_key( $key ) : preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
			if ( '' === $key ) {
				continue;
			}
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$out[ $key ] = $value;
			} elseif ( is_string( $value ) ) {
				$out[ $key ] = substr( function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : strip_tags( $value ), 0, 500 );
			} elseif ( is_array( $value ) ) {
				$out[ $key ] = self::sanitize_context( $value );
			}
		}
		return $out;
	}

	/** Strict action token validation. */
	private static function strict_token( $value ) {
		return is_string( $value ) && strlen( $value ) <= 80 && 1 === preg_match( '/^[a-z0-9]+(?:_[a-z0-9]+)*$/D', $value ) ? $value : '';
	}
}
