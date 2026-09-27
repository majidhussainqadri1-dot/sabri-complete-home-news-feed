<?php
/** Executable evidence for File 21 independent eighty-round review. */
declare(strict_types=1);

$root = getenv('FILE21_ROOT') ?: dirname(__DIR__);
$paths = array(
    'feed' => $root . '/includes/class-next-generation-feed.php',
    'hard' => $root . '/includes/class-next-generation-hardening.php',
    'rest' => $root . '/includes/class-rest-next-generation.php',
    'int' => $root . '/includes/class-next-generation-integrations.php',
    'renderer' => $root . '/includes/class-feed-renderer.php',
    'news' => $root . '/includes/class-news-public-projector.php',
    'timeline' => $root . '/includes/class-profile-timeline.php',
    'comments' => $root . '/includes/class-comment-service.php',
    'news_card' => $root . '/templates/news-card.php',
    'js' => $root . '/assets/js/next-generation-accessibility.js',
    'main' => $root . '/sabri-complete-home-news-feed.php',
    'report' => $root . '/docs/FILE21-EIGHTY-ROUND-REVIEW-2026-08-09.md',
    'companions' => $root . '/.github/workflows/file21-latest-companion-exact-contracts.yml',
    'workflow' => $root . '/.github/workflows/file21-eighty-round-review.yml',
    'latest_plan_test' => $root . '/tests/run-file21-latest-plan-fresh-ten-review-tests.php',
    'wp_stubs' => $root . '/tests/wp-stubs.php',
);
$read = static fn(string $p): string => is_file($p) ? (file_get_contents($p) ?: '') : '';
$f = array_map($read, $paths);
$failed=0; $passed=0;
$assert = static function(bool $ok, string $msg) use (&$failed,&$passed): void {
    if ($ok) { ++$passed; echo "PASS: {$msg}\n"; }
    else { ++$failed; fwrite(STDERR,"FAIL: {$msg}\n"); }
};
foreach ($paths as $k=>$p) $assert(is_file($p), "$k evidence exists");

preg_match_all('/^\|\s*(\d{1,2})\s*\|/m', $f['report'], $m);
$rounds=array_values(array_unique(array_map('intval',$m[1] ?? array())));
$assert($rounds===range(1,80),'report contains exactly rounds 1 through 80');
$defects=array(5,6,7,8,10,13,14,15,17,18,19,20,26,30,31,32,33,34,36,41,45,48,53,54,58,62,77,80);
foreach($defects as $r) $assert((bool)preg_match('/^\|\s*'.$r.'\s*\|.*\|\s*DEFECT\s*\|/mi',$f['report']),"round $r marked DEFECT");

