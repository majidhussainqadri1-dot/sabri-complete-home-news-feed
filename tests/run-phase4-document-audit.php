<?php
/**
 * Phase 4 Markdown integrity and cross-document consistency audit.
 *
 * @package SabriCompleteHomeNewsFeed
 */

$root = dirname( __DIR__ );
$documents = array(
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
$manifest = array();

function sabri_phase4_audit_fail( $message ) {
	global $failures;
	$failures[] = $message;
}

foreach ( $documents as $document ) {
	$path = $root . '/' . $document;
	if ( ! is_file( $path ) ) {
		sabri_phase4_audit_fail( 'Missing document: ' . $document );
		continue;
	}

	$content = (string) file_get_contents( $path );
	$lines   = explode( "\n", $content );
	$h1      = 0;
	$previous_heading_level = 0;
	$in_fence = false;

	foreach ( $lines as $index => $line ) {
		$line_number = $index + 1;
		if ( preg_match( '/[ \t]+$/', $line ) ) {
			sabri_phase4_audit_fail( $document . ':' . $line_number . ' contains trailing whitespace.' );
		}

		if ( 0 === strpos( ltrim( $line ), '```' ) ) {
			$in_fence = ! $in_fence;
			continue;
		}

		if ( ! $in_fence && preg_match( '/^(#{1,6})\s+\S/', $line, $matches ) ) {
			$level = strlen( $matches[1] );
			if ( 1 === $level ) {
				$h1++;
			}
			if ( 0 !== $previous_heading_level && $level > $previous_heading_level + 1 ) {
				sabri_phase4_audit_fail( $document . ':' . $line_number . ' skips a Markdown heading level.' );
			}
			$previous_heading_level = $level;
		}
	}

	if ( $in_fence ) {
		sabri_phase4_audit_fail( $document . ' ends with an unclosed fenced code block.' );
	}
	if ( 1 !== $h1 ) {
		sabri_phase4_audit_fail( $document . ' must contain exactly one H1 heading; found ' . $h1 . '.' );
	}
	if ( ! preg_match( '/\n\z/', $content ) ) {
		sabri_phase4_audit_fail( $document . ' must end with a newline.' );
	}
	if ( preg_match( '/\b(TODO|TBD|FIXME|XXX)\b/i', $content ) ) {
		sabri_phase4_audit_fail( $document . ' contains an unresolved planning marker.' );
	}
	if ( false === strpos( $content, '1.2.0' ) ) {
		sabri_phase4_audit_fail( $document . ' does not identify the Phase 4 development line 1.2.0.' );
	}

	$manifest[ $document ] = array(
		'bytes'  => strlen( $content ),
		'lines'  => count( $lines ),
		'sha256' => hash( 'sha256', $content ),
	);
}

$contracts = isset( $manifest['PHASE-4-CONTRACTS.md'] ) ? (string) file_get_contents( $root . '/PHASE-4-CONTRACTS.md' ) : '';
$addendum  = isset( $manifest['PHASE-4-CONTRACTS-ADDENDUM-1.md'] ) ? (string) file_get_contents( $root . '/PHASE-4-CONTRACTS-ADDENDUM-1.md' ) : '';
$checklist = isset( $manifest['PHASE-4-HOSTINGER-STAGING-ACCEPTANCE-CHECKLIST.md'] ) ? (string) file_get_contents( $root . '/PHASE-4-HOSTINGER-STAGING-ACCEPTANCE-CHECKLIST.md' ) : '';
$audit     = isset( $manifest['PHASE-4-COMPLETENESS-AUDIT.md'] ) ? (string) file_get_contents( $root . '/PHASE-4-COMPLETENESS-AUDIT.md' ) : '';

preg_match_all( '/^Checklist key: `([^`]+)`$/m', $checklist, $key_matches );
$keys = isset( $key_matches[1] ) ? $key_matches[1] : array();
if ( 20 !== count( $keys ) ) {
	sabri_phase4_audit_fail( 'Expected exactly 20 checklist keys; found ' . count( $keys ) . '.' );
}
if ( count( $keys ) !== count( array_unique( $keys ) ) ) {
	sabri_phase4_audit_fail( 'Duplicate Hostinger checklist keys found.' );
}

preg_match_all( '/^\| `([a-z0-9-]+)` \| [^\n]+$/m', $addendum, $section_matches );
$section_candidates = isset( $section_matches[1] ) ? $section_matches[1] : array();
$expected_sections = array(
	'platform-news', 'classical-homeopathy', 'homeopathy-research', 'clinical-education',
	'materia-medica', 'repertory', 'public-health', 'medical-research', 'pathology-anatomy',
	'nutrition-hygiene', 'homeopathy-education', 'universities-conferences',
	'doctors-global-clinics', 'professional-regulatory', 'islamic-spiritual-healing',
	'founder-updates', 'research-center-news', 'worldwide-health-developments',
);
$section_rows = array_values( array_intersect( $section_candidates, $expected_sections ) );
if ( count( $expected_sections ) !== count( array_unique( $section_rows ) ) ) {
	sabri_phase4_audit_fail( 'Frozen section table is incomplete or contains duplicate section rows.' );
}

$expected_documents = array(
	'PHASE-4-CONTRACTS.md',
	'PHASE-4-CONTRACTS-ADDENDUM-1.md',
	'PHASE-4-EDITORIAL-POLICY.md',
	'PHASE-4-SECURITY-PRIVACY.md',
	'PHASE-4-ARCHITECTURE.md',
	'PHASE-4-HOSTINGER-STAGING-ACCEPTANCE-CHECKLIST.md',
	'PHASE-4-ROLLBACK-RUNBOOK.md',
	'PHASE-4-COMPLETENESS-AUDIT.md',
);
foreach ( $expected_documents as $document ) {
	if ( false === strpos( $audit, $document ) && 'PHASE-4-COMPLETENESS-AUDIT.md' !== $document ) {
		sabri_phase4_audit_fail( 'Completeness audit does not reference normative document: ' . $document );
	}
}

$consistency_terms = array(
	'build/phase-4-editorial-news-1.2.0',
	'sabri_news',
	'sabri-home-news-feed/v1',
	'editorial_news_enabled',
	'news_submissions_enabled',
	'breaking_news_enabled',
	'scheduled_news_enabled',
	'news_corrections_enabled',
	'news_rss_enabled',
	'news_schema_enabled',
	'news_notifications_enabled',
);
foreach ( $consistency_terms as $term ) {
	if ( false === strpos( $contracts . "\n" . $addendum, $term ) ) {
		sabri_phase4_audit_fail( 'Frozen consistency term is missing: ' . $term );
	}
}

$prohibited_claims = array(
	'Phase 4 is fully implemented',
	'Phase 4 is live',
	'approved for live deployment',
	'automatically deploys to live',
);
$combined = '';
foreach ( $documents as $document ) {
	if ( is_file( $root . '/' . $document ) ) {
		$combined .= "\n" . file_get_contents( $root . '/' . $document );
	}
}
foreach ( $prohibited_claims as $claim ) {
	if ( false !== stripos( $combined, $claim ) ) {
		sabri_phase4_audit_fail( 'Planning documents contain a prohibited implementation/release claim: ' . $claim );
	}
}

ksort( $manifest );
$output_directory = $root . '/phase4-document-audit';
if ( ! is_dir( $output_directory ) && ! mkdir( $output_directory, 0777, true ) && ! is_dir( $output_directory ) ) {
	sabri_phase4_audit_fail( 'Unable to create document-audit output directory.' );
}
if ( is_dir( $output_directory ) ) {
	file_put_contents(
		$output_directory . '/manifest.json',
		json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n"
	);
}

if ( $failures ) {
	echo "FAILED - Phase 4 document audit\n";
	foreach ( $failures as $failure ) {
		echo '- ' . $failure . "\n";
	}
	exit( 1 );
}

echo 'OK - Phase 4 document audit passed for ' . count( $manifest ) . " documents.\n";
foreach ( $manifest as $document => $details ) {
	echo $details['sha256'] . '  ' . $document . "\n";
}
