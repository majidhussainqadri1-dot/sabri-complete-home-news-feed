<?php
/**
 * Cross-owner adapters for the File 21 next-generation experience.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps File 21 presentation/context features behind versioned owner contracts.
 * No AI, global recommendation, notification transport, or visual-system backend
 * is duplicated here.
 */
final class NextGenerationIntegrations {
	/** Register compatibility filters. */
	public static function register() {
		// Deliberately hook-free. Public static methods are the stable File 21 adapter API.
	}

	/** File 16-owned AI summary projection. */
	public static function ai_summary( $post_id ) {
		$post_id = absint( $post_id );
		$cached  = self::post_array_meta( $post_id, NextGenerationFeed::META_AI_SUMMARY );
		if ( ! empty( $cached['text'] ) ) {
			return array(
				'available' => true,
				'owner'     => 'file-16',
				'generated' => true,
				'label'     => __( 'AI-generated summary', 'sabri-complete-home-news-feed' ),
				'text'      => self::clean_textarea( $cached['text'] ),
				'sources'   => self::clean_url_list( isset( $cached['sources'] ) ? $cached['sources'] : array() ),
				'updated'   => self::clean_text( isset( $cached['updated'] ) ? $cached['updated'] : '' ),
			);
		}

		$payload = array();
		if ( function_exists( 'apply_filters' ) ) {
			$payload = apply_filters( 'sabri_file16_article_summary', array(), $post_id );
		}
		if ( ! is_array( $payload ) || empty( $payload['text'] ) ) {
			return array(
				'available' => false,
				'owner'     => 'file-16',
				'generated' => true,
				'label'     => __( 'AI summary unavailable', 'sabri-complete-home-news-feed' ),
				'text'      => '',
				'sources'   => array(),
			);
		}

		return array(
			'available' => true,
			'owner'     => 'file-16',
			'generated' => true,
			'label'     => __( 'AI-generated summary', 'sabri-complete-home-news-feed' ),
			'text'      => self::clean_textarea( $payload['text'] ),
			'sources'   => self::clean_url_list( isset( $payload['sources'] ) ? $payload['sources'] : array() ),
			'updated'   => self::clean_text( isset( $payload['updated'] ) ? $payload['updated'] : '' ),
		);
	}

	/** File 16-owned article Q&A entry point. */
	public static function ask_article( $post_id ) {
		$post_id = absint( $post_id );
		$payload = array();
		if ( function_exists( 'apply_filters' ) ) {
			$payload = apply_filters( 'sabri_file16_ask_article_contract', array(), $post_id );
		}
		if ( ! is_array( $payload ) || empty( $payload['url'] ) ) {
			return array( 'available' => false, 'owner' => 'file-16', 'url' => '', 'scope' => 'article-only' );
		}
		return array(
			'available' => true,
			'owner'     => 'file-16',
			'url'       => esc_url_raw( $payload['url'] ),
			'scope'     => 'article-only',
		);
	}

	/** File 16/service-owned translation projection with File 21 relation metadata. */
	public static function translation_options( $post_id ) {
		$post_id = absint( $post_id );
		$options = self::post_array_meta( $post_id, NextGenerationFeed::META_TRANSLATIONS );
		if ( function_exists( 'apply_filters' ) ) {
			$options = apply_filters( 'sabri_file16_translation_options', $options, $post_id );
		}
		if ( ! is_array( $options ) ) {
			return array();
		}
		$out = array();
		foreach ( $options as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$lang = self::clean_key( isset( $item['language'] ) ? $item['language'] : '' );
			$url  = isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '';
			if ( '' === $lang || '' === $url ) {
				continue;
			}
			$out[] = array(
				'language' => $lang,
				'url'      => $url,
				'method'   => in_array( isset( $item['method'] ) ? $item['method'] : '', array( 'human', 'machine' ), true ) ? $item['method'] : 'machine',
				'label'    => self::clean_text( isset( $item['label'] ) ? $item['label'] : strtoupper( $lang ) ),
			);
		}
		return array_slice( $out, 0, 12 );
	}

