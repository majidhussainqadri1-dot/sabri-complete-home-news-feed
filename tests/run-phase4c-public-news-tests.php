<?php
/** Phase 4C public News, privacy, routing, cache, correction, REST, and Home Feed tests. */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/phase4b-stubs.php';
require_once __DIR__ . '/phase4c-stubs.php';

use Sabri\HomeNewsFeed\Assets;
use Sabri\HomeNewsFeed\InteractionPermissions;
use Sabri\HomeNewsFeed\NewsCache;
use Sabri\HomeNewsFeed\NewsFeatureSettings;
use Sabri\HomeNewsFeed\NewsFeedIntegration;
use Sabri\HomeNewsFeed\NewsPolicy;
use Sabri\HomeNewsFeed\NewsPublicProjector;
use Sabri\HomeNewsFeed\NewsPublicRuntime;
use Sabri\HomeNewsFeed\NewsPublicSnapshot;
use Sabri\HomeNewsFeed\NewsQueryService;
use Sabri\HomeNewsFeed\NewsRouting;
use Sabri\HomeNewsFeed\Phase4Contracts;
use Sabri\HomeNewsFeed\RestNews;
use Sabri\HomeNewsFeed\Settings;

$failures = array();
function sabri_phase4c_assert( $condition, $message ) {
	global $failures;
	if ( ! $condition ) { $failures[] = $message; }
}

sabri_test_reset_state( true );
global $sabri_test_filter_overrides, $sabri_test_terms, $sabri_test_posts, $sabri_test_post_meta, $sabri_test_rewrite_rules, $sabri_test_rest_routes, $sabri_test_enqueued_styles, $sabri_test_enqueued_scripts;

sabri_phase4c_assert( '1.0.0' === SABRI_HNF_VERSION, 'Phase 4C must not promote the plugin version.' );
sabri_phase4c_assert( '1.0.0' === SABRI_HNF_SCHEMA_VERSION, 'Phase 4C must not promote the schema version.' );
sabri_phase4c_assert( '4A' === Phase4Contracts::CHECKPOINT, 'Phase 4C must preserve the Phase 4A checkpoint.' );
sabri_phase4c_assert( 0 === NewsFeatureSettings::defaults()['editorial_news_enabled'], 'Editorial News must remain disabled by default.' );
$disabled = NewsQueryService::query();
sabri_phase4c_assert( empty( $disabled['success'] ) && 'editorial_news_disabled' === $disabled['code'], 'Gate-off collection must use the frozen disabled code.' );

$settings = Settings::defaults();
$settings['general']['enabled'] = 1;
$settings['feed']['enabled'] = 1;
update_option( Settings::OPTION_NAME, $settings, false );
update_option( NewsFeatureSettings::OPTION_NAME, array_merge( NewsFeatureSettings::defaults(), array( 'editorial_news_enabled' => 1 ) ), false );
foreach ( Phase4Contracts::sections() as $slug => $label ) { $sabri_test_terms['sabri_news_section'][ $slug ] = $label; }
foreach ( Phase4Contracts::article_types() as $slug => $label ) { $sabri_test_terms['sabri_news_type'][ $slug ] = $label; }
$sabri_test_terms['sabri_news_topic']['research-methods'] = 'Research Methods';
$sabri_test_terms['sabri_news_country']['pakistan'] = 'Pakistan';
$sabri_test_terms['sabri_news_region']['south-asia'] = 'South Asia';

