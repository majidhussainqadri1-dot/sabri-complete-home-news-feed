<?php
/**
 * Cleanup for crash-left File 22 execution locks.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Removes only expired atomic execution locks in bounded batches. */
final class UniversalComposerExecutionLockMaintenance {
	private const LOCK_PREFIX = 'sabri_hnf_file22_exec_';
	private const CRON_HOOK   = 'sabri_hnf_file22_idempotency_cleanup';
	private const BATCH_LIMIT = 100;

	/** Attach after the record-reconciliation callback. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( self::CRON_HOOK, array( __CLASS__, 'run' ), 20 );
		}
	}

	/** Run one bounded cleanup batch. */
	public static function run() {
		self::cleanup_expired( self::BATCH_LIMIT );
	}

	/** Return the number of expired or malformed locks removed. */
	public static function cleanup_expired( int $limit = self::BATCH_LIMIT ): int {
		$deleted = 0;
		foreach ( self::option_rows( max( 1, min( self::BATCH_LIMIT, $limit ) ) ) as $row ) {
			$name  = isset( $row['option_name'] ) ? (string) $row['option_name'] : '';
			$value = isset( $row['option_value'] ) ? maybe_unserialize( $row['option_value'] ) : null;
			if ( '' === $name ) {
				continue;
			}
			if ( ! is_array( $value ) || empty( $value['token'] ) || (int) ( $value['expires_at'] ?? 0 ) <= time() ) {
				if ( function_exists( 'delete_option' ) && delete_option( $name ) ) {
					++$deleted;
				}
			}
		}
		return $deleted;
	}

	/** Fetch only lock-prefixed option rows. */
	private static function option_rows( int $limit ): array {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'esc_like' ) ) {
			return array();
		}
		$like = $wpdb->esc_like( self::LOCK_PREFIX ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id ASC LIMIT %d", $like, $limit ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
}
