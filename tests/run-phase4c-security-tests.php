<?php
/** Phase 4C security-negative, privacy, cache-isolation, and non-enumeration tests. */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/phase4b-stubs.php';
require_once __DIR__ . '/phase4c-stubs.php';

use Sabri\HomeNewsFeed\NewsCache;
use Sabri\HomeNewsFeed\NewsFeatureSettings;
use Sabri\HomeNewsFeed\NewsPolicy;
use Sabri\HomeNewsFeed\NewsPublicProjector;
use Sabri\HomeNewsFeed\NewsPublicSnapshot;
use Sabri\HomeNewsFeed\NewsQueryService;
use Sabri\HomeNewsFeed\Phase4Contracts;
use Sabri\HomeNewsFeed\RestNews;
use Sabri\HomeNewsFeed\Settings;

$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) { if ( ! $condition ) { $failures[] = $message; } };

sabri_test_reset_state( true );
global $sabri_test_filter_overrides, $sabri_test_terms, $sabri_test_posts;
$settings = Settings::defaults();
$settings['general']['enabled'] = 1;
$settings['feed']['enabled'] = 1;
update_option( Settings::OPTION_NAME, $settings, false );
update_option( NewsFeatureSettings::OPTION_NAME, array_merge( NewsFeatureSettings::defaults(), array( 'editorial_news_enabled' => 1 ) ), false );
foreach ( Phase4Contracts::sections() as $slug => $label ) { $sabri_test_terms['sabri_news_section'][ $slug ] = $label; }
foreach ( Phase4Contracts::article_types() as $slug => $label ) { $sabri_test_terms['sabri_news_type'][ $slug ] = $label; }
$sabri_test_terms['sabri_news_topic']['safe-topic'] = 'Safe Topic';
$sabri_test_terms['sabri_news_country']['safe-country'] = 'Safe Country';
$sabri_test_terms['sabri_news_region']['safe-region'] = 'Safe Region';

$public_id = sabri_test_add_post(
	array(
		'ID' => 701,
		'post_type' => Phase4Contracts::POST_TYPE,
		'post_status' => 'publish',
		'post_name' => 'security-public-news',
		'post_title' => 'Security Public News',
		'post_excerpt' => 'Safe summary',
		'post_content' => '<p>Safe body</p><img src="javascript:alert(1)" onerror="alert(2)"><a href="javascript:alert(3)">unsafe</a>',
		'post_author' => 77,
		'post_date' => '2026-07-20 10:00:00',
	),
	array(
		Phase4Contracts::WORKFLOW_META_KEY => 'published',
		'_sabri_news_summary' => 'Safe summary',
		'_sabri_news_private_note' => 'TOP SECRET NOTE',
		'_sabri_news_source_confidence' => 'CONFIDENTIAL SOURCE',
		'_sabri_news_public_institution_name' => 'Safe Institution',
		'_sabri_news_public_institution_url' => 'javascript:alert(9)',
	),
	array(
		'sabri_news_section' => array( 'platform-news' ),
		'sabri_news_type' => array( 'standard-news' ),
		'sabri_news_topic' => array( 'safe-topic' ),
		'sabri_news_country' => array( 'safe-country' ),
		'sabri_news_region' => array( 'safe-region' ),
	)
);
$private_ids = array();
foreach ( array( 'draft','needs-sources','editorial-review','fact-check','medical-review','ready-for-publication','scheduled','archived' ) as $index => $state ) {
	$status = 'scheduled' === $state ? 'future' : ( 'archived' === $state ? 'private' : 'draft' );
	$private_ids[ $state ] = sabri_test_add_post(
		array( 'ID'=>720+$index, 'post_type'=>Phase4Contracts::POST_TYPE, 'post_status'=>$status, 'post_name'=>'secret-'.$state, 'post_title'=>'Secret '.$state, 'post_content'=>'PRIVATE BODY '.$state ),
		array( Phase4Contracts::WORKFLOW_META_KEY=>$state, '_sabri_news_summary'=>'PRIVATE SUMMARY '.$state, '_sabri_news_private_note'=>'PRIVATE NOTE '.$state ),
		array( 'sabri_news_section'=>array('platform-news'), 'sabri_news_type'=>array('standard-news') )
	);
}
$retracted_id = sabri_test_add_post(
	array( 'ID'=>750, 'post_type'=>Phase4Contracts::POST_TYPE, 'post_status'=>'private', 'post_name'=>'security-retracted', 'post_title'=>'Security Retracted', 'post_content'=>'HIDDEN RETRACTED ORIGINAL' ),
	array( Phase4Contracts::WORKFLOW_META_KEY=>'retracted', '_sabri_news_retraction_notice'=>'Public accountability notice.', '_sabri_news_private_note'=>'PRIVATE RETRACTION DELIBERATION' ),
	array( 'sabri_news_section'=>array('platform-news'), 'sabri_news_type'=>array('retraction-notice') )
);
$sabri_test_filter_overrides['sabri_phase4c_test_posts'] = array_values( $sabri_test_posts );
NewsCache::invalidate();

