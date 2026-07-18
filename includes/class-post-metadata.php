<?php
/**
 * Post metadata helpers for Phase 2 runtime.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders plugin-owned post metadata.
 */
final class PostMetadata {
	const META_TYPE = '_sabri_feed_type';
	const META_VISIBILITY = '_sabri_feed_visibility';
	const META_EVIDENCE_LEVEL = '_sabri_evidence_level';
	const META_REVIEW_STATE = '_sabri_feed_review_state';
	const META_COMMENTS_ENABLED = '_sabri_comments_enabled';
	const META_LANGUAGE = '_sabri_language';
	const META_REGION = '_sabri_country_region';
	const META_DISCLAIMER_CONFIRMED = '_sabri_medical_disclaimer_confirmed';
	const META_PATIENT_PRIVACY_CONFIRMED = '_sabri_patient_privacy_confirmed';
	const META_ATTACHMENTS = '_sabri_feed_attachments';
	const META_GALLERY = '_sabri_feed_gallery';
	const META_FEATURED = '_sabri_feed_featured';
	const META_PINNED = '_sabri_feed_pinned';
	const META_CLINICAL_CASE = '_sabri_clinical_case';
	const META_RESEARCH = '_sabri_research';
	const META_EDITED_AT = '_sabri_edited_at';
	const LEGACY_BLANK_REVIEW_STATE_OPTION = 'sabri_feed_allow_legacy_blank_review_state';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', array( __CLASS__, 'register_meta' ), 21 );
			add_action( 'template_redirect', array( __CLASS__, 'enforce_single_visibility' ), 1 );
			add_action( 'pre_get_posts', array( __CLASS__, 'filter_public_queries' ) );
		}

		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'rest_prepare_post', array( __CLASS__, 'filter_rest_post_response' ), 10, 3 );
		}
	}

	/**
	 * Register public metadata with explicit auth callbacks.
	 *
	 * @return void
	 */
	public static function register_meta() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;

		if ( ! function_exists( 'register_post_meta' ) ) {
			return;
		}

		$string_meta = array(
			self::META_TYPE,
			self::META_VISIBILITY,
			self::META_EVIDENCE_LEVEL,
			self::META_REVIEW_STATE,
			self::META_LANGUAGE,
			self::META_REGION,
			self::META_EDITED_AT,
		);

		foreach ( $string_meta as $meta_key ) {
			register_post_meta(
				'post',
				$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => array( __CLASS__, 'meta_auth_callback' ),
					'sanitize_callback' => 'sanitize_text_field',
				)
			);
		}

		foreach ( array( self::META_COMMENTS_ENABLED, self::META_DISCLAIMER_CONFIRMED, self::META_PATIENT_PRIVACY_CONFIRMED, self::META_FEATURED, self::META_PINNED ) as $meta_key ) {
			register_post_meta(
				'post',
				$meta_key,
				array(
					'type'              => 'boolean',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => array( __CLASS__, 'meta_auth_callback' ),
					'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
				)
			);
		}

		foreach ( array( self::META_ATTACHMENTS, self::META_GALLERY, self::META_CLINICAL_CASE, self::META_RESEARCH ) as $meta_key ) {
			register_post_meta(
				'post',
				$meta_key,
				array(
					'type'              => 'array',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => array( __CLASS__, 'meta_auth_callback' ),
					'sanitize_callback' => array( __CLASS__, 'sanitize_array' ),
				)
			);
		}
	}

	/**
	 * Metadata edit authorization.
	 *
	 * @param bool   $allowed Current value.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id Post ID.
	 * @param int    $user_id User ID.
	 * @return bool
	 */
	public static function meta_auth_callback( $allowed, $meta_key, $post_id, $user_id ) {
		unset( $allowed, $meta_key );

		$post_id = (int) $post_id;
		$user_id = (int) $user_id;
		if ( $post_id > 0 ) {
			return ComposerPermissions::user_can_edit_post( $post_id, $user_id ) || ComposerPermissions::user_can_moderate();
		}

		return ComposerPermissions::user_can_create( $user_id );
	}

	/**
	 * Save sanitized metadata for a post.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $payload Sanitized payload.
	 * @return void
	 */
	public static function save_for_post( $post_id, array $payload ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || ! function_exists( 'update_post_meta' ) ) {
			return;
		}

		$type       = isset( $payload['feed_type'] ) ? sanitize_key( $payload['feed_type'] ) : 'standard-post';
		$visibility = isset( $payload['visibility'] ) ? sanitize_key( $payload['visibility'] ) : 'public';

		update_post_meta( $post_id, self::META_TYPE, $type );
		update_post_meta( $post_id, self::META_VISIBILITY, $visibility );
		update_post_meta( $post_id, self::META_REVIEW_STATE, isset( $payload['review_state'] ) ? sanitize_key( $payload['review_state'] ) : 'approved' );
		update_post_meta( $post_id, self::META_COMMENTS_ENABLED, ! empty( $payload['comments_enabled'] ) ? 1 : 0 );
		update_post_meta( $post_id, self::META_LANGUAGE, isset( $payload['language'] ) ? self::clean_text( $payload['language'] ) : '' );
		update_post_meta( $post_id, self::META_REGION, isset( $payload['country_region'] ) ? self::clean_text( $payload['country_region'] ) : '' );
		update_post_meta( $post_id, self::META_DISCLAIMER_CONFIRMED, ! empty( $payload['medical_disclaimer_confirmed'] ) ? 1 : 0 );
		update_post_meta( $post_id, self::META_PATIENT_PRIVACY_CONFIRMED, ! empty( $payload['patient_privacy_confirmed'] ) ? 1 : 0 );
		update_post_meta( $post_id, self::META_ATTACHMENTS, self::sanitize_array( isset( $payload['attachments'] ) ? $payload['attachments'] : array() ) );
		update_post_meta( $post_id, self::META_GALLERY, self::sanitize_array( isset( $payload['gallery'] ) ? $payload['gallery'] : array() ) );
		update_post_meta( $post_id, self::META_CLINICAL_CASE, self::sanitize_array( isset( $payload['clinical_case'] ) ? $payload['clinical_case'] : array() ) );
		update_post_meta( $post_id, self::META_RESEARCH, self::sanitize_array( isset( $payload['research'] ) ? $payload['research'] : array() ) );

		if ( ! empty( $payload['evidence_level'] ) ) {
			update_post_meta( $post_id, self::META_EVIDENCE_LEVEL, sanitize_key( $payload['evidence_level'] ) );
		}

		if ( ! empty( $payload['edited_at'] ) ) {
			update_post_meta( $post_id, self::META_EDITED_AT, self::clean_text( $payload['edited_at'] ) );
		}

		if ( function_exists( 'wp_set_object_terms' ) ) {
			wp_set_object_terms( $post_id, $type, 'sabri_feed_type', false );
			wp_set_object_terms( $post_id, $visibility, 'sabri_visibility', false );
			if ( ! empty( $payload['evidence_level'] ) ) {
				wp_set_object_terms( $post_id, sanitize_key( $payload['evidence_level'] ), 'sabri_evidence_level', false );
			}
			if ( ! empty( $payload['topic'] ) ) {
				wp_set_object_terms( $post_id, self::clean_text( $payload['topic'] ), 'sabri_feed_topic', false );
			}
		}
	}

	/**
	 * Get feed type for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function feed_type( $post_id ) {
		$value = self::meta( $post_id, self::META_TYPE );
		return '' !== $value ? sanitize_key( $value ) : 'standard-post';
	}

	/**
	 * Get visibility for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function visibility( $post_id ) {
		$value = self::meta( $post_id, self::META_VISIBILITY );
		return '' !== $value ? sanitize_key( $value ) : 'public';
	}

	/**
	 * Whether a post may appear in public/custom feed contexts.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function user_can_view( $post_id, $user_id = 0 ) {
		$post_id = (int) $post_id;
		$user_id = $user_id ? (int) $user_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		$status  = function_exists( 'get_post_status' ) ? get_post_status( $post_id ) : 'publish';

		if ( 'publish' !== $status ) {
			if ( ComposerPermissions::user_can_moderate() ) {
				return true;
			}
			$author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0;
			return $author_id > 0 && $author_id === $user_id && ComposerPermissions::user_can_edit_post( $post_id, $user_id );
		}

		if ( ! self::review_state_publicly_visible( $post_id ) ) {
			return false;
		}

		$visibility = self::visibility( $post_id );
		if ( 'public' === $visibility ) {
			return true;
		}

		if ( 'private' === $visibility ) {
			$author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0;
			return $author_id > 0 && $author_id === $user_id && ComposerPermissions::user_can_edit_post( $post_id, $user_id );
		}

		return in_array( $visibility, FeedContext::visible_feed_scopes_for_user( $user_id ), true );
	}

	/**
	 * Review state.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function review_state( $post_id ) {
		$value = self::meta( $post_id, self::META_REVIEW_STATE );
		return '' !== $value ? sanitize_key( $value ) : '';
	}

	/**
	 * Review states excluded from public feed output.
	 *
	 * @return array<int,string>
	 */
	public static function excluded_review_states() {
		return array( 'pending', 'removed', 'rejected', 'archived', 'limited' );
	}

	/**
	 * Review states allowed on public surfaces.
	 *
	 * @return array<int,string>
	 */
	public static function public_review_states() {
		return array( 'approved' );
	}

	/**
	 * Whether blank legacy review state may remain visible.
	 *
	 * @return bool
	 */
	public static function legacy_blank_review_state_allowed() {
		$allowed = function_exists( 'get_option' ) ? (bool) get_option( self::LEGACY_BLANK_REVIEW_STATE_OPTION, false ) : false;
		return function_exists( 'apply_filters' ) ? (bool) apply_filters( 'sabri_hnf_allow_legacy_blank_review_state', $allowed ) : $allowed;
	}

	/**
	 * Whether a post review state can appear on public surfaces.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function review_state_publicly_visible( $post_id ) {
		$state = self::review_state( $post_id );
		if ( '' === $state ) {
			return self::legacy_blank_review_state_allowed();
		}

		return in_array( $state, self::public_review_states(), true );
	}

	/**
	 * Get structured metadata for rendering.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key Meta key.
	 * @return array<string,mixed>
	 */
	public static function structured( $post_id, $key ) {
		$meta_key = 'clinical_case' === $key ? self::META_CLINICAL_CASE : self::META_RESEARCH;
		$value    = function_exists( 'get_post_meta' ) ? get_post_meta( (int) $post_id, $meta_key, true ) : array();
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Render single-post contextual sections.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function render_single_context( $post_id ) {
		$post_id = (int) $post_id;
		$type    = self::feed_type( $post_id );
		$html    = '';

		if ( 'clinical-case' === $type ) {
			$html .= self::render_structured_section( __( 'Clinical Case', 'sabri-complete-home-news-feed' ), self::structured( $post_id, 'clinical_case' ) );
		}

		if ( 'research' === $type ) {
			$html .= self::render_structured_section( __( 'Research', 'sabri-complete-home-news-feed' ), self::structured( $post_id, 'research' ) );
		}

		$attachments = function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, self::META_ATTACHMENTS, true ) : array();
		if ( is_array( $attachments ) && ! empty( $attachments ) ) {
			$settings = Settings::get();
			$limit = isset( $settings['media']['max_items'] ) ? max( 1, (int) $settings['media']['max_items'] ) : 4;
			$html .= FeedRenderer::template( 'media-gallery', array( 'attachment_ids' => array_slice( MediaHandler::visible_attachment_ids( array_map( 'absint', $attachments ) ), 0, $limit ) ) );
		}

		$html .= self::render_related_foundation( $post_id, $type );

		if ( in_array( $type, array( 'clinical-case', 'research', 'public-health' ), true ) ) {
			$html .= '<aside class="sabri-hnf-medical-disclaimer">' . esc_html__( 'This content is educational and does not replace professional medical advice, diagnosis, or treatment.', 'sabri-complete-home-news-feed' ) . '</aside>';
		}

		return $html;
	}

	/**
	 * Enforce visibility on single post requests.
	 *
	 * @return void
	 */
	public static function enforce_single_visibility() {
		if ( function_exists( 'is_singular' ) && ! is_singular( 'post' ) ) {
			return;
		}

		$post_id = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
		if ( $post_id <= 0 || self::user_can_view( $post_id ) ) {
			return;
		}

		if ( function_exists( 'status_header' ) ) {
			status_header( 404 );
		}

		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		if ( function_exists( 'wp_die' ) ) {
			wp_die( esc_html__( 'This content is unavailable.', 'sabri-complete-home-news-feed' ), esc_html__( 'Unavailable', 'sabri-complete-home-news-feed' ), array( 'response' => 404 ) );
		}
	}

	/**
	 * Add visibility filters to public main queries.
	 *
	 * @param mixed $query Query.
	 * @return void
	 */
	public static function filter_public_queries( $query ) {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return;
		}

		if ( ! is_object( $query ) || ! method_exists( $query, 'is_main_query' ) || ! $query->is_main_query() || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		if ( $post_type && 'post' !== $post_type && ! ( is_array( $post_type ) && in_array( 'post', $post_type, true ) ) ) {
			return;
		}

		$existing = $query->get( 'meta_query' );
		$existing = is_array( $existing ) ? $existing : array();
		$existing[] = self::visibility_meta_clause();
		$existing[] = self::review_state_meta_clause();
		$query->set( 'meta_query', $existing );
	}

	/**
	 * Remove restricted REST post data.
	 *
	 * @param mixed $response Response.
	 * @param mixed $post Post.
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function filter_rest_post_response( $response, $post, $request ) {
		unset( $request );

		$post_id = is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : 0;
		if ( $post_id <= 0 || self::user_can_view( $post_id ) ) {
			return $response;
		}

		if ( is_object( $response ) && method_exists( $response, 'set_data' ) ) {
			$response->set_data(
				array(
					'id'      => $post_id,
					'status'  => 'restricted',
					'message' => __( 'This content is unavailable.', 'sabri-complete-home-news-feed' ),
				)
			);
		}

		return $response;
	}

	/**
	 * Visibility meta query clause for public queries.
	 *
	 * @return array<string,mixed>
	 */
	public static function visibility_meta_clause() {
		return array(
			'relation' => 'OR',
			array(
				'key'     => self::META_VISIBILITY,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => self::META_VISIBILITY,
				'value'   => FeedContext::visible_feed_scopes_for_user(),
				'compare' => 'IN',
			),
		);
	}

	/**
	 * Review state meta query clause for public queries.
	 *
	 * @return array<string,mixed>
	 */
	public static function review_state_meta_clause() {
		$clause = array(
			'relation' => 'OR',
			array(
				'key'   => self::META_REVIEW_STATE,
				'value' => self::public_review_states(),
				'compare' => 'IN',
			),
		);

		if ( self::legacy_blank_review_state_allowed() ) {
			$clause[] = array(
				'key'     => self::META_REVIEW_STATE,
				'compare' => 'NOT EXISTS',
			);
		}

		return $clause;
	}

	/**
	 * Render structured metadata as a definition list.
	 *
	 * @param string              $title Title.
	 * @param array<string,mixed> $data Data.
	 * @return string
	 */
	private static function render_structured_section( $title, array $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		$html = '<section class="sabri-hnf-structured-section"><h2>' . esc_html( $title ) . '</h2><dl>';
		foreach ( $data as $key => $value ) {
			if ( '' === trim( (string) $value ) ) {
				continue;
			}
			$html .= '<dt>' . esc_html( ucwords( str_replace( '_', ' ', (string) $key ) ) ) . '</dt>';
			$html .= '<dd>' . wp_kses_post( nl2br( esc_html( (string) $value ) ) ) . '</dd>';
		}
		$html .= '</dl></section>';

		return $html;
	}

	/**
	 * Render a small related-posts foundation from real posts only.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type Feed type.
	 * @return string
	 */
	private static function render_related_foundation( $post_id, $type ) {
		if ( ! class_exists( 'WP_Query' ) || '' === $type ) {
			return '';
		}

		$query = new \WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => array( 'publish' ),
				'posts_per_page'      => 3,
				'post__not_in'        => array( (int) $post_id ),
				'ignore_sticky_posts' => true,
				'tax_query'           => array(
					array(
						'taxonomy' => 'sabri_feed_type',
						'field'    => 'slug',
						'terms'    => array( sanitize_key( $type ) ),
					),
				),
				'meta_query'          => array(
					'relation' => 'AND',
					self::visibility_meta_clause(),
					self::review_state_meta_clause(),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			return '';
		}

		$html = '<section class="sabri-hnf-related"><h2>' . esc_html__( 'Related Posts', 'sabri-complete-home-news-feed' ) . '</h2><ul>';
		foreach ( $query->posts as $post ) {
			$related_id = isset( $post->ID ) ? (int) $post->ID : 0;
			if ( $related_id <= 0 || ! self::user_can_view( $related_id ) ) {
				continue;
			}
			$html .= '<li><a href="' . esc_url( function_exists( 'get_permalink' ) ? get_permalink( $related_id ) : '#' ) . '">' . esc_html( function_exists( 'get_the_title' ) ? get_the_title( $related_id ) : '' ) . '</a></li>';
		}
		$html .= '</ul></section>';

		return $html;
	}

	/**
	 * Sanitize boolean meta.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function sanitize_bool( $value ) {
		return empty( $value ) ? 0 : 1;
	}

	/**
	 * Sanitize array meta.
	 *
	 * @param mixed $value Value.
	 * @return array<string,mixed>
	 */
	public static function sanitize_array( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();
		foreach ( $value as $key => $item ) {
			$key = is_int( $key ) ? $key : sanitize_key( $key );
			if ( is_array( $item ) ) {
				$out[ $key ] = self::sanitize_array( $item );
			} elseif ( is_numeric( $item ) ) {
				$out[ $key ] = (string) $item;
			} else {
				$out[ $key ] = self::clean_text( $item );
			}
		}

		return $out;
	}

	/**
	 * Get scalar meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key Meta key.
	 * @return string
	 */
	private static function meta( $post_id, $key ) {
		$value = function_exists( 'get_post_meta' ) ? get_post_meta( (int) $post_id, $key, true ) : '';
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Clean text.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_text( $value ) {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}

		return trim( strip_tags( (string) $value ) );
	}
}
