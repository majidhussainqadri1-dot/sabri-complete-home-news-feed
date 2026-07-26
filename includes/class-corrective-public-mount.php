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
		$replacement   = CorrectivePublicSettings::enabled( 'replace_existing_feed_surface' );

		return array(
			'front_page_id'             => $front_page_id,
			'existing_feed_shortcode'   => $shortcode,
			'feed_conflict'             => '' !== $shortcode,
			'replacement_enabled'       => $replacement,
			'can_mount_without_duplicate' => '' === $shortcode || $replacement,
			'navigation_duplicate_keys' => $navigation,
			'navigation_conflict'       => ! empty( $navigation ),
			'plugin_adds_navigation'    => false,
		);
	}

	/**
	 * Mount exactly one File 21 Feed surface.
	 *
	 * If a known legacy/current Feed shortcode exists, explicit replacement mode
	 * substitutes its runtime output without mutating the page in the database.
	 * Additional duplicate Feed shortcode instances are removed from that request.
	 */
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
		$existing_shortcode = self::content_feed_shortcode( $raw_content );
		$replacement        = CorrectivePublicSettings::enabled( 'replace_existing_feed_surface' );
		if ( CorrectivePublicSettings::enabled( 'duplicate_feed_guard' ) && '' !== $existing_shortcode && ! $replacement ) {
			return $content;
		}

		self::$rendered = true;
		$feed = HomeIntegration::render_feed_once( 'corrective_front_page_mount', array() );
		if ( '' === $feed ) {
			return $content;
		}
		$surface = self::surface( $feed );
		self::enqueue_assets();

		if ( '' !== $existing_shortcode && $replacement ) {
			$replaced = self::replace_known_feed_shortcodes( $content, $surface );
			return $replaced !== $content ? $replaced : $content;
		}

		return $content . $surface;
	}

	/** Replace the first known Feed shortcode with File 21 and remove duplicates. */
	public static function replace_known_feed_shortcodes( $content, $surface ) {
		$content = is_string( $content ) ? $content : '';
		$surface = is_string( $surface ) ? $surface : '';
		if ( '' === $content || '' === $surface || '' === self::content_feed_shortcode( $content ) ) {
			return $content;
		}

		$replacement_count = 0;
		if ( function_exists( 'get_shortcode_regex' ) ) {
			$regex = get_shortcode_regex( self::known_feed_shortcodes() );
			$result = preg_replace_callback(
				'~' . $regex . '~s',
				static function ( $match ) use ( &$replacement_count, $surface ) {
					if ( isset( $match[1], $match[6] ) && '[' === $match[1] && ']' === $match[6] ) {
						return substr( $match[0], 1, -1 );
					}
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
			static function () use ( &$replacement_count, $surface ) {
				$replacement_count++;
				return 1 === $replacement_count ? $surface : '';
			},
			$content
		);
		return is_string( $result ) ? $result : $content;
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
		$enabled     = CorrectivePublicSettings::enabled( 'home_surface_enabled' );
		$status      = 'Available but not configured';
		$detail      = __( 'File 21 never inserts primary navigation and renders at most one corrective Feed surface per request.', 'sabri-complete-home-news-feed' );
		if ( $enabled && ! empty( $diagnostics['feed_conflict'] ) && empty( $diagnostics['replacement_enabled'] ) ) {
			$status = 'Blocked by duplicate guard';
			$detail = sprintf( __( 'Existing Feed shortcode detected: %s. Enable controlled replacement in the Activation Wizard or keep File 21 auto-mount disabled.', 'sabri-complete-home-news-feed' ), $diagnostics['existing_feed_shortcode'] );
		} elseif ( $enabled && ! empty( $diagnostics['feed_conflict'] ) && ! empty( $diagnostics['replacement_enabled'] ) ) {
			$status = 'Enabled with controlled replacement';
			$detail = sprintf( __( 'Existing Feed shortcode %s is replaced only at render time; page content is not mutated.', 'sabri-complete-home-news-feed' ), $diagnostics['existing_feed_shortcode'] );
		} elseif ( $enabled ) {
			$status = 'Enabled';
		}
		$rows[] = array(
			'label'  => __( 'File 21 public mount', 'sabri-complete-home-news-feed' ),
			'status' => $status,
			'detail' => $detail,
		);
		return $rows;
	}

	/** Reset request guards for tests. */
	public static function reset_runtime_guards() {
		self::$rendered = false;
	}

	/** Build the identifiable File 21 surface. */
	private static function surface( $feed ) {
		$marker = CorrectivePublicSettings::enabled( 'distinct_surface_marker' )
			? '<p class="sabri-hnf-corrective-surface__eyebrow">' . esc_html__( 'Sabri Home & News Feed', 'sabri-complete-home-news-feed' ) . '</p>'
			: '';
		return '<section class="sabri-hnf-corrective-surface" data-sabri-hnf-surface="file-21-corrective" data-sabri-hnf-version="1.0.1">'
			. $marker
			. $feed
			. '</section>';
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
