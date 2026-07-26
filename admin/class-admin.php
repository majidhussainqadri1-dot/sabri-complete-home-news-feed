<?php
/**
 * Admin foundation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers administration screens.
 */
final class Admin {
	/**
	 * Menu pages.
	 *
	 * @return array<string,string>
	 */
	public static function pages() {
		return array(
			'overview'           => __( 'Overview', 'sabri-complete-home-news-feed' ),
			'feed-settings'      => __( 'Feed Settings', 'sabri-complete-home-news-feed' ),
			'social-features'    => __( 'Social Features', 'sabri-complete-home-news-feed' ),
			'news-settings'      => __( 'News Settings', 'sabri-complete-home-news-feed' ),
			'composer-settings'  => __( 'Composer', 'sabri-complete-home-news-feed' ),
			'roles-capabilities' => __( 'Roles & Capabilities', 'sabri-complete-home-news-feed' ),
			'integrations'       => __( 'Integrations', 'sabri-complete-home-news-feed' ),
			'system-check'       => __( 'System Check', 'sabri-complete-home-news-feed' ),
			'repair'             => __( 'Repair', 'sabri-complete-home-news-feed' ),
			'migration'          => __( 'Migration', 'sabri-complete-home-news-feed' ),
			'rollback'           => __( 'Rollback', 'sabri-complete-home-news-feed' ),
			'help'               => __( 'Help', 'sabri-complete-home-news-feed' ),
		);
	}

