<?php
/**
 * Taxonomy architecture.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers Feed taxonomies and canonical approved terms. */
final class Taxonomies {
	const TERM_VERSION_OPTION = 'sabri_hnf_feed_term_version';
	const TERM_VERSION = '1.0.1-harmonized';

	/** Register hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 9 );
			add_action( 'admin_init', array( __CLASS__, 'maybe_ensure_default_terms' ), 10 );
		}
	}

	/** Taxonomy definitions. */
	public static function taxonomies() {
		return array(
			'sabri_feed_topic' => array( 'singular' => __( 'Feed Topic', 'sabri-complete-home-news-feed' ), 'plural' => __( 'Feed Topics', 'sabri-complete-home-news-feed' ), 'hierarchical' => true ),
			'sabri_feed_type' => array( 'singular' => __( 'Feed Type', 'sabri-complete-home-news-feed' ), 'plural' => __( 'Feed Types', 'sabri-complete-home-news-feed' ), 'hierarchical' => true ),
			'sabri_evidence_level' => array( 'singular' => __( 'Evidence Level', 'sabri-complete-home-news-feed' ), 'plural' => __( 'Evidence Levels', 'sabri-complete-home-news-feed' ), 'hierarchical' => false ),
			'sabri_region' => array( 'singular' => __( 'Region', 'sabri-complete-home-news-feed' ), 'plural' => __( 'Regions', 'sabri-complete-home-news-feed' ), 'hierarchical' => true ),
			'sabri_visibility' => array( 'singular' => __( 'Visibility', 'sabri-complete-home-news-feed' ), 'plural' => __( 'Visibility', 'sabri-complete-home-news-feed' ), 'hierarchical' => false ),
		);
	}

	/**
	 * Exactly 22 canonical Feed types.
	 *
	 * Legacy aliases are mapped by PostMetadata and are never deleted from old
	 * posts or term storage. Institutional Breaking News remains a separate
	 * Editorial News projection rather than a social Feed author type.
	 */
	public static function feed_type_terms() {
		return array(
			'standard-post' => __( 'Standard Post', 'sabri-complete-home-news-feed' ),
			'founder-update' => __( 'Founder Update', 'sabri-complete-home-news-feed' ),
			'classical-homeopathy' => __( 'Classical Homeopathy', 'sabri-complete-home-news-feed' ),
			'homeopathy-education' => __( 'Homeopathy Education', 'sabri-complete-home-news-feed' ),
			'materia-medica' => __( 'Materia Medica', 'sabri-complete-home-news-feed' ),
			'repertory' => __( 'Repertory', 'sabri-complete-home-news-feed' ),
			'clinical-education' => __( 'Clinical Education', 'sabri-complete-home-news-feed' ),
			'clinical-case' => __( 'Clinical and Patient Case', 'sabri-complete-home-news-feed' ),
			'research' => __( 'Research', 'sabri-complete-home-news-feed' ),
			'nutrition' => __( 'Nutrition', 'sabri-complete-home-news-feed' ),
			'public-health-education' => __( 'Public Health Education', 'sabri-complete-home-news-feed' ),
			'platform-news' => __( 'Platform News and Announcements', 'sabri-complete-home-news-feed' ),
			'pathology' => __( 'Pathology', 'sabri-complete-home-news-feed' ),
			'anatomy' => __( 'Anatomy', 'sabri-complete-home-news-feed' ),
			'principles-of-hygiene' => __( 'Principles of Hygiene', 'sabri-complete-home-news-feed' ),
			'islamic-spiritual-healing' => __( 'Islamic Spiritual Healing', 'sabri-complete-home-news-feed' ),
			'homeopathy-philosophy' => __( 'Homeopathy Philosophy', 'sabri-complete-home-news-feed' ),
			'event' => __( 'Event', 'sabri-complete-home-news-feed' ),
			'video' => __( 'Video Reference', 'sabri-complete-home-news-feed' ),
			'document' => __( 'Document Reference', 'sabri-complete-home-news-feed' ),
			'poll' => __( 'Poll', 'sabri-complete-home-news-feed' ),
			'clinic-announcement' => __( 'Clinic Announcement', 'sabri-complete-home-news-feed' ),
		);
	}

	/** Non-destructive aliases from previous project generations. */
	public static function feed_type_aliases() {
		return array(
			'education' => 'homeopathy-education',
			'public-health' => 'public-health-education',
			'hygiene' => 'principles-of-hygiene',
			'patient-case' => 'clinical-case',
			'doctor-announcement' => 'platform-news',
			'breaking-news' => 'platform-news',
		);
	}

