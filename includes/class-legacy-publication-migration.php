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
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'sabri_file21_legacy_media_preflight_v1', array( __CLASS__, 'file04_media_preflight' ), 10, 2 );
			add_filter( 'sabri_file21_verify_migrated_legacy_media_v1', array( __CLASS__, 'file04_verify_migrated_media' ), 10, 2 );
			add_filter( 'sabri_file21_legacy_metadata_preflight_v1', array( __CLASS__, 'file04_metadata_preflight' ), 10, 2 );
			add_filter( 'sabri_file21_verify_migrated_legacy_metadata_v1', array( __CLASS__, 'file04_verify_migrated_metadata' ), 10, 2 );
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
				'copy_media' => true,
				'copy_references' => true,
				'target' => 'auto',
				'migrate_interactions' => false,
				'interaction_provider' => '',
				'author_identity_context' => array(),
				'media_preflight_context' => array(),
				'legacy_metadata_context' => array(),
			),
			$options
		);
		$options['target'] = in_array( sanitize_key( $options['target'] ), array( 'auto', 'post', 'sabri_news' ), true ) ? sanitize_key( $options['target'] ) : 'auto';
		$options['interaction_provider'] = sanitize_key( $options['interaction_provider'] );
		$options['migrate_interactions'] = ! empty( $options['migrate_interactions'] );
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
			$author_context = self::resolve_author_context( $legacy_id, $legacy, $options );
			if ( is_wp_error( $author_context ) ) {
				$skipped[ $legacy_id ] = $author_context->get_error_code();
				continue;
			}
			$metadata_context = self::resolve_legacy_metadata_context( $legacy_id, $options );
			if ( is_wp_error( $metadata_context ) ) {
				$skipped[ $legacy_id ] = $metadata_context->get_error_code();
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
			$media_context = isset( $options['media_preflight_context'][ $legacy_id ] ) && is_array( $options['media_preflight_context'][ $legacy_id ] ) ? $options['media_preflight_context'][ $legacy_id ] : array();
			self::copy_public_metadata( $legacy_id, $target_id, $target_type, $author_context, $media_context, $metadata_context );
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
	private static function copy_public_metadata( $legacy_id, $target_id, $target_type, array $author_context = array(), array $media_context = array(), array $metadata_context = array() ) {
		if ( ! function_exists( 'get_post_meta' ) || ! function_exists( 'update_post_meta' ) ) {
			return;
		}
		$thumbnail = get_post_meta( $legacy_id, '_thumbnail_id', true );
		if ( absint( $thumbnail ) > 0 ) {
			update_post_meta( $target_id, '_thumbnail_id', absint( $thumbnail ) );
		}
		update_post_meta( $target_id, '_sabri_hnf_legacy_source_id', $legacy_id );
		update_post_meta( $target_id, '_sabri_hnf_legacy_source_type', self::LEGACY_POST_TYPE );
		if ( ! empty( $author_context['platform_uuid'] ) ) {
			update_post_meta( $target_id, '_sabri_hnf_legacy_author_platform_uuid_v1', sanitize_text_field( (string) $author_context['platform_uuid'] ) );
			update_post_meta( $target_id, '_sabri_hnf_legacy_author_placeholder_v1', ! empty( $author_context['placeholder'] ) ? 1 : 0 );
		}
		if ( ! empty( $media_context ) ) {
			$reference_ids = array();
			foreach ( (array) ( $media_context['references'] ?? array() ) as $reference ) {
				if ( is_array( $reference ) && ! empty( $reference['reference_id'] ) ) {
					$reference_ids[] = sanitize_text_field( (string) $reference['reference_id'] );
				}
			}
			$manifest = array(
				'provider_id'       => sanitize_key( (string) ( $media_context['provider_id'] ?? '' ) ),
				'source_signature'  => strtolower( (string) ( $media_context['source_signature'] ?? '' ) ),
				'request_digest'    => strtolower( (string) ( $media_context['request_digest'] ?? '' ) ),
				'reference_ids'     => array_values( array_unique( array_filter( $reference_ids ) ) ),
				'reference_count'   => count( array_unique( array_filter( $reference_ids ) ) ),
				'preserved_by_ref'  => true,
				'copied_at_utc'     => gmdate( 'Y-m-d H:i:s' ),
			);
			update_post_meta( $target_id, '_sabri_hnf_legacy_media_reference_manifest_v1', $manifest );
		}
		if ( ! empty( $metadata_context['fields'] ) && is_array( $metadata_context['fields'] ) ) {
			$fields = self::normalize_legacy_metadata_fields( $metadata_context['fields'] );
			$provenance = array(
				'provider_id'      => sanitize_key( (string) ( $metadata_context['provider_id'] ?? '' ) ),
				'source_signature' => strtolower( (string) ( $metadata_context['source_signature'] ?? '' ) ),
				'request_digest'   => strtolower( (string) ( $metadata_context['request_digest'] ?? '' ) ),
				'fields'           => $fields,
				'copied_at_utc'    => gmdate( 'Y-m-d H:i:s' ),
			);
			update_post_meta( $target_id, '_sabri_hnf_legacy_metadata_v1', $provenance );
			if ( isset( $fields['_snp_language'] ) ) {
				if ( 'post' === $target_type ) {
					update_post_meta( $target_id, PostMetadata::META_LANGUAGE, (string) $fields['_snp_language'] );
				} elseif ( class_exists( __NAMESPACE__ . '\\Phase4Contracts' ) && Phase4Contracts::POST_TYPE === $target_type ) {
					update_post_meta( $target_id, '_sabri_news_language', (string) $fields['_snp_language'] );
				}
			}
			if ( 'post' === $target_type ) {
				if ( array_key_exists( '_snp_featured', $fields ) ) { update_post_meta( $target_id, PostMetadata::META_FEATURED, $fields['_snp_featured'] ? 1 : 0 ); }
				if ( array_key_exists( '_snp_pinned', $fields ) ) { update_post_meta( $target_id, PostMetadata::META_PINNED, $fields['_snp_pinned'] ? 1 : 0 ); }
				if ( ! empty( $fields['_snp_tags'] ) && function_exists( 'wp_set_object_terms' ) ) {
					wp_set_object_terms( $target_id, (array) $fields['_snp_tags'], 'post_tag', true );
				}
			}
		}
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
			$comment_user_id = isset( $comment->user_id ) ? absint( $comment->user_id ) : 0;
			if ( $comment_user_id > 0 && function_exists( 'get_userdata' ) && ! get_userdata( $comment_user_id ) ) { $comment_user_id = 0; }
			$new_id = wp_insert_comment(
				array(
					'comment_post_ID' => $target_id,
					'comment_author' => (string) $comment->comment_author,
					'comment_author_email' => (string) $comment->comment_author_email,
					'comment_author_url' => (string) $comment->comment_author_url,
					'comment_content' => (string) $comment->comment_content,
					'comment_type' => (string) $comment->comment_type,
					'comment_parent' => $parent,
					'user_id' => $comment_user_id,
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


	/**
	 * Bind a File 04 migration author to File 00's immutable identity contract.
	 */
	private static function resolve_author_context( $legacy_id, $legacy, array $options ) {
		$contexts = isset( $options['author_identity_context'] ) && is_array( $options['author_identity_context'] ) ? $options['author_identity_context'] : array();
		$row = isset( $contexts[ $legacy_id ] ) && is_array( $contexts[ $legacy_id ] ) ? $contexts[ $legacy_id ] : array();
		$legacy_author_id = isset( $legacy->post_author ) ? absint( $legacy->post_author ) : 0;
		if ( empty( $row ) ) {
			if ( $legacy_author_id <= 0 || ! function_exists( 'get_userdata' ) || ! get_userdata( $legacy_author_id ) ) {
				return new \WP_Error( 'file21_author_context_required', 'A canonical File 00 author identity is required for legacy migration.' );
			}
			$uuid = class_exists( 'SMC_CF01_Contract' ) && is_callable( array( 'SMC_CF01_Contract', 'ensure_subject_uuid' ) ) ? \SMC_CF01_Contract::ensure_subject_uuid( $legacy_author_id ) : '';
			$row = array( 'user_id' => $legacy_author_id, 'platform_uuid' => $uuid, 'placeholder' => false );
		}
		$user_id = self::positive_id( $row['user_id'] ?? 0 );
		$uuid = strtolower( trim( (string) ( $row['platform_uuid'] ?? '' ) ) );
		$placeholder = ! empty( $row['placeholder'] );
		if ( $user_id <= 0 || ! self::valid_uuid( $uuid ) || ! function_exists( 'get_userdata' ) || ! get_userdata( $user_id ) ) {
			return new \WP_Error( 'file21_author_context_invalid', 'The File 00 author identity context is invalid.' );
		}
		$canonical_uuid = class_exists( 'SMC_CF01_Contract' ) && is_callable( array( 'SMC_CF01_Contract', 'ensure_subject_uuid' ) ) ? \SMC_CF01_Contract::ensure_subject_uuid( $user_id ) : '';
		if ( ! self::valid_uuid( $canonical_uuid ) || ! hash_equals( strtolower( $canonical_uuid ), $uuid ) ) {
			return new \WP_Error( 'file21_author_uuid_mismatch', 'The supplied author UUID does not match File 00 canonical identity.' );
		}
		if ( ! $placeholder && $legacy_author_id > 0 && $legacy_author_id !== $user_id ) {
			return new \WP_Error( 'file21_author_rebinding_forbidden', 'A known legacy author cannot be silently rebound to another identity.' );
		}
		return array( 'user_id' => $user_id, 'platform_uuid' => $uuid, 'placeholder' => $placeholder );
	}

	/**
	 * File 21 provider for File 04 media/reference preflight. Technical facts are
	 * verified here; rights acceptance must be explicitly recorded on the legacy
	 * publication or provided by a canonical policy filter.
	 */
	public static function file04_media_preflight( $existing, $request ) {
		if ( is_array( $existing ) && ! empty( $existing['verified'] ) ) { return $existing; }
		$request = is_array( $request ) ? $request : array();
		$legacy_id = self::positive_id( $request['legacy_id'] ?? 0 );
		$source_signature = strtolower( trim( (string) ( $request['source_signature'] ?? '' ) ) );
		$request_digest = strtolower( trim( (string) ( $request['request_digest'] ?? '' ) ) );
		$refs = isset( $request['references'] ) && is_array( $request['references'] ) ? $request['references'] : array();
		if ( $legacy_id <= 0 || ! self::valid_hash( $source_signature ) || ! self::valid_hash( $request_digest ) || empty( $refs ) ) {
			return array( 'verified' => false, 'provider_id' => 'file21_legacy_media_v1' );
		}
		$source = function_exists( 'get_post' ) ? get_post( $legacy_id ) : null;
		if ( ! is_object( $source ) || self::LEGACY_POST_TYPE !== (string) $source->post_type ) {
			return array( 'verified' => false, 'provider_id' => 'file21_legacy_media_v1' );
		}
		$accepted = array(); $broken = 0; $ownership = true; $alt_ok = true;
		foreach ( $refs as $ref ) {
			if ( ! is_array( $ref ) || empty( $ref['reference_id'] ) || empty( $ref['type'] ) ) { $broken++; continue; }
			$type = sanitize_key( (string) $ref['type'] );
			$reference_id = sanitize_text_field( (string) $ref['reference_id'] );
			if ( 'attachment' === $type ) {
				$attachment_id = self::positive_id( $ref['attachment_id'] ?? 0 );
				$attachment = $attachment_id > 0 && function_exists( 'get_post' ) ? get_post( $attachment_id ) : null;
				$file = $attachment_id > 0 && function_exists( 'get_attached_file' ) ? get_attached_file( $attachment_id, true ) : '';
				$hash = is_string( $file ) && is_file( $file ) ? hash_file( 'sha256', $file ) : '';
				$relations = array_values( array_unique( array_intersect( array_map( 'sanitize_key', (array) ( $ref['relations'] ?? array( 'child' ) ) ), array( 'child', 'featured' ) ) ) );
				$relation_valid = ! empty( $relations );
				if ( in_array( 'child', $relations, true ) ) {
					$relation_valid = $relation_valid && absint( $attachment->post_parent ?? 0 ) === $legacy_id;
				}
				if ( in_array( 'featured', $relations, true ) ) {
					$relation_valid = $relation_valid && function_exists( 'get_post_meta' ) && absint( get_post_meta( $legacy_id, '_thumbnail_id', true ) ) === $attachment_id;
				}
				if ( ! is_object( $attachment ) || 'attachment' !== (string) $attachment->post_type || ! $relation_valid || ! self::valid_hash( $hash ) || ! hash_equals( strtolower( $hash ), strtolower( (string) ( $ref['sha256'] ?? '' ) ) ) ) {
					$ownership = false; $broken++; continue;
				}
				$mime = (string) ( $attachment->post_mime_type ?? '' );
				if ( 0 === strpos( $mime, 'image/' ) && function_exists( 'get_post_meta' ) && '' === trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) ) {
					$alt_ok = false;
				}
			} elseif ( 'legacy_reference' === $type ) {
				$key = sanitize_key( (string) ( $ref['meta_key'] ?? '' ) );
				if ( ! in_array( $key, array( '_snp_video_url', '_snp_media_manifest', '_snp_source_ledger' ), true ) ) { $ownership = false; $broken++; continue; }
				$value = function_exists( 'get_post_meta' ) ? get_post_meta( $legacy_id, $key, true ) : '';
				if ( '' === $value && empty( $value ) ) { $broken++; continue; }
			} else {
				$ownership = false; $broken++; continue;
			}
			$accepted[] = $reference_id;
		}
		$rights = function_exists( 'get_post_meta' ) && ! empty( get_post_meta( $legacy_id, '_snp_media_rights_verified', true ) );
		$rights = (bool) apply_filters( 'sabri_hnf_file04_media_rights_verified_v1', $rights, $legacy_id, $refs );
		$verified = $ownership && $rights && $alt_ok && 0 === $broken && count( $accepted ) === count( $refs );
		return array(
			'verified'                    => $verified,
			'provider_id'                 => 'file21_legacy_media_v1',
			'legacy_id'                   => $legacy_id,
			'source_signature'            => $source_signature,
			'request_digest'              => $request_digest,
			'ownership_verified'          => $ownership,
			'rights_or_license_verified'  => $rights,
			'alt_policy_verified'         => $alt_ok,
			'duplicate_hash_checked'      => true,
			'broken_links'                => $broken,
			'canonical_target_contract'   => true,
			'accepted_reference_ids'      => array_values( array_unique( $accepted ) ),
			'verified_at_utc'             => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	/** Verify that File 04 media/reference evidence was bound to the migrated target. */
	public static function file04_verify_migrated_media( $existing, $request ) {
		if ( is_array( $existing ) && ! empty( $existing['verified'] ) ) { return $existing; }
		$request = is_array( $request ) ? $request : array();
		$legacy_id = self::positive_id( $request['legacy_id'] ?? 0 );
		$target_id = self::positive_id( $request['target_id'] ?? 0 );
		$request_digest = strtolower( trim( (string) ( $request['request_digest'] ?? '' ) ) );
		$source_signature = strtolower( trim( (string) ( $request['source_signature'] ?? '' ) ) );
		if ( $legacy_id <= 0 || $target_id <= 0 || ! self::valid_hash( $request_digest ) || ! self::valid_hash( $source_signature ) ) {
			return array( 'verified' => false, 'provider_id' => 'file21_legacy_media_v1' );
		}
		$target = function_exists( 'get_post' ) ? get_post( $target_id ) : null;
		$manifest = function_exists( 'get_post_meta' ) ? get_post_meta( $target_id, '_sabri_hnf_legacy_media_reference_manifest_v1', true ) : array();
		$expected = array();
		foreach ( (array) ( $request['references'] ?? array() ) as $ref ) {
			if ( is_array( $ref ) && ! empty( $ref['reference_id'] ) ) { $expected[] = sanitize_text_field( (string) $ref['reference_id'] ); }
		}
		$expected = array_values( array_unique( array_filter( $expected ) ) ); sort( $expected );
		$actual = is_array( $manifest ) ? array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) ( $manifest['reference_ids'] ?? array() ) ) ) ) ) : array(); sort( $actual );
		$provenance = function_exists( 'get_post_meta' ) ? absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) : 0;
		$relation_verified = true;
		foreach ( (array) ( $request['references'] ?? array() ) as $ref ) {
			if ( ! is_array( $ref ) || 'attachment' !== sanitize_key( (string) ( $ref['type'] ?? '' ) ) ) { continue; }
			$relations = array_values( array_unique( array_map( 'sanitize_key', (array) ( $ref['relations'] ?? array( 'child' ) ) ) ) );
			if ( in_array( 'featured', $relations, true ) && absint( get_post_meta( $target_id, '_thumbnail_id', true ) ) !== self::positive_id( $ref['attachment_id'] ?? 0 ) ) {
				$relation_verified = false;
				break;
			}
		}
		$verified = is_object( $target ) && $provenance === $legacy_id && $expected === $actual && ! empty( $expected ) && $relation_verified
			&& is_array( $manifest ) && hash_equals( $source_signature, strtolower( (string) ( $manifest['source_signature'] ?? '' ) ) );
		return array(
			'verified'                 => $verified,
			'provider_id'              => 'file21_legacy_media_v1',
			'legacy_id'                => $legacy_id,
			'target_id'                => $target_id,
			'source_signature'         => $source_signature,
			'request_digest'           => $request_digest,
			'verified_reference_count' => $verified ? count( $actual ) : 0,
			'verified_at_utc'          => gmdate( 'Y-m-d H:i:s' ),
		);
	}


	/** Return the exact known legacy fields governed by the File 21 mapping contract. */
	private static function legacy_metadata_source_fields( $legacy_id ) {
		$fields = array();
		if ( ! function_exists( 'get_post_meta' ) ) { return $fields; }
		foreach ( array( '_snp_tags', '_snp_language', '_snp_featured', '_snp_pinned', '_snp_video_url' ) as $key ) {
			$value = get_post_meta( $legacy_id, $key, true );
			$present = is_array( $value ) ? ! empty( $value ) : ( is_scalar( $value ) && '' !== trim( (string) $value ) );
			if ( $present ) { $fields[ $key ] = self::canonicalize_legacy_value( $value ); }
		}
		ksort( $fields );
		return $fields;
	}

	private static function canonicalize_legacy_value( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $item ) { $out[ $key ] = self::canonicalize_legacy_value( $item ); }
			if ( ! empty( $out ) && array_keys( $out ) !== range( 0, count( $out ) - 1 ) ) { ksort( $out, SORT_STRING ); }
			return $out;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) { return $value; }
		return is_scalar( $value ) ? (string) $value : '';
	}

	private static function normalize_legacy_boolean( $value ) {
		if ( true === $value || 1 === $value ) { return 1; }
		if ( false === $value || 0 === $value ) { return 0; }
		if ( ! is_scalar( $value ) ) { return null; }
		$value = strtolower( trim( (string) $value ) );
		if ( in_array( $value, array( '1', 'true', 'yes', 'on' ), true ) ) { return 1; }
		if ( in_array( $value, array( '0', 'false', 'no', 'off' ), true ) ) { return 0; }
		return null;
	}

	private static function normalize_legacy_tags( $value ) {
		$raw = is_array( $value ) ? $value : preg_split( '/[,;\r\n]+/', (string) $value );
		$tags = array();
		foreach ( (array) $raw as $item ) {
			if ( ! is_scalar( $item ) ) { return null; }
			$item = sanitize_text_field( (string) $item );
			if ( '' === $item ) { continue; }
			if ( strlen( $item ) > 100 ) { return null; }
			$tags[] = $item;
			if ( count( $tags ) > 50 ) { return null; }
		}
		$tags = array_values( array_unique( $tags ) );
		sort( $tags, SORT_NATURAL | SORT_FLAG_CASE );
		return $tags;
	}

	private static function normalize_legacy_metadata_fields( array $fields ) {
		$normalized = array();
		foreach ( $fields as $key => $value ) {
			$key = sanitize_key( (string) $key );
			switch ( $key ) {
				case '_snp_tags':
					$tags = self::normalize_legacy_tags( $value );
					if ( null !== $tags && ! empty( $tags ) ) { $normalized[ $key ] = $tags; }
					break;
				case '_snp_language':
					$language = is_scalar( $value ) ? trim( (string) $value ) : '';
					if ( '' !== $language && strlen( $language ) <= 20 && 1 === preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $language ) ) { $normalized[ $key ] = $language; }
					break;
				case '_snp_featured':
				case '_snp_pinned':
					$boolean = self::normalize_legacy_boolean( $value );
					if ( null !== $boolean ) { $normalized[ $key ] = $boolean; }
					break;
				case '_snp_video_url':
					$url = is_scalar( $value ) ? trim( (string) $value ) : '';
					$parts = '' !== $url && strlen( $url ) <= 2048 ? ( function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url ) ) : false;
					$scheme = is_array( $parts ) ? strtolower( (string) ( $parts['scheme'] ?? '' ) ) : '';
					$host = is_array( $parts ) ? trim( (string) ( $parts['host'] ?? '' ) ) : '';
					if ( in_array( $scheme, array( 'http', 'https' ), true ) && '' !== $host ) {
						$normalized[ $key ] = function_exists( 'esc_url_raw' ) ? esc_url_raw( $url, array( 'http', 'https' ) ) : $url;
					}
					break;
			}
		}
		ksort( $normalized );
		return $normalized;
	}

	/**
	 * Require a File 04 supplied metadata context whenever governed legacy fields
	 * are present. Direct privileged File 21 calls cannot silently drop them.
	 */
	private static function resolve_legacy_metadata_context( $legacy_id, array $options ) {
		$current = self::legacy_metadata_source_fields( $legacy_id );
		$contexts = isset( $options['legacy_metadata_context'] ) && is_array( $options['legacy_metadata_context'] ) ? $options['legacy_metadata_context'] : array();
		$row = isset( $contexts[ $legacy_id ] ) && is_array( $contexts[ $legacy_id ] ) ? $contexts[ $legacy_id ] : array();
		if ( empty( $current ) ) { return array( 'verified' => true, 'fields' => array() ); }
		if ( empty( $row ) || empty( $row['provider_id'] ) || ! self::valid_hash( (string) ( $row['source_signature'] ?? '' ) ) || ! self::valid_hash( (string) ( $row['request_digest'] ?? '' ) ) ) {
			return new \WP_Error( 'file21_legacy_metadata_context_required', 'Complete File 04 legacy metadata mapping evidence is required.' );
		}
		$supplied = isset( $row['fields'] ) && is_array( $row['fields'] ) ? self::canonicalize_legacy_value( $row['fields'] ) : array();
		if ( serialize( $current ) !== serialize( $supplied ) ) {
			return new \WP_Error( 'file21_legacy_metadata_context_changed', 'Legacy metadata changed after preflight.' );
		}
		$normalized = self::normalize_legacy_metadata_fields( $current );
		if ( array_keys( $normalized ) !== array_keys( $current ) ) {
			return new \WP_Error( 'file21_legacy_metadata_invalid', 'One or more legacy metadata values cannot be mapped safely.' );
		}
		$row['fields'] = $current;
		$row['verified'] = true;
		return $row;
	}

	/** File 21 attestation that all present known legacy publication fields are safely mappable. */
	public static function file04_metadata_preflight( $existing, $request ) {
		if ( is_array( $existing ) && ! empty( $existing['verified'] ) ) { return $existing; }
		$request = is_array( $request ) ? $request : array();
		$legacy_id = self::positive_id( $request['legacy_id'] ?? 0 );
		$source_signature = strtolower( trim( (string) ( $request['source_signature'] ?? '' ) ) );
		$request_digest = strtolower( trim( (string) ( $request['request_digest'] ?? '' ) ) );
		$requested = isset( $request['fields'] ) && is_array( $request['fields'] ) ? self::canonicalize_legacy_value( $request['fields'] ) : array();
		$current = $legacy_id > 0 ? self::legacy_metadata_source_fields( $legacy_id ) : array();
		$normalized = self::normalize_legacy_metadata_fields( $current );
		$valid = $legacy_id > 0 && self::valid_hash( $source_signature ) && self::valid_hash( $request_digest )
			&& ! empty( $current ) && serialize( $current ) === serialize( $requested ) && array_keys( $normalized ) === array_keys( $current );
		return array(
			'verified'         => $valid,
			'provider_id'      => 'file21_legacy_metadata_v1',
			'legacy_id'        => $legacy_id,
			'source_signature' => $source_signature,
			'request_digest'   => $request_digest,
			'accepted_fields'  => $valid ? array_keys( $current ) : array(),
			'verified_at_utc'  => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	/** Verify that mapped legacy fields and their provenance survived canonical migration. */
	public static function file04_verify_migrated_metadata( $existing, $request ) {
		if ( is_array( $existing ) && ! empty( $existing['verified'] ) ) { return $existing; }
		$request = is_array( $request ) ? $request : array();
		$legacy_id = self::positive_id( $request['legacy_id'] ?? 0 );
		$target_id = self::positive_id( $request['target_id'] ?? 0 );
		$request_digest = strtolower( trim( (string) ( $request['request_digest'] ?? '' ) ) );
		$source_signature = strtolower( trim( (string) ( $request['source_signature'] ?? '' ) ) );
		$fields = isset( $request['fields'] ) && is_array( $request['fields'] ) ? self::canonicalize_legacy_value( $request['fields'] ) : array();
		$expected = self::normalize_legacy_metadata_fields( $fields );
		$target = $target_id > 0 && function_exists( 'get_post' ) ? get_post( $target_id ) : null;
		$provenance = $target_id > 0 && function_exists( 'get_post_meta' ) ? get_post_meta( $target_id, '_sabri_hnf_legacy_metadata_v1', true ) : array();
		$stored_fields = is_array( $provenance ) && is_array( $provenance['fields'] ?? null ) ? self::normalize_legacy_metadata_fields( $provenance['fields'] ) : array();
		$verified = $legacy_id > 0 && $target_id > 0 && self::valid_hash( $request_digest ) && self::valid_hash( $source_signature )
			&& is_object( $target ) && ! empty( $expected ) && serialize( $expected ) === serialize( $stored_fields )
			&& absint( get_post_meta( $target_id, '_sabri_hnf_legacy_source_id', true ) ) === $legacy_id
			&& is_array( $provenance ) && hash_equals( $source_signature, strtolower( (string) ( $provenance['source_signature'] ?? '' ) ) );

		if ( $verified && 'post' === (string) $target->post_type ) {
			if ( isset( $expected['_snp_language'] ) ) { $verified = $verified && (string) get_post_meta( $target_id, PostMetadata::META_LANGUAGE, true ) === (string) $expected['_snp_language']; }
			if ( array_key_exists( '_snp_featured', $expected ) ) { $verified = $verified && absint( get_post_meta( $target_id, PostMetadata::META_FEATURED, true ) ) === (int) $expected['_snp_featured']; }
			if ( array_key_exists( '_snp_pinned', $expected ) ) { $verified = $verified && absint( get_post_meta( $target_id, PostMetadata::META_PINNED, true ) ) === (int) $expected['_snp_pinned']; }
			if ( ! empty( $expected['_snp_tags'] ) && function_exists( 'wp_get_object_terms' ) ) {
				$actual_tags = wp_get_object_terms( $target_id, 'post_tag', array( 'fields' => 'names' ) );
				$actual_tags = is_wp_error( $actual_tags ) ? array() : array_map( 'sanitize_text_field', (array) $actual_tags );
				foreach ( (array) $expected['_snp_tags'] as $tag ) { if ( ! in_array( $tag, $actual_tags, true ) ) { $verified = false; break; } }
			}
		} elseif ( $verified && class_exists( __NAMESPACE__ . '\\Phase4Contracts' ) && Phase4Contracts::POST_TYPE === (string) $target->post_type && isset( $expected['_snp_language'] ) ) {
			$verified = (string) get_post_meta( $target_id, '_sabri_news_language', true ) === (string) $expected['_snp_language'];
		}

		return array(
			'verified'         => $verified,
			'provider_id'      => 'file21_legacy_metadata_v1',
			'legacy_id'        => $legacy_id,
			'target_id'        => $target_id,
			'source_signature' => $source_signature,
			'request_digest'   => $request_digest,
			'verified_fields'  => $verified ? array_keys( $expected ) : array(),
			'verified_at_utc'  => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	private static function valid_hash( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/i', $value );
	}

	private static function valid_uuid( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $value );
	}

	private static function positive_id( $value ) {
		if ( is_int( $value ) ) { return $value > 0 ? $value : 0; }
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) { return 0; }
		$parsed = (int) $value;
		return $parsed > 0 && (string) $parsed === $value ? $parsed : 0;
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
