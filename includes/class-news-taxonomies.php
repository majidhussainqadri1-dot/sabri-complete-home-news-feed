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

/**
 * Registers the Editorial News taxonomy model without exposing public routes
 * before the master feature gate is accepted.
 */
final class NewsTaxonomies {
	/** Register hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 9 );
			add_action( 'init', array( __CLASS__, 'ensure_default_terms' ), 30 );
		}
	}

	/** Return taxonomy definitions. */
	public static function definitions() {
		return array(
			'sabri_news_section' => array(
				'singular'     => __( 'News Section', 'sabri-complete-home-news-feed' ),
				'plural'       => __( 'News Sections', 'sabri-complete-home-news-feed' ),
				'hierarchical' => true,
				'route'        => 'section',
			),
			'sabri_news_topic' => array(
				'singular'     => __( 'News Topic', 'sabri-complete-home-news-feed' ),
				'plural'       => __( 'News Topics', 'sabri-complete-home-news-feed' ),
				'hierarchical' => true,
				'route'        => 'topic',
			),
			'sabri_news_country' => array(
				'singular'     => __( 'News Country', 'sabri-complete-home-news-feed' ),
				'plural'       => __( 'News Countries', 'sabri-complete-home-news-feed' ),
				'hierarchical' => true,
				'route'        => 'country',
			),
			'sabri_news_region' => array(
				'singular'     => __( 'News Region', 'sabri-complete-home-news-feed' ),
				'plural'       => __( 'News Regions', 'sabri-complete-home-news-feed' ),
				'hierarchical' => true,
				'route'        => 'region',
			),
			'sabri_news_type' => array(
				'singular'     => __( 'News Type', 'sabri-complete-home-news-feed' ),
				'plural'       => __( 'News Types', 'sabri-complete-home-news-feed' ),
				'hierarchical' => false,
				'route'        => 'type',
			),
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
					'public'            => $public_enabled,
					'publicly_queryable' => $public_enabled,
					'show_ui'           => true,
					'show_admin_column' => true,
					'show_in_nav_menus' => false,
					'show_in_rest'      => false,
					'hierarchical'      => ! empty( $definition['hierarchical'] ),
					'query_var'         => $public_enabled ? $taxonomy : false,
					'rewrite'           => $public_enabled ? array( 'slug' => 'news/' . $definition['route'], 'with_front' => false ) : false,
					'capabilities'      => array(
						'manage_terms' => 'manage_news_taxonomies',
						'edit_terms'   => 'manage_news_taxonomies',
						'delete_terms' => 'manage_news_taxonomies',
						'assign_terms' => 'create_editorial_news',
					),
				)
			);
		}
	}

	/** Ensure frozen section and article-type terms exist additively. */
	public static function ensure_default_terms() {
		$report = array( 'created' => array(), 'skipped' => array() );
		if ( ! function_exists( 'term_exists' ) || ! function_exists( 'wp_insert_term' ) ) {
			return $report;
		}
		self::ensure_terms( 'sabri_news_section', Phase4Contracts::sections(), $report );
		self::ensure_terms( 'sabri_news_type', Phase4Contracts::article_types(), $report );
		return $report;
	}

	/** Add missing terms without rewriting or deleting administrator data. */
	private static function ensure_terms( $taxonomy, array $terms, array &$report ) {
		foreach ( $terms as $slug => $label ) {
			$key = $taxonomy . ':' . $slug;
			if ( term_exists( $slug, $taxonomy ) ) {
				$report['skipped'][] = $key;
				continue;
			}
			$result = wp_insert_term( __( $label, 'sabri-complete-home-news-feed' ), $taxonomy, array( 'slug' => $slug ) );
			if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
				$report['skipped'][] = $key;
			} else {
				$report['created'][] = $key;
			}
		}
	}
}
