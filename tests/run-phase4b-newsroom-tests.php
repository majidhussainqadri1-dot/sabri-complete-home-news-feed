<?php
/**
 * Phase 4B newsroom workflow and composer validation tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\NewsComposerValidator;
use Sabri\HomeNewsFeed\NewsWorkflow;
use Sabri\HomeNewsFeed\Phase4Contracts;

$phase4b_failures = array();

function sabri_phase4b_assert( $condition, $message ) {
	global $phase4b_failures;
	if ( ! $condition ) {
		$phase4b_failures[] = $message;
	}
}

sabri_test_reset_state( true );

$transitions = NewsWorkflow::transitions();
sabri_phase4b_assert( array_keys( $transitions ) === Phase4Contracts::editorial_states(), 'Every frozen Phase 4 state must have an explicit transition entry.' );
sabri_phase4b_assert( in_array( 'editorial-review', NewsWorkflow::allowed_targets( 'draft' ), true ), 'Draft must allow submission to editorial review.' );
sabri_phase4b_assert( ! in_array( 'published', NewsWorkflow::allowed_targets( 'draft' ), true ), 'Draft must not bypass review and publish directly.' );
sabri_phase4b_assert( 'submit_editorial_news' === NewsWorkflow::required_capability( 'draft', 'editorial-review' ), 'Draft submission must require the submit capability.' );
sabri_phase4b_assert( 'review_editorial_news' === NewsWorkflow::required_capability( 'editorial-review', 'ready-for-publication' ), 'Editorial approval must require review authority.' );
sabri_phase4b_assert( 'medical_review_editorial_news' === NewsWorkflow::required_capability( 'medical-review', 'ready-for-publication' ), 'Medical approval must require medical-review authority.' );
sabri_phase4b_assert( 'schedule_editorial_news' === NewsWorkflow::required_capability( 'ready-for-publication', 'scheduled' ), 'Scheduling must require scheduling authority.' );
sabri_phase4b_assert( 'publish_editorial_news' === NewsWorkflow::required_capability( 'scheduled', 'published' ), 'Scheduled publication must require publication authority.' );
sabri_phase4b_assert( 'do_not_allow' === NewsWorkflow::required_capability( 'draft', 'published' ), 'Unknown or bypass transitions must fail closed.' );

$valid_transition = NewsWorkflow::validate_transition( 'editorial-review', 'fact-check' );
sabri_phase4b_assert( $valid_transition['success'] && 'workflow_transition_valid' === $valid_transition['code'], 'Allow-listed workflow transitions must validate.' );
$unchanged_transition = NewsWorkflow::validate_transition( 'draft', 'draft' );
sabri_phase4b_assert( $unchanged_transition['success'] && 'workflow_unchanged' === $unchanged_transition['code'], 'Idempotent same-state requests must be safe no-ops.' );
$invalid_transition = NewsWorkflow::validate_transition( ' Draft', 'published!!!' );
sabri_phase4b_assert( ! $invalid_transition['success'] && 'invalid_workflow_state' === $invalid_transition['code'], 'Malformed workflow identifiers must fail closed.' );
$bypass_transition = NewsWorkflow::validate_transition( 'draft', 'published' );
sabri_phase4b_assert( ! $bypass_transition['success'] && 'workflow_transition_denied' === $bypass_transition['code'], 'Review-bypass transitions must be denied.' );

$valid_input = array(
	'title'               => 'Phase 4B Newsroom Test Article',
	'content'             => '<p>Validated editorial content.</p>',
	'subtitle'            => 'Secure composer foundation',
	'summary'             => 'A bounded summary for editorial review.',
	'language'            => 'en-US',
	'priority'            => '25',
	'section'             => 'platform-news',
	'article_type'        => 'standard-news',
	'reviewing_editor_id' => 7,
	'medical_reviewer_id' => 8,
	'target_state'        => 'scheduled',
	'schedule_at'         => '2027-01-02T03:04:05+05:00',
	'unknown_field'       => 'must-not-propagate',
);
$valid = NewsComposerValidator::validate( $valid_input );
sabri_phase4b_assert( $valid['success'], 'A complete, explicitly zoned composer payload must validate.' );
sabri_phase4b_assert( '2027-01-01 22:04:05' === $valid['data']['schedule_at_utc'], 'Scheduled datetime must normalize to UTC.' );
sabri_phase4b_assert( ! array_key_exists( 'unknown_field', $valid['data'] ), 'Unknown composer fields must not propagate.' );
sabri_phase4b_assert( 25 === $valid['data']['priority'], 'Validated priority must retain its bounded integer value.' );

$invalid = NewsComposerValidator::validate(
	array(
		'title'        => '',
		'language'     => ' en-US',
		'priority'     => '25evil',
		'section'      => 'Platform-News',
		'article_type' => 'standard-news!',
		'target_state' => 'scheduled',
		'schedule_at'  => '2027-01-02 03:04:05',
	)
);
sabri_phase4b_assert( ! $invalid['success'], 'Malformed composer input must fail validation.' );
foreach ( array( 'title', 'language', 'priority', 'section', 'article_type', 'summary', 'schedule_at' ) as $field ) {
	sabri_phase4b_assert( isset( $invalid['errors'][ $field ] ), 'Expected composer validation error was not reported: ' . $field );
}

$publication = NewsComposerValidator::validate(
	array(
		'title'         => 'Premature Publication',
		'summary'       => 'This must remain closed during Phase 4B.',
		'language'      => 'en-US',
		'section'       => 'platform-news',
		'article_type'  => 'standard-news',
		'target_state'  => 'published',
	)
);
sabri_phase4b_assert( ! $publication['success'] && 'phase4b_publication_closed' === $publication['errors']['target_state'], 'Phase 4B composer must not authorize public publication.' );

if ( ! empty( $phase4b_failures ) ) {
	fwrite( STDERR, "Phase 4B newsroom tests failed:\n- " . implode( "\n- ", $phase4b_failures ) . "\n" );
	exit( 1 );
}

echo "OK - Phase 4B workflow and composer validation tests passed.\n";
