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
		// Phase 4B administration and service layers call this validator explicitly.
	}

	/** Exact input fields accepted by the Phase 4B composer. */
	public static function fields() {
		return array(
			'title', 'content', 'subtitle', 'summary', 'language', 'priority',
			'section', 'article_type', 'topics', 'countries', 'regions',
			'featured_image_id', 'reviewing_editor_id', 'medical_reviewer_id',
			'fact_check_required', 'medical_review_required', 'target_state', 'schedule_at',
		);
	}

	/** Sanitize only known fields; unknown fields are never propagated. */
	public static function sanitize( array $input ) {
		$known = array_intersect_key( $input, array_fill_keys( self::fields(), true ) );
		return array(
			'title' => self::sanitize_text( isset( $known['title'] ) ? $known['title'] : '' ),
			'content' => self::sanitize_content( isset( $known['content'] ) ? $known['content'] : '' ),
			'subtitle' => self::sanitize_text( isset( $known['subtitle'] ) ? $known['subtitle'] : '' ),
			'summary' => self::sanitize_textarea( isset( $known['summary'] ) ? $known['summary'] : '' ),
			'language' => self::sanitize_language( isset( $known['language'] ) ? $known['language'] : 'en-US' ),
			'priority' => self::sanitize_priority( isset( $known['priority'] ) ? $known['priority'] : 0 ),
			'section' => self::sanitize_section( isset( $known['section'] ) ? $known['section'] : '' ),
			'article_type' => self::sanitize_article_type( isset( $known['article_type'] ) ? $known['article_type'] : '' ),
			'topics' => self::sanitize_slug_list( isset( $known['topics'] ) ? $known['topics'] : array() ),
			'countries' => self::sanitize_slug_list( isset( $known['countries'] ) ? $known['countries'] : array() ),
			'regions' => self::sanitize_slug_list( isset( $known['regions'] ) ? $known['regions'] : array() ),
			'featured_image_id' => self::sanitize_id( isset( $known['featured_image_id'] ) ? $known['featured_image_id'] : 0 ),
			'reviewing_editor_id' => self::sanitize_id( isset( $known['reviewing_editor_id'] ) ? $known['reviewing_editor_id'] : 0 ),
			'medical_reviewer_id' => self::sanitize_id( isset( $known['medical_reviewer_id'] ) ? $known['medical_reviewer_id'] : 0 ),
			'fact_check_required' => self::sanitize_boolean( isset( $known['fact_check_required'] ) ? $known['fact_check_required'] : 0 ),
			'medical_review_required' => self::sanitize_boolean( isset( $known['medical_review_required'] ) ? $known['medical_review_required'] : 0 ),
			'target_state' => NewsStatuses::sanitize_state( isset( $known['target_state'] ) ? $known['target_state'] : 'draft' ),
			'schedule_at_utc' => class_exists( __NAMESPACE__ . '\\NewsSchedulingService' ) ? NewsSchedulingService::normalize_utc( isset( $known['schedule_at'] ) ? $known['schedule_at'] : '' ) : '',
		);
	}

	/** Validate raw input before fallbacks can conceal malformed values. */
	public static function validate( array $input ) {
		$errors = array();
		$data = self::sanitize( $input );
		if ( '' === $data['title'] ) {
			$errors['title'] = 'required';
		} elseif ( self::raw_length( isset( $input['title'] ) ? $input['title'] : '' ) > 300 ) {
			$errors['title'] = 'too_long';
		}
		if ( self::raw_length( isset( $input['subtitle'] ) ? $input['subtitle'] : '' ) > 300 ) {
			$errors['subtitle'] = 'too_long';
		}
		if ( self::raw_length( isset( $input['summary'] ) ? $input['summary'] : '' ) > 1000 ) {
			$errors['summary'] = 'too_long';
		}
		if ( self::raw_length( isset( $input['content'] ) ? $input['content'] : '' ) > 1000000 ) {
			$errors['content'] = 'too_long';
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
		foreach ( array( 'topics', 'countries', 'regions' ) as $field ) {
			if ( isset( $input[ $field ] ) && ! self::is_valid_slug_list( $input[ $field ] ) ) {
				$errors[ $field ] = 'invalid';
			}
		}
		foreach ( array( 'featured_image_id', 'reviewing_editor_id', 'medical_reviewer_id' ) as $field ) {
			if ( isset( $input[ $field ] ) && ! self::is_valid_id( $input[ $field ] ) ) {
				$errors[ $field ] = 'invalid';
			}
		}
		foreach ( array( 'fact_check_required', 'medical_review_required' ) as $field ) {
			if ( isset( $input[ $field ] ) && ! self::is_valid_boolean( $input[ $field ] ) ) {
				$errors[ $field ] = 'invalid';
			}
		}
		if ( isset( $input['target_state'] ) && '' === $data['target_state'] ) {
			$errors['target_state'] = 'invalid';
		}
		if ( isset( $input['schedule_at'] ) && '' !== (string) $input['schedule_at'] && '' === $data['schedule_at_utc'] ) {
			$errors['schedule_at'] = 'invalid_or_ambiguous';
		}
		if ( in_array( $data['target_state'], array( 'ready-for-publication', 'scheduled', 'published' ), true ) && '' === $data['summary'] ) {
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
			'code' => empty( $errors ) ? 'composer_input_valid' : 'composer_input_invalid',
			'errors' => $errors,
			'data' => $data,
		);
	}

	/** Accept a bounded BCP-47-style tag exactly as submitted. */
	private static function is_valid_language( $value ) {
		return is_string( $value ) && '' !== $value && strlen( $value ) <= 20 && 1 === preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $value );
	}

	private static function sanitize_language( $value ) {
		return self::is_valid_language( $value ) ? (string) $value : 'en-US';
	}

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

	private static function sanitize_priority( $value ) {
		return self::is_valid_priority( $value ) ? (int) $value : 0;
	}

	private static function sanitize_section( $value ) {
		$value = self::strict_slug( $value );
		return $value && array_key_exists( $value, Phase4Contracts::sections() ) ? $value : '';
	}

	private static function sanitize_article_type( $value ) {
		$value = self::strict_slug( $value );
		return $value && array_key_exists( $value, Phase4Contracts::article_types() ) ? $value : '';
	}

	private static function is_valid_slug_list( $value ) {
		if ( '' === $value || null === $value ) {
			return true;
		}
		if ( is_string( $value ) ) {
			$value = array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' );
		}
		if ( ! is_array( $value ) || count( $value ) > 20 ) {
			return false;
		}
		foreach ( $value as $slug ) {
			if ( '' === self::strict_slug( $slug ) ) {
				return false;
			}
		}
		return true;
	}

	private static function sanitize_slug_list( $value ) {
		if ( ! self::is_valid_slug_list( $value ) ) {
			return array();
		}
		if ( is_string( $value ) ) {
			$value = array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' );
		}
		return array_values( array_unique( array_map( array( __CLASS__, 'strict_slug' ), (array) $value ) ) );
	}

	private static function is_valid_id( $value ) {
		return is_int( $value ) && $value >= 0 || is_string( $value ) && 1 === preg_match( '/^(?:0|[1-9][0-9]*)$/D', $value );
	}

	private static function sanitize_id( $value ) {
		return self::is_valid_id( $value ) ? (int) $value : 0;
	}

	private static function is_valid_boolean( $value ) {
		return in_array( $value, array( 0, 1, '0', '1', false, true ), true );
	}

	private static function sanitize_boolean( $value ) {
		return in_array( $value, array( 1, '1', true ), true );
	}

	private static function strict_slug( $value ) {
		return is_string( $value ) && '' !== $value && strlen( $value ) <= 80 && 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) ? $value : '';
	}

	private static function sanitize_text( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( $value ) );
	}

	private static function sanitize_textarea( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		return function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $value ) : trim( strip_tags( $value ) );
	}

	private static function sanitize_content( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $value ) : trim( $value );
	}

	private static function raw_length( $value ) {
		return is_scalar( $value ) ? strlen( (string) $value ) : 0;
	}
}
