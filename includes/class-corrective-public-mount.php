<?php
/**
 * Corrective public Home/News mounting and duplicate protection.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Makes File 21 public output observable without taking over global navigation. */
final class CorrectivePublicMount {
	/** @var bool */
	private static $home_rendered = false;
	/** @var bool */
	private static $news_rendered = false;

	/** Register native, content, loop, shortcode, and block mounting paths. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'mount_on_public_page' ), 8 );
			add_filter( 'pre_do_shortcode_tag', array( __CLASS__, 'intercept_surface_shortcode' ), 8, 4 );
			add_filter( 'render_block', array( __CLASS__, 'intercept_shortcode_block' ), 8, 2 );
			add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
			add_filter( 'sabri_shell_system_check_report', array( __CLASS__, 'append_shell_report' ) );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'loop_start', array( __CLASS__, 'mount_on_posts_index_loop' ), 1 );
		}
	}

	/** Known Home/legacy Feed shortcodes. */
	public static function known_feed_shortcodes() {
		return array( 'sabri_complete_home_feed', 'sabri_news_feed', 'sabri_news_home', 'sabri_platform_home', 'sabri_shell_home_feed' );
	}

	/** Known File 04 public News shortcodes. */
	public static function known_news_shortcodes() {
		return array( 'sabri_news_home', 'sabri_news_feed' );
	}

	/** Detect the first known shortcode in raw content. */
	public static function content_surface_shortcode( $content, array $tags = array() ) {
		$content = is_string( $content ) ? $content : '';
		$tags    = $tags ? $tags : self::known_feed_shortcodes();
		foreach ( $tags as $shortcode ) {
			if ( function_exists( 'has_shortcode' ) ? has_shortcode( $content, $shortcode ) : false !== strpos( $content, '[' . $shortcode ) ) {
				return $shortcode;
			}
		}
		return '';
	}

	/** Backward-compatible Home shortcode detector. */
	public static function content_feed_shortcode( $content ) {
		return self::content_surface_shortcode( $content, self::known_feed_shortcodes() );
	}

	/** Public Home/News diagnostics without mutating options or content. */
	public static function diagnostics() {
		$front_page_id = function_exists( 'get_option' ) && 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		$content       = $front_page_id > 0 && function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_content', $front_page_id ) : '';
		$shortcode     = self::content_feed_shortcode( $content );
		$replacement   = CorrectivePublicSettings::enabled( 'replace_existing_feed_surface' );
		$home_enabled  = CorrectivePublicSettings::enabled( 'home_surface_enabled' );
		$safe_disabled = class_exists( __NAMESPACE__ . '\\SafeMode' ) ? SafeMode::public_features_disabled() : false;
		$can_mount     = '' === $shortcode || $replacement;
		$effective     = $home_enabled && ! $safe_disabled && $can_mount;
		$reason        = 'ready';
		if ( ! $home_enabled ) { $reason = 'home_surface_disabled'; }
		elseif ( $safe_disabled ) { $reason = 'safe_or_emergency_mode'; }
		elseif ( ! $can_mount ) { $reason = 'legacy_feed_blocked_by_duplicate_guard'; }
		$slot_audit = class_exists( __NAMESPACE__ . '\\Integrations' ) ? Integrations::shell_slot_audit() : array( 'complete' => false, 'missing' => array( 'sabri_shell_home_before_main', 'sabri_shell_home_main', 'sabri_shell_home_after_main', 'sabri_shell_home_right_sidebar', 'sabri_shell_news_main' ) );

		return array(
			'front_page_id'               => $front_page_id,
			'existing_feed_shortcode'     => $shortcode,
			'feed_conflict'               => '' !== $shortcode,
			'replacement_enabled'         => $replacement,
			'home_surface_enabled'        => $home_enabled,
			'profile_timeline_enabled'    => CorrectivePublicSettings::enabled( 'profile_timeline_enabled' ),
			'can_mount_without_duplicate' => $can_mount,
			'effective_home_surface'      => $effective,
			'visibility_reason'           => $reason,
			'news_public_reads_enabled'   => class_exists( __NAMESPACE__ . '\\NewsPolicy' ) && NewsPolicy::public_reads_allowed(),
			'news_route'                  => function_exists( 'home_url' ) ? home_url( '/news/' ) : '/news/',
			'native_shell_slots_ready'    => ! empty( $slot_audit['complete'] ),
			'missing_shell_slots'         => isset( $slot_audit['missing'] ) ? $slot_audit['missing'] : array(),
			'navigation_duplicate_keys'   => self::navigation_duplicates(),
			'plugin_adds_navigation'      => false,
			'preferred_mounts'            => array( 'sabri_shell_home_main', 'sabri_shell_news_main' ),
			'fallback_mounts'             => array( 'the_content', 'pre_do_shortcode_tag', 'render_block', 'loop_start' ),
		);
	}

