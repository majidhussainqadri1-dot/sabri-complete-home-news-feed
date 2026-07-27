<?php
require __DIR__ . '/phase5-stubs.php';
$root = dirname( __DIR__ );
foreach ( array(
	'class-phase4-contracts.php','class-phase5-contracts.php','class-phase5-feature-settings.php','class-phase5-database.php','class-phase5-repository.php','class-privacy-scanner.php','class-upload-security.php','class-ssrf-guard.php'
) as $file ) require_once $root . '/includes/' . $file;

use Sabri\HomeNewsFeed\Phase4Contracts;
use Sabri\HomeNewsFeed\Phase5Contracts;
use Sabri\HomeNewsFeed\Phase5FeatureSettings;
use Sabri\HomeNewsFeed\Phase5Database;
use Sabri\HomeNewsFeed\PrivacyScanner;
use Sabri\HomeNewsFeed\UploadSecurity;

$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) { if ( ! $condition ) $failures[] = $message; };
$assert( '4A' === Phase4Contracts::CHECKPOINT, 'Checkpoint changed before release acceptance.' );
$assert( '1.0.2' === SABRI_HNF_VERSION && '1.0.0' === SABRI_HNF_SCHEMA_VERSION, 'Plugin/schema release identities are inconsistent.' );
$assert( 0 === array_sum( Phase5Contracts::feature_flags() ), 'Phase 5 gates are not disabled by default.' );
$assert( count( Phase5Database::table_names( 'wp_' ) ) === 10, 'Phase 5 canonical table count is incomplete.' );
foreach ( Phase5Database::table_names( 'wp_' ) as $table ) $assert( str_starts_with( $table, 'wp_sabri_news_' ), 'Dynamic table prefix is incorrect.' );
$clean = Phase5FeatureSettings::sanitize( array( 'sources_enabled' => '1', 'reviews_enabled' => ' 1 ', 'unknown' => 1 ) );
$assert( 1 === $clean['sources_enabled'] && 0 === $clean['reviews_enabled'] && ! isset( $clean['unknown'] ), 'Feature gate sanitization is not fail closed.' );
$assert( Phase5Contracts::positive_int( '5' ) === 5 && Phase5Contracts::positive_int( '-1' ) === 0 && Phase5Contracts::positive_int( '1e3' ) === 0, 'Strict identifier parsing failed.' );
$assert( 'en-US' === Phase5Contracts::language_tag( 'en-US' ) && '' === Phase5Contracts::language_tag( '../en' ), 'Language validation failed.' );
$scan = PrivacyScanner::scan( 'Contact test@example.com or +92 300 1234567.' );
$assert( $scan['blocked'] && in_array( 'email', $scan['categories'], true ) && in_array( 'phone', $scan['categories'], true ), 'Privacy scanner missed direct identifiers.' );
$redacted = PrivacyScanner::redact( 'test@example.com +92 300 1234567' );
$assert( false === strpos( $redacted, 'test@example.com' ), 'Privacy redaction retained email.' );
$assert( in_array( 'docx', UploadSecurity::allowed_extensions(), true ) && ! in_array( 'svg', UploadSecurity::allowed_extensions(), true ), 'Upload extension policy is incorrect.' );
$schema = implode( "\n", Phase5Database::schema( 'wp_' ) );
foreach ( array( 'sabri_news_sources','sabri_news_reviews','sabri_news_submissions','sabri_news_corrections','sabri_news_breaking','sabri_news_translations','sabri_news_preview_tokens','sabri_news_audit_integrity' ) as $needle ) $assert( false !== strpos( $schema, $needle ), 'Missing schema table: ' . $needle );
$plugin = file_get_contents( $root . '/includes/class-plugin.php' );
foreach ( array( 'Phase5Migrations::class','SourceRegistry::class','ReviewLedger::class','SubmissionService::class','BreakingNewsService::class','CorrectionLedger::class','NewsDistribution::class','Phase5Rest::class','Phase5NewsroomAdmin::class' ) as $needle ) $assert( false !== strpos( $plugin, $needle ), 'Plugin bootstrap is missing ' . $needle );
$assert( false === strpos( $plugin, 'Phase5Contracts::class' ), 'Utility-only Phase5Contracts was incorrectly registered as a runtime module.' );
$assert( false === strpos( $plugin, 'UploadSecurity::class' ), 'Utility-only UploadSecurity was incorrectly registered as a runtime module.' );

$source_registry = file_get_contents( $root . '/includes/class-source-registry.php' );
foreach ( array( 'data[\'status\']', 'duplicate_exists(', 'verify_news_sources' ) as $needle ) $assert( false !== strpos( $source_registry, $needle ), 'Source registry integrity guard is missing: ' . $needle );
$review_ledger = file_get_contents( $root . '/includes/class-review-ledger.php' );
foreach ( array( 'array( \'pending\', \'changes-requested\' )', 'revision_belongs', 'superseded' ) as $needle ) $assert( false !== strpos( $review_ledger, $needle ), 'Review ledger immutability/revision guard is missing: ' . $needle );
$submission_service = file_get_contents( $root . '/includes/class-submission-service.php' );
$assert( false !== strpos( $submission_service, 'validate( $input, false )' ), 'Submission drafts are incorrectly required to be publication-complete.' );
$assert( false === strpos( $submission_service, '\'converted\'=>array(' ), 'Submission state can still be forged as converted outside conversion service.' );
$breaking_service = file_get_contents( $root . '/includes/class-breaking-news-service.php' );
$assert( false !== strpos( $breaking_service, 'NewsPolicy::can_public_read' ), 'Breaking News can be attached to a non-public article.' );
$correction_ledger = file_get_contents( $root . '/includes/class-correction-ledger.php' );
foreach ( array( 'array( \'requested\', \'under-review\', \'approved\' )', 'wp_is_post_revision', 'wp_update_post' ) as $needle ) $assert( false !== strpos( $correction_ledger, $needle ), 'Correction integrity guard is missing: ' . $needle );
$translation_service = file_get_contents( $root . '/includes/class-translation-service.php' );
foreach ( array( 'ReviewLedger::decide', 'public static function publish', '\'state\'=>\'published\'' ) as $needle ) $assert( false !== strpos( $translation_service, $needle ), 'Translation approval/publication lifecycle is incomplete: ' . $needle );

if ( $failures ) { fwrite( STDERR, implode( "\n", $failures ) . "\n" ); exit( 1 ); }
echo "Phase 5 final contract tests passed.\n";