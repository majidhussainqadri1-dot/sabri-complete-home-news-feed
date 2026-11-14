<?php
/** Governing V12.3 News-specific brand and text-contrast acceptance. */
declare(strict_types=1);
$root = dirname( __DIR__ );
$news = file_get_contents( $root . '/assets/css/news.css' );
$feed = file_get_contents( $root . '/assets/css/feed.css' );
$failure = array();
$check = static function ( bool $condition, string $why ) use ( &$failure ): void {
    if ( ! $condition ) { $failure[] = $why; }
};
$check( is_string( $news ) && is_string( $feed ), 'Missing News/Feed CSS' );
if ( is_string( $news ) && is_string( $feed ) ) {
    $check( str_contains( $news, '--sabri-news-accent: #087a4e;' ), 'News central primary is not #087A4E' );
    $check( str_contains( $news, '--sabri-news-soft: #e7f5ee;' ), 'News soft surface is not File25-approved #E7F5EE' );
    $check( str_contains( $news, '.sabri-news-eyebrow, .sabri-news-card__label { color: #065c3b;' ), 'News source labels are using the legacy primary orange' );
    $check( str_contains( $news, 'background: var(--sabri-news-accent); border-color: #065c3b; color: #fff;' ), 'News primary button lacks accessible white foreground' );
    $check( str_contains( $news, '.sabri-news-pagination .current { background: var(--sabri-news-accent); color: #fff;' ), 'News active pagination lacks accessible white foreground' );
    $check( ! str_contains( strtolower( $news ), '#f28c28' ) && ! str_contains( strtolower( $news ), '#a94f00' ), 'Legacy News orange remains in primary control tokens' );
    $check( str_contains( strtolower( $feed ), '#087a4e' ), 'News brand diverges from Feed central primary' );
}
$lum = static function ( string $hex ): float {
    $n = array( hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) );
    $components = array_map( static function ( $v ) {
        $v /= 255.0;
        return $v <= 0.04045 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
    }, $n );
    return .2126 * $components[0] + .7152 * $components[1] + .0722 * $components[2];
};
$light = $lum( '#ffffff' );
$primary = $lum( '#087a4e' );
$contrast = ( max( $light, $primary ) + .05 ) / ( min( $light, $primary ) + .05 );
$check( $contrast >= 4.5, 'News primary button/pagination misses 4.5:1 WCAG normal-text contrast' );
if ( $failure ) {
    fwrite( STDERR, "V12.3 News primary color acceptance FAILED:\n- " . implode( "\n- ", $failure ) . "\n" );
    exit( 1 );
}
printf( "V12.3 News brand #087A4E and WCAG button/pagination contrast %.2f:1: PASS\n", $contrast );
