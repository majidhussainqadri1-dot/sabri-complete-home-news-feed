<?php
/**
 * Feed context and controlled option helpers.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes Phase 2 feed modes, visibility, and bounded request values.
 */
final class FeedContext {
	const DEFAULT_MODE = 'for-you';

	/** Phase 2 Home Feed modes. */
	public static function modes() {
		return array(
			'for-you'              => __( 'For You', 'sabri-complete-home-news-feed' ),
			'latest'               => __( 'Latest', 'sabri-complete-home-news-feed' ),
			'founder-updates'      => __( 'Founder Updates', 'sabri-complete-home-news-feed' ),
			'classical-homeopathy' => __( 'Classical Homeopathy', 'sabri-complete-home-news-feed' ),
			'clinical-cases'       => __( 'Clinical Cases', 'sabri-complete-home-news-feed' ),
			'materia-medica'       => __( 'Materia Medica', 'sabri-complete-home-news-feed' ),
			'repertory'            => __( 'Repertory', 'sabri-complete-home-news-feed' ),
			'research'             => __( 'Research', 'sabri-complete-home-news-feed' ),
			'education'            => __( 'Education', 'sabri-complete-home-news-feed' ),
			'public-health'        => __( 'Public Health', 'sabri-complete-home-news-feed' ),
			'platform-news'        => __( 'Platform News', 'sabri-complete-home-news-feed' ),
		);
	}

	/** Phase 2 feed types available from the public composer. */
	public static function phase2_feed_type_slugs() {
		return array(
			'standard-post',
			'founder-update',
			'platform-news',
			'classical-homeopathy',
			'clinical-case',
			'materia-medica',
			'repertory',
			'research',
			'education',
			'public-health',
		);
	}

	/** Map feed mode keys to feed type term slugs. */
	public static function mode_type_map() {
		return array(
			'founder-updates'      => 'founder-update',
			'classical-homeopathy' => 'classical-homeopathy',
			'clinical-cases'       => 'clinical-case',
			'materia-medica'       => 'materia-medica',
			'repertory'            => 'repertory',
			'research'             => 'research',
			'education'            => 'education',
			'public-health'        => 'public-health',
			'platform-news'        => 'platform-news',
		);
	}

	/** Visibility slugs allowed by the accepted Phase 2 controls. */
	public static function phase2_visibility_slugs( $include_private = false ) {
		$slugs = array( 'public', 'members', 'doctors', 'students', 'patients' );
		if ( $include_private ) {
			$slugs[] = 'private';
		}
		return $slugs;
	}

	/** Normalize a feed mode against enabled settings. */
	public static function normalize_mode( $mode, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$mode     = self::clean_key( $mode );
		$enabled  = self::enabled_modes( $settings );
		if ( in_array( $mode, $enabled, true ) ) {
			return $mode;
		}
		$default = ! empty( $settings['feed']['default_mode'] ) ? self::clean_key( $settings['feed']['default_mode'] ) : self::DEFAULT_MODE;
		if ( in_array( $default, $enabled, true ) ) {
			return $default;
		}
		return self::DEFAULT_MODE;
	}

	/** Enabled feed modes. */
	public static function enabled_modes( $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$modes    = array_keys( self::modes() );
		$config   = isset( $settings['feed']['enabled_filters'] ) && is_array( $settings['feed']['enabled_filters'] ) ? $settings['feed']['enabled_filters'] : $modes;
		$enabled  = array();
		foreach ( $config as $mode ) {
			$mode = self::clean_key( $mode );
			if ( in_array( $mode, $modes, true ) ) {
				$enabled[] = $mode;
			}
		}
		return $enabled ? array_values( array_unique( $enabled ) ) : array( self::DEFAULT_MODE, 'latest' );
	}

	/** Normalize a visibility mode for composer or query use. */
	public static function normalize_visibility( $visibility, $settings = null, $include_private = false ) {
		$settings   = null === $settings ? Settings::get() : $settings;
		$visibility = self::clean_key( $visibility );
		$allowed    = self::allowed_composer_visibility( $settings, $include_private );
		return in_array( $visibility, $allowed, true ) ? $visibility : 'public';
	}

	/** Visibility modes configured for composer use, including gated followers. */
	public static function allowed_composer_visibility( $settings = null, $include_private = false ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$allowed  = self::phase2_visibility_slugs( $include_private );
		$followers_enabled = Phase3FeatureSettings::enabled( 'followers_visibility_enabled' );
		if ( $followers_enabled ) {
			$allowed[] = FollowersVisibility::VISIBILITY;
		}
		$config = isset( $settings['composer']['allowed_visibility_modes'] ) && is_array( $settings['composer']['allowed_visibility_modes'] ) ? $settings['composer']['allowed_visibility_modes'] : $allowed;
		$out = array();
		foreach ( $config as $visibility ) {
			$visibility = self::clean_key( $visibility );
			if ( in_array( $visibility, $allowed, true ) ) {
				$out[] = $visibility;
			}
		}
		if ( $followers_enabled ) {
			$out[] = FollowersVisibility::VISIBILITY;
		}
		return $out ? array_values( array_unique( $out ) ) : array( 'public' );
	}

	/** Visibility values the current request may see in a feed. */
	public static function visible_feed_scopes_for_user( $user_id = 0, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$user_id  = $user_id ? (int) $user_id : self::current_user_id();
		$scopes   = array( 'public' );
		if ( $user_id > 0 ) {
			$scopes[] = 'members';
			if ( Phase3FeatureSettings::enabled( 'followers_visibility_enabled' ) ) {
				$scopes[] = FollowersVisibility::VISIBILITY;
			}
		}
		if ( ComposerPermissions::user_has_role_group( $user_id, 'verified_doctor_roles', $settings ) || ComposerPermissions::user_has_role_group( $user_id, 'unverified_doctor_roles', $settings ) ) {
			$scopes[] = 'doctors';
		}
		if ( ComposerPermissions::user_has_role_group( $user_id, 'student_roles', $settings ) ) {
			$scopes[] = 'students';
		}
		if ( ComposerPermissions::user_has_role_group( $user_id, 'patient_roles', $settings ) ) {
			$scopes[] = 'patients';
		}
		return array_values( array_unique( $scopes ) );
	}

	/** Get a bounded page number. */
	public static function page( $value ) {
		$value = function_exists( 'absint' ) ? absint( $value ) : abs( (int) $value );
		return max( 1, $value );
	}

	/** Get a bounded per-page value. */
	public static function per_page( $value, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$default  = ! empty( $settings['feed']['posts_per_page'] ) ? (int) $settings['feed']['posts_per_page'] : (int) $settings['feed']['default_count'];
		$value    = null === $value || '' === $value ? $default : $value;
		$value    = function_exists( 'absint' ) ? absint( $value ) : abs( (int) $value );
		return min( 50, max( 1, $value ) );
	}

	/** Return current user ID safely. */
	private static function current_user_id() {
		return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
	}

	/** Sanitize a key. */
	private static function clean_key( $value ) {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}
}
