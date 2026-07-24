<?php
/**
 * Public Editorial News projections.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Builds public data by inclusion and never serializes private domain objects. */
final class NewsPublicProjector {
	const DEFAULT_DISCLAIMER = 'This article is for education and public information. It does not replace individualized medical diagnosis, emergency care, or professional consultation.';

	/** Register no hooks; callers use explicit projections. */
	public static function register() {}

	/** Build a complete public article or retraction projection. */
	public static function article( $post ) {
		$post = self::post( $post );
		if ( ! $post || ! NewsPolicy::is_public_post( $post, 'single' ) ) {
			return array();
		}
		$state = NewsPolicy::workflow_state( $post->ID );
		if ( 'retracted' === $state ) {
			return self::retraction( $post );
		}

		$headline = self::title( $post );
		$type     = self::first_term( $post->ID, 'sabri_news_type' );
		$section  = self::terms( $post->ID, 'sabri_news_section' );

		return array(
			'projection'          => 'article',
			'id'                  => (int) $post->ID,
			'slug'                => self::slug( $post ),
			'canonical_url'       => self::canonical_url( $post ),
			'headline'            => $headline,
			'subtitle'            => self::meta_text( $post->ID, '_sabri_news_subtitle', 240 ),
			'summary'             => self::summary( $post ),
			'body_html'           => self::body( $post ),
			'language'            => self::language( $post->ID ),
			'article_type'        => $type ? $type['slug'] : 'standard-news',
			'public_label'        => self::public_label( $type ? $type['slug'] : 'standard-news', $state ),
			'section'             => $section,
			'topics'              => self::terms( $post->ID, 'sabri_news_topic' ),
			'country'             => self::terms( $post->ID, 'sabri_news_country' ),
			'region'              => self::terms( $post->ID, 'sabri_news_region' ),
			'public_author'       => self::public_author( $post ),
			'reviewing_editor'    => self::public_reviewing_editor( $post->ID ),
			'featured_media'      => self::featured_media( $post->ID, $headline ),
			'published_at'        => self::published_at( $post ),
			'updated_at'          => self::updated_at( $post ),
			'reading_time'        => self::reading_time( self::content( $post ) ),
			'disclaimer'          => self::disclaimer( $post->ID ),
			'conflict_disclosure' => self::meta_text( $post->ID, '_sabri_news_conflict_disclosure', 1000 ),
			'correction_state'    => self::correction_state( $post->ID, $state ),
			'retraction_notice'   => null,
			'interaction_id'      => (int) $post->ID,
		);
	}

	/** Build a bounded card projection. */
	public static function card( $post ) {
		$post = self::post( $post );
		if ( ! $post || ! NewsPolicy::is_public_post( $post, 'archive' ) ) {
			return array();
		}
		$state = NewsPolicy::workflow_state( $post->ID );
		$type  = self::first_term( $post->ID, 'sabri_news_type' );
		return array(
			'projection'    => 'card',
			'item_type'     => 'editorial_news',
			'global_key'    => 'news:' . (int) $post->ID,
			'object_id'     => (int) $post->ID,
			'headline'      => self::title( $post ),
			'summary'       => self::summary( $post ),
			'canonical_url' => self::canonical_url( $post ),
			'published_at'  => self::published_at( $post ),
			'updated_at'    => self::updated_at( $post ),
			'public_label'  => self::public_label( $type ? $type['slug'] : 'standard-news', $state ),
			'section'       => self::terms( $post->ID, 'sabri_news_section' ),
			'image'         => self::featured_media( $post->ID, self::title( $post ) ),
			'reading_time'  => self::reading_time( self::content( $post ) ),
			'interaction_id'=> (int) $post->ID,
		);
	}

