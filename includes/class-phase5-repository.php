<?php
/**
 * Phase 5 repository foundation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bounded table access with internal table/column allow-lists. */
final class Phase5Repository {
	private static $columns = array(
		'sources' => array( 'article_id','source_type','evidence_class','title','publisher','public_url','normalized_url','doi','publication_date','public_citation','private_notes','conflict_flags','status','verified_by','verified_at','created_by','created_at','updated_at' ),
		'reviews' => array( 'article_id','revision_id','review_type','reviewer_user_id','decision','public_summary','private_notes','requirements_json','decided_at','created_at','updated_at' ),
		'submissions' => array( 'submitter_user_id','status','title','summary','body','source_urls','declarations','private_editor_notes','converted_article_id','created_at','updated_at','submitted_at' ),
		'submission_files' => array( 'submission_id','attachment_id','original_name','stored_mime','sha256','size_bytes','consent_status','status','created_at' ),
		'corrections' => array( 'article_id','correction_class','state','requester_user_id','affected_claim','private_reason','public_note','previous_revision_id','corrected_revision_id','approved_by','approved_at','published_at','created_at','updated_at' ),
		'breaking' => array( 'article_id','state','priority','starts_at','expires_at','created_by','cancelled_by','created_at','updated_at' ),
		'translations' => array( 'article_id','source_article_id','translation_group','language_tag','translator_user_id','reviewer_user_id','state','source_revision_id','created_at','updated_at' ),
		'preview_tokens' => array( 'article_id','token_hash','scope','state','expires_at','created_by','created_at','revoked_at' ),
		'rate_limits' => array( 'bucket_hash','window_key','hit_count','expires_at','updated_at' ),
		'audit_integrity' => array( 'event_type','object_type','object_id','actor_user_id','previous_digest','event_digest','context_json','created_at' ),
	);

	public static function table( $slug ) {
		$tables = Phase5Database::table_names();
		return is_string( $slug ) && isset( $tables[ $slug ] ) ? $tables[ $slug ] : '';
	}

	public static function insert( $slug, array $data ) {
		global $wpdb;
		$table = self::table( $slug );
		$data = self::filter_data( $slug, $data );
		if ( ! isset( $wpdb ) || '' === $table || ! $data ) {
			return 0;
		}
		$result = $wpdb->insert( $table, $data );
		return false === $result ? 0 : (int) $wpdb->insert_id;
	}

	public static function update( $slug, $id, array $data ) {
		global $wpdb;
		$table = self::table( $slug );
		$id = Phase5Contracts::positive_int( $id );
		$data = self::filter_data( $slug, $data );
		if ( ! isset( $wpdb ) || '' === $table || $id < 1 || ! $data ) {
			return false;
		}
		$result = $wpdb->update( $table, $data, array( 'id' => $id ) );
		return false !== $result;
	}

	public static function find( $slug, $id ) {
		global $wpdb;
		$table = self::table( $slug );
		$id = Phase5Contracts::positive_int( $id );
		if ( ! isset( $wpdb ) || '' === $table || $id < 1 ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table is internal allow-list.
	}

	public static function list_by( $slug, $column, $value, $limit = 50, $offset = 0, $order = 'DESC' ) {
		global $wpdb;
		$table = self::table( $slug );
		$column = self::column( $slug, $column );
		$limit = max( 1, min( 100, (int) $limit ) );
		$offset = max( 0, (int) $offset );
		$order = 'ASC' === strtoupper( (string) $order ) ? 'ASC' : 'DESC';
		if ( ! isset( $wpdb ) || '' === $table || '' === $column ) {
			return array();
		}
		$sql = "SELECT * FROM `{$table}` WHERE `{$column}` = %s ORDER BY id {$order} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers are allow-listed.
		return $wpdb->get_results( $wpdb->prepare( $sql, (string) $value, $limit, $offset ), ARRAY_A );
	}

	public static function query( $slug, array $where = array(), $limit = 50, $offset = 0, $order_by = 'id', $order = 'DESC' ) {
		global $wpdb;
		$table = self::table( $slug );
		$order_by = self::column( $slug, $order_by );
		$order_by = '' === $order_by ? 'id' : $order_by;
		$order = 'ASC' === strtoupper( (string) $order ) ? 'ASC' : 'DESC';
		$limit = max( 1, min( 100, (int) $limit ) );
		$offset = max( 0, (int) $offset );
		if ( ! isset( $wpdb ) || '' === $table ) {
			return array();
		}
		$clauses = array();
		$values = array();
		foreach ( $where as $column => $value ) {
			$column = self::column( $slug, $column );
			if ( '' === $column ) {
				continue;
			}
			$clauses[] = "`{$column}` = %s";
			$values[] = (string) $value;
		}
		$sql = "SELECT * FROM `{$table}`"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table is allow-listed.
		if ( $clauses ) {
			$sql .= ' WHERE ' . implode( ' AND ', $clauses );
		}
		$sql .= " ORDER BY `{$order_by}` {$order} LIMIT %d OFFSET %d";
		$values[] = $limit;
		$values[] = $offset;
		return $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- generated placeholders are complete.
	}

	public static function count( $slug, array $where = array() ) {
		global $wpdb;
		$table = self::table( $slug );
		if ( ! isset( $wpdb ) || '' === $table ) {
			return 0;
		}
		$clauses = array();
		$values = array();
		foreach ( $where as $column => $value ) {
			$column = self::column( $slug, $column );
			if ( '' === $column ) {
				continue;
			}
			$clauses[] = "`{$column}` = %s";
			$values[] = (string) $value;
		}
		$sql = "SELECT COUNT(*) FROM `{$table}`"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table is allow-listed.
		if ( $clauses ) {
			$sql .= ' WHERE ' . implode( ' AND ', $clauses );
		}
		if ( $values ) {
			$sql = $wpdb->prepare( $sql, $values );
		}
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared above or no values.
	}

	private static function filter_data( $slug, array $data ) {
		if ( ! isset( self::$columns[ $slug ] ) ) {
			return array();
		}
		return array_intersect_key( $data, array_fill_keys( self::$columns[ $slug ], true ) );
	}

	private static function column( $slug, $column ) {
		if ( 'id' === $column ) {
			return 'id';
		}
		return is_string( $column ) && isset( self::$columns[ $slug ] ) && in_array( $column, self::$columns[ $slug ], true ) ? $column : '';
	}
}
