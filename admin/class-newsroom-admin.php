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

	/** Register administration hooks only. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
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
		echo '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html( $data['label'] ) . '</caption><thead><tr>';
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
		$value = static function ( $key, $default = '' ) use ( $meta ) {
			return isset( $meta[ $key ][0] ) ? $meta[ $key ][0] : $default;
		};
		echo '<div class="wrap sabri-news-composer"><h1>' . esc_html__( 'Editorial News Composer', 'sabri-complete-home-news-feed' ) . '</h1>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::SAVE_ACTION ) . '" /><input type="hidden" name="post_id" value="' . esc_attr( $post_id ) . '" />';
		wp_nonce_field( self::SAVE_ACTION );
		self::field( 'title', __( 'Title', 'sabri-complete-home-news-feed' ), $post ? $post->post_title : '', true );
		self::textarea( 'content', __( 'Article body', 'sabri-complete-home-news-feed' ), $post ? $post->post_content : '', 16 );
		self::field( 'subtitle', __( 'Subtitle', 'sabri-complete-home-news-feed' ), $value( '_sabri_news_subtitle' ) );
		self::textarea( 'summary', __( 'Summary', 'sabri-complete-home-news-feed' ), $value( '_sabri_news_summary' ), 5 );
		self::field( 'language', __( 'Language tag', 'sabri-complete-home-news-feed' ), $value( '_sabri_news_language', 'en-US' ) );
		self::field( 'priority', __( 'Priority (0–100)', 'sabri-complete-home-news-feed' ), $value( '_sabri_news_priority', 0 ), false, 'number' );
		self::select( 'section', __( 'Section', 'sabri-complete-home-news-feed' ), Phase4Contracts::sections(), self::first_term_slug( $post_id, 'sabri_news_section' ) );
		self::select( 'article_type', __( 'Article type', 'sabri-complete-home-news-feed' ), Phase4Contracts::article_types(), self::first_term_slug( $post_id, 'sabri_news_type' ) );
		self::field( 'topics', __( 'Topics (comma-separated slugs)', 'sabri-complete-home-news-feed' ), implode( ',', self::term_slugs( $post_id, 'sabri_news_topic' ) ) );
		self::field( 'countries', __( 'Countries (comma-separated slugs)', 'sabri-complete-home-news-feed' ), implode( ',', self::term_slugs( $post_id, 'sabri_news_country' ) ) );
		self::field( 'regions', __( 'Regions (comma-separated slugs)', 'sabri-complete-home-news-feed' ), implode( ',', self::term_slugs( $post_id, 'sabri_news_region' ) ) );
		self::field( 'featured_image_id', __( 'Featured image attachment ID', 'sabri-complete-home-news-feed' ), function_exists( 'get_post_thumbnail_id' ) ? get_post_thumbnail_id( $post_id ) : 0, false, 'number' );
		self::field( 'reviewing_editor_id', __( 'Reviewing editor user ID', 'sabri-complete-home-news-feed' ), $value( '_sabri_news_reviewing_editor_id', 0 ), false, 'number' );
		self::field( 'medical_reviewer_id', __( 'Medical reviewer user ID', 'sabri-complete-home-news-feed' ), $value( '_sabri_news_medical_reviewer_id', 0 ), false, 'number' );
		self::checkbox( 'fact_check_required', __( 'Fact check required', 'sabri-complete-home-news-feed' ), (bool) $value( '_sabri_news_fact_check_required', 0 ) );
		self::checkbox( 'medical_review_required', __( 'Medical review required', 'sabri-complete-home-news-feed' ), (bool) $value( '_sabri_news_medical_review_required', 0 ) );
		$current_state = $post_id ? NewsStatuses::sanitize_state( $value( Phase4Contracts::WORKFLOW_META_KEY, 'draft' ) ) : 'draft';
		$states = array_combine( NewsStatuses::states(), array_map( static function ( $state ) { return ucwords( str_replace( '-', ' ', $state ) ); }, NewsStatuses::states() ) );
		self::select( 'target_state', __( 'Workflow target', 'sabri-complete-home-news-feed' ), $states, $current_state );
		self::field( 'schedule_at', __( 'Schedule with timezone', 'sabri-complete-home-news-feed' ), $value( NewsSchedulingService::META_KEY ), false, 'text', 'Example: 2027-01-02T03:04:05+05:00' );
		submit_button( __( 'Save Editorial News', 'sabri-complete-home-news-feed' ) );
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

	private static function store_notice( array $result ) {
		if ( function_exists( 'set_transient' ) && function_exists( 'get_current_user_id' ) ) {
			set_transient( 'sabri_newsroom_notice_' . get_current_user_id(), $result, 5 * MINUTE_IN_SECONDS );
		}
	}

	private static function redirect( $page, array $args = array() ) {
		$url = add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	private static function field( $name, $label, $value, $required = false, $type = 'text', $description = '' ) {
		echo '<p><label for="sabri-news-' . esc_attr( $name ) . '"><strong>' . esc_html( $label ) . '</strong></label><br />';
		echo '<input class="regular-text" id="sabri-news-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '"' . ( $required ? ' required' : '' ) . ' />';
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

	private static function term_slugs( $post_id, $taxonomy ) {
		$terms = $post_id && function_exists( 'get_the_terms' ) ? get_the_terms( $post_id, $taxonomy ) : array();
		if ( ! is_array( $terms ) ) { return array(); }
		return array_values( array_filter( array_map( static function ( $term ) { return isset( $term->slug ) ? (string) $term->slug : ''; }, $terms ) ) );
	}

	private static function first_term_slug( $post_id, $taxonomy ) {
		$slugs = self::term_slugs( $post_id, $taxonomy );
		return $slugs ? $slugs[0] : '';
	}

	private static function strict_slug( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) ? $value : '';
	}
}
