<?php
/**
 * Regression tests for the live Safe Boot recursion reported in
 * HarmonizedSettings option normalization.
 */

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}
	if ( ! defined( 'SABRI_HNF_VERSION' ) ) {
		define( 'SABRI_HNF_VERSION', '1.0.3' );
	}

	/** The hotfix must never call WordPress' filterable sanitize_key(). */
	function sanitize_key( $value ) {
		throw new \RuntimeException( 'sanitize_key() must not run inside HarmonizedSettings option normalization: ' . (string) $value );
	}
}

namespace Sabri\HomeNewsFeed {
	final class Settings {
		const OPTION_NAME = 'sabri_feed_settings';
	}

	final class FeedContext {
		private static $reentered = false;

		public static function modes() {
			if ( ! self::$reentered ) {
				self::$reentered = true;
				HarmonizedSettings::normalize(
					array(
						'feed' => array( 'enabled_filters' => array( 'latest' ) ),
					)
				);
			}
			return array( 'for-you' => 'For You', 'latest' => 'Latest' );
		}

		public static function phase2_feed_type_slugs() {
			return array( 'standard-post', 'founder-update' );
		}

		public static function phase2_visibility_slugs( $include_private = false ) {
			$values = array( 'public', 'members' );
			if ( $include_private ) {
				$values[] = 'private';
			}
			return $values;
		}
	}

	final class Taxonomies {
		public static function feed_type_terms() {
			return array( 'standard-post' => 'Standard Post', 'founder-update' => 'Founder Update' );
		}
	}

	final class AuditLog {
		public static function record( $event, array $context = array() ) {
			unset( $event, $context );
		}
	}

	require_once dirname( __DIR__ ) . '/includes/class-harmonized-settings.php';

	$input = array(
		'capabilities' => array(
			'founder_roles' => array( 'Founder', 'custom founder', 'founder' ),
			'verified_doctor_policy' => 'submit',
		),
		'feed' => array(
			'enabled_filters' => array( 'latest', 'not-allowed' ),
			'allowed_types' => array( 'standard-post', 'unsafe type' ),
		),
		'composer' => array(
			'allowed_feed_types' => array( 'standard-post', 'unsafe type' ),
			'allowed_visibility_modes' => array( 'public', 'private', 'unsafe mode' ),
		),
		'future_namespace' => array( 'preserve_me' => true ),
	);

	$result = HarmonizedSettings::normalize( $input );

	$failures = array();
	if ( '1.0.2' !== $result['version'] ) {
		$failures[] = 'Runtime version was not normalized.';
	}
	if ( ! in_array( 'founder', $result['capabilities']['founder_roles'], true ) || ! in_array( 'sabri_founder', $result['capabilities']['founder_roles'], true ) ) {
		$failures[] = 'Founder aliases were not normalized.';
	}
	if ( in_array( 'custom founder', $result['capabilities']['founder_roles'], true ) ) {
		$failures[] = 'Controlled role keys were not sanitized.';
	}
	if ( 'trusted' !== $result['capabilities']['verified_doctor_policy'] ) {
		$failures[] = 'Legacy submit policy was not normalized to trusted.';
	}
	if ( in_array( 'not-allowed', $result['feed']['enabled_filters'], true ) ) {
		$failures[] = 'Unknown Feed mode was retained.';
	}
	if ( in_array( 'unsafetype', $result['feed']['allowed_types'], true ) ) {
		$failures[] = 'Unknown Feed type was retained.';
	}
	if ( empty( $result['future_namespace']['preserve_me'] ) ) {
		$failures[] = 'Unknown future settings were not preserved.';
	}

	// A second complete call proves the try/finally guard was released.
	$second = HarmonizedSettings::normalize( array() );
	if ( '1.0.2' !== $second['version'] || empty( $second['feed']['enabled_filters'] ) ) {
		$failures[] = 'The re-entry guard did not reset after normalization.';
	}

	if ( $failures ) {
		fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
		exit( 1 );
	}

	echo "File 21 settings recursion regression tests passed.\n";
}
