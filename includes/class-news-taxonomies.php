<?php
/**
 * Phase 4 Editorial News taxonomies.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers the Editorial News taxonomy model without exposing public routes before acceptance. */
final class NewsTaxonomies {
	const TERM_VERSION_OPTION = 'sabri_feed_phase4_terms_version';

	/** Register taxonomies and one bounded authorized admin-side upgrade check. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 9 );
			add_action( 'admin_init', array( __CLASS__, 'maybe_ensure_default_terms' ), 30 );
		}
	}

	/** Return taxonomy definitions. */
	public static function definitions() {
		return array(
			'sabri_news_section' => array( 'singular' => __( 'News Section', 'sabri-complete-home-news-feed' ), 'plural' => __( 'News Sections', 'sabri-complete-home-news-feed' ), 'hierarchical' => true, 'route' => 'section' ),
			'sabri_news_topic'   => array( 'singular' => __( 'News Topic', 'sabri-complete-home-news-feed' ), 'plural' => __( 'News Topics', 'sabri-complete-home-news-feed' ), 'hierarchical' => true, 'route' => 'topic' ),
			'sabri_news_country' => array( 'singular' => __( 'News Country', 'sabri-complete-home-news-feed' ), 'plural' => __( 'News Countries', 'sabri-complete-home-news-feed' ), 'hierarchical' => true, 'route' => 'country' ),
			'sabri_news_region'  => array( 'singular' => __( 'News Region', 'sabri-complete-home-news-feed' ), 'plural' => __( 'News Regions', 'sabri-complete-home-news-feed' ), 'hierarchical' => true, 'route' => 'region' ),
			'sabri_news_type'    => array( 'singular' => __( 'News Type', 'sabri-complete-home-news-feed' ), 'plural' => __( 'News Types', 'sabri-complete-home-news-feed' ), 'hierarchical' => false, 'route' => 'type' ),
		);
	}

	/** Register all taxonomies against the Editorial News post type. */
	public static function register_taxonomies() {
		if ( ! function_exists( 'register_taxonomy' ) ) {
			return;
		}
		$public_enabled = NewsFeatureSettings::enabled( 'editorial_news_enabled' );
		foreach ( self::definitions() as $taxonomy => $definition ) {
			register_taxonomy(
				$taxonomy,
				array( Phase4Contracts::POST_TYPE ),
				array(
					'labels' => array(
						'name'          => $definition['plural'],
						'singular_name' => $definition['singular'],
						'search_items'  => sprintf( __( 'Search %s', 'sabri-complete-home-news-feed' ), $definition['plural'] ),
						'all_items'     => sprintf( __( 'All %s', 'sabri-complete-home-news-feed' ), $definition['plural'] ),
						'edit_item'     => sprintf( __( 'Edit %s', 'sabri-complete-home-news-feed' ), $definition['singular'] ),
					),
					'public'             => $public_enabled,
					'publicly_queryable' => $public_enabled,
					'show_ui'            => true,
					'show_admin_column'  => true,
					'show_in_nav_menus'  => false,
					'show_in_rest'       => false,
					'hierarchical'       => ! empty( $definition['hierarchical'] ),
					'query_var'          => $public_enabled ? $taxonomy : false,
					'rewrite'            => $public_enabled ? array( 'slug' => 'news/' . $definition['route'], 'with_front' => false ) : false,
					'capabilities'       => array(
						'manage_terms' => 'manage_news_taxonomies',
						'edit_terms'   => 'manage_news_taxonomies',
						'delete_terms' => 'manage_news_taxonomies',
						'assign_terms' => 'create_editorial_news',
					),
				)
			);
		}
	}

	/** Install/repair frozen terms after an upgrade only for an authorized session. */
	public static function maybe_ensure_default_terms() {
		$target           = Phase4Contracts::TARGET_VERSION . '-' . Phase4Contracts::CHECKPOINT;
		$current_terms    = function_exists( 'get_option' ) ? get_option( self::TERM_VERSION_OPTION, '' ) : '';
		$current_contract = function_exists( 'get_option' ) ? get_option( 'sabri_feed_phase4_contract_version', '' ) : '';
		if ( $target === $current_terms && $target === $current_contract ) {
			return array( 'created' => array(), 'skipped' => array(), 'failed' => array(), 'success' => true );
		}
		if ( ! self::authorized_upgrade_request() ) {
			return array(
				'created' => array(),
				'skipped' => array(),
				'failed'  => array( 'authorization' => 'An authorized News settings administrator is required.' ),
				'success' => false,
			);
		}

		$report = self::ensure_default_terms();
		if ( $report['success'] && function_exists( 'update_option' ) ) {
			update_option( self::TERM_VERSION_OPTION, $target, false );
			update_option( 'sabri_feed_phase4_contract_version', $target, false );
		}
		return $report;
	}

	/** Ensure frozen section and article-type terms exist additively. */
	public static function ensure_default_terms() {
		$report = array( 'created' => array(), 'skipped' => array(), 'failed' => array(), 'success' => false );
		if ( ! function_exists( 'term_exists' ) || ! function_exists( 'wp_insert_term' ) ) {
			$report['failed']['runtime'] = 'Required WordPress term functions are unavailable.';
			return $report;
		}
		self::ensure_terms( 'sabri_news_section', Phase4Contracts::sections(), $report );
		self::ensure_terms( 'sabri_news_type', Phase4Contracts::article_types(), $report );
		$report['success'] = empty( $report['failed'] );
		return $report;
	}

	/** Add missing terms without rewriting or deleting administrator data. */
	private static function ensure_terms( $taxonomy, array $terms, array &$report ) {
		foreach ( $terms as $slug => $label ) {
			$key    = $taxonomy . ':' . $slug;
			$exists = term_exists( $slug, $taxonomy );
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $exists ) ) {
				$report['failed'][ $key ] = self::error_message( $exists );
				continue;
			}
			if ( $exists ) {
				$report['skipped'][] = $key;
				continue;
			}
			$result = wp_insert_term( __( $label, 'sabri-complete-home-news-feed' ), $taxonomy, array( 'slug' => $slug ) );
			if ( function_exists( 'apply_filters' ) ) {
				$result = apply_filters( 'sabri_feed_phase4_insert_term_result', $result, $taxonomy, $slug );
			}
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
				$report['failed'][ $key ] = self::error_message( $result );
				continue;
			}
			$verified = is_array( $result ) && ! empty( $result['term_id'] );
			if ( ! $verified ) {
				$verified = (bool) term_exists( $slug, $taxonomy );
			}
			if ( ! $verified ) {
				$report['failed'][ $key ] = 'WordPress did not return or verify the inserted term.';
				continue;
			}
			$report['created'][] = $key;
		}
	}

	/** Require a privileged web session, or an explicit WP-CLI execution. */
	private static function authorized_upgrade_request() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		return function_exists( 'current_user_can' ) && ( current_user_can( 'manage_news_settings' ) || current_user_can( 'manage_options' ) );
	}

	/** Return a bounded diagnostic message without exposing private data. */
	private static function error_message( $error ) {
		$message = is_object( $error ) && method_exists( $error, 'get_error_message' ) ? $error->get_error_message() : 'Unknown term installation error.';
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $message ) : trim( strip_tags( (string) $message ) );
	}
}
