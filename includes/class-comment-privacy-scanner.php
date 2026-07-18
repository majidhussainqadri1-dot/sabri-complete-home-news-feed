<?php
/**
 * Phase 3C clinical comment privacy scanner.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects obvious patient identifiers before a clinical-case comment is stored.
 */
final class CommentPrivacyScanner {
	/**
	 * Scan plain text for high-confidence privacy risks.
	 *
	 * The scanner returns categories only and never returns the matched private
	 * value. It is deliberately conservative and filterable for staging review.
	 *
	 * @param string $content Plain text.
	 * @return array<string,mixed>
	 */
	public static function scan( $content ) {
		$content = (string) $content;
		$risks   = array();

		$patterns = array(
			'email-address' => '/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i',
			'phone-number'  => '/(?<!\d)(?:\+?\d[\s().\-]*){10,15}(?!\d)/',
			'cnic-number'   => '/\b\d{5}[\s\-]?\d{7}[\s\-]?\d\b/',
			'passport-id'   => '/\b(?:passport|cnic|national\s+id|identity\s+card)\s*(?:number|no\.?|#|:)\s*[A-Z0-9\-]{5,20}\b/i',
			'patient-name'  => '/\b(?:patient|client)\s+(?:full\s+)?name\s*(?:is|:|\-)/i',
			'contact-label' => '/\b(?:phone|mobile|whatsapp|contact)\s*(?:number|no\.?|#|:)\s*/i',
			'address-label' => '/\b(?:home|residential|patient)\s+address\s*(?:is|:|\-)/i',
			'medical-id'    => '/\b(?:mrn|medical\s+record|registration)\s*(?:number|no\.?|#|:)\s*[A-Z0-9\-]{3,20}\b/i',
		);

		foreach ( $patterns as $risk => $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				$risks[] = $risk;
			}
		}

		$risks = array_values( array_unique( $risks ) );
		if ( function_exists( 'apply_filters' ) ) {
			$risks = apply_filters( 'sabri_feed_comment_privacy_risks', $risks, $content );
		}
		$risks = is_array( $risks ) ? array_values( array_unique( array_filter( array_map( 'sanitize_key', $risks ) ) ) ) : array();

		return array(
			'safe'  => empty( $risks ),
			'risks' => $risks,
		);
	}
}
