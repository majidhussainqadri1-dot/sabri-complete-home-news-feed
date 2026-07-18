<?php
/**
 * Reversible capability policy.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Applies plugin-specific capabilities without inventing broad roles.
 */
final class Capabilities {
	const MUTATION_OPTION = 'sabri_feed_capability_mutations';

	/**
	 * Register capability helpers.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'user_has_cap', array( __CLASS__, 'respect_emergency_disable' ), 10, 4 );
		}
	}

	/**
	 * Plugin-specific capabilities.
	 *
	 * @return array<int,string>
	 */
	public static function capabilities() {
		return array(
			'sabri_feed_create_posts',
			'sabri_feed_publish_posts',
			'sabri_feed_submit_for_review',
			'sabri_feed_moderate_posts',
			'sabri_feed_manage_news',
			'sabri_feed_manage_breaking_news',
			'sabri_feed_manage_settings',
			'sabri_feed_view_analytics',
			'sabri_feed_manage_reports',
			'sabri_feed_run_repairs',
			'sabri_feed_run_migrations',
			'sabri_feed_run_rollbacks',
		);
	}

	/**
	 * Capability labels for documentation/admin.
	 *
	 * @return array<string,string>
	 */
	public static function labels() {
		return array(
			'sabri_feed_create_posts'        => __( 'Create feed posts', 'sabri-complete-home-news-feed' ),
			'sabri_feed_publish_posts'       => __( 'Publish feed posts', 'sabri-complete-home-news-feed' ),
			'sabri_feed_submit_for_review'   => __( 'Submit feed posts for review', 'sabri-complete-home-news-feed' ),
			'sabri_feed_moderate_posts'      => __( 'Moderate feed posts', 'sabri-complete-home-news-feed' ),
			'sabri_feed_manage_news'         => __( 'Manage news', 'sabri-complete-home-news-feed' ),
			'sabri_feed_manage_breaking_news' => __( 'Manage breaking news', 'sabri-complete-home-news-feed' ),
			'sabri_feed_manage_settings'     => __( 'Manage Home and News Feed settings', 'sabri-complete-home-news-feed' ),
			'sabri_feed_view_analytics'      => __( 'View feed analytics', 'sabri-complete-home-news-feed' ),
			'sabri_feed_manage_reports'      => __( 'Manage reports', 'sabri-complete-home-news-feed' ),
			'sabri_feed_run_repairs'         => __( 'Run repairs', 'sabri-complete-home-news-feed' ),
			'sabri_feed_run_migrations'      => __( 'Run migrations', 'sabri-complete-home-news-feed' ),
			'sabri_feed_run_rollbacks'       => __( 'Run rollbacks', 'sabri-complete-home-news-feed' ),
		);
	}

	/**
	 * Build the default role map from configured, existing role slugs.
	 *
	 * @param array<string,mixed>|null $settings Settings.
	 * @return array<string,array<int,string>>
	 */
	public static function default_role_map( $settings = null ) {
		if ( null === $settings ) {
			$settings = Settings::get();
		}

		$administrator_caps = self::capabilities();
		$editorial_caps     = array(
			'sabri_feed_create_posts',
			'sabri_feed_publish_posts',
			'sabri_feed_submit_for_review',
			'sabri_feed_moderate_posts',
			'sabri_feed_manage_news',
			'sabri_feed_manage_reports',
		);
		$founder_caps       = array(
			'sabri_feed_create_posts',
			'sabri_feed_publish_posts',
			'sabri_feed_submit_for_review',
			'sabri_feed_manage_news',
			'sabri_feed_manage_breaking_news',
		);
		$submit_caps        = array( 'sabri_feed_create_posts', 'sabri_feed_submit_for_review' );
		$doctor_caps        = $submit_caps;

		if ( isset( $settings['capabilities']['verified_doctor_policy'] ) && 'publish' === $settings['capabilities']['verified_doctor_policy'] ) {
			$doctor_caps = array( 'sabri_feed_create_posts', 'sabri_feed_publish_posts', 'sabri_feed_submit_for_review' );
		}

		$map = array(
			'administrator' => $administrator_caps,
			'editor'        => $editorial_caps,
		);

		foreach ( self::role_setting( $settings, 'editorial_roles' ) as $role ) {
			if ( 'editor' !== $role ) {
				$map[ $role ] = $editorial_caps;
			}
		}

		foreach ( self::role_setting( $settings, 'founder_roles' ) as $role ) {
			$map[ $role ] = $founder_caps;
		}

		foreach ( self::role_setting( $settings, 'verified_doctor_roles' ) as $role ) {
			$map[ $role ] = $doctor_caps;
		}

		foreach ( self::role_setting( $settings, 'unverified_doctor_roles' ) as $role ) {
			$map[ $role ] = $submit_caps;
		}

		foreach ( self::role_setting( $settings, 'student_roles' ) as $role ) {
			$map[ $role ] = array();
		}

		foreach ( self::role_setting( $settings, 'patient_roles' ) as $role ) {
			$map[ $role ] = array();
		}

		return $map;
	}

