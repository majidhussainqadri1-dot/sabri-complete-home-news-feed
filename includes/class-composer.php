<?php
/**
 * Public post composer runtime.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and handles the public composer.
 */
final class Composer {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_post_sabri_public_composer', array( __CLASS__, 'handle_form' ) );
			add_action( 'admin_post_nopriv_sabri_public_composer', array( __CLASS__, 'handle_guest_form' ) );
		}
	}

	/**
	 * Render composer.
	 *
	 * @param array<string,mixed> $atts Attributes.
	 * @return string
	 */
	public static function render( array $atts = array() ) {
		unset( $atts );
		Assets::enqueue_composer();

		$settings = Settings::get();
		$user_id  = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;

		if ( ! SafeMode::feature_enabled( 'composer' ) ) {
			return FeedRenderer::template(
				'feed-error',
				array(
					'message' => __( 'The public composer is currently disabled.', 'sabri-complete-home-news-feed' ),
				)
			);
		}

		if ( ! ComposerPermissions::user_can_create( $user_id, $settings ) ) {
			$message = $user_id > 0 ? __( 'Your account cannot create general Home Feed posts.', 'sabri-complete-home-news-feed' ) : __( 'Please sign in to create a post.', 'sabri-complete-home-news-feed' );
			return FeedRenderer::template(
				'feed-error',
				array(
					'message' => $message,
				)
			);
		}

		return FeedRenderer::template(
			'composer',
			array(
				'settings'       => $settings,
				'feed_types'     => self::composer_feed_type_labels( $settings ),
				'visibility'     => FeedContext::allowed_composer_visibility( $settings, true ),
				'evidence_terms' => Taxonomies::evidence_level_terms(),
				'clinical_fields' => ComposerValidation::clinical_fields(),
				'research_fields' => ComposerValidation::research_fields(),
				'action_url'     => function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : '',
			)
		);
	}

	/**
	 * Handle form submission.
	 *
	 * @return void
	 */
	public static function handle_form() {
		if ( function_exists( 'check_admin_referer' ) ) {
			check_admin_referer( 'sabri_public_composer' );
		}

		$result = self::create_or_update_from_request( isset( $_POST ) ? wp_unslash( $_POST ) : array(), isset( $_FILES['sabri_media'] ) ? $_FILES['sabri_media'] : array() );
		if ( empty( $result['ok'] ) ) {
			self::finish_form_request( $result );
		}

		$url = ! empty( $result['permalink'] ) ? $result['permalink'] : ( function_exists( 'home_url' ) ? home_url( '/' ) : '' );
		if ( function_exists( 'wp_safe_redirect' ) ) {
			wp_safe_redirect( $url );
			exit;
		}
	}

	/**
	 * Guest form handler.
	 *
	 * @return void
	 */
	public static function handle_guest_form() {
		self::finish_form_request(
			self::error( 'login_required', __( 'Please sign in to create a post.', 'sabri-complete-home-news-feed' ), 401 )
		);
	}

	/**
	 * Create, update, or preview from request data.
	 *
	 * @param array<string,mixed> $input Input.
	 * @param array<string,mixed> $files Files.
	 * @param int                 $user_id User ID.
	 * @return array<string,mixed>
	 */
	public static function create_or_update_from_request( array $input, array $files = array(), $user_id = 0 ) {
		$settings = Settings::get();
		$user_id  = $user_id ? (int) $user_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );

		if ( ! ComposerPermissions::user_can_create( $user_id, $settings ) ) {
			return self::error( 'composer_denied', __( 'You do not have permission to create posts.', 'sabri-complete-home-news-feed' ), 403 );
		}

		if ( ! self::rate_limit_allowed( $user_id ) ) {
			return self::error( 'rate_limited', __( 'Please wait before trying again.', 'sabri-complete-home-news-feed' ), 429 );
		}

		$validation = ComposerValidation::validate( $input, $user_id, $settings );
		if ( empty( $validation['valid'] ) ) {
			return self::error( 'validation_failed', __( 'Please correct the highlighted fields.', 'sabri-complete-home-news-feed' ), 400, $validation['errors'] );
		}

		$data = $validation['data'];

		if ( 'preview' === $data['action'] ) {
			if ( empty( $settings['composer']['previews_enabled'] ) ) {
				return self::error( 'preview_disabled', __( 'Preview is disabled.', 'sabri-complete-home-news-feed' ), 403 );
			}
			return array(
				'ok'      => true,
				'preview' => self::preview_html( $data ),
			);
		}

		$status = ComposerPermissions::resolve_status_for_action( $data['action'], $user_id, $settings, $data['scheduled_date'] );
		if ( empty( $status['allowed'] ) ) {
			return self::error( $status['code'], $status['message'], 403 );
		}

		$media = empty( $files ) ? array( 'uploaded' => array(), 'errors' => array() ) : MediaHandler::upload_files( $files, $user_id, $data );
		if ( ! empty( $media['errors'] ) ) {
			return self::error( 'media_failed', __( 'One or more media files could not be attached.', 'sabri-complete-home-news-feed' ), 400, $media['errors'] );
		}

		$data['attachments'] = array_values( array_unique( array_merge( $data['attachments'], $media['uploaded'] ) ) );
		$data['gallery'] = array_values( array_unique( array_merge( $data['gallery'], self::image_attachment_ids( $media['uploaded'] ) ) ) );
		if ( ! empty( $data['privacy_review_required'] ) && in_array( $status['status'], array( 'publish', 'future' ), true ) ) {
			$status['status'] = 'pending';
		}
		$data['review_state'] = 'publish' === $status['status'] || 'future' === $status['status'] ? 'approved' : 'pending';

		$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		if ( $post_id > 0 && ! ComposerPermissions::user_can_edit_post( $post_id, $user_id ) ) {
			return self::error( 'edit_denied', __( 'You cannot edit this post.', 'sabri-complete-home-news-feed' ), 403 );
		}

		$postarr = array(
			'post_type'    => 'post',
			'post_status'  => $status['status'],
			'post_title'   => self::post_title_from_data( $data ),
			'post_content' => $data['content'],
			'post_author'  => $user_id,
			'comment_status' => ! empty( $data['comments_enabled'] ) ? 'open' : 'closed',
		);

		if ( 'future' === $status['status'] && ! empty( $data['scheduled_date'] ) ) {
			$postarr['post_date'] = $data['scheduled_date'];
		}

		if ( $post_id > 0 ) {
			$postarr['ID'] = $post_id;
			$data['edited_at'] = gmdate( 'Y-m-d H:i:s' );
			$saved_id = function_exists( 'wp_update_post' ) ? wp_update_post( $postarr, true ) : 0;
		} else {
			$saved_id = function_exists( 'wp_insert_post' ) ? wp_insert_post( $postarr, true ) : 0;
		}

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $saved_id ) ) {
			return self::error( 'save_failed', __( 'The post could not be saved.', 'sabri-complete-home-news-feed' ), 500 );
		}

		$saved_id = (int) $saved_id;
		if ( $saved_id <= 0 ) {
			return self::error( 'save_failed', __( 'The post could not be saved.', 'sabri-complete-home-news-feed' ), 500 );
		}

		PostMetadata::save_for_post( $saved_id, $data );
		MediaHandler::associate_attachments_with_post( $data['attachments'], $saved_id );
		FeedQuery::invalidate_cache();
		AuditLog::record( 'composer_post_saved', array( 'post_id' => $saved_id, 'status' => $status['status'] ) );

		return array(
			'ok'        => true,
			'post_id'   => $saved_id,
			'status'    => $status['status'],
			'permalink' => function_exists( 'get_permalink' ) ? get_permalink( $saved_id ) : '',
		);
	}

	/**
	 * Labels for allowed feed types.
	 *
	 * @param array<string,mixed> $settings Settings.
	 * @return array<string,string>
	 */
	private static function composer_feed_type_labels( array $settings ) {
		$all     = Taxonomies::feed_type_terms();
		$allowed = isset( $settings['composer']['allowed_feed_types'] ) && is_array( $settings['composer']['allowed_feed_types'] ) ? $settings['composer']['allowed_feed_types'] : FeedContext::phase2_feed_type_slugs();
		$out     = array();

		foreach ( $allowed as $slug ) {
			if ( isset( $all[ $slug ] ) ) {
				$out[ $slug ] = $all[ $slug ];
			}
		}

		return $out;
	}

	/**
	 * Preview markup.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return string
	 */
	private static function preview_html( array $data ) {
		return '<article class="sabri-hnf-preview"><h2>' . esc_html( self::post_title_from_data( $data ) ) . '</h2><div>' . wp_kses_post( $data['content'] ) . '</div></article>';
	}

	/**
	 * Determine post title.
	 *
	 * @param array<string,mixed> $data Data.
	 * @return string
	 */
	private static function post_title_from_data( array $data ) {
		if ( ! empty( $data['title'] ) ) {
			return $data['title'];
		}

		$text = trim( wp_strip_all_tags( $data['content'] ) );
		if ( '' === $text ) {
			return __( 'Untitled Draft', 'sabri-complete-home-news-feed' );
		}

		if ( function_exists( 'wp_trim_words' ) ) {
			return wp_trim_words( $text, 10, '' );
		}

		return substr( $text, 0, 80 );
	}

	/**
	 * Return uploaded image attachment IDs.
	 *
	 * @param array<int,int> $attachment_ids Attachment IDs.
	 * @return array<int,int>
	 */
	private static function image_attachment_ids( array $attachment_ids ) {
		$images = array();
		foreach ( $attachment_ids as $attachment_id ) {
			$mime = function_exists( 'get_post_mime_type' ) ? get_post_mime_type( $attachment_id ) : '';
			if ( 0 === strpos( (string) $mime, 'image/' ) ) {
				$images[] = (int) $attachment_id;
			}
		}

		return $images;
	}

	/**
	 * Lightweight per-user rate limit for composer actions.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function rate_limit_allowed( $user_id ) {
		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
			return true;
		}

		$key   = 'sabri_hnf_composer_rate_' . (int) $user_id;
		$count = (int) get_transient( $key );
		if ( $count >= 12 ) {
			return false;
		}

		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Structured error.
	 *
	 * @param string $code Code.
	 * @param string $message Message.
	 * @param int    $status HTTP status.
	 * @param array<int,mixed> $details Details.
	 * @return array<string,mixed>
	 */
	private static function error( $code, $message, $status, array $details = array() ) {
		return array(
			'ok'      => false,
			'code'    => $code,
			'message' => $message,
			'status'  => $status,
			'details' => $details,
		);
	}

	/**
	 * End a form request without stack traces.
	 *
	 * @param array<string,mixed> $result Result.
	 * @return void
	 */
	private static function finish_form_request( array $result ) {
		if ( function_exists( 'wp_die' ) ) {
			wp_die( esc_html( $result['message'] ), esc_html__( 'Composer unavailable', 'sabri-complete-home-news-feed' ), array( 'response' => isset( $result['status'] ) ? (int) $result['status'] : 400 ) );
		}
	}
}
