<?php
/**
 * Search/discovery projection contracts for File 20 legacy shell search and the
 * canonical File 26 federated Search, Discovery and Ranking owner.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers visibility-safe Home/News projections without duplicating source data. */
final class SearchProviderRegistry {
	const MAX_QUERY_LENGTH = 120;
	const MAX_RESULTS_PER_PROVIDER = 20;
	const FILE26_CONNECTOR_SLUG = 'file21-publication';
	const FILE26_CONTRACT_VERSION = '1.0';

	/** Register legacy read adapters plus the canonical File 26 owner connector. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			// Compatibility only: File 20 may still expose these read-only providers
			// while File 26 is shadow-indexed and accepted. File 21 is not the global
			// search/ranking owner.
			add_filter( 'sabri_search_providers', array( __CLASS__, 'register_providers' ) );
			add_filter( 'sabri_shell_search_providers', array( __CLASS__, 'register_providers' ) );
			add_filter( 'sabri_search_results', array( __CLASS__, 'append_results' ), 10, 3 );
			add_filter( 'sabri_shell_search_results', array( __CLASS__, 'append_results' ), 10, 3 );
		}
		if ( function_exists( 'add_action' ) ) {
			// File 26 normally boots before File 21 (plugins_loaded priority 5). The
			// ready hook also supports controlled alternative load orders.
			add_action( 'sabri_file26_connectors_ready', array( __CLASS__, 'register_file26_connector' ), 20, 1 );
			add_action( 'save_post', array( __CLASS__, 'file26_on_save_post' ), 100, 3 );
			add_action( 'added_post_meta', array( __CLASS__, 'file26_on_meta_change' ), 100, 4 );
			add_action( 'updated_post_meta', array( __CLASS__, 'file26_on_meta_change' ), 100, 4 );
			add_action( 'deleted_post_meta', array( __CLASS__, 'file26_on_meta_change' ), 100, 4 );
			add_action( 'before_delete_post', array( __CLASS__, 'file26_on_delete_post' ), 100, 2 );
		}
		self::register_file26_connector();
	}

	/** File 21 provider definitions retained only as a fail-soft compatibility read path. */
	public static function providers() {
		return array(
			'file21-posts' => array(
				'label' => __( 'Posts', 'sabri-complete-home-news-feed' ),
				'callback' => array( __CLASS__, 'search_posts' ),
				'visibility' => 'object-authorized',
				'max_results' => self::MAX_RESULTS_PER_PROVIDER,
			),
			'file21-news' => array(
				'label' => __( 'News', 'sabri-complete-home-news-feed' ),
				'callback' => array( __CLASS__, 'search_news' ),
				'visibility' => 'approved-public-projection',
				'max_results' => self::MAX_RESULTS_PER_PROVIDER,
			),
		);
	}

	/** Merge File 21 providers into an older shared registry without claiming ownership. */
	public static function register_providers( $providers ) {
		$providers = is_array( $providers ) ? $providers : array();
		return array_merge( $providers, self::providers() );
	}

	/** Append normalized compatibility results when an older Shell calls a result filter. */
	public static function append_results( $results, $query = '', $args = array() ) {
		$results = is_array( $results ) ? $results : array();
		$query = self::normalize_query( $query );
		$args = is_array( $args ) ? $args : array();
		if ( '' === $query ) {
			return $results;
		}
		$limit = isset( $args['per_provider'] ) ? max( 1, min( self::MAX_RESULTS_PER_PROVIDER, (int) $args['per_provider'] ) ) : 10;
		foreach ( self::providers() as $provider_id => $provider ) {
			$items = call_user_func( $provider['callback'], $query, $limit );
			$results[ $provider_id ] = array(
				'provider' => $provider_id,
				'label' => $provider['label'],
				'items' => $items,
			);
		}
		return $results;
	}