	/** Whether the read-only Home surface may render. */
	public static function public_mount_allowed() {
		$diagnostics = self::diagnostics();
		return ! empty( $diagnostics['effective_home_surface'] );
	}

	/** Whether Editorial News may render. */
	public static function news_mount_allowed() {
		if ( class_exists( __NAMESPACE__ . '\\SafeMode' ) && SafeMode::public_features_disabled() ) { return false; }
		return class_exists( __NAMESPACE__ . '\\NewsPolicy' ) && NewsPolicy::public_reads_allowed();
	}

	/** Mount exactly one Home or News surface on a public static Page. */
	public static function mount_on_public_page( $content ) {
		if ( ( function_exists( 'is_admin' ) && is_admin() ) || ! self::main_content_context() ) { return $content; }
		if ( self::is_public_home_context() && ! self::$home_rendered ) {
			$surface = self::render_complete_surface( 'corrective_front_page_mount' );
			if ( '' === $surface ) { return $content; }
			$tags = self::known_feed_shortcodes();
			return '' !== self::content_surface_shortcode( self::raw_current_content(), $tags )
				? self::replace_known_shortcodes( $content, $surface, $tags )
				: $content . $surface;
		}
		if ( self::is_public_news_context() && ! self::$news_rendered && self::news_mount_allowed() ) {
			$surface = self::render_news_surface( 'corrective_news_page_mount' );
			if ( '' === $surface ) { return $content; }
			$tags = self::known_news_shortcodes();
			return '' !== self::content_surface_shortcode( self::raw_current_content(), $tags )
				? self::replace_known_shortcodes( $content, $surface, $tags )
				: $surface . $content;
		}
		return $content;
	}

	/** Backward-compatible Home mount method. */
	public static function mount_on_front_page( $content ) { return self::mount_on_public_page( $content ); }

	/** Intercept legacy shortcode execution used by themes/builders. */
	public static function intercept_surface_shortcode( $return, $tag, $attr, $match ) {
		unset( $attr, $match );
		$tag = function_exists( 'sanitize_key' ) ? sanitize_key( $tag ) : (string) $tag;
		if ( false !== $return || ! in_array( $tag, self::known_feed_shortcodes(), true ) || ! CorrectivePublicSettings::enabled( 'replace_existing_feed_surface' ) ) { return $return; }
		if ( self::is_public_news_context() && in_array( $tag, self::known_news_shortcodes(), true ) && self::news_mount_allowed() ) {
			$surface = self::render_news_surface( 'direct_news_shortcode_replacement' );
			return '' !== $surface ? $surface : $return;
		}
		if ( self::is_public_home_context() ) {
			$surface = self::render_complete_surface( 'direct_home_shortcode_replacement' );
			return '' !== $surface ? $surface : $return;
		}
		return $return;
	}

	/** Backward-compatible shortcode filter callback. */
	public static function intercept_feed_shortcode( $return, $tag, $attr, $match ) {
		return self::intercept_surface_shortcode( $return, $tag, $attr, $match );
	}

	/** Intercept a Shortcode block before a builder bypasses normal content filters. */
	public static function intercept_shortcode_block( $block_content, $block ) {
		if ( ! is_array( $block ) || 'core/shortcode' !== ( isset( $block['blockName'] ) ? $block['blockName'] : '' ) ) { return $block_content; }
		$raw = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : (string) $block_content;
		if ( self::is_public_news_context() && '' !== self::content_surface_shortcode( $raw, self::known_news_shortcodes() ) && self::news_mount_allowed() ) {
			$surface = self::render_news_surface( 'news_shortcode_block_replacement' );
			return '' !== $surface ? $surface : $block_content;
		}
		if ( self::is_public_home_context() && '' !== self::content_feed_shortcode( $raw ) ) {
			$surface = self::render_complete_surface( 'home_shortcode_block_replacement' );
			return '' !== $surface ? $surface : $block_content;
		}
		return $block_content;
	}

