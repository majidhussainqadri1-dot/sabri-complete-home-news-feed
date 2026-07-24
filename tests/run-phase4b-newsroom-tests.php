<?php
/**
 * Phase 4B newsroom, composer, queue, workflow, scheduling, diagnostics, and audit tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/phase4b-stubs.php';

use Sabri\HomeNewsFeed\NewsCapabilities;
use Sabri\HomeNewsFeed\NewsComposerValidator;
use Sabri\HomeNewsFeed\NewsPolicy;
use Sabri\HomeNewsFeed\NewsQueueService;
use Sabri\HomeNewsFeed\NewsSchedulingService;
use Sabri\HomeNewsFeed\NewsService;
use Sabri\HomeNewsFeed\NewsWorkflow;
use Sabri\HomeNewsFeed\NewsroomAdmin;
use Sabri\HomeNewsFeed\NewsroomDiagnostics;
use Sabri\HomeNewsFeed\Phase4Contracts;
use Sabri\HomeNewsFeed\SafeMode;

$phase4b_failures = array();

function sabri_phase4b_assert( $condition, $message ) {
	global $phase4b_failures;
	if ( ! $condition ) {
		$phase4b_failures[] = $message;
	}
}

sabri_test_reset_state( true );
global $sabri_test_current_user_id, $sabri_test_current_caps, $sabri_test_scheduled_events, $sabri_test_roles, $sabri_test_user_roles;
$sabri_test_scheduled_events = array();
$sabri_test_current_user_id = 1;
sabri_phase4b_grant_test_caps();
NewsCapabilities::apply_default_policy();

$transitions = NewsWorkflow::transitions();
sabri_phase4b_assert( array_keys( $transitions ) === Phase4Contracts::editorial_states(), 'Every frozen Phase 4 state must have an explicit transition entry.' );
sabri_phase4b_assert( in_array( 'editorial-review', NewsWorkflow::allowed_targets( 'draft' ), true ), 'Draft must allow submission to editorial review.' );
sabri_phase4b_assert( ! in_array( 'published', NewsWorkflow::allowed_targets( 'draft' ), true ), 'Draft must not bypass review and publish directly.' );
sabri_phase4b_assert( 'submit_editorial_news' === NewsWorkflow::required_capability( 'draft', 'editorial-review' ), 'Draft submission must require submit authority.' );
sabri_phase4b_assert( 'review_editorial_news' === NewsWorkflow::required_capability( 'editorial-review', 'ready-for-publication' ), 'Approval must require review authority.' );
sabri_phase4b_assert( 'schedule_editorial_news' === NewsWorkflow::required_capability( 'ready-for-publication', 'scheduled' ), 'Scheduling must require scheduling authority.' );
sabri_phase4b_assert( 'do_not_allow' === NewsWorkflow::required_capability( 'draft', 'published' ), 'Review bypasses must fail closed.' );
sabri_phase4b_assert( ! NewsWorkflow::validate_transition( ' Draft', 'published!!!' )['success'], 'Malformed workflow identifiers must fail closed.' );

$schedule_input = gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 2 * DAY_IN_SECONDS ) );
$valid_input = array(
	'title' => 'Phase 4B Newsroom Test Article',
	'content' => '<p>Validated editorial content.</p>',
	'subtitle' => 'Secure composer foundation',
	'summary' => 'A bounded summary for editorial review.',
	'language' => 'en-US',
	'priority' => '25',
	'section' => 'platform-news',
	'article_type' => 'standard-news',
	'topics' => 'research,platform',
	'countries' => array( 'pakistan' ),
	'regions' => array( 'south-asia' ),
	'featured_image_id' => '77',
	'reviewing_editor_id' => 2,
	'medical_reviewer_id' => 2,
	'fact_check_required' => '1',
	'medical_review_required' => true,
	'target_state' => 'scheduled',
	'schedule_at' => $schedule_input,
	'unknown_field' => 'must-not-propagate',
);
$valid = NewsComposerValidator::validate( $valid_input );
sabri_phase4b_assert( $valid['success'], 'A complete explicitly zoned composer payload must validate.' );
sabri_phase4b_assert( NewsSchedulingService::normalize_utc( $schedule_input ) === $valid['data']['schedule_at_utc'], 'Schedule values must normalize to UTC.' );
sabri_phase4b_assert( array( 'research', 'platform' ) === $valid['data']['topics'], 'Comma-separated taxonomy slugs must normalize safely.' );
sabri_phase4b_assert( ! array_key_exists( 'unknown_field', $valid['data'] ), 'Unknown composer fields must not propagate.' );
sabri_phase4b_assert( '' === NewsSchedulingService::normalize_utc( '2027-02-31T03:04:05Z' ), 'Invalid calendar dates must fail rather than normalize.' );
sabri_phase4b_assert( '' === NewsSchedulingService::normalize_utc( '2027-01-02 03:04:05' ), 'Timezone-ambiguous schedule values must fail.' );

$invalid = NewsComposerValidator::validate(
	array(
		'title' => '', 'language' => ' en-US', 'priority' => '25evil',
		'section' => 'Platform-News', 'article_type' => 'standard-news!',
		'topics' => array( 'valid', 'Bad Slug' ), 'featured_image_id' => array( 1 ),
		'fact_check_required' => 'yes', 'target_state' => 'scheduled',
		'schedule_at' => '2027-02-31T03:04:05Z',
	)
);
foreach ( array( 'title', 'language', 'priority', 'section', 'article_type', 'topics', 'featured_image_id', 'fact_check_required', 'summary', 'schedule_at' ) as $field ) {
	sabri_phase4b_assert( isset( $invalid['errors'][ $field ] ), 'Expected validation error missing: ' . $field );
}

$guard_method = NewsService::request_guard( array( 'method' => 'GET', 'nonce_verified' => true ) );
$guard_nonce = NewsService::request_guard( array( 'method' => 'POST', 'nonce_verified' => false ) );
sabri_phase4b_assert( ! $guard_method['success'] && 'request_method_invalid' === $guard_method['code'], 'State changes must require POST.' );
sabri_phase4b_assert( ! $guard_nonce['success'] && 'request_nonce_invalid' === $guard_nonce['code'], 'State changes must require verified nonces.' );

$screens = NewsroomAdmin::screens();
sabri_phase4b_assert( isset( $screens['newsroom'], $screens['composer'] ), 'Dedicated newsroom and composer screens must exist.' );
sabri_phase4b_assert( 'read_editorial_news' === $screens['newsroom']['capability'], 'Newsroom screen capability changed.' );
sabri_phase4b_assert( 'create_editorial_news' === $screens['composer']['capability'], 'Composer screen capability changed.' );

$queues = NewsQueueService::definitions();
foreach ( array( 'own-drafts', 'submitted', 'editorial-review', 'fact-check', 'medical-review', 'changes-requested', 'approved', 'scheduled', 'publication-ready', 'published', 'accountability' ) as $queue ) {
	sabri_phase4b_assert( isset( $queues[ $queue ] ), 'Required newsroom queue missing: ' . $queue );
}
sabri_phase4b_assert( empty( NewsQueueService::query_args( 'unknown-queue' ) ), 'Unknown queues must not reveal query structure or counts.' );
$own_args = NewsQueueService::query_args( 'own-drafts', 1, 500 );
sabri_phase4b_assert( 50 === $own_args['posts_per_page'] && 1 === $own_args['author'], 'Own queues must be bounded and author-isolated.' );
$submitted_args = NewsQueueService::query_args( 'submitted', 1, 20 );
sabri_phase4b_assert( 1 === $submitted_args['author'] && in_array( 'editorial-review', $submitted_args['meta_query'][0]['value'], true ), 'Submitted items must remain submitter-isolated.' );
$fact_check_args = NewsQueueService::query_args( 'fact-check', 1, 20 );
$medical_args = NewsQueueService::query_args( 'medical-review', 1, 20 );
sabri_phase4b_assert( '_sabri_news_reviewing_editor_id' === $fact_check_args['meta_query'][1]['key'] && 1 === $fact_check_args['meta_query'][1]['value'], 'Fact-check queues must be isolated to assigned reviewers.' );
sabri_phase4b_assert( '_sabri_news_medical_reviewer_id' === $medical_args['meta_query'][1]['key'] && 1 === $medical_args['meta_query'][1]['value'], 'Medical-review queues must be isolated to assigned reviewers.' );

$original_caps = $sabri_test_current_caps;
$sabri_test_current_user_id = 3;
$sabri_test_current_caps = array( 'review_editorial_news' => true, 'edit_editorial_news' => true );
sabri_phase4b_assert( ! NewsPolicy::can_assign_reviewer( 0, 3, 'medical' ), 'Restricted reviewers must not self-assign.' );
$sabri_test_current_user_id = 1;
$sabri_test_current_caps = $original_caps;
$sabri_test_roles['editor_only'] = new Sabri_Test_Role( array( 'review_editorial_news' => true ) );
$sabri_test_user_roles[8] = array( 'editor_only' );
sabri_phase4b_assert( NewsPolicy::can_assign_reviewer( 0, 8, 'editorial' ), 'Editorial reviewers must remain assignable to editorial review.' );
sabri_phase4b_assert( ! NewsPolicy::can_assign_reviewer( 0, 8, 'fact-check' ), 'Editorial-only reviewers must not be assigned as fact-checkers.' );

$invalid_image_input = $valid_input;
$invalid_image_input['target_state'] = 'draft';
$invalid_image_input['featured_image_id'] = 999;
unset( $invalid_image_input['schedule_at'] );
$invalid_image = NewsService::save( 0, $invalid_image_input, array( 'method' => 'POST', 'nonce_verified' => true ) );
sabri_phase4b_assert( ! $invalid_image['success'] && 'featured_image_invalid' === $invalid_image['code'], 'Non-image attachment IDs must fail before article persistence.' );

$draft_input = $valid_input;
$draft_input['target_state'] = 'draft';
unset( $draft_input['schedule_at'] );
$created = NewsService::save( 0, $draft_input, array( 'method' => 'POST', 'nonce_verified' => true ) );
sabri_phase4b_assert( $created['success'] && ! empty( $created['data']['post_id'] ), 'Authorized composer save must create a private Editorial News draft.' );
$post_id = ! empty( $created['data']['post_id'] ) ? (int) $created['data']['post_id'] : 0;
sabri_phase4b_assert( 'draft' === get_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, true ), 'New articles must retain canonical draft state.' );
sabri_phase4b_assert( has_term( 'platform-news', 'sabri_news_section', $post_id ), 'Composer service must store the controlled section.' );
sabri_phase4b_assert( 77 === (int) get_post_meta( $post_id, '_thumbnail_id', true ), 'Validated featured images must use core thumbnail storage.' );

$remove_image_input = $draft_input;
$remove_image_input['featured_image_id'] = 0;
$removed_image = NewsService::save( $post_id, $remove_image_input, array( 'method' => 'POST', 'nonce_verified' => true ) );
sabri_phase4b_assert( $removed_image['success'] && 0 === (int) get_post_meta( $post_id, '_thumbnail_id', true ), 'An explicit zero must remove an existing featured image.' );
$omit_image_input = $draft_input;
unset( $omit_image_input['featured_image_id'] );
$omitted_image = NewsService::save( $post_id, $omit_image_input, array( 'method' => 'POST', 'nonce_verified' => true ) );
sabri_phase4b_assert( $omitted_image['success'], 'Programmatic updates may omit the featured-image field without failure.' );

$submitted = NewsService::transition( $post_id, 'editorial-review', array( 'method' => 'POST', 'nonce_verified' => true ) );
$submitted_again = NewsService::transition( $post_id, 'editorial-review', array( 'method' => 'POST', 'nonce_verified' => true ) );
$approved = NewsService::transition( $post_id, 'ready-for-publication', array( 'method' => 'POST', 'nonce_verified' => true ) );
sabri_phase4b_assert( $submitted['success'] && $approved['success'], 'Allow-listed review transitions must complete through the service.' );
sabri_phase4b_assert( $submitted_again['success'] && 'workflow_unchanged' === $submitted_again['code'], 'Repeated same-state transitions must be no-ops.' );

$scheduled = NewsSchedulingService::schedule( $post_id, $schedule_input );
sabri_phase4b_assert( $scheduled['success'], 'Authorized future schedules must be created.' );
sabri_phase4b_assert( 'scheduled' === get_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, true ), 'Scheduling must set canonical scheduled state.' );
$scheduled_again = NewsSchedulingService::schedule( $post_id, $schedule_input );
sabri_phase4b_assert( $scheduled_again['success'] && 'schedule_unchanged' === $scheduled_again['code'], 'Duplicate schedules must be idempotent.' );
$event_key = NewsSchedulingService::HOOK . '|' . md5( serialize( array( $post_id ) ) );
unset( $sabri_test_scheduled_events[ $event_key ] );
$missing_diagnostics = NewsSchedulingService::diagnostics( $post_id );
sabri_phase4b_assert( $missing_diagnostics['event_missing'], 'Missing cron events must be detected.' );
$repaired = NewsSchedulingService::repair_missing_event( $post_id );
sabri_phase4b_assert( $repaired['success'] && 'schedule_repaired' === $repaired['code'], 'Missing cron events must be repairable without duplicate publication.' );
NewsSchedulingService::mark_due( $post_id );
$diagnostics = NewsSchedulingService::diagnostics( $post_id );
sabri_phase4b_assert( $diagnostics['due'] && false === $diagnostics['auto_publish_enabled'], 'Due schedules must remain publication-preparation only in Phase 4B.' );

$newsroom_health = NewsroomDiagnostics::report();
sabri_phase4b_assert( $newsroom_health['success'], 'Read-only Phase 4B diagnostics must report complete runtime classes.' );
sabri_phase4b_assert( ! $newsroom_health['checks']['publicly_queryable'] && ! $newsroom_health['checks']['rest_exposed'], 'Diagnostics must confirm public News and REST remain closed.' );
sabri_phase4b_assert( false === $newsroom_health['checks']['auto_publish_enabled'] && false === $newsroom_health['checks']['mutated'], 'Diagnostics must remain read-only and must not authorize publication.' );

$cancelled = NewsSchedulingService::cancel( $post_id );
sabri_phase4b_assert( $cancelled['success'] && 'ready-for-publication' === get_post_meta( $post_id, Phase4Contracts::WORKFLOW_META_KEY, true ), 'Schedule cancellation must restore publication-ready state.' );
$audit_option = get_option( 'sabri_feed_news_audit_' . $post_id, array() );
sabri_phase4b_assert( is_array( $audit_option ) && count( $audit_option ) >= 8, 'Consequential editorial actions must append private audit events.' );

SafeMode::set_emergency_disabled( true );
sabri_phase4b_assert( ! NewsPolicy::writes_allowed(), 'Emergency Disable must close Phase 4B writes.' );
$blocked = NewsService::transition( $post_id, 'editorial-review', array( 'method' => 'POST', 'nonce_verified' => true ) );
sabri_phase4b_assert( ! $blocked['success'], 'Emergency Disable must reject state-changing service operations.' );
SafeMode::set_emergency_disabled( false );

$publication = NewsComposerValidator::validate(
	array(
		'title' => 'Premature Publication', 'content' => 'Closed', 'summary' => 'Closed during Phase 4B.',
		'language' => 'en-US', 'section' => 'platform-news', 'article_type' => 'standard-news', 'target_state' => 'published',
	)
);
sabri_phase4b_assert( ! $publication['success'] && 'phase4b_publication_closed' === $publication['errors']['target_state'], 'Phase 4B must not authorize public publication.' );

if ( ! empty( $phase4b_failures ) ) {
	fwrite( STDERR, "Phase 4B newsroom tests failed:\n- " . implode( "\n- ", $phase4b_failures ) . "\n" );
	exit( 1 );
}

echo "OK - Phase 4B newsroom, composer, queue, workflow, scheduling, diagnostics, and audit tests passed.\n";
