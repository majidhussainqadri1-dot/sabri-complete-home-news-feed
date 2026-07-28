<?php
/**
 * WordPress Page and core-post query routing regression tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! function_exists( 'remove_action' ) ) {
	function remove_action( $hook, $callback, $priority = 10 ) {
		global $sabri_test_actions;
		$sabri_test_actions = array_values(
			array_filter(
				$sabri_test_actions,
				static function ( $action ) use ( $hook, $callback, $priority ) {
					return ! ( $action['hook'] === $hook && $action['callback'] === $callback && (int) $action['priority'] === (int) $priority );
				}
			)
		);
		return true;
	}
}

require_once __DIR__ . '/bootstrap.php';

use Sabri\HomeNewsFeed\FollowersVisibility;
use Sabri\HomeNewsFeed\Plugin;
use Sabri\HomeNewsFeed\PostMetadata;
use Sabri\HomeNewsFeed\PublicQueryGuard;

final class Sabri_Public_Query_Routing_Fixture {
	private $vars;
	private $flags;

	public function __construct( array $vars = array(), array $flags = array() ) {
		$this->vars  = $vars;
		$this->flags = $flags;
	}

	public function get( $key ) {
		return array_key_exists( $key, $this->vars ) ? $this->vars[ $key ] : '';
	}

	public function set( $key, $value ) {
		$this->vars[ $key ] = $value;
	}

	public function is_main_query() { return empty( $this->flags['not_main'] ); }
	public function is_page() { return ! empty( $this->flags['page'] ); }
	public function is_attachment() { return ! empty( $this->flags['attachment'] ); }
	public function is_search() { return ! empty( $this->flags['search'] ); }
	public function is_404() { return ! empty( $this->flags['404'] ); }
	public function is_single() { return ! empty( $this->flags['single'] ); }
	public function is_singular() { return ! empty( $this->flags['single'] ); }
	public function is_home() { return ! empty( $this->flags['home'] ); }
	public function is_category() { return ! empty( $this->flags['category'] ); }
	public function is_tag() { return ! empty( $this->flags['tag'] ); }
	public function is_date() { return ! empty( $this->flags['date'] ); }
	public function is_author() { return ! empty( $this->flags['author'] ); }
	public function is_feed() { return ! empty( $this->flags['feed'] ); }
}

$failures = array();
function sabri_public_routing_assert( $condition, $message ) {
	global $failures;
	if ( ! $condition ) {
		$failures[] = $message;
	}
}

function sabri_public_routing_assert_preserved( array $vars, array $flags, $message ) {
	$sentinel = array( array( 'key' => 'route-sentinel', 'value' => 'preserve' ) );
	$vars['meta_query'] = $sentinel;
	$query = new Sabri_Public_Query_Routing_Fixture( $vars, $flags );
	PublicQueryGuard::filter_public_queries( $query );
	sabri_public_routing_assert( $sentinel === $query->get( 'meta_query' ), $message . ' Original meta query changed.' );
	sabri_public_routing_assert( '' === $query->get( PublicQueryGuard::FILTER_MARKER ), $message . ' Guard marker was added.' );
}

function sabri_public_routing_assert_filtered( array $vars, array $flags, $message ) {
	$sentinel = array( array( 'key' => 'route-sentinel', 'value' => 'preserve' ) );
	$vars['meta_query'] = $sentinel;
	$query = new Sabri_Public_Query_Routing_Fixture( $vars, $flags );
	PublicQueryGuard::filter_public_queries( $query );
	$filtered = $query->get( 'meta_query' );
	sabri_public_routing_assert( is_array( $filtered ) && 4 === count( $filtered ), $message . ' Visibility clauses were not added exactly once.' );
	sabri_public_routing_assert( isset( $filtered['relation'] ) && 'AND' === $filtered['relation'], $message . ' Meta-query relation must remain explicit.' );
	sabri_public_routing_assert( isset( $filtered[0] ) && $sentinel === $filtered[0], $message . ' Existing metadata conditions were not preserved.' );
	sabri_public_routing_assert( 1 === (int) $query->get( PublicQueryGuard::FILTER_MARKER ), $message . ' Guard marker is missing.' );
	PublicQueryGuard::filter_public_queries( $query );
	sabri_public_routing_assert( 4 === count( $query->get( 'meta_query' ) ), $message . ' Repeated execution duplicated metadata clauses.' );
}

sabri_test_reset_state( true );
Plugin::instance()->register();

global $sabri_test_actions, $sabri_test_filters;
$legacy_prequery_hook    = false;
$followers_prequery_hook = false;
$public_prequery_hook    = false;
$posts_result_filter     = false;
foreach ( $sabri_test_actions as $action ) {
	if ( 'pre_get_posts' !== $action['hook'] ) {
		continue;
	}
	if ( array( PostMetadata::class, 'filter_public_queries' ) === $action['callback'] ) {
		$legacy_prequery_hook = true;
	}
	if ( array( FollowersVisibility::class, 'extend_post_queries' ) === $action['callback'] ) {
		$followers_prequery_hook = true;
	}
	if ( array( PublicQueryGuard::class, 'filter_public_queries' ) === $action['callback'] ) {
		$public_prequery_hook = true;
	}
}
foreach ( $sabri_test_filters as $filter ) {
	if ( 'the_posts' === $filter['hook'] && array( PublicQueryGuard::class, 'filter_public_post_results' ) === $filter['callback'] ) {
		$posts_result_filter = true;
	}
}
sabri_public_routing_assert( ! $legacy_prequery_hook, 'The unsafe broad PostMetadata pre_get_posts callback must be removed.' );
sabri_public_routing_assert( ! $followers_prequery_hook, 'The unrelated followers pre_get_posts callback must remain removed.' );
sabri_public_routing_assert( $public_prequery_hook, 'The bounded public main-list pre_get_posts callback must be registered.' );
sabri_public_routing_assert( $posts_result_filter, 'Resolved singular/ambiguous post visibility fallback must be registered on the_posts.' );

$page = (object) array( 'ID' => 14, 'post_type' => 'page' );
$page_results = PublicQueryGuard::filter_public_post_results( array( $page ), new Sabri_Public_Query_Routing_Fixture() );
sabri_public_routing_assert( 1 === count( $page_results ) && $page === $page_results[0], 'Resolved Page objects must pass through unchanged.' );
sabri_public_routing_assert( array() === PublicQueryGuard::filter_public_post_results( array(), new Sabri_Public_Query_Routing_Fixture() ), 'Empty missing-route results must remain empty.' );

sabri_public_routing_assert_preserved( array( 'post_type' => 'page' ), array( 'page' => true ), 'Explicit Page query must be preserved.' );
sabri_public_routing_assert_preserved( array( 'page_id' => 14 ), array(), 'page_id query must be preserved.' );
sabri_public_routing_assert_preserved( array( 'pagename' => 'sample-page' ), array(), 'pagename query must be preserved.' );
sabri_public_routing_assert_preserved( array( 'name' => 'missing-pretty-route' ), array( 'single' => true ), 'Unresolved single-slug query must be preserved.' );
sabri_public_routing_assert_preserved( array( 'error' => '404' ), array( '404' => true ), '404 query must be preserved.' );
sabri_public_routing_assert_preserved( array(), array( 'page' => true ), 'Untyped is_page query must be preserved.' );
sabri_public_routing_assert_preserved( array( 'post_type' => array( 'post', 'page' ) ), array(), 'Mixed post/page query must be preserved.' );
sabri_public_routing_assert_preserved( array( 'post_type' => 'any' ), array(), 'post_type any query must be preserved.' );
sabri_public_routing_assert_preserved( array(), array( 'search' => true ), 'Search query must be preserved.' );
sabri_public_routing_assert_preserved( array( 'post_type' => 'attachment' ), array( 'attachment' => true ), 'Attachment query must be preserved.' );
sabri_public_routing_assert_preserved( array(), array( 'single' => true ), 'Ambiguous untyped single query must be preserved for late authorization.' );
sabri_public_routing_assert_preserved( array( 'p' => 25 ), array( 'single' => true ), 'Direct numeric single-post query must be preserved for late authorization.' );
sabri_public_routing_assert_preserved( array(), array(), 'Unknown untyped query must fail closed without mutation.' );
sabri_public_routing_assert_preserved( array( 'post_type' => 'post' ), array( 'not_main' => true ), 'Secondary post query must be preserved.' );

sabri_public_routing_assert_filtered( array( 'post_type' => 'post' ), array(), 'Explicit main post-list query must be filtered.' );
sabri_public_routing_assert_filtered( array( 'post_type' => array( 'post' ) ), array(), 'Exclusive main post array query must be filtered.' );
sabri_public_routing_assert_filtered( array(), array( 'home' => true ), 'Posts index must be filtered before pagination.' );
sabri_public_routing_assert_filtered( array(), array( 'category' => true ), 'Category archive must be filtered before pagination.' );
sabri_public_routing_assert_filtered( array(), array( 'tag' => true ), 'Tag archive must be filtered before pagination.' );
sabri_public_routing_assert_filtered( array(), array( 'date' => true ), 'Date archive must be filtered before pagination.' );
sabri_public_routing_assert_filtered( array(), array( 'author' => true ), 'Author archive must be filtered before pagination.' );
sabri_public_routing_assert_filtered( array(), array( 'feed' => true ), 'Core post feed must be filtered before pagination.' );

if ( ! empty( $failures ) ) {
	fwrite( STDERR, "Public query routing tests failed:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "Public query routing tests passed.\n";
