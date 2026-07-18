<?php
/**
 * Deactivation boundary.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preserves data on deactivation.
 */
final class Deactivator {
	/**
	 * Deactivate plugin without deleting data.
	 *
	 * @return array<string,mixed>
	 */
	public static function deactivate() {
		if ( function_exists( 'update_option' ) ) {
			update_option( 'sabri_feed_flush_rewrite_rules', 1, false );
		}

		AuditLog::record( 'deactivation', array( 'preserved_data' => true ) );

		return array(
			'preserved_data' => true,
			'deleted_content' => false,
		);
	}
}
