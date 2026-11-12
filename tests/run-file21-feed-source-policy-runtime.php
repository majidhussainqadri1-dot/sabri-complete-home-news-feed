<?php
/**
 * File 21 Issue #67 canonical feed source query after hostile extension hooks.
 *
 * Confirms filter composition without requiring Live DB/WP access.
 */
// The lean repository stubs have no WordPress user-meta backend.
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key, $single = true ) {
		return $GLOBALS['issue67_user_meta'][ (int) $user_id ][ (string) $key ] ?? ( $single ? '' : array() );
	}
}
if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $user_id, $key, $value ) {
		$GLOBALS['issue67_user_meta'][ (int) $user_id ][ (string) $key ] = $value;
		return true;
	}
}
require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\FeedQuery;
use Sabri\HomeNewsFeed\FeedUserAgency;
use Sabri\HomeNewsFeed\Plugin;
use Sabri\HomeNewsFeed\PostMetadata;
use Sabri\HomeNewsFeed\Settings;

if ( ! defined( 'HOUR_IN_SECONDS' ) ) { define( 'HOUR_IN_SECONDS', 3600 ); }
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
if ( ! defined( 'WEEK_IN_SECONDS' ) ) { define( 'WEEK_IN_SECONDS', 604800 ); }

sabri_test_reset_state( true );
Plugin::instance()->register();
$GLOBALS['issue67_failures'] = array();
$GLOBALS['sabri_test_current_user_id'] = 7;
$assert = static function ( $truth, $reason ) {
	if ( ! $truth ) {
		$GLOBALS['issue67_failures'][] = $reason;
	}
};
$walk = static function ( $value, $needle ) use ( &$walk ) {
	if ( ! is_array( $value ) ) { return false; }
	if ( isset( $value['key'] ) && (string) $value['key'] === (string) $needle ) { return true; }
	foreach ( $value as $child ) {
		if ( is_array( $child ) && $walk( $child, $needle ) ) { return true; }
	}
	return false;
};

$settings = Settings::defaults();
$original = FeedQuery::wp_query_args( 'latest', 1, 20, 7, $settings );
$assert( 'post' === $original['post_type'] && array( 'publish' ) === $original['post_status'], 'Canonical baseline must be core published posts.' );
$assert( $walk( $original['meta_query'], PostMetadata::META_VISIBILITY ) && $walk( $original['meta_query'], PostMetadata::META_REVIEW_STATE ), 'Canonical baseline must require viewer visibility and review approval.' );

// A later extension receives the fully valid built-in File21 policies and
// deliberately attempts to widen publication, review, audience and scan.
add_filter( 'sabri_hnf_feed_query_args', static function ( $args ) {
	if ( isset( $GLOBALS['issue67_attack'] ) && 'throw' === $GLOBALS['issue67_attack'] ) {
		throw new \RuntimeException( 'Simulated extension failure' );
	}
	return array_key_exists( 'issue67_attack', $GLOBALS ) ? $GLOBALS['issue67_attack'] : $args;
}, 999, 4 );
$GLOBALS['issue67_attack'] = array(
	'post_type'         => 'page',
	'post_status'       => array( 'draft', 'private' ),
	'posts_per_page'    => 10000,
	'paged'             => 9876,
	'nopaging'          => true,
	'offset'            => 500,
	'has_password'      => true,
	'orderby'           => 'rand',
	'order'             => 'ASC',
	'ignore_sticky_posts' => false,
	'suppress_filters'  => true,
	'fields'            => 'ids',
	'meta_query'        => array( 'relation' => 'OR', array( 'key' => 'fake-bypass', 'value' => 'all' ) ),
	'tax_query'         => array( 'relation' => 'OR', array( 'taxonomy' => 'category', 'terms' => array( 99 ) ) ),
	'author__in'        => array( 99 ),
	'post__in'          => array( 10, 11 ),
);
$bounded = FeedQuery::wp_query_args( 'latest', 3, 13, 7, $settings );
$assert( 'post' === $bounded['post_type'] && array( 'publish' ) === $bounded['post_status'], 'Hostile filter cannot request pages, private/draft or other types.' );
$assert( 13 === $bounded['posts_per_page'] && 3 === $bounded['paged'] && empty( $bounded['nopaging'] ) && ! isset( $bounded['offset'] ), 'Hostile filter cannot override bounded pagination.' );
$assert( false === $bounded['has_password'] && false === $bounded['suppress_filters'] && 'all' === $bounded['fields'], 'Hostile filter cannot include passworded content, disable source filters or reduce source objects to IDs.' );
$assert( 'date' === $bounded['orderby'] && 'DESC' === $bounded['order'], 'Hostile filter cannot falsify Latest chronology.' );
$assert( $walk( $bounded['meta_query'], PostMetadata::META_VISIBILITY ) && $walk( $bounded['meta_query'], PostMetadata::META_REVIEW_STATE ), 'Hostile OR meta query cannot remove source review/visibility AND clauses.' );
$assert( 'AND' === ( $bounded['meta_query']['relation'] ?? '' ), 'Extension meta query must be AND-composed with source gates.' );

