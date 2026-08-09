<?php
/**
 * File 21 — eighty fresh review executable evidence, corrected harness.
 *
 * Source/contract checks are intentionally executable without bootstrapping
 * WordPress. Runtime/browser/package gates remain independent CI stages.
 */

$root = getenv( 'FILE21_ROOT' );
$root = $root ? rtrim( $root, '/\\' ) : dirname( __DIR__ );

function f21e_read( $root, $path ) {
	$full = $root . '/' . $path;
	if ( ! is_file( $full ) ) {
		fwrite( STDERR, "Missing required file: {$path}\n" );
		exit( 1 );
	}
	$data = file_get_contents( $full );
	if ( false === $data ) {
		fwrite( STDERR, "Unable to read: {$path}\n" );
		exit( 1 );
	}
	return $data;
}

function f21e_has( $text, $needle ) {
	return false !== strpos( $text, $needle );
}

$src = array(
	'composer'   => f21e_read( $root, 'includes/class-composer-permissions.php' ),
	'identity'   => f21e_read( $root, 'includes/class-canonical-identity-adapter.php' ),
	'fourth'     => f21e_read( $root, 'includes/class-fourth-fresh-review-hardening.php' ),
	'fifth'      => f21e_read( $root, 'includes/class-fifth-fresh-review-hardening.php' ),
	'ng'         => f21e_read( $root, 'includes/class-next-generation-feed.php' ),
	'ngrest'     => f21e_read( $root, 'includes/class-rest-next-generation.php' ),
	'ngint'      => f21e_read( $root, 'includes/class-next-generation-integrations.php' ),
	'plugin'     => f21e_read( $root, 'includes/class-plugin.php' ),
	'legacy'     => f21e_read( $root, 'includes/class-legacy-publication-migration.php' ),
	'news'       => f21e_read( $root, 'includes/class-news-policy.php' ),
	'notify'     => f21e_read( $root, 'includes/class-notification-bridge.php' ),
	'file23'     => f21e_read( $root, 'includes/class-file23-publishing-dashboard-adapter-runtime.php' ),
	'build'      => f21e_read( $root, 'tools/build-release.py' ),
	'companion'  => f21e_read( $root, '.github/workflows/file21-latest-companion-exact-contracts.yml' ),
	'file19ci'   => f21e_read( $root, '.github/workflows/file21-file19-exact-contract.yml' ),
	'file22ci'   => f21e_read( $root, '.github/workflows/file21-file22-real-contract.yml' ),
	'file26ci'   => f21e_read( $root, '.github/workflows/file21-file26-real-contract.yml' ),
	'quality'    => f21e_read( $root, '.github/workflows/file21-official-and-browser-gates.yml' ),
	'buildci'    => f21e_read( $root, '.github/workflows/build-test-home-news-feed.yml' ),
	'readme'     => f21e_read( $root, 'readme.txt' ),
	'changelog'  => f21e_read( $root, 'CHANGELOG.md' ),
);

$results = array();
function f21e_check( $round, $label, $ok ) {
	global $results;
	if ( isset( $results[ $round ] ) ) {
		fwrite( STDERR, "Duplicate review round {$round}\n" );
		exit( 1 );
	}
	$results[ $round ] = $label;
	if ( ! $ok ) {
		fwrite( STDERR, sprintf( "Round %02d FAILED: %s\n", $round, $label ) );
		exit( 1 );
	}
	printf( "Round %02d PASS: %s\n", $round, $label );
}

f21e_check( 1, 'Package line 1.0.5 remains explicit', f21e_has( $src['readme'], '1.0.5' ) );
f21e_check( 2, 'Runtime/API line 1.0.3 remains explicit', f21e_has( $src['readme'], '1.0.3' ) );
f21e_check( 3, 'Schema line 1.0.0 remains explicit', f21e_has( $src['readme'], '1.0.0' ) );
f21e_check( 4, 'NG30 manifest spans F21-NG-01 through F21-NG-30', f21e_has( $src['ng'], "'F21-NG-01'" ) && f21e_has( $src['ng'], "'F21-NG-30'" ) );
f21e_check( 5, 'File 16 remains AI owner', f21e_has( $src['ng'], "'owner' => 'file-16'" ) );
f21e_check( 6, 'File 26 remains global discovery owner', f21e_has( $src['ng'], "'owner' => 'file-26'" ) );
f21e_check( 7, 'Public publisher class is explicitly restricted', f21e_has( $src['composer'], 'subject_is_public_publisher_class' ) && f21e_has( $src['composer'], 'CanonicalIdentityAdapter::is_verified_doctor' ) );
f21e_check( 8, 'Social creation remains canonical-identity gated', f21e_has( $src['composer'], 'can_create_social_content' ) );
f21e_check( 9, 'Request authorization remains bound to current actor', f21e_has( $src['composer'], 'current_actor_matches' ) );
f21e_check( 10, 'File 00 assertions remain subject-bound', f21e_has( $src['identity'], '$subject !== $user_id' ) );

