<?php
/**
 * Administration for File 21 corrective and harmonization completion.
 *
 * @package SabriCompleteHomeNewsFeed
 */
namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Registers the activation wizard and explicit non-destructive actions. */
final class CorrectiveAdmin {
	const PAGE_SLUG = 'sabri-feed-corrective-wizard';

	/** Register hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
			add_action( 'admin_post_sabri_hnf_corrective_save_components', array( __CLASS__, 'save_components' ) );
			add_action( 'admin_post_sabri_hnf_corrective_save_news_gates', array( __CLASS__, 'save_news_gates' ) );
			add_action( 'admin_post_sabri_feed_restore_legacy_founder_posts', array( __CLASS__, 'restore_legacy_founder_posts' ) );
			add_action( 'admin_post_sabri_feed_migrate_legacy_publications', array( __CLASS__, 'migrate_legacy_publications' ) );
			add_action( 'admin_post_sabri_feed_rollback_legacy_publications', array( __CLASS__, 'rollback_legacy_publications' ) );
			add_action( 'admin_notices', array( __CLASS__, 'wizard_notice' ) );
		}
	}

	/** Add the wizard beneath Home & News Feed. */
	public static function menu() {
		if ( function_exists( 'add_submenu_page' ) ) {
			add_submenu_page( 'sabri-feed-overview', __( 'File 21 Activation Wizard', 'sabri-complete-home-news-feed' ), __( 'Activation Wizard', 'sabri-complete-home-news-feed' ), self::capability(), self::PAGE_SLUG, array( __CLASS__, 'render' ) );
		}
	}

	/** Render the wizard view. */
	public static function render() {
		self::require_capability();
		$preview = CorrectiveActivationWizard::preview();
		$steps = CorrectiveActivationWizard::steps();
		$component_labels = CorrectiveActivationWizard::component_definitions();
		$gate_definitions = CorrectiveActivationWizard::gate_definitions();
		$view_file = SABRI_HNF_PATH . 'admin/views/corrective-wizard.php';
		echo '<div class="wrap sabri-feed-admin"><h1>' . esc_html__( 'File 21 Comprehensive Activation Wizard', 'sabri-complete-home-news-feed' ) . '</h1>';
		if ( is_readable( $view_file ) ) { include $view_file; }
		echo '</div>';
	}

	/** Save public components. */
	public static function save_components() {
		self::require_action( 'sabri_hnf_corrective_save_components' );
		$input = isset( $_POST['components'] ) && is_array( $_POST['components'] ) ? wp_unslash( $_POST['components'] ) : array();
		$result = CorrectiveActivationWizard::save_components( $input );
		if ( function_exists( 'update_option' ) ) { update_option( 'sabri_hnf_corrective_last_component_report', $result, false ); }
		self::redirect_wizard( array( 'components_saved' => 1 ) );
	}

	/** Save News gates. */
	public static function save_news_gates() {
		self::require_action( 'sabri_hnf_corrective_save_news_gates' );
		$phase4 = isset( $_POST['phase4'] ) && is_array( $_POST['phase4'] ) ? wp_unslash( $_POST['phase4'] ) : array();
		$phase5 = isset( $_POST['phase5'] ) && is_array( $_POST['phase5'] ) ? wp_unslash( $_POST['phase5'] ) : array();
		$result = CorrectiveActivationWizard::save_news_gates( $phase4, $phase5 );
		if ( function_exists( 'update_option' ) ) { update_option( 'sabri_hnf_corrective_last_gate_report', $result, false ); }
		self::redirect_wizard( array( 'gates_saved' => 1 ) );
	}

