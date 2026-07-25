<?php
/**
 * Final publication prerequisite policy.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Enforces authoritative source and review ledgers before publication transitions. */
final class Phase5PublicationPolicy {
	public static function register() {}

	public static function eligible( $article_id, $target_state ) {
		$article_id = Phase5Contracts::positive_int( $article_id );
		if ( $article_id < 1 || ! in_array( $target_state, array( 'ready-for-publication','scheduled','published' ), true ) ) return true;
		$revision_id = self::revision_id( $article_id );
		$medical_required = self::medical_required( $article_id );
		$translation_required = self::translation_required( $article_id );
		$errors = array();
		if ( Phase5FeatureSettings::enabled('sources_enabled') && ! SourceRegistry::publication_ready($article_id) ) $errors[]='verified_source_required';
		if ( Phase5FeatureSettings::enabled('reviews_enabled') && ! ReviewLedger::publication_ready($article_id,$revision_id,$medical_required,$translation_required) ) $errors[]='required_reviews_missing';
		$privacy = self::privacy_check( $article_id ); if(!empty($privacy['blocked']))$errors[]='privacy_blocked';
		return empty($errors)?true:array('success'=>false,'code'=>'phase5_release_blocked','errors'=>$errors);
	}
	private static function revision_id($article_id){if(function_exists('wp_get_post_revisions')){$revisions=wp_get_post_revisions($article_id,array('numberposts'=>1,'orderby'=>'ID','order'=>'DESC'));if($revisions){$first=reset($revisions);return(int)$first->ID;}}return 0;}
	private static function medical_required($article_id){return function_exists('get_post_meta')&&'1'===(string)get_post_meta($article_id,'_sabri_news_medical_review_required',true);}
	private static function translation_required($article_id){return function_exists('get_post_meta')&&'1'===(string)get_post_meta($article_id,'_sabri_news_translation_review_required',true);}
	private static function privacy_check($article_id){if(!function_exists('get_post'))return array('blocked'=>false,'categories'=>array());$post=get_post($article_id);if(!$post)return array('blocked'=>true,'categories'=>array('missing'));return PrivacyScanner::scan((string)$post->post_title."\n".(string)$post->post_excerpt."\n".strip_tags((string)$post->post_content));}
}
