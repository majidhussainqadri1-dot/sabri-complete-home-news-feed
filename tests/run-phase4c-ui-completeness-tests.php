<?php
/** Phase 4C source-level completeness, accessibility, privacy, and architecture assertions. */

$root = dirname( __DIR__ );
$failures = array();
$read = static function ( $path ) use ( $root, &$failures ) {
	$file = $root . '/' . $path;
	if ( ! is_readable( $file ) ) {
		$failures[] = 'Missing required file: ' . $path;
		return '';
	}
	return (string) file_get_contents( $file );
};
$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) { $failures[] = $message; }
};

$required = array(
	'includes/class-news-public-snapshot.php',
	'includes/class-news-cache.php',
	'includes/class-news-public-projector.php',
	'includes/class-news-query-service.php',
	'includes/class-news-feed-integration.php',
	'includes/class-rest-news.php',
	'public/class-news-routing.php',
	'public/class-news-public-runtime.php',
	'templates/news-home.php',
	'templates/news-archive.php',
	'templates/news-single.php',
	'templates/news-card.php',
	'templates/news-correction-notice.php',
	'templates/news-retraction-notice.php',
	'templates/news-empty-state.php',
	'assets/css/news.css',
	'assets/js/news.js',
	'tests/run-phase4c-public-news-tests.php',
	'tests/run-phase4c-security-tests.php',
	'tests/run-phase4c-playground-tests.mjs',
	'.github/workflows/phase4c-public-news-tests.yml',
	'.github/workflows/phase4c-one-hour-visible-qa.yml',
);
foreach ( $required as $path ) { $read( $path ); }

$snapshot = $read( 'includes/class-news-public-snapshot.php' );
$projector = $read( 'includes/class-news-public-projector.php' );
$query = $read( 'includes/class-news-query-service.php' );
$rest = $read( 'includes/class-rest-news.php' );
$routing = $read( 'public/class-news-routing.php' );
$runtime = $read( 'public/class-news-public-runtime.php' );
$feed = $read( 'includes/class-news-feed-integration.php' );
$assets = $read( 'includes/class-assets.php' );
$interaction = $read( 'includes/class-interaction-permissions.php' );
$social = $read( 'includes/class-social-runtime.php' );
$renderer = $read( 'includes/class-feed-renderer.php' );
$plugin = $read( 'includes/class-plugin.php' );
$bootstrap = $read( 'sabri-complete-home-news-feed.php' );
$css = $read( 'assets/css/news.css' );
$js = $read( 'assets/js/news.js' );
$home = $read( 'templates/news-home.php' );
$archive = $read( 'templates/news-archive.php' );
$single = $read( 'templates/news-single.php' );
$card = $read( 'templates/news-card.php' );
$correction = $read( 'templates/news-correction-notice.php' );
$retraction = $read( 'templates/news-retraction-notice.php' );
$workflow = $read( '.github/workflows/phase4c-one-hour-visible-qa.yml' );

$assert( false !== strpos( $bootstrap, "SABRI_HNF_PATH . 'public'" ), 'Autoloader must include the public controller directory.' );
foreach ( array( 'NewsPublicSnapshot::class', 'NewsCache::class', 'NewsPublicProjector::class', 'NewsQueryService::class', 'NewsFeedIntegration::class', 'NewsRouting::class', 'NewsPublicRuntime::class', 'RestNews::class' ) as $module ) {
	$assert( false !== strpos( $plugin, $module ), 'Plugin coordinator is missing: ' . $module );
}
$assert( false !== strpos( $snapshot, 'SNAPSHOT_META' ) && false !== strpos( $snapshot, 'PENDING_META' ) && false !== strpos( $snapshot, 'promote_pending' ), 'Last-approved public snapshot and private pending-correction boundary are incomplete.' );
$assert( false !== strpos( $projector, 'NewsPublicSnapshot::article' ) && false !== strpos( $projector, 'NewsPublicSnapshot::card' ), 'Correction-pending projections must read the approved snapshot.' );
$assert( false !== strpos( $projector, "'body_html'         => ''" ), 'Retraction projection must explicitly hide the original body.' );
$assert( false === strpos( $projector, "'_sabri_news_private_note'" ), 'Private editorial notes must not be named in public projection output.' );
$assert( false !== strpos( $projector, '_sabri_news_public_author_approved' ) && false !== strpos( $projector, '_sabri_news_public_institution_name' ), 'Approved author/institution policy is incomplete.' );

foreach ( array( 'featured', 'latest', 'editors-picks', 'research', 'classical-homeopathy', 'public-health', 'homeopathy-education', 'platform-news', 'founder-updates', 'worldwide-health-developments', 'recently-updated' ) as $component ) {
	$assert( false !== strpos( $query, "'" . $component . "'" ), 'News landing component missing: ' . $component );
}
foreach ( array( 'editorial_news_disabled', 'public_news_not_found', 'public_news_filter_invalid', 'public_news_page_invalid', 'public_news_taxonomy_invalid', 'public_news_retracted', 'public_news_query_failed' ) as $code ) {
	$assert( false !== strpos( $query . $rest, $code ), 'Frozen public error code missing: ' . $code );
}
$assert( false !== strpos( $query, 'MAX_PER_PAGE = 24' ) && false !== strpos( $query, 'MAX_PAGE = 1000' ), 'Public queries must remain explicitly bounded.' );
$assert( false !== strpos( $query, 'term_exists' ) && false !== strpos( $query, 'get_term_by' ), 'Controlled public taxonomy filters must verify term existence.' );
$assert( false !== strpos( $query, 'Phase4Contracts::WORKFLOW_META_KEY' ), 'Public queries must use authoritative domain state.' );