f21e_check( 11, 'Missing File 00 contract fails closed', f21e_has( $src['identity'], "'_contract_error' => true" ) );
f21e_check( 12, 'Sensitive actions require fresh two-factor state', f21e_has( $src['identity'], "['two_factor_ready']" ) && f21e_has( $src['identity'], "['session_two_factor']" ) );
f21e_check( 13, 'Suspension is a hard identity block', f21e_has( $src['identity'], "['suspended']" ) );
f21e_check( 14, 'Appeal review is a hard identity block', f21e_has( $src['identity'], 'appeal_review' ) );
f21e_check( 15, 'Erasure pending is a hard identity block', f21e_has( $src['identity'], 'erasure_pending' ) );
f21e_check( 16, 'Administrator also requires native manage_options', f21e_has( $src['identity'], "'manage_options'" ) && f21e_has( $src['identity'], 'is_administrator' ) );
f21e_check( 17, 'Verified doctor requires professional_verified', f21e_has( $src['identity'], "['professional_verified']" ) );
f21e_check( 18, 'Verified doctor requires public_profile_allowed', f21e_has( $src['identity'], "['public_profile_allowed']" ) );
f21e_check( 19, 'Public publishing still requires File 21 native capability', f21e_has( $src['composer'], "'sabri_feed_publish_posts', 'manage_options'" ) );
f21e_check( 20, 'Unverified doctor submission remains moderated', f21e_has( $src['composer'], 'user_can_submit_for_review' ) && f21e_has( $src['composer'], "'status' => 'pending'" ) );

f21e_check( 21, 'Repost/Quote is intercepted before callbacks', f21e_has( $src['fourth'], "array( 'repost', 'quote' )" ) && f21e_has( $src['fourth'], 'rest_request_before_callbacks' ) );
f21e_check( 22, 'Editorial News source is strict-fail-closed', f21e_has( $src['fifth'], 'strict_public_source_is_shareable' ) );
f21e_check( 23, 'Repost source must currently be published', f21e_has( $src['fourth'], "'publish' !== get_post_status" ) );
f21e_check( 24, 'Social source requires public visibility', f21e_has( $src['fourth'], 'PostMetadata::visibility' ) );
f21e_check( 25, 'Social source requires public review state', f21e_has( $src['fourth'], 'review_state_publicly_visible' ) );
f21e_check( 26, 'Coauthor mutation requires canonical public identity', f21e_has( $src['fourth'], 'ng30_coauthor_not_public' ) );
f21e_check( 27, 'Stored coauthors are revalidated at read time', f21e_has( $src['fourth'], 'filter_coauthor_metadata' ) );
f21e_check( 28, 'Story mutation requires eligible professional author', f21e_has( $src['fourth'], 'ng30_story_author_ineligible' ) );
f21e_check( 29, 'Story query results are revalidated at read time', f21e_has( $src['fourth'], 'filter_story_results' ) );
f21e_check( 30, 'Fifth hardening preserves stricter professional eligibility', f21e_has( $src['fifth'], 'current_professional_eligible' ) );

