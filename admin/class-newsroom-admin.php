<?php
/**
 * Phase 4B Editorial News administration screens.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers capability-safe newsroom queues and composer actions. */
final class NewsroomAdmin {
	const PAGE = 'sabri-feed-newsroom';
	const COMPOSER_PAGE = 'sabri-feed-news-composer';
	const SAVE_ACTION = 'sabri_feed_news_save';
	const TRANSITION_ACTION = 'sabri_feed_news_transition';
	const NOTICE_PREFIX = 'sabri_newsroom_notice_';
	const INPUT_PREFIX = 'sabri_newsroom_input_';

	/** Register administration hooks only. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
			add_action( 'admin_post_' . self::SAVE_ACTION, array( __CLASS__, 'handle_save' ) );
			add_action( 'admin_post_' . self::TRANSITION_ACTION, array( __CLASS__, 'handle_transition' ) );
		}
	}

	/** Frozen screen definitions used by menu registration and tests. */
	public static function screens() {
		return array(
			'newsroom' => array( 'slug' => self::PAGE, 'capability' => 'read_editorial_news', 'title' => __( 'Editorial Newsroom', 'sabri-complete-home-news-feed' ) ),
			'composer' => array( 'slug' => self::COMPOSER_PAGE, 'capability' => 'create_editorial_news', 'title' => __( 'News Composer', 'sabri-complete-home-news-feed' ) ),
		);
	}

