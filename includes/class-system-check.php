<?php
/**
 * System check foundation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Produces diagnostics without claiming production acceptance.
 */
final class SystemCheck {
	/**
	 * Full report.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function report() {
		$rows = array(
			self::row( 'PHP version', version_compare( PHP_VERSION, SABRI_HNF_MINIMUM_PHP, '>=' ) ? 'Connected' : 'Unsupported', PHP_VERSION ),
			self::row( 'WordPress version', self::wp_supported() ? 'Connected' : 'Unsupported', self::wp_version() ),
			self::row( 'Database access', self::database_available() ? 'Connected' : 'Missing', self::database_available() ? 'wpdb is available.' : 'wpdb was not available in this environment.' ),
			self::row( 'Options', function_exists( 'get_option' ) ? 'Connected' : 'Missing', Settings::OPTION_NAME ),
			self::row( 'Cron availability', self::cron_available() ? 'Connected' : 'Disabled', self::cron_available() ? 'WP-Cron is not disabled by constant.' : 'DISABLE_WP_CRON is true.' ),
			self::row( 'REST availability', function_exists( 'register_rest_route' ) ? 'Connected' : 'Missing', 'Foundation diagnostics routes are admin-only.' ),
			self::row( 'Media directory', self::media_status(), self::media_detail() ),
			self::row( 'Filesystem write status', is_writable( SABRI_HNF_PATH ) ? 'Connected' : 'Available but not configured', SABRI_HNF_PATH ),
			self::row( 'Safe Mode', SafeMode::query_safe_mode() ? 'Connected' : 'Available but not configured', 'Administrator query flag: ?sabri_feed_safe=1' ),
			self::row( 'Emergency Disable', SafeMode::emergency_disabled() ? 'Disabled' : 'Connected', SafeMode::emergency_disabled() ? 'Future public actions are disabled.' : 'Future public actions are gated normally.' ),
			self::row( 'Current branch', self::git_context()['branch_status'], self::git_context()['branch'] ),
			self::row( 'Current commit', self::git_context()['commit_status'], self::git_context()['commit'] ),
		);

		foreach ( Database::table_status() as $slug => $status ) {
			$rows[] = self::row( 'Table: ' . $slug, $status, Database::table_names()[ $slug ] );
		}

		foreach ( Database::index_status() as $slug => $indexes ) {
			$missing = array();
			foreach ( $indexes as $index => $status ) {
				if ( 'Connected' !== $status ) {
					$missing[] = $index;
				}
			}
			$rows[] = self::row( 'Indexes: ' . $slug, empty( $missing ) ? 'Connected' : 'Missing', empty( $missing ) ? 'Expected indexes are present.' : 'Missing: ' . implode( ', ', $missing ) );
		}

		$integrations = Integrations::detect();
		foreach ( $integrations as $key => $value ) {
			if ( is_array( $value ) ) {
				$rows[] = self::row( 'Integration: ' . $key, isset( $value['status'] ) ? $value['status'] : 'Available but not configured', isset( $value['version'] ) && $value['version'] ? 'Version ' . $value['version'] : 'No runtime requirement.' );
			} else {
				$rows[] = self::row( 'Integration: ' . $key, $value, 'Detected foundation state.' );
			}
		}

		return $rows;
	}

	/**
	 * Capability status summary.
	 *
	 * @return array<string,string>
	 */
	public static function capability_status() {
		$status = array();

		if ( ! function_exists( 'get_role' ) ) {
			return array( 'roles' => 'Missing' );
		}

		foreach ( Capabilities::default_role_map() as $role_slug => $caps ) {
			$role = get_role( $role_slug );
			if ( ! $role ) {
				$status[ $role_slug ] = 'Missing';
				continue;
			}

			$missing = array();
			foreach ( $caps as $capability ) {
				if ( empty( $role->capabilities[ $capability ] ) ) {
					$missing[] = $capability;
				}
			}

			$status[ $role_slug ] = empty( $missing ) ? 'Connected' : 'Available but not configured';
		}

		return $status;
	}

