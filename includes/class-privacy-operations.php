<?php
/**
 * Privacy export, erase, and retention operations.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Integrates Phase 5 records with WordPress privacy operations. */
final class PrivacyOperations {
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
			add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
		}
		if ( function_exists( 'add_action' ) ) add_action( 'sabri_hnf_phase5_cleanup', array( __CLASS__, 'retention_cleanup' ) );
	}
	public static function exporters( $exporters ) {
		$exporters = is_array($exporters)?$exporters:array();
		$exporters['sabri-phase5-submissions']=array('exporter_friendly_name'=>__('Sabri Editorial News submissions','sabri-complete-home-news-feed'),'callback'=>array(__CLASS__,'export_user'));
		return $exporters;
	}
	public static function erasers( $erasers ) {
		$erasers=is_array($erasers)?$erasers:array();
		$erasers['sabri-phase5-submissions']=array('eraser_friendly_name'=>__('Sabri Editorial News submission drafts','sabri-complete-home-news-feed'),'callback'=>array(__CLASS__,'erase_user'));
		return $erasers;
	}
	public static function export_user( $email, $page=1 ) {
		$user = function_exists('get_user_by')?get_user_by('email',$email):false; if(!$user)return array('data'=>array(),'done'=>true);
		$rows=Phase5Repository::query('submissions',array('submitter_user_id'=>(int)$user->ID),50,max(0,((int)$page-1)*50),'id','ASC');$data=array();
		foreach($rows as$row){$data[]=array('group_id'=>'sabri-editorial-submissions','group_label'=>__('Editorial News submissions','sabri-complete-home-news-feed'),'item_id'=>'submission-'.$row['id'],'data'=>array(array('name'=>__('Status','sabri-complete-home-news-feed'),'value'=>$row['status']),array('name'=>__('Title','sabri-complete-home-news-feed'),'value'=>$row['title']),array('name'=>__('Created','sabri-complete-home-news-feed'),'value'=>$row['created_at'])));}
		return array('data'=>$data,'done'=>count($rows)<50);
	}
	public static function erase_user( $email, $page=1 ) {
		unset( $page );
		$user=function_exists('get_user_by')?get_user_by('email',$email):false;if(!$user)return array('items_removed'=>false,'items_retained'=>false,'messages'=>array(),'done'=>true);
		$rows=Phase5Repository::query('submissions',array('submitter_user_id'=>(int)$user->ID),50,0,'id','ASC');$removed=false;$retained=false;$messages=array();
		foreach($rows as$row){if(in_array($row['status'],array('draft','withdrawn','rejected'),true)){self::erase_submission_files((int)$row['id']);Phase5Repository::update('submissions',$row['id'],array('title'=>'[erased]','summary'=>'','body'=>'','source_urls'=>'[]','declarations'=>'{}','private_editor_notes'=>'','submitter_user_id'=>0,'updated_at'=>gmdate('Y-m-d H:i:s')));$removed=true;}else{$retained=true;$messages[]=__('A submission linked to published or accountability records was retained with minimized personal data.','sabri-complete-home-news-feed');Phase5Repository::update('submissions',$row['id'],array('submitter_user_id'=>0,'private_editor_notes'=>'','updated_at'=>gmdate('Y-m-d H:i:s')));}}
		return array('items_removed'=>$removed,'items_retained'=>$retained,'messages'=>array_values(array_unique($messages)),'done'=>count($rows)<50);
	}
	public static function retention_cleanup() {
		if ( ! Phase5FeatureSettings::enabled( 'privacy_automation_enabled' ) ) return 0;
		$cutoff=gmdate('Y-m-d H:i:s',time()-180*DAY_IN_SECONDS);$count=0;
		foreach(array('draft','withdrawn','rejected')as$status){$rows=Phase5Repository::query('submissions',array('status'=>$status),100,0,'updated_at','ASC');foreach($rows as$row){if((string)$row['updated_at']>=$cutoff)continue;self::erase_submission_files((int)$row['id']);if(Phase5Repository::update('submissions',$row['id'],array('title'=>'[expired]','summary'=>'','body'=>'','source_urls'=>'[]','declarations'=>'{}','private_editor_notes'=>'','submitter_user_id'=>0,'updated_at'=>gmdate('Y-m-d H:i:s'))))$count++;}}
		return $count;
	}
	private static function erase_submission_files($submission_id){$rows=Phase5Repository::query('submission_files',array('submission_id'=>$submission_id),100,0,'id','ASC');foreach($rows as$row){$attachment=(int)$row['attachment_id'];if($attachment>0&&function_exists('wp_delete_attachment'))wp_delete_attachment($attachment,true);Phase5Repository::update('submission_files',$row['id'],array('original_name'=>'[erased]','sha256'=>str_repeat('0',64),'size_bytes'=>0,'status'=>'erased'));}}

}
