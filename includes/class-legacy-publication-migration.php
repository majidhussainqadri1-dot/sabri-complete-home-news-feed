<?php
/**
 * Safe migration from File 04 legacy publications into File 21 content.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Preview, selectively migrate, map and redirect legacy SNP publications. */
final class LegacyPublicationMigration {
	const LEGACY_POST_TYPE = 'snp_publication';
	const MAPPING_OPTION = 'sabri_hnf_legacy_publication_mapping';
	const LAST_REPORT_OPTION = 'sabri_hnf_legacy_publication_last_report';
	const MAX_BATCH = 100;

	/** Register redirect and diagnostics hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'redirect_migrated_legacy_single' ), 1 );
		}
	}

	/** Non-mutating bounded preview. */
	public static function preview( $limit = self::MAX_BATCH ) {
		$limit = max( 1, min( self::MAX_BATCH, (int) $limit ) );
		$candidates = array();
		if ( class_exists( 'WP_Query' ) && function_exists( 'post_type_exists' ) && post_type_exists( self::LEGACY_POST_TYPE ) ) {
			$query = new \WP_Query(
				array(
					'post_type' => self::LEGACY_POST_TYPE,
					'post_status' => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page' => $limit,
					'orderby' => 'ID',
					'order' => 'ASC',
					'no_found_rows' => true,
				)
			);
			foreach ( (array) $query->posts as $post ) {
				$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : 0;
				if ( $post_id > 0 && ! self::target_for( $post_id ) ) {
					$candidates[] = self::candidate_summary( $post );
				}
			}
		}
		return array(
			'legacy_post_type' => self::LEGACY_POST_TYPE,
			'candidate_count' => count( $candidates ),
			'candidates' => $candidates,
			'interaction_providers' => class_exists( __NAMESPACE__ . '\\LegacyInteractionMigrationAdapter' ) ? LegacyInteractionMigrationAdapter::providers() : array(),
			'interaction_migration_default' => 'not_requested',
			'max_batch' => self::MAX_BATCH,
			'destructive' => false,
			'automatic' => false,
		);
	}

