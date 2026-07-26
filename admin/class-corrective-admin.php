<?php
/**
 * Administration for the File 21 corrective completion.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers the activation wizard and explicit gate controls. */
final class CorrectiveAdmin {
	const PAGE_SLUG = 'sabri-feed-corrective-wizard';

	/** Register hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
			add_action( 'admin_post_sabri_hnf_corrective_save_components', array( __CLASS__, 'save_components' ) );
			add_action( 'admin_post_sabri_hnf_corrective_save_news_gates', array( __CLASS__, 'save_news_gates' ) );
			add_action( 'admin_notices', array( __CLASS__, 'wizard_notice' ) );
		}
	}

	/** Add the wizard beneath Home & News Feed. */
	public static function menu() {
		if ( ! function_exists( 'add_submenu_page' ) ) {
			return;
		}
		add_submenu_page(
			'sabri-feed-overview',
			__( 'File 21 Activation Wizard', 'sabri-complete-home-news-feed' ),
			__( 'Activation Wizard', 'sabri-complete-home-news-feed' ),
			self::capability(),
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/** Render the wizard view. */
	public static function render() {
		self::require_capability();
		$preview          = CorrectiveActivationWizard::preview();
		$steps            = CorrectiveActivationWizard::steps();
		$component_labels = CorrectiveActivationWizard::component_definitions();
		$gate_definitions = CorrectiveActivationWizard::gate_definitions();
		$view_file        = SABRI_HNF_PATH . 'admin/views/corrective-wizard.php';

		echo '<div class="wrap sabri-feed-admin">';
		echo '<h1>' . esc_html__( 'File 21 Corrective Activation Wizard', 'sabri-complete-home-news-feed' ) . '</h1>';
		if ( is_readable( $view_file ) ) {
			include $view_file;
		}
		echo '</div>';
	}

	/** Save public components. */
	public static function save_components() {
		self::require_action( 'sabri_hnf_corrective_save_components' );
		$input  = isset( $_POST['components'] ) && is_array( $_POST['components'] ) ? wp_unslash( $_POST['components'] ) : array();
		$result = CorrectiveActivationWizard::save_components( $input );
		if ( function_exists( 'update_option' ) ) {
			update_option( 'sabri_hnf_corrective_last_component_report', $result, false );
		}
		self::redirect( 'components_saved=1' );
	}

	/** Save News gates. */
	public static function save_news_gates() {
		self::require_action( 'sabri_hnf_corrective_save_news_gates' );
		$phase4 = isset( $_POST['phase4'] ) && is_array( $_POST['phase4'] ) ? wp_unslash( $_POST['phase4'] ) : array();
		$phase5 = isset( $_POST['phase5'] ) && is_array( $_POST['phase5'] ) ? wp_unslash( $_POST['phase5'] ) : array();
		$result = CorrectiveActivationWizard::save_news_gates( $phase4, $phase5 );
		if ( function_exists( 'update_option' ) ) {
			update_option( 'sabri_hnf_corrective_last_gate_report', $result, false );
		}
		self::redirect( 'gates_saved=1' );
	}

	/** Show a bounded admin reminder until the wizard is reviewed. */
	public static function wizard_notice() {
		if ( ! self::can_manage() || CorrectivePublicSettings::enabled( 'wizard_completed' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( is_object( $screen ) && isset( $screen->id ) && false !== strpos( (string) $screen->id, self::PAGE_SLUG ) ) {
			return;
		}
		$url = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=' . self::PAGE_SLUG ) : '#';
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'File 21 corrective public components remain fail-closed until the Activation Wizard is reviewed.', 'sabri-complete-home-news-feed' ) . ' ';
		echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Open Activation Wizard', 'sabri-complete-home-news-feed' ) . '</a>';
		echo '</p></div>';
	}

	/** Capability. */
	private static function capability() {
		return 'sabri_feed_manage_settings';
	}

	/** Whether the current user can manage. */
	private static function can_manage() {
		return function_exists( 'current_user_can' ) && ( current_user_can( self::capability() ) || current_user_can( 'manage_options' ) );
	}

	/** Require capability. */
	private static function require_capability() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to manage the corrective activation wizard.', 'sabri-complete-home-news-feed' ) );
		}
	}

	/** Require capability and nonce. */
	private static function require_action( $action ) {
		self::require_capability();
		if ( function_exists( 'check_admin_referer' ) ) {
			check_admin_referer( $action );
		}
	}

	/** Redirect back to the wizard. */
	private static function redirect( $query ) {
		$url = function_exists( 'admin_url' ) ? admin_url( 'admin.php?page=' . self::PAGE_SLUG ) : '';
		if ( function_exists( 'add_query_arg' ) ) {
			$url = add_query_arg( wp_parse_args( $query ), $url );
		}
		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( $url );
			exit;
		}
	}
}
