<?php
/**
 * Home Feed renderer.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders accessible feed UI from real WordPress posts.
 */
final class FeedRenderer {
	/**
	 * Render a feed instance.
	 *
	 * @param array<string,mixed> $atts Shortcode or hook attributes.
	 * @return string
	 */
	public static function render( array $atts = array() ) {
		Assets::enqueue_feed();

		$result   = FeedQuery::query( $atts );
		$settings = Settings::get();

		if ( 'disabled' === $result['status'] ) {
			return self::template(
				'feed-error',
				array(
					'message' => __( 'The custom Home Feed is currently disabled.', 'sabri-complete-home-news-feed' ),
				)
			);
		}

		return self::template(
			'feed',
			array(
				'result'      => $result,
				'settings'    => $settings,
				'filters'     => self::render_filter_nav( $result['mode'], $settings ),
				'cards'       => self::render_cards( $result['posts'], $settings ),
				'pagination'  => self::render_pagination( $result, $settings ),
				'empty_state' => self::template( 'feed-empty', array() ),
			)
		);
	}

	/**
	 * Render filter navigation.
	 *
	 * @param string              $active Active mode.
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	public static function render_filter_nav( $active, array $settings ) {
		$modes = FeedContext::modes();
		$html  = '<nav class="sabri-hnf-filter" aria-label="' . esc_attr__( 'Home Feed filters', 'sabri-complete-home-news-feed' ) . '">';
		$html .= '<ul>';

		foreach ( FeedContext::enabled_modes( $settings ) as $mode ) {
			if ( empty( $modes[ $mode ] ) ) {
				continue;
			}
			$url = self::feed_url( $mode, 1 );
			$html .= '<li><a class="sabri-hnf-filter__link' . ( $active === $mode ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $active === $mode ? ' aria-current="page"' : '' ) . ' data-sabri-feed-mode="' . esc_attr( $mode ) . '">' . esc_html( $modes[ $mode ] ) . '</a></li>';
		}

		$html .= '</ul></nav>';

		return $html;
	}

	/**
	 * Render cards.
	 *
	 * @param array<int,mixed>    $posts Posts.
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	public static function render_cards( array $posts, array $settings ) {
		$html = '';
		foreach ( $posts as $post ) {
			$html .= self::render_card( $post, $settings );
		}

		return $html;
	}

	/**
	 * Render a single feed card.
	 *
	 * @param mixed               $post Post.
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	public static function render_card( $post, array $settings ) {
		if ( is_array( $post ) && 'editorial_news' === ( isset( $post['item_type'] ) ? $post['item_type'] : '' ) ) {
			return class_exists( __NAMESPACE__ . '\\NewsPublicRuntime' ) ? NewsPublicRuntime::render_card( $post ) : '';
		}

		$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
		if ( $post_id <= 0 || ! PostMetadata::user_can_view( $post_id ) ) {
			return '';
		}

		$type    = PostMetadata::feed_type( $post_id );

		return self::template(
			'feed-card',
			array(
				'post_id'       => $post_id,
				'title'         => self::post_title( $post_id ),
				'excerpt'       => self::post_excerpt( $post_id ),
				'permalink'     => self::permalink( $post_id ),
				'author_avatar' => empty( $settings['feed']['show_author_details'] ) ? '' : self::author_avatar( $post_id ),
				'author_name'   => empty( $settings['feed']['show_author_details'] ) ? '' : self::author_name( $post_id ),
				'author_label'  => empty( $settings['feed']['show_author_details'] ) ? '' : self::author_label( $post_id ),
				'badges'        => empty( $settings['feed']['show_author_details'] ) ? array() : self::author_badges( $post_id ),
				'feed_type'     => empty( $settings['feed']['show_post_type'] ) ? '' : self::feed_type_label( $type ),
				'visibility'    => PostMetadata::visibility( $post_id ),
				'date'          => self::post_date( $post_id ),
				'time'          => self::post_time( $post_id ),
				'edited'        => self::is_edited( $post_id ),
				'featured'      => self::featured_image( $post_id, $settings ),
				'gallery'       => self::media_gallery( $post_id, $settings ),
				'topics'        => self::term_links( $post_id, 'sabri_feed_topic' ),
				'categories'    => self::term_links( $post_id, 'category' ),
				'hashtags'      => self::term_links( $post_id, 'post_tag' ),
				'disclaimer'    => self::medical_disclaimer( $type, $settings ),
			)
		);
	}

	/**
	 * Render pagination and Load More markup.
	 *
	 * @param array<string,mixed> $result Result.
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	public static function render_pagination( array $result, array $settings ) {
		return self::template(
			'pagination',
			array(
				'result'     => $result,
				'settings'   => $settings,
				'pagination' => self::pagination_links( $result ),
				'next_url'   => self::rest_feed_url( $result['mode'], (int) $result['page'] + 1, (int) $result['per_page'] ),
				'state_url'  => self::feed_url( $result['mode'], (int) $result['page'] + 1 ),
			)
		);
	}

	/**
	 * Load a template.
	 *
	 * @param string              $template Template slug.
	 * @param array<string,mixed> $vars Variables.
	 * @return string
	 */
	public static function template( $template, array $vars ) {
		$file = SABRI_HNF_PATH . 'templates/' . sanitize_key( $template ) . '.php';
		if ( ! is_readable( $file ) ) {
			return '';
		}

		ob_start();
		extract( $vars, EXTR_SKIP );
		include $file;
		return (string) ob_get_clean();
	}