$assert( false === strpos( $rest, "'POST'" ) && false === strpos( $rest, "'PATCH'" ) && false === strpos( $rest, "'DELETE'" ), 'Phase 4C public REST must be GET-only.' );
foreach ( array( "'type'=>'integer'", "'minimum'=>1", "'maximum'=>NewsQueryService::MAX_PER_PAGE", 'validate_boolean', 'validate_date', 'unknown_params' ) as $schema_piece ) {
	$assert( false !== strpos( $rest, $schema_piece ), 'Strict REST schema boundary missing: ' . $schema_piece );
}
$assert( false !== strpos( $routing, "'^news/?$'" ) && false !== strpos( $routing, "'^news/([a-z0-9]" ), 'Canonical archive and single rewrite rules are missing.' );
$assert( false !== strpos( $routing, "'section'=>'sabri_news_section'" ) && false !== strpos( $routing, "'topic'=>'sabri_news_topic'" ) && false !== strpos( $routing, "'country'=>'sabri_news_country'" ) && false !== strpos( $routing, "'region'=>'sabri_news_region'" ) && false !== strpos( $routing, "'type'=>'sabri_news_type'" ), 'All five controlled taxonomy route families must exist.' );

$assert( false !== strpos( $feed, "'global_key'" ) || false !== strpos( $feed, 'news:' ), 'News Feed adapter must preserve stable cross-type identity.' );
$assert( false !== strpos( $feed, 'contains_editorial_news' ) && false !== strpos( $assets, 'contains_news' ), 'News assets must be card-aware instead of gate-wide.' );
$assert( false !== strpos( $renderer, "'editorial_news'" ) && false !== strpos( $renderer, 'NewsPublicRuntime::render_card' ), 'Home Feed renderer must delegate normalized News cards.' );
$assert( false !== strpos( $interaction, 'NewsPolicy::can_public_read' ) && false !== strpos( $social, 'render_news_action_bar' ), 'Phase 3 interaction boundary for approved News is incomplete.' );

foreach ( array( 'Featured Story', 'Latest News', 'Editor', 'Research News', 'Recently Updated' ) as $text ) {
	$assert( false !== strpos( $query . $home, $text ), 'News home implementation is missing planned section text: ' . $text );
}
$assert( false !== strpos( $runtime, '<label for="sabri-news-keyword"' ) && false !== strpos( $runtime, 'date_from' ) && false !== strpos( $runtime, 'institution' ) && false !== strpos( $runtime, 'retracted' ), 'Public filter UI is not complete.' );
$assert( false !== strpos( $home, '<main id="main-content"' ) && false !== strpos( $archive, '<main id="main-content"' ) && false !== strpos( $single, '<main id="main-content"' ), 'Public News templates need one main landmark.' );
foreach ( array( 'reviewing_editor', 'updated_at', 'taxonomy_groups', '$article[\'country\']', '$article[\'region\']', 'article_type_term', 'copy', 'interactions' ) as $single_piece ) {
	$assert( false !== strpos( $single, $single_piece ), 'Single News template is incomplete: ' . $single_piece );
}
$assert( false !== strpos( $correction, 'Correction notice' ) && false !== strpos( $correction, 'role="note"' ), 'Correction notice template is incomplete.' );
$assert( false !== strpos( $card, 'data-sabri-global-key' ), 'Dedicated News card identity attribute is missing.' );
$assert( false !== strpos( $retraction, 'role="alert"' ) && false === strpos( $retraction, 'body_html' ), 'Retraction template must display only the accountability notice.' );

$assert( false !== strpos( $css, ':focus-visible' ), 'Visible keyboard focus styling is missing.' );
$assert( false !== strpos( $css, '@media (prefers-reduced-motion: reduce)' ), 'Reduced-motion protection is missing.' );
$assert( false !== strpos( $css, '@media (forced-colors: active)' ), 'Forced-colors support is missing.' );
$assert( false !== strpos( $css, '@media (max-width:' ), 'Responsive public News layout is missing.' );
$assert( false !== strpos( $js, 'data-sabri-global-key' ) && false !== strpos( $js, 'aria-busy' ) && false !== strpos( $js, 'navigator.clipboard' ), 'Progressive enhancement coverage is incomplete.' );
$assert( false === strpos( $js, 'innerHTML =' ) && false === strpos( $js, 'ev' . 'al(' ), 'Public News JavaScript must not inject arbitrary HTML or execute dynamic code.' );

foreach ( array( $home, $archive, $single, $card, $correction, $retraction ) as $template_source ) {
	$assert( false === strpos( $template_source, '$_GET' ) && false === strpos( $template_source, 'WP_Query' ) && false === strpos( $template_source, '$wpdb' ), 'Templates must not read requests or query persistence directly.' );
}
$assert( false !== strpos( $workflow, 'MINIMUM_SECONDS=3900' ) && false !== strpos( $workflow, 'REQUIRED_CYCLES=13' ), 'One-hour workflow must enforce 3,900 seconds and 13 cycles.' );
$assert( false !== strpos( $workflow, 'initial-tracked-files.sha256' ) && false !== strpos( $workflow, 'final-tracked-files.sha256' ), 'One-hour workflow must compare tracked manifests.' );

if ( $failures ) {
	fwrite( STDERR, "Phase 4C UI completeness failures:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}
echo "Phase 4C UI completeness tests passed.\n";
