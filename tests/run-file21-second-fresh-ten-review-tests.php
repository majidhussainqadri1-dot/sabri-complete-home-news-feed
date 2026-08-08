<?php
/** Executable source gate for the second independent fresh ten-round File 21 review. */
$root = getenv( 'FILE21_ROOT' ) ?: dirname( __DIR__ );
$files = array(
	'bootstrap'    => file_get_contents( $root . '/sabri-complete-home-news-feed.php' ),
	'readme'       => file_get_contents( $root . '/readme.txt' ),
	'plugin'       => file_get_contents( $root . '/includes/class-plugin.php' ),
	'identity'     => file_get_contents( $root . '/includes/class-canonical-identity-adapter.php' ),
	'composer'     => file_get_contents( $root . '/includes/class-composer-permissions.php' ),
	'privacy'      => file_get_contents( $root . '/includes/class-next-generation-privacy.php' ),
	'uninstall'    => file_get_contents( $root . '/uninstall.php' ),
	'home'         => file_get_contents( $root . '/includes/class-home-composition-registry.php' ),
	'news_policy'  => file_get_contents( $root . '/includes/class-news-policy.php' ),
	'integrations' => file_get_contents( $root . '/includes/class-next-generation-integrations.php' ),
	'hardening1'   => file_get_contents( $root . '/includes/class-next-generation-hardening.php' ),
	'hardening2'   => file_get_contents( $root . '/includes/class-second-fresh-review-hardening.php' ),
	'css'          => file_get_contents( $root . '/assets/css/next-generation.css' ),
	'builder'      => file_get_contents( $root . '/tools/build-release.py' ),
);
$failures = array();
$check = static function ( $ok, $message ) use ( &$failures ) {
	if ( ! $ok ) {
		$failures[] = $message;
	}
};

// Round 1: release identity and canonical ownership remain aligned without a version/schema drift.
foreach ( array( '* Version: 1.0.5', "SABRI_HNF_PACKAGE_VERSION', '1.0.5", "SABRI_HNF_VERSION', '1.0.3", "SABRI_HNF_SCHEMA_VERSION', '1.0.0" ) as $needle ) {
	$check( false !== strpos( $files['bootstrap'], $needle ), 'Round 1: release identity drift: ' . $needle );
}
$check( false !== strpos( $files['readme'], 'Stable tag: 1.0.5' ), 'Round 1: readme stable tag drift.' );
$check( false !== strpos( $files['readme'], 'File 26 the canonical federated Search/Discovery/Recommendations/Ranking layer' ), 'Round 1: File 26 ownership statement drift.' );

// Round 2: current File 00 subject binding and current-session assurance stay fail-closed.
$check( false !== strpos( $files['identity'], 'SMC_Contracts::assertions' ), 'Round 2: File 00 assertion contract missing.' );
$check( false !== strpos( $files['identity'], "['two_factor_ready']" ) && false !== strpos( $files['identity'], "['session_two_factor']" ), 'Round 2: current-session assurance missing.' );
$check( false !== strpos( $files['composer'], 'current_actor_matches' ) && false !== strpos( $files['composer'], 'current_action_ready' ), 'Round 2: actor binding/current assurance missing from Composer policy.' );

// Round 3: mixed post/news visibility and the full NG30 private-state lifecycle are corrected.
$check( false !== strpos( $files['hardening2'], 'post_context_response' ) && false !== strpos( $files['hardening2'], 'InteractionPermissions::can_view_post( $post_id )' ), 'Round 3: canonical cross-domain post-context visibility override missing.' );
$metadata_pos = strpos( $files['privacy'], "metadata_exists( 'user'" );
$userstate_pos = strpos( $files['privacy'], 'NextGenerationFeed::user_state' );
$check( false !== $metadata_pos && false !== $userstate_pos && $metadata_pos < $userstate_pos, 'Round 3: privacy exporter may synthesize unstored defaults.' );
$check( false !== strpos( $files['uninstall'], "delete_metadata( 'user', 0, '_sabri_hnf_ng_user_v1', '', true )" ), 'Round 3: destructive uninstall does not erase File 21 NG30 private user state.' );
$check( false !== strpos( $files['hardening1'], "'Cache-Control', 'no-store, private, max-age=0'" ), 'Round 3: private NG30 REST no-store guard missing.' );

