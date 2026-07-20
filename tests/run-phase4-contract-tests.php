<?php
/**
 * Phase 4 checkpoint 4.0 documentation and contract tests.
 *
 * This suite intentionally validates the frozen planning documents before any
 * Phase 4 feature implementation is authorized.
 *
 * @package SabriCompleteHomeNewsFeed
 */

$root = dirname( __DIR__ );

$required_documents = array(
	'PHASE-4-CONTRACTS.md',
	'PHASE-4-CONTRACTS-ADDENDUM-1.md',
	'PHASE-4-EDITORIAL-POLICY.md',
	'PHASE-4-ARCHITECTURE.md',
	'PHASE-4-SECURITY-PRIVACY.md',
	'PHASE-4-HOSTINGER-STAGING-ACCEPTANCE-CHECKLIST.md',
	'PHASE-4-ROLLBACK-RUNBOOK.md',
	'PHASE-4-COMPLETENESS-AUDIT.md',
);

$failures = array();
$contents = array();

function sabri_phase4_fail( $message ) {
	global $failures;
	$failures[] = $message;
}

function sabri_phase4_assert( $condition, $message ) {
	if ( ! $condition ) {
		sabri_phase4_fail( $message );
	}
}

function sabri_phase4_contains( $document, $needle, $message ) {
	global $contents;
	sabri_phase4_assert(
		isset( $contents[ $document ] ) && false !== strpos( $contents[ $document ], $needle ),
		$message
	);
}

foreach ( $required_documents as $document ) {
	$path = $root . '/' . $document;
	sabri_phase4_assert( is_file( $path ), 'Missing required Phase 4 document: ' . $document );
	if ( is_file( $path ) ) {
		$contents[ $document ] = (string) file_get_contents( $path );
		sabri_phase4_assert( '' !== trim( $contents[ $document ] ), 'Required document is empty: ' . $document );
		sabri_phase4_assert( 0 !== strncmp( $contents[ $document ], "\xEF\xBB\xBF", 3 ), 'UTF-8 BOM is not permitted: ' . $document );
		sabri_phase4_assert( 0 === substr_count( $contents[ $document ], "\r" ), 'CR line endings are not permitted: ' . $document );
		sabri_phase4_assert( 0 === substr_count( $contents[ $document ], '<<<<<<<' ), 'Merge-conflict marker found: ' . $document );
		sabri_phase4_assert( 0 === substr_count( $contents[ $document ], '=======' ), 'Merge-conflict marker found: ' . $document );
		sabri_phase4_assert( 0 === substr_count( $contents[ $document ], '>>>>>>>' ), 'Merge-conflict marker found: ' . $document );
		sabri_phase4_assert( 0 === ( substr_count( $contents[ $document ], '```' ) % 2 ), 'Unbalanced fenced code block: ' . $document );
	}
}

$all = implode( "\n", $contents );

sabri_phase4_contains( 'PHASE-4-CONTRACTS.md', 'Target development line: `1.2.0`', 'Target development line must remain frozen at 1.2.0.' );
sabri_phase4_contains( 'PHASE-4-CONTRACTS.md', 'Development branch: `build/phase-4-editorial-news-1.2.0`', 'Phase 4 branch identifier changed unexpectedly.' );
sabri_phase4_contains( 'PHASE-4-CONTRACTS.md', 'sabri_news', 'Editorial News post type must remain frozen as sabri_news.' );
sabri_phase4_contains( 'PHASE-4-CONTRACTS.md', 'sabri-home-news-feed/v1', 'REST namespace must remain compatible with the existing plugin.' );
sabri_phase4_contains( 'PHASE-4-CONTRACTS.md', 'all Phase 4 gates are `0`', 'All Phase 4 feature gates must default disabled.' );
sabri_phase4_contains( 'PHASE-4-CONTRACTS.md', 'Live deployment remains a separate explicit decision after merge.', 'Merge and live deployment must remain separate decisions.' );

$feature_gates = array(
	'editorial_news_enabled',
	'news_submissions_enabled',
	'breaking_news_enabled',
	'scheduled_news_enabled',
	'news_corrections_enabled',
	'news_rss_enabled',
	'news_schema_enabled',
	'news_notifications_enabled',
);
foreach ( $feature_gates as $gate ) {
	sabri_phase4_assert( false !== strpos( $contents['PHASE-4-CONTRACTS.md'], $gate ), 'Missing frozen feature gate: ' . $gate );
}

