<?php
/**
 * Phase 3E report and moderation policy.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central allow-lists and bounded transitions for confidential reports.
 */
final class ReportPolicy {
	const REPORTER_NOTE_MAX = 1000;
	const MODERATOR_NOTE_MAX = 2000;

	/**
	 * Reportable object types.
	 *
	 * @return array<int,string>
	 */
	public static function object_types() {
		return array( 'post', 'comment' );
	}

	/**
	 * Allowed report reasons.
	 *
	 * @return array<int,string>
	 */
	public static function reasons() {
		return Phase3Contracts::report_reasons();
	}

	/**
	 * Allowed report states.
	 *
	 * @return array<int,string>
	 */
	public static function states() {
		return Phase3Contracts::report_states();
	}

	/**
	 * Human-readable reason labels.
	 *
	 * @return array<string,string>
	 */
	public static function reason_labels() {
		return array(
			'spam'                => __( 'Spam', 'sabri-complete-home-news-feed' ),
			'harassment'          => __( 'Harassment', 'sabri-complete-home-news-feed' ),
			'hate-abuse'          => __( 'Hate or abuse', 'sabri-complete-home-news-feed' ),
			'misinformation'      => __( 'Misinformation', 'sabri-complete-home-news-feed' ),
			'medical-safety-risk' => __( 'Medical safety risk', 'sabri-complete-home-news-feed' ),
			'patient-privacy'     => __( 'Patient privacy', 'sabri-complete-home-news-feed' ),
			'copyright-source'    => __( 'Copyright or missing source', 'sabri-complete-home-news-feed' ),
			'impersonation'       => __( 'Impersonation', 'sabri-complete-home-news-feed' ),
			'other'               => __( 'Other', 'sabri-complete-home-news-feed' ),
		);
	}

	/**
	 * Human-readable state labels.
	 *
	 * @return array<string,string>
	 */
	public static function state_labels() {
		return array(
			'open'      => __( 'Open', 'sabri-complete-home-news-feed' ),
			'triaged'   => __( 'Triaged', 'sabri-complete-home-news-feed' ),
			'resolved'  => __( 'Resolved', 'sabri-complete-home-news-feed' ),
			'dismissed' => __( 'Dismissed', 'sabri-complete-home-news-feed' ),
			'duplicate' => __( 'Duplicate', 'sabri-complete-home-news-feed' ),
		);
	}

	/**
	 * Whether an object type is allowed.
	 *
	 * @param mixed $type Object type.
	 * @return bool
	 */
	public static function object_type_allowed( $type ) {
		return in_array( self::clean_key( $type ), self::object_types(), true );
	}

	/**
	 * Whether a reason is allowed.
	 *
	 * @param mixed $reason Reason.
	 * @return bool
	 */
	public static function reason_allowed( $reason ) {
		return in_array( self::clean_key( $reason ), self::reasons(), true );
	}

	/**
	 * Whether a state is allowed.
	 *
	 * @param mixed $state State.
	 * @return bool
	 */
	public static function state_allowed( $state ) {
		return in_array( self::clean_key( $state ), self::states(), true );
	}

	/**
	 * Whether a moderation transition is allowed.
	 *
	 * @param string $from Current state.
	 * @param string $to Requested state.
	 * @return bool
	 */
	public static function transition_allowed( $from, $to ) {
		$from = self::clean_key( $from );
		$to   = self::clean_key( $to );
		if ( $from === $to && self::state_allowed( $from ) ) {
			return true;
		}

		$map = array(
			'open'      => array( 'triaged', 'resolved', 'dismissed', 'duplicate' ),
			'triaged'   => array( 'open', 'resolved', 'dismissed', 'duplicate' ),
			'resolved'  => array( 'triaged' ),
			'dismissed' => array( 'triaged' ),
			'duplicate' => array( 'triaged' ),
		);

		return isset( $map[ $from ] ) && in_array( $to, $map[ $from ], true );
	}

	/**
	 * Sanitize a bounded reporter note.
	 *
	 * @param mixed $note Note.
	 * @return string
	 */
	public static function reporter_note( $note ) {
		return self::bounded_textarea( $note, self::REPORTER_NOTE_MAX );
	}

	/**
	 * Sanitize a bounded moderator note.
	 *
	 * @param mixed $note Note.
	 * @return string
	 */
	public static function moderator_note( $note ) {
		return self::bounded_textarea( $note, self::MODERATOR_NOTE_MAX );
	}

	/**
	 * Encode confidential notes without exposing them through public responses.
	 *
	 * @param string $reporter_note Reporter note.
	 * @param string $moderator_note Moderator note.
	 * @param int    $moderator_id Last moderator user ID.
	 * @param string $moderated_at UTC timestamp.
	 * @return string
	 */
	public static function encode_notes( $reporter_note = '', $moderator_note = '', $moderator_id = 0, $moderated_at = '' ) {
		$payload = array(
			'reporter_note'     => self::reporter_note( $reporter_note ),
			'moderator_note'    => self::moderator_note( $moderator_note ),
			'last_moderator_id' => max( 0, (int) $moderator_id ),
			'last_moderated_at' => preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $moderated_at ) ? (string) $moderated_at : '',
		);
		return function_exists( 'wp_json_encode' ) ? (string) wp_json_encode( $payload ) : (string) json_encode( $payload );
	}

	/**
	 * Decode confidential notes with safe defaults.
	 *
	 * @param mixed $notes Stored notes.
	 * @return array<string,mixed>
	 */
	public static function decode_notes( $notes ) {
		$data = json_decode( (string) $notes, true );
		$data = is_array( $data ) ? $data : array();
		return array(
			'reporter_note'     => self::reporter_note( isset( $data['reporter_note'] ) ? $data['reporter_note'] : '' ),
			'moderator_note'    => self::moderator_note( isset( $data['moderator_note'] ) ? $data['moderator_note'] : '' ),
			'last_moderator_id' => isset( $data['last_moderator_id'] ) ? max( 0, (int) $data['last_moderator_id'] ) : 0,
			'last_moderated_at' => isset( $data['last_moderated_at'] ) && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $data['last_moderated_at'] ) ? (string) $data['last_moderated_at'] : '',
		);
	}

	/**
	 * Sanitize a key.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}

	/**
	 * Sanitize and truncate textarea text.
	 *
	 * @param mixed $value Value.
	 * @param int   $max Maximum characters.
	 * @return string
	 */
	private static function bounded_textarea( $value, $max ) {
		$value = function_exists( 'wp_unslash' ) ? wp_unslash( $value ) : $value;
		$value = function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $value ) : trim( strip_tags( (string) $value ) );
		$value = trim( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}
}