	/** Migrate only explicitly selected legacy IDs. */
	public static function migrate_selected( array $legacy_ids, $actor_id = 0, array $options = array() ) {
		$actor_id = $actor_id ? absint( $actor_id ) : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		if ( ! self::actor_can_migrate( $actor_id ) ) {
			return array( 'success' => false, 'error' => 'permission_denied', 'migrated' => array(), 'skipped' => array(), 'warnings' => array() );
		}
		$legacy_ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $legacy_ids ) ) ) ), 0, self::MAX_BATCH );
		if ( empty( $legacy_ids ) ) {
			return array( 'success' => false, 'error' => 'no_publications_selected', 'migrated' => array(), 'skipped' => array(), 'warnings' => array() );
		}
		$options = array_merge(
			array(
				'copy_comments' => true,
				'target' => 'auto',
				'migrate_interactions' => false,
				'interaction_provider' => '',
				'require_file04_context' => false,
				'author_identity_context' => array(),
				'media_preflight_context' => array(),
			),
			$options
		);
		$options['target'] = in_array( sanitize_key( $options['target'] ), array( 'auto', 'post', 'sabri_news' ), true ) ? sanitize_key( $options['target'] ) : 'auto';
		$options['interaction_provider'] = sanitize_key( $options['interaction_provider'] );
		$options['migrate_interactions'] = ! empty( $options['migrate_interactions'] );
		$options['require_file04_context'] = ! empty( $options['require_file04_context'] );
		$options['author_identity_context'] = is_array( $options['author_identity_context'] ) ? $options['author_identity_context'] : array();
		$options['media_preflight_context'] = is_array( $options['media_preflight_context'] ) ? $options['media_preflight_context'] : array();
		Snapshot::capture_before_mutation( 'legacy_file04_publication_migration' );
		$migrated = array();
		$skipped = array();
		$warnings = array();
		foreach ( $legacy_ids as $legacy_id ) {
			if ( self::target_for( $legacy_id ) ) {
				$skipped[ $legacy_id ] = 'already_migrated';
				continue;
			}
			$legacy = function_exists( 'get_post' ) ? get_post( $legacy_id ) : null;
			if ( ! is_object( $legacy ) || self::LEGACY_POST_TYPE !== (string) $legacy->post_type ) {
				$skipped[ $legacy_id ] = 'invalid_legacy_publication';
				continue;
			}
			$author_context = self::resolve_file04_author_context( $legacy_id, $legacy, $options );
			if ( is_wp_error( $author_context ) ) {
				$skipped[ $legacy_id ] = $author_context->get_error_code();
				continue;
			}
			$media_context = self::resolve_file04_media_context( $legacy_id, $options );
			if ( is_wp_error( $media_context ) ) {
				$skipped[ $legacy_id ] = $media_context->get_error_code();
				continue;
			}
			$target_type = self::target_type( $legacy, $options );
			$postarr = array(
				'post_type' => $target_type,
				'post_status' => self::target_status( $legacy, $target_type ),
				'post_author' => (int) $author_context['user_id'],
				'post_title' => (string) $legacy->post_title,
				'post_content' => (string) $legacy->post_content,
				'post_excerpt' => (string) $legacy->post_excerpt,
				'post_name' => (string) $legacy->post_name,
				'post_date' => (string) $legacy->post_date,
				'post_date_gmt' => (string) $legacy->post_date_gmt,
				'post_modified' => (string) $legacy->post_modified,
				'post_modified_gmt' => (string) $legacy->post_modified_gmt,
				'comment_status' => (string) $legacy->comment_status,
				'ping_status' => 'closed',
			);
			$target_id = function_exists( 'wp_insert_post' ) ? wp_insert_post( wp_slash( $postarr ), true ) : 0;
			if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $target_id ) ) || (int) $target_id <= 0 ) {
				$skipped[ $legacy_id ] = 'target_insert_failed';
				continue;
			}
			$target_id = (int) $target_id;
			$context_recorded = self::record_file04_context( $legacy_id, $target_id, $author_context, $media_context );
			if ( is_wp_error( $context_recorded ) ) {
				if ( function_exists( 'wp_delete_post' ) ) { wp_delete_post( $target_id, true ); }
				$skipped[ $legacy_id ] = $context_recorded->get_error_code();
				continue;
			}
			self::copy_public_metadata( $legacy_id, $target_id, $target_type );
			self::copy_terms( $legacy_id, $target_id, $target_type );
			$comment_map = ! empty( $options['copy_comments'] ) ? self::copy_comments( $legacy_id, $target_id ) : array();
			$interaction_report = self::interaction_report( $legacy_id, $target_id, $actor_id, $options );
			if ( ! empty( $options['migrate_interactions'] ) && ! in_array( $interaction_report['status'], array( 'migrated', 'nothing_to_migrate' ), true ) ) {
				$warnings[ $legacy_id ] = 'interaction_' . sanitize_key( $interaction_report['status'] );
			}
			self::record_mapping( $legacy_id, $target_id, $target_type, $comment_map, $interaction_report );
			$migrated[ $legacy_id ] = array(
				'target_id' => $target_id,
				'target_type' => $target_type,
				'comments_copied' => count( $comment_map ),
				'interactions' => $interaction_report,
			);
			AuditLog::record(
				'legacy_file04_publication_migrated',
				array(
					'legacy_id' => $legacy_id,
					'target_id' => $target_id,
					'target_type' => $target_type,
					'actor_id' => $actor_id,
					'interaction_status' => $interaction_report['status'],
					'interaction_provider' => $interaction_report['provider'],
				),
				'post',
				$target_id
			);
		}
		FeedQuery::invalidate_cache();
		$report = array(
			'success' => empty( $skipped ) && empty( $warnings ),
			'partial' => ! empty( $migrated ) && ( ! empty( $skipped ) || ! empty( $warnings ) ),
			'actor_id' => $actor_id,
			'migrated' => $migrated,
			'skipped' => $skipped,
			'warnings' => $warnings,
			'interaction_provider_requested' => $options['interaction_provider'],
			'interaction_migration_requested' => $options['migrate_interactions'],
			'created_at_utc' => gmdate( 'Y-m-d H:i:s' ),
			'destructive' => false,
			'automatic' => false,
		);
		if ( function_exists( 'update_option' ) ) {
			update_option( self::LAST_REPORT_OPTION, $report, false );
		}
		return $report;
	}

	/** Redirect an accepted migrated legacy single URL to its canonical target. */
	public static function redirect_migrated_legacy_single() {
		if ( ! function_exists( 'is_singular' ) || ! is_singular( self::LEGACY_POST_TYPE ) || ! function_exists( 'get_queried_object_id' ) ) {
			return;
		}
		$target_id = self::target_for( get_queried_object_id() );
		$url = $target_id > 0 && function_exists( 'get_permalink' ) ? get_permalink( $target_id ) : '';
		if ( $url && function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( $url, 301, 'Sabri File 21 Legacy Migration' );
			exit;
		}
	}

	/** Return mapped target ID. */
	public static function target_for( $legacy_id ) {
		$mapping = function_exists( 'get_option' ) ? get_option( self::MAPPING_OPTION, array() ) : array();
		if ( ! is_array( $mapping ) || ! isset( $mapping[ absint( $legacy_id ) ] ) || ! is_array( $mapping[ absint( $legacy_id ) ] ) ) {
			return 0;
		}
		$row = $mapping[ absint( $legacy_id ) ];
		return 'rolled_back' === ( isset( $row['status'] ) ? $row['status'] : '' ) ? 0 : ( isset( $row['target_id'] ) ? absint( $row['target_id'] ) : 0 );
	}

	/** Copy only public-safe and required metadata. */
	private static function copy_public_metadata( $legacy_id, $target_id, $target_type ) {
		if ( ! function_exists( 'get_post_meta' ) || ! function_exists( 'update_post_meta' ) ) {
			return;
		}
		$thumbnail = get_post_meta( $legacy_id, '_thumbnail_id', true );
		if ( absint( $thumbnail ) > 0 ) {
			update_post_meta( $target_id, '_thumbnail_id', absint( $thumbnail ) );
		}
		update_post_meta( $target_id, '_sabri_hnf_legacy_source_id', $legacy_id );
		update_post_meta( $target_id, '_sabri_hnf_legacy_source_type', self::LEGACY_POST_TYPE );
		if ( 'post' === $target_type ) {
			update_post_meta( $target_id, PostMetadata::META_REVIEW_STATE, 'publish' === get_post_status( $target_id ) ? 'approved' : 'pending' );
			update_post_meta( $target_id, PostMetadata::META_VISIBILITY, 'public' );
		}
	}

	/** Map legacy topics without deleting legacy terms. */
	private static function copy_terms( $legacy_id, $target_id, $target_type ) {
		if ( ! function_exists( 'wp_get_object_terms' ) || ! function_exists( 'wp_set_object_terms' ) ) {
			return;
		}
		$terms = wp_get_object_terms( $legacy_id, 'snp_topic', array( 'fields' => 'names' ) );
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $terms ) ) {
			return;
		}
		$terms = array_values( array_filter( array_map( 'sanitize_text_field', (array) $terms ) ) );
		if ( empty( $terms ) ) {
			return;
		}
		if ( 'post' === $target_type ) {
			wp_set_object_terms( $target_id, $terms, 'post_tag', true );
		} elseif ( class_exists( __NAMESPACE__ . '\\Phase4Contracts' ) ) {
			wp_set_object_terms( $target_id, $terms, 'sabri_news_topic', true );
		}
	}

	/** Copy approved comments and their public metadata, retaining originals. */
	private static function copy_comments( $legacy_id, $target_id ) {
		$map = array();
		if ( ! function_exists( 'get_comments' ) || ! function_exists( 'wp_insert_comment' ) ) {
			return $map;
		}
		$comments = get_comments( array( 'post_id' => $legacy_id, 'status' => 'approve', 'orderby' => 'comment_ID', 'order' => 'ASC' ) );
		foreach ( (array) $comments as $comment ) {
			if ( ! is_object( $comment ) ) {
				continue;
			}
			$parent = isset( $comment->comment_parent ) && isset( $map[ (int) $comment->comment_parent ] ) ? $map[ (int) $comment->comment_parent ] : 0;
			$new_id = wp_insert_comment(
				array(
					'comment_post_ID' => $target_id,
					'comment_author' => (string) $comment->comment_author,
					'comment_author_email' => (string) $comment->comment_author_email,
					'comment_author_url' => (string) $comment->comment_author_url,
					'comment_content' => (string) $comment->comment_content,
					'comment_type' => (string) $comment->comment_type,
					'comment_parent' => $parent,
					'user_id' => (int) $comment->user_id,
					'comment_date' => (string) $comment->comment_date,
					'comment_date_gmt' => (string) $comment->comment_date_gmt,
					'comment_approved' => 1,
				)
			);
			if ( (int) $new_id > 0 ) {
				$map[ (int) $comment->comment_ID ] = (int) $new_id;
				if ( function_exists( 'add_comment_meta' ) ) {
					add_comment_meta( $new_id, '_sabri_hnf_legacy_comment_id', (int) $comment->comment_ID, true );
				}
			}
		}
		return $map;
	}

	/** Explicit interaction report; no provider means no guessed migration. */
	private static function interaction_report( $legacy_id, $target_id, $actor_id, array $options ) {
		if ( empty( $options['migrate_interactions'] ) ) {
			return array(
				'status' => 'not_requested',
				'provider' => '',
				'migrated_records' => 0,
				'migrated_metrics' => array(),
				'skipped_records' => 0,
				'errors' => array(),
				'source_deleted' => false,
				'automatic' => false,
			);
		}
		if ( ! class_exists( __NAMESPACE__ . '\\LegacyInteractionMigrationAdapter' ) ) {
			return array(
				'status' => 'unavailable',
				'provider' => '',
				'migrated_records' => 0,
				'migrated_metrics' => array(),
				'skipped_records' => 0,
				'errors' => array( 'adapter_unavailable' ),
				'source_deleted' => false,
				'automatic' => false,
			);
		}
		return LegacyInteractionMigrationAdapter::migrate( $legacy_id, $target_id, $actor_id, $options['interaction_provider'] );
	}


	private static function resolve_file04_author_context( $legacy_id, $legacy, array $options ) {
		if ( empty( $options['require_file04_context'] ) ) {
			return array( 'user_id' => (int) $legacy->post_author, 'platform_uuid' => '', 'placeholder' => false );
		}
		$row = $options['author_identity_context'][ $legacy_id ] ?? $options['author_identity_context'][ (string) $legacy_id ] ?? null;
		if ( ! is_array( $row ) ) {
			return new \WP_Error( 'file21_file04_author_context_missing', 'File 04 migration author context is required.' );
		}
		$user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : 0;
		$uuid = strtolower( trim( (string) ( $row['platform_uuid'] ?? '' ) ) );
		if ( $user_id <= 0 || ! get_userdata( $user_id ) || 1 !== preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $uuid ) ) {
			return new \WP_Error( 'file21_file04_author_context_invalid', 'File 04 author identity context is invalid.' );
		}
		$current_uuid = apply_filters(
			'sabri_file00_platform_uuid_v1',
			'',
			$user_id,
			array( 'file_number' => '04', 'legacy_id' => $legacy_id, 'purpose' => 'legacy_publication_migration' )
		);
		$current_uuid = strtolower( trim( (string) $current_uuid ) );
		if ( '' === $current_uuid || ! hash_equals( $uuid, $current_uuid ) ) {
			return new \WP_Error( 'file21_file04_author_context_stale', 'File 00 no longer confirms the supplied author UUID.' );
		}
		return array( 'user_id' => $user_id, 'platform_uuid' => $uuid, 'placeholder' => ! empty( $row['placeholder'] ) );
	}

	private static function resolve_file04_media_context( $legacy_id, array $options ) {
		if ( empty( $options['require_file04_context'] ) ) {
			return array( 'provider_id' => '', 'reference_count' => 0, 'references' => array(), 'source_signature' => '', 'request_digest' => '' );
		}
		$row = $options['media_preflight_context'][ $legacy_id ] ?? $options['media_preflight_context'][ (string) $legacy_id ] ?? null;
		if ( ! is_array( $row ) ) {
			return new \WP_Error( 'file21_file04_media_context_missing', 'File 04 media preflight context is required.' );
		}
		$count = isset( $row['reference_count'] ) ? (int) $row['reference_count'] : -1;
		$references = isset( $row['references'] ) && is_array( $row['references'] ) ? array_values( $row['references'] ) : array();
		if ( $count < 0 || $count !== count( $references ) || $count > File04MigrationContracts::MAX_REFERENCES ) {
			return new \WP_Error( 'file21_file04_media_context_invalid', 'File 04 media preflight count is invalid.' );
		}
		if ( 0 === $count ) {
			return array( 'provider_id' => sanitize_key( (string) ( $row['provider_id'] ?? 'file04_no_media' ) ), 'reference_count' => 0, 'references' => array(), 'source_signature' => '', 'request_digest' => '' );
		}
		$source_signature = strtolower( trim( (string) ( $row['source_signature'] ?? '' ) ) );
		$request_digest = strtolower( trim( (string) ( $row['request_digest'] ?? '' ) ) );
		if ( File04MigrationContracts::PROVIDER_ID !== sanitize_key( (string) ( $row['provider_id'] ?? '' ) )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $source_signature )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/', $request_digest ) ) {
			return new \WP_Error( 'file21_file04_media_context_unbound', 'File 04 media context is not bound to the canonical File 21 attestation.' );
		}
		return array(
			'provider_id' => File04MigrationContracts::PROVIDER_ID,
			'reference_count' => $count,
			'references' => $references,
			'source_signature' => $source_signature,
			'request_digest' => $request_digest,
		);
	}

	private static function record_file04_context( $legacy_id, $target_id, array $author, array $media ) {
		if ( ! function_exists( 'update_post_meta' ) || ! function_exists( 'get_post_meta' ) ) { return true; }
		if ( '' !== (string) ( $author['platform_uuid'] ?? '' ) ) {
			update_post_meta( $target_id, '_sabri_hnf_legacy_author_platform_uuid', (string) $author['platform_uuid'] );
			update_post_meta( $target_id, '_sabri_hnf_legacy_author_placeholder', ! empty( $author['placeholder'] ) ? '1' : '0' );
			if ( ! hash_equals( (string) $author['platform_uuid'], (string) get_post_meta( $target_id, '_sabri_hnf_legacy_author_platform_uuid', true ) ) ) {
				return new \WP_Error( 'file21_file04_author_context_persist_failed', 'Canonical author migration evidence could not be persisted.' );
			}
		}
		$reference_ids = array();
		foreach ( (array) ( $media['references'] ?? array() ) as $reference ) {
			if ( is_array( $reference ) && ! empty( $reference['reference_id'] ) ) { $reference_ids[] = sanitize_text_field( (string) $reference['reference_id'] ); }
		}
		$attestation = array(
			'legacy_id' => (int) $legacy_id,
			'provider_id' => sanitize_key( (string) ( $media['provider_id'] ?? '' ) ),
			'source_signature' => (string) ( $media['source_signature'] ?? '' ),
			'preflight_request_digest' => (string) ( $media['request_digest'] ?? '' ),
			'reference_ids' => array_values( array_unique( $reference_ids ) ),
			'recorded_at_utc' => gmdate( 'Y-m-d H:i:s' ),
		);
		update_post_meta( $target_id, '_sabri_hnf_legacy_media_attestation_v1', $attestation );
		$stored = get_post_meta( $target_id, '_sabri_hnf_legacy_media_attestation_v1', true );
		if ( ! is_array( $stored ) || (int) ( $stored['legacy_id'] ?? 0 ) !== (int) $legacy_id ) {
			return new \WP_Error( 'file21_file04_media_context_persist_failed', 'Canonical media migration evidence could not be persisted.' );
		}
		return true;
	}

	/** Target post type chosen by explicit option or legacy editorial markers. */
	private static function target_type( $legacy, array $options ) {
		$requested = isset( $options['target'] ) ? sanitize_key( $options['target'] ) : 'auto';
		if ( in_array( $requested, array( 'post', 'sabri_news' ), true ) ) {
			return $requested;
		}
		$editorial = function_exists( 'get_post_meta' ) ? get_post_meta( $legacy->ID, '_snp_editorial_news', true ) : '';
		return $editorial && class_exists( __NAMESPACE__ . '\\Phase4Contracts' ) ? Phase4Contracts::POST_TYPE : 'post';
	}

	/** Safe status mapping; Editorial News never bypasses its workflow. */
	private static function target_status( $legacy, $target_type ) {
		$status = isset( $legacy->post_status ) ? sanitize_key( $legacy->post_status ) : 'draft';
		if ( class_exists( __NAMESPACE__ . '\\Phase4Contracts' ) && Phase4Contracts::POST_TYPE === $target_type ) {
			return 'draft';
		}
		return in_array( $status, array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) ? $status : 'draft';
	}

	/** Persist an idempotent migration mapping. */
	private static function record_mapping( $legacy_id, $target_id, $target_type, array $comment_map, array $interaction_report ) {
		$mapping = function_exists( 'get_option' ) ? get_option( self::MAPPING_OPTION, array() ) : array();
		$mapping = is_array( $mapping ) ? $mapping : array();
		$mapping[ $legacy_id ] = array(
			'target_id' => $target_id,
			'target_type' => $target_type,
			'comment_map' => $comment_map,
			'interaction_report' => $interaction_report,
			'status' => 'active',
			'migrated_at_utc' => gmdate( 'Y-m-d H:i:s' ),
		);
		if ( function_exists( 'update_option' ) ) {
			update_option( self::MAPPING_OPTION, $mapping, false );
		}
	}

	/** Public-safe candidate summary. */
	private static function candidate_summary( $post ) {
		return array( 'id' => (int) $post->ID, 'title' => (string) $post->post_title, 'author_id' => (int) $post->post_author, 'status' => (string) $post->post_status, 'slug' => (string) $post->post_name, 'published' => (string) $post->post_date_gmt, 'target' => 'auto' );
	}

	/** Migration authority. */
	private static function actor_can_migrate( $actor_id ) {
		return $actor_id > 0
			&& function_exists( 'get_current_user_id' )
			&& (int) get_current_user_id() === $actor_id
			&& CanonicalIdentityAdapter::current_action_ready( $actor_id )
			&& function_exists( 'current_user_can' )
			&& ( current_user_can( 'manage_options' ) || current_user_can( 'sabri_feed_run_migrations' ) );
	}
}
