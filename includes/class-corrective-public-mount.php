<?php
/**
 * Corrective public mounting and duplicate protection.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Makes File 21 public output observable without replacing the Unified Shell. */
final class CorrectivePublicMount {
	/** Whether this corrective surface rendered in the current request. */
	private static $rendered = false;

	/** Register native, content, loop, shortcode, and block mounting paths. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'mount_on_front_page' ), 8 );
			add_filter( 'pre_do_shortcode_tag', array( __CLASS__, 'intercept_feed_shortcode' ), 8, 4 );
			add_filter( 'render_block', array( __CLASS__, 'intercept_shortcode_block' ), 8, 2 );
			add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
			add_filter( 'sabri_shell_system_check_report', array( __CLASS__, 'append_shell_report' ) );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'loop_start', array( __CLASS__, 'mount_on_posts_index_loop' ), 1 );
		}
	}

	/** Known old/new Feed shortcodes that would create duplicate center output. */
	public static function known_feed_shortcodes() {
		return array( 'sabri_complete_home_feed', 'sabri_news_feed', 'sabri_news_home', 'sabri_platform_home', 'sabri_shell_home_feed' );
	}

	/** Detect a Feed shortcode already present in raw page content. */
	public static function content_feed_shortcode( $content ) {
		$content = is_string( $content ) ? $content : '';
		foreach ( self::known_feed_shortcodes() as $shortcode ) {
			if ( function_exists( 'has_shortcode' ) ? has_shortcode( $content, $shortcode ) : false !== strpos( $content, '[' . $shortcode ) ) {
				return $shortcode;
			}
		}
		return '';
	}