$founder = FeedQuery::wp_query_args( 'founder-updates', 1, 10, 7, $settings );
$assert( isset( $founder['author__in'] ) && array( 0 ) === $founder['author__in'], 'Founder listing cannot accept an unverified author from hostile filter.' );
$doctor = FeedQuery::wp_query_args( 'doctors-posts', 1, 10, 7, $settings );
$assert( isset( $doctor['author__in'] ) && ! in_array( 99, $doctor['author__in'], true ), 'Doctor listing cannot accept an unrelated author from hostile filter.' );
$following = FeedQuery::wp_query_args( 'following', 1, 10, 7, $settings );
$assert( array( 0 ) === $following['author__in'], 'Hostile Following scope cannot replace empty canonical follows with a stranger.' );

update_user_meta( 7, FeedUserAgency::META_KEY, array(
	'hidden_posts'  => array( 501 ),
	'muted_authors' => array( 4 ),
	'muted_topics'  => array( 'restricted-topic' ),
) );
$chosen = FeedQuery::wp_query_args( 'latest', 2, 15, 7, $settings );
$assert( in_array( 501, $chosen['post__not_in'] ?? array(), true ) && in_array( 4, $chosen['author__not_in'] ?? array(), true ), 'A late plugin filter cannot undo the current user hidden/muted post-author choices.' );
$assert( 'AND' === ( $chosen['tax_query']['relation'] ?? '' ), 'Current user muted topic must remain an AND exclusion even if extension uses OR.' );

// Unknown plugin response should yield an explicitly empty source query.
$GLOBALS['issue67_attack'] = 'invalid query object';
$bad = FeedQuery::wp_query_args( 'latest', 1, 20, 7, $settings );
$assert( array( 0 ) === ( $bad['post__in'] ?? array() ) && 'post' === $bad['post_type'] && array( 'publish' ) === $bad['post_status'], 'Invalid extension output cannot become an unbounded query.' );
$assert( $walk( $bad['meta_query'], PostMetadata::META_REVIEW_STATE ), 'Fail-closed malformed extension must retain source approval state.' );

$GLOBALS['issue67_attack'] = array( 'meta_query' => 'malformed-nested-review-expression' );
$bad_nested = FeedQuery::wp_query_args( 'latest', 1, 20, 7, $settings );
$assert( array( 0 ) === ( $bad_nested['post__in'] ?? array() ), 'Malformed nested metadata conditions must fail closed.' );
$GLOBALS['issue67_attack'] = array( 'post__in' => range( 1, 1000 ) );
$oversized = FeedQuery::wp_query_args( 'latest', 1, 20, 7, $settings );
$assert( array( 0 ) === ( $oversized['post__in'] ?? array() ), 'Unbounded extension ID lists must fail closed before WordPress SQL.' );
$GLOBALS['issue67_attack'] = 'throw';
$exception = FeedQuery::wp_query_args( 'latest', 1, 20, 7, $settings );
$assert( array( 0 ) === ( $exception['post__in'] ?? array() ), 'Throwing late filter cannot crash public Feed request or execute an unbounded query.' );

unset( $GLOBALS['issue67_attack'] );
$restored = FeedQuery::wp_query_args( 'latest', 1, 20, 7, $settings );
$assert( 20 === $restored['posts_per_page'] && 'post' === $restored['post_type'] && array( 'publish' ) === $restored['post_status'], 'Correct canonical query works normally after hostile extension removed.' );

if ( ! empty( $GLOBALS['issue67_failures'] ) ) {
	fwrite( STDERR, "File 21 Issue #67 source/extension contract errors:\n- " . implode( "\n- ", $GLOBALS['issue67_failures'] ) . "\n" );
	exit( 1 );
}
echo "File21 source query immutability after late filters, Founder/Doctor/Following and current user's mute: PASS\n";