$published_id = sabri_test_add_post(
	array( 'ID'=>401,'post_type'=>Phase4Contracts::POST_TYPE,'post_status'=>'publish','post_name'=>'verified-public-news','post_title'=>'Verified Public News','post_excerpt'=>'A public News summary.','post_content'=>'<p>Approved public body.</p><script>alert(1)</script>','post_author'=>1,'post_date'=>'2026-07-20 10:00:00','post_modified'=>'2026-07-21 10:00:00' ),
	array( Phase4Contracts::WORKFLOW_META_KEY=>'published','_sabri_news_summary'=>'A public News summary.','_sabri_news_language'=>'en-US','_sabri_news_medical_review_required'=>1,'_sabri_news_public_institution_name'=>'Sabri Research Center','_sabri_news_public_institution_slug'=>'sabri-research-center','_sabri_news_priority'=>100,'_sabri_news_editor_pick'=>1,'_sabri_news_private_note'=>'never public' ),
	array( 'sabri_news_section'=>array('platform-news'),'sabri_news_type'=>array('standard-news'),'sabri_news_topic'=>array('research-methods'),'sabri_news_country'=>array('pakistan'),'sabri_news_region'=>array('south-asia') )
);
$corrected_id = sabri_test_add_post(
	array( 'ID'=>402,'post_type'=>Phase4Contracts::POST_TYPE,'post_status'=>'publish','post_name'=>'corrected-public-news','post_title'=>'Corrected Public News','post_content'=>'<p>Corrected public body.</p>','post_date'=>'2026-07-19 10:00:00' ),
	array( Phase4Contracts::WORKFLOW_META_KEY=>'corrected','_sabri_news_correction_status'=>'corrected','_sabri_news_correction_notice'=>'A factual date was corrected.' ),
	array( 'sabri_news_section'=>array('public-health'),'sabri_news_type'=>array('research-news') )
);
$ordinary_id = sabri_test_add_post(
	array( 'ID'=>406,'post_type'=>'post','post_status'=>'publish','post_title'=>'Ordinary Community Post','post_content'=>'Community body' ),
	array( '_sabri_feed_review_state'=>'approved','_sabri_feed_visibility'=>'public','_sabri_feed_type'=>'standard-post' ),
	array( 'sabri_feed_type'=>array('standard-post') )
);
$private_states = array( 'draft','needs-sources','editorial-review','fact-check','medical-review','ready-for-publication','scheduled','archived' );
foreach ( $private_states as $index => $state ) {
	$status = 'archived' === $state ? 'private' : ( 'scheduled' === $state ? 'future' : 'draft' );
	sabri_test_add_post( array( 'ID'=>420+$index,'post_type'=>Phase4Contracts::POST_TYPE,'post_status'=>$status,'post_name'=>'private-'.$state,'post_title'=>'Private '.$state,'post_content'=>'Private body '.$state ), array( Phase4Contracts::WORKFLOW_META_KEY=>$state,'_sabri_news_private_note'=>'secret' ), array( 'sabri_news_section'=>array('platform-news'),'sabri_news_type'=>array('standard-news') ) );
}
$retracted_id = sabri_test_add_post(
	array( 'ID'=>405,'post_type'=>Phase4Contracts::POST_TYPE,'post_status'=>'private','post_name'=>'retracted-news','post_title'=>'Retracted News','post_content'=>'Hidden original body' ),
	array( Phase4Contracts::WORKFLOW_META_KEY=>'retracted','_sabri_news_retraction_notice'=>'Retracted after verification failed.','_sabri_news_private_note'=>'private deliberation' ),
	array( 'sabri_news_section'=>array('platform-news'),'sabri_news_type'=>array('retraction-notice') )
);
$sabri_test_filter_overrides['sabri_phase4c_test_posts'] = array_values( $sabri_test_posts );
$sabri_test_filter_overrides['sabri_feed_test_posts'] = array( $sabri_test_posts[ $ordinary_id ] );
NewsCache::invalidate();

sabri_phase4c_assert( NewsPolicy::public_reads_allowed(), 'Explicit gate should enable public reads.' );
$emergency = Settings::get(); $emergency['advanced']['emergency_disabled'] = 1; update_option( Settings::OPTION_NAME, $emergency, false );
sabri_phase4c_assert( ! NewsPolicy::public_reads_allowed(), 'Emergency Disable must close public News.' );
$emergency['advanced']['emergency_disabled'] = 0; update_option( Settings::OPTION_NAME, $emergency, false );
sabri_phase4c_assert( NewsPolicy::public_reads_allowed(), 'Public News should recover only after explicit emergency clearance.' );

foreach ( $private_states as $state ) {
	$result = NewsQueryService::single( 'private-' . $state );
	sabri_phase4c_assert( empty( $result['success'] ) && 'public_news_not_found' === $result['code'], 'Private state became enumerable: ' . $state );
}
$article = NewsPublicProjector::article( $published_id );
sabri_phase4c_assert( 'article' === $article['projection'], 'Published article projection missing.' );
sabri_phase4c_assert( false === strpos( $article['body_html'], '<script' ), 'Public body must remove scripts.' );
sabri_phase4c_assert( false === strpos( serialize( $article ), 'never public' ), 'Private notes leaked.' );
sabri_phase4c_assert( 'institution' === $article['public_author']['type'] && 'Sabri Research Center' === $article['public_author']['name'], 'Unapproved account identity must fall back to approved institution.' );
sabri_phase4c_assert( ! isset( $article['public_author']['id'] ), 'Raw author ID leaked.' );

