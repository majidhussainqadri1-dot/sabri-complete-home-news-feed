<?php
/**
 * Canonical public Composer route and create-post action.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Exposes the enabled social Composer on the public website. */
final class PublicComposerSurface {
	const QUERY_VAR = 'sabri_hnf_create_post';
	const ROUTE_SLUG = 'create-post';
	const REWRITE_POLICY_OPTION = 'sabri_hnf_public_composer_route_policy';
	const REWRITE_POLICY_VERSION = '1.0.3-public-composer-route-v1';

	/** @var bool Prevent duplicate CTA output across Shell and fallback mounts. */
	private static $button_rendered = false;

	/** Register route, public action, Shell URL and one-shot rewrite recovery. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', array( __CLASS__, 'register_route' ), 15 );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ), 20 );
			add_action( 'template_redirect', array( __CLASS__, 'render_route' ), 1 );
			add_action( 'admin_init', array( __CLASS__, 'schedule_rewrite_recovery' ), 20 );
			add_action( 'sabri_shell_home_before_main', array( __CLASS__, 'render_shell_home_button' ), 5 );
			add_action( 'sabri_shell_news_main', array( __CLASS__, 'render_shell_news_button' ), 5 );
			add_action( 'loop_start', array( __CLASS__, 'render_loop_button' ), 0 );
		}
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
			add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
			add_filter( 'document_title_parts', array( __CLASS__, 'document_title' ) );
			add_filter( 'sabri_shell_create_url', array( __CLASS__, 'filter_shell_create_url' ), 30 );
			add_filter( 'the_content', array( __CLASS__, 'inject_content_button' ), 9 );
		}
	}

	/** Register the canonical virtual route. */
	public static function register_route() {
		if ( function_exists( 'add_rewrite_rule' ) ) {
			add_rewrite_rule( '^' . self::ROUTE_SLUG . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
		}
	}

	/** Advertise the route query variable. */
	public static function query_vars( $vars ) {
		$vars = is_array( $vars ) ? $vars : array();
		$vars[] = self::QUERY_VAR;
		return array_values( array_unique( $vars ) );
	}

	/** Canonical public Composer destination, with a configured override. */
	public static function canonical_url() {
		$fallback = function_exists( 'home_url' ) ? home_url( '/' . self::ROUTE_SLUG . '/' ) : '/' . self::ROUTE_SLUG . '/';
		$settings = Settings::get();
		$configured = isset( $settings['integrations']['composer_page_url'] ) ? trim( (string) $settings['integrations']['composer_page_url'] ) : '';
		if ( '' === $configured ) {
			return $fallback;
		}
		return function_exists( 'wp_validate_redirect' ) ? wp_validate_redirect( $configured, $fallback ) : $configured;
	}

	/** Replace an empty or stale Shell Create destination with the working route. */
	public static function filter_shell_create_url( $url ) {
		return self::composer_enabled() ? self::canonical_url() : $url;
	}

	/** Load styles before wp_head whenever the route or a public action is relevant. */
	public static function maybe_enqueue_assets() {
		if ( ! self::composer_enabled() ) {
			return;
		}
		$relevant = self::is_route_request();
		if ( ! $relevant && class_exists( __NAMESPACE__ . '\CorrectivePublicMount' ) ) {
			$relevant = CorrectivePublicMount::is_public_home_context() || CorrectivePublicMount::is_public_news_context();
		}
		if ( $relevant ) {
			self::enqueue_assets();
		}
	}

	/** Render the public create-post action for Home or News surfaces. */
	public static function render_button( $context = 'home' ) {
		if ( ! self::composer_enabled() ) {
			return '';
		}
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$url = self::canonical_url();
		$label = __( 'Create Post', 'sabri-complete-home-news-feed' );
		$class = 'sabri-hnf-public-composer-cta sabri-hnf-public-composer-cta--' . self::clean_key( $context );

		if ( $user_id > 0 ) {
			if ( ! ComposerPermissions::user_can_create( $user_id, Settings::get() ) ) {
				return '';
			}
		} elseif ( function_exists( 'wp_login_url' ) ) {
			$url = wp_login_url( $url );
			$label = __( 'Sign in to Post', 'sabri-complete-home-news-feed' );
		}

		self::enqueue_assets();
		return '<div class="sabri-hnf-public-composer-action" data-sabri-hnf-public-composer-action="1"><a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '"><span aria-hidden="true">＋</span> ' . esc_html( $label ) . '</a></div>';
	}

	/** Render the CTA before the native Shell Home surface. */
	public static function render_shell_home_button() {
		self::echo_button_once( 'home' );
	}

	/** Render the CTA before the native Shell News surface. */
	public static function render_shell_news_button() {
		self::echo_button_once( 'news' );
	}

	/** Render before posts-index fallback output. */
	public static function render_loop_button( $query ) {
		if ( self::$button_rendered || self::is_route_request() || ( function_exists( 'is_admin' ) && is_admin() ) ) {
			return;
		}
		if ( is_object( $query ) && method_exists( $query, 'is_main_query' ) && ! $query->is_main_query() ) {
			return;
		}
		if ( class_exists( __NAMESPACE__ . '\CorrectivePublicMount' ) && CorrectivePublicMount::is_public_home_context() && function_exists( 'is_home' ) && is_home() ) {
			self::echo_button_once( 'home' );
		}
	}

	/** Inject the CTA on static public Home and News Pages only. */
	public static function inject_content_button( $content ) {
		if ( self::$button_rendered || self::is_route_request() || ( function_exists( 'is_admin' ) && is_admin() ) ) {
			return $content;
		}
		if ( function_exists( 'in_the_loop' ) && ! in_the_loop() ) {
			return $content;
		}
		if ( function_exists( 'is_main_query' ) && ! is_main_query() ) {
			return $content;
		}
		$current_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		$queried_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		if ( $queried_id > 0 && $current_id !== $queried_id ) {
			return $content;
		}

		$context = '';
		if ( class_exists( __NAMESPACE__ . '\CorrectivePublicMount' ) && CorrectivePublicMount::is_public_home_context() ) {
			$context = 'home';
		} elseif ( class_exists( __NAMESPACE__ . '\CorrectivePublicMount' ) && CorrectivePublicMount::is_public_news_context() ) {
			$context = 'news';
		}
		if ( '' === $context ) {
			return $content;
		}

		$button = self::render_button( $context );
		if ( '' === $button ) {
			return $content;
		}
		self::$button_rendered = true;
		return $button . $content;
	}

	/** Render the canonical public Composer page. */
	public static function render_route() {
		if ( ! self::is_route_request() ) {
			return;
		}

		global $wp_query;
		if ( is_object( $wp_query ) ) {
			$wp_query->is_404 = false;
		}
		if ( function_exists( 'status_header' ) ) {
			status_header( 200 );
		}
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		if ( $user_id <= 0 && function_exists( 'wp_safe_redirect' ) && function_exists( 'wp_login_url' ) ) {
			wp_safe_redirect( wp_login_url( self::canonical_url() ) );
			exit;
		}

		self::enqueue_assets();
		$composer_html = Composer::render();
		if ( function_exists( 'get_header' ) ) {
			get_header();
		}

		echo '<main id="sabri-hnf-public-composer-page" class="sabri-hnf-public-composer-page" data-sabri-hnf-surface="public-composer">';
		echo '<div class="sabri-hnf-public-composer-page__inner">';
		echo '<header class="sabri-hnf-public-composer-page__header"><h1>' . esc_html__( 'Create a Post', 'sabri-complete-home-news-feed' ) . '</h1><p>' . esc_html__( 'Publish an approved Home Feed post from the public website.', 'sabri-complete-home-news-feed' ) . '</p></header>';
		echo $composer_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div></main>';

		if ( function_exists( 'get_footer' ) ) {
			get_footer();
		}
		exit;
	}

	/** Recognize both the rewrite query and exact request path. */
	public static function is_route_request() {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return false;
		}
		if ( function_exists( 'get_query_var' ) && '1' === (string) get_query_var( self::QUERY_VAR, '' ) ) {
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) && is_scalar( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$path = function_exists( 'wp_parse_url' ) ? wp_parse_url( $uri, PHP_URL_PATH ) : parse_url( $uri, PHP_URL_PATH );
		$path = is_string( $path ) ? '/' . trim( $path, '/' ) . '/' : '/';
		return '/' . self::ROUTE_SLUG . '/' === $path;
	}

	/** Add an observable body marker on the virtual Composer page. */
	public static function body_classes( $classes ) {
		$classes = is_array( $classes ) ? $classes : array();
		if ( self::is_route_request() ) {
			$classes[] = 'sabri-hnf-public-composer-page-active';
		}
		return array_values( array_unique( $classes ) );
	}

	/** Set a precise browser title for the virtual route. */
	public static function document_title( $parts ) {
		$parts = is_array( $parts ) ? $parts : array();
		if ( self::is_route_request() ) {
			$parts['title'] = __( 'Create a Post', 'sabri-complete-home-news-feed' );
		}
		return $parts;
	}

	/** Schedule one rewrite refresh after ZIP replacement without public writes. */
	public static function schedule_rewrite_recovery() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) || ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}
		if ( self::REWRITE_POLICY_VERSION === (string) get_option( self::REWRITE_POLICY_OPTION, '' ) ) {
			return;
		}
		update_option( self::REWRITE_POLICY_OPTION, self::REWRITE_POLICY_VERSION, false );
		update_option( RewriteRules::FLUSH_OPTION, 1, false );
	}

	/** Output one escaped CTA and consume the request guard only on success. */
	private static function echo_button_once( $context ) {
		if ( self::$button_rendered ) {
			return;
		}
		$button = self::render_button( $context );
		if ( '' === $button ) {
			return;
		}
		self::$button_rendered = true;
		echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** Enqueue the route and CTA stylesheet. */
	private static function enqueue_assets() {
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'sabri-hnf-public-composer-surface', SABRI_HNF_URL . 'assets/css/public-composer-surface.css', array(), SABRI_HNF_VERSION );
		}
	}

	/** Whether the public social Composer is operational. */
	private static function composer_enabled() {
		$settings = Settings::get();
		return ! empty( $settings['composer']['public_composer_enabled'] ) && ! SafeMode::public_features_disabled();
	}

	/** Controlled CSS/context key. */
	private static function clean_key( $value ) {
		$value = strtolower( (string) $value );
		$value = preg_replace( '/[^a-z0-9_\-]/', '', $value );
		return is_string( $value ) && '' !== $value ? $value : 'public';
	}
}
