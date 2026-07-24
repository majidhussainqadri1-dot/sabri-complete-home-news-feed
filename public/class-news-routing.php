<?php
/**
 * Canonical public Editorial News routing.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Registers fail-closed public routes and resolves public projections. */
final class NewsRouting {
	const Q_ARCHIVE='sabri_news_public_archive';
	const Q_SLUG='sabri_news_public_slug';
	const Q_TAXONOMY='sabri_news_public_taxonomy';
	const Q_TERM='sabri_news_public_term';

	public static function register(){
		if(!function_exists('add_action')||!function_exists('add_filter')){return;}
		add_action('init',array(__CLASS__,'rewrite_rules'),12); add_action('template_redirect',array(__CLASS__,'prepare_request'),0);
		add_filter('query_vars',array(__CLASS__,'query_vars')); add_filter('template_include',array(__CLASS__,'template_include'),99);
		add_filter('redirect_canonical',array(__CLASS__,'redirect_canonical'),10,2); add_filter('document_title_parts',array(__CLASS__,'document_title_parts')); add_filter('wp_robots',array(__CLASS__,'robots'));
	}
	public static function rewrite_rules(){
		if(!NewsPolicy::public_reads_allowed()||!function_exists('add_rewrite_rule')){return;}
		add_rewrite_rule('^news/?$','index.php?'.self::Q_ARCHIVE.'=1','top');
		foreach(array('section'=>'sabri_news_section','topic'=>'sabri_news_topic','country'=>'sabri_news_country','region'=>'sabri_news_region','type'=>'sabri_news_type') as $route=>$taxonomy){
			add_rewrite_rule('^news/'.$route.'/([a-z0-9]+(?:-[a-z0-9]+)*)/?$','index.php?'.self::Q_ARCHIVE.'=1&'.self::Q_TAXONOMY.'='.$taxonomy.'&'.self::Q_TERM.'=$matches[1]','top');
		}
		add_rewrite_rule('^news/([a-z0-9]+(?:-[a-z0-9]+)*)/?$','index.php?'.self::Q_SLUG.'=$matches[1]','top');
	}
	public static function query_vars($vars){$vars=is_array($vars)?$vars:array();foreach(array(self::Q_ARCHIVE,self::Q_SLUG,self::Q_TAXONOMY,self::Q_TERM)as$var){$vars[]=$var;}return array_values(array_unique($vars));}

	public static function prepare_request(){
		if(!self::is_news_request()){return;}
		if(!NewsPolicy::public_reads_allowed()){self::mark_404();return;}
		$slug=self::query_var(self::Q_SLUG);$native_id=self::native_single_id();
		if(''!==$slug||$native_id>0){
			$result=NewsQueryService::single($native_id>0?$native_id:$slug);
			if(empty($result['success'])){self::mark_404();return;}
			NewsPublicRuntime::set_context(array('route'=>'single','article'=>$result['data'],'canonical_base'=>$result['data']['canonical_url'],'title'=>$result['data']['headline']));return;
		}
		$taxonomy=self::query_var(self::Q_TAXONOMY);$term=self::query_var(self::Q_TERM);
		if(''===$taxonomy&&''===$term){$native=self::native_taxonomy_context();$taxonomy=$native['taxonomy'];$term=$native['term'];}
		$args=self::request_filters();$title=__('News','sabri-complete-home-news-feed');$canonical=function_exists('home_url')?home_url('/news/'):'/news/';
		if(''!==$taxonomy||''!==$term){
			$filter=self::taxonomy_filter($taxonomy);$term=self::strict_slug($term);
			if(''===$filter||''===$term||!self::term_exists($taxonomy,$term)){self::mark_404();return;}
			$args[$filter]=$term;$title=self::term_title($taxonomy,$term);$canonical=function_exists('home_url')?home_url('/news/'.self::taxonomy_route($taxonomy).'/'.rawurlencode($term).'/'):'/news/';
		}
		if(isset($args['view'])){
			if('editors-picks'===$args['view']){$args['editor_pick']=1;}elseif('recently-updated'===$args['view']){$args['recently_updated']=1;}elseif(!in_array($args['view'],array('latest'),true)){self::mark_404();return;}unset($args['view']);
		}
		$is_main=''===$taxonomy&&self::is_unfiltered_landing($args);
		$result=$is_main?NewsQueryService::landing():NewsQueryService::query($args);
		if(empty($result['success'])){
			$status=isset($result['status'])?(int)$result['status']:500;
			$message=isset($result['message'])?(string)$result['message']:__('The News filters could not be applied.','sabri-complete-home-news-feed');
			if(404===$status){self::mark_404();return;}
			self::mark_status($status);
			$result=array('success'=>true,'data'=>array('items'=>array(),'page'=>1,'per_page'=>NewsQueryService::DEFAULT_PER_PAGE,'total'=>0,'max_pages'=>0,'has_more'=>false,'filters'=>array()));
			NewsPublicRuntime::set_context(array('route'=>'archive','result'=>$result,'title'=>$title,'description'=>$message,'canonical_base'=>$canonical));return;
		}
		NewsPublicRuntime::set_context(array('route'=>$is_main?'landing':(''!==$taxonomy?'taxonomy':'archive'),'result'=>$result,'title'=>$title,'description'=>'','canonical_base'=>$canonical));
	}

