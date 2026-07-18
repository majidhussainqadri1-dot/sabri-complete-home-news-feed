<?php
/**
 * Non-destructive repair foundation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Performs explicit, non-destructive repairs.
 */
final class Repair {
	/**
	 * Repair actions.
	 *
	 * @return array<string,string>
	 */
	public static function actions() {
		return array(
			'missing_tables'       => __( 'Repair missing tables', 'sabri-complete-home-news-feed' ),
			'missing_indexes'      => __( 'Repair missing indexes', 'sabri-complete-home-news-feed' ),
			'default_terms'        => __( 'Restore missing default terms', 'sabri-complete-home-news-feed' ),
			'default_settings'     => __( 'Restore missing default settings', 'sabri-complete-home-news-feed' ),
			'stale_schema_version' => __( 'Refresh stale schema version', 'sabri-complete-home-news-feed' ),
			'rewrite_refresh'      => __( 'Refresh rewrite rules', 'sabri-complete-home-news-feed' ),
			'integration_cache'    => __( 'Refresh integration cache', 'sabri-complete-home-news-feed' ),
			'orphan_preview'       => __( 'Preview orphan social rows', 'sabri-complete-home-news-feed' ),
		);
	}

	/**
	 * Preview all repair categories.
	 *
	 * @return array<string,mixed>
	 */
	public static function preview() {
		return array(
			'tables'          => Database::table_status(),
			'indexes'         => Database::index_status(),
			'default_terms'   => array_keys( Taxonomies::feed_type_terms() ),
			'default_settings' => Settings::namespaces(),
			'schema_version'  => array(
				'current' => Migrations::current_version(),
				'target'  => SABRI_HNF_SCHEMA_VERSION,
			),
			'rewrite_refresh' => 'Available',
			'integration_cache' => Integrations::detect(),
			'orphan_preview'  => self::orphan_preview(),
			'destructive'     => false,
		);
	}

	/**
	 * Execute one repair action.
	 *
	 * @param string $action Action key.
	 * @return array<string,mixed>
	 */
	public static function execute( $action ) {
		$action = sanitize_key( $action );
		$result = array(
			'action'      => $action,
			'destructive' => false,
			'result'      => array(),
		);

		if ( ! isset( self::actions()[ $action ] ) ) {
			$result['result'] = array( 'error' => 'unsupported_action' );
			return $result;
		}

		switch ( $action ) {
			case 'missing_tables':
			case 'missing_indexes':
				$result['result'] = Database::install();
				break;
			case 'default_terms':
				Taxonomies::register_taxonomies();
				$result['result'] = Taxonomies::ensure_default_terms();
				break;
			case 'default_settings':
				$result['result'] = Settings::ensure_defaults();
				break;
			case 'stale_schema_version':
				$result['result'] = Migrations::migrate();
				break;
			case 'rewrite_refresh':
				if ( function_exists( 'flush_rewrite_rules' ) ) {
					flush_rewrite_rules( false );
				}
				if ( function_exists( 'update_option' ) ) {
					update_option( 'sabri_feed_flush_rewrite_rules', 0, false );
				}
				$result['result'] = array( 'refreshed' => true );
				break;
			case 'integration_cache':
				if ( function_exists( 'delete_transient' ) ) {
					delete_transient( 'sabri_feed_integration_status' );
				}
				$result['result'] = Integrations::detect();
				break;
			case 'orphan_preview':
				$result['result'] = self::orphan_preview();
				break;
		}

		AuditLog::record( 'repair_' . $action, $result );

		return $result;
	}

	/**
	 * Preview orphan social rows without deleting anything.
	 *
	 * @return array<string,mixed>
	 */
	public static function orphan_preview() {
		global $wpdb;

		$preview = array(
			'checked' => false,
			'rows'    => array(),
			'note'    => 'Phase 1 previews orphan records only; it never deletes them automatically.',
		);

		if ( ! isset( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $preview;
		}

		$preview['checked'] = true;
		$tables = Database::table_names();
		foreach ( array( 'reactions', 'saves', 'views' ) as $slug ) {
			$table = $tables[ $slug ];
			$preview['rows'][ $slug ] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `" . str_replace( '`', '', $table ) . "` f LEFT JOIN `" . str_replace( '`', '', $wpdb->posts ) . "` p ON p.ID = f.post_id WHERE p.ID IS NULL" );
		}

		return $preview;
	}
}
