<?php
/**
 * Visibility-safe Profile Timeline foundation for File 22.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Supplies bounded author timelines without owning the final File 22 design. */
final class ProfileTimeline {
	const MAX_PER_PAGE = 20;

	/** Register the profile hook. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'sabri_profile_timeline', array( __CLASS__, 'render_action' ), 10, 2 );
		}
	}

	/** Query a public-safe timeline. */
	public static function query( $user_id, array $args = array() ) {
		$user_id  = absint( $user_id );
		$page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
		$per_page = isset( $args['per_page'] ) ? max( 1, min( self::MAX_PER_PAGE, absint( $args['per_page'] ) ) ) : 10;
		$viewer   = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

		if ( $user_id <= 0 || ! CorrectivePublicSettings::enabled( 'profile_timeline_enabled' ) || SafeMode::public_features_disabled() ) {
			return self::empty_result( $user_id, $page, $per_page, 'disabled' );
		}

		$query_args = array(
			'post_type'           => 'post',
			'post_status'         => array( 'publish' ),
			'author'              => $user_id,
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => false,
			'meta_query'          => array(
				'relation' => 'AND',
				PostMetadata::review_state_meta_clause(),
			),
		);
		if ( function_exists( 'apply_filters' ) ) {
			$query_args = apply_filters( 'sabri_hnf_profile_timeline_query_args', $query_args, $user_id, $viewer );
		}

		$posts     = array();
		$total     = 0;
		$max_pages = 0;
		if ( class_exists( 'WP_Query' ) ) {
			$query = new \WP_Query( $query_args );
			foreach ( (array) $query->posts as $post ) {
				$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
				if ( $post_id > 0 && PostMetadata::user_can_view( $post_id, $viewer ) ) {
					$posts[] = $post;
				}
			}
			$total     = isset( $query->found_posts ) ? (int) $query->found_posts : count( $posts );
			$max_pages = isset( $query->max_num_pages ) ? (int) $query->max_num_pages : (int) ceil( $total / max( 1, $per_page ) );
		} elseif ( function_exists( 'apply_filters' ) ) {
			$posts = apply_filters( 'sabri_hnf_profile_timeline_test_posts', array(), $query_args );
			$posts = array_values(
				array_filter(
					is_array( $posts ) ? $posts : array(),
					static function ( $post ) use ( $viewer ) {
						$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
						return $post_id > 0 && PostMetadata::user_can_view( $post_id, $viewer );
					}
				)
			);
			$total     = count( $posts );
			$posts     = array_slice( $posts, ( $page - 1 ) * $per_page, $per_page );
			$max_pages = (int) ceil( $total / max( 1, $per_page ) );
		}

		$items = array();
		foreach ( $posts as $post ) {
			$item = self::serialize_post( $post );
			if ( ! empty( $item ) ) {
				$items[] = $item;
			}
		}

		return array(
			'status'    => 'ok',
			'user_id'   => $user_id,
			'page'      => $page,
			'per_page'  => $per_page,
			'total'     => $total,
			'max_pages' => max( 0, $max_pages ),
			'has_more'  => $page < max( 0, $max_pages ),
			'items'     => $items,
		);
	}

	/** Render the basic functional timeline surface. */
	public static function render( $user_id, array $args = array() ) {
		$result = self::query( $user_id, $args );
		if ( 'disabled' === $result['status'] ) {
			return '';
		}

		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'sabri-hnf-corrective-public', SABRI_HNF_URL . 'assets/css/corrective-public.css', array(), SABRI_HNF_VERSION );
		}

		$author_name = function_exists( 'get_the_author_meta' ) ? (string) get_the_author_meta( 'display_name', (int) $result['user_id'] ) : '';
		ob_start();
		?>
		<section class="sabri-hnf-profile-timeline" data-sabri-profile-timeline data-user-id="<?php echo esc_attr( (int) $result['user_id'] ); ?>" data-page="<?php echo esc_attr( (int) $result['page'] ); ?>">
			<header class="sabri-hnf-profile-timeline__header">
				<h2><?php echo esc_html( $author_name ? sprintf( __( '%s — Posts', 'sabri-complete-home-news-feed' ), $author_name ) : __( 'Posts', 'sabri-complete-home-news-feed' ) ); ?></h2>
				<span><?php echo esc_html( sprintf( _n( '%d published post', '%d published posts', (int) $result['total'], 'sabri-complete-home-news-feed' ), (int) $result['total'] ) ); ?></span>
			</header>
			<?php if ( empty( $result['items'] ) ) : ?>
				<p class="sabri-hnf-profile-timeline__empty"><?php esc_html_e( 'No public posts are available on this profile yet.', 'sabri-complete-home-news-feed' ); ?></p>
			<?php else : ?>
				<ol class="sabri-hnf-profile-timeline__list">
					<?php foreach ( $result['items'] as $item ) : ?>
						<li class="sabri-hnf-profile-timeline__item" data-post-id="<?php echo esc_attr( (int) $item['id'] ); ?>">
							<article>
								<h3><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3>
								<p class="sabri-hnf-profile-timeline__meta"><time datetime="<?php echo esc_attr( $item['date_gmt'] ); ?>"><?php echo esc_html( $item['date_display'] ); ?></time></p>
								<?php if ( '' !== $item['excerpt'] ) : ?><p><?php echo esc_html( $item['excerpt'] ); ?></p><?php endif; ?>
							</article>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/** Action bridge for profile plugins and File 22. */
	public static function render_action( $user_id, $args = array() ) {
		echo self::render( $user_id, is_array( $args ) ? $args : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** Public serializer containing no private metadata. */
	public static function serialize_post( $post ) {
		$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : absint( $post );
		if ( $post_id <= 0 ) {
			return array();
		}
		$title   = function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : '';
		$content = function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_content', $post_id ) : '';
		$excerpt = function_exists( 'get_the_excerpt' ) ? (string) get_the_excerpt( $post_id ) : '';
		if ( '' === $excerpt && '' !== $content ) {
			$excerpt = function_exists( 'wp_trim_words' ) ? wp_trim_words( wp_strip_all_tags( $content ), 32, '…' ) : substr( strip_tags( $content ), 0, 220 );
		}
		$item = array(
			'id'           => $post_id,
			'title'        => '' !== $title ? $title : __( 'Untitled post', 'sabri-complete-home-news-feed' ),
			'excerpt'      => $excerpt,
			'url'          => function_exists( 'get_permalink' ) ? (string) get_permalink( $post_id ) : '',
			'date_gmt'     => function_exists( 'get_post_time' ) ? (string) get_post_time( DATE_W3C, true, $post_id ) : '',
			'date_display' => function_exists( 'get_the_date' ) ? (string) get_the_date( '', $post_id ) : '',
		);
		return function_exists( 'apply_filters' ) ? apply_filters( 'sabri_hnf_profile_timeline_item', $item, $post_id ) : $item;
	}

	/** Empty response. */
	private static function empty_result( $user_id, $page, $per_page, $status ) {
		return array(
			'status'    => $status,
			'user_id'   => (int) $user_id,
			'page'      => (int) $page,
			'per_page'  => (int) $per_page,
			'total'     => 0,
			'max_pages' => 0,
			'has_more'  => false,
			'items'     => array(),
		);
	}
}
