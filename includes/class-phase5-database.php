<?php
/**
 * Phase 5 normalized database schema.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Defines final-phase tables, indexes, and structure verification. */
final class Phase5Database {
	const INSTALL_RESULT_OPTION = 'sabri_feed_phase5_schema_install_result';

	public static function table_names( $prefix = null ) {
		global $wpdb;
		if ( null === $prefix ) {
			$prefix = isset( $wpdb ) && ! empty( $wpdb->prefix ) ? $wpdb->prefix : 'wp_';
		}
		return array(
			'sources'          => $prefix . 'sabri_news_sources',
			'reviews'          => $prefix . 'sabri_news_reviews',
			'submissions'      => $prefix . 'sabri_news_submissions',
			'submission_files' => $prefix . 'sabri_news_submission_files',
			'corrections'      => $prefix . 'sabri_news_corrections',
			'breaking'         => $prefix . 'sabri_news_breaking',
			'translations'     => $prefix . 'sabri_news_translations',
			'preview_tokens'   => $prefix . 'sabri_news_preview_tokens',
			'rate_limits'      => $prefix . 'sabri_news_rate_limits',
			'audit_integrity'  => $prefix . 'sabri_news_audit_integrity',
		);
	}

	public static function expected_indexes() {
		return array(
			'sources'          => array( 'PRIMARY', 'article_status', 'normalized_url', 'doi_lookup', 'verified_status' ),
			'reviews'          => array( 'PRIMARY', 'article_revision_type', 'reviewer_status', 'article_status' ),
			'submissions'      => array( 'PRIMARY', 'submitter_status', 'status_created', 'converted_article' ),
			'submission_files' => array( 'PRIMARY', 'submission_status', 'attachment_lookup', 'checksum_lookup' ),
			'corrections'      => array( 'PRIMARY', 'article_state', 'class_state', 'public_time' ),
			'breaking'         => array( 'PRIMARY', 'state_window', 'article_state', 'priority_state' ),
			'translations'     => array( 'PRIMARY', 'article_language', 'group_state', 'source_article' ),
			'preview_tokens'   => array( 'PRIMARY', 'token_hash', 'article_expiry', 'state_expiry' ),
			'rate_limits'      => array( 'PRIMARY', 'bucket_window', 'expires_at' ),
			'audit_integrity'  => array( 'PRIMARY', 'event_created', 'object_lookup' ),
		);
	}

