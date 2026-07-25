<?php
/**
 * Complete Phase 5 Newsroom administration.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Adds bounded operational screens under the existing Newsroom. */
final class Phase5NewsroomAdmin {
	private static $screens=array(
		'phase5-overview'=>'Overview','phase5-submissions'=>'Submissions','phase5-editorial-review'=>'Editorial Review','phase5-fact-check'=>'Fact Check Queue','phase5-medical-review'=>'Medical Review Queue','phase5-translation-review'=>'Translation Review','phase5-calendar'=>'Editorial Calendar','phase5-scheduled'=>'Scheduled News','phase5-breaking'=>'Breaking News','phase5-sources'=>'Sources','phase5-corrections'=>'Corrections','phase5-retracted'=>'Retracted News','phase5-taxonomies'=>'Taxonomies','phase5-settings'=>'Settings','phase5-system-check'=>'System Check','phase5-audit'=>'Audit Log','phase5-release'=>'Release Readiness',
	);
	public static function register(){if(function_exists('add_action')){add_action('admin_menu',array(__CLASS__,'menu'),30);add_action('admin_enqueue_scripts',array(__CLASS__,'assets'));}}
	public static function menu(){if(!function_exists('add_submenu_page'))return;foreach(self::$screens as$slug=>$label){$cap=self::capability($slug);add_submenu_page('sabri-newsroom',__($label,'sabri-complete-home-news-feed'),__($label,'sabri-complete-home-news-feed'),$cap,'sabri-newsroom-'.$slug,array(__CLASS__,'render'));}}
	public static function assets($hook){if(false===strpos((string)$hook,'sabri-newsroom-phase5-'))return;wp_enqueue_style('sabri-hnf-phase5-admin',SABRI_HNF_URL.'assets/css/phase5-admin.css',array(),SABRI_HNF_VERSION);wp_enqueue_script('sabri-hnf-phase5-admin',SABRI_HNF_URL.'assets/js/phase5-admin.js',array(),SABRI_HNF_VERSION,true);}
	public static function render(){if(!function_exists('current_user_can'))return;$page=isset($_GET['page'])?sanitize_key(wp_unslash($_GET['page'])):'';$slug=str_replace('sabri-newsroom-','',$page);$cap=self::capability($slug);if(!current_user_can($cap)){wp_die(esc_html__('You are not allowed to access this Newsroom screen.','sabri-complete-home-news-feed'));}$label=self::$screens[$slug]??'Newsroom';$diagnostics=Phase5Diagnostics::report();echo '<div class="wrap sabri-phase5-admin"><h1>'.esc_html__($label,'sabri-complete-home-news-feed').'</h1>';if('phase5-overview'===$slug||'phase5-release'===$slug||'phase5-system-check'===$slug){self::diagnostic_cards($diagnostics);}else{echo '<p>'.esc_html__('This bounded operational screen uses authenticated REST services and server-side policy checks.','sabri-complete-home-news-feed').'</p><div id="sabri-phase5-admin-app" data-screen="'.esc_attr($slug).'" aria-live="polite"></div>';}echo '</div>';}
	private static function diagnostic_cards(array$d){echo '<div class="sabri-phase5-grid">';foreach(array('ready'=>$d['ready']?'Yes':'No','Schema tables missing'=>count($d['schema']['missing_tables']),'Schema indexes missing'=>count($d['schema']['missing_indexes']),'Migration status'=>$d['migration']['status']??'unknown','Audit chain'=>$d['audit']['success']?'Valid':'Invalid','Breaking active'=>$d['breaking_active'],'Pending submissions'=>$d['submissions_pending'],'Release blockers'=>count($d['blockers']))as$label=>$value){echo '<section class="sabri-phase5-card"><h2>'.esc_html($label).'</h2><p>'.esc_html((string)$value).'</p></section>';}echo '</div>';if($d['blockers'])echo '<div class="notice notice-error"><p>'.esc_html(implode(', ',$d['blockers'])).'</p></div>';}
	private static function capability($slug){if('phase5-submissions'===$slug)return'manage_news_submissions';if('phase5-breaking'===$slug)return'manage_breaking_news';if('phase5-sources'===$slug)return'manage_news_sources';if(in_array($slug,array('phase5-corrections','phase5-retracted'),true))return'manage_news_corrections';if('phase5-translation-review'===$slug)return'translate_editorial_news';if('phase5-medical-review'===$slug)return'medical_review_editorial_news';if('phase5-fact-check'===$slug)return'fact_check_editorial_news';if('phase5-audit'===$slug)return'view_news_audit';if(in_array($slug,array('phase5-release','phase5-system-check','phase5-settings'),true))return'manage_news_release';return'review_editorial_news';}
}
