<?php
/**
 * Public read-only Editorial News REST routes.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Exposes only allow-listed public News projections. */
final class RestNews {
	public static function register(){if(function_exists('add_action')){add_action('rest_api_init',array(__CLASS__,'register_routes'));}}
	public static function register_routes(){
		if(!function_exists('register_rest_route')){return;}
		register_rest_route(Phase4Contracts::REST_NAMESPACE,'/news',array('methods'=>'GET','callback'=>array(__CLASS__,'collection'),'permission_callback'=>array(__CLASS__,'permission_callback'),'args'=>self::collection_args()));
		register_rest_route(Phase4Contracts::REST_NAMESPACE,'/news/(?P<id>[1-9][0-9]*)',array('methods'=>'GET','callback'=>array(__CLASS__,'single'),'permission_callback'=>array(__CLASS__,'permission_callback'),'args'=>array('id'=>array('description'=>__('Editorial News article ID.','sabri-complete-home-news-feed'),'type'=>'integer','required'=>true,'minimum'=>1,'sanitize_callback'=>array(__CLASS__,'sanitize_positive_int'),'validate_callback'=>array(__CLASS__,'validate_positive_int')))));
	}
	public static function permission_callback(){return NewsPolicy::public_reads_allowed();}

	public static function collection($request){
		$unknown=self::unknown_params($request,array_keys(self::collection_args()));
		if($unknown){return self::response_from_result(array('success'=>false,'code'=>'public_news_filter_invalid','message'=>__('An unsupported News filter was supplied.','sabri-complete-home-news-feed'),'field'=>$unknown[0],'status'=>400,'data'=>array()));}
		$args=array();foreach(array_keys(self::collection_args())as$key){$value=self::request_param($request,$key);if(null!==$value&&''!==$value){$args[$key]=$value;}}
		return self::response_from_result(NewsQueryService::query($args));
	}
	public static function single($request){return self::response_from_result(NewsQueryService::single(self::request_param($request,'id')));}

	public static function collection_args(){
		$slug=array('type'=>'string','maxLength'=>120,'pattern'=>'^[a-z0-9]+(?:-[a-z0-9]+)*$','sanitize_callback'=>array(__CLASS__,'sanitize_slug'),'validate_callback'=>array(__CLASS__,'validate_slug'));
		$date=array('type'=>'string','pattern'=>'^\d{4}-\d{2}-\d{2}$','sanitize_callback'=>'sanitize_text_field','validate_callback'=>array(__CLASS__,'validate_date'));
		$bool=array('type'=>'boolean','sanitize_callback'=>array(__CLASS__,'sanitize_boolean'),'validate_callback'=>array(__CLASS__,'validate_boolean'));
		$positive=array('type'=>'integer','minimum'=>1,'sanitize_callback'=>array(__CLASS__,'sanitize_positive_int'),'validate_callback'=>array(__CLASS__,'validate_positive_int'));
		return array(
			'keyword'=>array('type'=>'string','maxLength'=>NewsQueryService::MAX_KEYWORD_LENGTH,'sanitize_callback'=>'sanitize_text_field'),
			'q'=>array('type'=>'string','maxLength'=>NewsQueryService::MAX_KEYWORD_LENGTH,'sanitize_callback'=>'sanitize_text_field'),
			'section'=>$slug,'topic'=>$slug,'country'=>$slug,'region'=>$slug,'type'=>$slug,
			'date_from'=>$date,'date_to'=>$date,'author'=>$positive,'institution'=>$slug,
			'research'=>$bool,'corrected'=>$bool,'retracted'=>$bool,
			'page'=>$positive,
			'per_page'=>array('type'=>'integer','minimum'=>1,'maximum'=>NewsQueryService::MAX_PER_PAGE,'sanitize_callback'=>array(__CLASS__,'sanitize_positive_int'),'validate_callback'=>array(__CLASS__,'validate_per_page')),
		);
	}
	public static function validate_positive_int($value){return is_int($value)||(is_string($value)&&preg_match('/^[1-9][0-9]*$/D',$value));}
	public static function sanitize_positive_int($value){return self::validate_positive_int($value)?(int)$value:0;}
	public static function validate_per_page($value){return self::validate_positive_int($value)&&(int)$value<=NewsQueryService::MAX_PER_PAGE;}
	public static function validate_slug($value){return ''===(string)$value||(is_string($value)&&strlen($value)<=120&&preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$value));}
	public static function sanitize_slug($value){return self::validate_slug($value)?(string)$value:'';}
	public static function validate_boolean($value){return in_array($value,array(true,false,1,0,'1','0'),true);}
	public static function sanitize_boolean($value){if(in_array($value,array(true,1,'1'),true)){return 1;}if(in_array($value,array(false,0,'0'),true)){return 0;}return null;}
	public static function validate_date($value){if(''===(string)$value){return true;}if(!is_string($value)||!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$value,$m)){return false;}return checkdate((int)$m[2],(int)$m[3],(int)$m[1]);}

	private static function response_from_result(array$result){
		$status=isset($result['status'])?(int)$result['status']:500;
		$payload=!empty($result['success'])?array('ok'=>true,'code'=>isset($result['code'])?$result['code']:'public_news_found','data'=>$result['data']):array('ok'=>false,'code'=>isset($result['code'])?$result['code']:'public_news_query_failed','message'=>isset($result['message'])?$result['message']:__('The News request could not be completed.','sabri-complete-home-news-feed'),'field'=>isset($result['field'])?$result['field']:'');
		if(class_exists('WP_REST_Response')){$response=new \WP_REST_Response($payload,$status);if(method_exists($response,'header')){$response->header('Cache-Control',200===$status?'public, max-age=60, s-maxage=60':'no-store');$response->header('X-Content-Type-Options','nosniff');$response->header('Vary','Accept-Language');}return$response;}
		return array('status'=>$status,'payload'=>$payload);
	}
	private static function request_param($request,$key){if(is_array($request)&&array_key_exists($key,$request)){return$request[$key];}if(is_object($request)&&method_exists($request,'get_param')){return$request->get_param($key);}return null;}
	private static function unknown_params($request,array$allowed){
		$params=array();if(is_array($request)){$params=$request;}elseif(is_object($request)&&method_exists($request,'get_params')){$params=$request->get_params();}
		$ignored=array('context','_fields','_embed','_envelope');$unknown=array();foreach(array_keys(is_array($params)?$params:array())as$key){if(!in_array($key,$allowed,true)&&!in_array($key,$ignored,true)){$unknown[]=(string)$key;}}return$unknown;
	}
}
