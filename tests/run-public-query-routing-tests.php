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
	sabri_public_routing_assert( is_array( $filtered ) && 3 === count( $filtered ), $message . ' Visibility clauses were not added exactly once.' );
	sabri_public_routing_assert( 1 === (int) $query->get( PublicQueryGuard::FILTER_MARKER ), $message . ' Guard marker is missing.' );
	PublicQueryGuard::filter_public_queries( $query );
	sabri_public_routing_assert( 3 === count( $query->get( 'meta_query' ) ), $message . ' Repeated execution duplicated metadata clauses.' );
}

sabri_test_reset_state( true );
Plugin::instance()->register();

global $sabri_test_actions;
$legacy_hook = false;
$guard_hook  = false;
foreach ( $sabri_test_actions as $action ) {
	if ( 'pre_get_posts' !== $action['hook'] || 10 !== (int) $action['priority'] ) {
		continue;
	}
	if ( array( PostMetadata::class, 'filter_public_queries' ) === $action['callback'] ) {
		$legacy_hook = true;
	}
	if ( array( PublicQueryGuard::class, 'filter_public_queries' ) === $action['callback'] ) {
		$guard_hook = true;
	}
}
sabri_public_routing_assert( ! $legacy_hook, 'The unsafe PostMetadata pre_get_posts callback must be removed.' );
sabri_public_routing_assert( $guard_hook, 'The strict PublicQueryGuard callback must be registered.' );

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
sabri_public_routing_assert_preserved( array(), array(), 'Unknown untyped query must fail closed without mutation.' );
sabri_public_routing_assert_preserved( array( 'post_type' => 'post' ), array( 'not_main' => true ), 'Secondary post query must be preserved.' );

sabri_public_routing_assert_filtered( array( 'post_type' => 'post' ), array(), 'Explicit post query must be filtered.' );
sabri_public_routing_assert_filtered( array( 'post_type' => array( 'post' ) ), array(), 'Exclusive post array query must be filtered.' );
sabri_public_routing_assert_filtered( array( 'p' => 25 ), array(), 'Direct numeric post query must be filtered.' );
sabri_public_routing_assert_filtered( array(), array( 'home' => true ), 'Posts index query must be filtered.' );
sabri_public_routing_assert_filtered( array(), array( 'category' => true ), 'Category post archive must be filtered.' );
sabri_public_routing_assert_filtered( array(), array( 'tag' => true ), 'Tag post archive must be filtered.' );
sabri_public_routing_assert_filtered( array(), array( 'date' => true ), 'Date post archive must be filtered.' );
sabri_public_routing_assert_filtered( array(), array( 'author' => true ), 'Author post archive must be filtered.' );
sabri_public_routing_assert_filtered( array(), array( 'feed' => true ), 'Core post feed must be filtered.' );

if ( ! empty( $failures ) ) {
	fwrite( STDERR, "Public query routing tests failed:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "Public query routing tests passed.\n";
