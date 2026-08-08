<?php
/** Executable source gate for the 2026-08-08 fresh ten-round latest-plan review. */
$root = getenv( 'FILE21_ROOT' ) ?: dirname( __DIR__ );
$files = array(
	'plugin'       => file_get_contents( $root . '/includes/class-plugin.php' ),
	'feed'         => file_get_contents( $root . '/includes/class-next-generation-feed.php' ),
	'hardening'    => file_get_contents( $root . '/includes/class-next-generation-hardening.php' ),
	'privacy'      => file_get_contents( $root . '/includes/class-next-generation-privacy.php' ),
	'integrations' => file_get_contents( $root . '/includes/class-next-generation-integrations.php' ),
	'identity'     => file_get_contents( $root . '/includes/class-canonical-identity-adapter.php' ),
	'builder'      => file_get_contents( $root . '/tools/build-release.py' ),
);
$failures = array();
$check = static function ( $ok, $message ) use ( &$failures ) {
	if ( ! $ok ) {
		$failures[] = $message;
	}
};

// Round 1: current File 00 action assurance remains the only privileged identity gate.
$check( false !== strpos( $files['identity'], 'SMC_Contracts::assertions' ), 'Round 1: canonical File 00 assertions missing.' );
$check( false !== strpos( $files['identity'], "['two_factor_ready']" ) && false !== strpos( $files['identity'], "['session_two_factor']" ), 'Round 1: current 2FA/session assurance gate missing.' );

// Round 2: private NG30 state participates in privacy export and erasure.
$check( false !== strpos( $files['plugin'], 'NextGenerationPrivacy::class' ), 'Round 2: privacy module not registered.' );
$check( false !== strpos( $files['privacy'], 'wp_privacy_personal_data_exporters' ) && false !== strpos( $files['privacy'], 'wp_privacy_personal_data_erasers' ), 'Round 2: WordPress privacy hooks missing.' );
$check( false !== strpos( $files['privacy'], 'NextGenerationFeed::USER_META' ) && false !== strpos( $files['privacy'], 'delete_user_meta' ), 'Round 2: NG30 private user state erasure missing.' );

// Round 3: article/News visibility uses the canonical cross-domain gate.
$check( false === strpos( $files['feed'], 'PostMetadata::user_can_view(' ), 'Round 3: legacy social-only visibility call remains in NG30 runtime.' );
$check( substr_count( $files['feed'], 'InteractionPermissions::can_view_post(' ) >= 8, 'Round 3: canonical cross-domain visibility not applied comprehensively.' );

// Round 4: read-heavy public/private REST surfaces have bounded rate gates.
foreach ( array( 'ng-read-post-context', 'ng-read-compare', 'ng-read-share-card', 'ng-read-stories', 'ng-read-offline-pack', 'ng-read-digest' ) as $bucket ) {
	$check( false !== strpos( $files['hardening'], $bucket ), 'Round 4: missing rate-limit bucket ' . $bucket );
}

// Round 5: File 19 receives the strict sun.event.v1 envelope through its canonical PHP API.
foreach (
	array(
		'sun_register_notification_producer',
		'sun_ingest_domain_event',
		'file21-home-news-feed',
		'Publishing.DigestCandidatesPrepared',
		"'schema_version'",
		"'occurred_at'",
		"'recipients'",
		"'idempotency_key'",
		"'trace_id'",
		"'candidate_window'",
		'sabri_file19_digest_candidates',
	) as $needle
) {
	$check( false !== strpos( $files['integrations'], $needle ), 'Round 5: File 19 exact contract missing ' . $needle );
}
$check( false === strpos( $files['integrations'], 'File21DigestCandidatesPrepared.v1' ), 'Round 5: obsolete non-File19 event type remains.' );
$check( false !== strpos( $files['integrations'], "'owner'           => 'File 21'" ), 'Round 5: canonical producer owner is not frozen.' );
$check( false !== strpos( $files['integrations'], "'delivery_available'" ) && false !== strpos( $files['integrations'], "'ingest_status'" ), 'Round 5: File 19 unavailable/rejected state is not exposed honestly.' );

// Round 6: File 21 corrective assets are route/context conditional.
$check( false !== strpos( $files['feed'], 'assets_required_on_current_request' ) && false !== strpos( $files['feed'], 'sabri_hnf_next_generation_assets_required' ), 'Round 6: conditional asset policy missing.' );
$check( false !== strpos( $files['hardening'], 'NextGenerationFeed::assets_required_on_current_request()' ), 'Round 6: hardening assets bypass conditional policy.' );

// Round 7: File 25 remains the visual renderer; File 21 remains semantic payload owner.
$check( false !== strpos( $files['integrations'], 'sabri_file25_shareable_knowledge_card' ), 'Round 7: File 25 visual handoff missing.' );
$check( false !== strpos( $files['feed'], "'file25_rendered'" ), 'Round 7: File 25 render projection not exposed.' );

// Round 8: File 26 remains global discovery owner and File 21 only consumes versioned adapter hooks.
$check( false !== strpos( $files['integrations'], 'sabri_file26_why_trending' ) && false !== strpos( $files['integrations'], 'sabri_file26_related_knowledge' ), 'Round 8: File 26 discovery adapters missing.' );

// Round 9: state-changing NG30 action path still requires current identity assurance and nonce enforcement.
$check( false !== strpos( $files['feed'], 'CanonicalIdentityAdapter::current_action_ready' ), 'Round 9: current identity assurance missing from user action.' );
$check( false !== strpos( $files['hardening'], 'InteractionPermissions::nonce_valid' ), 'Round 9: nonce enforcement missing from protected NG30 path.' );

// Round 10: new runtime file is deterministic-package mandatory.
$check( false !== strpos( $files['builder'], 'includes/class-next-generation-privacy.php' ), 'Round 10: new privacy runtime file is not package-mandatory.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, "FAIL: {$failure}\n" );
	}
	exit( 1 );
}
echo "File 21 fresh ten-round latest-plan source gate: PASS\n";
