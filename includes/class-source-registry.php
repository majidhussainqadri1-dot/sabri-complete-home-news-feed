<?php
/**
 * Editorial source and evidence registry.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Manages normalized source records and safe public projections. */
final class SourceRegistry {
	public static function register() {}

	public static function create( $article_id, array $input ) {
		$article_id = Phase5Contracts::positive_int( $article_id );
		if ( $article_id < 1 || ! self::can_manage( $article_id ) ) {
			return self::error( 'phase5_permission_denied', 403 );
		}
		$clean = self::validate( $input );
		if ( empty( $clean['success'] ) ) {
			return $clean;
		}
		$data = $clean['data'];
		$data['article_id'] = $article_id;
		$data['created_by'] = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$data['created_at'] = gmdate( 'Y-m-d H:i:s' );
		$data['updated_at'] = $data['created_at'];
		if ( self::duplicate_exists( $article_id, $data['normalized_url'], $data['doi'] ) ) {
			return self::error( 'phase5_conflict', 409, 'Duplicate source.' );
		}
		$id = Phase5Repository::insert( 'sources', $data );
		if ( $id < 1 ) {
			return self::error( 'phase5_query_failed', 500 );
		}
		Phase5AuditIntegrity::record( 'source-created', 'source', $id, array( 'source_type' => $data['source_type'] ) );
		return array( 'success' => true, 'status' => 201, 'data' => self::find( $id, true ) );
	}

	public static function update( $id, array $patch ) {
		$row = Phase5Repository::find( 'sources', $id );
		if ( ! $row || ! self::can_manage( (int) $row['article_id'] ) ) {
			return self::error( 'phase5_not_found', 404 );
		}
		$merged = array_merge( $row, $patch );
		$clean = self::validate( $merged );
		if ( empty( $clean['success'] ) ) {
			return $clean;
		}
		$data = $clean['data'];
		$data['status'] = (string) $row['status'];
		$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		if ( self::duplicate_exists( (int) $row['article_id'], $data['normalized_url'], $data['doi'], (int) $id ) ) {
			return self::error( 'phase5_conflict', 409, 'Duplicate source.' );
		}
		if ( ! Phase5Repository::update( 'sources', $id, $data ) ) {
			return self::error( 'phase5_query_failed', 500 );
		}
		Phase5AuditIntegrity::record( 'source-updated', 'source', $id, array( 'source_type' => $data['source_type'] ) );
		return array( 'success' => true, 'status' => 200, 'data' => self::find( $id, true ) );
	}

	public static function verify( $id, $decision = 'verified' ) {
		$row = Phase5Repository::find( 'sources', $id );
		if ( ! $row || ! function_exists( 'current_user_can' ) || ! current_user_can( 'verify_news_sources' ) ) {
			return self::error( 'phase5_not_found', 404 );
		}
		$status = in_array( $decision, array( 'verified', 'retired', 'active' ), true ) ? $decision : '';
		if ( '' === $status ) {
			return self::error( 'phase5_state_invalid', 400 );
		}
		$updated = Phase5Repository::update(
			'sources',
			$id,
			array(
				'status' => $status,
				'verified_by' => (int) get_current_user_id(),
				'verified_at' => gmdate( 'Y-m-d H:i:s' ),
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
		if ( ! $updated ) {
			return self::error( 'phase5_query_failed', 500 );
		}
		Phase5AuditIntegrity::record( 'source-' . $status, 'source', $id, array( 'result' => $status ) );
		return array( 'success' => true, 'status' => 200, 'data' => self::find( $id, true ) );
	}

	public static function list_for_article( $article_id, $private = false ) {
		$article_id = Phase5Contracts::positive_int( $article_id );
		if ( $article_id < 1 ) {
			return array();
		}
		if ( $private && ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_news_sources' ) ) ) {
			return array();
		}
		$rows = Phase5Repository::query( 'sources', array( 'article_id' => $article_id ), 100, 0, 'id', 'ASC' );
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! $private && ! in_array( (string) $row['status'], array( 'active', 'verified' ), true ) ) {
				continue;
			}
			$out[] = self::project( $row, $private );
		}
		return $out;
	}

	public static function find( $id, $private = false ) {
		$row = Phase5Repository::find( 'sources', $id );
		return $row ? self::project( $row, $private ) : null;
	}

	public static function publication_ready( $article_id ) {
		$rows = self::list_for_article( $article_id, true );
		foreach ( $rows as $row ) {
			if ( 'verified' === $row['status'] && ! empty( $row['public_citation'] ) ) {
				return true;
			}
		}
		return false;
	}

