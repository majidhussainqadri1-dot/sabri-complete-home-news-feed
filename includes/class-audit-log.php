<?php
/**
 * Audit log foundation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records plugin-owned administrative events.
 */
final class AuditLog {
	/**
	 * Record an audit event.
	 *
	 * @param string              $action Action key.
	 * @param array<string,mixed> $context Context.
	 * @param string              $object_type Object type.
	 * @param int                 $object_id Object ID.
	 * @return bool
	 */
	public static function record( $action, array $context = array(), $object_type = '', $object_id = 0 ) {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'insert' ) ) {
			return false;
		}

		$table = Database::table_names()['audit_log'];
		$now   = gmdate( 'Y-m-d H:i:s' );
		$user  = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
		$json  = function_exists( 'wp_json_encode' ) ? wp_json_encode( $context ) : json_encode( $context );

		$wpdb->insert(
			$table,
			array(
				'actor_user_id' => $user,
				'action'        => sanitize_key( $action ),
				'object_type'   => sanitize_key( $object_type ),
				'object_id'     => absint( $object_id ),
				'context'       => $json,
				'ip_hash'       => self::ip_hash(),
				'created_at'    => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return true;
	}

	/**
	 * Hash the remote address when available, without exposing the raw value.
	 *
	 * @return string
	 */
	private static function ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? preg_replace( '/[^0-9a-fA-F:\.]/', '', (string) $_SERVER['REMOTE_ADDR'] ) : '';
		if ( '' === $ip ) {
			return '';
		}

		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : SABRI_HNF_SLUG;
		return hash( 'sha256', $ip . '|' . $salt );
	}
}