$taxonomies = array(
	'sabri_news_section',
	'sabri_news_topic',
	'sabri_news_country',
	'sabri_news_region',
	'sabri_news_type',
);
foreach ( $taxonomies as $taxonomy ) {
	sabri_phase4_assert( false !== strpos( $contents['PHASE-4-CONTRACTS.md'], $taxonomy ), 'Missing frozen taxonomy: ' . $taxonomy );
}

$article_types = array(
	'breaking-news',
	'standard-news',
	'research-news',
	'editorial',
	'analysis',
	'interview',
	'event-report',
	'official-announcement',
	'correction-notice',
	'retraction-notice',
);
foreach ( $article_types as $type ) {
	sabri_phase4_assert( false !== strpos( $contents['PHASE-4-CONTRACTS.md'], '`' . $type . '`' ), 'Missing frozen article type: ' . $type );
}

$article_states = array(
	'draft',
	'needs-sources',
	'editorial-review',
	'fact-check',
	'medical-review',
	'ready-for-publication',
	'scheduled',
	'published',
	'updated',
	'correction-pending',
	'corrected',
	'retracted',
	'archived',
);
foreach ( $article_states as $state ) {
	sabri_phase4_assert( false !== strpos( $contents['PHASE-4-CONTRACTS.md'], $state ), 'Missing frozen article state: ' . $state );
}

$capabilities = array(
	'read_editorial_news',
	'create_editorial_news',
	'edit_own_editorial_news',
	'edit_others_editorial_news',
	'submit_editorial_news',
	'review_editorial_news',
	'fact_check_editorial_news',
	'medical_review_editorial_news',
	'publish_editorial_news',
	'schedule_editorial_news',
	'manage_breaking_news',
	'manage_news_sources',
	'manage_news_corrections',
	'retract_editorial_news',
	'translate_editorial_news',
	'manage_news_taxonomies',
	'manage_news_settings',
);
foreach ( $capabilities as $capability ) {
	sabri_phase4_assert( false !== strpos( $contents['PHASE-4-CONTRACTS.md'], $capability ), 'Missing frozen capability: ' . $capability );
	sabri_phase4_assert( false !== strpos( $contents['PHASE-4-CONTRACTS-ADDENDUM-1.md'], $capability ), 'Capability missing from role matrix: ' . $capability );
}

$section_slugs = array(
	'platform-news',
	'classical-homeopathy',
	'homeopathy-research',
	'clinical-education',
	'materia-medica',
	'repertory',
	'public-health',
	'medical-research',
	'pathology-anatomy',
	'nutrition-hygiene',
	'homeopathy-education',
	'universities-conferences',
	'doctors-global-clinics',
	'professional-regulatory',
	'islamic-spiritual-healing',
	'founder-updates',
	'research-center-news',
	'worldwide-health-developments',
);
foreach ( $section_slugs as $slug ) {
	sabri_phase4_assert( 1 === substr_count( $contents['PHASE-4-CONTRACTS-ADDENDUM-1.md'], '| `' . $slug . '` |' ), 'Section slug must occur exactly once in the frozen section table: ' . $slug );
}

$submission_states = array(
	'submitted',
	'initial-review',
	'needs-more-information',
	'accepted-for-editing',
	'rejected',
	'converted-to-news-draft',
	'published',
);
foreach ( $submission_states as $state ) {
	sabri_phase4_assert( false !== strpos( $contents['PHASE-4-CONTRACTS-ADDENDUM-1.md'], $state ), 'Missing frozen submission state: ' . $state );
}

for ( $item = 1; $item <= 16; $item++ ) {
	sabri_phase4_assert(
		1 === preg_match( '/^' . preg_quote( (string) $item, '/' ) . '\.\s+/m', $contents['PHASE-4-CONTRACTS-ADDENDUM-1.md'] ),
		'Missing fact-check checklist item ' . $item . '.'
	);
}

$public_routes = array(
	'/news/',
	'/news/{article-slug}/',
	'/news/section/{slug}/',
	'/news/topic/{slug}/',
	'/news/country/{slug}/',
	'/news/region/{slug}/',
	'/news/type/{slug}/',
	'/news/feed/',
	'/news/section/{slug}/feed/',
	'/news-sitemap.xml',
);
foreach ( $public_routes as $route ) {
	sabri_phase4_assert( false !== strpos( $contents['PHASE-4-CONTRACTS-ADDENDUM-1.md'], $route ), 'Missing frozen public route: ' . $route );
}

