<?php
/**
 * Immutable editorial review ledger.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stores revision-specific editorial, fact-check, medical, and translation decisions. */
final class ReviewLedger {
	public static function register() {}

	public static function assign( $article_id, $revision_id, $type, $reviewer_id ) {
		$article_id = Phase5Contracts::positive_int( $article_id );
		$revision_id = max( 0, (int) $revision_id );
		$reviewer_id = Phase5Contracts::positive_int( $reviewer_id );
		if ( $article_id < 1 || $reviewer_id < 1 || ! in_array( $type, Phase5Contracts::review_types(), true ) || ! self::revision_belongs( $article_id, $revision_id ) || ! self::can_assign( $article_id, $reviewer_id, $type ) ) {
			return self::error( 'phase5_permission_denied', 403 );
		}
		$existing = self::find_for_revision( $article_id, $revision_id, $type );
		$now = gmdate( 'Y-m-d H:i:s' );
		$data = array(
			'article_id' => $article_id,
			'revision_id' => $revision_id,
			'review_type' => $type,
			'reviewer_user_id' => $reviewer_id,
			'decision' => 'pending',
			'public_summary' => '',
			'private_notes' => '',
			'requirements_json' => '[]',
			'decided_at' => null,
			'created_at' => $now,
			'updated_at' => $now,
		);
		if ( $existing ) {
			if ( 'pending' !== (string) $existing['decision'] ) {
				return self::error( 'phase5_conflict', 409 );
			}
			if ( (int) $existing['reviewer_user_id'] === $reviewer_id ) {
				return array( 'success' => true, 'status' => 200, 'data' => $existing, 'idempotent' => true );
			}
			if ( ! Phase5Repository::update( 'reviews', $existing['id'], array( 'reviewer_user_id' => $reviewer_id, 'updated_at' => $now ) ) ) {
				return self::error( 'phase5_query_failed', 500 );
			}
			$id = (int) $existing['id'];
		} else {
			$id = Phase5Repository::insert( 'reviews', $data );
			if ( $id < 1 ) {
				return self::error( 'phase5_query_failed', 500 );
			}
		}
		Phase5AuditIntegrity::record( 'review-assigned', 'review', $id, array( 'review_type' => $type, 'state' => 'pending' ) );
		return array( 'success' => true, 'status' => 200, 'data' => Phase5Repository::find( 'reviews', $id ) );
	}

