<?php
/**
 * Phase 3F poll definition and results policy.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes poll definitions and resolves close/results behavior.
 */
final class PollPolicy {
	const META_KEY = '_sabri_hnf_poll';
	const MIN_OPTIONS = 2;
	const MAX_OPTIONS = 8;
	const QUESTION_MAX = 240;
	const OPTION_MAX = 120;
	const VOTE_GROUP = 'default';

	/**
	 * Sanitize and validate a raw poll definition.
	 *
	 * @param mixed $raw Raw definition.
	 * @param bool  $require_future_close Whether a supplied close time must be future.
	 * @return array<string,mixed>
	 */
	public static function validate_definition( $raw, $require_future_close = false ) {
		$definition = self::sanitize_definition( $raw );
		$errors     = array();

		if ( '' === $definition['question'] ) {
			$errors[] = array( 'code' => 'poll_question_required', 'message' => __( 'Poll question is required.', 'sabri-complete-home-news-feed' ) );
		}

		if ( count( $definition['options'] ) < self::MIN_OPTIONS ) {
			$errors[] = array( 'code' => 'poll_options_required', 'message' => __( 'A poll requires at least two distinct options.', 'sabri-complete-home-news-feed' ) );
		}

		if ( count( $definition['options'] ) > self::MAX_OPTIONS ) {
			$errors[] = array( 'code' => 'poll_options_exceeded', 'message' => __( 'A poll may contain no more than eight options.', 'sabri-complete-home-news-feed' ) );
		}

		if ( $require_future_close && '' !== $definition['closes_at'] && self::timestamp( $definition['closes_at'] ) <= self::now() ) {
			$errors[] = array( 'code' => 'poll_close_must_be_future', 'message' => __( 'Poll closing time must be in the future.', 'sabri-complete-home-news-feed' ) );
		}

		return array(
			'valid'      => empty( $errors ),
			'errors'     => $errors,
			'definition' => $definition,
		);
	}

	/**
	 * Sanitize a raw definition without trusting option keys.
	 *
	 * @param mixed $raw Raw definition.
	 * @return array<string,mixed>
	 */
	public static function sanitize_definition( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$question = self::bounded_text( isset( $raw['question'] ) ? $raw['question'] : '', self::QUESTION_MAX );
		$options  = array();
		$seen     = array();
		$raw_options = isset( $raw['options'] ) && is_array( $raw['options'] ) ? $raw['options'] : array();

		foreach ( $raw_options as $option ) {
			$label = is_array( $option ) && array_key_exists( 'label', $option ) ? $option['label'] : $option;
			$label = self::bounded_text( $label, self::OPTION_MAX );
			if ( '' === $label ) {
				continue;
			}

			$identity = function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label );
			if ( isset( $seen[ $identity ] ) ) {
				continue;
			}
			$seen[ $identity ] = true;

			$key = is_array( $option ) && ! empty( $option['key'] ) ? self::option_key( $option['key'] ) : '';
			if ( '' === $key || isset( $options[ $key ] ) ) {
				$key = 'option-' . ( count( $options ) + 1 );
			}

			$options[ $key ] = array(
				'key'   => $key,
				'label' => $label,
			);

			if ( count( $options ) >= self::MAX_OPTIONS ) {
				break;
			}
		}

		$results_policy = self::clean_key( isset( $raw['results_policy'] ) ? $raw['results_policy'] : 'after_vote' );
		if ( ! in_array( $results_policy, Phase3Contracts::poll_results_policies(), true ) ) {
			$results_policy = 'after_vote';
		}