	/** Register hooks. */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_sabri_feed_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_sabri_feed_save_phase3_features', array( __CLASS__, 'handle_save_phase3_features' ) );
		add_action( 'admin_post_sabri_feed_emergency', array( __CLASS__, 'handle_emergency' ) );
		add_action( 'admin_post_sabri_feed_repair', array( __CLASS__, 'handle_repair' ) );
		add_action( 'admin_post_sabri_feed_migration', array( __CLASS__, 'handle_migration' ) );
		add_action( 'admin_post_sabri_feed_restore_legacy_founder_posts', array( __CLASS__, 'handle_restore_legacy_founder_posts' ) );
		add_action( 'admin_post_sabri_feed_rollback', array( __CLASS__, 'handle_rollback' ) );
	}

	/** Register admin menu. */
	public static function menu() {
		$capability = self::capability();

		add_menu_page(
			__( 'Home & News Feed', 'sabri-complete-home-news-feed' ),
			__( 'Home & News Feed', 'sabri-complete-home-news-feed' ),
			$capability,
			'sabri-feed-overview',
			array( __CLASS__, 'render_overview' ),
			'dashicons-megaphone',
			58
		);

		foreach ( self::pages() as $slug => $title ) {
			$page_slug = 'overview' === $slug ? 'sabri-feed-overview' : 'sabri-feed-' . $slug;
			add_submenu_page(
				'sabri-feed-overview',
				$title,
				$title,
				$capability,
				$page_slug,
				array( __CLASS__, 'render_' . str_replace( '-', '_', $slug ) )
			);
		}

		add_submenu_page(
			'sabri-feed-overview',
			__( 'Staging Preview', 'sabri-complete-home-news-feed' ),
			__( 'Staging Preview', 'sabri-complete-home-news-feed' ),
			'manage_options',
			'sabri-feed-staging-preview',
			array( __CLASS__, 'render_staging_preview' )
		);
	}

	/** Render callbacks. */
	public static function render_overview() { self::render( 'overview' ); }
	public static function render_feed_settings() { self::render( 'feed-settings' ); }
	public static function render_social_features() { self::render( 'social-features' ); }
	public static function render_news_settings() { self::render( 'news-settings' ); }
	public static function render_composer_settings() { self::render( 'composer-settings' ); }
	public static function render_roles_capabilities() { self::render( 'roles-capabilities' ); }
	public static function render_integrations() { self::render( 'integrations' ); }
	public static function render_system_check() { self::render( 'system-check' ); }
	public static function render_repair() { self::render( 'repair' ); }
	public static function render_migration() { self::render( 'migration' ); }
	public static function render_rollback() { self::render( 'rollback' ); }
	public static function render_help() { self::render( 'help' ); }

	/** Render the administrator-only staging preview. */
	public static function render_staging_preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access the staging preview.', 'sabri-complete-home-news-feed' ) );
		}
		self::render( 'staging-preview' );
	}

	/** Handle Phase 2 settings save. */
	public static function handle_save_settings() {
		self::require_admin_action( 'sabri_feed_save_settings' );
		$tab   = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
		$input = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		Settings::update_tab( $tab, $input );
		AuditLog::record( 'settings_update', array( 'tab' => $tab ) );
		self::redirect( 'sabri-feed-' . self::page_from_tab( $tab ), 'updated=1' );
	}

	/** Save the complete isolated Phase 3 checkbox state. */
	public static function handle_save_phase3_features() {
		self::require_admin_action( 'sabri_feed_save_phase3_features' );
		$input   = isset( $_POST['features'] ) ? wp_unslash( $_POST['features'] ) : array();
		$updated = Phase3FeatureSettings::update_from_admin( $input );
		$enabled = array_keys( array_filter( $updated ) );
		AuditLog::record(
			'phase3_feature_settings_update',
			array(
				'enabled_features' => $enabled,
				'enabled_count'    => count( $enabled ),
			)
		);
		self::redirect( 'sabri-feed-social-features', 'updated=1' );
	}

	/** Handle emergency enable/disable. */
	public static function handle_emergency() {
		self::require_admin_action( 'sabri_feed_emergency' );
		$state = isset( $_POST['state'] ) ? sanitize_key( wp_unslash( $_POST['state'] ) ) : 'disable';
		SafeMode::set_emergency_disabled( 'enable' !== $state );
		self::redirect( 'sabri-feed-overview', 'emergency=1' );
	}

	/** Handle repair action. */
	public static function handle_repair() {
		self::require_admin_action( 'sabri_feed_repair' );
		$action  = isset( $_POST['repair_action'] ) ? sanitize_key( wp_unslash( $_POST['repair_action'] ) ) : '';
		$confirm = ! empty( $_POST['confirm_repair'] );
		$result  = $confirm ? Repair::execute( $action ) : array( 'error' => 'confirmation_required' );
		update_option( 'sabri_feed_last_repair_report', $result, false );
		self::redirect( 'sabri-feed-repair', 'repaired=1' );
	}

	/** Handle migration action. */
	public static function handle_migration() {
		self::require_admin_action( 'sabri_feed_migration' );
		$mode   = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'preview';
		$result = 'execute' === $mode ? Migrations::migrate() : Migrations::preview();
		update_option( 'sabri_feed_last_migration_report', $result, false );
		self::redirect( 'sabri-feed-migration', 'migration=1' );
	}

	/** Handle selected legacy Founder/Admin post restoration. */
	public static function handle_restore_legacy_founder_posts() {
		self::require_admin_action( 'sabri_feed_restore_legacy_founder_posts' );
		$post_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] ) ? wp_unslash( $_POST['post_ids'] ) : array();
		$result   = LegacyFounderPostMigration::restore_selected( $post_ids );
		update_option( LegacyFounderPostMigration::LAST_REPORT_OPTION, $result, false );
		self::redirect( 'sabri-feed-migration', 'legacy_restore=1' );
	}

	/** Handle rollback action. */
	public static function handle_rollback() {
		self::require_admin_action( 'sabri_feed_rollback' );
		$mode   = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'preview';
		$result = 'execute' === $mode ? Rollback::execute() : Rollback::preview();
		update_option( 'sabri_feed_last_rollback_report', $result, false );
		self::redirect( 'sabri-feed-rollback', 'rollback=1' );
	}

	/** Render a view. */
	private static function render( $view ) {
		if ( ! current_user_can( self::capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sabri-complete-home-news-feed' ) );
		}

		$settings = Settings::get();
		$identity = Plugin::identity();
		$view_file = SABRI_HNF_PATH . 'admin/views/' . $view . '.php';

		echo '<div class="wrap sabri-feed-admin">';
		echo '<h1>' . esc_html__( 'Home & News Feed', 'sabri-complete-home-news-feed' ) . '</h1>';
		self::tabs( $view );
		if ( is_readable( $view_file ) ) {
			include $view_file;
		}
		echo '</div>';
	}

	/** Render admin tabs. */
	private static function tabs( $active ) {
		echo '<nav class="nav-tab-wrapper sabri-feed-tabs">';
		foreach ( self::pages() as $slug => $title ) {
			$page  = 'overview' === $slug ? 'sabri-feed-overview' : 'sabri-feed-' . $slug;
			$class = $active === $slug ? ' nav-tab-active' : '';
			echo '<a class="nav-tab' . esc_attr( $class ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $page ) ) . '">' . esc_html( $title ) . '</a>';
		}
		echo '</nav>';
	}

	/** Require capability and nonce. */
	private static function require_admin_action( $action ) {
		if ( ! current_user_can( self::capability() ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'sabri-complete-home-news-feed' ) );
		}
		check_admin_referer( $action );
	}

	/** Redirect safely. */
	private static function redirect( $page, $query = '' ) {
		$url = admin_url( 'admin.php?page=' . sanitize_key( $page ) );
		if ( $query ) {
			$url = add_query_arg( wp_parse_args( $query ), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/** Map settings tab to page slug. */
	private static function page_from_tab( $tab ) {
		$map = array(
			'feed'         => 'feed-settings',
			'news'         => 'news-settings',
			'composer'     => 'composer-settings',
			'capabilities' => 'roles-capabilities',
			'integrations' => 'integrations',
			'advanced'     => 'overview',
		);
		return isset( $map[ $tab ] ) ? $map[ $tab ] : 'overview';
	}

	/** Admin capability. */
	private static function capability() {
		return 'sabri_feed_manage_settings';
	}
}
