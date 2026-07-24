<?php
/**
 * Approved public Editorial News snapshot and pending-correction boundary.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Preserves the last approved public version while corrections are private. */
final class NewsPublicSnapshot {
	const SNAPSHOT_META = '_sabri_news_public_snapshot_v1';
	const PENDING_META  = '_sabri_news_pending_correction_v1';
	const VERSION       = 1;

	/** Register non-destructive snapshot hooks. */
	public static function register() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'save_post_' . Phase4Contracts::POST_TYPE, array( __CLASS__, 'capture_on_save' ), 40, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'capture_on_meta_change' ), 40, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'capture_on_meta_change' ), 40, 4 );
	}

	/** Capture after an approved public article is persisted. */
	public static function capture_on_save( $post_id, $post, $update ) {
		unset( $update );
		if ( self::is_revision( $post_id ) || ! is_object( $post ) ) {
			return;
		}
		self::capture( $post_id );
	}

	/** Capture when the authoritative state becomes an approved public state. */
	public static function capture_on_meta_change( $meta_id, $post_id, $meta_key, $meta_value ) {
		unset( $meta_id );
		if ( Phase4Contracts::WORKFLOW_META_KEY !== $meta_key || ! in_array( (string) $meta_value, self::approved_states(), true ) ) {
			return;
		}
		self::capture( $post_id, true );
	}

	/** Store one inclusion-only approved public projection. */
	public static function capture( $post_id, $force = false ) {
		$post_id = self::positive_int( $post_id );
		$post = $post_id > 0 && function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		if ( ! is_object( $post ) || Phase4Contracts::POST_TYPE !== self::post_type( $post ) ) {
			return false;
		}
		$state = NewsPolicy::workflow_state( $post_id );
		if ( ! $force && ! in_array( $state, self::approved_states(), true ) ) {
			return false;
		}
		if ( ! in_array( self::post_status( $post ), array( 'publish' ), true ) ) {
			return false;
		}
		$payload = NewsPublicProjector::snapshot_payload( $post );
		if ( empty( $payload['article'] ) || empty( $payload['card'] ) ) {
			return false;
		}
		$snapshot = array(
			'version'         => self::VERSION,
			'captured_at_utc' => gmdate( 'c' ),
			'source_state'    => $state,
			'article'         => $payload['article'],
			'card'            => $payload['card'],
		);
		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, self::SNAPSHOT_META, $snapshot );
			NewsCache::invalidate();
			return true;
		}
		return false;
	}

	/** Read a validated approved snapshot. */
	public static function get( $post_id ) {
		$post_id = self::positive_int( $post_id );
		$value = $post_id > 0 && function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, self::SNAPSHOT_META, true ) : array();
		if ( ! is_array( $value ) || self::VERSION !== ( isset( $value['version'] ) ? (int) $value['version'] : 0 ) ) {
			return array();
		}
		if ( empty( $value['article'] ) || ! is_array( $value['article'] ) || empty( $value['card'] ) || ! is_array( $value['card'] ) ) {
			return array();
		}
		return $value;
	}

	/** Return only the last approved public article projection. */
	public static function article( $post_id ) {
		$snapshot = self::get( $post_id );
		return $snapshot ? $snapshot['article'] : array();
	}

	/** Return only the last approved public card projection. */
	public static function card( $post_id ) {
		$snapshot = self::get( $post_id );
		return $snapshot ? $snapshot['card'] : array();
	}

	/** Store private validated correction input without touching public fields. */
	public static function store_pending( $post_id, array $data, $featured_image_supplied = false ) {
		$post_id = self::positive_int( $post_id );
		if ( $post_id < 1 || ! function_exists( 'update_post_meta' ) ) {
			return self::result( false, 'pending_correction_storage_unavailable' );
		}
		$pending = array(
			'version'                   => self::VERSION,
			'stored_at_utc'             => gmdate( 'c' ),
			'title'                     => isset( $data['title'] ) ? (string) $data['title'] : '',
			'content'                   => isset( $data['content'] ) ? (string) $data['content'] : '',
			'subtitle'                  => isset( $data['subtitle'] ) ? (string) $data['subtitle'] : '',
			'summary'                   => isset( $data['summary'] ) ? (string) $data['summary'] : '',
			'language'                  => isset( $data['language'] ) ? (string) $data['language'] : 'en-US',
			'priority'                  => isset( $data['priority'] ) ? (int) $data['priority'] : 0,
			'section'                   => isset( $data['section'] ) ? (string) $data['section'] : '',
			'article_type'              => isset( $data['article_type'] ) ? (string) $data['article_type'] : '',
			'topics'                    => isset( $data['topics'] ) && is_array( $data['topics'] ) ? array_values( $data['topics'] ) : array(),
			'countries'                 => isset( $data['countries'] ) && is_array( $data['countries'] ) ? array_values( $data['countries'] ) : array(),
			'regions'                   => isset( $data['regions'] ) && is_array( $data['regions'] ) ? array_values( $data['regions'] ) : array(),
			'reviewing_editor_id'       => isset( $data['reviewing_editor_id'] ) ? (int) $data['reviewing_editor_id'] : 0,
			'medical_reviewer_id'       => isset( $data['medical_reviewer_id'] ) ? (int) $data['medical_reviewer_id'] : 0,
			'fact_check_required'       => ! empty( $data['fact_check_required'] ) ? 1 : 0,
			'medical_review_required'   => ! empty( $data['medical_review_required'] ) ? 1 : 0,
			'featured_image_id'         => isset( $data['featured_image_id'] ) ? (int) $data['featured_image_id'] : 0,
			'featured_image_supplied'   => $featured_image_supplied ? 1 : 0,
		);
		update_post_meta( $post_id, self::PENDING_META, $pending );
		return self::result( true, 'pending_correction_stored', array( 'post_id' => $post_id ) );
	}

	/** Read a private pending correction payload. */
	public static function pending( $post_id ) {
		$post_id = self::positive_int( $post_id );
		$value = $post_id > 0 && function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, self::PENDING_META, true ) : array();
		return is_array( $value ) && self::VERSION === ( isset( $value['version'] ) ? (int) $value['version'] : 0 ) ? $value : array();
	}

	/** Promote the private pending payload into the canonical public record. */
	public static function promote_pending( $post_id ) {
		$post_id = self::positive_int( $post_id );
		$pending = self::pending( $post_id );
		if ( $post_id < 1 || ! $pending ) {
			return self::result( false, 'pending_correction_missing' );
		}
		if ( ! function_exists( 'wp_update_post' ) ) {
			return self::result( false, 'pending_correction_persistence_unavailable' );
		}
		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_type'    => Phase4Contracts::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $pending['title'],
				'post_content' => $pending['content'],
				'post_excerpt' => $pending['summary'],
			),
			true
		);
		if ( false === $updated || ( function_exists( 'is_wp_error' ) && is_wp_error( $updated ) ) ) {
			return self::result( false, 'pending_correction_post_update_failed' );
		}

		if ( function_exists( 'update_post_meta' ) ) {
			$meta = array(
				'_sabri_news_subtitle'                => $pending['subtitle'],
				'_sabri_news_summary'                 => $pending['summary'],
				'_sabri_news_language'                => $pending['language'],
				'_sabri_news_priority'                => $pending['priority'],
				'_sabri_news_reviewing_editor_id'     => $pending['reviewing_editor_id'],
				'_sabri_news_medical_reviewer_id'     => $pending['medical_reviewer_id'],
				'_sabri_news_fact_check_required'     => $pending['fact_check_required'],
				'_sabri_news_medical_review_required' => $pending['medical_review_required'],
			);
			foreach ( $meta as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}
		}

		if ( function_exists( 'wp_set_object_terms' ) ) {
			$assignments = array(
				'sabri_news_section' => $pending['section'] ? array( $pending['section'] ) : array(),
				'sabri_news_type'    => $pending['article_type'] ? array( $pending['article_type'] ) : array(),
				'sabri_news_topic'   => $pending['topics'],
				'sabri_news_country' => $pending['countries'],
				'sabri_news_region'  => $pending['regions'],
			);
			foreach ( $assignments as $taxonomy => $terms ) {
				$result = wp_set_object_terms( $post_id, $terms, $taxonomy, false );
				if ( false === $result || ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) ) {
					return self::result( false, 'pending_correction_taxonomy_failed', array( 'taxonomy' => $taxonomy ) );
				}
			}
		}

		if ( ! empty( $pending['featured_image_supplied'] ) ) {
			$attachment_id = self::positive_int( $pending['featured_image_id'] );
			if ( $attachment_id > 0 && function_exists( 'set_post_thumbnail' ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			} elseif ( 0 === $attachment_id && function_exists( 'delete_post_thumbnail' ) ) {
				delete_post_thumbnail( $post_id );
			}
		}
		self::clear_pending( $post_id );
		return self::result( true, 'pending_correction_promoted', array( 'post_id' => $post_id ) );
	}

	/** Remove private pending data without deleting the public snapshot. */
	public static function clear_pending( $post_id ) {
		$post_id = self::positive_int( $post_id );
		return $post_id > 0 && function_exists( 'delete_post_meta' ) ? delete_post_meta( $post_id, self::PENDING_META ) : false;
	}

	/** Approved states eligible to replace the public snapshot. */
	public static function approved_states() {
		return array( 'published', 'updated', 'corrected' );
	}

	/** Detect revisions/autosaves. */
	private static function is_revision( $post_id ) {
		return ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) )
			|| ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) );
	}

	/** Exact object post type. */
	private static function post_type( $post ) {
		return isset( $post->post_type ) ? (string) $post->post_type : '';
	}

	/** Exact object status. */
	private static function post_status( $post ) {
		return isset( $post->post_status ) ? (string) $post->post_status : '';
	}

	/** Strict positive integer. */
	private static function positive_int( $value ) {
		return ( is_int( $value ) || ( is_string( $value ) && preg_match( '/^[1-9][0-9]*$/D', $value ) ) ) ? max( 0, (int) $value ) : 0;
	}

	/** Stable service result. */
	private static function result( $success, $code, array $data = array() ) {
		return array( 'success' => (bool) $success, 'code' => (string) $code, 'data' => $data );
	}
}