	/**
	 * Build pagination links.
	 *
	 * @param array<string,mixed> $result Result.
	 * @return string
	 */
	private static function pagination_links( array $result ) {
		if ( (int) $result['max_pages'] <= 1 ) {
			return '';
		}

		if ( function_exists( 'paginate_links' ) ) {
			$links = paginate_links(
				array(
					'base'      => self::feed_url( $result['mode'], '%#%' ),
					'format'    => '',
					'current'   => (int) $result['page'],
					'total'     => (int) $result['max_pages'],
					'type'      => 'list',
					'prev_text' => __( 'Previous', 'sabri-complete-home-news-feed' ),
					'next_text' => __( 'Next', 'sabri-complete-home-news-feed' ),
				)
			);

			return is_string( $links ) ? $links : '';
		}

		return '';
	}

	/**
	 * Build a shareable feed URL.
	 *
	 * @param string     $mode Mode.
	 * @param int|string $page Page.
	 * @return string
	 */
	private static function feed_url( $mode, $page ) {
		$base = function_exists( 'get_pagenum_link' ) ? get_pagenum_link( 1 ) : '';
		if ( '' === $base && function_exists( 'home_url' ) ) {
			$base = home_url( '/' );
		}

		if ( function_exists( 'add_query_arg' ) ) {
			return add_query_arg(
				array(
					'sabri_feed_mode' => sanitize_key( $mode ),
					'sabri_feed_page' => $page,
				),
				$base
			);
		}

		return $base . '?sabri_feed_mode=' . rawurlencode( sanitize_key( $mode ) ) . '&sabri_feed_page=' . rawurlencode( (string) $page );
	}

	/**
	 * Build REST feed URL for progressive enhancement.
	 *
	 * @param string $mode Mode.
	 * @param int    $page Page.
	 * @param int    $per_page Per page.
	 * @return string
	 */
	private static function rest_feed_url( $mode, $page, $per_page ) {
		$base = function_exists( 'rest_url' ) ? rest_url( RestFoundation::NAMESPACE . '/feed' ) : '';
		if ( function_exists( 'add_query_arg' ) ) {
			return add_query_arg(
				array(
					'mode'     => sanitize_key( $mode ),
					'page'     => (int) $page,
					'per_page' => (int) $per_page,
				),
				$base
			);
		}

		return $base . '?mode=' . rawurlencode( sanitize_key( $mode ) ) . '&page=' . rawurlencode( (string) $page ) . '&per_page=' . rawurlencode( (string) $per_page );
	}