	/** Render before a posts-index Home loop. */
	public static function mount_on_posts_index_loop( $query ) {
		if ( self::$home_rendered || ! self::is_public_home_context() || ! function_exists( 'is_home' ) || ! is_home() ) { return; }
		if ( is_object( $query ) && method_exists( $query, 'is_main_query' ) && ! $query->is_main_query() ) { return; }
		$surface = self::render_complete_surface( 'posts_index_loop' );
		if ( '' !== $surface ) { echo $surface; }
	}

	/** Build the complete identifiable Home surface. No database recovery runs here. */
	public static function render_complete_surface( $source = 'public' ) {
		if ( self::$home_rendered || ! self::public_mount_allowed() ) { return ''; }
		$feed = HomeIntegration::render_feed_once( self::clean_key( $source ), array() );
		if ( '' === $feed ) { return ''; }
		$rows = class_exists( __NAMESPACE__ . '\\HomeCompositionRegistry' ) ? HomeCompositionRegistry::render_rows() : '';
		self::$home_rendered = true;
		self::enqueue_home_assets();
		return self::home_surface( $feed, $rows, $source );
	}

	/** Build the canonical Editorial News surface for Shell or compatibility pages. */
	public static function render_news_surface( $source = 'public' ) {
		if ( self::$news_rendered || ! self::news_mount_allowed() || ! class_exists( __NAMESPACE__ . '\\NewsPublicRuntime' ) ) { return ''; }
		$context = NewsPublicRuntime::context();
		if ( empty( $context['route'] ) && class_exists( __NAMESPACE__ . '\\NewsQueryService' ) ) {
			$result = NewsQueryService::landing();
			if ( empty( $result['success'] ) ) { return ''; }
			NewsPublicRuntime::set_context( array(
				'route'          => 'landing',
				'result'         => $result,
				'title'          => __( 'News', 'sabri-complete-home-news-feed' ),
				'description'    => '',
				'canonical_base' => function_exists( 'home_url' ) ? home_url( '/news/' ) : '/news/',
			) );
			$context = NewsPublicRuntime::context();
		}
		$body = 'single' === ( isset( $context['route'] ) ? $context['route'] : '' ) ? NewsPublicRuntime::render_single() : NewsPublicRuntime::render_archive();
		if ( '' === $body ) { return ''; }
		self::$news_rendered = true;
		return '<section class="sabri-hnf-news-surface" aria-label="' . esc_attr__( 'Sabri Editorial News', 'sabri-complete-home-news-feed' ) . '" data-sabri-hnf-surface="file-21-news" data-sabri-hnf-version="' . esc_attr( SABRI_HNF_VERSION ) . '" data-sabri-hnf-mount-source="' . esc_attr( self::clean_key( $source ) ) . '">' . $body . '</section>';
	}

	/** Replace first known shortcode and remove additional duplicates. */
	public static function replace_known_shortcodes( $content, $surface, array $tags ) {
		$content = is_string( $content ) ? $content : '';
		$surface = is_string( $surface ) ? $surface : '';
		if ( '' === $content || '' === $surface || '' === self::content_surface_shortcode( $content, $tags ) ) { return $content; }
		$count = 0;
		if ( function_exists( 'get_shortcode_regex' ) ) {
			$regex  = get_shortcode_regex( $tags );
			$result = preg_replace_callback( '~' . $regex . '~s', static function ( $match ) use ( &$count, $surface ) {
				if ( isset( $match[1], $match[6] ) && '[' === $match[1] && ']' === $match[6] ) { return substr( $match[0], 1, -1 ); }
				$count++;
				return 1 === $count ? $surface : '';
			}, $content );
			return is_string( $result ) ? $result : $content;
		}
		$quoted = implode( '|', array_map( 'preg_quote', $tags ) );
		$result = preg_replace_callback( '~\[(?:' . $quoted . ')(?:\s[^\]]*)?\](?:.*?\[/(?:' . $quoted . ')\])?~is', static function () use ( &$count, $surface ) {
			$count++;
			return 1 === $count ? $surface : '';
		}, $content );
		return is_string( $result ) ? $result : $content;
	}

