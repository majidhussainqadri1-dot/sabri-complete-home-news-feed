<?php
/**
 * Append-only Phase 5 audit-integrity chain.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Records privacy-minimized chained audit events. */
final class Phase5AuditIntegrity {
	public static function register() {}

	public static function record( $event_type, $object_type = '', $object_id = 0, array $context = array() ) {
		$event_type = Phase5Contracts::slug( str_replace( '_', '-', (string) $event_type ), 64 );
		$object_type = Phase5Contracts::slug( str_replace( '_', '-', (string) $object_type ), 48 );
		$object_id = Phase5Contracts::positive_int( $object_id );
		if ( '' === $event_type ) {
			return 0;
		}
		$context = self::sanitize_context( $context );
		$previous = self::latest_digest();
		$created = gmdate( 'Y-m-d H:i:s' );
		$actor = function_exists( 'get_current_user_id' ) ? max( 0, (int) get_current_user_id() ) : 0;
		$canonical = array(
			'event_type' => $event_type,
			'object_type' => $object_type,
			'object_id' => $object_id,
			'actor_user_id' => $actor,
			'previous_digest' => $previous,
			'context' => $context,
			'created_at' => $created,
		);
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $canonical ) : json_encode( $canonical );
		$digest = hash( 'sha256', (string) $json );
		return Phase5Repository::insert(
			'audit_integrity',
			array(
				'event_type' => $event_type,
				'object_type' => $object_type,
				'object_id' => $object_id,
				'actor_user_id' => $actor,
				'previous_digest' => $previous,
				'event_digest' => $digest,
				'context_json' => $json,
				'created_at' => $created,
			)
		);
	}

	public static function verify_chain( $limit = 1000 ) {
		$rows = Phase5Repository::query( 'audit_integrity', array(), min( 1000, max( 1, (int) $limit ) ), 0, 'id', 'ASC' );
		$previous = '';
		foreach ( $rows as $row ) {
			if ( ! hash_equals( $previous, isset( $row['previous_digest'] ) ? (string) $row['previous_digest'] : '' ) ) {
				return array( 'success' => false, 'id' => isset( $row['id'] ) ? (int) $row['id'] : 0 );
			}
			$context = isset( $row['context_json'] ) ? json_decode( (string) $row['context_json'], true ) : array();
			if ( ! is_array( $context ) ) {
				return array( 'success' => false, 'id' => (int) $row['id'] );
			}
			$canonical = array(
				'event_type' => (string) $row['event_type'],
				'object_type' => (string) $row['object_type'],
				'object_id' => (int) $row['object_id'],
				'actor_user_id' => (int) $row['actor_user_id'],
				'previous_digest' => (string) $row['previous_digest'],
				'context' => isset( $context['context'] ) && is_array( $context['context'] ) ? $context['context'] : array(),
				'created_at' => (string) $row['created_at'],
			);
			$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $canonical ) : json_encode( $canonical );
			$expected = hash( 'sha256', (string) $json );
			if ( ! hash_equals( $expected, (string) $row['event_digest'] ) ) {
				return array( 'success' => false, 'id' => (int) $row['id'] );
			}
			$previous = (string) $row['event_digest'];
		}
		return array( 'success' => true, 'count' => count( $rows ), 'digest' => $previous );
	}

	private static function latest_digest() {
		$rows = Phase5Repository::query( 'audit_integrity', array(), 1, 0, 'id', 'DESC' );
		return $rows && isset( $rows[0]['event_digest'] ) ? (string) $rows[0]['event_digest'] : '';
	}

	private static function sanitize_context( array $context ) {
		$allowed = array( 'state', 'previous_state', 'decision', 'review_type', 'correction_class', 'source_type', 'duration_ms', 'target', 'result', 'count', 'language_tag' );
		$out = array();
		foreach ( $allowed as $key ) {
			if ( isset( $context[ $key ] ) && is_scalar( $context[ $key ] ) ) {
				$out[ $key ] = substr( (string) $context[ $key ], 0, 255 );
			}
		}
		return $out;
	}
}
