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

/** Supplies bounded File 21 publication timelines for File 03 profiles; File 25 retains visual-system ownership. */
final class ProfileTimeline {
	const MAX_PER_PAGE             = 20;
	const MAX_SCAN                 = 500;
	const FILE03_PROVIDER_MAX      = 250;
	const FILE03_PROVIDER_CONTRACT = '1.0.0';

	/** Register the profile hook and compatibility bridge. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'sabri_profile_timeline', array( __CLASS__, 'render_action' ), 10, 2 );
		}
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'do_shortcode_tag', array( __CLASS__, 'append_to_profile_shortcode' ), 20, 4 );
			add_filter( 'sabri_file21_profile_timeline_provider_health_v1', array( __CLASS__, 'file03_provider_health' ), 10, 3 );
			add_filter( 'sabri_file21_profile_timeline_items_v1', array( __CLASS__, 'file03_provider_items' ), 10, 3 );
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
		if ( ! is_array( $query_args ) ) {
			return self::empty_result( $user_id, $page, $per_page, 'unavailable' );
		}
		// Extension filters may narrow ordering/topics, never expand canonical
		// authorship, publication state, scan budget or review/visibility policy.
		$query_args['post_type']           = 'post';
		$query_args['post_status']         = array( 'publish' );
		$query_args['author']              = $user_id;
		$query_args['posts_per_page']      = self::MAX_SCAN;
		$query_args['paged']               = 1;
		$query_args['no_found_rows']       = true;
		$query_args['ignore_sticky_posts'] = true;
		$query_args['meta_query']          = array(
			'relation' => 'AND',
			PostMetadata::visibility_meta_clause(),
			PostMetadata::review_state_meta_clause(),
		);

		$candidates = array();
		if ( class_exists( 'WP_Query' ) ) {
			$query      = new \WP_Query( $query_args );
			$candidates = (array) $query->posts;
		} elseif ( function_exists( 'apply_filters' ) ) {
			$candidates = apply_filters( 'sabri_hnf_profile_timeline_test_posts', array(), $query_args );
			$candidates = is_array( $candidates ) ? array_slice( $candidates, 0, self::MAX_SCAN ) : array();
		}

		$visible = array();
		foreach ( array_slice( $candidates, 0, self::MAX_SCAN ) as $post ) {
			$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : absint( $post );
			if ( $post_id <= 0 ) {
				continue;
			}
			$current = function_exists( 'get_post' ) ? get_post( $post_id ) : $post;
			if ( ! is_object( $current ) || ! isset( $current->ID, $current->post_author, $current->post_type, $current->post_status ) ||
				(int) $current->ID !== $post_id || (int) $current->post_author !== $user_id ||
				'post' !== (string) $current->post_type || 'publish' !== (string) $current->post_status ||
				! PostMetadata::review_state_publicly_visible( $post_id ) ||
				! PostMetadata::user_can_view( $post_id, $viewer ) ) {
				continue;
			}
			$visible[] = $current;
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


	/** Current File 21 health projection for File 03 timeline consumption. */
	public static function file03_provider_health( $claim = null, $user_id = 0, $consumer_contract = '' ) {
		unset( $claim, $consumer_contract );
		$user_id = absint( $user_id );
		$now = time();
		return array(
			'contract_version' => self::FILE03_PROVIDER_CONTRACT,
			'owner' => 'file21',
			'user_id' => $user_id,
			'status' => $user_id > 0 && CorrectivePublicSettings::enabled( 'profile_timeline_enabled' ) && ! SafeMode::public_features_disabled() ? 'available' : 'unavailable',
			'generated_at' => gmdate( 'c', $now ),
			'valid_until' => gmdate( 'c', $now + 300 ),
		);
	}