	/** Front-page duplicate and visibility diagnostics. */
	public static function diagnostics() {
		$front_page_id = function_exists( 'get_option' ) && 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		$content       = $front_page_id > 0 && function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_content', $front_page_id ) : '';
		$shortcode     = self::content_feed_shortcode( $content );
		$navigation    = self::navigation_duplicates();
		$replacement   = CorrectivePublicSettings::enabled( 'replace_existing_feed_surface' );
		$home_enabled  = CorrectivePublicSettings::enabled( 'home_surface_enabled' );
		$safe_disabled = class_exists( __NAMESPACE__ . '\\SafeMode' ) ? SafeMode::public_features_disabled() : false;
		$can_mount     = '' === $shortcode || $replacement;
		$effective     = $home_enabled && ! $safe_disabled && $can_mount;
		$reason        = 'ready';
		if ( ! $home_enabled ) {
			$reason = 'home_surface_disabled';
		} elseif ( $safe_disabled ) {
			$reason = 'safe_or_emergency_mode';
		} elseif ( ! $can_mount ) {
			$reason = 'legacy_feed_blocked_by_duplicate_guard';
		}

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
			'navigation_duplicate_keys'   => $navigation,
			'navigation_conflict'         => ! empty( $navigation ),
			'plugin_adds_navigation'      => false,
			'preferred_mount'             => 'sabri_shell_home_main',
			'fallback_mounts'             => array( 'the_content', 'pre_do_shortcode_tag', 'render_block', 'loop_start' ),
		);
	}

	/** Whether the read-only Home surface may render in this request. */
	public static function public_mount_allowed() {
		$diagnostics = self::diagnostics();
		return ! empty( $diagnostics['effective_home_surface'] );
	}

	/** Mount exactly one File 21 surface on a static front page. */
	public static function mount_on_front_page( $content ) {
		if ( self::$rendered || ( function_exists( 'is_admin' ) && is_admin() ) ) { return $content; }
		if ( ! function_exists( 'is_front_page' ) || ! is_front_page() || HomeIntegration::is_single_post_request() ) { return $content; }
		if ( function_exists( 'is_home' ) && is_home() ) { return $content; }
		if ( function_exists( 'in_the_loop' ) && ! in_the_loop() ) { return $content; }
		if ( function_exists( 'is_main_query' ) && ! is_main_query() ) { return $content; }

		$raw_content = '';
		$post_id     = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		if ( $post_id > 0 && function_exists( 'get_post_field' ) ) { $raw_content = (string) get_post_field( 'post_content', $post_id ); }
		$existing_shortcode = self::content_feed_shortcode( $raw_content );
		$replacement        = CorrectivePublicSettings::enabled( 'replace_existing_feed_surface' );
		if ( CorrectivePublicSettings::enabled( 'duplicate_feed_guard' ) && '' !== $existing_shortcode && ! $replacement ) { return $content; }

		$surface = self::render_complete_surface( 'corrective_front_page_mount' );
		if ( '' === $surface ) { return $content; }
		if ( '' !== $existing_shortcode && $replacement ) {
			$replaced = self::replace_known_feed_shortcodes( $content, $surface );
			return $replaced !== $content ? $replaced : $content . $surface;
		}
		return $content . $surface;
	}

	/** Intercept direct legacy shortcode execution used by themes or builders. */
	public static function intercept_feed_shortcode( $return, $tag, $attr, $match ) {
		unset( $attr, $match );
		if ( false !== $return || self::$rendered || ! in_array( sanitize_key( $tag ), self::known_feed_shortcodes(), true ) ) { return $return; }
		if ( ! self::is_public_front_context() || ! CorrectivePublicSettings::enabled( 'replace_existing_feed_surface' ) ) { return $return; }
		$surface = self::render_complete_surface( 'direct_shortcode_replacement' );
		return '' !== $surface ? $surface : $return;
	}

	/** Intercept a Shortcode block before a builder bypasses the normal content filter. */
	public static function intercept_shortcode_block( $block_content, $block ) {
		if ( self::$rendered || ! is_array( $block ) || 'core/shortcode' !== ( isset( $block['blockName'] ) ? $block['blockName'] : '' ) ) { return $block_content; }
		$raw = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : (string) $block_content;
		if ( '' === self::content_feed_shortcode( $raw ) || ! self::is_public_front_context() || ! CorrectivePublicSettings::enabled( 'replace_existing_feed_surface' ) ) { return $block_content; }
		$surface = self::render_complete_surface( 'shortcode_block_replacement' );
		return '' !== $surface ? $surface : $block_content;
	}

	/** Render before a posts-index loop when no static Page content is available. */
	public static function mount_on_posts_index_loop( $query ) {
		if ( self::$rendered || ( function_exists( 'is_admin' ) && is_admin() ) ) { return; }
		if ( ! function_exists( 'is_front_page' ) || ! is_front_page() || ! function_exists( 'is_home' ) || ! is_home() ) { return; }
		if ( is_object( $query ) && method_exists( $query, 'is_main_query' ) && ! $query->is_main_query() ) { return; }
		$surface = self::render_complete_surface( 'posts_index_loop' );
		if ( '' !== $surface ) { echo $surface; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** Build the complete identifiable Home surface for Shell or fallback callers. */
	public static function render_complete_surface( $source = 'public' ) {
		if ( self::$rendered ) { return ''; }
		if ( ! self::public_mount_allowed() && class_exists( __NAMESPACE__ . '\\PublicSurfaceRecovery' ) ) {
			PublicSurfaceRecovery::maybe_recover();
		}
		if ( ! self::public_mount_allowed() ) { return ''; }
		$feed = HomeIntegration::render_feed_once( sanitize_key( $source ), array() );
		if ( '' === $feed ) { return ''; }
		$rows = class_exists( __NAMESPACE__ . '\\HomeCompositionRegistry' ) ? HomeCompositionRegistry::render_rows() : '';
		self::$rendered = true;
		self::enqueue_assets();
		return self::surface( $feed, $rows, $source );
	}

	/** Replace the first known Feed shortcode with File 21 and remove duplicates. */
	public static function replace_known_feed_shortcodes( $content, $surface ) {
		$content = is_string( $content ) ? $content : '';
		$surface = is_string( $surface ) ? $surface : '';
		if ( '' === $content || '' === $surface || '' === self::content_feed_shortcode( $content ) ) { return $content; }
		$replacement_count = 0;
		if ( function_exists( 'get_shortcode_regex' ) ) {
			$regex = get_shortcode_regex( self::known_feed_shortcodes() );
			$result = preg_replace_callback(
				'~' . $regex . '~s',
				static function ( $match ) use ( &$replacement_count, $surface ) {
					if ( isset( $match[1], $match[6] ) && '[' === $match[1] && ']' === $match[6] ) { return substr( $match[0], 1, -1 ); }
					$replacement_count++;
					return 1 === $replacement_count ? $surface : '';
				},
				$content
			);
			return is_string( $result ) ? $result : $content;
		}
		$tags = implode( '|', array_map( 'preg_quote', self::known_feed_shortcodes() ) );
		$result = preg_replace_callback(
			'~\[(?:' . $tags . ')(?:\s[^\]]*)?\](?:.*?\[/(?:' . $tags . ')\])?~is',
			static function () use ( &$replacement_count, $surface ) { $replacement_count++; return 1 === $replacement_count ? $surface : ''; },
			$content
		);
		return is_string( $result ) ? $result : $content;
	}

	/** Add observable body markers. */
	public static function body_classes( $classes ) {
		$classes = is_array( $classes ) ? $classes : array();
		if ( CorrectivePublicSettings::enabled( 'home_surface_enabled' ) ) { $classes[] = 'sabri-hnf-corrective-public-enabled'; }
		if ( self::public_mount_allowed() ) { $classes[] = 'sabri-hnf-public-surface-ready'; }
		return array_values( array_unique( $classes ) );
	}

	/** Add duplicate protection and visibility status to Unified Shell diagnostics. */
	public static function append_shell_report( $rows ) {
		if ( ! is_array( $rows ) ) { return $rows; }
		$diagnostics = self::diagnostics();
		$status = 'Available but not configured';
		$detail = __( 'File 21 never inserts primary navigation and renders at most one complete Home surface per request.', 'sabri-complete-home-news-feed' );
		if ( 'safe_or_emergency_mode' === $diagnostics['visibility_reason'] ) {
			$status = 'Disabled';
			$detail = __( 'Safe Mode or Emergency Disable is preventing all File 21 public surfaces.', 'sabri-complete-home-news-feed' );
		} elseif ( 'home_surface_disabled' === $diagnostics['visibility_reason'] ) {
			$status = 'Disabled by settings';
			$detail = __( 'The File 21 Home surface is disabled. Use the Activation Wizard or public-surface recovery action.', 'sabri-complete-home-news-feed' );
		} elseif ( 'legacy_feed_blocked_by_duplicate_guard' === $diagnostics['visibility_reason'] ) {
			$status = 'Blocked by duplicate guard';
			$detail = sprintf( __( 'Existing Feed shortcode detected: %s. Enable controlled replacement or run public-surface recovery.', 'sabri-complete-home-news-feed' ), $diagnostics['existing_feed_shortcode'] );
		} elseif ( ! empty( $diagnostics['feed_conflict'] ) && ! empty( $diagnostics['replacement_enabled'] ) ) {
			$status = 'Enabled with controlled replacement';
			$detail = sprintf( __( 'Existing Feed shortcode %s is replaced only at render time; saved page content is not mutated.', 'sabri-complete-home-news-feed' ), $diagnostics['existing_feed_shortcode'] );
		} elseif ( ! empty( $diagnostics['effective_home_surface'] ) ) {
			$status = 'Enabled';
		}
		$rows[] = array( 'label' => __( 'File 21 public mount', 'sabri-complete-home-news-feed' ), 'status' => $status, 'detail' => $detail );
		return $rows;
	}

	/** Reset request guards for tests. */
	public static function reset_runtime_guards() {
		self::$rendered = false;
		if ( class_exists( __NAMESPACE__ . '\\HomeIntegration' ) ) { HomeIntegration::reset_runtime_guards(); }
		if ( class_exists( __NAMESPACE__ . '\\HomeCompositionRegistry' ) && method_exists( HomeCompositionRegistry::class, 'reset_runtime_guards' ) ) { HomeCompositionRegistry::reset_runtime_guards(); }
	}

	/** Build the identifiable File 21 surface. */
	private static function surface( $feed, $rows, $source ) {
		$marker = CorrectivePublicSettings::enabled( 'distinct_surface_marker' )
			? '<header class="sabri-hnf-corrective-surface__header"><p class="sabri-hnf-corrective-surface__eyebrow">' . esc_html__( 'Sabri Home & News Feed', 'sabri-complete-home-news-feed' ) . '</p><p class="sabri-hnf-corrective-surface__status">' . esc_html__( 'File 21 public surface is active', 'sabri-complete-home-news-feed' ) . '</p></header>'
			: '';
		return '<section class="sabri-hnf-corrective-surface" aria-label="' . esc_attr__( 'Sabri Home and News Feed', 'sabri-complete-home-news-feed' ) . '" data-sabri-hnf-surface="file-21-corrective" data-sabri-hnf-version="' . esc_attr( SABRI_HNF_VERSION ) . '" data-sabri-hnf-mount-source="' . esc_attr( sanitize_key( $source ) ) . '">' . $marker . $feed . $rows . '</section>';
	}

	/** Determine whether a replacement belongs to the public front context. */
	private static function is_public_front_context() {
		if ( function_exists( 'is_admin' ) && is_admin() ) { return false; }
		if ( HomeIntegration::is_single_post_request() ) { return false; }
		return function_exists( 'is_front_page' ) && is_front_page();
	}

	/** Detect duplicate enabled Shell destinations that resolve to the same URL/page. */
	private static function navigation_duplicates() {
		$settings = function_exists( 'get_option' ) ? get_option( 'sabri_shell_settings', array() ) : array();
		$nav = is_array( $settings ) && isset( $settings['navigation'] ) && is_array( $settings['navigation'] ) ? $settings['navigation'] : array();
		$seen = array();
		$dupes = array();
		foreach ( $nav as $key => $row ) {
			if ( ! is_array( $row ) || empty( $row['enabled'] ) ) { continue; }
			$identity = '';
			if ( ! empty( $row['url_override'] ) ) { $identity = 'url:' . strtolower( trim( (string) $row['url_override'] ) ); }
			elseif ( ! empty( $row['page_id'] ) ) { $identity = 'page:' . (int) $row['page_id']; }
			elseif ( ! empty( $row['slug'] ) ) { $identity = 'slug:' . sanitize_key( $row['slug'] ); }
			if ( '' === $identity ) { continue; }
			if ( isset( $seen[ $identity ] ) ) { $dupes[] = $seen[ $identity ] . '+' . sanitize_key( $key ); }
			else { $seen[ $identity ] = sanitize_key( $key ); }
		}
		return array_values( array_unique( $dupes ) );
	}

	/** Enqueue the corrective public marker stylesheet. */
	private static function enqueue_assets() {
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'sabri-hnf-corrective-public', SABRI_HNF_URL . 'assets/css/corrective-public.css', array( 'sabri-hnf-home-composition' ), SABRI_HNF_VERSION );
		}
	}
}
