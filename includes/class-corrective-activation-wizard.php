<?php
/**
 * File 21 corrective activation wizard service.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Builds previews and applies only explicit, non-destructive activation choices. */
final class CorrectiveActivationWizard {
	/** Wizard steps. */
	public static function steps() {
		return array(
			'environment'          => __( 'Environment and Dependencies', 'sabri-complete-home-news-feed' ),
			'existing-content'     => __( 'Existing Content', 'sabri-complete-home-news-feed' ),
			'public-components'    => __( 'Public Components', 'sabri-complete-home-news-feed' ),
			'duplicate-protection' => __( 'Duplicate Protection', 'sabri-complete-home-news-feed' ),
			'news-gates'           => __( 'News Gates', 'sabri-complete-home-news-feed' ),
			'preview-activate'     => __( 'Preview and Activate', 'sabri-complete-home-news-feed' ),
		);
	}

	/** Public component labels. */
	public static function component_definitions() {
		return array(
			'home_surface_enabled'       => __( 'Mount the identifiable File 21 Home surface', 'sabri-complete-home-news-feed' ),
			'profile_timeline_enabled'   => __( 'Enable Profile Timeline data and public rendering', 'sabri-complete-home-news-feed' ),
			'distinct_surface_marker'    => __( 'Show the File 21 surface marker', 'sabri-complete-home-news-feed' ),
			'duplicate_feed_guard'       => __( 'Block auto-mount when an old/new Feed shortcode already exists', 'sabri-complete-home-news-feed' ),
			'duplicate_navigation_guard' => __( 'Report duplicate Unified Shell navigation destinations', 'sabri-complete-home-news-feed' ),
		);
	}

	/** Gate labels and public evidence URLs. */
	public static function gate_definitions() {
		return array(
			'phase4' => array(
				'editorial_news_enabled'     => array( 'label' => __( 'Editorial News', 'sabri-complete-home-news-feed' ), 'url' => '/news/' ),
				'news_submissions_enabled'   => array( 'label' => __( 'News Submissions', 'sabri-complete-home-news-feed' ), 'url' => '/wp-admin/admin.php?page=sabri-newsroom' ),
				'breaking_news_enabled'      => array( 'label' => __( 'Breaking News', 'sabri-complete-home-news-feed' ), 'url' => '/' ),
				'scheduled_news_enabled'     => array( 'label' => __( 'Scheduled News', 'sabri-complete-home-news-feed' ), 'url' => '/news/' ),
				'news_corrections_enabled'   => array( 'label' => __( 'News Corrections', 'sabri-complete-home-news-feed' ), 'url' => '/news/' ),
				'news_rss_enabled'           => array( 'label' => __( 'News RSS', 'sabri-complete-home-news-feed' ), 'url' => '/news/feed/' ),
				'news_schema_enabled'        => array( 'label' => __( 'News Schema', 'sabri-complete-home-news-feed' ), 'url' => '/news/' ),
				'news_notifications_enabled' => array( 'label' => __( 'News Notifications', 'sabri-complete-home-news-feed' ), 'url' => '/news/' ),
			),
			'phase5' => array(
				'sources_enabled'            => array( 'label' => __( 'Sources Registry', 'sabri-complete-home-news-feed' ), 'url' => '/wp-admin/admin.php?page=sabri-phase5-newsroom' ),
				'reviews_enabled'            => array( 'label' => __( 'Editorial and Medical Reviews', 'sabri-complete-home-news-feed' ), 'url' => '/wp-admin/admin.php?page=sabri-phase5-newsroom' ),
				'submissions_enabled'        => array( 'label' => __( 'Contributor Submissions', 'sabri-complete-home-news-feed' ), 'url' => '/wp-admin/admin.php?page=sabri-phase5-newsroom' ),
				'breaking_news_enabled'      => array( 'label' => __( 'Breaking News Services', 'sabri-complete-home-news-feed' ), 'url' => '/' ),
				'corrections_enabled'        => array( 'label' => __( 'Correction and Retraction Ledger', 'sabri-complete-home-news-feed' ), 'url' => '/news/' ),
				'translations_enabled'       => array( 'label' => __( 'Translations', 'sabri-complete-home-news-feed' ), 'url' => '/news/' ),
				'news_seo_enabled'           => array( 'label' => __( 'News SEO', 'sabri-complete-home-news-feed' ), 'url' => '/news/' ),
				'news_rss_enabled'           => array( 'label' => __( 'Phase 5 RSS Projection', 'sabri-complete-home-news-feed' ), 'url' => '/news/feed/' ),
				'news_sitemap_enabled'       => array( 'label' => __( 'News Sitemap', 'sabri-complete-home-news-feed' ), 'url' => '/news-sitemap.xml' ),
				'private_previews_enabled'   => array( 'label' => __( 'Private Previews', 'sabri-complete-home-news-feed' ), 'url' => '/wp-admin/admin.php?page=sabri-phase5-newsroom' ),
				'privacy_automation_enabled' => array( 'label' => __( 'Privacy Automation', 'sabri-complete-home-news-feed' ), 'url' => '/wp-admin/admin.php?page=sabri-phase5-newsroom' ),
				'operator_alerts_enabled'    => array( 'label' => __( 'Operator Alerts', 'sabri-complete-home-news-feed' ), 'url' => '/wp-admin/admin.php?page=sabri-phase5-newsroom' ),
			),
		);
	}

