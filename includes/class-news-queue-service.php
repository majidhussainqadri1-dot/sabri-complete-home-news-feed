<?php
/**
 * Phase 4B Editorial News queue service.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Produces bounded, capability-aware private newsroom queues. */
final class NewsQueueService {
	/** Register queue foundations without creating public endpoints. */
	public static function register() {
		// Administration screens call this service explicitly.
	}

	/** Frozen queue definitions. */
	public static function definitions() {
		return array(
			'own-drafts' => array(
				'label' => __( 'My Drafts', 'sabri-complete-home-news-feed' ),
				'states' => array( 'draft' ),
				'capability' => 'edit_own_editorial_news',
				'own_only' => true,
				'read_only' => false,
			),
			'submitted' => array(
				'label' => __( 'My Submitted Articles', 'sabri-complete-home-news-feed' ),
				'states' => array( 'editorial-review', 'fact-check', 'medical-review', 'ready-for-publication', 'scheduled' ),
				'capability' => 'submit_editorial_news',
				'own_only' => true,
				'read_only' => true,
			),
			'editorial-review' => array(
				'label' => __( 'Editorial Review', 'sabri-complete-home-news-feed' ),
				'states' => array( 'editorial-review' ),
				'capability' => 'review_editorial_news',
				'own_only' => false,
				'read_only' => false,
			),
			'fact-check' => array(
				'label' => __( 'My Fact Check Assignments', 'sabri-complete-home-news-feed' ),
				'states' => array( 'fact-check' ),
				'capability' => 'fact_check_editorial_news',
				'own_only' => false,
				'assignment_meta' => '_sabri_news_reviewing_editor_id',
				'read_only' => false,
			),
			'medical-review' => array(
				'label' => __( 'My Medical Review Assignments', 'sabri-complete-home-news-feed' ),
				'states' => array( 'medical-review' ),
				'capability' => 'medical_review_editorial_news',
				'own_only' => false,
				'assignment_meta' => '_sabri_news_medical_reviewer_id',
				'read_only' => false,
			),
			'changes-requested' => array(
				'label' => __( 'Changes Requested', 'sabri-complete-home-news-feed' ),
				'states' => array( 'needs-sources' ),
				'capability' => 'edit_own_editorial_news',
				'own_only' => true,
				'read_only' => false,
			),
			'approved' => array(
				'label' => __( 'Approved', 'sabri-complete-home-news-feed' ),
				'states' => array( 'ready-for-publication' ),
				'capability' => 'review_editorial_news',
				'own_only' => false,
				'read_only' => false,
			),
			'publication-ready' => array(
				'label' => __( 'Publication Ready', 'sabri-complete-home-news-feed' ),
				'states' => array( 'ready-for-publication' ),
				'capability' => 'publish_editorial_news',
				'own_only' => false,
				'read_only' => false,
			),
			'scheduled' => array(
				'label' => __( 'Scheduled', 'sabri-complete-home-news-feed' ),
				'states' => array( 'scheduled' ),
				'capability' => 'schedule_editorial_news',
				'own_only' => false,
				'read_only' => false,
			),
			'published' => array(
				'label' => __( 'Published Records', 'sabri-complete-home-news-feed' ),
				'states' => array( 'published', 'updated', 'corrected' ),
				'capability' => 'review_editorial_news',
				'own_only' => false,
				'read_only' => true,
			),
			'accountability' => array(
				'label' => __( 'Corrections and Retractions', 'sabri-complete-home-news-feed' ),
				'states' => array( 'correction-pending', 'retracted', 'archived' ),
				'capability' => 'manage_news_corrections',
				'own_only' => false,
				'read_only' => true,
			),
		);
	}

	/** Return one exact queue definition or an empty array. */
	public static function definition( $queue ) {
		$queue = self::strict_slug( $queue );
		$definitions = self::definitions();
		return $queue && isset( $definitions[ $queue ] ) ? $definitions[ $queue ] : array();
	}

	/** Whether the current user may access one queue. */
	public static function can_access( $queue ) {
		$definition = self::definition( $queue );
		return ! empty( $definition ) && NewsPolicy::can_access_queue( $definition['capability'], ! empty( $definition['own_only'] ) );
	}

	/** Return only queue definitions visible to the current user, without counts. */
	public static function visible_definitions() {
		$visible = array();
		foreach ( self::definitions() as $slug => $definition ) {
			if ( self::can_access( $slug ) ) {
				$visible[ $slug ] = $definition;
			}
		}
		return $visible;
	}

	/** Build bounded WordPress query arguments for one authorized queue. */
	public static function query_args( $queue, $page = 1, $per_page = 20 ) {
		$definition = self::definition( $queue );
		if ( empty( $definition ) || ! self::can_access( $queue ) ) {
			return array();
		}
		$page = max( 1, (int) $page );
		$per_page = max( 1, min( 50, (int) $per_page ) );
		$core_statuses = array();
		foreach ( $definition['states'] as $state ) {
			$status = NewsStatuses::wordpress_status( $state );
			if ( $status ) {
				$core_statuses[] = $status;
			}
		}
		$meta_query = array(
			array(
				'key' => Phase4Contracts::WORKFLOW_META_KEY,
				'value' => $definition['states'],
				'compare' => 'IN',
			),
		);
		if ( ! empty( $definition['assignment_meta'] ) ) {
			$meta_query['relation'] = 'AND';
			$meta_query[] = array(
				'key' => $definition['assignment_meta'],
				'value' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
				'compare' => '=',
				'type' => 'NUMERIC',
			);
		}
		$args = array(
			'post_type' => Phase4Contracts::POST_TYPE,
			'post_status' => array_values( array_unique( $core_statuses ) ),
			'posts_per_page' => $per_page,
			'paged' => $page,
			'orderby' => 'modified',
			'order' => 'DESC',
			'no_found_rows' => false,
			'meta_query' => $meta_query,
		);
		if ( ! empty( $definition['own_only'] ) ) {
			$args['author'] = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		}
		return $args;
	}

	/** Execute one authorized queue without disclosing unauthorized counts. */
	public static function query( $queue, $page = 1, $per_page = 20 ) {
		$args = self::query_args( $queue, $page, $per_page );
		if ( empty( $args ) || ! class_exists( 'WP_Query' ) ) {
			return self::result( false, 'queue_access_denied' );
		}
		$query = new \WP_Query( $args );
		$posts = is_array( $query->posts ) ? $query->posts : array();
		$post_ids = array_values( array_filter( array_map( static function ( $post ) { return isset( $post->ID ) ? (int) $post->ID : 0; }, $posts ) ) );
		if ( $post_ids && function_exists( 'update_meta_cache' ) ) {
			update_meta_cache( 'post', $post_ids );
		}
		if ( $post_ids && function_exists( 'update_object_term_cache' ) ) {
			update_object_term_cache( $post_ids, Phase4Contracts::POST_TYPE );
		}
		$definition = self::definition( $queue );
		return self::result(
			true,
			'queue_loaded',
			array(
				'queue' => (string) $queue,
				'label' => $definition['label'],
				'read_only' => ! empty( $definition['read_only'] ),
				'posts' => $posts,
				'total' => (int) $query->found_posts,
				'pages' => (int) $query->max_num_pages,
				'page' => max( 1, (int) $page ),
			)
		);
	}

	private static function strict_slug( $value ) {
		return is_string( $value ) && strlen( $value ) <= 80 && 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) ? $value : '';
	}

	private static function result( $success, $code, array $data = array() ) {
		return array( 'success' => (bool) $success, 'code' => (string) $code, 'data' => $data );
	}
}
