<?php
/**
 * Truthful Home-row adapters for companion modules.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes a module landing card when a companion has no normalized item API.
 * It never fabricates videos, books, clinics, listings or engagement data.
 */
final class CompanionHomeRowAdapters {
	/** Register one bounded fallback per cross-module row. */
	public static function register() {
		if ( ! function_exists( 'add_filter' ) ) {
			return;
		}
		$map = self::row_map();
		foreach ( $map as $row_key => $definition ) {
			add_filter(
				'sabri_hnf_home_row_items_' . $row_key,
				static function ( $items, $row, $limit ) use ( $definition ) {
					return CompanionHomeRowAdapters::provide( $items, $row, $limit, $definition );
				},
				10,
				3
			);
		}
	}

	/** Row-to-service contracts. */
	public static function row_map() {
		return array(
			'learn-classical-homeopathy' => array( 'service' => 'learning', 'title' => 'Learn Sabri Classical Homeopathy', 'path' => '/learn-sabri-classical-homeopathy/', 'summary' => 'Open the complete learning area.' ),
			'videos' => array( 'service' => 'video_wall', 'title' => 'Video Wall', 'path' => '/video-wall/', 'summary' => 'Open public educational videos.' ),
			'reels' => array( 'service' => 'reels', 'title' => 'Reels', 'path' => '/reels/', 'summary' => 'Open public short-form videos.' ),
			'pdf-books' => array( 'service' => 'pdf_library', 'title' => 'PDF Library', 'path' => '/pdf-library/', 'summary' => 'Open public PDF books and documents.' ),
			'clinics' => array( 'service' => 'appointments', 'title' => 'Worldwide Clinic', 'path' => '/worldwide-clinic/', 'summary' => 'Find doctors, clinics and appointment services.' ),
			'marketplace' => array( 'service' => 'marketplace', 'title' => 'Marketplace', 'path' => '/marketplace/', 'summary' => 'Open verified public marketplace listings.' ),
		);
	}

	/** Return provider items or one honest module entry point. */
	public static function provide( $items, $row, $limit, array $definition ) {
		$items = is_array( $items ) ? $items : array();
		$limit = max( 1, min( 12, (int) $limit ) );
		if ( ! empty( $items ) ) {
			return array_slice( $items, 0, $limit );
		}
		$state = CompanionIntegrationRegistry::service( $definition['service'] );
		if ( ! isset( $state['status'] ) || 'Missing' === $state['status'] ) {
			return array();
		}
		$url = function_exists( 'home_url' ) ? home_url( $definition['path'] ) : $definition['path'];
		if ( function_exists( 'apply_filters' ) ) {
			$url = apply_filters( 'sabri_hnf_module_url_' . sanitize_key( $definition['service'] ), $url, $definition );
		}
		if ( ! is_scalar( $url ) || '' === trim( (string) $url ) ) {
			return array();
		}
		return array(
			array(
				'title' => __( $definition['title'], 'sabri-complete-home-news-feed' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
				'url' => esc_url_raw( (string) $url ),
				'summary' => __( $definition['summary'], 'sabri-complete-home-news-feed' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
				'type' => 'module-entry',
				'integration_status' => $state['status'],
			)
		);
	}
}