	/**
	 * Title.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function post_title( $post_id ) {
		return function_exists( 'get_the_title' ) ? get_the_title( $post_id ) : '';
	}

	/**
	 * Excerpt.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function post_excerpt( $post_id ) {
		if ( function_exists( 'get_the_excerpt' ) ) {
			return get_the_excerpt( $post_id );
		}

		$content = function_exists( 'get_post_field' ) ? get_post_field( 'post_content', $post_id ) : '';
		return wp_strip_all_tags( substr( (string) $content, 0, 260 ) );
	}

	/**
	 * Permalink.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function permalink( $post_id ) {
		return function_exists( 'get_permalink' ) ? get_permalink( $post_id ) : '#';
	}

	/**
	 * Author avatar.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function author_avatar( $post_id ) {
		$author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0;
		return function_exists( 'get_avatar' ) ? get_avatar( $author_id, 48, '', '', array( 'class' => 'sabri-hnf-card__avatar-img' ) ) : '';
	}

	/**
	 * Author name.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function author_name( $post_id ) {
		$author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0;
		$name      = function_exists( 'get_the_author_meta' ) ? trim( (string) get_the_author_meta( 'display_name', $author_id ) ) : '';
		$is_email  = '' !== $name && ( ( function_exists( 'is_email' ) && is_email( $name ) ) || false !== filter_var( $name, FILTER_VALIDATE_EMAIL ) );
		return '' !== $name && ! $is_email ? $name : __( 'Sabri member', 'sabri-complete-home-news-feed' );
	}

	/**
	 * Author label.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function author_label( $post_id ) {
		$settings  = Settings::get();
		$author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0;

		if ( ComposerPermissions::user_has_role_group( $author_id, 'founder_roles', $settings ) ) {
			return __( 'Founder', 'sabri-complete-home-news-feed' );
		}
		if ( ComposerPermissions::user_has_role_group( $author_id, 'verified_doctor_roles', $settings ) ) {
			return __( 'Verified doctor', 'sabri-complete-home-news-feed' );
		}
		if ( ComposerPermissions::user_has_role_group( $author_id, 'unverified_doctor_roles', $settings ) ) {
			return __( 'Doctor', 'sabri-complete-home-news-feed' );
		}

		return __( 'Author', 'sabri-complete-home-news-feed' );
	}

	/**
	 * Author badges.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,string>
	 */
	private static function author_badges( $post_id ) {
		$settings  = Settings::get();
		$author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0;
		$badges    = array();

		if ( ComposerPermissions::user_has_role_group( $author_id, 'founder_roles', $settings ) ) {
			$badges[] = __( 'Founder', 'sabri-complete-home-news-feed' );
		}
		if ( ComposerPermissions::user_has_role_group( $author_id, 'verified_doctor_roles', $settings ) ) {
			$badges[] = __( 'Verified', 'sabri-complete-home-news-feed' );
		}

		return $badges;
	}

	/**
	 * Feed type label.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	private static function feed_type_label( $type ) {
		$terms = Taxonomies::feed_type_terms();
		return isset( $terms[ $type ] ) ? $terms[ $type ] : __( 'Post', 'sabri-complete-home-news-feed' );
	}

	/**
	 * Date.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function post_date( $post_id ) {
		return function_exists( 'get_the_date' ) ? get_the_date( '', $post_id ) : '';
	}

	/**
	 * Time.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private static function post_time( $post_id ) {
		return function_exists( 'get_the_time' ) ? get_the_time( '', $post_id ) : '';
	}

	/**
	 * Edited state.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private static function is_edited( $post_id ) {
		$created  = function_exists( 'get_post_time' ) ? (int) get_post_time( 'U', true, $post_id ) : 0;
		$modified = function_exists( 'get_post_modified_time' ) ? (int) get_post_modified_time( 'U', true, $post_id ) : 0;
		return $created > 0 && $modified > $created;
	}

	/**
	 * Featured image.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private static function featured_image( $post_id, array $settings ) {
		if ( empty( $settings['feed']['show_media'] ) || ! function_exists( 'has_post_thumbnail' ) || ! has_post_thumbnail( $post_id ) || ! function_exists( 'get_the_post_thumbnail' ) ) {
			return '';
		}

		return get_the_post_thumbnail( $post_id, 'large', array( 'class' => 'sabri-hnf-card__featured', 'loading' => 'lazy' ) );
	}

	/**
	 * Media gallery.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private static function media_gallery( $post_id, array $settings ) {
		if ( empty( $settings['feed']['show_media'] ) || ! function_exists( 'get_post_meta' ) ) {
			return '';
		}

		$ids = get_post_meta( $post_id, PostMetadata::META_ATTACHMENTS, true );
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			$ids = get_post_meta( $post_id, PostMetadata::META_GALLERY, true );
		}
		if ( ! is_array( $ids ) || empty( $ids ) ) {
			return '';
		}

		$limit = isset( $settings['media']['max_items'] ) ? max( 1, (int) $settings['media']['max_items'] ) : 4;
		return self::template( 'media-gallery', array( 'attachment_ids' => array_slice( MediaHandler::visible_attachment_ids( array_map( 'absint', $ids ) ), 0, $limit ) ) );
	}

	/**
	 * Term links.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array<int,string>
	 */
	private static function term_links( $post_id, $taxonomy ) {
		if ( ! function_exists( 'get_the_terms' ) ) {
			return array();
		}

		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		$labels = array();
		foreach ( $terms as $term ) {
			if ( is_object( $term ) && ! empty( $term->name ) ) {
				$labels[] = $term->name;
			}
		}

		return array_values( array_unique( $labels ) );
	}

	/**
	 * Medical disclaimer.
	 *
	 * @param string              $type Type.
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	private static function medical_disclaimer( $type, array $settings ) {
		if ( empty( $settings['feed']['show_disclaimer'] ) || ! in_array( $type, array( 'clinical-case', 'research', 'public-health' ), true ) ) {
			return '';
		}

		return __( 'Educational content only; consult a qualified professional for care decisions.', 'sabri-complete-home-news-feed' );
	}
}