	/** Build a public accountability projection with no hidden original body. */
	public static function retraction( $post ) {
		$post = self::post( $post );
		if ( ! $post || ! NewsPolicy::is_public_post( $post, 'retraction' ) ) {
			return array();
		}
		$notice = self::meta_text( $post->ID, '_sabri_news_retraction_notice', 2000 );
		if ( '' === $notice ) {
			$notice = __( 'This article has been retracted. The original article body is no longer publicly available.', 'sabri-complete-home-news-feed' );
		}
		return array(
			'projection'        => 'retraction',
			'id'                => (int) $post->ID,
			'slug'              => self::slug( $post ),
			'canonical_url'     => self::canonical_url( $post ),
			'headline'          => self::title( $post ),
			'public_label'      => 'Retraction',
			'published_at'      => self::published_at( $post ),
			'updated_at'        => self::updated_at( $post ),
			'retraction_notice' => $notice,
			'body_html'         => '',
			'summary'           => '',
			'featured_media'    => array(),
			'interaction_id'    => 0,
		);
	}

	/** Return safe taxonomy links. */
	public static function terms( $post_id, $taxonomy ) {
		if ( ! in_array( $taxonomy, Phase4Contracts::taxonomies(), true ) || ! function_exists( 'get_the_terms' ) ) {
			return array();
		}
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) || ( function_exists( 'is_wp_error' ) && is_wp_error( $terms ) ) ) {
			return array();
		}
		$out = array();
		foreach ( array_slice( $terms, 0, 25 ) as $term ) {
			$slug = isset( $term->slug ) ? self::clean_slug( $term->slug ) : '';
			$name = isset( $term->name ) ? self::clean_text( $term->name, 120 ) : '';
			if ( '' === $slug || '' === $name ) {
				continue;
			}
			$url = function_exists( 'get_term_link' ) ? get_term_link( $term, $taxonomy ) : self::taxonomy_url( $taxonomy, $slug );
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $url ) ) {
				$url = self::taxonomy_url( $taxonomy, $slug );
			}
			$out[] = array(
				'slug' => $slug,
				'name' => $name,
				'url'  => (string) $url,
			);
		}
		return $out;
	}

	/** Get the first public term. */
	private static function first_term( $post_id, $taxonomy ) {
		$terms = self::terms( $post_id, $taxonomy );
		return $terms ? $terms[0] : array();
	}

	/** Public author projection excludes email and account metadata. */
	private static function public_author( $post ) {
		$author_id = isset( $post->post_author ) ? (int) $post->post_author : 0;
		$name = '';
		if ( $author_id > 0 && function_exists( 'get_the_author_meta' ) ) {
			$name = trim( (string) get_the_author_meta( 'display_name', $author_id ) );
		}
		if ( '' === $name || false !== filter_var( $name, FILTER_VALIDATE_EMAIL ) ) {
			$name = __( 'Sabri Editorial Team', 'sabri-complete-home-news-feed' );
		}
		$url = '';
		if ( $author_id > 0 && function_exists( 'get_author_posts_url' ) ) {
			$url = (string) get_author_posts_url( $author_id );
		}
		return array(
			'name' => self::clean_text( $name, 120 ),
			'url'  => $url,
		);
	}

	/** Reviewer identity remains private unless an explicit policy filter approves it. */
	private static function public_reviewing_editor( $post_id ) {
		$editor_id = (int) self::meta( $post_id, '_sabri_news_reviewing_editor_id' );
		$allowed = function_exists( 'apply_filters' ) ? (bool) apply_filters( 'sabri_news_public_reviewing_editor_allowed', false, $post_id, $editor_id ) : false;
		if ( ! $allowed || $editor_id < 1 || ! function_exists( 'get_the_author_meta' ) ) {
			return array();
		}
		$name = trim( (string) get_the_author_meta( 'display_name', $editor_id ) );
		if ( '' === $name || false !== filter_var( $name, FILTER_VALIDATE_EMAIL ) ) {
			return array();
		}
		return array( 'name' => self::clean_text( $name, 120 ) );
	}

	/** Public image projection includes approved display data only. */
	private static function featured_media( $post_id, $headline ) {
		$attachment_id = function_exists( 'get_post_thumbnail_id' ) ? (int) get_post_thumbnail_id( $post_id ) : 0;
		if ( $attachment_id < 1 ) {
			return array();
		}
		$url = function_exists( 'get_the_post_thumbnail_url' ) ? (string) get_the_post_thumbnail_url( $post_id, 'large' ) : '';
		if ( '' === $url && function_exists( 'wp_get_attachment_image_url' ) ) {
			$url = (string) wp_get_attachment_image_url( $attachment_id, 'large' );
		}
		if ( '' === $url ) {
			return array();
		}
		$alt = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) : '';
		$alt = self::clean_text( $alt, 300 );
		if ( '' === $alt ) {
			$alt = self::clean_text( $headline, 300 );
		}
		$caption = function_exists( 'wp_get_attachment_caption' ) ? self::clean_text( wp_get_attachment_caption( $attachment_id ), 500 ) : '';
		$credit = self::meta_text( $post_id, '_sabri_news_featured_image_credit', 500 );
		return array(
			'id'      => $attachment_id,
			'url'     => $url,
			'alt'     => $alt,
			'caption' => $caption,
			'credit'  => $credit,
		);
	}

	/** Return a controlled public label. */
	private static function public_label( $type, $state ) {
		if ( 'corrected' === $state ) {
			return 'Correction';
		}
		$map = array(
			'breaking-news'         => 'Breaking News',
			'standard-news'         => 'News',
			'research-news'         => 'Research News',
			'editorial'             => 'Editorial',
			'analysis'              => 'Analysis',
			'interview'             => 'Interview',
			'event-report'          => 'Event Report',
			'official-announcement' => 'Official Announcement',
			'correction-notice'     => 'Correction',
			'retraction-notice'     => 'Retraction',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : 'News';
	}

	/** Public disclaimer. */
	private static function disclaimer( $post_id ) {
		$custom = self::meta_text( $post_id, '_sabri_news_disclaimer', 1200 );
		if ( '' !== $custom ) {
			return $custom;
		}
		$required = self::truthy( self::meta( $post_id, '_sabri_news_medical_review_required' ) );
		return $required ? __( self::DEFAULT_DISCLAIMER, 'sabri-complete-home-news-feed' ) : '';
	}

	/** Public correction state only. */
	private static function correction_state( $post_id, $state ) {
		if ( 'corrected' === $state ) {
			return 'corrected';
		}
		if ( 'correction-pending' === $state ) {
			return 'none';
		}
		$stored = self::clean_slug( self::meta( $post_id, '_sabri_news_correction_status' ) );
		return 'corrected' === $stored ? 'corrected' : 'none';
	}

	/** Build a deterministic canonical route. */
	private static function canonical_url( $post ) {
		$slug = self::slug( $post );
		return function_exists( 'home_url' ) ? home_url( '/news/' . rawurlencode( $slug ) . '/' ) : '/news/' . rawurlencode( $slug ) . '/';
	}

	/** Build a controlled taxonomy route. */
	private static function taxonomy_url( $taxonomy, $slug ) {
		$map = array(
			'sabri_news_section' => 'section',
			'sabri_news_topic'   => 'topic',
			'sabri_news_country' => 'country',
			'sabri_news_region'  => 'region',
			'sabri_news_type'    => 'type',
		);
		$route = isset( $map[ $taxonomy ] ) ? $map[ $taxonomy ] : '';
		$path = '/news/' . $route . '/' . rawurlencode( $slug ) . '/';
		return function_exists( 'home_url' ) ? home_url( $path ) : $path;
	}

	/** Resolve a post object. */
	private static function post( $post ) {
		if ( is_numeric( $post ) && function_exists( 'get_post' ) ) {
			$post = get_post( (int) $post );
		}
		return is_object( $post ) && ! empty( $post->ID ) ? $post : null;
	}

	/** Headline. */
	private static function title( $post ) {
		$title = function_exists( 'get_the_title' ) ? get_the_title( $post->ID ) : ( isset( $post->post_title ) ? $post->post_title : '' );
		return self::clean_text( $title, 300 );
	}

	/** Slug. */
	private static function slug( $post ) {
		$slug = isset( $post->post_name ) ? $post->post_name : '';
		if ( '' === $slug ) {
			$slug = function_exists( 'sanitize_title' ) ? sanitize_title( self::title( $post ) ) : self::clean_slug( str_replace( ' ', '-', self::title( $post ) ) );
		}
		return self::clean_slug( $slug );
	}

	/** Summary. */
	private static function summary( $post ) {
		$summary = self::meta_text( $post->ID, '_sabri_news_summary', 1000 );
		if ( '' !== $summary ) {
			return $summary;
		}
		$excerpt = isset( $post->post_excerpt ) ? $post->post_excerpt : '';
		if ( '' === trim( (string) $excerpt ) && function_exists( 'get_the_excerpt' ) ) {
			$excerpt = get_the_excerpt( $post->ID );
		}
		if ( '' === trim( (string) $excerpt ) ) {
			$content = self::content( $post );
			$excerpt = function_exists( 'wp_trim_words' ) ? wp_trim_words( self::strip_tags( $content ), 45, '…' ) : substr( self::strip_tags( $content ), 0, 280 );
		}
		return self::clean_text( $excerpt, 1000 );
	}

	/** Rich article body. */
	private static function body( $post ) {
		$content = self::content( $post );
		if ( function_exists( 'apply_filters' ) ) {
			$content = apply_filters( 'the_content', $content );
		}
		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $content ) : strip_tags( $content, '<p><br><strong><em><b><i><ul><ol><li><a><blockquote><code><pre><h2><h3><h4><figure><figcaption><img>' );
	}

	/** Raw article content. */
	private static function content( $post ) {
		return isset( $post->post_content ) ? (string) $post->post_content : '';
	}

	/** Language. */
	private static function language( $post_id ) {
		$value = (string) self::meta( $post_id, '_sabri_news_language' );
		return preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/D', $value ) ? $value : 'en-US';
	}

	/** Published timestamp in ISO-8601 UTC. */
	private static function published_at( $post ) {
		$value = isset( $post->post_date_gmt ) && $post->post_date_gmt ? $post->post_date_gmt : ( isset( $post->post_date ) ? $post->post_date : '' );
		return self::iso_time( $value );
	}

	/** Updated timestamp in ISO-8601 UTC. */
	private static function updated_at( $post ) {
		$value = isset( $post->post_modified_gmt ) && $post->post_modified_gmt ? $post->post_modified_gmt : ( isset( $post->post_modified ) ? $post->post_modified : '' );
		return self::iso_time( $value );
	}

	/** Convert a WordPress time to ISO-8601. */
	private static function iso_time( $value ) {
		$timestamp = is_numeric( $value ) ? (int) $value : strtotime( (string) $value . ( preg_match( '/(?:Z|[+-][0-9]{2}:[0-9]{2})$/', (string) $value ) ? '' : ' UTC' ) );
		return $timestamp > 0 ? gmdate( 'c', $timestamp ) : '';
	}

	/** Reading time rounded up. */
	private static function reading_time( $content ) {
		$plain = trim( self::strip_tags( $content ) );
		if ( '' === $plain ) {
			return 0;
		}
		$words = preg_split( '/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY );
		return max( 1, (int) ceil( count( $words ) / 220 ) );
	}

	/** Read metadata. */
	private static function meta( $post_id, $key ) {
		return function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, $key, true ) : '';
	}

	/** Read and bound public text. */
	private static function meta_text( $post_id, $key, $limit ) {
		return self::clean_text( self::meta( $post_id, $key ), $limit );
	}

	/** Clean text without exposing raw markup. */
	private static function clean_text( $value, $limit ) {
		$value = function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $value ) : trim( strip_tags( (string) $value ) );
		return self::substr( $value, 0, $limit );
	}

	/** Clean one slug without repairing malformed input at query boundaries. */
	private static function clean_slug( $value ) {
		$value = is_string( $value ) ? $value : '';
		return preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $value ) ? $value : '';
	}

	/** Truthy scalar. */
	private static function truthy( $value ) {
		return in_array( $value, array( true, 1, '1' ), true );
	}

	/** Strip tags. */
	private static function strip_tags( $value ) {
		return function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $value ) : strip_tags( (string) $value );
	}

	/** Multibyte-safe substring where available. */
	private static function substr( $value, $start, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $value, $start, $length ) : substr( (string) $value, $start, $length );
	}
}