$rest_routes = array(
	'GET    /news',
	'GET    /news/{id}',
	'POST   /news',
	'PATCH  /news/{id}',
	'DELETE /news/{id}',
	'POST   /news/{id}/submit',
	'POST   /news/{id}/review',
	'POST   /news/{id}/publish',
	'POST   /news/{id}/schedule',
	'POST   /news/{id}/correct',
	'POST   /news/{id}/retract',
	'GET    /news/{id}/sources',
	'POST   /news/{id}/sources',
	'GET    /news/submissions/me',
);
foreach ( $rest_routes as $route ) {
	sabri_phase4_assert( false !== strpos( $contents['PHASE-4-CONTRACTS.md'], $route ), 'Missing frozen REST route: ' . $route );
}

$checklist_keys = array(
	'phase4_environment_backup',
	'phase4_clean_install',
	'phase4_upgrade_install',
	'phase4_phase2_regression',
	'phase4_phase3_regression',
	'phase4_roles_content_model',
	'phase4_workflow_composer',
	'phase4_sources_factcheck',
	'phase4_medical_privacy',
	'phase4_submissions',
	'phase4_public_routes_feed',
	'phase4_search_cache',
	'phase4_breaking_scheduling',
	'phase4_corrections_retractions',
	'phase4_seo_distribution',
	'phase4_translation',
	'phase4_accessibility',
	'phase4_security_performance',
	'phase4_privacy_emergency',
	'phase4_rollback_acceptance',
);
foreach ( $checklist_keys as $key ) {
	sabri_phase4_assert( 1 === substr_count( $contents['PHASE-4-HOSTINGER-STAGING-ACCEPTANCE-CHECKLIST.md'], 'Checklist key: `' . $key . '`' ), 'Checklist key must occur exactly once: ' . $key );
}
sabri_phase4_assert( 20 === substr_count( $contents['PHASE-4-HOSTINGER-STAGING-ACCEPTANCE-CHECKLIST.md'], 'Checklist key: `phase4_' ), 'Hostinger acceptance checklist must contain exactly 20 Phase 4 keys.' );

$required_policy_phrases = array(
	'Speed does not override accuracy.',
	'Synthetic, reconstructed, or AI-generated quotations are prohibited.',
	'Until that workflow is implemented and approved, identifiable clinical photographs remain prohibited.',
	'Institutional belief or clinical experience may be reported as such and must not be relabelled as universally established scientific proof.',
	'Machine translation may assist drafting but cannot self-publish.',
);
foreach ( $required_policy_phrases as $phrase ) {
	sabri_phase4_assert( false !== strpos( $contents['PHASE-4-EDITORIAL-POLICY.md'], $phrase ), 'Missing editorial safety rule: ' . $phrase );
}

$required_security_phrases = array(
	'a missing permission denies the action',
	'UI hiding is not authorization.',
	'SVG must remain disabled unless a dedicated sanitizer',
	'stored as a one-way hash',
	'Never serialize an all-fields domain object',
	'Do not persist raw IP addresses or full user agents.',
);
foreach ( $required_security_phrases as $phrase ) {
	sabri_phase4_assert( false !== stripos( $all, $phrase ), 'Missing security/privacy invariant: ' . $phrase );
}

sabri_phase4_contains( 'PHASE-4-COMPLETENESS-AUDIT.md', 'Result: **complete at planning and contract level, but not implemented, tested, staging-accepted, merge-approved, version-promoted, or live-deployed.**', 'Completeness audit must not misrepresent planning as implementation.' );
sabri_phase4_contains( 'PHASE-4-ROLLBACK-RUNBOOK.md', 'Hostinger', 'Rollback runbook must remain operationally tied to staging recovery.' );
sabri_phase4_contains( 'PHASE-4-ROLLBACK-RUNBOOK.md', 'Phase 2', 'Rollback must verify Phase 2 regression.' );
sabri_phase4_contains( 'PHASE-4-ROLLBACK-RUNBOOK.md', 'Phase 3', 'Rollback must verify Phase 3 regression.' );

if ( $failures ) {
	echo "FAILED - Phase 4 contract tests\n";
	foreach ( $failures as $failure ) {
		echo '- ' . $failure . "\n";
	}
	exit( 1 );
}

echo 'OK - Phase 4 checkpoint 4.0 frozen contract tests passed across ' . count( $required_documents ) . " documents.\n";
