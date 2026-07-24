<?php
/** Phase 4C public News, REST, routing, cache, and Home Feed integration tests. */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/phase4b-stubs.php';
require_once __DIR__ . '/phase4c-stubs.php';

use Sabri\HomeNewsFeed\FeedContext;
use Sabri\HomeNewsFeed\FeedQuery;
use Sabri\HomeNewsFeed\NewsCache;
use Sabri\HomeNewsFeed\NewsFeatureSettings;
use Sabri\HomeNewsFeed\NewsFeedIntegration;
use Sabri\HomeNewsFeed\NewsPolicy;
use Sabri\HomeNewsFeed\NewsPublicProjector;
use Sabri\HomeNewsFeed\NewsPublicRuntime;
use Sabri\HomeNewsFeed\NewsQueryService;
use Sabri\HomeNewsFeed\NewsRouting;
use Sabri\HomeNewsFeed\Phase4Contracts;
use Sabri\HomeNewsFeed\RestNews;
use Sabri\HomeNewsFeed\Settings;

$phase4c_failures = array();
function sabri_phase4c_assert( $condition, $message ) {
	global $phase4c_failures;
	if ( ! $condition ) {
		$phase4c_failures[] = $message;
	}
}

sabri_test_reset_state( true );
global $sabri_test_filter_overrides, $sabri_test_terms, $sabri_test_posts, $sabri_test_post_meta, $sabri_test_rewrite_rules, $sabri_test_rest_routes;

sabri_phase4c_assert( '1.0.0' === SABRI_HNF_VERSION, 'Phase 4C must not promote the plugin version.' );
sabri_phase4c_assert( '1.0.0' === SABRI_HNF_SCHEMA_VERSION, 'Phase 4C must not promote the schema version.' );
sabri_phase4c_assert( '4A' === Phase4Contracts::CHECKPOINT, 'Phase 4C must preserve the Phase 4A checkpoint contract for regressions.' );
sabri_phase4c_assert( 0 === NewsFeatureSettings::defaults()['editorial_news_enabled'], 'Editorial News must remain disabled by default.' );

$disabled = NewsQueryService::query();
sabri_phase4c_assert( empty( $disabled['success'] ) && 404 === $disabled['status'], 'Public News must fail closed while the gate is disabled.' );
sabri_phase4c_assert( ! RestNews::permission_callback(), 'Public REST permission must fail closed while disabled.' );

$settings = Settings::defaults();
$settings['general']['enabled'] = 1;
$settings['feed']['enabled'] = 1;
update_option( Settings::OPTION_NAME, $settings, false );
update_option( NewsFeatureSettings::OPTION_NAME, array_merge( NewsFeatureSettings::defaults(), array( 'editorial_news_enabled' => 1 ) ), false );

foreach ( Phase4Contracts::sections() as $slug => $label ) {
	$sabri_test_terms['sabri_news_section'][ $slug ] = $label;
}
foreach ( Phase4Contracts::article_types() as $slug => $label ) {
	$sabri_test_terms['sabri_news_type'][ $slug ] = $label;
}
$sabri_test_terms['sabri_news_topic']['research-methods'] = 'Research Methods';
$sabri_test_terms['sabri_news_country']['pakistan'] = 'Pakistan';
$sabri_test_terms['sabri_news_region']['south-asia'] = 'South Asia';