sabri_phase4c_assert( NewsPublicSnapshot::capture( $published_id, true ), 'Approved public snapshot could not be captured.' );
update_post_meta( $published_id, Phase4Contracts::WORKFLOW_META_KEY, 'correction-pending' );
if ( function_exists( 'wp_update_post' ) ) { wp_update_post( array( 'ID'=>$published_id,'post_title'=>'Private Pending Title','post_content'=>'Private pending body must not appear','post_excerpt'=>'Private pending summary' ) ); }
if ( function_exists( 'wp_set_object_terms' ) ) { wp_set_object_terms( $published_id, array( 'public-health' ), 'sabri_news_section', false ); }
$pending_public = NewsPublicProjector::article( $published_id );
sabri_phase4c_assert( 'Verified Public News' === $pending_public['headline'], 'Pending title overwrote last approved public snapshot.' );
sabri_phase4c_assert( false === strpos( $pending_public['body_html'], 'Private pending body' ), 'Pending body leaked into public output.' );
sabri_phase4c_assert( 'platform-news' === $pending_public['section'][0]['slug'], 'Pending taxonomy leaked into public output.' );
$pending_data = array( 'title'=>'Approved Corrected Title','content'=>'<p>Approved corrected body.</p>','subtitle'=>'','summary'=>'Approved corrected summary','language'=>'en-US','priority'=>50,'section'=>'public-health','article_type'=>'research-news','topics'=>array('research-methods'),'countries'=>array('pakistan'),'regions'=>array('south-asia'),'reviewing_editor_id'=>0,'medical_reviewer_id'=>0,'fact_check_required'=>0,'medical_review_required'=>0,'featured_image_id'=>0 );
sabri_phase4c_assert( ! empty( NewsPublicSnapshot::store_pending( $published_id, $pending_data, false )['success'] ), 'Private pending correction could not be stored.' );
sabri_phase4c_assert( ! empty( NewsPublicSnapshot::promote_pending( $published_id )['success'] ), 'Approved correction could not be promoted.' );
update_post_meta( $published_id, Phase4Contracts::WORKFLOW_META_KEY, 'corrected' );
NewsPublicSnapshot::capture( $published_id, true );
$approved_correction = NewsPublicProjector::article( $published_id );
sabri_phase4c_assert( 'Approved Corrected Title' === $approved_correction['headline'], 'Approved correction was not promoted.' );

$retraction = NewsPublicProjector::article( $retracted_id );
sabri_phase4c_assert( 'retraction' === $retraction['projection'] && '' === $retraction['body_html'], 'Retraction must hide original body.' );
sabri_phase4c_assert( false === strpos( serialize( $retraction ), 'Hidden original body' ), 'Retracted body leaked.' );

$invalid_date = NewsQueryService::normalize_args( array( 'date_from'=>'2027-02-31' ) );
sabri_phase4c_assert( empty($invalid_date['success']) && 'public_news_filter_invalid'===$invalid_date['code'], 'Invalid date did not fail closed.' );
$invalid_term = NewsQueryService::normalize_args( array( 'topic'=>'unknown-topic' ) );
sabri_phase4c_assert( empty($invalid_term['success']) && 'public_news_taxonomy_invalid'===$invalid_term['code'], 'Unknown controlled term was accepted.' );
$invalid_page = NewsQueryService::normalize_args( array( 'per_page'=>'25' ) );
sabri_phase4c_assert( empty($invalid_page['success']) && 'public_news_page_invalid'===$invalid_page['code'], 'Oversized page did not use page error code.' );
$unsupported = NewsQueryService::normalize_args( array( 'orderby'=>'RAND()' ) );
sabri_phase4c_assert( empty($unsupported['success']) && 'public_news_filter_invalid'===$unsupported['code'], 'Unsupported query argument was accepted.' );
$conflict = NewsQueryService::normalize_args( array( 'corrected'=>'1','retracted'=>'1' ) );
sabri_phase4c_assert( empty($conflict['success']), 'Corrected and retracted filters widened each other.' );

