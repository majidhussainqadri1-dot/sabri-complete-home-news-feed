<?php
/**
 * Phase 5 final completion contracts.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Immutable Phase 5 contracts and allow-lists. */
final class Phase5Contracts {
	const INTERNAL_SCHEMA_TARGET = 'phase5-final-1';
	const OPTION_PREFIX = 'sabri_hnf_phase5_';

	/** Return disabled-by-default feature gates. */
	public static function feature_flags() {
		return array(
			'sources_enabled'              => 0,
			'reviews_enabled'              => 0,
			'submissions_enabled'          => 0,
			'breaking_news_enabled'        => 0,
			'corrections_enabled'          => 0,
			'translations_enabled'         => 0,
			'news_seo_enabled'             => 0,
			'news_rss_enabled'             => 0,
			'news_sitemap_enabled'         => 0,
			'private_previews_enabled'     => 0,
			'privacy_automation_enabled'   => 0,
			'operator_alerts_enabled'      => 0,
		);
	}

	/** Return final Phase 5 capabilities. */
	public static function capabilities() {
		return array(
			'manage_news_sources',
			'verify_news_sources',
			'review_editorial_news',
			'fact_check_editorial_news',
			'medical_review_editorial_news',
			'translate_editorial_news',
			'submit_editorial_news',
			'manage_news_submissions',
			'manage_breaking_news',
			'manage_news_corrections',
			'retract_editorial_news',
			'manage_news_privacy',
			'manage_news_release',
			'view_news_audit',
			'view_news_diagnostics',
		);
	}

	/** Source classes. */
	public static function source_types() {
		return array(
			'original-research', 'systematic-review', 'official-dataset', 'guideline',
			'regulation', 'judgment', 'institutional-record', 'verified-statement',
			'book', 'classical-homeopathy-text', 'interview', 'press-release',
			'established-secondary-reporting', 'contextual-source', 'unverified-claim',
		);
	}

	/** Evidence classifications. */
	public static function evidence_classes() {
		return array(
			'primary', 'authoritative-secondary', 'professional-secondary', 'contextual',
			'supplied-content', 'preliminary', 'conflicted', 'unverified',
		);
	}

	/** Review types. */
	public static function review_types() {
		return array( 'editorial', 'fact-check', 'medical', 'translation' );
	}

	/** Review decisions. */
	public static function review_decisions() {
		return array( 'pending', 'approved', 'changes-requested', 'rejected', 'withdrawn', 'superseded' );
	}

	/** Submission states. */
	public static function submission_states() {
		return array( 'draft', 'submitted', 'needs-information', 'under-assessment', 'accepted', 'converted', 'rejected', 'withdrawn', 'archived' );
	}

	/** Breaking states. */
	public static function breaking_states() {
		return array( 'scheduled', 'active', 'expired', 'cancelled', 'superseded' );
	}

	/** Correction and retraction classes. */
	public static function correction_classes() {
		return array( 'minor', 'clarification', 'material', 'medical-safety', 'evidence-update', 'retraction' );
	}

	/** Correction states. */
	public static function correction_states() {
		return array( 'requested', 'under-review', 'approved', 'rejected', 'published', 'withdrawn' );
	}

	/** Translation states. */
	public static function translation_states() {
		return array( 'draft', 'in-review', 'approved', 'published', 'needs-update', 'withdrawn' );
	}

	/** Stable error codes. */
	public static function error_codes() {
		return array(
			'phase5_disabled', 'phase5_permission_denied', 'phase5_nonce_invalid',
			'phase5_identifier_invalid', 'phase5_payload_invalid', 'phase5_state_invalid',
			'phase5_conflict', 'phase5_rate_limited', 'phase5_not_found',
			'phase5_privacy_blocked', 'phase5_upload_rejected', 'phase5_migration_failed',
			'phase5_release_blocked', 'phase5_query_failed',
		);
	}

	/** Public route names added by Phase 5. */
	public static function public_routes() {
		return array(
			'/news/feed/',
			'/news/section/{slug}/feed/',
			'/news-sitemap.xml',
		);
	}

	/** Strict scalar truth. */
	public static function scalar_enabled( $value ) {
		return in_array( $value, array( 1, '1', true ), true );
	}

	/** Strict positive integer. */
	public static function positive_int( $value ) {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : 0;
		}
		return is_string( $value ) && preg_match( '/^[1-9][0-9]*$/D', $value ) ? (int) $value : 0;
	}

	/** Strict lower-case slug. */
	public static function slug( $value, $max = 120 ) {
		return is_string( $value ) && strlen( $value ) <= $max && preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) ? $value : '';
	}

	/** Strict BCP 47 language tag subset. */
	public static function language_tag( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > 35 ) {
			return '';
		}
		return preg_match( '/^[a-z]{2,3}(?:-[A-Z][a-z]{3})?(?:-(?:[A-Z]{2}|[0-9]{3}))?(?:-[A-Za-z0-9]{5,8})*$/D', $value ) ? $value : '';
	}
}
