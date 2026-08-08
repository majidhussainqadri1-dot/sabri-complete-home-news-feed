<?php
/**
 * Exact contract test for the Founder-approved File 21 next-generation 30-feature release.
 *
 * @package SabriCompleteHomeNewsFeed
 */

$root = getenv( 'FILE21_ROOT' );
$root = $root ? rtrim( $root, '/\\' ) : dirname( __DIR__ );
$failures = array();

$read = static function ( $relative ) use ( $root, &$failures ) {
	$path = $root . '/' . ltrim( $relative, '/' );
	if ( ! is_file( $path ) ) {
		$failures[] = 'Missing file: ' . $relative;
		return '';
	}
	$contents = file_get_contents( $path );
	if ( false === $contents ) {
		$failures[] = 'Unreadable file: ' . $relative;
		return '';
	}
	return $contents;
};

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$feed         = $read( 'includes/class-next-generation-feed.php' );
$integrations = $read( 'includes/class-next-generation-integrations.php' );
$rest         = $read( 'includes/class-rest-next-generation.php' );
$plugin       = $read( 'includes/class-plugin.php' );
$bootstrap    = $read( 'sabri-complete-home-news-feed.php' );
$feed_tpl     = $read( 'templates/feed.php' );
$card_tpl     = $read( 'templates/feed-card.php' );
$js           = $read( 'assets/js/next-generation.js' );
$css          = $read( 'assets/css/next-generation.css' );
$builder      = $read( 'tools/build-release.py' );

preg_match_all( "/'F21-NG-(\\d{2})'\\s*=>/", $feed, $matches );
$ids = isset( $matches[1] ) ? array_values( array_unique( $matches[1] ) ) : array();
sort( $ids );
$expected_ids = array_map( static function ( $number ) { return str_pad( (string) $number, 2, '0', STR_PAD_LEFT ); }, range( 1, 30 ) );
$assert( $expected_ids === $ids, 'Feature manifest must contain exactly F21-NG-01 through F21-NG-30 once each.' );

$features = array(
	'repost', 'quote-post', 'post-threads', 'coauthored-posts', 'professional-stories',
	'developing-story-timeline', 'expert-context', 'evidence-card', 'source-diversity',
	'edit-correction-history', 'smart-share-warning', 'ai-summary', 'ask-this-article',
	'intelligent-translation', 'follow-topics', 'my-topics-feed', 'catch-up', 'continue-reading',
	'reading-queue', 'low-bandwidth', 'offline-feed-pack', 'data-saver', 'doctor-answer-badge',
	'structured-qna', 'why-trending', 'knowledge-graph-cards', 'news-compare',
	'shareable-knowledge-cards', 'personal-feed-recipe', 'knowledge-digest',
);
foreach ( $features as $feature ) {
	$assert( false !== strpos( $feed, "'slug' => '" . $feature . "'" ), 'Manifest is missing feature slug: ' . $feature );
}

$owner_expectations = array(
	'F21-NG-12' => 'file-16',
	'F21-NG-13' => 'file-16',
	'F21-NG-14' => 'file-16',
	'F21-NG-25' => 'file-26',
	'F21-NG-26' => 'file-26',
	'F21-NG-28' => 'file-25',
	'F21-NG-30' => 'file-19',
);
foreach ( $owner_expectations as $id => $owner ) {
	$pattern = "/'" . preg_quote( $id, '/' ) . "'\\s*=>\\s*array\\([^\\n]+?'owner'\\s*=>\\s*'" . preg_quote( $owner, '/' ) . "'/";
	$assert( 1 === preg_match( $pattern, $feed ), $id . ' must preserve canonical owner ' . $owner . '.' );
}

$assert( false !== strpos( $feed, 'ComposerPermissions::user_can_create' ), 'Repost/Quote creation must use File 21 canonical creation permissions.' );
$assert( false !== strpos( $feed, 'META_ORIGINAL_ID' ), 'Repost/Quote must retain an original source reference.' );
$assert( false !== strpos( $feed, "'repost' : 'quote'" ) || ( false !== strpos( $feed, "'repost'" ) && false !== strpos( $feed, "'quote'" ) ), 'Repost and Quote variants must both be implemented.' );
$assert( false !== strpos( $feed, 'META_THREAD_ID' ) && false !== strpos( $feed, 'META_THREAD_ORDER' ), 'Thread/Series metadata and ordering must exist.' );
$assert( false !== strpos( $feed, 'META_COAUTHORS' ), 'Co-authored post metadata must exist.' );
$assert( false !== strpos( $feed, 'DAY_IN_SECONDS' ) && false !== strpos( $feed, 'META_STORY_EXPIRES' ), 'Professional Stories must use bounded 24-hour expiry semantics.' );
$assert( false !== strpos( $feed, 'CanonicalIdentityAdapter::is_verified_doctor' ) && false !== strpos( $feed, 'verified_expert_required' ), 'Expert Context must require canonical verified professional truth or moderation authority.' );
$assert( false !== strpos( $feed, 'Verified Doctor Response' ), 'Structured Q&A must expose the Verified Doctor Response badge.' );
$assert( false !== strpos( $feed, 'wp_get_post_revisions' ) && false !== strpos( $feed, 'CorrectionLedger::public_history' ), 'Edit/correction history must combine revisions and canonical correction history.' );
$assert( false !== strpos( $feed, 'share_warning' ) && false !== strpos( $feed, 'retracted' ), 'Smart Share Warning must account for correction/retraction state.' );

