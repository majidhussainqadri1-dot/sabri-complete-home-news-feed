<?php
/**
 * Privacy and data retention foundation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers export and erasure hooks for plugin-owned social data.
 */
final class DataRetention {
	/**
	 * Register privacy hooks.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
			add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		}
	}

	/**
	 * Retention policy summary.
	 *
	 * @return array<string,string>
	 */
	public static function policy() {
		return array(
			'saves'       => 'Saved posts are private to the saving user and are exposed only through authenticated personal data export.',
			'follows'     => 'Follow relationships are exportable for the requesting user and removable or anonymized during erasure.',
			'reports'     => 'Reports remain confidential to authorized moderators and administrators.',
			'audit_log'   => 'Audit logs are restricted to administrators and retain administrative accountability.',
			'views'       => 'Anonymous view data is minimized through hashed identities and date-level aggregation.',
			'uninstall'   => 'Default uninstall behavior retains data unless an administrator intentionally changes the retention setting.',
		);
	}

	/**
	 * Register exporter.
	 *
	 * @param array<string,mixed> $exporters Exporters.
	 * @return array<string,mixed>
	 */
	public static function register_exporter( $exporters ) {
		$exporters['sabri-home-news-feed'] = array(
			'exporter_friendly_name' => __( 'Sabri Home and News Feed data', 'sabri-complete-home-news-feed' ),
			'callback'               => array( __CLASS__, 'exporter' ),
		);

		return $exporters;
	}

	/**
	 * Register eraser.
	 *
	 * @param array<string,mixed> $erasers Erasers.
	 * @return array<string,mixed>
	 */
	public static function register_eraser( $erasers ) {
		$erasers['sabri-home-news-feed'] = array(
			'eraser_friendly_name' => __( 'Sabri Home and News Feed data', 'sabri-complete-home-news-feed' ),
			'callback'             => array( __CLASS__, 'eraser' ),
		);

		return $erasers;
	}

	/**
	 * Export plugin-owned user data.
	 *
	 * @param string $email_address Email.
	 * @param int    $page Page.
	 * @return array<string,mixed>
	 */
	public static function exporter( $email_address, $page = 1 ) {
		unset( $page );

		$user = function_exists( 'get_user_by' ) ? get_user_by( 'email', $email_address ) : false;
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}

		$user_id = absint( $user->ID );
		$data    = array();

		foreach ( self::export_groups( $user_id ) as $group_id => $items ) {
			if ( empty( $items ) ) {
				continue;
			}
			$data[] = array(
				'group_id'    => 'sabri-home-news-feed-' . $group_id,
				'group_label' => __( 'Sabri Home and News Feed', 'sabri-complete-home-news-feed' ),
				'item_id'     => 'sabri-home-news-feed-' . $group_id . '-' . $user_id,
				'data'        => $items,
			);
		}

		return array( 'data' => $data, 'done' => true );
	}

	/**
	 * Erase/anonymize plugin-owned user data without deleting content.
	 *
	 * @param string $email_address Email.
	 * @param int    $page Page.
	 * @return array<string,mixed>
	 */
	public static function eraser( $email_address, $page = 1 ) {
		unset( $page );

		$user = function_exists( 'get_user_by' ) ? get_user_by( 'email', $email_address ) : false;
		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}

		$user_id = absint( $user->ID );
		self::anonymize_user_rows( $user_id );

		AuditLog::record( 'privacy_erase', array( 'user_id' => $user_id, 'mode' => 'anonymize_or_mark_removed' ) );

		return array(
			'items_removed'  => false,
			'items_retained' => true,
			'messages'       => array( __( 'Home and News Feed personal rows were anonymized or marked removed where supported. Content was not deleted.', 'sabri-complete-home-news-feed' ) ),
			'done'           => true,
		);
	}

	/**
	 * Export row groups.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,array<int,array<string,string>>>
	 */
	private static function export_groups( $user_id ) {
		return array(
			'saves'   => self::export_rows( 'saves', 'user_id', $user_id, array( 'post_id', 'collection_key', 'status', 'created_at' ) ),
			'follows' => self::export_rows( 'follows', 'follower_user_id', $user_id, array( 'target_user_id', 'target_type', 'status', 'created_at' ) ),
			'reports' => self::export_rows( 'reports', 'reporter_user_id', $user_id, array( 'object_type', 'object_id', 'reason', 'status', 'created_at' ) ),
		);
	}

	/**
	 * Export whitelisted columns from a user-owned table.
	 *
	 * @param string            $table_slug Table slug.
	 * @param string            $user_column User column.
	 * @param int               $user_id User ID.
	 * @param array<int,string> $columns Columns.
	 * @return array<int,array<string,string>>
	 */
	private static function export_rows( $table_slug, $user_column, $user_id, array $columns ) {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return array();
		}

		$tables = Database::table_names();
		if ( empty( $tables[ $table_slug ] ) ) {
			return array();
		}

		$table = str_replace( '`', '', $tables[ $table_slug ] );
		$user_column = str_replace( '`', '', $user_column );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$user_column}` = %d LIMIT 100", $user_id ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$item = array();
			foreach ( $columns as $column ) {
				if ( isset( $row[ $column ] ) ) {
					$item[] = array(
						'name'  => $column,
						'value' => (string) $row[ $column ],
					);
				}
			}
			$out[] = $item;
		}

		return $out;
	}

	/**
	 * Anonymize or mark removed personal rows.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private static function anonymize_user_rows( $user_id ) {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'update' ) ) {
			return;
		}

		$tables = Database::table_names();
		$now    = gmdate( 'Y-m-d H:i:s' );

		$wpdb->update( $tables['saves'], array( 'status' => 'removed', 'updated_at' => $now ), array( 'user_id' => $user_id ), array( '%s', '%s' ), array( '%d' ) );
		$wpdb->update( $tables['follows'], array( 'status' => 'removed', 'updated_at' => $now ), array( 'follower_user_id' => $user_id ), array( '%s', '%s' ), array( '%d' ) );
		$wpdb->update( $tables['reports'], array( 'reporter_user_id' => 0, 'updated_at' => $now ), array( 'reporter_user_id' => $user_id ), array( '%d', '%s' ), array( '%d' ) );
		$wpdb->update( $tables['views'], array( 'user_id' => 0, 'anonymous_hash' => '', 'updated_at' => $now ), array( 'user_id' => $user_id ), array( '%d', '%s', '%s' ), array( '%d' ) );
		$wpdb->update( $tables['audit_log'], array( 'actor_user_id' => 0 ), array( 'actor_user_id' => $user_id ), array( '%d' ), array( '%d' ) );
	}
}
