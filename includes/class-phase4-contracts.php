<?php
/**
 * Phase 4 Editorial News executable contracts.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Stable identifiers shared by Phase 4 implementation checkpoints. */
final class Phase4Contracts {
	const TARGET_VERSION = '1.2.0';
	const CHECKPOINT = '4A';
	const POST_TYPE = 'sabri_news';
	const REST_NAMESPACE = 'sabri-home-news-feed/v1';
	const WORKFLOW_META_KEY = '_sabri_news_workflow_state';

	/** Phase 4 feature gates. */
	public static function feature_flags() {
		return array(
			'editorial_news_enabled'     => 0,
			'news_submissions_enabled'   => 0,
			'breaking_news_enabled'      => 0,
			'scheduled_news_enabled'     => 0,
			'news_corrections_enabled'   => 0,
			'news_rss_enabled'           => 0,
			'news_schema_enabled'        => 0,
			'news_notifications_enabled' => 0,
		);
	}

	/** Exact fail-closed feature lookup without repairing identifiers or values. */
	public static function feature_enabled( $feature, $settings = null ) {
		$flags = self::feature_flags();
		if ( ! is_string( $feature ) || ! array_key_exists( $feature, $flags ) ) {
			return false;
		}
		if ( null === $settings ) {
			$settings = $flags;
		}
		return is_array( $settings ) && array_key_exists( $feature, $settings ) && in_array( $settings[ $feature ], array( 1, '1', true ), true );
	}

	/** Frozen editorial taxonomies. */
	public static function taxonomies() {
		return array( 'sabri_news_section', 'sabri_news_topic', 'sabri_news_country', 'sabri_news_region', 'sabri_news_type' );
	}

	/** Frozen article-type slugs and labels. */
	public static function article_types() {
		return array(
			'breaking-news'         => 'Breaking News',
			'standard-news'         => 'Standard News',
			'research-news'         => 'Research News',
			'editorial'             => 'Editorial',
			'analysis'              => 'Analysis',
			'interview'             => 'Interview',
			'event-report'          => 'Event Report',
			'official-announcement' => 'Official Announcement',
			'correction-notice'     => 'Correction Notice',
			'retraction-notice'     => 'Retraction Notice',
		);
	}

	/** Frozen initial section slugs and labels. */
	public static function sections() {
		return array(
			'platform-news'                => 'Platform News',
			'classical-homeopathy'          => 'Classical Homeopathy',
			'homeopathy-research'           => 'Homeopathy Research',
			'clinical-education'            => 'Clinical Education',
			'materia-medica'                => 'Materia Medica',
			'repertory'                     => 'Repertory',
			'public-health'                 => 'Public Health',
			'medical-research'              => 'Medical Research',
			'pathology-anatomy'             => 'Pathology and Anatomy',
			'nutrition-hygiene'             => 'Nutrition and Hygiene',
			'homeopathy-education'          => 'Homeopathy Education',
			'universities-conferences'      => 'Universities and Conferences',
			'doctors-global-clinics'        => 'Doctors and Global Clinics',
			'professional-regulatory'       => 'Professional and Regulatory News',
			'islamic-spiritual-healing'     => 'Islamic Spiritual Healing',
			'founder-updates'               => 'Founder Updates',
			'research-center-news'          => 'Research Center News',
			'worldwide-health-developments' => 'Worldwide Health Developments',
		);
	}

	/** Frozen editorial workflow states. */
	public static function editorial_states() {
		return array(
			'draft', 'needs-sources', 'editorial-review', 'fact-check', 'medical-review',
			'ready-for-publication', 'scheduled', 'published', 'updated', 'correction-pending',
			'corrected', 'retracted', 'archived',
		);
	}

	/** WordPress storage status for each complete domain workflow state. */
	public static function wordpress_status_map() {
		return array(
			'draft'                 => 'draft',
			'needs-sources'         => 'draft',
			'editorial-review'      => 'pending',
			'fact-check'            => 'pending',
			'medical-review'        => 'pending',
			'ready-for-publication' => 'pending',
			'scheduled'             => 'future',
			'published'             => 'publish',
			'updated'               => 'publish',
			'correction-pending'    => 'publish',
			'corrected'             => 'publish',
			'retracted'             => 'private',
			'archived'              => 'private',
		);
	}