NewsCache::invalidate();
$collection = NewsQueryService::query( array( 'per_page'=>12 ) );
sabri_phase4c_assert( !empty($collection['success']), 'Public collection failed.' );
foreach ( $collection['data']['items'] as $item ) {
	sabri_phase4c_assert( 'editorial_news' === $item['item_type'], 'Collection item is not normalized News.' );
	sabri_phase4c_assert( !isset($item['workflow_state']), 'Internal state leaked into card.' );
}
$landing = NewsQueryService::landing();
$component_keys = array(); foreach ( !empty($landing['data']['components'])?$landing['data']['components']:array() as $component ) { $component_keys[] = $component['key']; }
foreach ( array( 'featured','latest','research','classical-homeopathy','public-health','homeopathy-education','platform-news','founder-updates','worldwide-health-developments','recently-updated' ) as $required_component ) {
	sabri_phase4c_assert( in_array( $required_component, $component_keys, true ), 'Landing component missing: ' . $required_component );
}

$context = NewsFeedIntegration::pagination_context( 'latest', 1, 10 );
$integrated = NewsFeedIntegration::integrate_result( array('posts'=>array($sabri_test_posts[$ordinary_id]),'total'=>1,'max_pages'=>1,'has_more'=>false), $context );
sabri_phase4c_assert( NewsFeedIntegration::result_has_news($integrated), 'Feed result did not identify its News card.' );
sabri_phase4c_assert( 'post:'.$ordinary_id === NewsFeedIntegration::global_key($sabri_test_posts[$ordinary_id]), 'Ordinary identity changed.' );
$news_keys=array_filter(array_map(array(NewsFeedIntegration::class,'global_key'),$integrated['posts']),static function($key){return 0===strpos($key,'news:');});
sabri_phase4c_assert( 1===count($news_keys), 'Feed must contain at most one unique News card per page.' );

$sabri_test_enqueued_styles=array();$sabri_test_enqueued_scripts=array();
Assets::enqueue_feed(false);
sabri_phase4c_assert( !in_array('sabri-hnf-news',$sabri_test_enqueued_styles,true), 'News CSS loaded on a feed with no News card.' );
NewsPublicRuntime::render_card( $collection['data']['items'][0] );
sabri_phase4c_assert( in_array('sabri-hnf-news',$sabri_test_enqueued_styles,true) && in_array('sabri-hnf-news',$sabri_test_enqueued_scripts,true), 'News assets did not load when a News card rendered.' );

RestNews::register_routes();
sabri_phase4c_assert( isset($sabri_test_rest_routes[Phase4Contracts::REST_NAMESPACE.'/news']), 'REST collection route missing.' );
sabri_phase4c_assert( 'GET'===$sabri_test_rest_routes[Phase4Contracts::REST_NAMESPACE.'/news']['methods'], 'REST collection is not GET-only.' );
sabri_phase4c_assert( !RestNews::validate_boolean('yes') && RestNews::validate_boolean('0'), 'REST boolean schema is not strict.' );
$unknown_rest = RestNews::collection( array( 'unsupported'=>'x' ) );
$unknown_payload = is_object( $unknown_rest ) && method_exists( $unknown_rest, 'get_data' ) ? $unknown_rest->get_data() : ( isset( $unknown_rest['payload'] ) ? $unknown_rest['payload'] : array() );
sabri_phase4c_assert( isset($unknown_payload['code']) && 'public_news_filter_invalid'===$unknown_payload['code'], 'REST unknown filter did not fail closed.' );

$sabri_test_rewrite_rules=array(); NewsRouting::rewrite_rules();
sabri_phase4c_assert( 7===count($sabri_test_rewrite_rules), 'Every exact public route must be registered once.' );
$key_before=NewsCache::key('collection',array('page'=>1)); NewsCache::invalidate(); $key_after=NewsCache::key('collection',array('page'=>1));
sabri_phase4c_assert( $key_before!==$key_after, 'Cache generation did not change.' );

if ( function_exists( 'get_current_user_id' ) && get_current_user_id() > 0 ) {
	sabri_phase4c_assert( InteractionPermissions::can_view_post($published_id), 'Approved News is not visible through the Phase 3 interaction boundary.' );
}

update_option( NewsFeatureSettings::OPTION_NAME, NewsFeatureSettings::defaults(), false );
sabri_phase4c_assert( !NewsPolicy::public_reads_allowed(), 'Gate closure failed.' );
sabri_phase4c_assert( null!==get_post($published_id), 'Gate closure deleted News data.' );

if($failures){fwrite(STDERR,"Phase 4C public News failures:\n- ".implode("\n- ",$failures)."\n");exit(1);}echo "Phase 4C public News tests passed.\n";