$article = NewsPublicProjector::article( $public_id );
$serialized = serialize( $article );
foreach ( array( 'TOP SECRET NOTE', 'CONFIDENTIAL SOURCE', 'PRIVATE', 'javascript:', 'onerror=' ) as $forbidden ) {
	$assert( false === stripos( $serialized, $forbidden ), 'Forbidden public projection content leaked: ' . $forbidden );
}
$assert( empty( $article['public_author']['id'] ) && empty( $article['public_author']['email'] ), 'Account identifiers leaked through author projection.' );
$assert( empty( $article['public_author']['url'] ), 'Unsafe institution URL was not rejected.' );

foreach ( $private_ids as $state => $id ) {
	$by_id = NewsQueryService::single( $id );
	$by_slug = NewsQueryService::single( 'secret-' . $state );
	$assert( empty( $by_id['success'] ) && 404 === $by_id['status'] && 'public_news_not_found' === $by_id['code'], 'Private ID enumeration leak: ' . $state );
	$assert( empty( $by_slug['success'] ) && 404 === $by_slug['status'] && 'public_news_not_found' === $by_slug['code'], 'Private slug enumeration leak: ' . $state );
}
foreach ( array( 0, -1, '01', '1.5', '1e2', ' 701 ', '701/../../', array( 701 ), null ) as $identifier ) {
	$result = NewsQueryService::single( $identifier );
	$assert( empty( $result['success'] ) && 404 === $result['status'], 'Malformed ID/slug did not fail as non-enumerating 404.' );
}

$retraction = NewsPublicProjector::article( $retracted_id );
$assert( 'retraction' === $retraction['projection'] && '' === $retraction['body_html'], 'Retraction projection did not suppress body.' );
$assert( false === strpos( serialize( $retraction ), 'HIDDEN RETRACTED ORIGINAL' ) && false === strpos( serialize( $retraction ), 'PRIVATE RETRACTION DELIBERATION' ), 'Retraction leaked hidden content.' );

$assert( NewsPublicSnapshot::capture( $public_id, true ), 'Could not create approved public snapshot for pending-correction isolation.' );
update_post_meta( $public_id, Phase4Contracts::WORKFLOW_META_KEY, 'correction-pending' );
if ( function_exists( 'wp_update_post' ) ) {
	wp_update_post( array( 'ID'=>$public_id, 'post_title'=>'PRIVATE PENDING TITLE', 'post_content'=>'PRIVATE PENDING BODY', 'post_excerpt'=>'PRIVATE PENDING SUMMARY' ) );
}
$pending = NewsPublicProjector::article( $public_id );
$assert( false === strpos( serialize( $pending ), 'PRIVATE PENDING' ), 'Pending correction leaked through the public snapshot boundary.' );

