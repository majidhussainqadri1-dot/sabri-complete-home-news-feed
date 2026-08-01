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
			add_filter( 'the_content', array( __CLASS__, 'format_single_content' ), 12 );
			add_filter( 'the_content', array( __CLASS__, 'wrap_single_content' ), 18 );
			add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
		}
		if ( function_exists( 'add_action' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_single_assets' ), 30 );
		}
	}

	/** Resolve the managed post for content filters or request-level assets. */
	private static function managed_post_id( $require_loop = false ) {
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
		if ( $post_id <= 0 || ! function_exists( 'get_post_meta' ) ) {
			return 0;
		}
		return '' !== trim( (string) get_post_meta( $post_id, PostMetadata::META_TYPE, true ) ) ? $post_id : 0;
	}

	/**
	 * Convert a deliberately small Markdown subset after WordPress wpautop.
	 *
	 * This is intentionally not a general Markdown engine. It only repairs the
	 * legacy/plain-text markers observed in File 21 publications and leaves
	 * already-authored HTML structures untouched.
	 */
	public static function format_single_content( $content ) {
		if ( self::$formatting || self::managed_post_id( true ) <= 0 || ! is_string( $content ) || '' === trim( $content ) ) {
			return $content;
		}
		self::$formatting = true;
		try {
			$content = preg_replace_callback(
				'/<p>\s*(#{1,4})\s+(.+?)\s*<\/p>/isu',
				static function ( $match ) {
					$level = min( 4, max( 2, strlen( $match[1] ) + 1 ) );
					return '<h' . $level . '>' . $match[2] . '</h' . $level . '>';
				},
				$content
			);

			// Process only text nodes so attributes, links and existing HTML remain intact.
			$parts = preg_split( '/(<[^>]+>)/u', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
			if ( ! is_array( $parts ) ) {
				return $content;
			}
			foreach ( $parts as $index => $part ) {
				if ( 0 === $index % 2 && '' !== $part ) {
					$part = preg_replace( '/\*\*([^*\r\n]{1,1000})\*\*/u', '<strong>$1</strong>', $part );
					$part = preg_replace( '/__([^_\r\n]{1,1000})__/u', '<strong>$1</strong>', $part );
					$parts[ $index ] = $part;
				}
			}
			$content = implode( '', $parts );
			return function_exists( 'wp_kses_post' ) ? wp_kses_post( $content ) : $content;
		} finally {
			self::$formatting = false;
		}
	}

	/** Add a stable content-width and overflow containment wrapper once. */
	public static function wrap_single_content( $content ) {
		if ( self::managed_post_id( true ) <= 0 || false !== strpos( (string) $content, 'sabri-hnf-single-content' ) ) {
			return $content;
		}
		return '<div class="sabri-hnf-single-content" data-sabri-hnf-post-content="1">' . $content . '</div>';
	}

	/** Add a page-level class for Shell/File 25 integration without DOM guessing. */
	public static function body_classes( $classes ) {
		if ( ! is_array( $classes ) ) {
			$classes = array();
		}
		if ( self::managed_post_id() > 0 ) {
			$classes[] = 'sabri-hnf-managed-single';
		}
		return array_values( array_unique( $classes ) );
	}

	/** Ensure the single-post containment rules are always present. */
	public static function enqueue_single_assets() {
		if ( self::managed_post_id() <= 0 ) {
			return;
		}
		if ( class_exists( __NAMESPACE__ . '\\Assets' ) ) {
			Assets::enqueue_interactions();
		}
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style(
				'sabri-hnf-public-content-integrity',
				SABRI_HNF_URL . 'assets/css/public-content-integrity.css',
				array( 'sabri-hnf-feed' ),
				'1.0.3.2'
			);
		}
	}
}
