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

	/** Register hooks. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'mount_on_front_page' ), 8 );
			add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
			add_filter( 'sabri_shell_system_check_report', array( __CLASS__, 'append_shell_report' ) );
		}
	}

	/** Known old/new Feed shortcodes that would create duplicate center output. */
	public static function known_feed_shortcodes() {
		return array(
			'sabri_complete_home_feed',
			'sabri_news_feed',
			'sabri_news_home',
			'sabri_platform_home',
			'sabri_shell_home_feed',
		);
	}

	/** Detect a feed shortcode already present in raw page content. */
	public static function content_feed_shortcode( $content ) {
		$content = is_string( $content ) ? $content : '';
		foreach ( self::known_feed_shortcodes() as $shortcode ) {
			if ( function_exists( 'has_shortcode' ) ? has_shortcode( $content, $shortcode ) : false !== strpos( $content, '[' . $shortcode ) ) {
				return $shortcode;
			}
		}
		return '';
	}

	/** Front-page duplicate diagnostics used by the wizard and System Check. */
	public static function diagnostics() {
		$front_page_id = function_exists( 'get_option' ) && 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		$content       = $front_page_id > 0 && function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_content', $front_page_id ) : '';
		$shortcode     = self::content_feed_shortcode( $content );
		$navigation    = self::navigation_duplicates();

		return array(
			'front_page_id'             => $front_page_id,
			'existing_feed_shortcode'   => $shortcode,
			'feed_conflict'             => '' !== $shortcode,
			'navigation_duplicate_keys' => $navigation,
			'navigation_conflict'       => ! empty( $navigation ),
			'plugin_adds_navigation'    => false,
		);
	}

	/** Never inject a second Feed when an existing Feed contract is detected. */
	public static function mount_on_front_page( $content ) {
		if ( ! CorrectivePublicSettings::enabled( 'home_surface_enabled' ) || SafeMode::public_features_disabled() || self::$rendered ) {
			return $content;
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return $content;
		}
		if ( ! function_exists( 'is_front_page' ) || ! is_front_page() || HomeIntegration::is_single_post_request() ) {
			return $content;
		}
		if ( function_exists( 'in_the_loop' ) && ! in_the_loop() ) {
			return $content;
		}
		if ( function_exists( 'is_main_query' ) && ! is_main_query() ) {
			return $content;
		}

		$raw_content = '';
		$post_id     = function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		if ( $post_id > 0 && function_exists( 'get_post_field' ) ) {
			$raw_content = (string) get_post_field( 'post_content', $post_id );
		}
		if ( CorrectivePublicSettings::enabled( 'duplicate_feed_guard' ) && '' !== self::content_feed_shortcode( $raw_content ) ) {
			return $content;
		}

		self::$rendered = true;
		$feed = HomeIntegration::render_feed_once( 'corrective_front_page_mount', array() );
		if ( '' === $feed ) {
			return $content;
		}

		$marker = CorrectivePublicSettings::enabled( 'distinct_surface_marker' )
			? '<p class="sabri-hnf-corrective-surface__eyebrow">' . esc_html__( 'Sabri Home & News Feed', 'sabri-complete-home-news-feed' ) . '</p>'
			: '';
		$surface = '<section class="sabri-hnf-corrective-surface" data-sabri-hnf-surface="file-21-corrective" data-sabri-hnf-version="1.0.1">'
			. $marker
			. $feed
			. '</section>';

		self::enqueue_assets();
		return $content . $surface;
	}

	/** Add an observable body marker only when the corrective surface is enabled. */
	public static function body_classes( $classes ) {
		$classes = is_array( $classes ) ? $classes : array();
		if ( CorrectivePublicSettings::enabled( 'home_surface_enabled' ) ) {
			$classes[] = 'sabri-hnf-corrective-public-enabled';
		}
		return array_values( array_unique( $classes ) );
	}

	/** Add duplicate protection status to Unified Shell diagnostics. */
	public static function append_shell_report( $rows ) {
		if ( ! is_array( $rows ) ) {
			return $rows;
		}
		$diagnostics = self::diagnostics();
		$rows[] = array(
			'label'  => __( 'File 21 public mount', 'sabri-complete-home-news-feed' ),
			'status' => CorrectivePublicSettings::enabled( 'home_surface_enabled' ) ? ( $diagnostics['feed_conflict'] ? 'Blocked by duplicate guard' : 'Enabled' ) : 'Available but not configured',
			'detail' => $diagnostics['feed_conflict']
				? sprintf( __( 'Existing Feed shortcode detected: %s. Corrective auto-mount remains blocked.', 'sabri-complete-home-news-feed' ), $diagnostics['existing_feed_shortcode'] )
				: __( 'File 21 never inserts primary navigation and renders at most one corrective Feed surface per request.', 'sabri-complete-home-news-feed' ),
		);
		return $rows;
	}

	/** Reset request guards for tests. */
	public static function reset_runtime_guards() {
		self::$rendered = false;
	}

	/** Detect duplicate enabled Shell destinations that resolve to the same URL/page. */
	private static function navigation_duplicates() {
		$settings = function_exists( 'get_option' ) ? get_option( 'sabri_shell_settings', array() ) : array();
		$nav      = is_array( $settings ) && isset( $settings['navigation'] ) && is_array( $settings['navigation'] ) ? $settings['navigation'] : array();
		$seen     = array();
		$dupes    = array();
		foreach ( $nav as $key => $row ) {
			if ( ! is_array( $row ) || empty( $row['enabled'] ) ) {
				continue;
			}
			$identity = '';
			if ( ! empty( $row['url_override'] ) ) {
				$identity = 'url:' . strtolower( trim( (string) $row['url_override'] ) );
			} elseif ( ! empty( $row['page_id'] ) ) {
				$identity = 'page:' . (int) $row['page_id'];
			} elseif ( ! empty( $row['slug'] ) ) {
				$identity = 'slug:' . sanitize_key( $row['slug'] );
			}
			if ( '' === $identity ) {
				continue;
			}
			if ( isset( $seen[ $identity ] ) ) {
				$dupes[] = $seen[ $identity ] . '+' . sanitize_key( $key );
			} else {
				$seen[ $identity ] = sanitize_key( $key );
			}
		}
		return array_values( array_unique( $dupes ) );
	}

	/** Enqueue the minimal corrective public marker stylesheet. */
	private static function enqueue_assets() {
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'sabri-hnf-corrective-public', SABRI_HNF_URL . 'assets/css/corrective-public.css', array(), SABRI_HNF_VERSION );
		}
	}
}