	/**
	 * Exact File 03 owner projection, paginated by its verified global cursor.
	 *
	 * The consumer sorts by published_at DESC then provider:canonical_id DESC.
	 * Replaying the newest $limit records on every page silently loses older
	 * posts. This bounded owner-side keyset scan follows the consumer ordering
	 * and rechecks all source privacy/status facts before projection.
	 *
	 * Return WP_Error on an unknown cursor/SQL failure/scan exhaustion: File 03
	 * will mark this provider degraded rather than claim a false empty page.
	 */
	public static function file03_provider_items( $items, $user_id, $args = array() ) {
		unset( $items );
		$user_id = absint( $user_id );
		$args = is_array( $args ) ? $args : array();
		$viewer = absint( $args['viewer_id'] ?? 0 );
		$limit = max( 1, min( self::FILE03_PROVIDER_MAX, absint( $args['limit'] ?? 20 ) ) );
		if ( $user_id <= 0 || ! CorrectivePublicSettings::enabled( 'profile_timeline_enabled' ) || SafeMode::public_features_disabled() ) {
			return array();
		}

		$boundary = self::file03_cursor_boundary( $args['cursor'] ?? array() );
		if ( is_wp_error( $boundary ) ) {
			return $boundary;
		}
		global $wpdb;
		if ( ! is_object( $wpdb ) || empty( $wpdb->posts ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_results' ) || ! function_exists( 'get_post' ) ) {
			return new \WP_Error( 'file21_file03_source_unavailable', 'Canonical publication source cannot be queried.' );
		}
		$results = array();
		$scanned = 0;
		$exhausted = false;
		$max_scan = self::MAX_SCAN;
		$scan_time = $boundary['t'];
		$scan_sort = $boundary['i'];
		$prefix = 'file21:file21-post:';

		while ( count( $results ) < $limit && $scanned < $max_scan ) {
			$batch_size = min( 80, $max_scan - $scanned );
			$params = array( 'post', 'publish', $user_id, $scan_time );
			$cursor_filter = 'post_date_gmt < %s';
			// File 03 compares sort_id lexicographically, not numeric post ID.
			// A cross-provider cursor can include or exclude the whole same-time
			// File 21 group depending on its provider prefix.
			if ( 0 === strpos( $scan_sort, $prefix ) ) {
				$id_text = substr( $scan_sort, strlen( $prefix ) );
				if ( ! preg_match( '/^[1-9][0-9]*$/D', $id_text ) ) {
					return new \WP_Error( 'file21_file03_cursor_invalid', 'The File 03 timeline cursor is malformed.' );
				}
				$cursor_filter = '(post_date_gmt < %s OR (post_date_gmt = %s AND CAST(ID AS CHAR) < %s))';
				$params[] = $scan_time;
				$params[] = $id_text;
			} elseif ( strcmp( $prefix, $scan_sort ) < 0 ) {
				$cursor_filter = 'post_date_gmt <= %s';
			}
			$params[] = $batch_size;
			$sql = "SELECT ID, post_date_gmt FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_author = %d AND " .
				$cursor_filter . ' ORDER BY post_date_gmt DESC, CAST(ID AS CHAR) DESC LIMIT %d';
			$wpdb->last_error = '';
			$prepared = $wpdb->prepare( $sql, ...$params );
			if ( ! is_string( $prepared ) || '' === $prepared ) {
				return new \WP_Error( 'file21_file03_query_invalid', 'A profile timeline query could not be prepared.' );
			}
			$rows = $wpdb->get_results( $prepared, ARRAY_A );
			if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
				return new \WP_Error( 'file21_file03_query_failed', 'A profile timeline source query failed.' );
			}
			if ( empty( $rows ) ) {
				$exhausted = true;
				break;
			}

			foreach ( $rows as $row ) {
				$id_text = is_array( $row ) && isset( $row['ID'] ) ? (string) $row['ID'] : '';
				$date = is_array( $row ) && isset( $row['post_date_gmt'] ) ? (string) $row['post_date_gmt'] : '';
				if ( ! preg_match( '/^[1-9][0-9]*$/D', $id_text ) || strlen( $id_text ) > strlen( (string) PHP_INT_MAX ) ||
					( strlen( $id_text ) === strlen( (string) PHP_INT_MAX ) && strcmp( $id_text, (string) PHP_INT_MAX ) > 0 ) ||
					! self::file03_valid_gmt_time( $date ) ) {
					return new \WP_Error( 'file21_file03_source_invalid', 'A profile timeline source row is malformed.' );
				}
				$post_id = (int) $id_text;
				$sort_id = $prefix . $id_text;
				if ( strcmp( $date, $scan_time ) > 0 || ( $date === $scan_time && strcmp( $sort_id, $scan_sort ) >= 0 ) ) {
					return new \WP_Error( 'file21_file03_source_order_invalid', 'A profile timeline source cursor did not advance.' );
				}
				$scan_time = $date;
				$scan_sort = $sort_id;
				++$scanned;

				$post = get_post( $post_id );
				if ( ! is_object( $post ) || ! isset( $post->ID, $post->post_type, $post->post_status, $post->post_author, $post->post_date_gmt ) ||
					(int) $post->ID !== $post_id || 'post' !== (string) $post->post_type ||
					'publish' !== (string) $post->post_status || (int) $post->post_author !== $user_id ||
					(string) $post->post_date_gmt !== $date ||
					! PostMetadata::review_state_publicly_visible( $post_id ) ||
					! PostMetadata::user_can_view( $post_id, $viewer ) ) {
					continue;
				}
				$serialized = self::serialize_post( $post, $viewer );
				if ( empty( $serialized ) || (int) ( $serialized['id'] ?? 0 ) !== $post_id ||
					empty( $serialized['url'] ) || empty( $serialized['date_gmt'] ) ||
					gmdate( 'Y-m-d H:i:s', strtotime( (string) $serialized['date_gmt'] ) ) !== $date ) {
					continue;
				}
				$modified = function_exists( 'get_post_modified_time' ) ? absint( get_post_modified_time( 'U', true, $post_id ) ) : 0;
				$thumb = function_exists( 'get_the_post_thumbnail_url' ) ? (string) get_the_post_thumbnail_url( $post_id, 'medium' ) : '';
				$results[] = array(
					'author_user_id' => $user_id,
					'contract_version' => self::FILE03_PROVIDER_CONTRACT,
					'canonical_id' => 'file21-post:' . $post_id,
					'owner_version' => (string) max( 1, $modified ?: $post_id ),
					'type' => PostMetadata::feed_type( $post_id ),
					'title' => (string) $serialized['title'],
					'excerpt' => (string) $serialized['excerpt'],
					'url' => (string) $serialized['url'],
					'published_at' => (string) $serialized['date_gmt'],
					'visibility' => PostMetadata::visibility( $post_id ),
					'status' => 'published',
					'thumbnail_url' => $thumb,
					'correction' => '',
				);
				if ( count( $results ) >= $limit ) {
					break;
				}
			}
			if ( count( $rows ) < $batch_size ) {
				$exhausted = true;
				break;
			}
		}

		// Never silently treat a saturated public/restricted scan as the end of
		// the author's timeline; defer to File 03's degraded provider behavior.
		if ( count( $results ) < $limit && ! $exhausted ) {
			return new \WP_Error( 'file21_file03_scan_limit', 'The profile timeline source scan requires a bounded continuation.' );
		}
		return $results;
	}

