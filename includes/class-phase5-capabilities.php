<?php
/**
 * Phase 5 capability policy.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Applies only plugin-owned final-phase capabilities. */
final class Phase5Capabilities {
	const MUTATION_OPTION = 'sabri_feed_phase5_capability_mutations';

	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'user_has_cap', array( __CLASS__, 'respect_emergency_disable' ), 10, 4 );
		}
	}

	public static function default_role_map() {
		$all = Phase5Contracts::capabilities();
		return array(
			'administrator'   => $all,
			'founder'         => $all,
			'editor_in_chief' => $all,
			'editor'          => array_diff( $all, array( 'manage_news_release', 'manage_news_privacy' ) ),
			'managing_editor' => array_diff( $all, array( 'manage_news_release' ) ),
			'medical_reviewer'=> array( 'medical_review_editorial_news', 'view_news_audit' ),
			'translator'      => array( 'translate_editorial_news' ),
			'verified_doctor' => array( 'submit_editorial_news' ),
		);
	}

	public static function apply_default_policy() {
		$report = array( 'roles' => array(), 'managed_caps' => array(), 'created_at_utc' => gmdate( 'Y-m-d H:i:s' ) );
		if ( ! function_exists( 'get_role' ) ) {
			return $report;
		}
		foreach ( self::default_role_map() as $role_slug => $caps ) {
			$role = get_role( $role_slug );
			if ( ! $role ) {
				continue;
			}
			foreach ( array_unique( $caps ) as $capability ) {
				if ( ! in_array( $capability, Phase5Contracts::capabilities(), true ) ) {
					continue;
				}
				if ( empty( $role->capabilities[ $capability ] ) ) {
					$role->add_cap( $capability );
					$report['roles'][ $role_slug ][ $capability ] = 'added';
				}
				$report['managed_caps'][ $role_slug ][ $capability ] = true;
			}
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::MUTATION_OPTION, $report, false );
		}
		return $report;
	}

	public static function respect_emergency_disable( $allcaps, $caps, $args, $user ) {
		unset( $caps, $args, $user );
		if ( ! is_array( $allcaps ) || ! class_exists( __NAMESPACE__ . '\\SafeMode' ) || ! SafeMode::public_features_disabled() ) {
			return $allcaps;
		}
		foreach ( Phase5Contracts::capabilities() as $capability ) {
			$allcaps[ $capability ] = false;
		}
		return $allcaps;
	}
}