$published_id = sabri_test_add_post(
	array(
		'ID' => 401,
		'post_type' => Phase4Contracts::POST_TYPE,
		'post_status' => 'publish',
		'post_name' => 'verified-public-news',
		'post_title' => 'Verified Public News',
		'post_excerpt' => 'A public News summary.',
		'post_content' => '<p>Public article body with <strong>verified</strong> information.</p><script>alert(1)</script>',
		'post_author' => 1,
		'post_date' => '2026-07-20 10:00:00',
		'post_modified' => '2026-07-21 10:00:00',
	),
	array(
		Phase4Contracts::WORKFLOW_META_KEY => 'published',
		'_sabri_news_summary' => 'A public News summary.',
		'_sabri_news_language' => 'en-US',
		'_sabri_news_medical_review_required' => 1,
		'_sabri_news_reviewing_editor_id' => 2,
		'_sabri_news_private_note' => 'never public',
	),
	array(
		'sabri_news_section' => array( 'platform-news' ),
		'sabri_news_type' => array( 'standard-news' ),
		'sabri_news_topic' => array( 'research-methods' ),
		'sabri_news_country' => array( 'pakistan' ),
		'sabri_news_region' => array( 'south-asia' ),
	)
);
$corrected_id = sabri_test_add_post(
	array(
		'ID' => 402,
		'post_type' => Phase4Contracts::POST_TYPE,
		'post_status' => 'publish',
		'post_name' => 'corrected-public-news',
		'post_title' => 'Corrected Public News',
		'post_content' => '<p>Corrected public body.</p>',
		'post_date' => '2026-07-19 10:00:00',
	),
	array( Phase4Contracts::WORKFLOW_META_KEY => 'corrected', '_sabri_news_correction_status' => 'corrected' ),
	array( 'sabri_news_section' => array( 'public-health' ), 'sabri_news_type' => array( 'research-news' ) )
);
$pending_correction_id = sabri_test_add_post(
	array(
		'ID' => 403,
		'post_type' => Phase4Contracts::POST_TYPE,
		'post_status' => 'publish',
		'post_name' => 'pending-correction-public-version',
		'post_title' => 'Pending Correction Public Version',
		'post_content' => '<p>Existing approved public body.</p>',
		'post_date' => '2026-07-18 10:00:00',
	),
	array( Phase4Contracts::WORKFLOW_META_KEY => 'correction-pending', '_sabri_news_correction_status' => 'pending-private-review' ),
	array( 'sabri_news_section' => array( 'public-health' ), 'sabri_news_type' => array( 'standard-news' ) )
);
$draft_id = sabri_test_add_post(
	array( 'ID' => 404, 'post_type' => Phase4Contracts::POST_TYPE, 'post_status' => 'draft', 'post_name' => 'private-draft', 'post_title' => 'Private Draft', 'post_content' => 'Private body' ),
	array( Phase4Contracts::WORKFLOW_META_KEY => 'draft', '_sabri_news_summary' => 'Private summary', '_sabri_news_private_note' => 'secret' ),
	array( 'sabri_news_section' => array( 'platform-news' ), 'sabri_news_type' => array( 'standard-news' ) )
);
$retracted_id = sabri_test_add_post(
	array( 'ID' => 405, 'post_type' => Phase4Contracts::POST_TYPE, 'post_status' => 'private', 'post_name' => 'retracted-news', 'post_title' => 'Retracted News', 'post_content' => 'Hidden original body' ),
	array( Phase4Contracts::WORKFLOW_META_KEY => 'retracted', '_sabri_news_retraction_notice' => 'This report was retracted after a verification failure.', '_sabri_news_private_note' => 'private deliberation' ),
	array( 'sabri_news_section' => array( 'platform-news' ), 'sabri_news_type' => array( 'retraction-notice' ) )
);
$ordinary_id = sabri_test_add_post(
	array( 'ID' => 406, 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Ordinary Community Post', 'post_content' => 'Community body' ),
	array( '_sabri_feed_review_state' => 'approved', '_sabri_feed_visibility' => 'public', '_sabri_feed_type' => 'standard-post' ),
	array( 'sabri_feed_type' => array( 'standard-post' ) )
);

$sabri_test_filter_overrides['sabri_phase4c_test_posts'] = array_values( $sabri_test_posts );
$sabri_test_filter_overrides['sabri_feed_test_posts'] = array( $sabri_test_posts[ $ordinary_id ] );

sabri_phase4c_assert( NewsPolicy::public_reads_allowed(), 'The explicit public News gate should enable public reads.' );
$emergency_settings = Settings::get();
$emergency_settings['advanced']['emergency_disabled'] = 1;
update_option( Settings::OPTION_NAME, $emergency_settings, false );
sabri_phase4c_assert( ! NewsPolicy::public_reads_allowed(), 'Emergency Disable must close every public News surface.' );
$emergency_settings['advanced']['emergency_disabled'] = 0;
update_option( Settings::OPTION_NAME, $emergency_settings, false );
sabri_phase4c_assert( NewsPolicy::public_reads_allowed(), 'Public News must recover only after Emergency Disable is explicitly cleared.' );
sabri_phase4c_assert( NewsPolicy::is_public_post( $published_id, 'archive' ), 'Published News must be public.' );
sabri_phase4c_assert( NewsPolicy::is_public_post( $pending_correction_id, 'archive' ), 'The existing approved public version may remain visible during a pending correction.' );
sabri_phase4c_assert( ! NewsPolicy::is_public_post( $draft_id, 'single' ), 'Drafts must never become public.' );
sabri_phase4c_assert( ! NewsPolicy::is_public_post( $retracted_id, 'archive' ) && NewsPolicy::is_public_post( $retracted_id, 'single' ), 'Retracted News must not be promoted but must retain a public accountability notice.' );

$article = NewsPublicProjector::article( $published_id );
sabri_phase4c_assert( 'article' === $article['projection'], 'A published article projection must be produced.' );
sabri_phase4c_assert( 'http://example.test/news/verified-public-news/' === $article['canonical_url'], 'Canonical News route changed.' );
sabri_phase4c_assert( false === strpos( $article['body_html'], '<script' ), 'Public article HTML must remove scripts.' );
sabri_phase4c_assert( false === strpos( serialize( $article ), 'never public' ), 'Private article notes must never enter public projections.' );
sabri_phase4c_assert( ! isset( $article['public_author']['id'] ), 'Raw account IDs must not enter the public author projection.' );
sabri_phase4c_assert( false !== strpos( $article['disclaimer'], 'does not replace' ), 'Medical/public-information disclaimer must appear when required.' );

$pending_projection = NewsPublicProjector::article( $pending_correction_id );
sabri_phase4c_assert( 'none' === $pending_projection['correction_state'], 'A private pending-correction decision must not leak publicly.' );
$retraction = NewsPublicProjector::article( $retracted_id );
sabri_phase4c_assert( 'retraction' === $retraction['projection'] && '' === $retraction['body_html'], 'Retraction projection must hide the original article body.' );
sabri_phase4c_assert( false === strpos( serialize( $retraction ), 'Hidden original body' ), 'Retracted original text must not enter the public projection.' );

$invalid_date = NewsQueryService::normalize_args( array( 'date_from' => '2027-02-31' ) );
sabri_phase4c_assert( empty( $invalid_date['success'] ) && 'date_from' === $invalid_date['field'], 'Invalid calendar dates must fail closed.' );
$invalid_slug = NewsQueryService::normalize_args( array( 'section' => 'Platform-News' ) );
sabri_phase4c_assert( empty( $invalid_slug['success'] ) && 'section' === $invalid_slug['field'], 'Malformed taxonomy filters must fail closed.' );
$invalid_page = NewsQueryService::normalize_args( array( 'per_page' => '25' ) );
sabri_phase4c_assert( empty( $invalid_page['success'] ) && 'per_page' === $invalid_page['field'], 'Public page size must remain bounded.' );
$conflict_filter = NewsQueryService::normalize_args( array( 'corrected' => '1', 'retracted' => '1' ) );
sabri_phase4c_assert( empty( $conflict_filter['success'] ), 'Corrected and retracted filters must not widen each other.' );

$collection = NewsQueryService::query( array( 'per_page' => 12 ) );
sabri_phase4c_assert( ! empty( $collection['success'] ) && 3 === count( $collection['data']['items'] ), 'Public collection must include only public promotable cards.' );
foreach ( $collection['data']['items'] as $item ) {
	sabri_phase4c_assert( 'editorial_news' === $item['item_type'], 'Every public collection item must be a normalized News card.' );
	sabri_phase4c_assert( ! isset( $item['workflow_state'] ), 'Internal workflow state must not be serialized into public cards.' );
}
$corrected = NewsQueryService::query( array( 'corrected' => '1' ) );
sabri_phase4c_assert( 1 === count( $corrected['data']['items'] ) && $corrected_id === $corrected['data']['items'][0]['object_id'], 'Corrected filter must constrain the authoritative workflow state.' );
$retracted = NewsQueryService::query( array( 'retracted' => '1' ) );
sabri_phase4c_assert( 1 === count( $retracted['data']['items'] ) && 'retraction' === $retracted['data']['items'][0]['projection'], 'Retracted filter must expose only accountability projections.' );

$single = NewsQueryService::single( 'verified-public-news' );
sabri_phase4c_assert( ! empty( $single['success'] ) && $published_id === $single['data']['id'], 'Canonical slug lookup must resolve one public article.' );
$private_single = NewsQueryService::single( $draft_id );
sabri_phase4c_assert( empty( $private_single['success'] ) && 404 === $private_single['status'], 'Private object lookup must be non-enumerating.' );
$malformed_single = NewsQueryService::single( 'Bad Slug' );
sabri_phase4c_assert( empty( $malformed_single['success'] ) && 404 === $malformed_single['status'], 'Malformed public identifiers must fail as safe not-found.' );

$query_args = NewsQueryService::wp_query_args( NewsQueryService::normalize_args( array( 'corrected' => 1 ) )['data'] );
sabri_phase4c_assert( array( 'corrected' ) === $query_args['meta_query'][0]['value'], 'Corrected WP query must use the exact domain state.' );
sabri_phase4c_assert( Phase4Contracts::POST_TYPE === $query_args['post_type'] && array( 'publish' ) === $query_args['post_status'], 'Public collection query must be isolated to published Editorial News.' );

$context = NewsFeedIntegration::pagination_context( 'latest', 1, 10 );
$integrated = NewsFeedIntegration::integrate_result(
	array( 'posts' => array( $sabri_test_posts[ $ordinary_id ] ), 'total' => 1, 'max_pages' => 1, 'has_more' => false ),
	$context
);
sabri_phase4c_assert( 2 === count( $integrated['posts'] ), 'Home Feed integration must add one bounded News card without replacing ordinary content.' );
sabri_phase4c_assert( 'post:' . $ordinary_id === NewsFeedIntegration::global_key( $sabri_test_posts[ $ordinary_id ] ), 'Ordinary post identity must remain distinct.' );
sabri_phase4c_assert( 'news:' . $published_id === NewsFeedIntegration::global_key( $integrated['posts'][1] ), 'News cards must use the frozen global key.' );
$duplicate = NewsFeedIntegration::integrate_result(
	array( 'posts' => array( $integrated['posts'][1] ), 'total' => 0, 'max_pages' => 0, 'has_more' => false ),
	$context
);
sabri_phase4c_assert( 1 === count( $duplicate['posts'] ), 'News items must deduplicate by global key.' );

$card_html = NewsPublicRuntime::render_card( $collection['data']['items'][0] );
sabri_phase4c_assert( false !== strpos( $card_html, 'data-sabri-global-key="news:' ), 'Dedicated News card markup must carry the stable identity.' );
sabri_phase4c_assert( false !== strpos( $card_html, '/news/' ), 'Dedicated News cards must link to canonical News routes.' );

RestNews::register_routes();
sabri_phase4c_assert( isset( $sabri_test_rest_routes[ Phase4Contracts::REST_NAMESPACE . '/news' ] ), 'Public News collection REST route is missing.' );
sabri_phase4c_assert( isset( $sabri_test_rest_routes[ Phase4Contracts::REST_NAMESPACE . '/news/(?P<id>[1-9][0-9]*)' ] ), 'Public News single REST route is missing.' );
sabri_phase4c_assert( 'GET' === $sabri_test_rest_routes[ Phase4Contracts::REST_NAMESPACE . '/news' ]['methods'], 'Phase 4C REST collection must remain read-only.' );

$sabri_test_rewrite_rules = array();
NewsRouting::rewrite_rules();
sabri_phase4c_assert( 7 === count( $sabri_test_rewrite_rules ), 'Every exact Phase 4C public route must be registered once.' );
$vars = NewsRouting::query_vars( array() );
foreach ( array( NewsRouting::Q_ARCHIVE, NewsRouting::Q_SLUG, NewsRouting::Q_TAXONOMY, NewsRouting::Q_TERM ) as $var ) {
	sabri_phase4c_assert( in_array( $var, $vars, true ), 'Required public route query variable is missing: ' . $var );
}

$key_before = NewsCache::key( 'collection', array( 'page' => 1 ) );
NewsCache::invalidate();
$key_after = NewsCache::key( 'collection', array( 'page' => 1 ) );
sabri_phase4c_assert( $key_before !== $key_after, 'News cache generation must change after invalidation.' );

update_option( NewsFeatureSettings::OPTION_NAME, NewsFeatureSettings::defaults(), false );
sabri_phase4c_assert( ! NewsPolicy::public_reads_allowed(), 'Disabling the gate must close public reads without deleting data.' );
sabri_phase4c_assert( null !== get_post( $published_id ), 'Feature-gate closure must preserve Editorial News data.' );

if ( $phase4c_failures ) {
	fwrite( STDERR, "Phase 4C public News failures:\n- " . implode( "\n- ", $phase4c_failures ) . "\n" );
	exit( 1 );
}

echo "Phase 4C public News tests passed.\n";
