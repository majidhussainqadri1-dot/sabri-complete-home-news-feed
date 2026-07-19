<?php
/**
 * Phase 3E public report-control runtime.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders login-gated, progressively enhanced report forms.
 */
final class ReportRuntime {
	/**
	 * Render a report control for a post or comment.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id Object ID.
	 * @param int    $owner_user_id Optional content owner ID.
	 * @return string
	 */
	public static function render_control( $object_type, $object_id, $owner_user_id = 0 ) {
		$object_type  = function_exists( 'sanitize_key' ) ? sanitize_key( $object_type ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $object_type ) );
		$object_id    = self::positive_id( $object_id );
		$owner_user_id = self::positive_id( $owner_user_id );
		if ( ! Phase3FeatureSettings::enabled( 'reports_enabled' ) || ! ReportPolicy::object_type_allowed( $object_type ) || $object_id <= 0 ) {
			return '';
		}

		$current_user_id = function_exists( 'get_current_user_id' ) ? self::positive_id( get_current_user_id() ) : 0;
		if ( $current_user_id > 0 && $owner_user_id > 0 && $current_user_id === $owner_user_id ) {
			return '';
		}

		$redirect = self::object_url( $object_type, $object_id );
		return FeedRenderer::template(
			'report-control',
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'reasons'     => ReportPolicy::reason_labels(),
				'report_url'  => function_exists( 'rest_url' ) ? rest_url( RestFoundation::NAMESPACE . '/reports' ) : '',
				'logged_in'   => $current_user_id > 0,
				'login_url'   => function_exists( 'wp_login_url' ) ? wp_login_url( $redirect ) : '',
				'note_max'    => ReportPolicy::REPORTER_NOTE_MAX,
			)
		);
	}

	/**
	 * Resolve a safe redirect target.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id Object ID.
	 * @return string
	 */
	private static function object_url( $object_type, $object_id ) {
		if ( 'comment' === $object_type && function_exists( 'get_comment_link' ) ) {
			return (string) get_comment_link( $object_id );
		}
		return function_exists( 'get_permalink' ) ? (string) get_permalink( $object_id ) : '';
	}

	/**
	 * Strict positive ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private static function positive_id( $value ) {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/', $value ) ) ) {
			return 0;
		}
		$value = (int) $value;
		return $value > 0 ? $value : 0;
	}
}
