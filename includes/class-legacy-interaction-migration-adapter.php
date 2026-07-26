<?php
/**
 * Auditable provider contract for File 04 interaction migration.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Delegates interaction migration to an installed provider that knows the
 * source schema. File 21 never guesses legacy tables or fabricates counts.
 */
final class LegacyInteractionMigrationAdapter {
	const MAX_PROVIDER_RECORDS = 10000;

	/** Register the provider-registry filter. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'sabri_hnf_legacy_interaction_migration_providers', array( __CLASS__, 'normalize_provider_registry' ), 999 );
		}
	}

	/** Normalize a shared provider registry. */
	public static function normalize_provider_registry( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		$normalized = array();
		foreach ( $providers as $provider_id => $provider ) {
			$provider_id = self::clean_key( $provider_id );
			if ( '' === $provider_id || ! is_array( $provider ) || empty( $provider['callback'] ) || ! is_callable( $provider['callback'] ) ) {
				continue;
			}
			$normalized[ $provider_id ] = array(
				'label' => isset( $provider['label'] ) ? sanitize_text_field( $provider['label'] ) : $provider_id,
				'callback' => $provider['callback'],
				'source_schema' => isset( $provider['source_schema'] ) ? sanitize_text_field( $provider['source_schema'] ) : '',
				'supports_rollback' => ! empty( $provider['supports_rollback'] ),
			);
		}
		return $normalized;
	}

	/** Available provider descriptors without exposing callbacks. */
	public static function providers() {
		$providers = function_exists( 'apply_filters' ) ? apply_filters( 'sabri_hnf_legacy_interaction_migration_providers', array() ) : array();
		$providers = self::normalize_provider_registry( $providers );
		$out = array();
		foreach ( $providers as $provider_id => $provider ) {
			$out[ $provider_id ] = array(
				'label' => $provider['label'],
				'source_schema' => $provider['source_schema'],
				'supports_rollback' => $provider['supports_rollback'],
			);
		}
		return $out;
	}

	/**
	 * Migrate interactions through exactly one explicit provider.
	 *
	 * The provider must return a bounded report. It may copy records or preserve
	 * aggregate metrics, but must never delete source data.
	 */
	public static function migrate( $legacy_id, $target_id, $actor_id, $provider_id = '' ) {
		$legacy_id = absint( $legacy_id );
		$target_id = absint( $target_id );
		$actor_id = absint( $actor_id );
		$provider_id = self::clean_key( $provider_id );
		$base = array(
			'status' => 'unavailable',
			'provider' => '',
			'migrated_records' => 0,
			'migrated_metrics' => array(),
			'skipped_records' => 0,
			'errors' => array(),
			'source_deleted' => false,
			'automatic' => false,
		);
		if ( ! self::actor_can_migrate( $actor_id ) ) {
			$base['status'] = 'permission_denied';
			$base['errors'][] = 'actor_authorization_failed';
			return $base;
		}
		if ( $legacy_id <= 0 || $target_id <= 0 || ! self::target_belongs_to_legacy( $target_id, $legacy_id ) ) {
			$base['status'] = 'invalid_target';
			$base['errors'][] = 'target_provenance_failed';
			return $base;
		}
		$providers = function_exists( 'apply_filters' ) ? apply_filters( 'sabri_hnf_legacy_interaction_migration_providers', array() ) : array();
		$providers = self::normalize_provider_registry( $providers );
		if ( '' === $provider_id && 1 === count( $providers ) ) {
			$provider_id = (string) array_key_first( $providers );
		}
		if ( '' === $provider_id || ! isset( $providers[ $provider_id ] ) ) {
			return $base;
		}

		$context = array(
			'legacy_id' => $legacy_id,
			'target_id' => $target_id,
			'actor_id' => $actor_id,
			'max_records' => self::MAX_PROVIDER_RECORDS,
			'delete_source' => false,
			'automatic' => false,
		);
		try {
			$report = call_user_func( $providers[ $provider_id ]['callback'], $context );
		} catch ( \Throwable $throwable ) {
			$report = array( 'status' => 'failed', 'errors' => array( 'provider_exception:' . get_class( $throwable ) ) );
		}
		$report = self::normalize_report( $report, $provider_id );
		AuditLog::record(
			'legacy_file04_interactions_processed',
			array(
				'legacy_id' => $legacy_id,
				'target_id' => $target_id,
				'actor_id' => $actor_id,
				'provider' => $provider_id,
				'status' => $report['status'],
				'migrated_records' => $report['migrated_records'],
				'source_deleted' => false,
			),
			'post',
			$target_id
		);
		return $report;
	}

	/** Normalize an untrusted provider report. */
	private static function normalize_report( $report, $provider_id ) {
		$report = is_array( $report ) ? $report : array();
		$status = isset( $report['status'] ) ? self::clean_key( $report['status'] ) : 'failed';
		if ( ! in_array( $status, array( 'migrated', 'partial', 'nothing_to_migrate', 'failed' ), true ) ) {
			$status = 'failed';
		}
		$metrics = array();
		foreach ( isset( $report['migrated_metrics'] ) && is_array( $report['migrated_metrics'] ) ? $report['migrated_metrics'] : array() as $key => $value ) {
			$key = self::clean_key( $key );
			if ( '' !== $key && is_numeric( $value ) ) {
				$metrics[ $key ] = max( 0, min( self::MAX_PROVIDER_RECORDS, (int) $value ) );
			}
		}
		$errors = array();
		foreach ( isset( $report['errors'] ) && is_array( $report['errors'] ) ? $report['errors'] : array() as $error ) {
			$errors[] = sanitize_text_field( $error );
		}
		return array(
			'status' => $status,
			'provider' => $provider_id,
			'migrated_records' => max( 0, min( self::MAX_PROVIDER_RECORDS, isset( $report['migrated_records'] ) ? (int) $report['migrated_records'] : 0 ) ),
			'migrated_metrics' => $metrics,
			'skipped_records' => max( 0, min( self::MAX_PROVIDER_RECORDS, isset( $report['skipped_records'] ) ? (int) $report['skipped_records'] : 0 ) ),
			'errors' => array_slice( $errors, 0, 50 ),
			'source_deleted' => false,
			'automatic' => false,
		);
	}

	/** Verify target provenance. */
	private static function target_belongs_to_legacy( $target_id, $legacy_id ) {
		return function_exists( 'get_post_meta' )
			&& absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) === $legacy_id
			&& LegacyPublicationMigration::LEGACY_POST_TYPE === (string) get_post_meta( $target_id, '_sabri_hnf_legacy_source_type', true );
	}

	/** Require the explicit current actor and migration authority at this boundary. */
	private static function actor_can_migrate( $actor_id ) {
		return $actor_id > 0
			&& function_exists( 'get_current_user_id' )
			&& (int) get_current_user_id() === $actor_id
			&& function_exists( 'current_user_can' )
			&& ( current_user_can( 'manage_options' ) || current_user_can( 'sabri_feed_run_migrations' ) );
	}

	/** Normalize provider IDs. */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}
}
