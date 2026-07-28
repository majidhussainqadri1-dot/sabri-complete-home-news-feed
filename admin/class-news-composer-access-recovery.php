<?php
/**
 * Administrator-safe recovery for the Editorial News Composer entry point.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reconciles plugin-owned Editorial News capabilities after a ZIP replacement
 * and keeps an explicit Composer entry point visible to the site administrator.
 */
final class NewsComposerAccessRecovery {
	const POLICY_VERSION_OPTION = 'sabri_hnf_news_composer_access_policy';
	const POLICY_VERSION = '1.0.3-news-composer-access-v1';

	/** Register administration-only recovery hooks. */
	public static function register() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'admin_init', array( __CLASS__, 'maybe_reconcile_capabilities' ), 5 );
		add_action( 'admin_menu', array( __CLASS__, 'ensure_composer_submenu' ), 99 );
		add_action( 'admin_notices', array( __CLASS__, 'render_newsroom_create_action' ), 20 );
	}

	/** Reapply only plugin-owned role capabilities after replacement/update. */
	public static function maybe_reconcile_capabilities() {
		if ( ! self::is_site_administrator() || ! function_exists( 'get_option' ) ) {
			return;
		}
		$current = (string) get_option( self::POLICY_VERSION_OPTION, '' );
		$has_create = function_exists( 'current_user_can' ) && current_user_can( 'create_editorial_news' );
		$has_read = function_exists( 'current_user_can' ) && current_user_can( 'read_editorial_news' );
		if ( self::POLICY_VERSION === $current && $has_create && $has_read ) {
			return;
		}
		if ( class_exists( __NAMESPACE__ . '\\NewsCapabilities' ) ) {
			NewsCapabilities::apply_default_policy();
		}
		if ( function_exists( 'update_option' ) ) {
			update_option( self::POLICY_VERSION_OPTION, self::POLICY_VERSION, false );
		}
	}

	/** Ensure the administrator always has a discoverable Composer submenu. */
	public static function ensure_composer_submenu() {
		if ( ! self::is_site_administrator() || ! function_exists( 'add_submenu_page' ) ) {
			return;
		}
		global $submenu;
		$parent = 'sabri-feed-overview';
		$exists = false;
		if ( isset( $submenu[ $parent ] ) && is_array( $submenu[ $parent ] ) ) {
			foreach ( $submenu[ $parent ] as $item ) {
				if ( isset( $item[2] ) && NewsroomAdmin::COMPOSER_PAGE === $item[2] ) {
					$exists = true;
					break;
				}
			}
		}
		if ( $exists ) {
			return;
		}
		add_submenu_page(
			$parent,
			__( 'News Composer', 'sabri-complete-home-news-feed' ),
			__( 'Post Editorial News', 'sabri-complete-home-news-feed' ),
			'manage_options',
			NewsroomAdmin::COMPOSER_PAGE,
			array( __CLASS__, 'render_administrator_composer' )
		);
	}

	/** Reconcile once more before delegating to the canonical Composer screen. */
	public static function render_administrator_composer() {
		self::maybe_reconcile_capabilities();
		NewsroomAdmin::render_composer();
	}

	/** Show a prominent create action on the Editorial Newsroom screen. */
	public static function render_newsroom_create_action() {
		if ( ! self::is_site_administrator() || ! function_exists( 'admin_url' ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? self::clean_slug( wp_unslash( $_GET['page'] ) ) : '';
		if ( NewsroomAdmin::PAGE !== $page ) {
			return;
		}
		$url = add_query_arg( 'page', NewsroomAdmin::COMPOSER_PAGE, admin_url( 'admin.php' ) );
		echo '<div class="notice notice-info sabri-news-composer-access"><p><strong>'
			. esc_html__( 'Editorial News posting is available.', 'sabri-complete-home-news-feed' )
			. '</strong> <a class="button button-primary" href="' . esc_url( $url ) . '">'
			. esc_html__( 'Create Editorial News', 'sabri-complete-home-news-feed' )
			. '</a></p></div>';
	}

	private static function is_site_administrator() {
		return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
	}

	private static function clean_slug( $value ) {
		$value = strtolower( (string) $value );
		$value = preg_replace( '/[^a-z0-9_\-]/', '', $value );
		return is_string( $value ) ? $value : '';
	}
}