	private static function validate( array $input ) {
		$type = isset( $input['source_type'] ) ? (string) $input['source_type'] : '';
		$evidence = isset( $input['evidence_class'] ) ? (string) $input['evidence_class'] : '';
		$title = isset( $input['title'] ) ? trim( (string) $input['title'] ) : '';
		if ( ! in_array( $type, Phase5Contracts::source_types(), true ) || ! in_array( $evidence, Phase5Contracts::evidence_classes(), true ) || '' === $title || strlen( $title ) > 1000 ) {
			return self::error( 'phase5_payload_invalid', 400 );
		}
		$url = isset( $input['public_url'] ) ? self::safe_url( $input['public_url'] ) : '';
		$doi = isset( $input['doi'] ) ? self::normalize_doi( $input['doi'] ) : '';
		if ( '' === $url && '' === $doi && 'book' !== $type && 'classical-homeopathy-text' !== $type && 'interview' !== $type ) {
			return self::error( 'phase5_payload_invalid', 400, 'A verifiable URL or DOI is required.' );
		}
		$date = isset( $input['publication_date'] ) ? self::date( $input['publication_date'] ) : '';
		if ( isset( $input['publication_date'] ) && '' !== (string) $input['publication_date'] && '' === $date ) {
			return self::error( 'phase5_payload_invalid', 400, 'Invalid publication date.' );
		}
		$conflicts = isset( $input['conflict_flags'] ) && is_array( $input['conflict_flags'] ) ? array_values( array_intersect( $input['conflict_flags'], array( 'affiliation','sponsorship','product-ownership','paid-promotion','research-authorship','institutional-interest' ) ) ) : array();
		return array(
			'success' => true,
			'data' => array(
				'source_type' => $type,
				'evidence_class' => $evidence,
				'title' => function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $title ) : strip_tags( $title ),
				'publisher' => isset( $input['publisher'] ) ? substr( trim( strip_tags( (string) $input['publisher'] ) ), 0, 255 ) : '',
				'public_url' => $url,
				'normalized_url' => '' !== $url ? hash( 'sha256', self::normalize_url( $url ) ) : '',
				'doi' => $doi,
				'publication_date' => '' !== $date ? $date : null,
				'public_citation' => isset( $input['public_citation'] ) ? substr( trim( strip_tags( (string) $input['public_citation'] ) ), 0, 4000 ) : '',
				'private_notes' => isset( $input['private_notes'] ) ? substr( trim( (string) $input['private_notes'] ), 0, 10000 ) : '',
				'conflict_flags' => json_encode( $conflicts ),
				'status' => isset( $input['status'] ) && in_array( $input['status'], array( 'active', 'verified', 'retired' ), true ) ? $input['status'] : 'active',
			)
		);
	}

	private static function project( array $row, $private ) {
		$out = array(
			'id' => (int) $row['id'],
			'article_id' => (int) $row['article_id'],
			'source_type' => (string) $row['source_type'],
			'evidence_class' => (string) $row['evidence_class'],
			'title' => (string) $row['title'],
			'publisher' => (string) $row['publisher'],
			'public_url' => self::safe_url( $row['public_url'] ),
			'doi' => (string) $row['doi'],
			'publication_date' => $row['publication_date'],
			'public_citation' => (string) $row['public_citation'],
			'status' => (string) $row['status'],
		);
		if ( $private ) {
			$out['private_notes'] = (string) $row['private_notes'];
			$out['conflict_flags'] = json_decode( (string) $row['conflict_flags'], true );
			$out['verified_by'] = (int) $row['verified_by'];
			$out['verified_at'] = $row['verified_at'];
		}
		return $out;
	}

	private static function can_manage( $article_id ) {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_news_sources' ) && ( ! function_exists( 'get_post_type' ) || Phase4Contracts::POST_TYPE === get_post_type( $article_id ) );
	}

	private static function duplicate_exists( $article_id, $normalized_url, $doi, $exclude_id = 0 ) {
		$rows = Phase5Repository::query( 'sources', array( 'article_id' => $article_id ), 100, 0, 'id', 'ASC' );
		foreach ( $rows as $row ) {
			if ( $exclude_id > 0 && (int) $row['id'] === (int) $exclude_id ) {
				continue;
			}
			if ( '' !== $normalized_url && hash_equals( (string) $row['normalized_url'], (string) $normalized_url ) ) {
				return true;
			}
			if ( '' !== $doi && strtolower( (string) $row['doi'] ) === strtolower( $doi ) ) {
				return true;
			}
		}
		return false;
	}

	private static function normalize_doi( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '#^(?:https?://(?:dx\.)?doi\.org/|doi:\s*)#i', '', $value );
		return preg_match( '#^10\.\d{4,9}/[-._;()/:a-z0-9]+$#i', $value ) ? substr( $value, 0, 255 ) : '';
	}

	private static function safe_url( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || preg_match( '/[\x00-\x1F\x7F]/', $value ) ) {
			return '';
		}
		$parts = parse_url( $value );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return '';
		}
		return function_exists( 'esc_url_raw' ) ? esc_url_raw( $value, array( 'http', 'https' ) ) : filter_var( $value, FILTER_VALIDATE_URL );
	}

	private static function normalize_url( $url ) {
		$parts = parse_url( strtolower( $url ) );
		if ( ! is_array( $parts ) ) {
			return '';
		}
		$path = isset( $parts['path'] ) ? rtrim( $parts['path'], '/' ) : '';
		$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		return $parts['scheme'] . '://' . $parts['host'] . $path . $query;
	}

	private static function date( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			return '';
		}
		return $value;
	}

	private static function error( $code, $status, $message = '' ) {
		return array( 'success' => false, 'status' => (int) $status, 'code' => $code, 'message' => $message );
	}
}
