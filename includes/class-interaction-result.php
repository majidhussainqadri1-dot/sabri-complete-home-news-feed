<?php
/**
 * Structured Phase 3 service result.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces one stable result shape for Phase 3 services and REST controllers.
 */
final class InteractionResult {
	/**
	 * Successful result.
	 *
	 * @param string              $code Machine-readable code.
	 * @param array<string,mixed> $data Safe response data.
	 * @param string              $message Human-readable message.
	 * @param int                 $status HTTP-style status.
	 * @return array<string,mixed>
	 */
	public static function success( $code = 'ok', array $data = array(), $message = '', $status = 200 ) {
		return self::build( true, $code, $message, $data, $status, 200 );
	}

	/**
	 * Error result.
	 *
	 * @param string              $code Machine-readable code.
	 * @param string              $message Human-readable message.
	 * @param array<string,mixed> $data Safe response data.
	 * @param int                 $status HTTP-style status.
	 * @return array<string,mixed>
	 */
	public static function error( $code = 'error', $message = '', array $data = array(), $status = 400 ) {
		return self::build( false, $code, $message, $data, $status, 400 );
	}

	/**
	 * Build a stable response payload.
	 *
	 * @param bool                $ok Success state.
	 * @param string              $code Machine-readable code.
	 * @param string              $message Human-readable message.
	 * @param array<string,mixed> $data Safe response data.
	 * @param int                 $status HTTP-style status.
	 * @param int                 $fallback_status Fallback status.
	 * @return array<string,mixed>
	 */
	private static function build( $ok, $code, $message, array $data, $status, $fallback_status ) {
		return array(
			'ok'      => (bool) $ok,
			'code'    => self::clean_code( $code, $ok ? 'ok' : 'error' ),
			'message' => self::clean_message( $message ),
			'data'    => $data,
			'status'  => self::normalize_status( $status, $fallback_status ),
		);
	}

	/**
	 * Sanitize a result code.
	 *
	 * @param mixed  $code Code value.
	 * @param string $fallback Fallback code.
	 * @return string
	 */
	private static function clean_code( $code, $fallback ) {
		if ( function_exists( 'sanitize_key' ) ) {
			$code = sanitize_key( $code );
		} else {
			$code = strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $code ) );
		}

		return '' !== $code ? $code : $fallback;
	}

	/**
	 * Sanitize a public message.
	 *
	 * @param mixed $message Message value.
	 * @return string
	 */
	private static function clean_message( $message ) {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $message );
		}

		return trim( strip_tags( (string) $message ) );
	}

	/**
	 * Bound an HTTP-style status code.
	 *
	 * @param mixed $status Status value.
	 * @param int   $fallback Fallback status.
	 * @return int
	 */
	private static function normalize_status( $status, $fallback ) {
		$status = (int) $status;
		return $status >= 100 && $status <= 599 ? $status : (int) $fallback;
	}
}
