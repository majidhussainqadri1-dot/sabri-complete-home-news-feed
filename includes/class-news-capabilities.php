<?php
/**
 * Phase 4 Editorial News capability policy.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies narrow editorial capabilities to existing roles only.
 */
final class NewsCapabilities {
	const MUTATION_OPTION = 'sabri_feed_phase4_capability_mutations';

	/** Register emergency-disable enforcement. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'user_has_cap', array( __CLASS__, 'respect_emergency_disable' ), 10, 4 );
		}
	}

	/** Return all frozen Phase 4 capabilities. */
	public static function capabilities() {
		return Phase4Contracts::capabilities();
	}

	/** Human-readable labels. */
	public static function labels() {
		return array(
			'read_editorial_news'           => __( 'Read Editorial News', 'sabri-complete-home-news-feed' ),
			'create_editorial_news'         => __( 'Create Editorial News', 'sabri-complete-home-news-feed' ),
			'edit_own_editorial_news'       => __( 'Edit own Editorial News', 'sabri-complete-home-news-feed' ),
			'edit_others_editorial_news'    => __( 'Edit others Editorial News', 'sabri-complete-home-news-feed' ),
			'submit_editorial_news'         => __( 'Submit Editorial News', 'sabri-complete-home-news-feed' ),
			'review_editorial_news'         => __( 'Review Editorial News', 'sabri-complete-home-news-feed' ),
			'fact_check_editorial_news'     => __( 'Fact-check Editorial News', 'sabri-complete-home-news-feed' ),
			'medical_review_editorial_news' => __( 'Medically review Editorial News', 'sabri-complete-home-news-feed' ),
			'publish_editorial_news'        => __( 'Publish Editorial News', 'sabri-complete-home-news-feed' ),
			'schedule_editorial_news'       => __( 'Schedule Editorial News', 'sabri-complete-home-news-feed' ),
			'manage_breaking_news'          => __( 'Manage Breaking News', 'sabri-complete-home-news-feed' ),
			'manage_news_sources'           => __( 'Manage News sources', 'sabri-complete-home-news-feed' ),
			'manage_news_corrections'       => __( 'Manage News corrections', 'sabri-complete-home-news-feed' ),
			'retract_editorial_news'        => __( 'Retract Editorial News', 'sabri-complete-home-news-feed' ),
			'translate_editorial_news'      => __( 'Translate Editorial News', 'sabri-complete-home-news-feed' ),
			'manage_news_taxonomies'        => __( 'Manage News taxonomies', 'sabri-complete-home-news-feed' ),
			'manage_news_settings'          => __( 'Manage News settings', 'sabri-complete-home-news-feed' ),
		);
	}

