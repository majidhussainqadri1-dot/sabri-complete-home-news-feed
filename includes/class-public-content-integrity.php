<?php
/**
 * Public content integrity and single-post presentation safeguards.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps File 21 public projections bound to their canonical post and provides
 * conservative Markdown compatibility for legacy/plain-text publications.
 */
final class PublicContentIntegrity {
	/** Guard against recursive content filtering. */
	private static $formatting = false;

	/** Register public safeguards. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'get_the_excerpt', array( __CLASS__, 'canonical_excerpt' ), PHP_INT_MAX, 2 );
			add_filter( 'get_the_terms', array( __CLASS__, 'filter_automatic_default_category' ), PHP_INT_MAX, 3 );
			add_filter( 'the_title', array( __CLASS__, 'format_single_title' ), 12, 2 );
			add_filter( 'the_content', array( __CLASS__, 'format_single_content' ), 12 );
			add_filter( 'the_content', array( __CLASS__, 'wrap_single_content' ), 18 );
			add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_single_assets' ), 30 );
		}
	}

	/** Whether a specific post is owned by File 21 public publishing metadata. */
	private static function is_managed_post( $post_id ) {
		return (int) $post_id > 0
			&& function_exists( 'get_post_meta' )
			&& '' !== trim( (string) get_post_meta( (int) $post_id, PostMetadata::META_TYPE, true ) );
	}

