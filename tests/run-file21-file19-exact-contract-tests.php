<?php
/**
 * Exact static contract test between File 21 and the pinned File 19 v3 source.
 *
 * @package SabriCompleteHomeNewsFeed
 */

$file21_root = getenv( 'FILE21_ROOT' ) ?: dirname( __DIR__ );
$file19_root = getenv( 'FILE19_ROOT' );
if ( ! is_string( $file19_root ) || '' === $file19_root ) {
	fwrite( STDERR, "FAIL: FILE19_ROOT is required.\n" );
	exit( 1 );
}

$read = static function ( $path ) {
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, 'FAIL: missing contract source ' . $path . "\n" );
		exit( 1 );
	}
	return (string) file_get_contents( $path );
};

$file21 = $read( $file21_root . '/includes/class-next-generation-integrations.php' );
$file19 = array(
	'functions' => $read( $file19_root . '/19-unified-notifications/includes/functions.php' ),
	'contracts' => $read( $file19_root . '/19-unified-notifications/docs/CONTRACTS.md' ),
	'registry'  => $read( $file19_root . '/19-unified-notifications/includes/class-sun-producer-registry.php' ),
	'validator' => $read( $file19_root . '/19-unified-notifications/includes/class-sun-event-validator.php' ),
	'activator' => $read( $file19_root . '/19-unified-notifications/includes/class-sun-activator.php' ),
);

$failures = array();
$check    = static function ( $ok, $message ) use ( &$failures ) {
	if ( ! $ok ) {
		$failures[] = $message;
	}
};

// File 19's public PHP ingestion surface must remain present at the pinned contract head.
$check( false !== strpos( $file19['functions'], 'function sun_register_notification_producer' ), 'File 19 producer-registration API missing.' );
$check( false !== strpos( $file19['functions'], 'function sun_ingest_domain_event' ), 'File 19 event-ingestion API missing.' );
$check( false !== strpos( $file19['contracts'], 'sun.event.v1' ), 'File 19 sun.event.v1 contract missing.' );

// The exact required envelope must agree with the current File 19 validator.
foreach ( array( 'producer', 'owner', 'event_id', 'event_type', 'schema_version', 'occurred_at', 'recipients' ) as $field ) {
	$check( false !== strpos( $file19['validator'], "'{$field}'" ), 'File 19 validator no longer requires/recognizes ' . $field . '.' );
	$check( false !== strpos( $file21, "'{$field}'" ), 'File 21 event envelope missing ' . $field . '.' );
}

// File 21 must register a bounded producer contract rather than rely on an untyped action only.
$check( false !== strpos( $file21, 'sun_register_notification_producer' ), 'File 21 does not register its File 19 producer.' );
$check( false !== strpos( $file21, "'event_types'" ) && false !== strpos( $file21, "'schema_versions'" ), 'File 21 producer declaration is not version/type bounded.' );
$check( false !== strpos( $file21, "'owner'           => 'File 21'" ), 'File 21 producer owner does not match its event owner.' );

// The chosen fact must satisfy the validator's naming shape and an active File 19 delivery policy.
$event_type = 'Publishing.DigestCandidatesPrepared';
$check( false !== strpos( $file21, $event_type ), 'File 21 exact File 19 event type changed unexpectedly.' );
$check( 1 === preg_match( '/^[A-Z][A-Za-z0-9]*(?:\.[A-Z][A-Za-z0-9]*)+$/', $event_type ), 'File 21 event type violates File 19 domain-fact syntax.' );
$check( false !== strpos( $file19['activator'], "'Publishing.*'" ), 'Pinned File 19 no longer seeds a matching Publishing.* policy.' );
$check( false !== strpos( $file19['registry'], 'schema_versions' ), 'Pinned File 19 producer registry no longer supports bounded schema versions.' );

// Canonical ingestion must be attempted and unavailable/rejected states must remain truthful.
$check( false !== strpos( $file21, 'sun_ingest_domain_event( $event )' ), 'File 21 does not call File 19 canonical ingestion.' );
$check( false !== strpos( $file21, "'delivery_available'" ) && false !== strpos( $file21, "'ingest_status'" ), 'File 21 does not expose File 19 availability/ingestion state.' );
$check( false !== strpos( $file21, 'sabri_file19_digest_candidates' ), 'Transitional observer hook was unexpectedly removed.' );
$check( false === strpos( $file21, 'File21DigestCandidatesPrepared.v1' ), 'Obsolete non-File19 event type remains.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . "\n" );
	}
	exit( 1 );
}

echo "File 21 <-> File 19 exact contract compatibility: PASS\n";