		return array(
			'question'       => $question,
			'options'        => array_values( $options ),
			'results_policy' => $results_policy,
			'closes_at'      => self::normalize_datetime( isset( $raw['closes_at'] ) ? $raw['closes_at'] : '' ),
			'allow_change'   => self::bool_value( isset( $raw['allow_change'] ) ? $raw['allow_change'] : true ) ? 1 : 0,
			'vote_group_key' => self::VOTE_GROUP,
		);
	}

	/**
	 * Save a validated poll definition.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $definition Definition.
	 * @return bool
	 */
	public static function save_definition( $post_id, array $definition ) {
		$post_id = self::positive_id( $post_id );
		if ( $post_id <= 0 || ! function_exists( 'update_post_meta' ) ) {
			return false;
		}

		$validated = self::validate_definition( $definition, false );
		if ( empty( $validated['valid'] ) ) {
			return false;
		}

		return false !== update_post_meta( $post_id, self::META_KEY, $validated['definition'] );
	}

	/**
	 * Remove poll definition from a non-poll post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function delete_definition( $post_id ) {
		$post_id = self::positive_id( $post_id );
		return $post_id > 0 && function_exists( 'delete_post_meta' ) ? (bool) delete_post_meta( $post_id, self::META_KEY ) : false;
	}

	/**
	 * Return a safe stored definition.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	public static function definition( $post_id ) {
		$post_id = self::positive_id( $post_id );
		$raw     = $post_id > 0 && function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, self::META_KEY, true ) : array();
		return self::sanitize_definition( is_array( $raw ) ? $raw : array() );
	}

	/**
	 * Whether a post is a valid poll post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_poll( $post_id ) {
		if ( 'poll' !== PostMetadata::feed_type( $post_id ) ) {
			return false;
		}
		$validated = self::validate_definition( self::definition( $post_id ), false );
		return ! empty( $validated['valid'] );
	}

	/**
	 * Whether the poll is closed by UTC time.
	 *
	 * @param array<string,mixed> $definition Definition.
	 * @return bool
	 */
	public static function is_closed( array $definition ) {
		return ! empty( $definition['closes_at'] ) && self::timestamp( $definition['closes_at'] ) <= self::now();
	}

	/**
	 * Whether aggregate results may be shown to this requester.
	 *
	 * @param array<string,mixed> $definition Definition.
	 * @param bool                $has_voted Whether current user has an active vote.
	 * @return bool
	 */
	public static function results_visible( array $definition, $has_voted ) {
		$policy = isset( $definition['results_policy'] ) ? self::clean_key( $definition['results_policy'] ) : 'after_vote';
		if ( 'always' === $policy ) {
			return true;
		}
		if ( 'after_close' === $policy ) {
			return self::is_closed( $definition );
		}
		return (bool) $has_voted;
	}

	/**
	 * Return one option label or an empty string.
	 *
	 * @param array<string,mixed> $definition Definition.
	 * @param mixed               $key Option key.
	 * @return string
	 */
	public static function option_label( array $definition, $key ) {
		$key = self::option_key( $key );
		foreach ( isset( $definition['options'] ) && is_array( $definition['options'] ) ? $definition['options'] : array() as $option ) {
			if ( isset( $option['key'], $option['label'] ) && $key === $option['key'] ) {
				return (string) $option['label'];
			}
		}
		return '';
	}

	/**
	 * Normalize an option key.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function option_key( $value ) {
		$value = self::clean_key( $value );
		return substr( $value, 0, 64 );
	}

	/**
	 * Filterable current UTC timestamp for deterministic tests.
	 *
	 * @return int
	 */
	public static function now() {
		$now = time();
		return function_exists( 'apply_filters' ) ? (int) apply_filters( 'sabri_feed_poll_now', $now ) : (int) $now;
	}

	/**
	 * Normalize accepted date/time input to UTC storage format.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function normalize_datetime( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		foreach ( array( 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i' ) as $format ) {
			$date   = \DateTimeImmutable::createFromFormat( '!' . $format, $value, new \DateTimeZone( 'UTC' ) );
			$errors = \DateTimeImmutable::getLastErrors();
			$valid  = is_array( $errors ) ? 0 === (int) $errors['warning_count'] && 0 === (int) $errors['error_count'] : true;
			if ( $date && $valid && $date->format( $format ) === $value ) {
				return $date->format( 'Y-m-d H:i:s' );
			}
		}
		return '';
	}

	/**
	 * Parse normalized UTC date.
	 *
	 * @param string $value Value.
	 * @return int
	 */
	private static function timestamp( $value ) {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', (string) $value, new \DateTimeZone( 'UTC' ) );
		return $date ? $date->getTimestamp() : 0;
	}

	/**
	 * Bounded plain text.
	 *
	 * @param mixed $value Value.
	 * @param int   $max Maximum characters.
	 * @return string
	 */
	private static function bounded_text( $value, $max ) {
		$value = function_exists( 'wp_unslash' ) ? wp_unslash( $value ) : $value;
		$value = function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( (string) $value ) );
		$value = trim( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
	}

	/**
	 * Boolean-like sanitizer.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function bool_value( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Strict positive ID.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private static function positive_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/', (string) $value ) ? (int) $value : 0;
	}

	/**
	 * Sanitized key.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}
}