// Round 4: the governing fourteen Home controls and ten rows are restored at the final hook priority.
$check( false !== strpos( $files['hardening2'], 'freeze_home_controls' ) && false !== strpos( $files['hardening2'], 'PHP_INT_MAX' ), 'Round 4: final frozen Home-control guard missing.' );
$check( false !== strpos( $files['hardening2'], 'freeze_home_rows' ), 'Round 4: final frozen Home-row guard missing.' );
foreach ( array( 'for-you', 'most-viral', 'latest', 'founder-updates', 'doctors-posts', 'classical-homeopathy', 'remedies', 'diseases', 'clinical-cases', 'videos', 'reels', 'pdf-books', 'clinics', 'marketplace' ) as $control ) {
	$check( false !== strpos( $files['hardening2'], "'{$control}'" ), 'Round 4: frozen control missing: ' . $control );
}
foreach ( array( 'most-viral-now', 'latest-news', 'from-founder', 'from-verified-doctors', 'learn-classical-homeopathy', 'videos', 'reels', 'pdf-books', 'clinics', 'marketplace' ) as $row ) {
	$check( false !== strpos( $files['hardening2'], "'{$row}'" ), 'Round 4: frozen Home row missing: ' . $row );
}

// Round 5: Editorial News retains its own gated lifecycle, public states and retraction policy.
$check( false !== strpos( $files['news_policy'], 'public_archive_states' ) && false !== strpos( $files['news_policy'], "'retracted' === \$state" ), 'Round 5: Editorial News public/retraction lifecycle drift.' );
$check( false !== strpos( $files['news_policy'], "current_user_can( 'edit_editorial_news', \$post_id )" ), 'Round 5: Editorial News object authorization missing.' );

// Round 6: co-authors and 24-hour professional Stories now enforce current canonical authority.
$check( false !== strpos( $files['hardening2'], 'sanitize_post_meta__sabri_hnf_ng_coauthors' ) && false !== strpos( $files['hardening2'], 'CanonicalIdentityAdapter::public_projection' ), 'Round 6: co-author canonical-identity guard missing.' );
$check( false !== strpos( $files['hardening2'], 'sanitize_post_meta__sabri_hnf_ng_story_expires' ) && false !== strpos( $files['hardening2'], 'ComposerPermissions::user_is_privileged_publisher' ), 'Round 6: professional Story authority guard missing.' );
$check( false !== strpos( $files['hardening2'], 'time() + DAY_IN_SECONDS' ), 'Round 6: 24-hour Story maximum missing.' );

// Round 7: File 16/19/25/26 remain adapters; File 19 exact ingestion stays canonical.
foreach ( array( 'sabri_file16_article_summary', 'sabri_file16_ask_article_contract', 'sabri_file25_shareable_knowledge_card', 'sabri_file26_why_trending', 'sabri_file26_related_knowledge', 'sun_register_notification_producer', 'sun_ingest_domain_event' ) as $needle ) {
	$check( false !== strpos( $files['integrations'], $needle ), 'Round 7: cross-owner adapter drift: ' . $needle );
}

// Round 8: the mutation endpoint now has explicit body and free-text bounds in addition to nonce/rate limits.
$check( false !== strpos( $files['hardening2'], 'MAX_ACTION_BODY_BYTES = 32768' ) && false !== strpos( $files['hardening2'], "'request_too_large'" ), 'Round 8: mutation body-size bound missing.' );
$check( false !== strpos( $files['hardening2'], 'MAX_ACTION_TEXT_CHARS = 5000' ) && false !== strpos( $files['hardening2'], "'text_too_large'" ), 'Round 8: mutation free-text bound missing.' );
$check( false !== strpos( $files['hardening1'], 'ng-action' ) && false !== strpos( $files['hardening1'], 'InteractionPermissions::nonce_valid' ), 'Round 8: existing rate/nonce guard regressed.' );

// Round 9: accessibility, reduced-motion and request-scoped asset behavior remain intact.
$check( false !== strpos( $files['css'], 'min-height: 44px' ), 'Round 9: 44px interaction target contract missing.' );
$check( false !== strpos( $files['css'], '@media (prefers-reduced-motion: reduce)' ), 'Round 9: reduced-motion contract missing.' );
$check( false !== strpos( $files['hardening1'], 'NextGenerationFeed::assets_required_on_current_request()' ), 'Round 9: context-scoped corrective assets regressed.' );

// Round 10: this second review is a permanent runtime + source/package gate, not a one-off report.
$check( false !== strpos( $files['plugin'], 'SecondFreshReviewHardening::class' ), 'Round 10: second-review hardening runtime is not registered.' );
$check( is_file( $root . '/includes/class-second-fresh-review-hardening.php' ), 'Round 10: hardening runtime source missing.' );
$check( false !== strpos( $files['builder'], 'collect_payload' ), 'Round 10: deterministic package builder missing.' );

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, "FAIL: {$failure}\n" );
	}
	exit( 1 );
}

echo "File 21 second fresh ten-round review source gate: PASS\n";
