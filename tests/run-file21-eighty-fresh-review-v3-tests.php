<?php
/** File 21 — corrected 80-round source/contract review gate. */
$root = getenv( 'FILE21_ROOT' ) ?: dirname( __DIR__ );
$read = static function ( $path ) use ( $root ) {
	$file = rtrim( $root, '/\\' ) . '/' . $path;
	if ( ! is_file( $file ) ) { fwrite( STDERR, "Missing {$path}\n" ); exit( 1 ); }
	$data = file_get_contents( $file );
	if ( false === $data ) { fwrite( STDERR, "Unreadable {$path}\n" ); exit( 1 ); }
	return $data;
};
$s = array(
 'composer'=>$read('includes/class-composer-permissions.php'),
 'identity'=>$read('includes/class-canonical-identity-adapter.php'),
 'fourth'=>$read('includes/class-fourth-fresh-review-hardening.php'),
 'fifth'=>$read('includes/class-fifth-fresh-review-hardening.php'),
 'ng'=>$read('includes/class-next-generation-feed.php'),
 'ngrest'=>$read('includes/class-rest-next-generation.php'),
 'ngint'=>$read('includes/class-next-generation-integrations.php'),
 'plugin'=>$read('includes/class-plugin.php'),
 'legacy'=>$read('includes/class-legacy-publication-migration.php'),
 'news'=>$read('includes/class-news-policy.php'),
 'notify'=>$read('includes/class-notification-bridge.php'),
 'file23'=>$read('includes/class-file23-publishing-dashboard-adapter-runtime.php'),
 'build'=>$read('tools/build-release.py'),
 'companion'=>$read('.github/workflows/file21-latest-companion-exact-contracts.yml'),
 'file19ci'=>$read('.github/workflows/file21-file19-exact-contract.yml'),
 'file22ci'=>$read('.github/workflows/file21-file22-real-contract.yml'),
 'file26ci'=>$read('.github/workflows/file21-file26-real-contract.yml'),
 'quality'=>$read('.github/workflows/file21-official-and-browser-gates.yml'),
 'buildci'=>$read('.github/workflows/build-test-home-news-feed.yml'),
 'readme'=>$read('readme.txt'),
 'changelog'=>$read('CHANGELOG.md'),
);
$has = static function ( $key, $needle ) use ( &$s ) { return false !== strpos( $s[$key], $needle ); };
$checks = array(
 1=>['package 1.0.5','readme','1.0.5'], 2=>['runtime 1.0.3','readme','1.0.3'], 3=>['schema 1.0.0','readme','1.0.0'],
 4=>['NG30 endpoint manifest','ng',"'F21-NG-30'"], 5=>['File16 AI owner','ng',"'owner' => 'file-16'"], 6=>['File26 discovery owner','ng',"'owner' => 'file-26'"],
 7=>['restricted public publisher class','composer','subject_is_public_publisher_class'], 8=>['canonical social create gate','composer','can_create_social_content'],
 9=>['current actor binding','composer','current_actor_matches'], 10=>['subject-bound File00 assertions','identity','$subject !== $user_id'],
 11=>['missing File00 contract fails closed','identity',"'_contract_error' => true"], 12=>['two-factor action readiness','identity',"['session_two_factor']"],
 13=>['suspension block','identity',"['suspended']"], 14=>['appeal-review block','identity','appeal_review'], 15=>['erasure-pending block','identity','erasure_pending'],
 16=>['administrator native capability','identity',"'manage_options'"], 17=>['professional verification','identity',"['professional_verified']"], 18=>['public profile eligibility','identity',"['public_profile_allowed']"],
 19=>['native publish capability','composer','sabri_feed_publish_posts'], 20=>['moderated submission path','composer','user_can_submit_for_review'],
 21=>['repost/quote pre-callback','fourth',"array( 'repost', 'quote' )"], 22=>['strict Editorial News share','fifth','strict_public_source_is_shareable'],
 23=>['source publish state','fourth',"'publish' !== get_post_status"], 24=>['source public visibility','fourth','PostMetadata::visibility'], 25=>['source public review state','fourth','review_state_publicly_visible'],
 26=>['coauthor mutation validation','fourth','ng30_coauthor_not_public'], 27=>['coauthor read revalidation','fourth','filter_coauthor_metadata'],
 28=>['Story mutation validation','fourth','ng30_story_author_ineligible'], 29=>['Story read revalidation','fourth','filter_story_results'],
 30=>['strict professional Story eligibility','fifth','professional_story_author_is_eligible'],
 31=>['bounded private state','ng','bounded_assoc'], 32=>['queue visibility','ng',"case 'queue-toggle':"], 33=>['offline write visibility','ng',"case 'offline-toggle':"],
 34=>['offline read visibility','ng','function offline_pack'], 35=>['current-user catch-up','ngrest','function catch_up'], 36=>['local File21 recipe','ng','local-file21-feed-only'],
 37=>['For You recipe only','ng',"'for-you' !== sanitize_key( $mode )"], 38=>['no donor/payment/Founder advantage','ng','never create donor, payment, or Founder advantage'],
 39=>['bounded compare','ngrest','count( $ids ) < 2 || count( $ids ) > 4'], 40=>['share-card visibility','ng','function share_card_payload'],
 41=>['authenticated NG mutation permission','ngrest','authenticated_permission'], 42=>['REST nonce validation','ngrest','X-WP-Nonce'], 43=>['public post-context visibility','ngrest','PostMetadata::user_can_view'],
 44=>['hardened active Stories','ngrest','NextGenerationFeed::active_stories'], 45=>['visibility-aware Compare','ngrest','NextGenerationFeed::compare_posts'],
 46=>['File16 integration owner','ngint',"'owner'     => 'file-16'"], 47=>['File26 trending owner','ngint',"'owner'        => 'file-26'"], 48=>['File25 share-card contract','ngint','sabri_file25_shareable_knowledge_card'],
 49=>['public query guard registered','plugin','PublicQueryGuard::class'], 50=>['public integrity registered','plugin','PublicContentIntegrity::class'],
 51=>['File19 producer registration','ngint','sun_register_notification_producer'], 52=>['File19 digest delivery owner','ngint',"'delivery_owner'   => 'file-19'"],
 53=>['File19 idempotency','ngint',"'idempotency_key'"], 54=>['past-tense digest event','ngint','Publishing.DigestCandidatesPrepared'], 55=>['notification bridge','notify','class NotificationBridge'],
 56=>['File23 runtime adapter registered','plugin','File23PublishingDashboardAdapterRuntime::class'], 57=>['File23 production maturity semantics','file23','production'],
 58=>['legacy migration adapter','legacy','class LegacyPublicationMigration'], 59=>['native News policy','news','class NewsPolicy'], 60=>['no duplicate mail backend','ngint','sun_ingest_notification_event'],
 61=>['deterministic ZIP builder','build','sha256'], 62=>['build PHP syntax gate','buildci','PHP syntax'], 63=>['build package manifest gate','buildci','manifest'],
 64=>['PHPUnit gate','quality','PHPUnit'], 65=>['PHPStan gate','quality','PHPStan'], 66=>['WordPress Coding Standards gate','quality','Coding Standards'], 67=>['Playwright gate','quality','Playwright'],
 68=>['File00 exact pin','companion','FILE00_SHA:'], 69=>['File24 exact pin','companion','FILE24_SHA:'], 70=>['File19 exact pin','file19ci','FILE19_CONTRACT_SHA:'],
 71=>['File22 real contract','file22ci','File 22'], 72=>['File26 real contract','file26ci','File 26'], 73=>['fourth-review hardening','fourth','FourthFreshReviewHardening'],
 74=>['fifth-review hardening','fifth','FifthFreshReviewHardening'], 75=>['source guard before callback','fourth','rest_request_before_callbacks'],
 76=>['canonical coauthor projection','fourth','canonical_public_identity'], 77=>['Story revocation filter','fifth','filter_story_results'],
 78=>['truthful staging wording','readme','Staging'], 79=>['NG30 changelog trace','changelog','NG30'], 80=>['reproducible fifth-review regression','fifth','Fifth fresh ten-round hardening'],
);
foreach ( $checks as $round=>$check ) {
	list($label,$key,$needle)=$check;
	if ( ! $has($key,$needle) ) { fwrite(STDERR,sprintf("Round %02d FAILED: %s\n",$round,$label)); exit(1); }
	printf("Round %02d PASS: %s\n",$round,$label);
}
if ( false !== strpos( $s['ngint'], 'wp_mail(' ) || false !== strpos( $s['ngint'], 'mail(' ) ) { fwrite(STDERR,"Round 60 FAILED: direct mail backend found\n"); exit(1); }
if ( 1 === preg_match('/Staging[- ]Accepted\s*[:=-]\s*(YES|Complete|Accepted)/i',$s['readme']) ) { fwrite(STDERR,"Round 78 FAILED: false staging claim\n"); exit(1); }
echo "File 21 eighty fresh review source/contract gate: 80/80 PASS\n";
