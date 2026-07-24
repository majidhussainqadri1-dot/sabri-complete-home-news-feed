<?php
/**
 * Phase 4B Editorial News composer validation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Provides bounded, fail-closed server-side composer validation. */
final class NewsComposerValidator {
	/** Register composer foundations without exposing public endpoints. */
	public static function register() {
		// Phase 4B admin and service layers call this validator explicitly.
	}

	/** Return the exact input fields accepted by the Phase 4B composer. */
	public static function fields() {
		return array(
			'title',
			'content',
			'subtitle',
			'summary',
			'language',
			'priority',
			'section',
			'article_type',
			'reviewing_editor_id',
			'medical_reviewer_id',
			'target_state',
			'schedule_at',
		);
	}

	/** Sanitize only known fields; unknown fields are never propagated. */
	public static function sanitize( array $input ) {
		$known = array_intersect_key( $input, array_fill_keys( self::fields(), true ) );
		$data  = array(
			'title'                 => self::sanitize_text( isset( $known['title'] ) ? $known['title'] : '' ),
			'content'               => self::sanitize_content( isset( $known['content'] ) ? $known['content'] : '' ),
			'subtitle'              => self::sanitize_text( isset( $known['subtitle'] ) ? $known['subtitle'] : '' ),
			'summary'               => self::sanitize_textarea( isset( $known['summary'] ) ? $known['summary'] : '' ),
			'language'              => self::sanitize_language( isset( $known['language'] ) ? $known['language'] : 'en-US' ),
			'priority'              => self::sanitize_priority( isset( $known['priority'] ) ? $known['priority'] : 0 ),
			'section'               => self::sanitize_section( isset( $known['section'] ) ? $known['section'] : '' ),
			'article_type'          => self::sanitize_article_type( isset( $known['article_type'] ) ? $known['article_type'] : '' ),
			'reviewing_editor_id'   => self::sanitize_id( isset( $known['reviewing_editor_id'] ) ? $known['reviewing_editor_id'] : 0 ),
			'medical_reviewer_id'   => self::sanitize_id( isset( $known['medical_reviewer_id'] ) ? $known['medical_reviewer_id'] : 0 ),
			'target_state'          => NewsStatuses::sanitize_state( isset( $known['target_state'] ) ? $known['target_state'] : 'draft' ),
			'schedule_at_utc'       => self::normalize_schedule_utc( isset( $known['schedule_at'] ) ? $known['schedule_at'] : '' ),
		);
		return $data;
	}

	/** Validate raw input before any fallback could conceal malformed values. */
	public static function validate( array $input ) {
		$errors = array();
		$data   = self::sanitize( $input );

		if ( '' === $data['title'] ) {
			$errors['title'] = 'required';
		} elseif ( strlen( $data['title'] ) > 300 ) {
			$errors['title'] = 'too_long';
		}

		if ( isset( $input['language'] ) && ! self::is_valid_language( $input['language'] ) ) {
			$errors['language'] = 'invalid';
		}
		if ( isset( $input['priority'] ) && ! self::is_valid_priority( $input['priority'] ) ) {
			$errors['priority'] = 'invalid';
		}
		if ( isset( $input['section'] ) && '' !== (string) $input['section'] && '' === $data['section'] ) {
			$errors['section'] = 'invalid';
		}
		if ( isset( $input['article_type'] ) && '' !== (string) $input['article_type'] && '' === $data['article_type'] ) {
			$errors['article_type'] = 'invalid';
		}
		if ( isset( $input['target_state'] ) && '' === $data['target_state'] ) {
			$errors['target_state'] = 'invalid';
		}
		if ( isset( $input['schedule_at'] ) && '' !== (string) $input['schedule_at'] && '' === $data['schedule_at_utc'] ) {
			$errors['schedule_at'] = 'invalid_or_ambiguous';
		}

		$requires_summary = in_array( $data['target_state'], array( 'ready-for-publication', 'scheduled', 'published' ), true );
		if ( $requires_summary && '' === $data['summary'] ) {
			$errors['summary'] = 'required_for_target_state';
		}
		if ( 'scheduled' === $data['target_state'] && '' === $data['schedule_at_utc'] ) {
			$errors['schedule_at'] = 'required_for_scheduled_state';
		}
		if ( 'published' === $data['target_state'] ) {
			$errors['target_state'] = 'phase4b_publication_closed';
		}

		return array(
			'success' => empty( $errors ),
			'code'    => empty( $errors ) ? 'composer_input_valid' : 'composer_input_invalid',
			'errors'  => $errors,
			'data'    => $data,
		);
	}

	/** Accept a bounded BCP-47-style tag exactly as submitted. */
	private static function is_valid_language( $value ) {
		return is_string( $value )
			&& '' !== $value
			&& strlen( $value ) <= 20
			&& 1 === preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $value );
	}

	/** Return a valid language or fail closed to the platform default. */
	private static function sanitize_language( $value ) {
		return self::is_valid_language( $value ) ? (string) $value : 'en-US';
	}

	/** Validate priority without coercing floats, arrays, or numeric prefixes. */
	private static function is_valid_priority( $value ) {
		if ( is_int( $value ) ) {
			$priority = $value;
		} elseif ( is_string( $value ) && 1 === preg_match( '/^(?:0|[1-9][0-9]{0,2})$/D', $value ) ) {
			$priority = (int) $value;
		} else {
			return false;
		}
		return $priority >= 0 && $priority <= 100;
	}

	/** Return a bounded priority or zero for invalid input. */
	private static function sanitize_priority( $value ) {
		return self::is_valid_priority( $value ) ? (int) $value : 0;
	}

	/** Retain only a frozen section slug. */
	private static function sanitize_section( $value ) {
		$value = self::strict_slug( $value );
		return $value && array_key_exists( $value, Phase4Contracts::sections() ) ? $value : '';
	}

	/** Retain only a frozen article-type slug. */
	private static function sanitize_article_type( $value ) {
		$value = self::strict_slug( $value );
		return $value && array_key_exists( $value, Phase4Contracts::article_types() ) ? $value : '';
	}

	/** Normalize an explicitly zoned datetime to UTC storage format. */
	private static function normalize_schedule_utc( $value ) {
		if ( ! is_string( $value ) || '' === $value || strlen( $value ) > 35 ) {
			return '';
		}
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value ) ) {
			return '';
		}
		try {
			$date = new \DateTimeImmutable( $value );
			return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $error ) {
			unset( $error );
			return '';
		}
	}

	/** Sanitize an integer identifier without accepting composite input. */
	private static function sanitize_id( $value ) {
		if ( function_exists( 'absint' ) ) {
			return absint( $value );
		}
		return is_scalar( $value ) ? max( 0, (int) $value ) : 0;
	}

	/** Strict lowercase slug validation without repairing aliases. */
	private static function strict_slug( $value ) {
		if ( ! is_string( $value ) || '' === $value || strlen( $value ) > 80 ) {
			return '';
		}
		return 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) ? $value : '';
	}

	/** Bounded single-line text sanitization. */
	private static function sanitize_text( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}
		return trim( strip_tags( $value ) );
	}

	/** Bounded multiline text sanitization. */
	private static function sanitize_textarea( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		if ( function_exists( 'sanitize_textarea_field' ) ) {
			return sanitize_textarea_field( $value );
		}
		return trim( strip_tags( $value ) );
	}

	/** Preserve only content accepted by WordPress post-content policy. */
	private static function sanitize_content( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $value ) : trim( $value );
	}
}