	/**
	 * Register File 21 as a source connector with File 26.
	 *
	 * The connector always begins as proposed. File 26 owns lifecycle promotion
	 * through contract-tested -> shadow -> approved/active after staging evidence;
	 * File 21 cannot silently activate global search or ranking.
	 */
	public static function register_file26_connector( $registry = null ) {
		unset( $registry );
		if ( ! function_exists( 'sabri_file26_register_connector' ) ) {
			return false;
		}
		return sabri_file26_register_connector(
			array(
				'slug' => self::FILE26_CONNECTOR_SLUG,
				'owner_file' => '21',
				'contract_version' => self::FILE26_CONTRACT_VERSION,
				'entity_types' => array( 'post', 'news' ),
				'privacy_classes' => array( 'public' ),
				'visibility_fields' => array( 'state', 'visibility', 'review_state', 'workflow_state' ),
				'deletion_semantics' => 'tombstone',
				'status' => 'proposed',
				'list_batch' => array( __CLASS__, 'file26_list_batch' ),
				'can_view' => array( __CLASS__, 'file26_can_view' ),
				'health' => array( __CLASS__, 'file26_health' ),
			)
		);
	}

	/** Bounded, restartable source batch for File 26 shadow reindex. */
	public static function file26_list_batch( $cursor, $limit, $scope = array() ) {
		unset( $scope );
		$offset = is_scalar( $cursor ) && preg_match( '/^\d+$/', (string) $cursor ) ? max( 0, (int) $cursor ) : 0;
		$limit = max( 1, min( 100, (int) $limit ) );
		if ( ! class_exists( 'WP_Query' ) ) {
			return array( 'items' => array(), 'next_cursor' => (string) $offset, 'done' => true );
		}
		$query = new \WP_Query(
			array(
				'post_type' => array( 'post', Phase4Contracts::POST_TYPE ),
				'post_status' => 'publish',
				'posts_per_page' => $limit,
				'offset' => $offset,
				'orderby' => 'ID',
				'order' => 'ASC',
				'no_found_rows' => true,
				'ignore_sticky_posts' => true,
			)
		);
		$raw = is_array( $query->posts ) ? $query->posts : array();
		$items = array();
		foreach ( $raw as $post ) {
			$document = self::file26_document( $post );
			if ( $document ) {
				$items[] = $document;
			}
		}
		$scanned = count( $raw );
		return array(
			'items' => $items,
			'next_cursor' => (string) ( $offset + $scanned ),
			'done' => $scanned < $limit,
		);
	}

	/** File 26 click/query-time owner authorization callback. */
	public static function file26_can_view( $document, $audience = array() ) {
		if ( ! is_array( $document ) ) {
			return false;
		}
		$post_id = isset( $document['object_id'] ) ? absint( $document['object_id'] ) : 0;
		$type = isset( $document['entity_type'] ) ? sanitize_key( $document['entity_type'] ) : '';
		if ( $post_id < 1 || ! in_array( $type, array( 'post', 'news' ), true ) ) {
			return false;
		}
		if ( 'post' === $type ) {
			// General File 26 indexing is public-only. Member/private feed scopes stay
			// native to File 21 and must never be broadened by search availability.
			return 'publish' === get_post_status( $post_id )
				&& 'public' === PostMetadata::visibility( $post_id )
				&& PostMetadata::review_state_publicly_visible( $post_id )
				&& PostMetadata::user_can_view( $post_id, 0 );
		}
		$post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		return $post && class_exists( __NAMESPACE__ . '\\NewsPublicProjector' ) && ! empty( NewsPublicProjector::card( $post ) );
	}

	/** Privacy-safe connector health; no content, tokens or user data are exposed. */
	public static function file26_health() {
		return array(
			'state' => function_exists( 'sabri_file26_register_connector' ) ? 'healthy' : 'degraded',
			'owner_file' => '21',
			'connector' => self::FILE26_CONNECTOR_SLUG,
			'contract_version' => self::FILE26_CONTRACT_VERSION,
			'package_version' => defined( 'SABRI_HNF_PACKAGE_VERSION' ) ? SABRI_HNF_PACKAGE_VERSION : '',
			'global_search_owner' => '26',
		);
	}