	/** Whether a public post needs conservative Markdown display repair. */
	private static function requires_integrity( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return false;
		}
		if ( self::is_managed_post( $post_id ) ) {
			return true;
		}
		if ( ! function_exists( 'get_post' ) ) {
			return false;
		}
		$post = get_post( $post_id );
		if ( ! is_object( $post ) || ! isset( $post->ID ) || $post_id !== (int) $post->ID ) {
			return false;
		}
		$title   = isset( $post->post_title ) ? (string) $post->post_title : '';
		$content = isset( $post->post_content ) ? (string) $post->post_content : '';
		return self::contains_markdown_artifact( $title, true ) || self::contains_markdown_artifact( $content, false );
	}

	/** Detect only the explicit Markdown artifacts repaired by this class. */
	private static function contains_markdown_artifact( $text, $title = false ) {
		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			return false;
		}
		if ( $title && 1 === preg_match( '/^\s*#{1,6}\s+\S/u', $text ) ) {
			return true;
		}
		if ( ! $title && 1 === preg_match( '/(?:^|[\r\n])\s*#{1,5}\s+\S/u', $text ) ) {
			return true;
		}
		return 1 === preg_match( '/(?:\*\*[^*\r\n]{1,1000}\*\*|__[^_\r\n]{1,1000}__|(?<![\\*])\*[^*\r\n]{1,1000}\*(?!\*))/u', $text );
	}

	/** Resolve the post for managed-only filters or broader integrity display. */
	private static function request_post_id( $require_loop = false, $managed_only = false ) {
		if ( ! class_exists( __NAMESPACE__ . '\\HomeIntegration' ) || ! HomeIntegration::is_single_post_request() ) {
			return 0;
		}
		if ( $require_loop ) {
			if ( function_exists( 'in_the_loop' ) && ! in_the_loop() ) {
				return 0;
			}
			if ( function_exists( 'is_main_query' ) && ! is_main_query() ) {
				return 0;
			}
		}
		$post_id = $require_loop && function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0;
		if ( $post_id <= 0 && function_exists( 'get_queried_object_id' ) ) {
			$post_id = (int) get_queried_object_id();
		}
		if ( $managed_only ) {
			return self::is_managed_post( $post_id ) ? $post_id : 0;
		}
		return self::requires_integrity( $post_id ) ? $post_id : 0;
	}

	/**
	 * Force excerpt projection to the explicitly requested canonical post.
	 *
	 * Some themes/plugins ignore the post argument and read the global loop,
	 * which paired the acceptance-test title with unrelated Global Clinic text.
	 */
	public static function canonical_excerpt( $excerpt, $post = null ) {
		$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : ( is_numeric( $post ) ? (int) $post : 0 );
		if ( $post_id <= 0 || ! self::is_managed_post( $post_id ) || ! function_exists( 'get_post' ) ) {
			return $excerpt;
		}
		$canonical = get_post( $post_id );
		if ( ! is_object( $canonical ) || ! isset( $canonical->ID ) || (int) $canonical->ID !== $post_id ) {
			return '';
		}
		$source = isset( $canonical->post_excerpt ) ? trim( (string) $canonical->post_excerpt ) : '';
		if ( '' === $source ) {
			$source = isset( $canonical->post_content ) ? (string) $canonical->post_content : '';
		}
		if ( function_exists( 'strip_shortcodes' ) ) {
			$source = strip_shortcodes( $source );
		}
		$plain = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $source, true ) : strip_tags( $source );
		$plain = preg_replace( '/(^|\s)#{1,6}\s+/u', '$1', (string) $plain );
		$plain = preg_replace( '/\*\*([^*\r\n]{1,1000})\*\*/u', '$1', (string) $plain );
		$plain = preg_replace( '/__([^_\r\n]{1,1000})__/u', '$1', (string) $plain );
		$plain = preg_replace( '/(?<![\\*])\*([^*\r\n]{1,1000})\*(?!\*)/u', '$1', (string) $plain );
		$plain = preg_replace( '/\s+/u', ' ', trim( (string) $plain ) );
		if ( function_exists( 'wp_trim_words' ) ) {
			return wp_trim_words( $plain, 45, '…' );
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $plain, 0, 280 ) : substr( $plain, 0, 280 );
	}

	/** Hide only an unambiguous automatic default Category projection. */
	public static function filter_automatic_default_category( $terms, $post_id, $taxonomy ) {
		if (
			'category' !== $taxonomy ||
			! is_array( $terms ) ||
			1 !== count( $terms ) ||
			! self::is_managed_post( $post_id ) ||
			! function_exists( 'get_option' ) ||
			! function_exists( 'get_the_terms' )
		) {
			return $terms;
		}
		$default_category = (int) get_option( 'default_category', 0 );
		$only_term        = reset( $terms );
		if (
			$default_category <= 0 ||
			! is_object( $only_term ) ||
			! isset( $only_term->term_id ) ||
			$default_category !== (int) $only_term->term_id
		) {
			return $terms;
		}
		$topics = get_the_terms( (int) $post_id, 'sabri_feed_topic' );
		return is_array( $topics ) && array() !== $topics ? array() : $terms;
	}

	/** Strip raw heading/emphasis markers from only the queried singular title. */
	public static function format_single_title( $title, $post_id = 0 ) {
		$post_id    = (int) $post_id;
		$queried_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : $post_id;
		if ( $post_id <= 0 || $queried_id !== $post_id || ! self::requires_integrity( $post_id ) || ! is_string( $title ) ) {
			return $title;
		}
		$title = preg_replace( '/^\s*#{1,6}\s+/u', '', $title );
		$title = preg_replace( '/\*\*([^*\r\n]{1,1000})\*\*/u', '$1', (string) $title );
		$title = preg_replace( '/__([^_\r\n]{1,1000})__/u', '$1', (string) $title );
		$title = preg_replace( '/(?<![\\*])\*([^*\r\n]{1,1000})\*(?!\*)/u', '$1', (string) $title );
		return trim( (string) $title );
	}

	/** Convert a deliberately small Markdown subset after WordPress wpautop. */
	public static function format_single_content( $content ) {
		if ( self::$formatting || self::request_post_id( true ) <= 0 || ! is_string( $content ) || '' === trim( $content ) ) {
			return $content;
		}
		self::$formatting = true;
		try {
			$content = preg_replace_callback(
				'/<p>\s*(#{1,5})\s+(.+?)\s*<\/p>/isu',
				static function ( $match ) {
					$level = min( 6, strlen( $match[1] ) + 1 );
					return '<h' . $level . '>' . $match[2] . '</h' . $level . '>';
				},
				$content
			);
			return self::format_text_nodes( (string) $content );
		} finally {
			self::$formatting = false;
		}
	}

	/** Format text nodes while preserving code, preformatted, script and style blocks. */
	private static function format_text_nodes( $content ) {
		$parts = preg_split( '/(<[^>]+>)/u', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return $content;
		}
		$excluded_depth = 0;
		foreach ( $parts as $index => $part ) {
			if ( 1 === $index % 2 ) {
				if ( 1 === preg_match( '/^<\s*\/\s*(?:pre|code|script|style)\b/iu', $part ) ) {
					$excluded_depth = max( 0, $excluded_depth - 1 );
				} elseif ( 1 === preg_match( '/^<\s*(?:pre|code|script|style)\b/iu', $part ) && 0 === preg_match( '/\/\s*>$/u', $part ) ) {
					++$excluded_depth;
				}
				continue;
			}
			if ( 0 !== $excluded_depth || '' === $part ) {
				continue;
			}
			$part = preg_replace( '/\*\*([^*\r\n]{1,1000})\*\*/u', '<strong>$1</strong>', $part );
			$part = preg_replace( '/__([^_\r\n]{1,1000})__/u', '<strong>$1</strong>', (string) $part );
			$part = preg_replace( '/(?<![\\*])\*([^\s*\r\n](?:[^*\r\n]{0,998}[^\s*\r\n])?)\*(?!\*)/u', '<em>$1</em>', (string) $part );
			$parts[ $index ] = $part;
		}
		return implode( '', $parts );
	}

	/** Add a stable content-width and overflow containment wrapper once. */
	public static function wrap_single_content( $content ) {
		if ( self::request_post_id( true ) <= 0 || false !== strpos( (string) $content, 'sabri-hnf-single-content' ) ) {
			return $content;
		}
		return '<div class="sabri-hnf-single-content" data-sabri-hnf-post-content="1">' . $content . '</div>';
	}

	/** Add stable page-level classes for Shell/File 25 integration. */
	public static function body_classes( $classes ) {
		if ( ! is_array( $classes ) ) {
			$classes = array();
		}
		$post_id = self::request_post_id();
		if ( $post_id > 0 ) {
			$classes[] = 'sabri-hnf-content-integrity-single';
			if ( self::is_managed_post( $post_id ) ) {
				$classes[] = 'sabri-hnf-managed-single';
			}
		}
		return array_values( array_unique( $classes ) );
	}

	/** Ensure the single-post containment rules are always present. */
	public static function enqueue_single_assets() {
		if ( self::request_post_id() <= 0 ) {
			return;
		}
		if ( class_exists( __NAMESPACE__ . '\\Assets' ) ) {
			Assets::enqueue_interactions();
		}
		if ( function_exists( 'wp_enqueue_style' ) ) {
			$version = defined( 'SABRI_HNF_PACKAGE_VERSION' ) ? SABRI_HNF_PACKAGE_VERSION . '-public-content-r3' : '1.0.3.2-public-content-r3';
			wp_enqueue_style(
				'sabri-hnf-public-content-integrity',
				SABRI_HNF_URL . 'assets/css/public-content-integrity.css',
				array(),
				$version
			);
		}
	}
}
