<?php
/**
 * Phase 5 performance and index audit.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Provides bounded performance diagnostics without exposing query text publicly. */
final class Phase5Performance {
	public static function register() {}
	public static function audit() {
		$start=microtime(true);$schema=Phase5Database::verify();$counts=array();foreach(array_keys(Phase5Database::table_names())as$slug)$counts[$slug]=Phase5Repository::count($slug);$duration=(int)round((microtime(true)-$start)*1000);
		return array('success'=>empty($schema['missing_tables'])&&empty($schema['missing_indexes']),'duration_ms'=>$duration,'counts'=>$counts,'missing_tables'=>$schema['missing_tables'],'missing_indexes'=>$schema['missing_indexes'],'budgets'=>array('diagnostic_ms'=>500,'archive_query_ms'=>750,'rss_items'=>30,'sitemap_chunk'=>100));
	}
}