$assert( false !== strpos( $integrations, 'sabri_file16_article_summary' ), 'AI summary must use the File 16 adapter contract.' );
$assert( false !== strpos( $integrations, 'sabri_file16_ask_article_contract' ), 'Ask This Article must use the File 16 adapter contract.' );
$assert( false !== strpos( $integrations, 'sabri_file16_translation_options' ), 'Translation must use the File 16/service adapter contract.' );
$assert( false !== strpos( $integrations, 'sabri_file26_why_trending' ), 'Why Trending must use the File 26 adapter contract.' );
$assert( false !== strpos( $integrations, 'sabri_file26_related_knowledge' ), 'Related Knowledge must use the File 26 adapter contract.' );
$assert( false !== strpos( $integrations, 'sabri_file25_shareable_knowledge_card' ), 'Shareable Knowledge Cards must use the File 25 visual contract.' );
$assert( false !== strpos( $integrations, 'sabri_file19_digest_candidates' ), 'Knowledge Digest must hand candidates to File 19 delivery.' );

$routes = array(
	'/next-generation/manifest', '/next-generation/post/', '/next-generation/action',
	'/next-generation/my-topics', '/next-generation/catch-up', '/next-generation/stories',
	'/next-generation/offline-pack', '/next-generation/compare', '/next-generation/share-card/',
	'/next-generation/digest',
);
foreach ( $routes as $route ) {
	$assert( false !== strpos( $rest, $route ), 'REST contract missing: ' . $route );
}
$assert( false !== strpos( $rest, 'nonce_valid' ) && false !== strpos( $rest, 'CanonicalIdentityAdapter::current_action_ready' ), 'Next-generation mutations must retain nonce and current identity assurance.' );

$assert( false !== strpos( $feed_tpl, 'NextGenerationFeed::render_feed_tools' ), 'Home must mount supplemental next-generation tools.' );
$assert( false !== strpos( $card_tpl, 'NextGenerationFeed::render_card_extensions' ), 'Feed cards must render next-generation context.' );
$assert( false !== strpos( $card_tpl, 'follow-topic' ) && false !== strpos( $card_tpl, 'unfollow-topic' ), 'Feed cards must expose Follow/Unfollow Topic controls.' );
$assert( false !== strpos( $js, "request('progress'" ) && false !== strpos( $js, 'queue-toggle' ) && false !== strpos( $js, 'offline-toggle' ), 'Reading progress, Read Later and Offline Pack client actions must be wired.' );
$assert( false !== strpos( $css, 'min-height: 44px' ), 'Next-generation controls must preserve 44px minimum target sizing.' );
$assert( false !== strpos( $css, '.sabri-hnf-low-bandwidth' ) && false !== strpos( $css, '.sabri-hnf-data-saver' ), 'Low-Bandwidth and Data Saver presentation modes must ship.' );

$assert( false !== strpos( $plugin, 'NextGenerationIntegrations::class' ) && false !== strpos( $plugin, 'NextGenerationFeed::class' ) && false !== strpos( $plugin, 'RestNextGeneration::class' ), 'Plugin coordinator must register all next-generation runtime modules.' );
$assert( false !== strpos( $bootstrap, "SABRI_HNF_PACKAGE_VERSION', '1.1.0'" ), 'Package identity must be 1.1.0.' );
$assert( false !== strpos( $bootstrap, "SABRI_HNF_VERSION', '1.0.3'" ), 'Stable File 21 runtime/API contract must remain 1.0.3.' );
$assert( false !== strpos( $bootstrap, "SABRI_HNF_SCHEMA_VERSION', '1.0.0'" ), 'Database schema must remain 1.0.0.' );
$assert( false === stripos( $feed, 'CREATE TABLE' ) && false === stripos( $integrations, 'CREATE TABLE' ) && false === stripos( $rest, 'CREATE TABLE' ), 'The next-generation release must not introduce an undeclared database table/schema migration.' );
$assert( false !== strpos( $builder, 'PACKAGE_VERSION = "1.1.0"' ) && false !== strpos( $builder, 'RUNTIME_VERSION = "1.0.3"' ), 'Deterministic builder must match package 1.1.0 and stable runtime 1.0.3.' );
foreach ( array( 'class-next-generation-feed.php', 'class-next-generation-integrations.php', 'class-rest-next-generation.php', 'next-generation.js', 'next-generation.css' ) as $required ) {
	$assert( false !== strpos( $builder, $required ), 'Deterministic package must require ' . $required . '.' );
}

if ( $failures ) {
	fwrite( STDERR, "File 21 next-generation 30-feature contract failures:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "File 21 next-generation 30-feature contract: PASS\n";
