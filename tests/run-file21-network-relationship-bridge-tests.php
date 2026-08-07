<?php
/** File 21/File 17 relationship bridge behavior regression. */
define( 'ABSPATH', __DIR__ . '/wp/' );
function add_filter() {}
function add_action() {}
function apply_filters( $hook, $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function absint( $value ) { return abs( (int) $value ); }
class WP_Error {}
class SN_Relationships {
    public static $fail = false;
    public static $calls = array();
    public static function state( $viewer, $author ) { return array( 'blocked' => false, 'follow' => array( 'state' => 'active', 'version' => 1 ) ); }
    public static function lists( $user, $scope, $limit, $cursor = '' ) {
        self::$calls[] = array( $user, $scope, $limit, $cursor );
        if ( self::$fail ) { return new WP_Error(); }
        $start = '' === $cursor ? 1 : (int) $cursor;
        $items = array();
        for ( $i = $start; $i < $start + $limit && $i <= 120; $i++ ) { $items[] = array( 'followed_id' => $i + 1000 ); }
        $next = $start + $limit <= 120 ? (string) ( $start + $limit ) : '';
        return array( 'items' => $items, 'next_cursor' => $next );
    }
}
class Phase3FeatureSettings { public static function enabled( $key ) { return true; } }
class InteractionQueryRepository { public static function following_user_ids() { return array( 9999 ); } }
class FollowService { const TARGET_TYPE = 'user'; }
class ProfileLinkResolver { public static function url( $id ) { return '/u/' . $id; } }
require_once dirname( __DIR__ ) . '/includes/class-network-relationship-bridge.php';
use Sabri\HomeNewsFeed\NetworkRelationshipBridge;
$ids = NetworkRelationshipBridge::following_user_ids( 10, 120 );
if ( 120 !== count( $ids ) || 3 !== count( SN_Relationships::$calls ) ) { fwrite( STDERR, "Pagination contract failed.\n" ); exit( 1 ); }
SN_Relationships::$fail = true;
SN_Relationships::$calls = array();
$ids = NetworkRelationshipBridge::following_user_ids( 10, 120 );
if ( array() !== $ids ) { fwrite( STDERR, "Native File 17 failure must fail closed, not use legacy fallback.\n" ); exit( 1 ); }
echo "File 21/File 17 relationship bridge tests passed.\n";