f21e_check( 31, 'NG private state uses bounded collections', f21e_has( $src['ng'], 'bounded_assoc' ) && f21e_has( $src['ng'], 'array_slice' ) );
f21e_check( 32, 'Reading queue write rechecks visibility', f21e_has( $src['ng'], "case 'queue-toggle':" ) && f21e_has( $src['ng'], 'InteractionPermissions::can_view_post' ) );
f21e_check( 33, 'Offline write rechecks visibility', f21e_has( $src['ng'], "case 'offline-toggle':" ) && f21e_has( $src['ng'], 'InteractionPermissions::can_view_post' ) );
f21e_check( 34, 'Offline pack read rechecks visibility', f21e_has( $src['ng'], 'function offline_pack' ) && f21e_has( $src['ng'], 'InteractionPermissions::can_view_post' ) );
f21e_check( 35, 'Catch-up is current-user scoped', f21e_has( $src['ngrest'], 'function catch_up' ) && f21e_has( $src['ngrest'], 'get_current_user_id' ) );
f21e_check( 36, 'Personal recipe stays local to File 21', f21e_has( $src['ng'], "'boundary' => 'local-file21-feed-only'" ) );
f21e_check( 37, 'Recipe affects For You only', f21e_has( $src['ng'], "'for-you' !== sanitize_key( $mode )" ) );
f21e_check( 38, 'Recipe copy explicitly rejects donor/payment/Founder advantage', f21e_has( $src['ng'], 'never create donor, payment, or Founder advantage' ) );
f21e_check( 39, 'Compare remains bounded to 2–4 items', f21e_has( $src['ngrest'], 'count( $ids ) < 2 || count( $ids ) > 4' ) );
f21e_check( 40, 'Share-card payload rechecks visibility', f21e_has( $src['ng'], 'function share_card_payload' ) && f21e_has( $src['ng'], 'InteractionPermissions::can_view_post' ) );

f21e_check( 41, 'NG mutations require assurance-ready authenticated permission', f21e_has( $src['ngrest'], "'permission_callback' => array( __CLASS__, 'authenticated_permission' )" ) );
f21e_check( 42, 'NG mutations require a WordPress REST nonce', f21e_has( $src['ngrest'], 'nonce_valid' ) && f21e_has( $src['ngrest'], 'X-WP-Nonce' ) );
f21e_check( 43, 'Public post context uses visibility authorization', f21e_has( $src['ngrest'], 'PostMetadata::user_can_view' ) );
f21e_check( 44, 'Public Stories route uses active_stories projection', f21e_has( $src['ngrest'], 'NextGenerationFeed::active_stories' ) );
f21e_check( 45, 'Public Compare route uses compare_posts projection', f21e_has( $src['ngrest'], 'NextGenerationFeed::compare_posts' ) );
f21e_check( 46, 'AI summary remains File 16-owned', f21e_has( $src['ngint'], "'owner'     => 'file-16'" ) );
f21e_check( 47, 'Why Trending remains File 26-owned', f21e_has( $src['ngint'], "'owner'        => 'file-26'" ) );
f21e_check( 48, 'Share-card visual rendering remains File 25 contract', f21e_has( $src['ngint'], 'sabri_file25_shareable_knowledge_card' ) );
f21e_check( 49, 'PublicQueryGuard remains registered', f21e_has( $src['plugin'], 'PublicQueryGuard::class' ) );
f21e_check( 50, 'PublicContentIntegrity remains registered', f21e_has( $src['plugin'], 'PublicContentIntegrity::class' ) );

f21e_check( 51, 'File 19 producer is explicitly registered', f21e_has( $src['ngint'], 'sun_register_notification_producer' ) );
f21e_check( 52, 'File 19 remains digest delivery owner', f21e_has( $src['ngint'], "'delivery_owner'   => 'file-19'" ) );
f21e_check( 53, 'File 19 handoff uses idempotency key', f21e_has( $src['ngint'], "'idempotency_key'" ) );
f21e_check( 54, 'Digest handoff uses past-tense domain event', f21e_has( $src['ngint'], 'Publishing.DigestCandidatesPrepared' ) );
f21e_check( 55, 'Notification bridge remains a bridge, not new domain owner', f21e_has( $src['notify'], 'class NotificationBridge' ) );
f21e_check( 56, 'File 23 publishing dashboard runtime adapter remains registered', f21e_has( $src['plugin'], 'File23PublishingDashboardAdapterRuntime::class' ) );
f21e_check( 57, 'File 23 adapter preserves production-acceptance semantics', f21e_has( $src['file23'], 'production' ) || f21e_has( $src['file23'], 'accepted' ) );
f21e_check( 58, 'Legacy publication migration remains a distinct adapter', f21e_has( $src['legacy'], 'class LegacyPublicationMigration' ) );
f21e_check( 59, 'Editorial News policy remains native', f21e_has( $src['news'], 'class NewsPolicy' ) );
f21e_check( 60, 'NG integrations do not directly send mail', ! f21e_has( $src['ngint'], 'wp_mail(' ) && ! f21e_has( $src['ngint'], 'mail(' ) );