	/** Sync a post/status change into File 26 only as a derivative projection. */
	public static function file26_on_save_post( $post_id, $post, $update ) {
		unset( $update );
		if ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) {
			return;
		}
		self::file26_sync_post( $post_id, $post );
	}

	/** Sync visibility/workflow metadata changes that can occur after save_post. */
	public static function file26_on_meta_change( $meta_id, $post_id, $meta_key, $meta_value = null ) {
		unset( $meta_id, $meta_value );
		$keys = array(
			PostMetadata::META_VISIBILITY,
			PostMetadata::META_REVIEW_STATE,
			PostMetadata::META_LANGUAGE,
			PostMetadata::META_REGION,
			PostMetadata::META_TYPE,
			Phase4Contracts::WORKFLOW_META_KEY,
			'_sabri_news_retraction_status',
			'_sabri_news_correction_status',
			'_sabri_news_language',
		);
		if ( ! in_array( (string) $meta_key, $keys, true ) ) {
			return;
		}
		self::file26_sync_post( $post_id );
	}

	/** Tombstone before source deletion so stale search results cannot survive. */
	public static function file26_on_delete_post( $post_id, $post = null ) {
		$post = is_object( $post ) ? $post : ( function_exists( 'get_post' ) ? get_post( $post_id ) : null );
		if ( ! self::is_owned_search_post( $post ) || ! function_exists( 'sabri_file26_tombstone_document' ) ) {
			return;
		}
		sabri_file26_tombstone_document(
			self::FILE26_CONNECTOR_SLUG,
			'publication',
			(string) absint( $post_id ),
			self::file26_object_version( $post ),
			'deleted'
		);
	}

	/** Emit an upsert or tombstone without transferring canonical ownership. */
	private static function file26_sync_post( $post_id, $post = null ) {
		$post = is_object( $post ) ? $post : ( function_exists( 'get_post' ) ? get_post( $post_id ) : null );
		if ( ! self::is_owned_search_post( $post ) ) {
			return;
		}
		$document = self::file26_document( $post );
		if ( $document && function_exists( 'do_action' ) ) {
			do_action( 'sabri_file26_source_upsert', $document );
			return;
		}
		if ( function_exists( 'sabri_file26_tombstone_document' ) ) {
			sabri_file26_tombstone_document(
				self::FILE26_CONNECTOR_SLUG,
				'publication',
				(string) absint( $post->ID ),
				self::file26_object_version( $post ),
				'restricted'
			);
		}
	}

	/** Build an allowlisted File 26 document only from a current public projection. */
	private static function file26_document( $post ) {
		if ( ! self::is_owned_search_post( $post ) || 'publish' !== (string) $post->post_status ) {
			return array();
		}
		$post_id = (int) $post->ID;
		$type = (string) $post->post_type;
		$entity_type = 'post';
		$title = '';
		$excerpt = '';
		$url = '';
		$locale = 'en-US';
		$state = 'published';
		$topics = array();
		$region = '';
		$content_type = '';

		if ( Phase4Contracts::POST_TYPE === $type ) {
			if ( ! class_exists( __NAMESPACE__ . '\\NewsPublicProjector' ) ) {
				return array();
			}
			$card = NewsPublicProjector::card( $post );
			if ( empty( $card ) ) {
				return array();
			}
			$entity_type = 'news';
			$title = isset( $card['headline'] ) ? (string) $card['headline'] : '';
			$excerpt = isset( $card['summary'] ) ? (string) $card['summary'] : '';
			$url = isset( $card['canonical_url'] ) ? (string) $card['canonical_url'] : '';
			$locale = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $post_id, '_sabri_news_language', true ) : 'en-US';
			$state = class_exists( __NAMESPACE__ . '\\NewsPolicy' ) ? sanitize_key( NewsPolicy::workflow_state( $post_id ) ) : 'published';
			$state = in_array( $state, array( 'published', 'updated', 'corrected' ), true ) ? ( 'corrected' === $state ? 'corrected' : 'published' ) : 'published';
			$content_type = isset( $card['public_label'] ) ? (string) $card['public_label'] : 'News';
			$topics = self::term_slugs( $post_id, 'sabri_news_topic' );
		} else {
			if ( 'public' !== PostMetadata::visibility( $post_id ) || ! PostMetadata::review_state_publicly_visible( $post_id ) || ! PostMetadata::user_can_view( $post_id, 0 ) ) {
				return array();
			}
			$title = function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : (string) $post->post_title;
			$url = function_exists( 'get_permalink' ) ? (string) get_permalink( $post_id ) : '';
			$excerpt = function_exists( 'get_the_excerpt' ) ? (string) get_the_excerpt( $post_id ) : '';
			$locale = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $post_id, PostMetadata::META_LANGUAGE, true ) : 'en-US';
			$region = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $post_id, PostMetadata::META_REGION, true ) : '';
			$content_type = PostMetadata::feed_type( $post_id );
			$topics = self::term_slugs( $post_id, 'sabri_feed_topic' );
		}

		$title = trim( $title );
		$url = trim( $url );
		if ( '' === $title || '' === $url ) {
			return array();
		}
		$locale = trim( $locale );
		if ( '' === $locale && function_exists( 'get_bloginfo' ) ) {
			$locale = (string) get_bloginfo( 'language' );
		}
		if ( '' === $locale ) {
			$locale = 'en-US';
		}
		$author_id = isset( $post->post_author ) ? (int) $post->post_author : 0;
		$verified_doctor = $author_id > 0 && CanonicalIdentityAdapter::is_verified_doctor( $author_id );
		$verified_author = $verified_doctor || ( $author_id > 0 && ( CanonicalIdentityAdapter::is_founder( $author_id ) || CanonicalIdentityAdapter::is_administrator( $author_id ) ) );
		$published = ! empty( $post->post_date_gmt ) && '0000-00-00 00:00:00' !== $post->post_date_gmt ? $post->post_date_gmt : gmdate( 'Y-m-d H:i:s' );

		return array(
			'connector_slug' => self::FILE26_CONNECTOR_SLUG,
			'domain' => 'publication',
			'object_id' => (string) $post_id,
			'object_version' => self::file26_object_version( $post ),
			'entity_type' => $entity_type,
			'locale' => $locale,
			'state' => $state,
			'visibility' => 'public',
			'title' => $title,
			'excerpt' => $excerpt,
			'search_text' => trim( $title . ' ' . $excerpt ),
			'canonical_url' => $url,
			'author_key' => $author_id > 0 ? 'user:' . $author_id : '',
			'topic_ids' => $topics,
			'country' => $region,
			'quality_score' => 0.5,
			'authority_score' => 0.0,
			'popularity_score' => 0.0,
			'freshness_at' => $published,
			'safety_class' => 'general',
			'payload' => array(
				'verified_author' => $verified_author,
				'verified_doctor' => $verified_doctor,
				'content_type_label' => $content_type,
			),
		);
	}

	/** Whether a WordPress object belongs to File 21 searchable source domains. */
	private static function is_owned_search_post( $post ) {
		return is_object( $post ) && isset( $post->ID, $post->post_type )
			&& in_array( (string) $post->post_type, array( 'post', Phase4Contracts::POST_TYPE ), true );
	}

	/** Stable monotonic-enough source version based on WordPress modification time. */
	private static function file26_object_version( $post ) {
		$modified = is_object( $post ) && ! empty( $post->post_modified_gmt ) ? strtotime( $post->post_modified_gmt . ' UTC' ) : 0;
		if ( ! $modified && is_object( $post ) && ! empty( $post->post_date_gmt ) ) {
			$modified = strtotime( $post->post_date_gmt . ' UTC' );
		}
		return max( 1, (int) $modified );
	}

	/** Public taxonomy slugs only; term descriptions/private metadata never leave the owner. */
	private static function term_slugs( $post_id, $taxonomy ) {
		if ( ! function_exists( 'get_the_terms' ) ) {
			return array();
		}
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) || ( function_exists( 'is_wp_error' ) && is_wp_error( $terms ) ) ) {
			return array();
		}
		$out = array();
		foreach ( array_slice( $terms, 0, 25 ) as $term ) {
			if ( isset( $term->slug ) ) {
				$slug = sanitize_key( $term->slug );
				if ( '' !== $slug ) {
					$out[] = $slug;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** Search authorized core posts. */
	public static function search_posts( $query, $limit = 10 ) {
		$query = self::normalize_query( $query );
		$limit = max( 1, min( self::MAX_RESULTS_PER_PROVIDER, (int) $limit ) );
		if ( '' === $query || ! class_exists( 'WP_Query' ) ) {
			return array();
		}
		$viewer = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$wp_query = new \WP_Query(
			array(
				'post_type' => 'post',
				'post_status' => 'publish',
				's' => $query,
				'posts_per_page' => min( 100, $limit * 5 ),
				'orderby' => 'relevance',
				'order' => 'DESC',
				'no_found_rows' => true,
				'ignore_sticky_posts' => true,
				'meta_query' => array(
					'relation' => 'AND',
					PostMetadata::visibility_meta_clause(),
					PostMetadata::review_state_meta_clause(),
				),
			)
		);
		$items = array();
		foreach ( (array) $wp_query->posts as $post ) {
			$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : 0;
			if ( $post_id <= 0 || ! PostMetadata::user_can_view( $post_id, $viewer ) ) {
				continue;
			}
			$items[] = self::post_projection( $post_id );
			if ( count( $items ) >= $limit ) {
				break;
			}
		}
		return array_values( array_filter( $items ) );
	}

	/** Search public Editorial News through its approved query service. */
	public static function search_news( $query, $limit = 10 ) {
		$query = self::normalize_query( $query );
		$limit = max( 1, min( self::MAX_RESULTS_PER_PROVIDER, (int) $limit ) );
		if ( '' === $query || ! class_exists( __NAMESPACE__ . '\\NewsQueryService' ) || ! class_exists( __NAMESPACE__ . '\\NewsPolicy' ) || ! NewsPolicy::public_reads_allowed() ) {
			return array();
		}
		$result = NewsQueryService::query( array( 'q' => $query, 'per_page' => $limit ) );
		$items = array();
		foreach ( ! empty( $result['data']['items'] ) && is_array( $result['data']['items'] ) ? $result['data']['items'] : array() as $article ) {
			$headline = isset( $article['headline'] ) ? (string) $article['headline'] : '';
			$url = isset( $article['canonical_url'] ) ? (string) $article['canonical_url'] : '';
			if ( '' === $headline || '' === $url ) {
				continue;
			}
			$items[] = array(
				'id' => isset( $article['interaction_id'] ) ? absint( $article['interaction_id'] ) : 0,
				'type' => 'news',
				'title' => sanitize_text_field( $headline ),
				'url' => esc_url_raw( $url ),
				'excerpt' => isset( $article['summary'] ) ? sanitize_text_field( $article['summary'] ) : '',
				'provider' => 'file21-news',
			);
		}
		return $items;
	}

	/** Public-safe core-post projection. */
	private static function post_projection( $post_id ) {
		$title = function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : '';
		$url = function_exists( 'get_permalink' ) ? (string) get_permalink( $post_id ) : '';
		if ( '' === $title || '' === $url ) {
			return array();
		}
		$excerpt = function_exists( 'get_the_excerpt' ) ? (string) get_the_excerpt( $post_id ) : '';
		return array(
			'id' => (int) $post_id,
			'type' => 'post',
			'title' => sanitize_text_field( $title ),
			'url' => esc_url_raw( $url ),
			'excerpt' => sanitize_text_field( $excerpt ),
			'provider' => 'file21-posts',
		);
	}

	/** Normalize a bounded plain-text search term. */
	private static function normalize_query( $query ) {
		$query = is_scalar( $query ) ? trim( (string) $query ) : '';
		$query = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $query ) : strip_tags( $query );
		return '' !== $query ? substr( $query, 0, self::MAX_QUERY_LENGTH ) : '';
	}
}
