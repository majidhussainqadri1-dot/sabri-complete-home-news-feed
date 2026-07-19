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
	const EXPORT_PAGE_SIZE = 50;

	/** Register privacy hooks. */
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
			'poll_votes'  => 'Individual poll choices are private user data; public poll results contain aggregates only.',
			'audit_log'   => 'Audit logs are restricted to administrators and retain administrative accountability.',
			'views'       => 'Anonymous view data is minimized through hashed identities and date-level aggregation.',
			'uninstall'   => 'Default uninstall behavior retains data unless an administrator intentionally changes the retention setting.',
		);
	}

	/** Register exporter. */
	public static function register_exporter( $exporters ) {
		$exporters['sabri-home-news-feed'] = array(
			'exporter_friendly_name' => __( 'Sabri Home and News Feed data', 'sabri-complete-home-news-feed' ),
			'callback'               => array( __CLASS__, 'exporter' ),
		);
		return $exporters;
	}

	/** Register eraser. */
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
		$user = function_exists( 'get_user_by' ) ? get_user_by( 'email', $email_address ) : false;
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}

		$user_id = absint( $user->ID );
		$page    = max( 1, absint( $page ) );
		$items   = self::export_items( $user_id );
		$offset  = ( $page - 1 ) * self::EXPORT_PAGE_SIZE;
		$data    = array_slice( $items, $offset, self::EXPORT_PAGE_SIZE );
		$done    = count( $items ) <= $offset + self::EXPORT_PAGE_SIZE;

		return array( 'data' => $data, 'done' => $done );
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
	 * Export WordPress-compatible privacy items.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function export_items( $user_id ) {
		return array_merge(
			self::export_rows(
				'saves',
				'user_id',
				$user_id,
				array(
					'post_id'        => __( 'Saved post ID', 'sabri-complete-home-news-feed' ),
					'collection_key' => __( 'Collection', 'sabri-complete-home-news-feed' ),
					'status'         => __( 'Status', 'sabri-complete-home-news-feed' ),
					'created_at'     => __( 'Created at', 'sabri-complete-home-news-feed' ),
				)
			),
			self::export_rows(
				'follows',
				'follower_user_id',
				$user_id,
				array(
					'target_user_id' => __( 'Followed user ID', 'sabri-complete-home-news-feed' ),
					'target_type'    => __( 'Follow target type', 'sabri-complete-home-news-feed' ),
					'status'         => __( 'Status', 'sabri-complete-home-news-feed' ),
					'created_at'     => __( 'Created at', 'sabri-complete-home-news-feed' ),
				)
			),
			self::export_rows(
				'reports',
				'reporter_user_id',
				$user_id,
				array(
					'object_type' => __( 'Reported object type', 'sabri-complete-home-news-feed' ),
					'object_id'   => __( 'Reported object ID', 'sabri-complete-home-news-feed' ),
					'reason'      => __( 'Report reason', 'sabri-complete-home-news-feed' ),
					'status'      => __( 'Report status', 'sabri-complete-home-news-feed' ),
					'created_at'  => __( 'Created at', 'sabri-complete-home-news-feed' ),
				)
			),
			self::export_rows(
				'poll_votes',
				'user_id',
				$user_id,
				array(
					'poll_post_id' => __( 'Poll post ID', 'sabri-complete-home-news-feed' ),
					'option_key'   => __( 'Selected poll option', 'sabri-complete-home-news-feed' ),
					'status'       => __( 'Status', 'sabri-complete-home-news-feed' ),
					'created_at'   => __( 'Created at', 'sabri-complete-home-news-feed' ),
				)
			)
		);
	}

	/**
	 * Export whitelisted columns from a user-owned table.
	 *
	 * @param string               $table_slug Table slug.
	 * @param string               $user_column User column.
	 * @param int                  $user_id User ID.
	 * @param array<string,string> $columns Columns and labels.
	 * @return array<int,array<string,mixed>>
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
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$user_column}` = %d ORDER BY id ASC", $user_id ), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$data = array();
			foreach ( $columns as $column => $label ) {
				if ( isset( $row[ $column ] ) ) {
					$data[] = array(
						'name'  => $label,
						'value' => (string) $row[ $column ],
					);
				}
			}

			if ( empty( $data ) ) {
				continue;
			}

			$out[] = array(
				'group_id'    => 'sabri-home-news-feed-' . $table_slug,
				'group_label' => __( 'Sabri Home and News Feed', 'sabri-complete-home-news-feed' ),
				'item_id'     => self::export_item_id( $table_slug, $row ),
				'data'        => $data,
			);
		}

		return $out;
	}

	/**
	 * Create a stable export item ID without exposing extra row data.
	 *
	 * @param string              $table_slug Table slug.
	 * @param array<string,mixed> $row Row.
	 * @return string
	 */
	private static function export_item_id( $table_slug, array $row ) {
		if ( isset( $row['id'] ) ) {
			return 'sabri-home-news-feed-' . $table_slug . '-' . absint( $row['id'] );
		}

		return 'sabri-home-news-feed-' . $table_slug . '-' . hash( 'sha256', wp_json_encode( $row ) );
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
		$salt   = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : SABRI_HNF_SLUG;
		$poll_hash = hash( 'sha256', 'erased-poll-vote|' . (int) $user_id . '|' . $salt );

		$wpdb->update( $tables['saves'], array( 'status' => 'removed', 'updated_at' => $now ), array( 'user_id' => $user_id ), array( '%s', '%s' ), array( '%d' ) );
		$wpdb->update( $tables['follows'], array( 'status' => 'removed', 'updated_at' => $now ), array( 'follower_user_id' => $user_id ), array( '%s', '%s' ), array( '%d' ) );
		$wpdb->update( $tables['follows'], array( 'status' => 'removed', 'updated_at' => $now ), array( 'target_user_id' => $user_id ), array( '%s', '%s' ), array( '%d' ) );
		$wpdb->update( $tables['reports'], array( 'reporter_user_id' => 0, 'updated_at' => $now ), array( 'reporter_user_id' => $user_id ), array( '%d', '%s' ), array( '%d' ) );
		$wpdb->update( $tables['poll_votes'], array( 'user_id' => 0, 'anonymous_hash' => $poll_hash, 'status' => 'removed', 'updated_at' => $now ), array( 'user_id' => $user_id ), array( '%d', '%s', '%s', '%s' ), array( '%d' ) );
		$wpdb->update( $tables['views'], array( 'user_id' => 0, 'anonymous_hash' => '', 'updated_at' => $now ), array( 'user_id' => $user_id ), array( '%d', '%s', '%s' ), array( '%d' ) );
		$wpdb->update( $tables['audit_log'], array( 'actor_user_id' => 0 ), array( 'actor_user_id' => $user_id ), array( '%d' ), array( '%d' ) );
	}
}
