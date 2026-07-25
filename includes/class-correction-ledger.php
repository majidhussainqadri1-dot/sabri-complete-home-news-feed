<?php
/**
 * Correction, clarification, update, and retraction ledger.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Preserves private deliberation and approved public accountability history. */
final class CorrectionLedger {
	public static function register() {}

	public static function request( $article_id, $class, array $input ) {
		$article_id = Phase5Contracts::positive_int( $article_id );
		if ( $article_id < 1 || ! in_array( $class, Phase5Contracts::correction_classes(), true ) || ! self::can_manage() ) { return self::error( 'phase5_permission_denied', 403 ); }
		if ( function_exists( 'get_post_type' ) && Phase4Contracts::POST_TYPE !== get_post_type( $article_id ) ) { return self::error( 'phase5_not_found', 404 ); }
		$open = Phase5Repository::query( 'corrections', array( 'article_id' => $article_id ), 100, 0, 'id', 'DESC' );
		foreach ( $open as $existing ) { if ( in_array( (string) $existing['state'], array( 'requested', 'under-review', 'approved' ), true ) ) return self::error( 'phase5_conflict', 409 ); }
		$private_reason = isset( $input['private_reason'] ) ? trim( (string) $input['private_reason'] ) : '';
		if ( '' === $private_reason || strlen( $private_reason ) > 10000 ) { return self::error( 'phase5_payload_invalid', 400 ); }
		if ( class_exists( __NAMESPACE__ . '\\NewsPublicSnapshot' ) ) { NewsPublicSnapshot::capture( $article_id, true ); }
		$now = gmdate( 'Y-m-d H:i:s' );
		$id = Phase5Repository::insert( 'corrections', array(
			'article_id'=>$article_id,'correction_class'=>$class,'state'=>'requested','requester_user_id'=>self::actor(),
			'affected_claim'=>isset($input['affected_claim'])?substr(trim(strip_tags((string)$input['affected_claim'])),0,4000):'',
			'private_reason'=>$private_reason,'public_note'=>'','previous_revision_id'=>max(0,(int)($input['previous_revision_id']??0)),
			'corrected_revision_id'=>0,'approved_by'=>0,'approved_at'=>null,'published_at'=>null,'created_at'=>$now,'updated_at'=>$now,
		) );
		if ( $id < 1 ) return self::error( 'phase5_query_failed', 500 );
		if ( function_exists( 'update_post_meta' ) && 'retraction' !== $class ) { update_post_meta( $article_id, Phase4Contracts::WORKFLOW_META_KEY, 'correction-pending' ); }
		PreviewTokenService::revoke_article( $article_id );
		Phase5AuditIntegrity::record( 'correction-requested', 'correction', $id, array( 'correction_class'=>$class,'state'=>'requested' ) );
		return array( 'success'=>true,'status'=>201,'data'=>Phase5Repository::find('corrections',$id) );
	}

	public static function approve( $id, array $input ) {
		$row = Phase5Repository::find( 'corrections', $id );
		if ( ! $row || ! self::can_manage() || ! in_array( $row['state'], array('requested','under-review'), true ) ) return self::error( 'phase5_conflict', 409 );
		$public_note = isset($input['public_note'])?trim(strip_tags((string)$input['public_note'])):'';
		if ( '' === $public_note || strlen($public_note)>4000 ) return self::error( 'phase5_payload_invalid', 400 );
		$corrected_revision = max( 0, (int) ($input['corrected_revision_id'] ?? 0) );
		if ( $corrected_revision > 0 && function_exists( 'wp_is_post_revision' ) && (int) wp_is_post_revision( $corrected_revision ) !== (int) $row['article_id'] ) return self::error( 'phase5_payload_invalid', 400 );
		$data = array('state'=>'approved','public_note'=>$public_note,'corrected_revision_id'=>$corrected_revision,'approved_by'=>self::actor(),'approved_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s'));
		if ( ! Phase5Repository::update('corrections',$id,$data) ) return self::error('phase5_query_failed',500);
		Phase5AuditIntegrity::record('correction-approved','correction',$id,array('correction_class'=>$row['correction_class'],'state'=>'approved'));
		return array('success'=>true,'status'=>200,'data'=>Phase5Repository::find('corrections',$id));
	}

	public static function publish( $id ) {
		$row = Phase5Repository::find('corrections',$id);
		if ( ! $row || ! self::can_manage() || 'approved' !== $row['state'] ) return self::error('phase5_conflict',409);
		$article_id=(int)$row['article_id']; $class=(string)$row['correction_class'];
		if ( 'retraction' === $class ) {
			if ( ! function_exists('current_user_can') || ! current_user_can('retract_editorial_news') ) return self::error('phase5_permission_denied',403);
			if ( function_exists('update_post_meta') ) { update_post_meta($article_id,Phase4Contracts::WORKFLOW_META_KEY,'retracted'); update_post_meta($article_id,'_sabri_news_retraction_notice',(string)$row['public_note']); }
			if ( function_exists('wp_update_post') ) { $updated_post = wp_update_post(array('ID'=>$article_id,'post_status'=>'private'), true); if ( function_exists('is_wp_error') && is_wp_error($updated_post) ) return self::error('phase5_query_failed',500); }
		} else {
			if ( function_exists('update_post_meta') ) { update_post_meta($article_id,Phase4Contracts::WORKFLOW_META_KEY,'corrected'); update_post_meta($article_id,'_sabri_news_correction_notice',(string)$row['public_note']); }
			if ( class_exists(__NAMESPACE__.'\\NewsPublicSnapshot') ) { NewsPublicSnapshot::capture($article_id,true); }
		}
		if ( ! Phase5Repository::update('corrections',$id,array('state'=>'published','published_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s'))) ) return self::error('phase5_query_failed',500);
		if ( class_exists(__NAMESPACE__.'\\NewsCache') ) { NewsCache::invalidate_all('phase5-correction'); }
		PreviewTokenService::revoke_article($article_id);
		Phase5AuditIntegrity::record('correction-published','correction',$id,array('correction_class'=>$class,'state'=>'published'));
		return array('success'=>true,'status'=>200);
	}

	public static function public_history( $article_id ) {
		if ( ! Phase5FeatureSettings::enabled('corrections_enabled') ) return array();
		$rows=Phase5Repository::query('corrections',array('article_id'=>Phase5Contracts::positive_int($article_id),'state'=>'published'),100,0,'published_at','ASC');
		$out=array(); foreach($rows as$row){$out[]=array('id'=>(int)$row['id'],'class'=>(string)$row['correction_class'],'public_note'=>(string)$row['public_note'],'published_at'=>$row['published_at'],'previous_revision_id'=>(int)$row['previous_revision_id'],'corrected_revision_id'=>(int)$row['corrected_revision_id']);} return$out;
	}
	private static function can_manage(){return Phase5FeatureSettings::enabled('corrections_enabled')&&function_exists('current_user_can')&&current_user_can('manage_news_corrections');}
	private static function actor(){return function_exists('get_current_user_id')?(int)get_current_user_id():0;}
	private static function error($code,$status){return array('success'=>false,'status'=>$status,'code'=>$code);}
}