	/** Load Media Library and private newsroom assets only on the two Phase 4B screens. */
	public static function enqueue_assets() {
		$page = isset( $_GET['page'] ) ? self::strict_slug( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! in_array( $page, array( self::PAGE, self::COMPOSER_PAGE ), true ) ) {
			return;
		}
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'sabri-phase4b-newsroom', SABRI_HNF_URL . 'assets/css/newsroom-admin.css', array(), SABRI_HNF_VERSION );
		}
		if ( self::COMPOSER_PAGE === $page ) {
			if ( function_exists( 'wp_enqueue_media' ) ) {
				wp_enqueue_media();
			}
			if ( function_exists( 'wp_enqueue_script' ) ) {
				wp_enqueue_script( 'sabri-phase4b-composer', SABRI_HNF_URL . 'assets/js/newsroom-editor.js', array(), SABRI_HNF_VERSION, true );
			}
			if ( function_exists( 'wp_localize_script' ) ) {
				wp_localize_script(
					'sabri-phase4b-composer',
					'SabriNewsroomComposer',
					array(
						'mediaTitle' => __( 'Select Editorial News featured image', 'sabri-complete-home-news-feed' ),
						'mediaButton' => __( 'Use this image', 'sabri-complete-home-news-feed' ),
						'siteTimezone' => self::site_timezone_label(),
					)
				);
			}
		}
	}

	/** Register newsroom submenus beneath the existing plugin administration menu. */
	public static function menu() {
		if ( ! function_exists( 'add_submenu_page' ) ) {
			return;
		}
		$screens = self::screens();
		add_submenu_page( 'sabri-feed-overview', $screens['newsroom']['title'], $screens['newsroom']['title'], $screens['newsroom']['capability'], self::PAGE, array( __CLASS__, 'render_newsroom' ) );
		add_submenu_page( 'sabri-feed-overview', $screens['composer']['title'], $screens['composer']['title'], $screens['composer']['capability'], self::COMPOSER_PAGE, array( __CLASS__, 'render_composer' ) );
	}

	/** Render isolated capability-aware queues without leaking inaccessible counts. */
	public static function render_newsroom() {
		self::require_capability( 'read_editorial_news' );
		$queue = isset( $_GET['queue'] ) ? self::strict_slug( wp_unslash( $_GET['queue'] ) ) : 'own-drafts';
		$page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$visible = NewsQueueService::visible_definitions();
		if ( ! isset( $visible[ $queue ] ) ) {
			$queue = key( $visible );
		}
		echo '<div class="wrap sabri-newsroom">';
		echo '<h1>' . esc_html__( 'Editorial Newsroom', 'sabri-complete-home-news-feed' ) . '</h1>';
		self::render_notice();
		echo '<p>' . esc_html__( 'Private editorial queues are shown only when the current user has the exact required capability.', 'sabri-complete-home-news-feed' ) . '</p>';
		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'Newsroom queues', 'sabri-complete-home-news-feed' ) . '">';
		foreach ( $visible as $slug => $definition ) {
			$url = add_query_arg( array( 'page' => self::PAGE, 'queue' => $slug ), admin_url( 'admin.php' ) );
			$class = $slug === $queue ? ' nav-tab-active' : '';
			echo '<a class="nav-tab' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '">' . esc_html( $definition['label'] ) . '</a>';
		}
		echo '</nav>';
		if ( ! $queue ) {
			echo '<p>' . esc_html__( 'No newsroom queue is available for this account.', 'sabri-complete-home-news-feed' ) . '</p></div>';
			return;
		}
		$result = NewsQueueService::query( $queue, $page, 20 );
		if ( empty( $result['success'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'This queue is unavailable.', 'sabri-complete-home-news-feed' ) . '</p></div></div>';
			return;
		}
		$data = $result['data'];
		echo '<table class="widefat striped sabri-newsroom-table"><caption class="screen-reader-text">' . esc_html( $data['label'] ) . '</caption><thead><tr>';
		foreach ( array( 'Title', 'Author', 'Workflow state', 'Modified', 'Action' ) as $heading ) {
			echo '<th scope="col">' . esc_html__( $heading, 'sabri-complete-home-news-feed' ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( empty( $data['posts'] ) ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No records are available in this queue.', 'sabri-complete-home-news-feed' ) . '</td></tr>';
		} else {
			foreach ( $data['posts'] as $post ) {
				$state = NewsStatuses::sanitize_state( get_post_meta( $post->ID, Phase4Contracts::WORKFLOW_META_KEY, true ) );
				$edit_url = add_query_arg( array( 'page' => self::COMPOSER_PAGE, 'post_id' => (int) $post->ID ), admin_url( 'admin.php' ) );
				echo '<tr><th scope="row">' . esc_html( $post->post_title ) . '</th><td>' . esc_html( (string) $post->post_author ) . '</td><td>' . esc_html( $state ) . '</td><td>' . esc_html( (string) $post->post_modified ) . '</td><td>';
				if ( empty( $data['read_only'] ) && NewsPolicy::can_edit( $post->ID ) ) {
					echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Open', 'sabri-complete-home-news-feed' ) . '</a>';
				} else {
					echo esc_html__( 'Read only', 'sabri-complete-home-news-feed' );
				}
				echo '</td></tr>';
			}
		}
		echo '</tbody></table>';
		if ( $data['pages'] > 1 && function_exists( 'paginate_links' ) ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array( 'total' => $data['pages'], 'current' => $data['page'] ) ) ) . '</div></div>';
		}
		echo '</div>';
	}

	/** Render the secure server-validated composer. */
	public static function render_composer() {
		$post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
		self::require_capability( $post_id ? 'edit_editorial_news' : 'create_editorial_news', $post_id );
		$post = $post_id ? get_post( $post_id ) : null;
		if ( $post_id && ( ! $post || Phase4Contracts::POST_TYPE !== $post->post_type ) ) {
			wp_die( esc_html__( 'The requested Editorial News record does not exist.', 'sabri-complete-home-news-feed' ) );
		}
		$meta = $post_id ? get_post_meta( $post_id ) : array();
		$preserved = self::consume_input();
		$value = static function ( $key, $default = '' ) use ( $meta ) {
			return isset( $meta[ $key ][0] ) ? $meta[ $key ][0] : $default;
		};
		$input = static function ( $key, $fallback = '' ) use ( $preserved ) {
			return array_key_exists( $key, $preserved ) ? $preserved[ $key ] : $fallback;
		};
		echo '<div class="wrap sabri-news-composer"><h1>' . esc_html__( 'Editorial News Composer', 'sabri-complete-home-news-feed' ) . '</h1>';
		self::render_notice();
		if ( $post_id && ! empty( $_GET['preview'] ) ) {
			self::render_private_preview( $post );
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate>';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::SAVE_ACTION ) . '" /><input type="hidden" name="post_id" value="' . esc_attr( $post_id ) . '" />';
		wp_nonce_field( self::SAVE_ACTION );
		self::field( 'title', __( 'Title', 'sabri-complete-home-news-feed' ), $input( 'title', $post ? $post->post_title : '' ), true );
		self::textarea( 'content', __( 'Article body', 'sabri-complete-home-news-feed' ), $input( 'content', $post ? $post->post_content : '' ), 16 );
		self::field( 'subtitle', __( 'Subtitle', 'sabri-complete-home-news-feed' ), $input( 'subtitle', $value( '_sabri_news_subtitle' ) ) );
		self::textarea( 'summary', __( 'Summary', 'sabri-complete-home-news-feed' ), $input( 'summary', $value( '_sabri_news_summary' ) ), 5 );
		self::field( 'language', __( 'Language tag', 'sabri-complete-home-news-feed' ), $input( 'language', $value( '_sabri_news_language', 'en-US' ) ) );
		self::field( 'priority', __( 'Priority (0–100)', 'sabri-complete-home-news-feed' ), $input( 'priority', $value( '_sabri_news_priority', 0 ) ), false, 'number' );
		self::select( 'section', __( 'Section', 'sabri-complete-home-news-feed' ), Phase4Contracts::sections(), $input( 'section', self::first_term_slug( $post_id, 'sabri_news_section' ) ) );
		self::select( 'article_type', __( 'Article type', 'sabri-complete-home-news-feed' ), Phase4Contracts::article_types(), $input( 'article_type', self::first_term_slug( $post_id, 'sabri_news_type' ) ) );
		self::field( 'topics', __( 'Topics (comma-separated slugs)', 'sabri-complete-home-news-feed' ), $input( 'topics', implode( ',', self::term_slugs( $post_id, 'sabri_news_topic' ) ) ) );
		self::field( 'countries', __( 'Countries (comma-separated slugs)', 'sabri-complete-home-news-feed' ), $input( 'countries', implode( ',', self::term_slugs( $post_id, 'sabri_news_country' ) ) ) );
		self::field( 'regions', __( 'Regions (comma-separated slugs)', 'sabri-complete-home-news-feed' ), $input( 'regions', implode( ',', self::term_slugs( $post_id, 'sabri_news_region' ) ) ) );
		self::media_field( $input( 'featured_image_id', function_exists( 'get_post_thumbnail_id' ) ? get_post_thumbnail_id( $post_id ) : 0 ) );
		self::field( 'reviewing_editor_id', __( 'Reviewing editor user ID', 'sabri-complete-home-news-feed' ), $input( 'reviewing_editor_id', $value( '_sabri_news_reviewing_editor_id', 0 ) ), false, 'number' );
		self::field( 'medical_reviewer_id', __( 'Medical reviewer user ID', 'sabri-complete-home-news-feed' ), $input( 'medical_reviewer_id', $value( '_sabri_news_medical_reviewer_id', 0 ) ), false, 'number' );
		self::checkbox( 'fact_check_required', __( 'Fact check required', 'sabri-complete-home-news-feed' ), (bool) $input( 'fact_check_required', $value( '_sabri_news_fact_check_required', 0 ) ) );
		self::checkbox( 'medical_review_required', __( 'Medical review required', 'sabri-complete-home-news-feed' ), (bool) $input( 'medical_review_required', $value( '_sabri_news_medical_review_required', 0 ) ) );
		$current_state = $post_id ? NewsStatuses::sanitize_state( $value( Phase4Contracts::WORKFLOW_META_KEY, 'draft' ) ) : 'draft';
		$states = array_combine( NewsStatuses::states(), array_map( static function ( $state ) { return ucwords( str_replace( '-', ' ', $state ) ); }, NewsStatuses::states() ) );
		self::select( 'target_state', __( 'Workflow target', 'sabri-complete-home-news-feed' ), $states, $input( 'target_state', $current_state ) );
		self::field( 'schedule_at', __( 'Schedule with timezone', 'sabri-complete-home-news-feed' ), $input( 'schedule_at', $value( NewsSchedulingService::META_KEY ) ), false, 'text', sprintf( __( 'Site timezone: %s. Include an explicit UTC offset. The normalized UTC value appears below.', 'sabri-complete-home-news-feed' ), self::site_timezone_label() ) );
		echo '<p id="sabri-news-schedule-utc" class="description" aria-live="polite"></p>';
		submit_button( __( 'Save Editorial News', 'sabri-complete-home-news-feed' ) );
		if ( $post_id ) {
			$preview_url = add_query_arg( array( 'page' => self::COMPOSER_PAGE, 'post_id' => $post_id, 'preview' => 1 ), admin_url( 'admin.php' ) );
			echo '<a class="button sabri-news-preview-button" href="' . esc_url( $preview_url ) . '">' . esc_html__( 'Private preview', 'sabri-complete-home-news-feed' ) . '</a>';
		}
		echo '</form></div>';
	}

	/** Handle composer saves through the application service only. */
	public static function handle_save() {
		self::require_post_request();
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		self::require_capability( $post_id ? 'edit_editorial_news' : 'create_editorial_news', $post_id );
		check_admin_referer( self::SAVE_ACTION );
		$input = array();
		foreach ( NewsComposerValidator::fields() as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$input[ $field ] = wp_unslash( $_POST[ $field ] );
			}
		}
		$result = NewsService::save( $post_id, $input, array( 'method' => 'POST', 'nonce_verified' => true ) );
		self::store_notice( $result );
		if ( empty( $result['success'] ) ) {
			self::store_input( $input );
		}
		$target_id = ! empty( $result['data']['post_id'] ) ? (int) $result['data']['post_id'] : $post_id;
		self::redirect( self::COMPOSER_PAGE, array( 'post_id' => $target_id, 'saved' => ! empty( $result['success'] ) ? 1 : 0 ) );
	}

	/** Handle explicit state transitions through the workflow service. */
	public static function handle_transition() {
		self::require_post_request();
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		self::require_capability( 'edit_editorial_news', $post_id );
		check_admin_referer( self::TRANSITION_ACTION );
		$target = isset( $_POST['target_state'] ) ? wp_unslash( $_POST['target_state'] ) : '';
		$result = NewsService::transition( $post_id, $target, array( 'method' => 'POST', 'nonce_verified' => true ) );
		self::store_notice( $result );
		self::redirect( self::COMPOSER_PAGE, array( 'post_id' => $post_id, 'transitioned' => ! empty( $result['success'] ) ? 1 : 0 ) );
	}

	private static function require_post_request() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : '';
		if ( 'POST' !== $method ) {
			wp_die( esc_html__( 'This action requires POST.', 'sabri-complete-home-news-feed' ) );
		}
	}

	private static function require_capability( $capability, $post_id = 0 ) {
		$allowed = $post_id ? current_user_can( $capability, $post_id ) : current_user_can( $capability );
		if ( ! $allowed ) {
			wp_die( esc_html__( 'You do not have permission to access this newsroom operation.', 'sabri-complete-home-news-feed' ) );
		}
	}

	private static function transient_key( $prefix ) {
		return $prefix . ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
	}

	private static function store_notice( array $result ) {
		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::transient_key( self::NOTICE_PREFIX ), $result, 5 * MINUTE_IN_SECONDS );
		}
	}

	private static function render_notice() {
		if ( ! function_exists( 'get_transient' ) ) {
			return;
		}
		$key = self::transient_key( self::NOTICE_PREFIX );
		$result = get_transient( $key );
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( $key );
		}
		if ( ! is_array( $result ) ) {
			return;
		}
		$success = ! empty( $result['success'] );
		$class = $success ? 'notice-success' : 'notice-error';
		$code = isset( $result['code'] ) ? (string) $result['code'] : 'newsroom_result';
		echo '<div class="notice ' . esc_attr( $class ) . '" role="status"><p><strong>' . esc_html( ucwords( str_replace( '_', ' ', $code ) ) ) . '</strong></p>';
		if ( ! empty( $result['data']['errors'] ) && is_array( $result['data']['errors'] ) ) {
			echo '<ul class="sabri-news-errors">';
			foreach ( $result['data']['errors'] as $field => $error ) {
				echo '<li><a href="#sabri-news-' . esc_attr( $field ) . '">' . esc_html( $field . ': ' . $error ) . '</a></li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	private static function store_input( array $input ) {
		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::transient_key( self::INPUT_PREFIX ), array_intersect_key( $input, array_fill_keys( NewsComposerValidator::fields(), true ) ), 5 * MINUTE_IN_SECONDS );
		}
	}

	private static function consume_input() {
		if ( ! function_exists( 'get_transient' ) ) {
			return array();
		}
		$key = self::transient_key( self::INPUT_PREFIX );
		$input = get_transient( $key );
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( $key );
		}
		return is_array( $input ) ? $input : array();
	}

	private static function render_private_preview( $post ) {
		if ( ! $post || ! NewsPolicy::can_edit( $post->ID ) ) {
			return;
		}
		echo '<section class="sabri-news-private-preview" aria-labelledby="sabri-news-preview-heading">';
		echo '<h2 id="sabri-news-preview-heading">' . esc_html__( 'Private editorial preview', 'sabri-complete-home-news-feed' ) . '</h2>';
		echo '<h3>' . esc_html( $post->post_title ) . '</h3>';
		echo '<div class="sabri-news-preview-content">' . wp_kses_post( apply_filters( 'the_content', $post->post_content ) ) . '</div>';
		echo '</section>';
	}

	private static function redirect( $page, array $args = array() ) {
		$url = add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	private static function field( $name, $label, $value, $required = false, $type = 'text', $description = '' ) {
		echo '<p><label for="sabri-news-' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label><br />';
		echo '<input class="regular-text" id="sabri-news-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required aria-required="true"' : '' ) . ' />';
		if ( $description ) { echo '<br /><span class="description">' . esc_html( $description ) . '</span>'; }
		echo '</p>';
	}

	private static function textarea( $name, $label, $value, $rows ) {
		echo '<p><label for="sabri-news-' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label><br /><textarea class="large-text" rows="' . esc_attr( $rows ) . '" id="sabri-news-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea></p>';
	}

	private static function select( $name, $label, array $options, $current ) {
		echo '<p><label for="sabri-news-' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label><br /><select id="sabri-news-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'Select', 'sabri-complete-home-news-feed' ) . '</option>';
		foreach ( $options as $value => $option_label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $option_label ) . '</option>';
		}
		echo '</select></p>';
	}

	private static function checkbox( $name, $label, $checked_value ) {
		echo '<p><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $checked_value, true, false ) . ' /> ' . esc_html( $label ) . '</label></p>';
	}

	private static function media_field( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		echo '<fieldset class="sabri-news-media-field"><legend><strong>' . esc_html__( 'Featured image', 'sabri-complete-home-news-feed' ) . '</strong></legend>';
		echo '<input id="sabri-news-featured_image_id" name="featured_image_id" type="hidden" value="' . esc_attr( $attachment_id ) . '" />';
		echo '<div id="sabri-news-featured-preview">';
		if ( $attachment_id && function_exists( 'wp_get_attachment_image' ) ) {
			echo wp_kses_post( wp_get_attachment_image( $attachment_id, 'medium' ) );
		}
		echo '</div><button type="button" class="button" id="sabri-news-select-image">' . esc_html__( 'Select from Media Library', 'sabri-complete-home-news-feed' ) . '</button> ';
		echo '<button type="button" class="button-link-delete" id="sabri-news-remove-image">' . esc_html__( 'Remove image', 'sabri-complete-home-news-feed' ) . '</button></fieldset>';
	}

	private static function term_slugs( $post_id, $taxonomy ) {
		$terms = $post_id && function_exists( 'get_the_terms' ) ? get_the_terms( $post_id, $taxonomy ) : array();
		if ( ! is_array( $terms ) ) { return array(); }
		return array_values( array_filter( array_map( static function ( $term ) { return isset( $term->slug ) ? (string) $term->slug : ''; }, $terms ) ) );
	}

	private static function first_term_slug( $post_id, $taxonomy ) {
		$slugs = self::term_slugs( $post_id, $taxonomy );
		return $slugs ? $slugs[0] : '';
	}

	private static function site_timezone_label() {
		if ( function_exists( 'wp_timezone_string' ) ) {
			$timezone = wp_timezone_string();
			return $timezone ? $timezone : 'UTC';
		}
		return 'UTC';
	}

	private static function strict_slug( $value ) {
		return is_string( $value ) && strlen( $value ) <= 80 && 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) ? $value : '';
	}
}