	/**
	 * Default role map using existing role slugs only.
	 *
	 * Object- and section-scoped restrictions remain mandatory in later policy
	 * services; this map never grants broad source powers to submitters.
	 */
	public static function default_role_map() {
		$all = self::capabilities();
		$map = array(
			'administrator'       => $all,
			'founder'             => $all,
			'editor_in_chief'     => array(
				'read_editorial_news', 'create_editorial_news', 'edit_own_editorial_news', 'edit_others_editorial_news',
				'submit_editorial_news', 'review_editorial_news', 'fact_check_editorial_news',
				'medical_review_editorial_news', 'publish_editorial_news', 'schedule_editorial_news',
				'manage_breaking_news', 'manage_news_sources', 'manage_news_corrections',
				'retract_editorial_news', 'translate_editorial_news', 'manage_news_taxonomies',
			),
			'editor'              => array(
				'read_editorial_news', 'create_editorial_news', 'edit_own_editorial_news', 'edit_others_editorial_news',
				'submit_editorial_news', 'review_editorial_news', 'fact_check_editorial_news',
				'medical_review_editorial_news', 'schedule_editorial_news', 'manage_news_sources',
				'manage_news_corrections', 'translate_editorial_news', 'manage_news_taxonomies',
			),
			'managing_editor'     => array(
				'read_editorial_news', 'create_editorial_news', 'edit_own_editorial_news', 'edit_others_editorial_news',
				'submit_editorial_news', 'review_editorial_news', 'fact_check_editorial_news',
				'medical_review_editorial_news', 'schedule_editorial_news', 'manage_news_sources',
				'manage_news_corrections', 'translate_editorial_news', 'manage_news_taxonomies',
			),
			'section_editor'      => array(
				'read_editorial_news', 'create_editorial_news', 'edit_own_editorial_news', 'edit_others_editorial_news',
				'submit_editorial_news', 'review_editorial_news', 'fact_check_editorial_news',
				'manage_news_sources', 'manage_news_corrections', 'translate_editorial_news',
			),
			'medical_reviewer'    => array(
				'read_editorial_news', 'create_editorial_news', 'edit_own_editorial_news',
				'submit_editorial_news', 'medical_review_editorial_news', 'manage_news_sources',
			),
			'reporter'            => array(
				'read_editorial_news', 'create_editorial_news', 'edit_own_editorial_news',
				'submit_editorial_news', 'manage_news_sources',
			),
			'verified_doctor'     => array( 'read_editorial_news', 'submit_editorial_news' ),
			'translator'          => array(
				'read_editorial_news', 'create_editorial_news', 'edit_own_editorial_news',
				'submit_editorial_news', 'translate_editorial_news',
			),
			'subscriber'          => array( 'read_editorial_news' ),
			'student'             => array( 'read_editorial_news' ),
			'patient'             => array( 'read_editorial_news' ),
		);

		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'sabri_feed_phase4_role_map', $map );
			if ( is_array( $filtered ) ) {
				$map = $filtered;
			}
		}
		return self::sanitize_role_map( $map );
	}

	/** Candidate role slugs for snapshot and rollback. */
	public static function candidate_role_slugs() {
		return array_keys( self::default_role_map() );
	}

	/** Apply capabilities to existing roles without creating or deleting roles. */
	public static function apply_default_policy() {
		$mutations = array(
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
			'roles'      => array(),
		);
		if ( ! function_exists( 'get_role' ) ) {
			return $mutations;
		}

		foreach ( self::default_role_map() as $role_slug => $capabilities ) {
			$role = get_role( $role_slug );
			if ( ! $role ) {
				continue;
			}
			$mutations['roles'][ $role_slug ] = array();
			foreach ( $capabilities as $capability ) {
				if ( empty( $role->capabilities[ $capability ] ) ) {
					$role->add_cap( $capability );
					$mutations['roles'][ $role_slug ][ $capability ] = 'added';
				}
			}
		}

		if ( function_exists( 'update_option' ) ) {
			update_option( self::MUTATION_OPTION, $mutations, false );
		}
		return $mutations;
	}

	/** Restore only Phase 4 capabilities from the activation snapshot. */
	public static function restore_from_snapshot( array $snapshot ) {
		$report = array( 'roles' => array() );
		if ( empty( $snapshot['capability_roles'] ) || ! is_array( $snapshot['capability_roles'] ) || ! function_exists( 'get_role' ) ) {
			return $report;
		}

		foreach ( $snapshot['capability_roles'] as $role_slug => $caps ) {
			$role = get_role( $role_slug );
			if ( ! $role || ! is_array( $caps ) ) {
				continue;
			}
			$report['roles'][ $role_slug ] = array();
			foreach ( self::capabilities() as $capability ) {
				$had_cap = ! empty( $caps[ $capability ] );
				$has_cap = ! empty( $role->capabilities[ $capability ] );
				if ( $had_cap && ! $has_cap ) {
					$role->add_cap( $capability );
					$report['roles'][ $role_slug ][ $capability ] = 'restored';
				} elseif ( ! $had_cap && $has_cap ) {
					$role->remove_cap( $capability );
					$report['roles'][ $role_slug ][ $capability ] = 'removed';
				}
			}
		}
		return $report;
	}

	/** Whether a default role receives publishing authority. */
	public static function role_can_publish( $role_slug ) {
		$map = self::default_role_map();
		return isset( $map[ $role_slug ] ) && in_array( 'publish_editorial_news', $map[ $role_slug ], true );
	}

	/** Remove editorial write powers while Emergency Disable is active. */
	public static function respect_emergency_disable( $allcaps, $caps, $args, $user ) {
		unset( $caps, $args, $user );
		if ( ! is_array( $allcaps ) || ! class_exists( __NAMESPACE__ . '\\SafeMode' ) || ! SafeMode::emergency_disabled() ) {
			return $allcaps;
		}
		foreach ( self::capabilities() as $capability ) {
			if ( 'read_editorial_news' !== $capability ) {
				$allcaps[ $capability ] = false;
			}
		}
		return $allcaps;
	}

	/** Strictly retain known capabilities and safe role slugs. */
	private static function sanitize_role_map( array $map ) {
		$known = self::capabilities();
		$out   = array();
		foreach ( $map as $role_slug => $capabilities ) {
			$role_slug = function_exists( 'sanitize_key' ) ? sanitize_key( $role_slug ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $role_slug ) );
			if ( '' === $role_slug || ! is_array( $capabilities ) ) {
				continue;
			}
			$out[ $role_slug ] = array_values( array_unique( array_intersect( $known, $capabilities ) ) );
		}
		return $out;
	}
}
