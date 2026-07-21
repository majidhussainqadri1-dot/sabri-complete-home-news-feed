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

	/** Return true only for an exact frozen domain state. */
	public static function is_valid( $state ) {
		return '' !== self::strict_identifier( $state ) && in_array( (string) $state, self::states(), true );
	}

	/** Return an exact state or an empty value for fail-closed validation. */
	public static function sanitize_state( $state ) {
		$state = self::strict_identifier( $state );
		return '' !== $state && in_array( $state, self::states(), true ) ? $state : '';
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

	/**
	 * Validate without repairing input into a different accepted identifier.
	 *
	 * Whitespace, uppercase aliases, punctuation, arrays, and objects fail closed.
	 */
	private static function strict_identifier( $value ) {
		if ( ! is_string( $value ) || '' === $value || strlen( $value ) > 64 ) {
			return '';
		}
		return preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) ? $value : '';
	}
}
