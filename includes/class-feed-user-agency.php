<?php
/**
 * Privacy-safe Feed user agency controls.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns private File 21 presentation preferences without duplicating File 17
 * relationship truth or File 26 global ranking governance.
 */
final class FeedUserAgency {
	const META_KEY = '_sabri_hnf_feed_preferences_v1';
	const MAX_HIDDEN_POSTS = 200;
	const MAX_MUTED_AUTHORS = 100;
	const MAX_MUTED_TOPICS = 100;
	const MAX_FOLLOWING_AUTHORS = 200;

	/** Register query constraints. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'sabri_hnf_feed_query_args', array( __CLASS__, 'filter_query_args' ), 20, 4 );
		}
	}

	/** Safe defaults: no covert or inferred personalization. */
	public static function defaults() {
		return array(
			'reduced_personalization' => 0,
			'hidden_posts'            => array(),
			'muted_authors'           => array(),
			'muted_topics'            => array(),
			'snoozed_authors'         => array(),
			'snoozed_topics'          => array(),
		);
	}

	/** Return normalized private preferences for the current authenticated user. */
	public static function preferences( $user_id = 0 ) {
		$user_id = self::current_user_id( $user_id );
		if ( $user_id <= 0 ) {
			return self::defaults();
		}
		$stored = function_exists( 'get_user_meta' ) ? get_user_meta( $user_id, self::META_KEY, true ) : array();
		return self::normalize( is_array( $stored ) ? $stored : array() );
	}

	/** Whether the current user explicitly requested reduced personalization. */
	public static function reduced_personalization( $user_id = 0 ) {
		$prefs = self::preferences( $user_id );
		return ! empty( $prefs['reduced_personalization'] );
	}

	/** Stable private cache fragment for explicit preferences and canonical follows. */
	public static function cache_fragment( $user_id = 0 ) {
		$user_id = self::current_user_id( $user_id );
		if ( $user_id <= 0 ) {
			return 'guest';
		}
		$prefs = self::preferences( $user_id );
		$following = NetworkRelationshipBridge::following_user_ids( $user_id, self::MAX_FOLLOWING_AUTHORS );
		$payload = function_exists( 'wp_json_encode' ) ? wp_json_encode( array( $prefs, $following ) ) : json_encode( array( $prefs, $following ) );
		return hash( 'sha256', (string) $payload );
	}

