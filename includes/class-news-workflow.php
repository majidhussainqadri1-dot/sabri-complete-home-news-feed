<?php
/**
 * Phase 4B Editorial News workflow policy.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Defines fail-closed, capability-aware Editorial News transitions. */
final class NewsWorkflow {
	/** Register workflow foundations without exposing public routes. */
	public static function register() {
		// Phase 4B services call this policy explicitly. No public hook is opened.
	}

	/** Return the complete allow-listed transition graph. */
	public static function transitions() {
		return array(
			'draft'                 => array( 'needs-sources', 'editorial-review' ),
			'needs-sources'         => array( 'draft', 'editorial-review' ),
			'editorial-review'      => array( 'draft', 'needs-sources', 'fact-check', 'medical-review', 'ready-for-publication' ),
			'fact-check'            => array( 'editorial-review', 'medical-review', 'ready-for-publication' ),
			'medical-review'        => array( 'editorial-review', 'fact-check', 'ready-for-publication' ),
			'ready-for-publication' => array( 'editorial-review', 'scheduled', 'published' ),
			'scheduled'             => array( 'ready-for-publication', 'published' ),
			'published'             => array( 'updated', 'correction-pending', 'retracted', 'archived' ),
			'updated'               => array( 'correction-pending', 'corrected', 'retracted', 'archived' ),
			'correction-pending'    => array( 'corrected', 'retracted' ),
			'corrected'             => array( 'correction-pending', 'retracted', 'archived' ),
			'retracted'             => array( 'archived' ),
			'archived'              => array(),
		);
	}

	/** Return exact allowed targets for a valid state. */
	public static function allowed_targets( $state ) {
		$state = NewsStatuses::sanitize_state( $state );
		$map   = self::transitions();
		return $state && isset( $map[ $state ] ) ? $map[ $state ] : array();
	}

	/** Return the exact capability required for an allow-listed transition. */
	public static function required_capability( $from, $to ) {
		$from = NewsStatuses::sanitize_state( $from );
		$to   = NewsStatuses::sanitize_state( $to );
		if ( ! $from || ! $to || ! in_array( $to, self::allowed_targets( $from ), true ) ) {
			return 'do_not_allow';
		}

		$exact = array(
			'draft>needs-sources'                     => 'submit_editorial_news',
			'draft>editorial-review'                  => 'submit_editorial_news',
			'needs-sources>draft'                     => 'edit_own_editorial_news',
			'needs-sources>editorial-review'          => 'submit_editorial_news',
			'editorial-review>draft'                  => 'review_editorial_news',
			'editorial-review>needs-sources'          => 'review_editorial_news',
			'editorial-review>fact-check'             => 'review_editorial_news',
			'editorial-review>medical-review'         => 'review_editorial_news',
			'editorial-review>ready-for-publication'  => 'review_editorial_news',
			'fact-check>editorial-review'             => 'fact_check_editorial_news',
			'fact-check>medical-review'               => 'fact_check_editorial_news',
			'fact-check>ready-for-publication'        => 'fact_check_editorial_news',
			'medical-review>editorial-review'         => 'medical_review_editorial_news',
			'medical-review>fact-check'               => 'medical_review_editorial_news',
			'medical-review>ready-for-publication'    => 'medical_review_editorial_news',
			'ready-for-publication>editorial-review' => 'review_editorial_news',
			'ready-for-publication>scheduled'        => 'schedule_editorial_news',
			'ready-for-publication>published'        => 'publish_editorial_news',
			'scheduled>ready-for-publication'        => 'schedule_editorial_news',
			'scheduled>published'                    => 'publish_editorial_news',
			'published>updated'                      => 'manage_news_corrections',
			'published>correction-pending'           => 'manage_news_corrections',
			'published>retracted'                    => 'retract_editorial_news',
			'published>archived'                     => 'manage_news_corrections',
			'updated>correction-pending'             => 'manage_news_corrections',
			'updated>corrected'                      => 'manage_news_corrections',
			'updated>retracted'                      => 'retract_editorial_news',
			'updated>archived'                       => 'manage_news_corrections',
			'correction-pending>corrected'           => 'manage_news_corrections',
			'correction-pending>retracted'           => 'retract_editorial_news',
			'corrected>correction-pending'           => 'manage_news_corrections',
			'corrected>retracted'                    => 'retract_editorial_news',
			'corrected>archived'                     => 'manage_news_corrections',
			'retracted>archived'                     => 'manage_news_corrections',
		);
		$key = $from . '>' . $to;
		return isset( $exact[ $key ] ) ? $exact[ $key ] : 'do_not_allow';
	}

	/** Validate identifiers and transition membership without authorizing a user. */
	public static function validate_transition( $from, $to ) {
		$clean_from = NewsStatuses::sanitize_state( $from );
		$clean_to   = NewsStatuses::sanitize_state( $to );
		if ( ! $clean_from || ! $clean_to ) {
			return self::result( false, 'invalid_workflow_state' );
		}
		if ( $clean_from === $clean_to ) {
			return self::result( true, 'workflow_unchanged', array( 'from' => $clean_from, 'to' => $clean_to ) );
		}
		if ( ! in_array( $clean_to, self::allowed_targets( $clean_from ), true ) ) {
			return self::result( false, 'workflow_transition_denied', array( 'from' => $clean_from, 'to' => $clean_to ) );
		}
		$capability = self::required_capability( $clean_from, $clean_to );
		if ( 'do_not_allow' === $capability ) {
			return self::result( false, 'workflow_capability_unresolved' );
		}
		return self::result( true, 'workflow_transition_valid', array( 'from' => $clean_from, 'to' => $clean_to, 'capability' => $capability ) );
	}

	/** Authorize a transition through Emergency Disable, object edit, and exact capability checks. */
	public static function can_transition( $from, $to, $post_id = 0 ) {
		$validation = self::validate_transition( $from, $to );
		if ( empty( $validation['success'] ) ) {
			return false;
		}
		if ( 'workflow_unchanged' === $validation['code'] ) {
			return true;
		}
		if ( class_exists( __NAMESPACE__ . '\\SafeMode' ) && SafeMode::public_features_disabled() ) {
			return false;
		}
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		$post_id = function_exists( 'absint' ) ? absint( $post_id ) : max( 0, (int) $post_id );
		if ( $post_id > 0 && ! current_user_can( 'edit_editorial_news', $post_id ) ) {
			return false;
		}
		if ( $post_id > 0 && in_array( $to, array( 'ready-for-publication', 'scheduled', 'published' ), true ) && class_exists( __NAMESPACE__ . '\\Phase5PublicationPolicy' ) ) {
			$eligibility = Phase5PublicationPolicy::eligible( $post_id, $to );
			if ( true !== $eligibility ) {
				return false;
			}
		}
		return current_user_can( $validation['data']['capability'] );
	}

	/** Stable result shape used by Phase 4B services and tests. */
	private static function result( $success, $code, array $data = array() ) {
		return array(
			'success' => (bool) $success,
			'code'    => (string) $code,
			'data'    => $data,
		);
	}
}
