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

/**
 * Registers feed taxonomies and default terms.
 */
final class Taxonomies {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 9 );
			add_action( 'init', array( __CLASS__, 'ensure_default_terms' ), 30 );
		}
	}

	/**
	 * Taxonomy definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function taxonomies() {
		return array(
			'sabri_feed_topic'    => array(
				'singular' => __( 'Feed Topic', 'sabri-complete-home-news-feed' ),
				'plural'   => __( 'Feed Topics', 'sabri-complete-home-news-feed' ),
				'hierarchical' => true,
			),
			'sabri_feed_type'     => array(
				'singular' => __( 'Feed Type', 'sabri-complete-home-news-feed' ),
				'plural'   => __( 'Feed Types', 'sabri-complete-home-news-feed' ),
				'hierarchical' => true,
			),
			'sabri_evidence_level' => array(
				'singular' => __( 'Evidence Level', 'sabri-complete-home-news-feed' ),
				'plural'   => __( 'Evidence Levels', 'sabri-complete-home-news-feed' ),
				'hierarchical' => false,
			),
			'sabri_region'        => array(
				'singular' => __( 'Region', 'sabri-complete-home-news-feed' ),
				'plural'   => __( 'Regions', 'sabri-complete-home-news-feed' ),
				'hierarchical' => true,
			),
			'sabri_visibility'    => array(
				'singular' => __( 'Visibility', 'sabri-complete-home-news-feed' ),
				'plural'   => __( 'Visibility', 'sabri-complete-home-news-feed' ),
				'hierarchical' => false,
			),
		);
	}

	/**
	 * Default feed type terms.
	 *
	 * @return array<string,string>
	 */
	public static function feed_type_terms() {
		return array(
			'standard-post'             => __( 'Standard Post', 'sabri-complete-home-news-feed' ),
			'founder-update'           => __( 'Founder Update', 'sabri-complete-home-news-feed' ),
			'platform-news'            => __( 'Platform News', 'sabri-complete-home-news-feed' ),
			'breaking-news'            => __( 'Breaking News', 'sabri-complete-home-news-feed' ),
			'classical-homeopathy'      => __( 'Classical Homeopathy', 'sabri-complete-home-news-feed' ),
			'materia-medica'           => __( 'Materia Medica', 'sabri-complete-home-news-feed' ),
			'repertory'                => __( 'Repertory', 'sabri-complete-home-news-feed' ),
			'clinical-case'            => __( 'Clinical Case', 'sabri-complete-home-news-feed' ),
			'research'                 => __( 'Research', 'sabri-complete-home-news-feed' ),
			'education'                => __( 'Education', 'sabri-complete-home-news-feed' ),
			'public-health'            => __( 'Public Health', 'sabri-complete-home-news-feed' ),
			'nutrition'                => __( 'Nutrition', 'sabri-complete-home-news-feed' ),
			'pathology'                => __( 'Pathology', 'sabri-complete-home-news-feed' ),
			'anatomy'                  => __( 'Anatomy', 'sabri-complete-home-news-feed' ),
			'hygiene'                  => __( 'Hygiene', 'sabri-complete-home-news-feed' ),
			'islamic-spiritual-healing' => __( 'Islamic Spiritual Healing', 'sabri-complete-home-news-feed' ),
			'event'                    => __( 'Event', 'sabri-complete-home-news-feed' ),
			'video'                    => __( 'Video', 'sabri-complete-home-news-feed' ),
			'document'                 => __( 'Document', 'sabri-complete-home-news-feed' ),
			'poll'                     => __( 'Poll', 'sabri-complete-home-news-feed' ),
			'doctor-announcement'      => __( 'Doctor Announcement', 'sabri-complete-home-news-feed' ),
			'clinic-announcement'      => __( 'Clinic Announcement', 'sabri-complete-home-news-feed' ),
		);
	}

	/**
	 * Default evidence-level terms.
	 *
	 * @return array<string,string>
	 */
	public static function evidence_level_terms() {
		return array(
			'systematic-review'  => __( 'Systematic Review', 'sabri-complete-home-news-feed' ),
			'randomized-trial'   => __( 'Randomized Trial', 'sabri-complete-home-news-feed' ),
			'observational-study' => __( 'Observational Study', 'sabri-complete-home-news-feed' ),
			'case-series'        => __( 'Case Series', 'sabri-complete-home-news-feed' ),
			'case-report'        => __( 'Case Report', 'sabri-complete-home-news-feed' ),
			'expert-opinion'     => __( 'Expert Opinion', 'sabri-complete-home-news-feed' ),
			'historical-source'  => __( 'Historical Source', 'sabri-complete-home-news-feed' ),
			'unverified-claim'   => __( 'Unverified Claim', 'sabri-complete-home-news-feed' ),
		);
	}

	/**
	 * Visibility terms.
	 *
	 * @return array<string,string>
	 */
	public static function visibility_terms() {
		return array(
			'public'    => __( 'Public', 'sabri-complete-home-news-feed' ),
			'members'   => __( 'Registered Members', 'sabri-complete-home-news-feed' ),
			'doctors'   => __( 'Doctors Only', 'sabri-complete-home-news-feed' ),
			'students'  => __( 'Students Only', 'sabri-complete-home-news-feed' ),
			'patients'  => __( 'Patients Only', 'sabri-complete-home-news-feed' ),
			'followers' => __( 'Followers', 'sabri-complete-home-news-feed' ),
			'private'   => __( 'Private Draft', 'sabri-complete-home-news-feed' ),
		);
	}

	/**
	 * Register taxonomies against core posts.
	 *
	 * @return void
	 */
	public static function register_taxonomies() {
		if ( ! function_exists( 'register_taxonomy' ) ) {
			return;
		}

		foreach ( self::taxonomies() as $taxonomy => $definition ) {
			register_taxonomy(
				$taxonomy,
				array( 'post' ),
				array(
					'labels'            => array(
						'name'          => $definition['plural'],
						'singular_name' => $definition['singular'],
						'search_items'  => sprintf(
							/* translators: %s: taxonomy plural label. */
							__( 'Search %s', 'sabri-complete-home-news-feed' ),
							$definition['plural']
						),
						'all_items'     => sprintf(
							/* translators: %s: taxonomy plural label. */
							__( 'All %s', 'sabri-complete-home-news-feed' ),
							$definition['plural']
						),
						'edit_item'     => sprintf(
							/* translators: %s: taxonomy singular label. */
							__( 'Edit %s', 'sabri-complete-home-news-feed' ),
							$definition['singular']
						),
					),
					'public'            => false,
					'show_ui'           => true,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'hierarchical'      => ! empty( $definition['hierarchical'] ),
					'rewrite'           => false,
					'capabilities'      => array(
						'manage_terms' => 'sabri_feed_manage_settings',
						'edit_terms'   => 'sabri_feed_manage_settings',
						'delete_terms' => 'sabri_feed_manage_settings',
						'assign_terms' => 'sabri_feed_create_posts',
					),
				)
			);
		}
	}

	/**
	 * Ensure default terms exist without deleting anything.
	 *
	 * @return array<string,mixed>
	 */
	public static function ensure_default_terms() {
		$report = array(
			'created' => array(),
			'skipped' => array(),
		);

		if ( ! function_exists( 'term_exists' ) || ! function_exists( 'wp_insert_term' ) ) {
			return $report;
		}

		foreach ( self::feed_type_terms() as $slug => $label ) {
			if ( term_exists( $slug, 'sabri_feed_type' ) ) {
				$report['skipped'][] = $slug;
				continue;
			}

			$result = wp_insert_term( $label, 'sabri_feed_type', array( 'slug' => $slug ) );
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
				$report['skipped'][] = $slug;
			} else {
				$report['created'][] = $slug;
			}
		}

		foreach ( self::visibility_terms() as $slug => $label ) {
			if ( term_exists( $slug, 'sabri_visibility' ) ) {
				$report['skipped'][] = 'visibility:' . $slug;
				continue;
			}
			$result = wp_insert_term( $label, 'sabri_visibility', array( 'slug' => $slug ) );
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
				$report['skipped'][] = 'visibility:' . $slug;
			} else {
				$report['created'][] = 'visibility:' . $slug;
			}
		}

		foreach ( self::evidence_level_terms() as $slug => $label ) {
			if ( term_exists( $slug, 'sabri_evidence_level' ) ) {
				$report['skipped'][] = 'evidence:' . $slug;
				continue;
			}
			$result = wp_insert_term( $label, 'sabri_evidence_level', array( 'slug' => $slug ) );
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
				$report['skipped'][] = 'evidence:' . $slug;
			} else {
				$report['created'][] = 'evidence:' . $slug;
			}
		}

		return $report;
	}
}