	/** Normalize a legacy or current Feed type. */
	public static function canonical_feed_type( $type ) {
		$type = function_exists( 'sanitize_key' ) ? sanitize_key( $type ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $type ) );
		$aliases = self::feed_type_aliases();
		return isset( $aliases[ $type ] ) ? $aliases[ $type ] : $type;
	}

	/** Canonical high-level discovery topics used by Home filters. */
	public static function feed_topic_terms() {
		return array(
			'remedies' => __( 'Remedies', 'sabri-complete-home-news-feed' ),
			'diseases' => __( 'Diseases', 'sabri-complete-home-news-feed' ),
			'clinical-cases' => __( 'Clinical Cases', 'sabri-complete-home-news-feed' ),
			'founder-posts' => __( 'Founder Posts', 'sabri-complete-home-news-feed' ),
			'doctors-posts' => __( 'Doctors Posts', 'sabri-complete-home-news-feed' ),
		);
	}

	/** Evidence-level terms. */
	public static function evidence_level_terms() {
		return array(
			'systematic-review' => __( 'Systematic Review', 'sabri-complete-home-news-feed' ),
			'randomized-trial' => __( 'Randomized Trial', 'sabri-complete-home-news-feed' ),
			'observational-study' => __( 'Observational Study', 'sabri-complete-home-news-feed' ),
			'case-series' => __( 'Case Series', 'sabri-complete-home-news-feed' ),
			'case-report' => __( 'Case Report', 'sabri-complete-home-news-feed' ),
			'expert-opinion' => __( 'Expert Opinion', 'sabri-complete-home-news-feed' ),
			'historical-source' => __( 'Historical Source', 'sabri-complete-home-news-feed' ),
			'unverified-claim' => __( 'Unverified Claim', 'sabri-complete-home-news-feed' ),
		);
	}

	/** Visibility terms. */
	public static function visibility_terms() {
		return array(
			'public' => __( 'Public', 'sabri-complete-home-news-feed' ),
			'members' => __( 'Registered Members', 'sabri-complete-home-news-feed' ),
			'doctors' => __( 'Doctors Only', 'sabri-complete-home-news-feed' ),
			'students' => __( 'Students Only', 'sabri-complete-home-news-feed' ),
			'patients' => __( 'Patients Only', 'sabri-complete-home-news-feed' ),
			'followers' => __( 'Followers', 'sabri-complete-home-news-feed' ),
			'private' => __( 'Private Draft', 'sabri-complete-home-news-feed' ),
		);
	}

	/** Register taxonomies against core posts. */
	public static function register_taxonomies() {
		if ( ! function_exists( 'register_taxonomy' ) ) {
			return;
		}
		foreach ( self::taxonomies() as $taxonomy => $definition ) {
			register_taxonomy(
				$taxonomy,
				array( 'post' ),
				array(
					'labels' => array(
						'name' => $definition['plural'],
						'singular_name' => $definition['singular'],
						'search_items' => sprintf( __( 'Search %s', 'sabri-complete-home-news-feed' ), $definition['plural'] ),
						'all_items' => sprintf( __( 'All %s', 'sabri-complete-home-news-feed' ), $definition['plural'] ),
						'edit_item' => sprintf( __( 'Edit %s', 'sabri-complete-home-news-feed' ), $definition['singular'] ),
					),
					'public' => false,
					'show_ui' => true,
					'show_admin_column' => true,
					'show_in_rest' => true,
					'hierarchical' => ! empty( $definition['hierarchical'] ),
					'rewrite' => false,
					'capabilities' => array(
						'manage_terms' => 'sabri_feed_manage_settings',
						'edit_terms' => 'sabri_feed_manage_settings',
						'delete_terms' => 'sabri_feed_manage_settings',
						'assign_terms' => 'sabri_feed_create_posts',
					),
				)
			);
		}
	}

	/** Run the bounded term installer only for an authorized version change. */
	public static function maybe_ensure_default_terms() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) || ! function_exists( 'get_option' ) ) {
			return array( 'success' => false, 'created' => array(), 'skipped' => array(), 'reason' => 'authorization' );
		}
		if ( self::TERM_VERSION === get_option( self::TERM_VERSION_OPTION, '' ) ) {
			return array( 'success' => true, 'created' => array(), 'skipped' => array(), 'reason' => 'current' );
		}
		$report = self::ensure_default_terms();
		if ( ! empty( $report['success'] ) && function_exists( 'update_option' ) ) {
			update_option( self::TERM_VERSION_OPTION, self::TERM_VERSION, false );
		}
		return $report;
	}

	/** Ensure accepted terms exist without deleting or renaming existing terms. */
	public static function ensure_default_terms() {
		$report = array( 'success' => true, 'created' => array(), 'skipped' => array(), 'failed' => array() );
		if ( ! function_exists( 'term_exists' ) || ! function_exists( 'wp_insert_term' ) ) {
			$report['success'] = false;
			$report['failed'][] = 'term_api_unavailable';
			return $report;
		}
		$sets = array(
			'sabri_feed_type' => self::feed_type_terms(),
			'sabri_feed_topic' => self::feed_topic_terms(),
			'sabri_visibility' => self::visibility_terms(),
			'sabri_evidence_level' => self::evidence_level_terms(),
		);
		foreach ( $sets as $taxonomy => $terms ) {
			foreach ( $terms as $slug => $label ) {
				$key = $taxonomy . ':' . $slug;
				if ( term_exists( $slug, $taxonomy ) ) {
					$report['skipped'][] = $key;
					continue;
				}
				$result = wp_insert_term( $label, $taxonomy, array( 'slug' => $slug ) );
				if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
					$report['success'] = false;
					$report['failed'][ $key ] = $result->get_error_code();
				} else {
					$report['created'][] = $key;
				}
			}
		}
		return $report;
	}
}