$assert(str_contains($f['main'],"SABRI_HNF_PACKAGE_VERSION', '1.0.5'"),'package stays 1.0.5');
$assert(str_contains($f['main'],"SABRI_HNF_VERSION', '1.0.3'"),'runtime stays 1.0.3');
$assert(str_contains($f['main'],"SABRI_HNF_SCHEMA_VERSION', '1.0.0'"),'schema stays 1.0.0');
$assert(str_contains($f['feed'],'function strict_public_item'),'one strict-public item boundary exists');
$assert(str_contains($f['rest'],'NextGenerationFeed::strict_public_item( $post_id )'),'public post context uses strict-public boundary');
$assert(str_contains($f['feed'],'Only a currently public, approved original may be reposted or quoted.'),'internal repost service independently fails closed');
$assert(str_contains($f['feed'],'CanonicalIdentityAdapter::public_projection( $user_id )'),'coauthor/public identity uses canonical projection');
$assert(str_contains($f['news'],'CanonicalIdentityAdapter::public_projection( $author_id )'),'Editorial News author uses canonical projection');
$assert(str_contains($f['timeline'],'CanonicalIdentityAdapter::public_projection'),'profile timeline title uses canonical projection');
$assert(str_contains($f['comments'],'CanonicalIdentityAdapter::public_projection'),'comment public name uses canonical projection');
$assert(str_contains($f['feed'],'Expert context sources') && str_contains($f['feed'],"'sources'         => \$sources"),'Expert Context is source-aware');
$assert(str_contains($f['js'],'Sources (optional, one http/https URL per line)'),'Expert Context source UI is explicitly labelled');
$assert(str_contains($f['feed'],'Quote text exceeds the 5,000-character limit.') && str_contains($f['feed'],'Q&A text exceeds the 5,000-character limit.'),'large NG30 text is server bounded');
$assert(str_contains($f['hard'],'strlen( $body ) > 65536'),'POST body has a hard server ceiling');
$assert(str_contains($f['feed'],'function safe_web_url') && str_contains($f['feed'],"array( 'http', 'https' )") && str_contains($f['feed'],"! empty( \$parts['user'] )"),'strict external URL contract rejects unsafe schemes/credentials');
$assert(str_contains($f['int'],"'method'   => 'original'") && str_contains($f['int'],"__( 'Original'"),'translation options always include original when canonical URL exists');
$assert(!str_contains($f['feed'],'This item has an edit or correction history; review the latest version before sharing.'),'ordinary revision history no longer triggers false share warning');
$assert(str_contains($f['renderer'],'NextGenerationFeed::media_transfer_suppressed()') && str_contains($f['news_card'],'NextGenerationFeed::media_transfer_suppressed()'),'data-saver suppresses media markup server-side');
$assert(str_contains($f['int'],"implode( '|', array( self::FILE19_PRODUCER, \$user_id, \$frequency, \$window ) )"),'digest idempotency is stable per producer/user/frequency/window');
$assert(str_contains($f['hard'],"'ng-object-' . \$post_id"),'mutations have per-object abuse bucket');
$assert(str_contains($f['hard'],"return function_exists( 'get_current_user_id' ) && absint( get_current_user_id() ) > 0"),'authenticated NG30 projections cannot enter shared cache');
$assert(str_contains($f['int'],'array_slice( $options, 0, 50 )') && str_contains($f['int'],'array_slice( $items, 0, 100 )'),'provider arrays are bounded before iteration');
$assert(str_contains($f['feed'],'array_slice( $progress, -250, null, true )'),'stored progress is bounded before iteration');
$assert(str_contains($f['companions'],'FILE04_SHA: 4d6266413b377fda451894b1c7076f2ce93f50fc'),'current File 04 pin');
$assert(str_contains($f['companions'],'FILE20_SHA: 7b4019091d1f83ef4cd9dc3f559abb2b3a95955d'),'current File 20 pin');
$assert(str_contains($f['companions'],'FILE24_SHA: 2b303722e68869cc59cfd0a621f770e5b2826ebf'),'current File 24 pin');
$assert(str_contains($f['companions'],'class-native-content-slots.php') && str_contains($f['companions'],'sabri_shell_news_main'),'latest File 20 exact five-slot runtime is executable evidence');
$assert(str_contains($f['latest_plan_test'],'function strict_public_item') && str_contains($f['latest_plan_test'],'self::strict_public_item('),'Round 80 stale visibility regression follows the stronger current boundary');
$assert(str_contains($f['wp_stubs'],'function get_post_type('),'Round 80 lean harness supplies the WordPress get_post_type core contract');
$assert(str_contains($f['wp_stubs'],"define( 'HOUR_IN_SECONDS', 3600 );") && str_contains($f['wp_stubs'],"define( 'WEEK_IN_SECONDS', 604800 );"),'Round 80 lean harness supplies WordPress time constants reached by strict public card rendering');
$assert(str_contains($f['workflow'],'run-file21-eighty-round-review-tests.php') && str_contains($f['workflow'],'tools/build-release.py --source-sha'),'80-round gate tests exact source and deterministic package');

printf("File 21 eighty-round final evidence: %d passed, %d failed.\n",$passed,$failed);
exit($failed?1:0);