$invalid_vectors = array(
	array( 'orderby'=>'RAND()' ),
	array( 'page'=>'-2' ),
	array( 'per_page'=>'999999' ),
	array( 'section'=>'platform-news OR 1=1' ),
	array( 'topic'=>'unknown-topic' ),
	array( 'country'=>'../secret' ),
	array( 'date_from'=>'2026-02-30' ),
	array( 'date_from'=>'2020-01-01','date_to'=>'2040-01-01' ),
	array( 'research'=>'yes' ),
	array( 'corrected'=>'1','retracted'=>'1' ),
);
foreach ( $invalid_vectors as $vector ) {
	$result = NewsQueryService::normalize_args( $vector );
	$assert( empty( $result['success'] ) && in_array( $result['code'], array( 'public_news_filter_invalid','public_news_page_invalid','public_news_taxonomy_invalid' ), true ), 'Unsafe filter vector widened the public query.' );
}

$cache_a = NewsCache::key( 'collection', array( 'page'=>1, 'keyword'=>'safe' ) );
$cache_b = NewsCache::key( 'collection', array( 'keyword'=>'safe', 'page'=>1 ) );
$cache_user = NewsCache::key( 'collection', array( 'page'=>1, 'current_user_id'=>999, 'nonce'=>'secret-token' ) );
$assert( $cache_a === $cache_b, 'Cache dimensions are not deterministic.' );
$assert( false === strpos( $cache_user, '999' ) && false === strpos( $cache_user, 'secret-token' ), 'Cache key exposed user or nonce data.' );
$old_key = NewsCache::key( 'single', array( 'id'=>$public_id ) );
$emergency = Settings::get(); $emergency['advanced']['emergency_disabled'] = 1; update_option( Settings::OPTION_NAME, $emergency, false );
$assert( ! NewsPolicy::public_reads_allowed(), 'Emergency Disable did not close public reads.' );
$new_key = NewsCache::key( 'single', array( 'id'=>$public_id ) );
$assert( $old_key !== $new_key, 'Emergency state was not included in cache isolation.' );
$emergency['advanced']['emergency_disabled'] = 0; update_option( Settings::OPTION_NAME, $emergency, false );

$assert( ! RestNews::validate_positive_int( '-1' ) && ! RestNews::validate_positive_int( '1.5' ) && RestNews::validate_positive_int( '1' ), 'REST positive integer validation is weak.' );
$assert( ! RestNews::validate_slug( 'Bad Slug' ) && ! RestNews::validate_slug( '../x' ) && RestNews::validate_slug( 'safe-topic' ), 'REST slug validation is weak.' );
$assert( ! RestNews::validate_boolean( 'yes' ) && RestNews::validate_boolean( '0' ) && RestNews::validate_boolean( false ), 'REST boolean validation is weak.' );
$assert( ! RestNews::validate_date( '2026-02-30' ) && RestNews::validate_date( '2026-02-28' ), 'REST calendar-date validation is weak.' );
$unknown = RestNews::collection( array( 'unknown_private_filter'=>'x' ) );
$payload = is_object( $unknown ) && method_exists( $unknown, 'get_data' ) ? $unknown->get_data() : ( isset( $unknown['payload'] ) ? $unknown['payload'] : array() );
$unknown_status = is_object( $unknown ) && method_exists( $unknown, 'get_status' ) ? $unknown->get_status() : ( isset( $unknown['status'] ) ? $unknown['status'] : 0 );
$assert( 400 === $unknown_status && 'public_news_filter_invalid' === ( isset( $payload['code'] ) ? $payload['code'] : '' ), 'REST unknown parameters were not rejected.' );

update_option( NewsFeatureSettings::OPTION_NAME, NewsFeatureSettings::defaults(), false );
$assert( ! NewsPolicy::public_reads_allowed(), 'Gate-off did not close the public surface.' );
$assert( null !== get_post( $public_id ) && null !== get_post( $retracted_id ), 'Safety controls deleted Editorial News data.' );

if ( $failures ) {
	fwrite( STDERR, "Phase 4C security failures:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}
echo "Phase 4C security tests passed.\n";
