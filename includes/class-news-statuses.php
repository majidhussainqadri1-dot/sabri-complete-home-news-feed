<?php
/**
 * Phase 4 editorial workflow-state model.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps full editorial workflow states outside WordPress post_status limits.
 */
final class NewsStatuses {
	/** Register the status model. No unsafe long custom post statuses are added. */
	public static function register() {
		// Domain states are stored in Phase4Contracts::WORKFLOW_META_KEY.
	}

	/** Return all domain states. */
	public static function states() {
		return Phase4Contracts::editorial_states();
	}

	/** Return true only for a frozen domain state. */
	public static function is_valid( $state ) {
		return in_array( self::sanitize_identifier( $state ), self::states(), true );
	}

	/** Normalize a state or return an empty value for fail-closed validation. */
	public static function sanitize_state( $state ) {
		$state = self::sanitize_identifier( $state );
		return self::is_valid( $state ) ? $state : '';
	}

	/** Return the compatible WordPress core status for a domain state. */
	public static function wordpress_status( $state ) {
		$state = self::sanitize_state( $state );
		$map   = Phase4Contracts::wordpress_status_map();
		return $state && isset( $map[ $state ] ) ? $map[ $state ] : '';
	}

	/** Describe the frozen dual-layer storage strategy. */
	public static function storage_contract() {
		return array(
			'domain_state_key' => Phase4Contracts::WORKFLOW_META_KEY,
			'core_status_map'  => Phase4Contracts::wordpress_status_map(),
			'source_of_truth'  => 'domain_state_key',
		);
	}

	/** Sanitize an identifier without requiring WordPress in lean tests. */
	private static function sanitize_identifier( $value ) {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}
		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}
}
