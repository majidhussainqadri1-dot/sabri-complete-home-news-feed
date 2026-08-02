<?php
/** Isolated File 21 authorization and projection regression. */
declare(strict_types=1);

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['f21_current_user'] = 1;
	$GLOBALS['f21_caps'] = array();
	$GLOBALS['f21_users'] = array();
	$GLOBALS['f21_assertions'] = array();
	$GLOBALS['f21_meta_reads'] = 0;
	$GLOBALS['f21_approved'] = array();
	$GLOBALS['f21_directory'] = array();
	$GLOBALS['f21_filters'] = array();
	$GLOBALS['f21_projection_attack'] = false;

	class WP_User { public $ID; public $roles; public function __construct($id, $roles=array()){ $this->ID=$id; $this->roles=$roles; } }
	class SMC_Contracts { public static function assertions($id){ return $GLOBALS['f21_assertions'][(int)$id] ?? array(); } }
	class SPD_Verification_Adapter {
		public static function directory_eligible($id){ return ! empty($GLOBALS['f21_directory'][(int)$id]); }
		public static function approved_fields($id){ return $GLOBALS['f21_approved'][(int)$id] ?? array(); }
	}
	function absint($v){ return abs((int)$v); }
	function sanitize_key($v){ return strtolower(preg_replace('/[^a-z0-9_\-]/i','',(string)$v)); }
	function sanitize_text_field($v){ return trim(strip_tags((string)$v)); }
	function get_userdata($id){ return $GLOBALS['f21_users'][(int)$id] ?? false; }
	function get_current_user_id(){ return (int)$GLOBALS['f21_current_user']; }
	function current_user_can($cap){ return ! empty($GLOBALS['f21_caps'][(int)$GLOBALS['f21_current_user']][$cap]); }
	function user_can($id,$cap){ return ! empty($GLOBALS['f21_caps'][(int)$id][$cap]); }
	function get_user_meta($id,$key,$single=true){ unset($id,$key,$single); $GLOBALS['f21_meta_reads']++; return 'SHOULD_NOT_BE_READ'; }
	function get_option($key,$default=false){ unset($key); return $default; }
	function get_users($args){
		$out=array(); foreach($GLOBALS['f21_users'] as $id=>$user){ if(in_array('sabri_doctor_verified',$user->roles,true)) $out[]=$id; }
		return array_slice($out,0,(int)($args['number']??500));
	}
	function add_filter($hook,$callback,$priority=10,$accepted_args=1){ $GLOBALS['f21_filters'][]=array($hook,$callback,$priority,$accepted_args); return true; }
	function apply_filters($hook,$value,...$args){
		unset($args);
		if('sabri_hnf_public_author_projection'===$hook && $GLOBALS['f21_projection_attack']){
			$value['id']=999; $value['name']='<b>Approved Doctor</b>'; $value['phone']='+923001234567'; $value['secret']='private'; $value['is_founder']='yes';
		}
		return $value;
	}
	function __($text,$domain=''){ unset($domain); return $text; }
}

namespace Sabri\HomeNewsFeed {
	final class Settings { public static function get(){ return array('capabilities'=>array('verified_doctor_policy'=>'trusted')); } }
	final class SafeMode {
		public static $enabled=true; public static $disabled=false;
		public static function feature_enabled($feature){ unset($feature); return self::$enabled && !self::$disabled; }
		public static function public_features_disabled(){ return self::$disabled; }
		public static function emergency_disabled(){ return self::$disabled; }
	}
	final class Capabilities {
		public static function role_can_publish($role,$settings=null){ unset($role,$settings); return true; }
		public static function capabilities(){ return array('sabri_feed_create_posts','sabri_feed_publish_posts','sabri_feed_moderate_posts','sabri_feed_manage_settings'); }
	}
	final class NewsCapabilities { public static function capabilities(){ return array('read_editorial_news','publish_editorial_news'); } }
	final class Phase5Contracts { public static function capabilities(){ return array('manage_news_release'); } }
	final class ProfileLinkResolver {
		public static function display_name($id){ return 'User '.(int)$id; }
		public static function url($id){ return '/profile/'.(int)$id; }
	}

	require dirname(__DIR__).'/includes/class-canonical-identity-adapter.php';
	require dirname(__DIR__).'/includes/class-composer-permissions.php';

	$pass=0; $fail=array();
	$assert=function($condition,$name) use (&$pass,&$fail){ if($condition){$pass++;}else{$fail[]=$name;} };
	CanonicalIdentityAdapter::register();
	$assert(1===count($GLOBALS['f21_filters']) && 'user_has_cap'===$GLOBALS['f21_filters'][0][0],'File 21 registers one central File 00 capability guard.');

	$base=function($id,$class='member',$type='doctor'){
		return array(
			'contract_version'=>'1.1.2','user_id'=>$id,'account_class'=>$class,'membership_type'=>$type,
			'status'=>'approved','approved'=>true,'eligible'=>true,'suspended'=>false,
			'two_factor_ready'=>true,'session_two_factor'=>true,'guardian_verified'=>true,
			'professional_verified'=>true,'can_publish'=>true,'public_profile_allowed'=>true,
		);
	};

	$GLOBALS['f21_users'][1]=new \WP_User(1,array('founder','subscriber'));
	$GLOBALS['f21_assertions'][1]=$base(1,'founder','');
	$GLOBALS['f21_caps'][1]=array('manage_options'=>true,'sabri_feed_create_posts'=>true,'sabri_feed_publish_posts'=>true,'sabri_feed_moderate_posts'=>true);
	$assert(ComposerPermissions::user_can_create(1),'Active Founder can create with exact capability.');
	$assert(ComposerPermissions::user_can_publish(1),'Active Founder can publish with current 2FA.');
	$assert(ComposerPermissions::user_can_moderate(),'Active Founder can moderate with exact capability.');

