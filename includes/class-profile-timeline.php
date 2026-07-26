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
	const MAX_SCAN     = 500;

	/** Register the profile hook and compatibility bridge. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'sabri_profile_timeline', array( __CLASS__, 'render_action' ), 10, 2 );
		}
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'do_shortcode_tag', array( __CLASS__, 'append_to_profile_shortcode' ), 20, 4 );
		}
	}

	/**
	 * Query a public-safe timeline.
	 *
	 * WordPress found_posts is deliberately not used: it can count restricted
	 * follower/private/group posts removed by object-level authorization. A
	 * bounded candidate scan is filtered first, and pagination is calculated
	 * only from posts the current viewer may actually see.
	 */
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
			'posts_per_page'      => self::MAX_SCAN,
			'paged'               => 1,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
			'meta_query'          => array(
				'relation' => 'AND',
				PostMetadata::visibility_meta_clause(),
				PostMetadata::review_state_meta_clause(),
			),
		);
		if ( function_exists( 'apply_filters' ) ) {
			$query_args = apply_filters( 'sabri_hnf_profile_timeline_query_args', $query_args, $user_id, $viewer );
		}

		$candidates = array();
		if ( class_exists( 'WP_Query' ) ) {
			$query      = new \WP_Query( $query_args );
			$candidates = (array) $query->posts;
		} elseif ( function_exists( 'apply_filters' ) ) {
			$candidates = apply_filters( 'sabri_hnf_profile_timeline_test_posts', array(), $query_args );
			$candidates = is_array( $candidates ) ? array_slice( $candidates, 0, self::MAX_SCAN ) : array();
		}

		$visible = array();
		foreach ( $candidates as $post ) {
			$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : (int) $post;
			if ( $post_id > 0 && PostMetadata::user_can_view( $post_id, $viewer ) ) {
				$visible[] = $post;
			}
		}

		$scan_truncated = count( $candidates ) >= self::MAX_SCAN;
		$visible_total  = count( $visible );
		$offset         = ( $page - 1 ) * $per_page;
		$page_posts     = array_slice( $visible, $offset, $per_page );
		$items          = array();
		foreach ( $page_posts as $post ) {
			$item = self::serialize_post( $post, $viewer );
			if ( ! empty( $item ) ) {
				$items[] = $item;
			}
		}

		$known_has_more = $offset + count( $items ) < $visible_total;
		$possible_more  = $scan_truncated && $offset + count( $items ) >= $visible_total;
		$max_pages      = (int) ceil( $visible_total / max( 1, $per_page ) );

		return array(
			'status'            => 'ok',
			'user_id'           => $user_id,
			'page'              => $page,
			'per_page'          => $per_page,
			'visible_total'     => $visible_total,
			'total_is_complete' => ! $scan_truncated,
			'max_pages'         => $scan_truncated ? null : max( 0, $max_pages ),
			'has_more'          => $known_has_more || $possible_more,
			'scan_limit'        => self::MAX_SCAN,
			'items'             => $items,
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
		$count_label = ! empty( $result['total_is_complete'] )
			? sprintf( _n( '%d public post', '%d public posts', (int) $result['visible_total'], 'sabri-complete-home-news-feed' ), (int) $result['visible_total'] )
			: sprintf( __( 'Showing up to %d authorized posts', 'sabri-complete-home-news-feed' ), (int) $result['scan_limit'] );
		ob_start();
		?>
		<section class="sabri-hnf-profile-timeline" data-sabri-profile-timeline data-user-id="<?php echo esc_attr( (int) $result['user_id'] ); ?>" data-page="<?php echo esc_attr( (int) $result['page'] ); ?>">
			<header class="sabri-hnf-profile-timeline__header">
				<h2><?php echo esc_html( $author_name ? sprintf( __( '%s — Posts', 'sabri-complete-home-news-feed' ), $author_name ) : __( 'Posts', 'sabri-complete-home-news-feed' ) ); ?></h2>
				<span><?php echo esc_html( $count_label ); ?></span>
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

	/**
	 * Append the functional Timeline to the existing File 03 profile shortcodes.
	 *
	 * This bridge does not require File 03 classes and does not alter its stored
	 * profile data. Unverified/hidden profile notices are not augmented because
	 * only shortcode output containing a real <main> profile surface is accepted.
	 *
	 * @param string              $output Shortcode output.
	 * @param string              $tag Shortcode tag.
	 * @param array<string,mixed> $attr Shortcode attributes.
	 * @param array<int,string>   $match Regex match.
	 * @return string
	 */
	public static function append_to_profile_shortcode( $output, $tag, $attr, $match ) {
		unset( $attr, $match );
		if ( ! is_string( $output ) || false === strpos( $output, '<main' ) || false !== strpos( $output, 'data-sabri-profile-timeline' ) ) {
			return $output;
		}
		if ( ! in_array( $tag, array( 'sabri_founder_profile', 'sabri_member_profile' ), true ) || ! CorrectivePublicSettings::enabled( 'profile_timeline_enabled' ) ) {
			return $output;
		}

		$user_id = self::profile_shortcode_user_id( $tag );
		$timeline = $user_id > 0 ? self::render( $user_id, array( 'page' => 1, 'per_page' => 10 ) ) : '';
		if ( '' === $timeline ) {
			return $output;
		}

		$position = strripos( $output, '</main>' );
		return false === $position ? $output . $timeline : substr_replace( $output, $timeline . '</main>', $position, 7 );
	}

	/** Action bridge for profile plugins and File 22. */
	public static function render_action( $user_id, $args = array() ) {
		echo self::render( $user_id, is_array( $args ) ? $args : array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** Public serializer containing no private metadata. */
	public static function serialize_post( $post, $viewer_id = 0 ) {
		$post_id   = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : absint( $post );
		$viewer_id = $viewer_id ? (int) $viewer_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		if ( $post_id <= 0 || ! PostMetadata::user_can_view( $post_id, $viewer_id ) ) {
			return array();
		}
		$title   = function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : '';
		$content = function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_content', $post_id ) : '';
		$excerpt = function_exists( 'get_the_excerpt' ) ? (string) get_the_excerpt( $post_id ) : '';
		if ( '' === $excerpt && '' !== $content ) {
			$plain   = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $content ) : strip_tags( $content );
			$excerpt = function_exists( 'wp_trim_words' ) ? wp_trim_words( $plain, 32, '…' ) : substr( $plain, 0, 220 );
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

	/** Resolve the author represented by a File 03 profile shortcode. */
	private static function profile_shortcode_user_id( $tag ) {
		if ( 'sabri_founder_profile' === $tag ) {
			foreach ( array( 'spf_founder_user_id', 'spd_founder_user_id', 'sabri_founder_user_id' ) as $option ) {
				$user_id = function_exists( 'get_option' ) ? absint( get_option( $option, 0 ) ) : 0;
				if ( $user_id > 0 ) {
					return $user_id;
				}
			}
			$ids = LegacyFounderPostMigration::privileged_author_ids();
			return ! empty( $ids ) ? (int) reset( $ids ) : 0;
		}

		$key = isset( $_GET['user'] ) ? sanitize_title( wp_unslash( $_GET['user'] ) ) : '';
		if ( '' !== $key && function_exists( 'get_user_by' ) ) {
			$user = get_user_by( 'slug', $key );
			if ( is_object( $user ) && isset( $user->ID ) ) {
				return (int) $user->ID;
			}
		}
		return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
	}

	/** Empty response. */
	private static function empty_result( $user_id, $page, $per_page, $status ) {
		return array(
			'status'            => $status,
			'user_id'           => (int) $user_id,
			'page'              => (int) $page,
			'per_page'          => (int) $per_page,
			'visible_total'     => 0,
			'total_is_complete' => true,
			'max_pages'         => 0,
			'has_more'          => false,
			'scan_limit'        => self::MAX_SCAN,
			'items'             => array(),
		);
	}
}