	public static function template_include($template){$context=NewsPublicRuntime::context();if(empty($context['route'])){return$template;}$file='single'===$context['route']?SABRI_HNF_PATH.'templates/news-single-page.php':SABRI_HNF_PATH.'templates/news-archive-page.php';return is_readable($file)?$file:$template;}
	public static function redirect_canonical($redirect_url,$requested_url){unset($requested_url);$c=NewsPublicRuntime::context();return 'single'===(isset($c['route'])?$c['route']:'')&&!empty($c['canonical_base'])?$c['canonical_base']:$redirect_url;}
	public static function document_title_parts($parts){$parts=is_array($parts)?$parts:array();$c=NewsPublicRuntime::context();if(!empty($c['title'])){$parts['title']=$c['title'];}return$parts;}
	public static function robots($robots){$robots=is_array($robots)?$robots:array();$c=NewsPublicRuntime::context();if(!empty($c['article']['projection'])&&'retraction'===$c['article']['projection']){$robots['noindex']=true;$robots['nofollow']=false;}return$robots;}

	private static function is_news_request(){
		if('1'===(string)self::query_var(self::Q_ARCHIVE)||''!==self::query_var(self::Q_SLUG)){return true;}
		if(function_exists('is_singular')&&is_singular(Phase4Contracts::POST_TYPE)){return true;}
		if(function_exists('is_post_type_archive')&&is_post_type_archive(Phase4Contracts::POST_TYPE)){return true;}
		if(function_exists('is_tax')){foreach(Phase4Contracts::taxonomies()as$taxonomy){if(is_tax($taxonomy)){return true;}}}
		return false;
	}
	private static function native_single_id(){return function_exists('is_singular')&&is_singular(Phase4Contracts::POST_TYPE)&&function_exists('get_queried_object_id')?max(0,(int)get_queried_object_id()):0;}
	private static function native_taxonomy_context(){
		$out=array('taxonomy'=>'','term'=>'');if(!function_exists('get_queried_object')){return$out;}$o=get_queried_object();if(!is_object($o)||empty($o->taxonomy)||empty($o->slug)){return$out;}$taxonomy=(string)$o->taxonomy;$term=self::strict_slug((string)$o->slug);if(!in_array($taxonomy,Phase4Contracts::taxonomies(),true)||''===$term){return$out;}return array('taxonomy'=>$taxonomy,'term'=>$term);
	}
	private static function request_filters(){
		$args=array();$map=array('q'=>'q','section'=>'section','topic'=>'topic','country'=>'country','region'=>'region','type'=>'type','date_from'=>'date_from','date_to'=>'date_to','author'=>'author','institution'=>'institution','research'=>'research','corrected'=>'corrected','retracted'=>'retracted','page'=>'page','per_page'=>'per_page','view'=>'view');
		foreach($map as$request_key=>$arg_key){if(isset($_GET[$request_key])&&is_scalar($_GET[$request_key])){$args[$arg_key]=function_exists('wp_unslash')?wp_unslash($_GET[$request_key]):$_GET[$request_key];}}
		return$args;
	}
	private static function is_unfiltered_landing(array$args){return empty($args)||((!isset($args['page'])||'1'===(string)$args['page'])&&count(array_filter($args,static function($v,$k){return'page'!==$k&&''!==$v&&null!==$v;},ARRAY_FILTER_USE_BOTH))===0);}
	private static function taxonomy_filter($taxonomy){$map=array('sabri_news_section'=>'section','sabri_news_topic'=>'topic','sabri_news_country'=>'country','sabri_news_region'=>'region','sabri_news_type'=>'type');return isset($map[$taxonomy])?$map[$taxonomy]:'';}
	private static function taxonomy_route($taxonomy){$filter=self::taxonomy_filter($taxonomy);return''!==$filter?$filter:'section';}
	private static function term_exists($taxonomy,$term){
		if('sabri_news_section'===$taxonomy){return isset(Phase4Contracts::sections()[$term]);}if('sabri_news_type'===$taxonomy){return isset(Phase4Contracts::article_types()[$term]);}
		if(function_exists('term_exists')){$exists=term_exists($term,$taxonomy);return!empty($exists)&&!(function_exists('is_wp_error')&&is_wp_error($exists));}
		if(function_exists('get_term_by')){$o=get_term_by('slug',$term,$taxonomy);return$o&&!(function_exists('is_wp_error')&&is_wp_error($o));}
		return function_exists('apply_filters')?(bool)apply_filters('sabri_phase4c_test_term_exists',false,$taxonomy,$term):false;
	}
	private static function term_title($taxonomy,$term){if('sabri_news_section'===$taxonomy&&isset(Phase4Contracts::sections()[$term])){return Phase4Contracts::sections()[$term];}if('sabri_news_type'===$taxonomy&&isset(Phase4Contracts::article_types()[$term])){return Phase4Contracts::article_types()[$term];}if(function_exists('get_term_by')){$o=get_term_by('slug',$term,$taxonomy);if($o&&!is_wp_error($o)&&!empty($o->name)){return(string)$o->name;}}return ucwords(str_replace('-',' ',$term));}
	private static function query_var($key){return function_exists('get_query_var')?(string)get_query_var($key,''):(isset($GLOBALS['wp_query']->query_vars[$key])?(string)$GLOBALS['wp_query']->query_vars[$key]:'');}
	private static function strict_slug($value){return is_string($value)&&strlen($value)<=120&&preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$value)?$value:'';}
	private static function mark_404(){self::mark_status(404);if(function_exists('nocache_headers')){nocache_headers();}}
	private static function mark_status($status){global$wp_query;if(is_object($wp_query)&&method_exists($wp_query,'set_404')&&404===$status){$wp_query->set_404();}if(function_exists('status_header')){status_header((int)$status);}}
}