	public static function schema( $prefix = null ) {
		global $wpdb;
		$tables  = self::table_names( $prefix );
		$charset = isset( $wpdb ) && method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
		return array(
			'sources' => "CREATE TABLE {$tables['sources']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	article_id bigint(20) unsigned NOT NULL,
	source_type varchar(48) NOT NULL,
	evidence_class varchar(48) NOT NULL,
	title text NOT NULL,
	publisher varchar(255) NOT NULL DEFAULT '',
	public_url text NULL,
	normalized_url char(64) NOT NULL DEFAULT '',
	doi varchar(255) NOT NULL DEFAULT '',
	publication_date date NULL,
	public_citation text NULL,
	private_notes longtext NULL,
	conflict_flags text NULL,
	status varchar(24) NOT NULL DEFAULT 'active',
	verified_by bigint(20) unsigned NOT NULL DEFAULT 0,
	verified_at datetime NULL,
	created_by bigint(20) unsigned NOT NULL DEFAULT 0,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY article_status (article_id,status),
	KEY normalized_url (normalized_url),
	KEY doi_lookup (doi(191)),
	KEY verified_status (verified_by,status)
) {$charset};",
			'reviews' => "CREATE TABLE {$tables['reviews']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	article_id bigint(20) unsigned NOT NULL,
	revision_id bigint(20) unsigned NOT NULL DEFAULT 0,
	review_type varchar(24) NOT NULL,
	reviewer_user_id bigint(20) unsigned NOT NULL,
	decision varchar(24) NOT NULL DEFAULT 'pending',
	public_summary text NULL,
	private_notes longtext NULL,
	requirements_json longtext NULL,
	decided_at datetime NULL,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY article_revision_type (article_id,revision_id,review_type),
	KEY reviewer_status (reviewer_user_id,decision),
	KEY article_status (article_id,decision)
) {$charset};",
			'submissions' => "CREATE TABLE {$tables['submissions']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	submitter_user_id bigint(20) unsigned NOT NULL,
	status varchar(32) NOT NULL DEFAULT 'draft',
	title text NOT NULL,
	summary text NULL,
	body longtext NULL,
	source_urls longtext NULL,
	declarations longtext NULL,
	private_editor_notes longtext NULL,
	converted_article_id bigint(20) unsigned NOT NULL DEFAULT 0,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	submitted_at datetime NULL,
	PRIMARY KEY  (id),
	KEY submitter_status (submitter_user_id,status),
	KEY status_created (status,created_at),
	KEY converted_article (converted_article_id)
) {$charset};",
			'submission_files' => "CREATE TABLE {$tables['submission_files']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	submission_id bigint(20) unsigned NOT NULL,
	attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
	original_name varchar(255) NOT NULL DEFAULT '',
	stored_mime varchar(128) NOT NULL DEFAULT '',
	sha256 char(64) NOT NULL DEFAULT '',
	size_bytes bigint(20) unsigned NOT NULL DEFAULT 0,
	consent_status varchar(24) NOT NULL DEFAULT 'not-applicable',
	status varchar(24) NOT NULL DEFAULT 'active',
	created_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY submission_status (submission_id,status),
	KEY attachment_lookup (attachment_id),
	KEY checksum_lookup (sha256)
) {$charset};",
			'corrections' => "CREATE TABLE {$tables['corrections']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	article_id bigint(20) unsigned NOT NULL,
	correction_class varchar(32) NOT NULL,
	state varchar(24) NOT NULL DEFAULT 'requested',
	requester_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	affected_claim text NULL,
	private_reason longtext NULL,
	public_note text NULL,
	previous_revision_id bigint(20) unsigned NOT NULL DEFAULT 0,
	corrected_revision_id bigint(20) unsigned NOT NULL DEFAULT 0,
	approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
	approved_at datetime NULL,
	published_at datetime NULL,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY article_state (article_id,state),
	KEY class_state (correction_class,state),
	KEY public_time (published_at)
) {$charset};",
			'breaking' => "CREATE TABLE {$tables['breaking']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	article_id bigint(20) unsigned NOT NULL,
	state varchar(24) NOT NULL DEFAULT 'scheduled',
	priority tinyint(3) unsigned NOT NULL DEFAULT 1,
	starts_at datetime NOT NULL,
	expires_at datetime NOT NULL,
	created_by bigint(20) unsigned NOT NULL DEFAULT 0,
	cancelled_by bigint(20) unsigned NOT NULL DEFAULT 0,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY state_window (state,starts_at,expires_at),
	KEY article_state (article_id,state),
	KEY priority_state (priority,state)
) {$charset};",
			'translations' => "CREATE TABLE {$tables['translations']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	article_id bigint(20) unsigned NOT NULL,
	source_article_id bigint(20) unsigned NOT NULL,
	translation_group char(36) NOT NULL,
	language_tag varchar(35) NOT NULL,
	translator_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	reviewer_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	state varchar(24) NOT NULL DEFAULT 'draft',
	source_revision_id bigint(20) unsigned NOT NULL DEFAULT 0,
	created_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY article_language (article_id,language_tag),
	KEY group_state (translation_group,state),
	KEY source_article (source_article_id,state)
) {$charset};",
			'preview_tokens' => "CREATE TABLE {$tables['preview_tokens']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	article_id bigint(20) unsigned NOT NULL,
	token_hash char(64) NOT NULL,
	scope varchar(32) NOT NULL DEFAULT 'preview',
	state varchar(24) NOT NULL DEFAULT 'active',
	expires_at datetime NOT NULL,
	created_by bigint(20) unsigned NOT NULL DEFAULT 0,
	created_at datetime NOT NULL,
	revoked_at datetime NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY token_hash (token_hash),
	KEY article_expiry (article_id,expires_at),
	KEY state_expiry (state,expires_at)
) {$charset};",
			'rate_limits' => "CREATE TABLE {$tables['rate_limits']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	bucket_hash char(64) NOT NULL,
	window_key varchar(64) NOT NULL,
	hit_count int(10) unsigned NOT NULL DEFAULT 1,
	expires_at datetime NOT NULL,
	updated_at datetime NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY bucket_window (bucket_hash,window_key),
	KEY expires_at (expires_at)
) {$charset};",
			'audit_integrity' => "CREATE TABLE {$tables['audit_integrity']} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	event_type varchar(64) NOT NULL,
	object_type varchar(48) NOT NULL DEFAULT '',
	object_id bigint(20) unsigned NOT NULL DEFAULT 0,
	actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
	previous_digest char(64) NOT NULL DEFAULT '',
	event_digest char(64) NOT NULL,
	context_json longtext NULL,
	created_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY event_created (event_type,created_at),
	KEY object_lookup (object_type,object_id)
) {$charset};",
		);
	}

	public static function install() {
		$report = array(
			'success' => false,
			'target' => Phase5Contracts::INTERNAL_SCHEMA_TARGET,
			'missing_tables' => array(),
			'missing_indexes' => array(),
			'dbdelta' => array(),
			'created_at_utc' => gmdate( 'Y-m-d H:i:s' ),
		);
		if ( ! function_exists( 'dbDelta' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			$report['message'] = 'dbDelta unavailable.';
			return self::record( $report );
		}
		foreach ( self::schema() as $slug => $sql ) {
			$result = dbDelta( $sql );
			$report['dbdelta'][ $slug ] = is_array( $result ) ? $result : array();
		}
		$verification = self::verify();
		$report = array_merge( $report, $verification );
		$report['success'] = ! $report['missing_tables'] && ! $report['missing_indexes'];
		$report['message'] = $report['success'] ? 'Phase 5 schema installed and verified.' : 'Phase 5 schema verification failed.';
		return self::record( $report );
	}

	public static function verify() {
		global $wpdb;
		$report = array( 'missing_tables' => array(), 'missing_indexes' => array(), 'table_status' => array(), 'index_status' => array() );
		if ( ! isset( $wpdb ) ) {
			$report['missing_tables'] = array_keys( self::table_names() );
			return $report;
		}
		foreach ( self::table_names() as $slug => $table ) {
			$like_table = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $table ) : addcslashes( $table, '_%\\' );
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like_table ) );
			$report['table_status'][ $slug ] = $exists === $table;
			if ( $exists !== $table ) {
				$report['missing_tables'][] = $slug;
				continue;
			}
			$rows = $wpdb->get_results( "SHOW INDEX FROM `{$table}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table is internal allow-list.
			$found = array();
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				if ( isset( $row['Key_name'] ) ) {
					$found[] = (string) $row['Key_name'];
				}
			}
			$report['index_status'][ $slug ] = array_values( array_unique( $found ) );
			foreach ( self::expected_indexes()[ $slug ] as $index ) {
				if ( ! in_array( $index, $found, true ) ) {
					$report['missing_indexes'][] = $slug . ':' . $index;
				}
			}
		}
		return $report;
	}

	private static function record( array $report ) {
		if ( function_exists( 'update_option' ) ) {
			update_option( self::INSTALL_RESULT_OPTION, $report, false );
		}
		return $report;
	}
}
