<?php
/**
 * Non-destructive rollback for File 04 publication migration.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Privates migrated targets and disables redirects without deleting either copy. */
final class LegacyPublicationRollback {
	const LAST_REPORT_OPTION = 'sabri_hnf_legacy_publication_rollback_report';
	const MAX_BATCH = 100;

	/** Register redirect suppression for rolled-back mappings. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'suppress_rolled_back_redirect' ), 0 );
		}
	}

	/** Return bounded active mappings eligible for rollback. */
	public static function preview( $limit = self::MAX_BATCH ) {
		$limit = max( 1, min( self::MAX_BATCH, (int) $limit ) );
		$mapping = self::mapping();
		$items = array();
		foreach ( $mapping as $legacy_id => $row ) {
			if ( count( $items ) >= $limit || ! is_array( $row ) || 'rolled_back' === ( isset( $row['status'] ) ? $row['status'] : '' ) ) {
				continue;
			}
			$target_id = isset( $row['target_id'] ) ? absint( $row['target_id'] ) : 0;
			if ( $target_id <= 0 || ! self::target_belongs_to_legacy( $target_id, $legacy_id ) ) {
				continue;
			}
			$items[] = array(
				'legacy_id' => absint( $legacy_id ),
				'target_id' => $target_id,
				'target_type' => isset( $row['target_type'] ) ? sanitize_key( $row['target_type'] ) : '',
				'target_status' => function_exists( 'get_post_status' ) ? (string) get_post_status( $target_id ) : '',
				'target_title' => function_exists( 'get_the_title' ) ? (string) get_the_title( $target_id ) : '',
			);
		}
		return array( 'candidate_count' => count( $items ), 'candidates' => $items, 'destructive' => false, 'automatic' => false, 'max_batch' => self::MAX_BATCH );
	}

	/** Roll back only selected active mappings. */
	public static function rollback_selected( array $legacy_ids, $actor_id = 0 ) {
		$actor_id = $actor_id ? absint( $actor_id ) : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		if ( ! self::actor_can_rollback( $actor_id ) ) {
			return array( 'success' => false, 'error' => 'permission_denied', 'rolled_back' => array(), 'skipped' => array() );
		}
		$legacy_ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $legacy_ids ) ) ) ), 0, self::MAX_BATCH );
		if ( empty( $legacy_ids ) ) {
			return array( 'success' => false, 'error' => 'no_mappings_selected', 'rolled_back' => array(), 'skipped' => array() );
		}
		Snapshot::capture_before_mutation( 'legacy_file04_publication_rollback' );
		$mapping = self::mapping();
		$rolled_back = array();
		$skipped = array();
		foreach ( $legacy_ids as $legacy_id ) {
			$row = isset( $mapping[ $legacy_id ] ) && is_array( $mapping[ $legacy_id ] ) ? $mapping[ $legacy_id ] : array();
			$target_id = isset( $row['target_id'] ) ? absint( $row['target_id'] ) : 0;
			if ( $target_id <= 0 || 'rolled_back' === ( isset( $row['status'] ) ? $row['status'] : '' ) || ! self::target_belongs_to_legacy( $target_id, $legacy_id ) ) {
				$skipped[ $legacy_id ] = 'invalid_or_inactive_mapping';
				continue;
			}
			$previous_status = function_exists( 'get_post_status' ) ? (string) get_post_status( $target_id ) : '';
			$result = function_exists( 'wp_update_post' ) ? wp_update_post( array( 'ID' => $target_id, 'post_status' => 'private' ), true ) : 0;
			if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) || (int) $result <= 0 ) {
				$skipped[ $legacy_id ] = 'target_private_failed';
				continue;
			}
			$mapping[ $legacy_id ]['status'] = 'rolled_back';
			$mapping[ $legacy_id ]['previous_target_status'] = $previous_status;
			$mapping[ $legacy_id ]['rolled_back_at_utc'] = gmdate( 'Y-m-d H:i:s' );
			$mapping[ $legacy_id ]['rolled_back_by'] = $actor_id;
			$rolled_back[ $legacy_id ] = array( 'target_id' => $target_id, 'previous_status' => $previous_status, 'new_status' => 'private' );
			AuditLog::record( 'legacy_file04_publication_rolled_back', array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'actor_id' => $actor_id, 'previous_status' => $previous_status ), 'post', $target_id );
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( LegacyPublicationMigration::MAPPING_OPTION, $mapping, false );
		}
		FeedQuery::invalidate_cache();
		$report = array( 'success' => empty( $skipped ), 'partial' => ! empty( $rolled_back ) && ! empty( $skipped ), 'actor_id' => $actor_id, 'rolled_back' => $rolled_back, 'skipped' => $skipped, 'created_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'destructive' => false, 'automatic' => false );
		if ( function_exists( 'update_option' ) ) {
			update_option( self::LAST_REPORT_OPTION, $report, false );
		}
		return $report;
	}

	/** Disable legacy redirect on a rolled-back mapping. */
	public static function suppress_rolled_back_redirect() {
		if ( ! function_exists( 'is_singular' ) || ! is_singular( LegacyPublicationMigration::LEGACY_POST_TYPE ) || ! function_exists( 'get_queried_object_id' ) ) {
			return;
		}
		$legacy_id = absint( get_queried_object_id() );
		$mapping = self::mapping();
		if ( isset( $mapping[ $legacy_id ]['status'] ) && 'rolled_back' === $mapping[ $legacy_id ]['status'] && function_exists( 'remove_action' ) ) {
			remove_action( 'template_redirect', array( LegacyPublicationMigration::class, 'redirect_migrated_legacy_single' ), 1 );
		}
	}

	/** Read the complete mapping. */
	private static function mapping() {
		$mapping = function_exists( 'get_option' ) ? get_option( LegacyPublicationMigration::MAPPING_OPTION, array() ) : array();
		return is_array( $mapping ) ? $mapping : array();
	}

	/** Verify target provenance before mutation. */
	private static function target_belongs_to_legacy( $target_id, $legacy_id ) {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return false;
		}
		return absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) === absint( $legacy_id )
			&& LegacyPublicationMigration::LEGACY_POST_TYPE === (string) get_post_meta( $target_id, '_sabri_hnf_legacy_source_type', true );
	}

	/** Rollback authority. */
	private static function actor_can_rollback( $actor_id ) {
		return $actor_id > 0
			&& function_exists( 'get_current_user_id' )
			&& (int) get_current_user_id() === $actor_id
			&& CanonicalIdentityAdapter::current_action_ready( $actor_id )
			&& function_exists( 'current_user_can' )
			&& ( current_user_can( 'manage_options' ) || current_user_can( 'sabri_feed_run_migrations' ) );
	}
}