	/**
	 * Migration status.
	 *
	 * @return string
	 */
	public static function migration_status() {
		return SABRI_HNF_SCHEMA_VERSION === Migrations::current_version() ? 'Connected' : 'Available but not configured';
	}

	/**
	 * Snapshot status.
	 *
	 * @return string
	 */
	public static function snapshot_status() {
		return Snapshot::latest() ? 'Connected' : 'Missing';
	}

	/**
	 * Build one report row.
	 *
	 * @param string $label Label.
	 * @param string $status Status.
	 * @param string $detail Detail.
	 * @return array<string,string>
	 */
	private static function row( $label, $status, $detail ) {
		return array(
			'label'  => $label,
			'status' => $status,
			'detail' => $detail,
		);
	}

	/**
	 * WordPress version supported.
	 *
	 * @return bool
	 */
	private static function wp_supported() {
		return version_compare( self::wp_version(), SABRI_HNF_MINIMUM_WP, '>=' );
	}

	/**
	 * WordPress version string.
	 *
	 * @return string
	 */
	private static function wp_version() {
		global $wp_version;
		return $wp_version ? (string) $wp_version : 'unknown';
	}

	/**
	 * Database availability.
	 *
	 * @return bool
	 */
	private static function database_available() {
		global $wpdb;
		return isset( $wpdb ) && is_object( $wpdb );
	}

	/**
	 * Cron availability.
	 *
	 * @return bool
	 */
	private static function cron_available() {
		return ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON );
	}

	/**
	 * Media directory status.
	 *
	 * @return string
	 */
	private static function media_status() {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return 'Missing';
		}

		$uploads = wp_upload_dir();
		return empty( $uploads['error'] ) ? 'Connected' : 'Unsupported';
	}

	/**
	 * Media directory detail.
	 *
	 * @return string
	 */
	private static function media_detail() {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return 'wp_upload_dir() unavailable.';
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return (string) $uploads['error'];
		}

		return isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : 'Upload directory available.';
	}

	/**
	 * Read branch and commit when a local .git directory is safely available.
	 *
	 * @return array<string,string>
	 */
	public static function git_context() {
		$empty = array(
			'branch_status' => 'Available but not configured',
			'branch'        => 'Unavailable in packaged installs.',
			'commit_status' => 'Available but not configured',
			'commit'        => 'Unavailable in packaged installs.',
		);

		$git = SABRI_HNF_PATH . '.git';
		if ( is_file( $git ) ) {
			$contents = trim( (string) file_get_contents( $git ) );
			if ( 0 === strpos( $contents, 'gitdir:' ) ) {
				$git = trim( substr( $contents, 7 ) );
				if ( ! preg_match( '/^[A-Za-z]:[\\\\\\/]/', $git ) ) {
					$git = realpath( SABRI_HNF_PATH . $git );
				}
			}
		}

		$head_file = is_dir( $git ) ? $git . DIRECTORY_SEPARATOR . 'HEAD' : '';
		if ( ! $head_file || ! is_readable( $head_file ) ) {
			return $empty;
		}

		$head = trim( (string) file_get_contents( $head_file ) );
		if ( 0 === strpos( $head, 'ref:' ) ) {
			$ref    = trim( substr( $head, 4 ) );
			$branch = basename( $ref );
			$commit = '';
			$ref_file = $git . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $ref );
			if ( is_readable( $ref_file ) ) {
				$commit = trim( (string) file_get_contents( $ref_file ) );
			}

			return array(
				'branch_status' => 'Connected',
				'branch'        => $branch,
				'commit_status' => $commit ? 'Connected' : 'Available but not configured',
				'commit'        => $commit ? substr( $commit, 0, 12 ) : 'Commit file unavailable.',
			);
		}

		return array(
			'branch_status' => 'Connected',
			'branch'        => 'detached',
			'commit_status' => 'Connected',
			'commit'        => substr( $head, 0, 12 ),
		);
	}
}
