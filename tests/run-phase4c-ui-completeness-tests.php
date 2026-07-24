<?php
/** Phase 4C source-level UI, security, and completeness assertions. */

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
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$required = array(
	'includes/class-news-cache.php',
	'includes/class-news-public-projector.php',
	'includes/class-news-query-service.php',
	'includes/class-news-feed-integration.php',
	'includes/class-rest-news.php',
	'public/class-news-routing.php',
	'public/class-news-public-runtime.php',
	'templates/news-archive.php',
	'templates/news-single.php',
	'templates/news-card.php',
	'templates/news-retraction-notice.php',
	'assets/css/news.css',
	'assets/js/news.js',
);
foreach ( $required as $path ) {
	$read( $path );
}

$projector = $read( 'includes/class-news-public-projector.php' );
$query = $read( 'includes/class-news-query-service.php' );
$rest = $read( 'includes/class-rest-news.php' );
$routing = $read( 'public/class-news-routing.php' );
$runtime = $read( 'public/class-news-public-runtime.php' );
$feed = $read( 'includes/class-news-feed-integration.php' );
$renderer = $read( 'includes/class-feed-renderer.php' );
$plugin = $read( 'includes/class-plugin.php' );
$bootstrap = $read( 'sabri-complete-home-news-feed.php' );
$css = $read( 'assets/css/news.css' );
$js = $read( 'assets/js/news.js' );
$archive = $read( 'templates/news-archive.php' );
$single = $read( 'templates/news-single.php' );
$card = $read( 'templates/news-card.php' );
$retraction = $read( 'templates/news-retraction-notice.php' );

$assert( false !== strpos( $bootstrap, "SABRI_HNF_PATH . 'public'" ), 'Autoloader must include the public controller directory.' );
foreach ( array( 'NewsCache::class', 'NewsPublicProjector::class', 'NewsQueryService::class', 'NewsFeedIntegration::class', 'NewsRouting::class', 'NewsPublicRuntime::class', 'RestNews::class' ) as $module ) {
	$assert( false !== strpos( $plugin, $module ), 'Plugin coordinator is missing: ' . $module );
}
$assert( false !== strpos( $renderer, "'editorial_news'" ) && false !== strpos( $renderer, 'NewsPublicRuntime::render_card' ), 'Home Feed renderer must delegate normalized News cards.' );
$assert( false !== strpos( $feed, "'global_key'" ) || false !== strpos( $feed, "news:" ), 'News Feed adapter must preserve stable cross-type identity.' );
$assert( false !== strpos( $query, 'MAX_PER_PAGE = 24' ) && false !== strpos( $query, 'MAX_PAGE = 1000' ), 'Public queries must remain explicitly bounded.' );
$assert( false !== strpos( $query, 'Phase4Contracts::WORKFLOW_META_KEY' ), 'Public query must use authoritative domain state.' );
$assert( false !== strpos( $projector, "'body_html'         => ''" ), 'Retraction projection must explicitly hide the original body.' );
$assert( false === strpos( $projector, "'_sabri_news_private_note'" ), 'Private editorial notes must not be named in public projection output.' );
$assert( false === strpos( $rest, "'POST'" ) && false === strpos( $rest, "'PATCH'" ) && false === strpos( $rest, "'DELETE'" ), 'Phase 4C public REST must be GET-only.' );
$assert( false !== strpos( $routing, "'^news/?$'" ) && false !== strpos( $routing, "'^news/([a-z0-9]" ), 'Canonical archive and single rewrite rules are missing.' );
$assert( false !== strpos( $runtime, '<label for="sabri-news-keyword"' ) && false !== strpos( $archive, 'aria-label' ), 'Public filter UI must have programmatic labels.' );
$assert( false !== strpos( $archive, '<main id="main-content"' ) && false !== strpos( $single, '<main id="main-content"' ), 'Public News templates need a main landmark.' );
$assert( false !== strpos( $card, 'data-sabri-global-key' ), 'Dedicated News card identity attribute is missing.' );
$assert( false !== strpos( $retraction, 'role="alert"' ) && false === strpos( $retraction, 'body_html' ), 'Retraction template must display only an accountability notice.' );
$assert( false !== strpos( $css, ':focus-visible' ), 'Visible keyboard focus styling is missing.' );
$assert( false !== strpos( $css, '@media (prefers-reduced-motion: reduce)' ), 'Reduced-motion protection is missing.' );
$assert( false !== strpos( $css, '@media (max-width:' ), 'Responsive public News layout is missing.' );
$assert( false !== strpos( $js, 'data-sabri-global-key' ) && false !== strpos( $js, 'aria-busy' ), 'Progressive enhancement must deduplicate cards and expose busy state.' );
$assert( false === strpos( $js, 'innerHTML =' ) && false === strpos( $js, 'ev' . 'al(' ), 'Public News JavaScript must not inject arbitrary HTML or execute dynamic code.' );

foreach ( array( $archive, $single, $card, $retraction ) as $template_source ) {
	$assert( false === strpos( $template_source, '$_GET' ) && false === strpos( $template_source, 'WP_Query' ) && false === strpos( $template_source, '$wpdb' ), 'Templates must not read requests or query persistence directly.' );
}

if ( $failures ) {
	fwrite( STDERR, "Phase 4C UI completeness failures:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "Phase 4C UI completeness tests passed.\n";