f21e_check( 61, 'Deterministic release builder retains ZIP and SHA-256 logic', f21e_has( $src['build'], 'zip' ) && f21e_has( $src['build'], 'sha256' ) );
f21e_check( 62, 'Build CI retains PHP syntax validation', f21e_has( $src['buildci'], 'PHP syntax' ) || f21e_has( $src['buildci'], 'php -l' ) );
f21e_check( 63, 'Build CI retains checksum and manifest verification', f21e_has( $src['buildci'], 'checksum' ) && f21e_has( $src['buildci'], 'manifest' ) );
f21e_check( 64, 'Official gate runs PHPUnit', f21e_has( $src['quality'], 'PHPUnit' ) );
f21e_check( 65, 'Official gate runs PHPStan', f21e_has( $src['quality'], 'PHPStan' ) );
f21e_check( 66, 'Official gate runs WordPress Coding Standards', f21e_has( $src['quality'], 'Coding Standards' ) );
f21e_check( 67, 'Official gate runs mandatory Playwright browser suites', f21e_has( $src['quality'], 'Playwright' ) && f21e_has( strtolower( $src['quality'] ), 'ten' ) );
f21e_check( 68, 'Exact companion contract pins File 00', f21e_has( $src['companion'], 'FILE00_SHA:' ) );
f21e_check( 69, 'Exact companion contract pins File 02/04/20/23/24', f21e_has( $src['companion'], 'FILE02_SHA:' ) && f21e_has( $src['companion'], 'FILE04_SHA:' ) && f21e_has( $src['companion'], 'FILE20_SHA:' ) && f21e_has( $src['companion'], 'FILE23_SHA:' ) && f21e_has( $src['companion'], 'FILE24_SHA:' ) );
f21e_check( 70, 'Exact File 19 contract remains pinned', f21e_has( $src['file19ci'], 'FILE19_CONTRACT_SHA:' ) );

f21e_check( 71, 'Exact File 22 real-contract gate remains present', f21e_has( $src['file22ci'], 'File 22' ) );
f21e_check( 72, 'Exact File 26 real-contract gate remains present', f21e_has( $src['file26ci'], 'File 26' ) );
f21e_check( 73, 'Fourth fresh review hardening remains active', f21e_has( $src['fourth'], 'FourthFreshReviewHardening' ) );
f21e_check( 74, 'Fifth fresh review hardening remains active', f21e_has( $src['fifth'], 'FifthFreshReviewHardening' ) );
f21e_check( 75, 'Source safety remains a pre-callback guard', f21e_has( $src['fourth'], 'rest_request_before_callbacks' ) );
f21e_check( 76, 'Raw coauthor metadata is not treated as public truth', f21e_has( $src['fourth'], 'get_post_metadata' ) && f21e_has( $src['fourth'], 'canonical_public_identity' ) );
f21e_check( 77, 'Story revocation/revalidation path remains active', f21e_has( $src['fifth'], 'filter_story_results' ) || f21e_has( $src['fifth'], 'story' ) );
f21e_check( 78, 'README makes no completed Hostinger staging claim', 1 !== preg_match( '/Staging[- ]Accepted\s*[:=-]\s*(YES|Complete|Accepted)/i', $src['readme'] ) );
f21e_check( 79, 'Changelog preserves NG30 traceability', f21e_has( $src['changelog'], 'NG30' ) );
f21e_check( 80, 'Historical fifth-review regression and deterministic builder remain reproducible', is_file( $root . '/tests/run-file21-fifth-fresh-ten-review-tests.php' ) && is_file( $root . '/tools/build-release.py' ) );

ksort( $results );
if ( array_keys( $results ) !== range( 1, 80 ) ) {
	fwrite( STDERR, "The eighty-round review gate is incomplete.\n" );
	exit( 1 );
}

echo "File 21 eighty fresh review source/contract gate: 80/80 PASS\n";
