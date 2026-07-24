<?php
/**
 * Phase 4B read-only newsroom diagnostics.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Reports Phase 4B health without mutating editorial data. */
final class NewsroomDiagnostics {
	/** Register diagnostic foundations. */
	public static function register() {
		// Read-only checks are invoked explicitly by authorized administrators.
	}

	/** Return a bounded capability-protected health report. */
	public static function report() {
		if ( ! function_exists( 'current_user_can' ) || ( ! current_user_can( 'manage_news_settings' ) && ! current_user_can( 'manage_options' ) ) ) {
			return array( 'success' => false, 'code' => 'diagnostics_access_denied', 'checks' => array() );
		}
		$required_classes = array(
			NewsPolicy::class,
			NewsWorkflow::class,
			NewsComposerValidator::class,
			NewsQueueService::class,
			NewsAudit::class,
			NewsSchedulingService::class,
			NewsService::class,
		);
		$classes = array();
		foreach ( $required_classes as $class ) {
			$classes[ $class ] = class_exists( $class );
		}
		$definition = EditorialNewsPostType::definition();
		$flags = NewsFeatureSettings::get();
		return array(
			'success' => ! in_array( false, $classes, true ),
			'code' => ! in_array( false, $classes, true ) ? 'diagnostics_healthy' : 'diagnostics_incomplete',
			'checks' => array(
				'classes' => $classes,
				'post_type' => Phase4Contracts::POST_TYPE,
				'post_type_registered' => function_exists( 'post_type_exists' ) ? post_type_exists( Phase4Contracts::POST_TYPE ) : false,
				'publicly_queryable' => ! empty( $definition['publicly_queryable'] ),
				'rest_exposed' => ! empty( $definition['show_in_rest'] ),
				'feature_flags' => $flags,
				'visible_queue_slugs' => array_keys( NewsQueueService::visible_definitions() ),
				'auto_publish_enabled' => false,
				'mutated' => false,
			),
		);
	}
}