	/**
	 * Candidate roles that may be inspected or restored.
	 *
	 * @param array<string,mixed>|null $settings Settings.
	 * @return array<int,string>
	 */
	public static function candidate_role_slugs( $settings = null ) {
		$roles = array_keys( self::default_role_map( $settings ) );
		return array_values( array_unique( array_filter( $roles ) ) );
	}

	/**
	 * Apply the default reversible policy to existing roles only.
	 *
	 * @return array<string,mixed>
	 */
	public static function apply_default_policy() {
		$mutations = array(
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
			'roles'      => array(),
		);

		if ( ! function_exists( 'get_role' ) ) {
			return $mutations;
		}

		$map = self::default_role_map();
		foreach ( $map as $role_slug => $caps ) {
			$role = get_role( $role_slug );
			if ( ! $role ) {
				continue;
			}

			$mutations['roles'][ $role_slug ] = array();

			foreach ( self::capabilities() as $capability ) {
				if ( in_array( $capability, $caps, true ) ) {
					if ( empty( $role->capabilities[ $capability ] ) ) {
						$role->add_cap( $capability );
						$mutations['roles'][ $role_slug ][ $capability ] = 'added';
					}
				}
			}
		}

		if ( function_exists( 'update_option' ) ) {
			update_option( self::MUTATION_OPTION, $mutations, false );
		}

		return $mutations;
	}

	/**
	 * Restore plugin capabilities from an activation snapshot.
	 *
	 * @param array<string,mixed> $snapshot Snapshot.
	 * @return array<string,mixed>
	 */
	public static function restore_from_snapshot( array $snapshot ) {
		$report = array(
			'roles' => array(),
		);

		if ( empty( $snapshot['capability_roles'] ) || ! is_array( $snapshot['capability_roles'] ) || ! function_exists( 'get_role' ) ) {
			return $report;
		}

		foreach ( $snapshot['capability_roles'] as $role_slug => $caps ) {
			$role = get_role( $role_slug );
			if ( ! $role ) {
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

	/**
	 * Emergency disable gate for future public write features.
	 *
	 * @param array<string,bool> $allcaps All capabilities.
	 * @param array<int,string>  $caps Required caps.
	 * @param array<int,mixed>   $args Capability args.
	 * @param mixed              $user User object.
	 * @return array<string,bool>
	 */
	public static function respect_emergency_disable( $allcaps, $caps, $args, $user ) {
		unset( $caps, $args, $user );

		if ( ! is_array( $allcaps ) || ! SafeMode::emergency_disabled() ) {
			return $allcaps;
		}

		foreach ( array( 'sabri_feed_create_posts', 'sabri_feed_publish_posts', 'sabri_feed_submit_for_review' ) as $capability ) {
			$allcaps[ $capability ] = false;
		}

		return $allcaps;
	}

	/**
	 * Whether a role is allowed immediate plugin publishing under the default map.
	 *
	 * @param string $role_slug Role slug.
	 * @param array<string,mixed>|null $settings Settings.
	 * @return bool
	 */
	public static function role_can_publish( $role_slug, $settings = null ) {
		$map = self::default_role_map( $settings );
		return ! empty( $map[ $role_slug ] ) && in_array( 'sabri_feed_publish_posts', $map[ $role_slug ], true );
	}

	/**
	 * Get configured role list.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @param string              $key Setting key.
	 * @return array<int,string>
	 */
	private static function role_setting( array $settings, $key ) {
		if ( empty( $settings['capabilities'][ $key ] ) || ! is_array( $settings['capabilities'][ $key ] ) ) {
			return array();
		}

		$clean = array();
		foreach ( $settings['capabilities'][ $key ] as $role ) {
			$clean[] = function_exists( 'sanitize_key' ) ? sanitize_key( $role ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $role ) );
		}

		return array_values( array_filter( $clean ) );
	}
}