	/** Complete non-mutating preview. */
	public static function preview() {
		$components  = CorrectivePublicSettings::get();
		$duplicates  = CorrectivePublicMount::diagnostics();
		$legacy      = LegacyFounderPostMigration::preview();
		$phase4      = NewsFeatureSettings::get();
		$phase5      = Phase5FeatureSettings::get();
		$front_page  = isset( $duplicates['front_page_id'] ) ? (int) $duplicates['front_page_id'] : 0;
		$post_count  = function_exists( 'wp_count_posts' ) ? wp_count_posts( 'post' ) : null;

		return array(
			'environment' => array(
				'wordpress'         => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '',
				'php'               => PHP_VERSION,
				'membership_core'   => defined( 'SABRI_MEMBERSHIP_CORE_VERSION' ) || class_exists( 'Sabri_Membership_Core' ) || class_exists( 'Sabri\\Membership\\Plugin' ),
				'unified_shell'     => defined( 'SABRI_SHELL_VERSION' ) || class_exists( 'Sabri\\UnifiedShell\\Plugin' ),
				'notifications'     => shortcode_exists( 'sabri_notifications' ) || function_exists( 'sabri_notifications_render' ),
				'database'          => SystemCheck::database_available(),
				'schema_status'     => SystemCheck::migration_status(),
			),
			'existing_content' => array(
				'front_page_id'              => $front_page,
				'published_posts'            => is_object( $post_count ) && isset( $post_count->publish ) ? (int) $post_count->publish : 0,
				'pending_posts'              => is_object( $post_count ) && isset( $post_count->pending ) ? (int) $post_count->pending : 0,
				'legacy_restore_candidates'  => isset( $legacy['candidate_count'] ) ? (int) $legacy['candidate_count'] : 0,
			),
			'components'            => $components,
			'duplicate_protection'  => $duplicates,
			'phase4_gates'          => $phase4,
			'phase5_gates'          => $phase5,
			'public_urls'           => array(
				'home'    => function_exists( 'home_url' ) ? home_url( '/' ) : '/',
				'news'    => function_exists( 'home_url' ) ? home_url( '/news/' ) : '/news/',
				'rss'     => function_exists( 'home_url' ) ? home_url( '/news/feed/' ) : '/news/feed/',
				'sitemap' => function_exists( 'home_url' ) ? home_url( '/news-sitemap.xml' ) : '/news-sitemap.xml',
			),
			'can_activate_home_surface' => empty( $duplicates['feed_conflict'] ),
			'destructive'               => false,
			'automatic_bulk_publish'    => false,
		);
	}

	/** Save explicit public-component choices, enforcing duplicate protection. */
	public static function save_components( array $input ) {
		$patch = array();
		foreach ( self::component_definitions() as $key => $unused ) {
			unset( $unused );
			$patch[ $key ] = isset( $input[ $key ] ) && in_array( $input[ $key ], array( 1, '1', true ), true ) ? 1 : 0;
		}
		$diagnostics = CorrectivePublicMount::diagnostics();
		if ( ! empty( $patch['home_surface_enabled'] ) && ! empty( $patch['duplicate_feed_guard'] ) && ! empty( $diagnostics['feed_conflict'] ) ) {
			$patch['home_surface_enabled'] = 0;
			$patch['wizard_completed']     = 0;
			$patch['blocked_reason']       = 'existing_feed_shortcode';
		} else {
			$patch['wizard_completed'] = 1;
		}
		$updated = CorrectivePublicSettings::patch( $patch );
		AuditLog::record( 'corrective_public_components_updated', array( 'settings' => $updated, 'diagnostics' => $diagnostics ) );
		return array( 'settings' => $updated, 'diagnostics' => $diagnostics );
	}

	/** Save gate-by-gate News choices while preserving dependency boundaries. */
	public static function save_news_gates( array $phase4_input, array $phase5_input ) {
		$phase4_patch = array();
		foreach ( Phase4Contracts::feature_flags() as $key => $unused ) {
			unset( $unused );
			$phase4_patch[ $key ] = isset( $phase4_input[ $key ] ) && in_array( $phase4_input[ $key ], array( 1, '1', true ), true ) ? 1 : 0;
		}
		if ( empty( $phase4_patch['editorial_news_enabled'] ) ) {
			foreach ( array( 'breaking_news_enabled', 'scheduled_news_enabled', 'news_corrections_enabled', 'news_rss_enabled', 'news_schema_enabled', 'news_notifications_enabled' ) as $dependent ) {
				$phase4_patch[ $dependent ] = 0;
			}
		}
		$phase4 = NewsFeatureSettings::update( $phase4_patch );

		$phase5_patch = array();
		foreach ( Phase5Contracts::feature_flags() as $key => $unused ) {
			unset( $unused );
			$phase5_patch[ $key ] = isset( $phase5_input[ $key ] ) && in_array( $phase5_input[ $key ], array( 1, '1', true ), true ) ? 1 : 0;
		}
		if ( empty( $phase4['editorial_news_enabled'] ) ) {
			foreach ( array( 'breaking_news_enabled', 'corrections_enabled', 'translations_enabled', 'news_seo_enabled', 'news_rss_enabled', 'news_sitemap_enabled' ) as $dependent ) {
				$phase5_patch[ $dependent ] = 0;
			}
		}
		$phase5 = Phase5FeatureSettings::patch( $phase5_patch );
		AuditLog::record( 'corrective_news_gates_updated', array( 'phase4' => $phase4, 'phase5' => $phase5 ) );
		return array( 'phase4' => $phase4, 'phase5' => $phase5 );
	}
}