	/** Apply private hide/mute/snooze and Following constraints before query execution. */
	public static function filter_query_args( $args, $mode, $user_id, $settings ) {
		$args = is_array( $args ) ? $args : array();
		$mode = self::clean_key( $mode );
		$user_id = self::current_user_id( $user_id );
		unset( $settings );

		if ( 'following' === $mode ) {
			$following = $user_id > 0 ? NetworkRelationshipBridge::following_user_ids( $user_id, self::MAX_FOLLOWING_AUTHORS ) : array();
			$args['author__in'] = $following ? array_values( array_unique( array_map( 'absint', $following ) ) ) : array( 0 );
		}

		if ( $user_id <= 0 ) {
			return $args;
		}

		$prefs = self::preferences( $user_id );
		$hidden_posts = array_values( array_unique( array_filter( array_map( 'absint', $prefs['hidden_posts'] ) ) ) );
		if ( $hidden_posts ) {
			$existing = isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] ) ? $args['post__not_in'] : array();
			$args['post__not_in'] = array_values( array_unique( array_merge( array_map( 'absint', $existing ), $hidden_posts ) ) );
		}

		$muted_authors = array_merge( $prefs['muted_authors'], self::active_snoozed_ids( $prefs['snoozed_authors'] ) );
		$muted_authors = array_values( array_unique( array_filter( array_map( 'absint', $muted_authors ) ) ) );
		if ( $muted_authors ) {
			$existing = isset( $args['author__not_in'] ) && is_array( $args['author__not_in'] ) ? $args['author__not_in'] : array();
			$args['author__not_in'] = array_values( array_unique( array_merge( array_map( 'absint', $existing ), $muted_authors ) ) );
		}

		$muted_topics = array_merge( $prefs['muted_topics'], self::active_snoozed_keys( $prefs['snoozed_topics'] ) );
		$muted_topics = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_key' ), $muted_topics ) ) ) );
		if ( $muted_topics ) {
			$tax_query = isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ? $args['tax_query'] : array();
			$tax_query[] = array(
				'taxonomy' => 'sabri_feed_topic',
				'field'    => 'slug',
				'terms'    => $muted_topics,
				'operator' => 'NOT IN',
			);
			$args['tax_query'] = $tax_query;
		}
		return $args;
	}

	/** Update one explicit current-user preference action. */
	public static function update( $action, $value = '', $duration = 0, $nonce = '', $user_id = 0 ) {
		$user_id = InteractionPermissions::authenticated_user_id( $user_id );
		if ( $user_id <= 0 ) {
			return InteractionResult::error( 'authentication_required', 'Authentication is required.', array(), 401 );
		}
		if ( ! CanonicalIdentityAdapter::current_action_ready( $user_id ) ) {
			return InteractionResult::error( 'identity_assurance_required', 'Current account assurance is required.', array(), 403 );
		}
		if ( ! InteractionPermissions::nonce_valid( $nonce ) ) {
			return InteractionResult::error( 'invalid_nonce', 'The security token is missing or invalid.', array(), 403 );
		}
		if ( SafeMode::public_features_disabled() ) {
			return InteractionResult::error( 'feed_preferences_unavailable', 'Feed preferences are temporarily unavailable.', array(), 503 );
		}

		$action = self::clean_key( $action );
		$prefs = self::preferences( $user_id );
		$now = time();
		$duration = self::duration( $duration );
		$audit = array( 'preference_action' => $action );

		switch ( $action ) {
			case 'reset':
				$prefs = self::defaults();
				break;
			case 'reduced-personalization':
				$prefs['reduced_personalization'] = self::truthy( $value ) ? 1 : 0;
				$audit['enabled'] = $prefs['reduced_personalization'];
				break;
			case 'hide-post':
				$post_id = self::positive_id( $value );
				if ( $post_id <= 0 || ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
					return InteractionResult::error( 'post_unavailable', 'The requested post is unavailable.', array(), 404 );
				}
				$prefs['hidden_posts'] = self::append_bounded_id( $prefs['hidden_posts'], $post_id, self::MAX_HIDDEN_POSTS );
				$audit['post_id'] = $post_id;
				break;
			case 'unhide-post':
				$post_id = self::positive_id( $value );
				$prefs['hidden_posts'] = array_values( array_diff( $prefs['hidden_posts'], array( $post_id ) ) );
				$audit['post_id'] = $post_id;
				break;
			case 'mute-author':
			case 'unmute-author':
			case 'snooze-author':
				$author_id = self::positive_id( $value );
				if ( $author_id <= 0 || ( function_exists( 'get_userdata' ) && ! get_userdata( $author_id ) ) ) {
					return InteractionResult::error( 'author_unavailable', 'The selected author is unavailable.', array(), 404 );
				}
				if ( 'mute-author' === $action ) {
					$prefs['muted_authors'] = self::append_bounded_id( $prefs['muted_authors'], $author_id, self::MAX_MUTED_AUTHORS );
					unset( $prefs['snoozed_authors'][ (string) $author_id ] );
				} elseif ( 'unmute-author' === $action ) {
					$prefs['muted_authors'] = array_values( array_diff( $prefs['muted_authors'], array( $author_id ) ) );
					unset( $prefs['snoozed_authors'][ (string) $author_id ] );
				} else {
					$prefs['snoozed_authors'][ (string) $author_id ] = $now + $duration;
				}
				$audit['author_id'] = $author_id;
				break;
			case 'mute-topic':
			case 'unmute-topic':
			case 'snooze-topic':
				$topic = self::clean_key( $value );
				if ( '' === $topic ) {
					return InteractionResult::error( 'topic_unavailable', 'The selected topic is unavailable.', array(), 400 );
				}
				if ( 'mute-topic' === $action ) {
					$prefs['muted_topics'] = self::append_bounded_key( $prefs['muted_topics'], $topic, self::MAX_MUTED_TOPICS );
					unset( $prefs['snoozed_topics'][ $topic ] );
				} elseif ( 'unmute-topic' === $action ) {
					$prefs['muted_topics'] = array_values( array_diff( $prefs['muted_topics'], array( $topic ) ) );
					unset( $prefs['snoozed_topics'][ $topic ] );
				} else {
					$prefs['snoozed_topics'][ $topic ] = $now + $duration;
				}
				$audit['topic'] = $topic;
				break;
			default:
				return InteractionResult::error( 'invalid_feed_preference_action', 'The requested feed preference action is invalid.', array(), 400 );
		}

		$prefs = self::normalize( $prefs );
		if ( ! function_exists( 'update_user_meta' ) ) {
			return InteractionResult::error( 'feed_preference_save_failed', 'The feed preference store is unavailable.', array(), 503 );
		}
		$updated = update_user_meta( $user_id, self::META_KEY, $prefs );
		if ( false === $updated ) {
			$persisted = function_exists( 'get_user_meta' ) ? get_user_meta( $user_id, self::META_KEY, true ) : array();
			$persisted = self::normalize( is_array( $persisted ) ? $persisted : array() );
			if ( $persisted !== $prefs ) {
				return InteractionResult::error( 'feed_preference_save_failed', 'The feed preference could not be saved.', array(), 500 );
			}
		}
		FeedQuery::invalidate_cache();
		AuditLog::record( 'feed_preference_updated', $audit, 'user', $user_id );
		return InteractionResult::success( 'feed_preference_updated', array( 'preferences' => $prefs ), 'Feed preference updated.', 200 );
	}

	/** Per-card user-agency controls; relationship Block/Restrict remains File 17-owned. */
	public static function card_controls( $post_id ) {
		$user_id = self::current_user_id();
		$post_id = self::positive_id( $post_id );
		if ( $user_id <= 0 || $post_id <= 0 || ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
			return '';
		}
		$author_id = function_exists( 'get_post_field' ) ? (int) get_post_field( 'post_author', $post_id ) : 0;
		$topic = self::first_topic_slug( $post_id );
		$html = '<details class="sabri-hnf-card-agency"><summary>' . esc_html__( 'Feed controls', 'sabri-complete-home-news-feed' ) . '</summary><div class="sabri-hnf-card-agency__actions">';
		$html .= self::button( 'hide-post', (string) $post_id, __( 'Not interested', 'sabri-complete-home-news-feed' ) );
		if ( $author_id > 0 && $author_id !== $user_id ) {
			$html .= self::button( 'snooze-author', (string) $author_id, __( 'Snooze author for 7 days', 'sabri-complete-home-news-feed' ), 604800 );
			$html .= self::button( 'mute-author', (string) $author_id, __( 'Hide this author from my Feed', 'sabri-complete-home-news-feed' ) );
		}
		if ( '' !== $topic ) {
			$html .= self::button( 'snooze-topic', $topic, __( 'Snooze this topic for 7 days', 'sabri-complete-home-news-feed' ), 604800 );
			$html .= self::button( 'mute-topic', $topic, __( 'Reduce this topic', 'sabri-complete-home-news-feed' ) );
		}
		return $html . '</div></details>';
	}

	/** Global agency controls rendered beside, not inside, the canonical 14-control bar. */
	public static function global_controls( $active_mode ) {
		$active_mode = self::clean_key( $active_mode );
		$user_id = self::current_user_id();
		$nonce = $user_id > 0 && function_exists( 'wp_create_nonce' ) ? wp_create_nonce( InteractionPermissions::REST_NONCE_ACTION ) : '';
		$endpoint = function_exists( 'rest_url' ) ? rest_url( RestFoundation::NAMESPACE . '/feed/preferences' ) : '';
		$login = $user_id <= 0 && function_exists( 'wp_login_url' ) ? wp_login_url( self::feed_url( 'for-you' ) ) : '';
		$prefs = self::preferences( $user_id );
		$html = '<section class="sabri-hnf-feed-agency" data-sabri-feed-agency data-preferences-url="' . esc_url( $endpoint ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-logged-in="' . ( $user_id > 0 ? '1' : '0' ) . '" data-login-url="' . esc_url( $login ) . '" aria-label="' . esc_attr__( 'Feed controls and explanation', 'sabri-complete-home-news-feed' ) . '">';
		$html .= '<nav class="sabri-hnf-feed-agency__modes" aria-label="' . esc_attr__( 'Feed choice', 'sabri-complete-home-news-feed' ) . '">';
		if ( $user_id > 0 && Phase3FeatureSettings::enabled( 'follows_enabled' ) ) {
			$html .= self::mode_link( 'following', __( 'Following', 'sabri-complete-home-news-feed' ), $active_mode );
		}
		$html .= self::mode_link( 'latest', __( 'Latest', 'sabri-complete-home-news-feed' ), $active_mode );
		$html .= self::mode_link( 'for-you', __( 'For You', 'sabri-complete-home-news-feed' ), $active_mode );
		$html .= '</nav><details class="sabri-hnf-feed-agency__why"><summary>' . esc_html__( 'Why am I seeing this?', 'sabri-complete-home-news-feed' ) . '</summary><p>' . esc_html( self::why_text( $active_mode, $user_id ) ) . '</p></details>';
		if ( $user_id > 0 ) {
			$html .= '<div class="sabri-hnf-feed-agency__preferences">';
			$html .= self::button( 'reduced-personalization', empty( $prefs['reduced_personalization'] ) ? '1' : '0', empty( $prefs['reduced_personalization'] ) ? __( 'Use less personalization', 'sabri-complete-home-news-feed' ) : __( 'Use normal personalization', 'sabri-complete-home-news-feed' ) );
			$html .= self::button( 'reset', '', __( 'Reset Feed preferences', 'sabri-complete-home-news-feed' ) );
			$html .= '</div>';
		}
		$html .= '<p class="sabri-hnf-feed-agency__status" data-sabri-feed-preference-status aria-live="polite"></p></section>';
		return $html;
	}

	/** Explain ranking without claiming hidden inference or File 26 ownership. */
	public static function why_text( $mode, $user_id = 0 ) {
		$mode = self::clean_key( $mode );
		if ( 'latest' === $mode ) {
			return __( 'Latest shows eligible approved publications in chronological order after safety and visibility checks.', 'sabri-complete-home-news-feed' );
		}
		if ( 'following' === $mode ) {
			return __( 'Following shows eligible publications from accounts you explicitly follow. Payment, donations and purchased engagement do not improve placement.', 'sabri-complete-home-news-feed' );
		}
		if ( 'most-viral' === $mode ) {
			return __( 'Most Viral uses genuine bounded engagement, freshness, quality and abuse penalties after eligibility checks. Donations and paid promotion are not ranking signals.', 'sabri-complete-home-news-feed' );
		}
		if ( self::reduced_personalization( $user_id ) ) {
			return __( 'For You is currently in reduced-personalization mode and relies mainly on eligible quality, freshness, diversity and safety signals.', 'sabri-complete-home-news-feed' );
		}
		return __( 'For You uses only eligible first-party context such as explicit follows or selected topics, plus quality, freshness, diversity and safety. It does not use private messages, patient data, hidden health inference, donations or paid promotion.', 'sabri-complete-home-news-feed' );
	}

	/** Normalize stored values and expire snoozes on read. */
	private static function normalize( array $prefs ) {
		$out = self::defaults();
		$out['reduced_personalization'] = ! empty( $prefs['reduced_personalization'] ) ? 1 : 0;
		$out['hidden_posts'] = array_slice( array_values( array_unique( array_filter( array_map( 'absint', isset( $prefs['hidden_posts'] ) && is_array( $prefs['hidden_posts'] ) ? $prefs['hidden_posts'] : array() ) ) ) ), 0, self::MAX_HIDDEN_POSTS );
		$out['muted_authors'] = array_slice( array_values( array_unique( array_filter( array_map( 'absint', isset( $prefs['muted_authors'] ) && is_array( $prefs['muted_authors'] ) ? $prefs['muted_authors'] : array() ) ) ) ), 0, self::MAX_MUTED_AUTHORS );
		$out['muted_topics'] = array_slice( array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_key' ), isset( $prefs['muted_topics'] ) && is_array( $prefs['muted_topics'] ) ? $prefs['muted_topics'] : array() ) ) ) ), 0, self::MAX_MUTED_TOPICS );
		$out['snoozed_authors'] = self::normalize_snoozes( isset( $prefs['snoozed_authors'] ) && is_array( $prefs['snoozed_authors'] ) ? $prefs['snoozed_authors'] : array(), true );
		$out['snoozed_topics'] = self::normalize_snoozes( isset( $prefs['snoozed_topics'] ) && is_array( $prefs['snoozed_topics'] ) ? $prefs['snoozed_topics'] : array(), false );
		return $out;
	}

	private static function normalize_snoozes( array $values, $numeric ) {
		$now = time();
		$out = array();
		foreach ( $values as $key => $expiry ) {
			$clean = $numeric ? (string) self::positive_id( $key ) : self::clean_key( $key );
			$expiry = is_numeric( $expiry ) ? (int) $expiry : 0;
			if ( '' !== $clean && '0' !== $clean && $expiry > $now ) {
				$out[ $clean ] = $expiry;
			}
		}
		return array_slice( $out, 0, 100, true );
	}

	private static function active_snoozed_ids( array $values ) {
		return array_map( 'absint', array_keys( self::normalize_snoozes( $values, true ) ) );
	}

	private static function active_snoozed_keys( array $values ) {
		return array_keys( self::normalize_snoozes( $values, false ) );
	}

	private static function append_bounded_id( array $values, $id, $limit ) {
		$values[] = (int) $id;
		$values = array_values( array_unique( array_filter( array_map( 'absint', $values ) ) ) );
		return array_slice( $values, -1 * max( 1, (int) $limit ) );
	}

	private static function append_bounded_key( array $values, $key, $limit ) {
		$values[] = self::clean_key( $key );
		$values = array_values( array_unique( array_filter( $values ) ) );
		return array_slice( $values, -1 * max( 1, (int) $limit ) );
	}

	private static function first_topic_slug( $post_id ) {
		if ( ! function_exists( 'get_the_terms' ) ) {
			return '';
		}
		$terms = get_the_terms( $post_id, 'sabri_feed_topic' );
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return '';
		}
		$term = reset( $terms );
		return is_object( $term ) ? self::clean_key( (string) $term->slug ) : '';
	}

	private static function mode_link( $mode, $label, $active_mode ) {
		$url = self::feed_url( $mode );
		return '<a class="sabri-hnf-feed-agency__mode' . ( $mode === $active_mode ? ' is-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $mode === $active_mode ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
	}

	private static function button( $action, $value, $label, $duration = 0 ) {
		return '<button type="button" class="sabri-hnf-feed-agency__button" data-sabri-feed-preference="' . esc_attr( $action ) . '" data-value="' . esc_attr( $value ) . '" data-duration="' . esc_attr( (string) $duration ) . '">' . esc_html( $label ) . '</button>';
	}

	private static function feed_url( $mode ) {
		$base = function_exists( 'home_url' ) ? home_url( '/' ) : '/';
		return function_exists( 'add_query_arg' ) ? add_query_arg( array( 'sabri_feed_mode' => self::clean_key( $mode ), 'sabri_feed_page' => 1 ), $base ) : $base;
	}

	private static function duration( $value ) {
		$value = is_numeric( $value ) ? (int) $value : 0;
		$allowed = array( 86400, 604800, 2592000 );
		return in_array( $value, $allowed, true ) ? $value : 604800;
	}

	private static function truthy( $value ) {
		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true );
	}

	private static function current_user_id( $user_id = 0 ) {
		$current = function_exists( 'get_current_user_id' ) ? self::positive_id( get_current_user_id() ) : 0;
		if ( $user_id ) {
			$requested = self::positive_id( $user_id );
			return $current > 0 && $requested === $current ? $current : 0;
		}
		return $current;
	}

	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}

	private static function positive_id( $value ) {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/', $value ) ) ) {
			return 0;
		}
		$value = (int) $value;
		return $value > 0 ? $value : 0;
	}
}