	/** Backward-compatible Home replacement. */
	public static function replace_known_feed_shortcodes( $content, $surface ) {
		return self::replace_known_shortcodes( $content, $surface, self::known_feed_shortcodes() );
	}

	/** Observable body markers. */
	public static function body_classes( $classes ) {
		$classes = is_array( $classes ) ? $classes : array();
		if ( CorrectivePublicSettings::enabled( 'home_surface_enabled' ) ) { $classes[] = 'sabri-hnf-corrective-public-enabled'; }
		if ( self::public_mount_allowed() ) { $classes[] = 'sabri-hnf-public-surface-ready'; }
		if ( self::news_mount_allowed() ) { $classes[] = 'sabri-hnf-news-surface-ready'; }
		return array_values( array_unique( $classes ) );
	}

	/** Add duplicate protection and visibility status to Unified Shell diagnostics. */
	public static function append_shell_report( $rows ) {
		if ( ! is_array( $rows ) ) { return $rows; }
		$d = self::diagnostics();
		$status = ! empty( $d['effective_home_surface'] ) ? 'Enabled' : 'Disabled';
		$detail = __( 'File 21 renders at most one Home and one News surface per request and never inserts primary navigation.', 'sabri-complete-home-news-feed' );
		if ( ! empty( $d['feed_conflict'] ) && ! empty( $d['replacement_enabled'] ) ) {
			$status = 'Enabled with controlled replacement';
			$detail .= ' ' . sprintf( __( 'Legacy shortcode %s is replaced only at render time.', 'sabri-complete-home-news-feed' ), $d['existing_feed_shortcode'] );
		}
		$rows[] = array( 'label' => __( 'File 21 public mount', 'sabri-complete-home-news-feed' ), 'status' => $status, 'detail' => $detail );
		$rows[] = array( 'label' => __( 'File 21 Editorial News route', 'sabri-complete-home-news-feed' ), 'status' => $d['news_public_reads_enabled'] ? 'Enabled' : 'Activation required', 'detail' => $d['news_public_reads_enabled'] ? $d['news_route'] : __( 'Run the Activation Wizard and flush rewrite rules after staging acceptance.', 'sabri-complete-home-news-feed' ) );
		return $rows;
	}

	/** Public Home context. */
	public static function is_public_home_context() {
		return self::public_request_allowed() && ! HomeIntegration::is_single_post_request() && function_exists( 'is_front_page' ) && is_front_page();
	}

	/** Public canonical or legacy News context. */
	public static function is_public_news_context() {
		if ( ! self::public_request_allowed() || self::is_public_home_context() ) { return false; }
		if ( class_exists( __NAMESPACE__ . '\\NewsPublicRuntime' ) ) {
			$context = NewsPublicRuntime::context();
			if ( ! empty( $context['route'] ) ) { return true; }
		}
		if ( function_exists( 'get_query_var' ) && ( '1' === (string) get_query_var( NewsRouting::Q_ARCHIVE, '' ) || '' !== (string) get_query_var( NewsRouting::Q_SLUG, '' ) ) ) { return true; }
		if ( function_exists( 'is_singular' ) && class_exists( __NAMESPACE__ . '\\Phase4Contracts' ) && is_singular( Phase4Contracts::POST_TYPE ) ) { return true; }
		if ( function_exists( 'is_post_type_archive' ) && class_exists( __NAMESPACE__ . '\\Phase4Contracts' ) && is_post_type_archive( Phase4Contracts::POST_TYPE ) ) { return true; }
		if ( function_exists( 'is_page' ) && is_page( array( 'news', 'sabri-news', 'blog' ) ) ) { return true; }
		$path = self::request_path();
		return in_array( $path, array( '/news/', '/sabri-news/', '/blog/' ), true );
	}