	$GLOBALS['f21_assertions'][1]['status']='suspended';
	$GLOBALS['f21_assertions'][1]['suspended']=true;
	$GLOBALS['f21_assertions'][1]['approved']=false;
	$GLOBALS['f21_assertions'][1]['eligible']=false;
	$assert(!ComposerPermissions::user_can_create(1),'Suspended Founder is denied despite stale Founder role/capability.');
	$assert(!ComposerPermissions::user_can_publish(1),'Suspended Founder cannot publish.');
	$assert(!ComposerPermissions::user_can_moderate(),'Suspended Founder cannot moderate.');
	$guarded=CanonicalIdentityAdapter::guard_file21_capabilities(array('sabri_feed_create_posts'=>true,'publish_editorial_news'=>true),array('sabri_feed_create_posts'),array(),$GLOBALS['f21_users'][1]);
	$assert(empty($guarded['sabri_feed_create_posts'])&&empty($guarded['publish_editorial_news']),'Suspended identity loses every File 21-owned capability despite stale role data.');

	$GLOBALS['f21_users'][2]=new \WP_User(2,array('sabri_doctor_verified','subscriber'));
	$GLOBALS['f21_assertions'][2]=$base(2,'member','doctor');
	$GLOBALS['f21_caps'][2]=array('sabri_feed_create_posts'=>true,'sabri_feed_publish_posts'=>true,'sabri_feed_submit_for_review'=>true);
	$GLOBALS['f21_directory'][2]=true;
	$GLOBALS['f21_approved'][2]=array('display_name'=>'Approved Doctor','country'=>'Pakistan','specialty'=>'Homeopathy');
	$GLOBALS['f21_current_user']=2;
	$assert(ComposerPermissions::user_can_create(2),'Verified Doctor can create only with current File 00 assertion and capability.');
	$assert(ComposerPermissions::user_can_publish(2),'Verified Doctor can publish only when File 00 and File 21 both allow.');
	$projection=CanonicalIdentityAdapter::public_projection(2);
	$assert('Pakistan'===($projection['country']??''),'Public projection uses File 03 approved country.');
	$assert(0===$GLOBALS['f21_meta_reads'],'Public projection never reads raw user meta.');
	$GLOBALS['f21_projection_attack']=true;
	$projection=CanonicalIdentityAdapter::public_projection(2);
	$assert(2===($projection['id']??0),'A projection filter cannot replace the canonical subject ID.');
	$assert(!isset($projection['phone'])&&!isset($projection['secret']),'A projection filter cannot add non-contract or private fields.');
	$assert('Approved Doctor'===($projection['name']??''),'Filtered public text is sanitized after extension callbacks.');
	$assert(false===($projection['is_founder']??true),'Filtered identity booleans are canonicalized.');
	$GLOBALS['f21_projection_attack']=false;
	$guarded=CanonicalIdentityAdapter::guard_file21_capabilities(array('sabri_feed_create_posts'=>true),array('sabri_feed_create_posts'),array(),$GLOBALS['f21_users'][2]);
	$assert(!empty($guarded['sabri_feed_create_posts']),'Current active subject retains an owned capability.');

	$GLOBALS['f21_users'][3]=new \WP_User(3,array('sabri_doctor_verified'));
	$GLOBALS['f21_assertions'][3]=$base(3,'member','doctor');
	$GLOBALS['f21_assertions'][3]['contract_version']='1.1.1';
	$GLOBALS['f21_caps'][3]=array('sabri_feed_create_posts'=>true,'sabri_feed_publish_posts'=>true);
	$GLOBALS['f21_current_user']=3;
	$assert(!ComposerPermissions::user_can_create(3),'Obsolete File 00 contract fails closed.');

	$GLOBALS['f21_users'][4]=new \WP_User(4,array('sabri_doctor_pending','subscriber'));
	$GLOBALS['f21_assertions'][4]=$base(4,'member','doctor');
	$GLOBALS['f21_assertions'][4]['professional_verified']=false;
	$GLOBALS['f21_assertions'][4]['eligible']=false;
	$GLOBALS['f21_assertions'][4]['can_publish']=false;
	$GLOBALS['f21_caps'][4]=array('sabri_feed_create_posts'=>true,'sabri_feed_submit_for_review'=>true);
	$GLOBALS['f21_current_user']=4;
	$assert(!ComposerPermissions::user_can_create(4),'Unverified Doctor cannot bypass File 00 with pending role/capability.');
	$assert(!ComposerPermissions::user_can_submit_for_review(4),'Unverified Doctor cannot submit through stale role/capability.');

	$GLOBALS['f21_current_user']=2;
	$GLOBALS['f21_assertions'][2]['session_two_factor']=false;
	$assert(!ComposerPermissions::user_can_create(2),'Missing current-session 2FA fails closed.');
	$GLOBALS['f21_assertions'][2]['session_two_factor']=true;
	$assert(!ComposerPermissions::user_can_create(1),'A current actor cannot borrow another subject authorization.');

	$GLOBALS['f21_assertions'][2]['user_id']=999;
	$assert(!ComposerPermissions::user_can_create(2),'Subject-mismatched File 00 assertion fails closed.');

	if($fail){ fwrite(STDERR,"File21 authorization integrity: {$pass} PASS, ".count($fail)." FAIL\n- ".implode("\n- ",$fail)."\n"); exit(1); }
	echo "File21 authorization integrity: {$pass} PASS, 0 FAIL\n";
}
