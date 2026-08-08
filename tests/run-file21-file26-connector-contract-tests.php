<?php
/**
 * File 21 -> File 26 canonical search/discovery connector regression.
 *
 * @package SabriCompleteHomeNewsFeed
 */

$root = dirname( __DIR__ );
$registry_path = $root . '/includes/class-search-provider-registry.php';
$feed_ranking_path = $root . '/includes/class-feed-ranking.php';
$viral_path = $root . '/includes/class-viral-ranking-signals.php';
$feed_css_path = $root . '/assets/css/feed.css';

$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$registry = file_get_contents( $registry_path );
$feed_ranking = file_get_contents( $feed_ranking_path );
$viral = file_get_contents( $viral_path );
$feed_css = file_get_contents( $feed_css_path );

$assert( false !== $registry, 'File 26 connector registry source must be readable.' );
$assert( false !== $feed_ranking, 'File 21 feed ranking source must be readable.' );
$assert( false !== $viral, 'File 21 viral ranking source must be readable.' );
$assert( false !== $feed_css, 'File 21 public feed CSS must be readable.' );

if ( false !== $registry ) {
	$required = array(
		"const FILE26_CONNECTOR_SLUG = 'file21-publication';",
		"const FILE26_CONTRACT_VERSION = '1.0';",
		"'owner_file' => '21'",
		"'status' => 'proposed'",
		"'list_batch' => array( __CLASS__, 'file26_list_batch' )",
		"'can_view' => array( __CLASS__, 'file26_can_view' )",
		"'health' => array( __CLASS__, 'file26_health' )",
		"'global_search_owner' => '26'",
		"NewsPublicProjector::card",
		"PostMetadata::review_state_publicly_visible",
		"PostMetadata::visibility",
		"sabri_file26_source_upsert",
		"sabri_file26_tombstone_document",
		"'authority_score' => 0.0",
		"'popularity_score' => 0.0",
	);
	foreach ( $required as $needle ) {
		$assert( false !== strpos( $registry, $needle ), 'Missing File 26 contract invariant: ' . $needle );
	}
	$assert(
		false === strpos( $registry, "'status' => 'active'" ),
		'File 21 must never self-activate the canonical File 26 connector.'
	);
}

/*
 * Inspect executable PHP tokens only. Human-readable policy/explanation strings
 * are allowed to say that donor/commercial advantage is prohibited; the gate
 * must fail only if those concepts appear in executable ranking identifiers/code.
 */
$executable_source = static function ( $source ) {
	if ( false === $source ) {
		return '';
	}
	$out = '';
	foreach ( token_get_all( (string) $source ) as $token ) {
		if ( is_array( $token ) ) {
			if ( in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_START_HEREDOC, T_END_HEREDOC ), true ) ) {
				continue;
			}
			$out .= $token[1];
			continue;
		}
		$out .= $token;
	}
	return strtolower( str_replace( array( '_', '-' ), ' ', $out ) );
};

$ranking_executable = $executable_source( $feed_ranking ) . "\n" . $executable_source( $viral );
foreach ( array( 'donation', 'donor', 'premium', 'advertising spend', 'paid promotion', 'payment' ) as $forbidden ) {
	$assert(
		false === strpos( $ranking_executable, $forbidden ),
		'File 21 executable organic ranking code must not use commercial/donor advantage signal: ' . $forbidden
	);
}

if ( false !== $feed_css ) {
	$assert( false === stripos( $feed_css, '#ff8a1f' ), 'Superseded orange primary token must not return in File 21 public feed CSS.' );
	$assert( false !== stripos( $feed_css, '#1f7a55' ), 'Current File 21 public feed must retain a green primary action/accent token.' );
}

if ( $failures ) {
	fwrite( STDERR, "File 21/File 26 connector contract regression FAILED:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "File 21/File 26 connector contract regression: PASS\n";