	/** Reset request guards for tests. */
	public static function reset_runtime_guards() {
		self::$home_rendered = false;
		self::$news_rendered = false;
		if ( class_exists( __NAMESPACE__ . '\\HomeIntegration' ) ) { HomeIntegration::reset_runtime_guards(); }
		if ( class_exists( __NAMESPACE__ . '\\HomeCompositionRegistry' ) && method_exists( HomeCompositionRegistry::class, 'reset_runtime_guards' ) ) { HomeCompositionRegistry::reset_runtime_guards(); }
	}

	/** Build identifiable Home surface. */
	private static function home_surface( $feed, $rows, $source ) {
		$marker = CorrectivePublicSettings::enabled( 'distinct_surface_marker' )
			? '<header class="sabri-hnf-corrective-surface__header"><p class="sabri-hnf-corrective-surface__eyebrow">' . esc_html__( 'Sabri Home & News Feed', 'sabri-complete-home-news-feed' ) . '</p><p class="sabri-hnf-corrective-surface__status">' . esc_html__( 'File 21 public surface is active', 'sabri-complete-home-news-feed' ) . '</p></header>'
			: '';
		return '<section class="sabri-hnf-corrective-surface" aria-label="' . esc_attr__( 'Sabri Home and News Feed', 'sabri-complete-home-news-feed' ) . '" data-sabri-hnf-surface="file-21-corrective" data-sabri-hnf-version="' . esc_attr( SABRI_HNF_VERSION ) . '" data-sabri-hnf-mount-source="' . esc_attr( self::clean_key( $source ) ) . '">' . $marker . $feed . $rows . '</section>';
	}

	/** Guard content filter to the real main loop. */
	private static function main_content_context() {
		if ( HomeIntegration::is_single_post_request() ) { return false; }
		if ( function_exists( 'in_the_loop' ) && ! in_the_loop() ) { return false; }
		if ( function_exists( 'is_main_query' ) && ! is_main_query() ) { return false; }
		return true;
	}

	/** Raw current Page content for shortcode detection. */
	private static function raw_current_content() {
		$post_id = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		return $post_id > 0 && function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_content', $post_id ) : '';
	}

	/** Public request safety boundary. */
	private static function public_request_allowed() {
		if ( function_exists( 'is_admin' ) && is_admin() ) { return false; }
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) { return false; }
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) { return false; }
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return false; }
		if ( function_exists( 'is_feed' ) && is_feed() ) { return false; }
		return true;
	}

	/** Current normalized request path. */
	private static function request_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) && is_scalar( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
		$path = function_exists( 'wp_parse_url' ) ? wp_parse_url( $uri, PHP_URL_PATH ) : parse_url( $uri, PHP_URL_PATH );
		$path = is_string( $path ) ? '/' . trim( $path, '/' ) . '/' : '/';
		return '//' === $path ? '/' : $path;
	}

	/** Duplicate enabled Shell destinations. */
	private static function navigation_duplicates() {
		$settings = function_exists( 'get_option' ) ? get_option( 'sabri_shell_settings', array() ) : array();
		$nav = is_array( $settings ) && isset( $settings['navigation'] ) && is_array( $settings['navigation'] ) ? $settings['navigation'] : array();
		$seen = array(); $dupes = array();
		foreach ( $nav as $key => $row ) {
			if ( ! is_array( $row ) || empty( $row['enabled'] ) ) { continue; }
			$identity = ! empty( $row['url_override'] ) ? 'url:' . strtolower( trim( (string) $row['url_override'] ) ) : ( ! empty( $row['page_id'] ) ? 'page:' . (int) $row['page_id'] : ( ! empty( $row['slug'] ) ? 'slug:' . self::clean_key( $row['slug'] ) : '' ) );
			if ( '' === $identity ) { continue; }
			if ( isset( $seen[ $identity ] ) ) { $dupes[] = $seen[ $identity ] . '+' . self::clean_key( $key ); }
			else { $seen[ $identity ] = self::clean_key( $key ); }
		}
		return array_values( array_unique( $dupes ) );
	}

	/** Enqueue Home corrective stylesheet. */
	private static function enqueue_home_assets() {
		if ( function_exists( 'wp_enqueue_style' ) ) { wp_enqueue_style( 'sabri-hnf-corrective-public', SABRI_HNF_URL . 'assets/css/corrective-public.css', array( 'sabri-hnf-home-composition' ), SABRI_HNF_VERSION ); }
	}

	/** Local controlled key helper. */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}
}