	/** Restore selected Founder/Admin posts. */
	public static function restore_legacy_founder_posts() {
		self::require_migration_action( 'sabri_feed_restore_legacy_founder_posts' );
		$post_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] ) ? wp_unslash( $_POST['post_ids'] ) : array();
		$result = LegacyFounderPostMigration::restore_selected( $post_ids, get_current_user_id() );
		self::redirect_migration( self::report_notice( $result, 'legacy_founder_restored', 'legacy_founder_partial', 'legacy_founder_failed' ) );
	}

	/** Migrate only selected File 04 publications. */
	public static function migrate_legacy_publications() {
		self::require_migration_action( 'sabri_feed_migrate_legacy_publications' );
		$legacy_ids = isset( $_POST['legacy_ids'] ) && is_array( $_POST['legacy_ids'] ) ? wp_unslash( $_POST['legacy_ids'] ) : array();
		$target = isset( $_POST['target'] ) ? sanitize_key( wp_unslash( $_POST['target'] ) ) : 'auto';
		$target = in_array( $target, array( 'auto', 'post', 'sabri_news' ), true ) ? $target : 'auto';
		$copy_comments = isset( $_POST['copy_comments'] ) && '1' === (string) wp_unslash( $_POST['copy_comments'] );
		$result = LegacyPublicationMigration::migrate_selected( $legacy_ids, get_current_user_id(), array( 'target' => $target, 'copy_comments' => $copy_comments ) );
		self::redirect_migration( self::report_notice( $result, 'legacy_publications_migrated', 'legacy_publications_partial', 'legacy_publications_failed' ) );
	}

	/** Roll back selected mappings without deleting source or target. */
	public static function rollback_legacy_publications() {
		self::require_migration_action( 'sabri_feed_rollback_legacy_publications' );
		$legacy_ids = isset( $_POST['legacy_ids'] ) && is_array( $_POST['legacy_ids'] ) ? wp_unslash( $_POST['legacy_ids'] ) : array();
		$result = LegacyPublicationRollback::rollback_selected( $legacy_ids, get_current_user_id() );
		self::redirect_migration( self::report_notice( $result, 'legacy_publications_rolled_back', 'legacy_publications_rollback_partial', 'legacy_publications_rollback_failed' ) );
	}

	/** Show a bounded reminder until the wizard is reviewed. */
	public static function wizard_notice() {
		if ( ! self::can_manage() || CorrectivePublicSettings::enabled( 'wizard_completed' ) ) { return; }
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( is_object( $screen ) && isset( $screen->id ) && false !== strpos( (string) $screen->id, self::PAGE_SLUG ) ) { return; }
		$url = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=' . self::PAGE_SLUG ) : '#';
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'File 21 public components remain fail-closed until the comprehensive Activation Wizard is reviewed.', 'sabri-complete-home-news-feed' ) . ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open Activation Wizard', 'sabri-complete-home-news-feed' ) . '</a></p></div>';
	}

	private static function capability() { return 'sabri_feed_manage_settings'; }
	private static function can_manage() { return function_exists( 'current_user_can' ) && ( current_user_can( self::capability() ) || current_user_can( 'manage_options' ) ); }
	private static function require_capability() { if ( ! self::can_manage() ) { wp_die( esc_html__( 'You do not have permission to manage File 21.', 'sabri-complete-home-news-feed' ) ); } }
	private static function require_action( $action ) { self::require_capability(); if ( function_exists( 'check_admin_referer' ) ) { check_admin_referer( $action ); } }
	private static function require_migration_action( $action ) {
		$can = function_exists( 'current_user_can' ) && ( current_user_can( 'manage_options' ) || current_user_can( 'sabri_feed_run_migrations' ) );
		if ( ! $can ) { wp_die( esc_html__( 'You do not have permission to run migrations.', 'sabri-complete-home-news-feed' ) ); }
		if ( function_exists( 'check_admin_referer' ) ) { check_admin_referer( $action ); }
	}
	private static function report_notice( array $result, $success, $partial, $failure ) { return ! empty( $result['success'] ) ? $success : ( ! empty( $result['partial'] ) ? $partial : $failure ); }
	private static function redirect_wizard( array $query ) {
		$url = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=' . self::PAGE_SLUG ) : '';
		if ( function_exists( 'add_query_arg' ) ) { $url = add_query_arg( $query, $url ); }
		if ( function_exists( 'wp_safe_redirect' ) ) { wp_safe_redirect( $url ); exit; }
	}
	private static function redirect_migration( $notice ) {
		$url = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=sabri-feed-migration' ) : '';
		if ( function_exists( 'add_query_arg' ) ) { $url = add_query_arg( array( 'sabri_notice' => sanitize_key( $notice ) ), $url ); }
		if ( function_exists( 'wp_safe_redirect' ) ) { wp_safe_redirect( $url ); exit; }
	}
}