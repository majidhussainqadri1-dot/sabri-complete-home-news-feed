<?php
/**
 * Explicit Founder/Administrator publication bridge for Editorial News.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restores the missing public-publication boundary without weakening the
 * fail-closed Phase 4B validator for ordinary callers.
 */
final class EditorialNewsPublicationBridge {
	/** Register only authenticated administration mutations and UI controls. */
	public static function register() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'admin_post_' . NewsroomAdmin::SAVE_ACTION, array( __CLASS__, 'intercept_composer_publication' ), 1 );
		add_action( 'admin_post_' . NewsroomAdmin::BULK_ACTION, array( __CLASS__, 'intercept_bulk_publication' ), 1 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_publication_controls' ), 20 );
	}

	/**
	 * Intercept only an explicitly confirmed `published` Composer target.
	 * All other Newsroom saves continue to the canonical NewsroomAdmin handler.
	 */
	public static function intercept_composer_publication() {
		$target = isset( $_POST['target_state'] ) ? NewsStatuses::sanitize_state( wp_unslash( $_POST['target_state'] ) ) : '';
		if ( 'published' !== $target ) {
			return;
		}

		self::require_post_request();
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		self::require_immediate_publisher( $post_id );
		check_admin_referer( NewsroomAdmin::SAVE_ACTION );

		if ( empty( $_POST['transition_confirmed'] ) ) {
			self::finish_composer(
				$post_id,
				self::result( false, 'workflow_confirmation_required', array( 'errors' => array( 'target_state' => 'confirmation_required' ) ) )
			);
		}

		$input = self::posted_composer_input();
		$current_state = $post_id > 0 && function_exists( 'get_post_meta' )
			? NewsStatuses::sanitize_state( get_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, true ) )
			: 'draft';
		$input['target_state'] = $current_state ? $current_state : 'draft';

		$saved = NewsService::save( $post_id, $input, array( 'method' => 'POST', 'nonce_verified' => true ) );
		if ( empty( $saved['success'] ) ) {
			self::finish_composer( $post_id, $saved );
		}

		$stored_id = ! empty( $saved['data']['post_id'] ) ? absint( $saved['data']['post_id'] ) : $post_id;
		self::finish_composer( $stored_id, self::publish_article( $stored_id ) );
	}

	/** Publish up to fifty explicitly selected hidden News records. */
	public static function intercept_bulk_publication() {
		$target = isset( $_POST['target_state'] ) ? NewsStatuses::sanitize_state( wp_unslash( $_POST['target_state'] ) ) : '';
		if ( 'published' !== $target ) {
			return;
		}

		self::require_post_request();
		self::require_immediate_publisher();
		check_admin_referer( NewsroomAdmin::BULK_ACTION );
		$queue = isset( $_POST['queue'] ) ? self::strict_slug( wp_unslash( $_POST['queue'] ) ) : 'own-drafts';
		$raw_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] ) ? wp_unslash( $_POST['post_ids'] ) : array();
		$ids = array_slice( array_values( array_filter( array_unique( array_map( 'absint', $raw_ids ) ) ) ), 0, 50 );

		if ( empty( $_POST['confirm_bulk'] ) || empty( $ids ) ) {
			self::finish_newsroom(
				$queue,
				self::result( false, 'bulk_transition_input_invalid', array( 'errors' => array( 'bulk' => 'selection_and_confirmation_required' ) ) )
			);
		}

		$completed = 0;
		$failed = array();
		foreach ( $ids as $post_id ) {
			$result = self::publish_article( $post_id );
			if ( ! empty( $result['success'] ) ) {
				++$completed;
			} else {
				$failed[ $post_id ] = isset( $result['code'] ) ? (string) $result['code'] : 'publication_failed';
			}
		}

		self::finish_newsroom(
			$queue,
			self::result(
				$completed > 0 && empty( $failed ),
				empty( $failed ) ? 'bulk_publication_completed' : ( $completed > 0 ? 'bulk_publication_partially_completed' : 'bulk_publication_failed' ),
				array( 'completed' => $completed, 'failed' => $failed, 'target_state' => 'published' )
			)
		);
	}

	/** Enqueue the missing trusted-publication controls only on Newsroom screens. */
	public static function enqueue_publication_controls() {
		if ( ! self::current_actor_is_immediate_publisher() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? self::strict_slug( wp_unslash( $_GET['page'] ) ) : '';
		if ( ! in_array( $page, array( NewsroomAdmin::PAGE, NewsroomAdmin::COMPOSER_PAGE ), true ) || ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		wp_enqueue_script(
			'sabri-editorial-news-publication-controls',
			SABRI_HNF_URL . 'assets/js/news-publication-controls.js',
			array(),
			SABRI_HNF_VERSION,
			true
		);
	}

	/** Perform one bounded, reversible publication mutation. */
	private static function publish_article( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 || ! NewsPolicy::writes_allowed() || ! self::current_actor_is_immediate_publisher() ) {
			return self::result( false, 'publication_authorization_denied' );
		}
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_editorial_news', $post_id ) || ! current_user_can( 'publish_editorial_news' ) ) {
			return self::result( false, 'publication_authorization_denied' );
		}
		$post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
		if ( ! is_object( $post ) || Phase4Contracts::POST_TYPE !== ( isset( $post->post_type ) ? (string) $post->post_type : '' ) ) {
			return self::result( false, 'publication_not_found' );
		}
		$author_id = isset( $post->post_author ) ? absint( $post->post_author ) : 0;
		if ( $author_id < 1 || ! CanonicalIdentityAdapter::can_publish_immediately( $author_id ) ) {
			return self::result( false, 'publication_author_not_trusted' );
		}

		$current_state = function_exists( 'get_post_meta' )
			? NewsStatuses::sanitize_state( get_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, true ) )
			: '';
		$current_state = $current_state ? $current_state : 'draft';
		if ( in_array( $current_state, array( 'retracted', 'archived', 'correction-pending' ), true ) ) {
			return self::result( false, 'publication_state_protected' );
		}
		if ( 'published' === $current_state && 'publish' === ( isset( $post->post_status ) ? (string) $post->post_status : '' ) ) {
			self::open_public_news_gate();
			return self::result( true, 'publication_unchanged', array( 'post_id' => $post_id, 'state' => 'published' ) );
		}
		if ( '' === trim( (string) $post->post_title ) || '' === trim( strip_tags( (string) $post->post_content ) ) ) {
			return self::result( false, 'publication_content_incomplete' );
		}

		self::prepare_public_defaults( $post_id, $post );
		$old_status = isset( $post->post_status ) ? (string) $post->post_status : 'draft';
		$updated = function_exists( 'wp_update_post' ) ? wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ), true ) : false;
		if ( false === $updated || ( function_exists( 'is_wp_error' ) && is_wp_error( $updated ) ) ) {
			return self::result( false, 'publication_post_update_failed' );
		}
		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, 'published' );
		}

		if ( ! NewsPublicSnapshot::capture( $post_id, true ) ) {
			if ( function_exists( 'wp_update_post' ) ) {
				wp_update_post( array( 'ID' => $post_id, 'post_status' => $old_status ), false );
			}
			if ( function_exists( 'update_post_meta' ) ) {
				update_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, $current_state );
			}
			return self::result( false, 'publication_snapshot_failed' );
		}

		self::open_public_news_gate();
		if ( class_exists( __NAMESPACE__ . '\\NewsCache' ) ) {
			NewsCache::purge_owned();
		}
		if ( class_exists( __NAMESPACE__ . '\\NewsAudit' ) ) {
			NewsAudit::record( $post_id, 'article_published_by_trusted_author', array( 'from_state' => $current_state, 'author_id' => $author_id ) );
		}
		return self::result( true, 'article_published', array( 'post_id' => $post_id, 'state' => 'published' ) );
	}

	/** Fill only missing summary and classification metadata from existing content. */
	private static function prepare_public_defaults( $post_id, $post ) {
		$summary = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $post_id, '_sabri_news_summary', true ) : '';
		if ( '' === trim( $summary ) ) {
			$excerpt = isset( $post->post_excerpt ) ? trim( (string) $post->post_excerpt ) : '';
			$content = isset( $post->post_content ) ? (string) $post->post_content : '';
			$summary = '' !== $excerpt ? $excerpt : self::summary_from_content( $content );
			if ( '' !== $summary && function_exists( 'update_post_meta' ) ) {
				update_post_meta( $post_id, '_sabri_news_summary', $summary );
			}
		}
		foreach ( array( 'sabri_news_section' => 'platform-news', 'sabri_news_type' => 'standard-news' ) as $taxonomy => $fallback ) {
			$terms = function_exists( 'get_the_terms' ) ? get_the_terms( $post_id, $taxonomy ) : array();
			if ( ( ! is_array( $terms ) || empty( $terms ) ) && function_exists( 'wp_set_object_terms' ) ) {
				wp_set_object_terms( $post_id, array( $fallback ), $taxonomy, false );
			}
		}
	}

	/** Open the canonical public News read gate only after a successful publish. */
	private static function open_public_news_gate() {
		if ( ! class_exists( __NAMESPACE__ . '\\NewsFeatureSettings' ) ) {
			return;
		}
		$was_enabled = NewsFeatureSettings::enabled( 'editorial_news_enabled' );
		NewsFeatureSettings::update( array( 'editorial_news_enabled' => 1 ) );
		if ( ! $was_enabled && function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
	}

	/** Read only allow-listed Composer input. */
	private static function posted_composer_input() {
		$input = array();
		foreach ( NewsComposerValidator::fields() as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$input[ $field ] = wp_unslash( $_POST[ $field ] );
			}
		}
		return $input;
	}

	/** Require exact Founder/Administrator publication authority. */
	private static function require_immediate_publisher( $post_id = 0 ) {
		$capability = $post_id > 0 ? 'edit_editorial_news' : 'create_editorial_news';
		$allowed = function_exists( 'current_user_can' ) && current_user_can( $capability, $post_id ) && current_user_can( 'publish_editorial_news' );
		if ( ! $allowed || ! self::current_actor_is_immediate_publisher() ) {
			wp_die( esc_html__( 'You do not have permission to publish this Editorial News item.', 'sabri-complete-home-news-feed' ) );
		}
	}

	/** Whether the current account has canonical immediate-publication authority. */
	private static function current_actor_is_immediate_publisher() {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		return $user_id > 0 && CanonicalIdentityAdapter::can_publish_immediately( $user_id );
	}

	/** Store the operation result in the Newsroom's existing bounded notice channel. */
	private static function store_notice( array $result ) {
		if ( function_exists( 'set_transient' ) ) {
			$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
			set_transient( NewsroomAdmin::NOTICE_PREFIX . $user_id, $result, 5 * MINUTE_IN_SECONDS );
		}
	}

	/** Redirect after Composer interception and stop the canonical callback chain. */
	private static function finish_composer( $post_id, array $result ) {
		self::store_notice( $result );
		$url = add_query_arg(
			array( 'page' => NewsroomAdmin::COMPOSER_PAGE, 'post_id' => absint( $post_id ), 'published' => ! empty( $result['success'] ) ? 1 : 0 ),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	/** Redirect after bulk interception and stop the canonical callback chain. */
	private static function finish_newsroom( $queue, array $result ) {
		self::store_notice( $result );
		$url = add_query_arg( array( 'page' => NewsroomAdmin::PAGE, 'queue' => $queue ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	private static function require_post_request() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : '';
		if ( 'POST' !== $method ) {
			wp_die( esc_html__( 'This action requires POST.', 'sabri-complete-home-news-feed' ) );
		}
	}

	private static function summary_from_content( $content ) {
		$text = trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $content ) ) );
		if ( '' === $text ) {
			return '';
		}
		return function_exists( 'wp_trim_words' ) ? (string) wp_trim_words( $text, 45, '…' ) : substr( $text, 0, 1000 );
	}

	private static function strict_slug( $value ) {
		return is_string( $value ) && strlen( $value ) <= 80 && 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) ? $value : '';
	}

	private static function result( $success, $code, array $data = array() ) {
		return array( 'success' => (bool) $success, 'code' => (string) $code, 'data' => $data );
	}
}
