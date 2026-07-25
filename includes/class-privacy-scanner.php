<?php
/**
 * Privacy scanner for editorial and submission content.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Detects high-risk direct identifiers without returning matched values. */
final class PrivacyScanner {
	public static function register() {}

	public static function scan( $text ) {
		$text = is_string( $text ) ? $text : '';
		$patterns = array(
			'email' => '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
			'phone' => '/(?<!\d)(?:\+?\d[\d\s().-]{7,}\d)(?!\d)/',
			'cnic' => '/\b\d{5}-?\d{7}-?\d\b/',
			'passport' => '/\b[A-Z]{1,2}[0-9]{6,9}\b/i',
			'medical-record' => '/\b(?:MRN|medical\s*record|patient\s*id|registration\s*no)\s*[:#-]?\s*[A-Z0-9-]{4,}\b/i',
			'address' => '/\b(?:house|street|road|lane|block|sector|flat|apartment)\s+(?:no\.?\s*)?[A-Z0-9-]{1,10}\b/i',
		);
		$categories = array();
		foreach ( $patterns as $category => $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				$categories[] = $category;
			}
		}
		return array( 'blocked' => ! empty( $categories ), 'categories' => $categories );
	}

	public static function redact( $text ) {
		$text = is_string( $text ) ? $text : '';
		$replacements = array(
			'/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i' => '[email removed]',
			'/(?<!\d)(?:\+?\d[\d\s().-]{7,}\d)(?!\d)/' => '[phone removed]',
			'/\b\d{5}-?\d{7}-?\d\b/' => '[identity removed]',
			'/\b[A-Z]{1,2}[0-9]{6,9}\b/i' => '[identifier removed]',
		);
		return preg_replace( array_keys( $replacements ), array_values( $replacements ), $text );
	}
}