	/** Frozen submission workflow states. */
	public static function submission_states() {
		return array( 'submitted', 'initial-review', 'needs-more-information', 'accepted-for-editing', 'rejected', 'converted-to-news-draft', 'published' );
	}

	/** Phase 4 custom capabilities. */
	public static function capabilities() {
		return array(
			'read_editorial_news', 'create_editorial_news', 'edit_own_editorial_news',
			'edit_others_editorial_news', 'submit_editorial_news', 'review_editorial_news',
			'fact_check_editorial_news', 'medical_review_editorial_news', 'publish_editorial_news',
			'schedule_editorial_news', 'manage_breaking_news', 'manage_news_sources',
			'manage_news_corrections', 'retract_editorial_news', 'translate_editorial_news',
			'manage_news_taxonomies', 'manage_news_settings',
		);
	}

	/** Frozen public routes. */
	public static function public_routes() {
		return array(
			'/news/', '/news/{article-slug}/', '/news/section/{slug}/', '/news/topic/{slug}/',
			'/news/country/{slug}/', '/news/region/{slug}/', '/news/type/{slug}/', '/news/feed/',
			'/news/section/{slug}/feed/', '/news-sitemap.xml',
		);
	}

	/** Frozen disclosure labels. */
	public static function disclosure_labels() {
		return array(
			'News', 'Breaking News', 'Research News', 'Editorial', 'Opinion', 'Analysis',
			'Interview', 'Event Report', 'Official Announcement', 'Press Release', 'Sponsored',
			'Partner Content', 'Correction', 'Retraction', 'AI-generated illustration',
		);
	}

	/** Frozen Hostinger staging acceptance keys. */
	public static function acceptance_keys() {
		return array(
			'phase4_environment_backup', 'phase4_clean_install', 'phase4_upgrade_install',
			'phase4_phase2_regression', 'phase4_phase3_regression', 'phase4_roles_content_model',
			'phase4_workflow_composer', 'phase4_sources_factcheck', 'phase4_medical_privacy',
			'phase4_submissions', 'phase4_public_routes_feed', 'phase4_search_cache',
			'phase4_breaking_scheduling', 'phase4_corrections_retractions', 'phase4_seo_distribution',
			'phase4_translation', 'phase4_accessibility', 'phase4_security_performance',
			'phase4_privacy_emergency', 'phase4_rollback_acceptance',
		);
	}

	/** Frozen REST route intentions. */
	public static function rest_routes() {
		return array(
			'index'          => array( 'method' => 'GET', 'path' => '/news' ),
			'single'         => array( 'method' => 'GET', 'path' => '/news/{id}' ),
			'create'         => array( 'method' => 'POST', 'path' => '/news' ),
			'update'         => array( 'method' => 'PATCH', 'path' => '/news/{id}' ),
			'delete'         => array( 'method' => 'DELETE', 'path' => '/news/{id}' ),
			'submit'         => array( 'method' => 'POST', 'path' => '/news/{id}/submit' ),
			'review'         => array( 'method' => 'POST', 'path' => '/news/{id}/review' ),
			'publish'        => array( 'method' => 'POST', 'path' => '/news/{id}/publish' ),
			'schedule'       => array( 'method' => 'POST', 'path' => '/news/{id}/schedule' ),
			'correct'        => array( 'method' => 'POST', 'path' => '/news/{id}/correct' ),
			'retract'        => array( 'method' => 'POST', 'path' => '/news/{id}/retract' ),
			'sources'        => array( 'method' => 'GET', 'path' => '/news/{id}/sources' ),
			'source_create'  => array( 'method' => 'POST', 'path' => '/news/{id}/sources' ),
			'my_submissions' => array( 'method' => 'GET', 'path' => '/news/submissions/me' ),
		);
	}
}