	/** File 26-owned global why-trending explanation. */
	public static function why_trending( $post_id ) {
		$post_id = absint( $post_id );
		$payload = array();
		if ( function_exists( 'apply_filters' ) ) {
			$payload = apply_filters( 'sabri_file26_why_trending', array(), $post_id );
		}
		if ( is_array( $payload ) && ! empty( $payload['reason'] ) ) {
			return array(
				'available'   => true,
				'owner'       => 'file-26',
				'reason'      => self::clean_textarea( $payload['reason'] ),
				'time_window' => self::clean_text( isset( $payload['time_window'] ) ? $payload['time_window'] : '' ),
				'source_count'=> absint( isset( $payload['source_count'] ) ? $payload['source_count'] : 0 ),
			);
		}
		return array(
			'available'   => false,
			'owner'       => 'file-26',
			'reason'      => __( 'Global trending explanation is available when the File 26 discovery contract is active.', 'sabri-complete-home-news-feed' ),
			'time_window' => '',
			'source_count'=> 0,
		);
	}

	/** File 26-owned related knowledge graph cards. */
	public static function related_knowledge( $post_id, $limit = 6 ) {
		$post_id = absint( $post_id );
		$limit   = min( 12, max( 1, absint( $limit ) ) );
		$items   = array();
		if ( function_exists( 'apply_filters' ) ) {
			$items = apply_filters( 'sabri_file26_related_knowledge', array(), $post_id, $limit );
		}
		if ( ! is_array( $items ) ) {
			return array();
		}
		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['url'] ) || empty( $item['title'] ) ) {
				continue;
			}
			$out[] = array(
				'title' => self::clean_text( $item['title'] ),
				'url'   => esc_url_raw( $item['url'] ),
				'type'  => self::clean_key( isset( $item['type'] ) ? $item['type'] : 'knowledge' ),
				'owner' => self::clean_key( isset( $item['owner'] ) ? $item['owner'] : 'file-26' ),
			);
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/** File 25-owned share-card visual renderer contract. */
	public static function share_card( array $payload ) {
		$rendered = '';
		if ( function_exists( 'apply_filters' ) ) {
			$rendered = apply_filters( 'sabri_file25_shareable_knowledge_card', '', $payload );
		}
		return is_string( $rendered ) ? $rendered : '';
	}

	/** File 19-owned digest delivery contract. */
	public static function dispatch_digest_candidates( $user_id, $frequency, array $items ) {
		$user_id   = absint( $user_id );
		$frequency = in_array( $frequency, array( 'daily', 'weekly' ), true ) ? $frequency : 'daily';
		$items     = array_slice( $items, 0, 20 );
		$window    = 'weekly' === $frequency ? gmdate( 'o-\\WW' ) : gmdate( 'Y-m-d' );
		$item_ids  = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) && ! empty( $item['id'] ) ) {
				$item_ids[] = absint( $item['id'] );
			}
		}
		$fingerprint    = implode( '|', array( 'file-21', $user_id, $frequency, $window, implode( ',', $item_ids ) ) );
		$idempotency_key = 'f21-digest-' . substr( hash( 'sha256', $fingerprint ), 0, 32 );
		$payload         = array(
			'contract_version' => '1.1.0',
			'owner'            => 'file-21',
			'event_type'       => 'File21DigestCandidatesPrepared.v1',
			'event_id'         => $idempotency_key,
			'idempotency_key'  => $idempotency_key,
			'trace_id'         => substr( hash( 'sha256', 'trace|' . $fingerprint ), 0, 32 ),
			'candidate_window' => $window,
			'user_id'          => $user_id,
			'frequency'        => $frequency,
			'items'            => $items,
			'generated_at_utc' => gmdate( 'c' ),
		);
		if ( function_exists( 'do_action' ) ) {
			do_action( 'sabri_file19_digest_candidates', $payload );
		}
		return $payload;
	}

	/** Safe post array metadata. */
	private static function post_array_meta( $post_id, $key ) {
		$value = function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, $key, true ) : array();
		return is_array( $value ) ? $value : array();
	}

	/** Sanitize URL list. */
	private static function clean_url_list( $values ) {
		$values = is_array( $values ) ? $values : array();
		$out    = array();
		foreach ( $values as $value ) {
			$url = esc_url_raw( $value );
			if ( '' !== $url ) {
				$out[] = $url;
			}
		}
		return array_slice( array_values( array_unique( $out ) ), 0, 20 );
	}

	private static function clean_text( $value ) {
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( (string) $value ) );
	}

	private static function clean_textarea( $value ) {
		return function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $value ) : trim( strip_tags( (string) $value ) );
	}

	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) );
	}
}