	/** Validate the exact decoded t/i shape passed by File 03's signed cursor. */
	private static function file03_cursor_boundary( $cursor ) {
		if ( null === $cursor || '' === $cursor || array() === $cursor ) {
			return array( 't' => gmdate( 'Y-m-d H:i:s', time() + 300 ), 'i' => 'zzzz' );
		}
		if ( ! is_array( $cursor ) || ! isset( $cursor['t'], $cursor['i'] ) ||
			! is_string( $cursor['t'] ) || ! self::file03_valid_gmt_time( $cursor['t'] ) ||
			! is_string( $cursor['i'] ) || strlen( $cursor['i'] ) > 240 ||
			! preg_match( '/^[A-Za-z0-9_.:\/-]{1,240}$/D', $cursor['i'] ) ||
			false === strpos( $cursor['i'], ':' ) ) {
			return new \WP_Error( 'file21_file03_cursor_invalid', 'The File 03 timeline cursor is malformed.' );
		}
		if ( strtotime( $cursor['t'] . ' UTC' ) > time() + 300 ) {
			return new \WP_Error( 'file21_file03_cursor_invalid', 'The File 03 timeline cursor is in the future.' );
		}
		return $cursor;
	}

	/** Reject invalid or zero WordPress GMT datetimes rather than silently reordering. */
	private static function file03_valid_gmt_time( $time ) {
		if ( ! is_string( $time ) || ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $time ) ) {
			return false;
		}
		$stamp = strtotime( $time . ' UTC' );
		return false !== $stamp && gmdate( 'Y-m-d H:i:s', $stamp ) === $time;
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

		$projection  = class_exists( __NAMESPACE__ . '\\CanonicalIdentityAdapter' ) ? CanonicalIdentityAdapter::public_projection( (int) $result['user_id'] ) : array();
		$author_name = is_array( $projection ) && ! empty( $projection['name'] ) ? sanitize_text_field( $projection['name'] ) : '';
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

	/** Action bridge for File 03 profile surfaces and File 25-compatible presentation consumers. */
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