	public static function decide( $review_id, $decision, array $input = array() ) {
		$row = Phase5Repository::find( 'reviews', $review_id );
		$reviewer_decisions = array( 'approved', 'changes-requested', 'rejected', 'withdrawn' );
		if ( ! $row || ! in_array( $decision, $reviewer_decisions, true ) || ! self::can_decide( $row ) ) {
			return self::error( 'phase5_permission_denied', 403 );
		}
		$current = (string) $row['decision'];
		if ( ! in_array( $current, array( 'pending', 'changes-requested' ), true ) ) {
			return self::error( 'phase5_conflict', 409 );
		}
		$requirements = isset( $input['requirements'] ) && is_array( $input['requirements'] ) ? array_slice( array_values( array_filter( array_map( 'strval', $input['requirements'] ) ) ), 0, 50 ) : array();
		$data = array(
			'decision' => $decision,
			'public_summary' => isset( $input['public_summary'] ) ? substr( trim( strip_tags( (string) $input['public_summary'] ) ), 0, 2000 ) : '',
			'private_notes' => isset( $input['private_notes'] ) ? substr( trim( (string) $input['private_notes'] ), 0, 10000 ) : '',
			'requirements_json' => json_encode( $requirements ),
			'decided_at' => gmdate( 'Y-m-d H:i:s' ),
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		if ( ! Phase5Repository::update( 'reviews', $review_id, $data ) ) {
			return self::error( 'phase5_query_failed', 500 );
		}
		Phase5AuditIntegrity::record( 'review-decided', 'review', $review_id, array( 'review_type' => $row['review_type'], 'decision' => $decision ) );
		return array( 'success' => true, 'status' => 200, 'data' => Phase5Repository::find( 'reviews', $review_id ) );
	}

	public static function invalidate_for_revision_change( $article_id, $new_revision_id ) {
		$article_id = Phase5Contracts::positive_int( $article_id );
		$new_revision_id = max( 0, (int) $new_revision_id );
		$rows = Phase5Repository::query( 'reviews', array( 'article_id' => $article_id ), 100, 0, 'id', 'ASC' );
		$count = 0;
		foreach ( $rows as $row ) {
			if ( (int) $row['revision_id'] === $new_revision_id || ! in_array( $row['decision'], array( 'approved', 'changes-requested' ), true ) ) {
				continue;
			}
			if ( Phase5Repository::update( 'reviews', $row['id'], array( 'decision' => 'superseded', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ) ) ) {
				$count++;
			}
		}
		if ( $count ) {
			Phase5AuditIntegrity::record( 'reviews-superseded', 'article', $article_id, array( 'count' => $count ) );
		}
		return $count;
	}

	public static function publication_ready( $article_id, $revision_id, $medical_required = false, $translation_required = false ) {
		$required = array( 'editorial', 'fact-check' );
		if ( $medical_required ) {
			$required[] = 'medical';
		}
		if ( $translation_required ) {
			$required[] = 'translation';
		}
		foreach ( $required as $type ) {
			$row = self::find_for_revision( $article_id, $revision_id, $type );
			if ( ! $row || 'approved' !== $row['decision'] ) {
				return false;
			}
		}
		return true;
	}

	public static function list_for_article( $article_id, $private = false ) {
		if ( $private && ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'review_editorial_news' ) ) ) {
			return array();
		}
		$rows = Phase5Repository::query( 'reviews', array( 'article_id' => Phase5Contracts::positive_int( $article_id ) ), 100, 0, 'id', 'ASC' );
		$out = array();
		foreach ( $rows as $row ) {
			$item = array(
				'id' => (int) $row['id'],
				'article_id' => (int) $row['article_id'],
				'revision_id' => (int) $row['revision_id'],
				'review_type' => (string) $row['review_type'],
				'decision' => (string) $row['decision'],
				'public_summary' => (string) $row['public_summary'],
				'decided_at' => $row['decided_at'],
			);
			if ( $private ) {
				$item['reviewer_user_id'] = (int) $row['reviewer_user_id'];
				$item['private_notes'] = (string) $row['private_notes'];
				$item['requirements'] = json_decode( (string) $row['requirements_json'], true );
			}
			$out[] = $item;
		}
		return $out;
	}

	private static function find_for_revision( $article_id, $revision_id, $type ) {
		$rows = Phase5Repository::query( 'reviews', array( 'article_id' => $article_id, 'revision_id' => $revision_id, 'review_type' => $type ), 1, 0, 'id', 'DESC' );
		return $rows ? $rows[0] : null;
	}

	private static function revision_belongs( $article_id, $revision_id ) {
		if ( $revision_id < 1 || ! function_exists( 'wp_is_post_revision' ) ) {
			return true;
		}
		return (int) wp_is_post_revision( $revision_id ) === (int) $article_id;
	}

	private static function can_assign( $article_id, $reviewer_id, $type ) {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'review_editorial_news' ) ) {
			return false;
		}
		if ( function_exists( 'get_current_user_id' ) && (int) get_current_user_id() === $reviewer_id && ! current_user_can( 'manage_news_settings' ) ) {
			return false;
		}
		$capability = self::capability( $type );
		return function_exists( 'user_can' ) && user_can( $reviewer_id, $capability ) && ( ! function_exists( 'get_post_type' ) || Phase4Contracts::POST_TYPE === get_post_type( $article_id ) );
	}

	private static function can_decide( array $row ) {
		$current = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		return $current > 0 && $current === (int) $row['reviewer_user_id'] && function_exists( 'current_user_can' ) && current_user_can( self::capability( $row['review_type'] ) );
	}

	private static function capability( $type ) {
		$map = array( 'editorial' => 'review_editorial_news', 'fact-check' => 'fact_check_editorial_news', 'medical' => 'medical_review_editorial_news', 'translation' => 'translate_editorial_news' );
		return isset( $map[ $type ] ) ? $map[ $type ] : 'do_not_allow';
	}

	private static function error( $code, $status ) {
		return array( 'success' => false, 'status' => $status, 'code' => $code );
	}
}
