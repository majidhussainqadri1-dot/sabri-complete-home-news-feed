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
	/** Stable File 19 producer key. */
	private const FILE19_PRODUCER = 'file21-home-news-feed';

	/** File 19-compatible past-tense domain fact. */
	private const FILE19_EVENT_TYPE = 'Publishing.DigestCandidatesPrepared';

	/** File 19 event-envelope schema emitted by File 21. */
	private const FILE19_SCHEMA_VERSION = '1.0.0';

	/** Register compatibility contracts after all plugins have loaded. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', array( __CLASS__, 'register_file19_producer' ), 20 );
		}
	}

	/** Register File 21 as an explicit File 19 notification producer. */
	public static function register_file19_producer() {
		if ( ! function_exists( 'sun_register_notification_producer' ) ) {
			return false;
		}

		return (bool) sun_register_notification_producer(
			self::FILE19_PRODUCER,
			array(
				'owner'           => 'File 21',
				'event_types'     => array( self::FILE19_EVENT_TYPE ),
				'schema_versions' => array( self::FILE19_SCHEMA_VERSION ),
			)
		);
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
				'available'    => true,
				'owner'        => 'file-26',
				'reason'       => self::clean_textarea( $payload['reason'] ),
				'time_window'  => self::clean_text( isset( $payload['time_window'] ) ? $payload['time_window'] : '' ),
				'source_count' => absint( isset( $payload['source_count'] ) ? $payload['source_count'] : 0 ),
			);
		}
		return array(
			'available'    => false,
			'owner'        => 'file-26',
			'reason'       => __( 'Global trending explanation is available when the File 26 discovery contract is active.', 'sabri-complete-home-news-feed' ),
			'time_window'  => '',
			'source_count' => 0,
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

	/**
	 * Hand a viewer-filtered digest candidate fact to File 19's canonical event intake.
	 *
	 * File 21 selects the public candidates. File 19 remains the sole notification,
	 * policy, quiet-hours, digest scheduling, retry/dead-letter and delivery owner.
	 */
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

		$fingerprint     = implode( '|', array( self::FILE19_PRODUCER, $user_id, $frequency, $window, implode( ',', $item_ids ) ) );
		$idempotency_key = 'f21-digest-' . substr( hash( 'sha256', $fingerprint ), 0, 32 );
		$trace_id        = 'f21-trace-' . substr( hash( 'sha256', 'trace|' . $fingerprint ), 0, 32 );
		$occurred_at     = gmdate( 'c' );
		$summary         = sprintf(
			/* translators: %d: number of digest candidate items. */
			__( '%d Home and News Feed items are ready for your knowledge digest.', 'sabri-complete-home-news-feed' ),
			count( $items )
		);
		$event           = array(
			'producer'        => self::FILE19_PRODUCER,
			'owner'           => 'File 21',
			'event_id'        => $idempotency_key,
			'event_type'      => self::FILE19_EVENT_TYPE,
			'schema_version'  => self::FILE19_SCHEMA_VERSION,
			'occurred_at'     => $occurred_at,
			'recipients'      => array( $user_id ),
			'trace_id'        => $trace_id,
			'idempotency_key' => $idempotency_key,
			'source_version'  => defined( 'SABRI_HNF_PACKAGE_VERSION' ) ? SABRI_HNF_PACKAGE_VERSION : '1.0.5',
			'category'        => 'publishing',
			'priority'        => 'normal',
			'sensitivity'     => 'standard',
			'data'            => array(
				'action_name'      => __( 'Knowledge digest ready', 'sabri-complete-home-news-feed' ),
				'object_name'      => __( 'Home and News Feed', 'sabri-complete-home-news-feed' ),
				'summary'          => $summary,
				'frequency'        => $frequency,
				'candidate_window' => $window,
				'items'            => $items,
			),
		);

		$registered = self::register_file19_producer();
		$available  = $registered && function_exists( 'sun_ingest_domain_event' );
		$status     = 'unavailable';
		$error_code = '';
		$ingest     = null;

		if ( $available ) {
			$ingest = sun_ingest_domain_event( $event );
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $ingest ) ) {
				$status     = 'rejected';
				$error_code = self::clean_key( $ingest->get_error_code() );
			} elseif ( is_array( $ingest ) ) {
				$status = self::clean_key( isset( $ingest['status'] ) ? $ingest['status'] : 'processed' );
			} else {
				$status = 'unknown';
			}
		}

		$compatibility_payload = array(
			'contract_version' => '1.2.0',
			'owner'            => 'file-21',
			'delivery_owner'   => 'file-19',
			'event'            => $event,
			'frequency'        => $frequency,
			'items'            => $items,
		);
		if ( function_exists( 'do_action' ) ) {
			// Transitional observer only; canonical File 19 v3 ingestion is the PHP API above.
			do_action( 'sabri_file19_digest_candidates', $compatibility_payload );
		}

		$response = array(
			'contract_version'  => '1.2.0',
			'owner'             => 'file-21',
			'delivery_owner'    => 'file-19',
			'delivery_available'=> $available,
			'ingest_status'     => $status,
			'event_id'          => $idempotency_key,
			'trace_id'          => $trace_id,
			'frequency'         => $frequency,
			'candidate_window'  => $window,
			'item_count'        => count( $items ),
			'items'             => $items,
			'generated_at_utc'  => $occurred_at,
		);
		if ( '' !== $error_code ) {
			$response['ingest_error_code'] = $error_code;
		}
		if ( is_array( $ingest ) && ! empty( $ingest['event_public_id'] ) ) {
			$response['notification_event_id'] = self::clean_text( $ingest['event_public_id'] );
		}

		return $response;
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
