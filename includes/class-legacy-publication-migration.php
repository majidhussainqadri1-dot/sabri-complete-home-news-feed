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
			'max_batch' => self::MAX_BATCH,
			'destructive' => false,
			'automatic' => false,
		);
	}

	/** Migrate only explicitly selected legacy IDs. */
	public static function migrate_selected( array $legacy_ids, $actor_id = 0, array $options = array() ) {
		$actor_id = $actor_id ? absint( $actor_id ) : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		if ( ! self::actor_can_migrate( $actor_id ) ) {
			return array( 'success' => false, 'error' => 'permission_denied', 'migrated' => array(), 'skipped' => array() );
		}
		$legacy_ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $legacy_ids ) ) ) ), 0, self::MAX_BATCH );
		if ( empty( $legacy_ids ) ) {
			return array( 'success' => false, 'error' => 'no_publications_selected', 'migrated' => array(), 'skipped' => array() );
		}
		$options = array_merge( array( 'copy_comments' => true, 'target' => 'auto' ), $options );
		Snapshot::capture_before_mutation( 'legacy_file04_publication_migration' );
		$migrated = array();
		$skipped = array();
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
			$target_type = self::target_type( $legacy, $options );
			$postarr = array(
				'post_type' => $target_type,
				'post_status' => self::target_status( $legacy, $target_type ),
				'post_author' => (int) $legacy->post_author,
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
			self::copy_public_metadata( $legacy_id, $target_id, $target_type );
			self::copy_terms( $legacy_id, $target_id, $target_type );
			$comment_map = ! empty( $options['copy_comments'] ) ? self::copy_comments( $legacy_id, $target_id ) : array();
			self::record_mapping( $legacy_id, $target_id, $target_type, $comment_map );
			$migrated[ $legacy_id ] = array( 'target_id' => $target_id, 'target_type' => $target_type, 'comments_copied' => count( $comment_map ) );
			AuditLog::record( 'legacy_file04_publication_migrated', array( 'legacy_id' => $legacy_id, 'target_id' => $target_id, 'target_type' => $target_type, 'actor_id' => $actor_id ), 'post', $target_id );
		}
		FeedQuery::invalidate_cache();
		$report = array( 'success' => empty( $skipped ), 'partial' => ! empty( $migrated ) && ! empty( $skipped ), 'actor_id' => $actor_id, 'migrated' => $migrated, 'skipped' => $skipped, 'created_at_utc' => gmdate( 'Y-m-d H:i:s' ), 'destructive' => false, 'automatic' => false );
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
		return is_array( $mapping ) && isset( $mapping[ absint( $legacy_id ) ]['target_id'] ) ? absint( $mapping[ absint( $legacy_id ) ]['target_id'] ) : 0;
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
	private static function record_mapping( $legacy_id, $target_id, $target_type, array $comment_map ) {
		$mapping = function_exists( 'get_option' ) ? get_option( self::MAPPING_OPTION, array() ) : array();
		$mapping = is_array( $mapping ) ? $mapping : array();
		$mapping[ $legacy_id ] = array( 'target_id' => $target_id, 'target_type' => $target_type, 'comment_map' => $comment_map, 'migrated_at_utc' => gmdate( 'Y-m-d H:i:s' ) );
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
		return $actor_id > 0 && function_exists( 'get_current_user_id' ) && (int) get_current_user_id() === $actor_id && function_exists( 'current_user_can' ) && ( current_user_can( 'manage_options' ) || current_user_can( 'sabri_feed_run_migrations' ) );
	}
}