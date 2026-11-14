<?php
/**
 * V12.3 viewer-scoped Profile Timeline REST no-store regression.
 * Runs the real File21 REST class with bounded isolated owner mocks.
 */
declare(strict_types=1);
namespace {
    define( 'ABSPATH', __DIR__ . '/' );
    $GLOBALS['file21_timeline_actor'] = 2;
    function absint( $v ): int { return abs( (int) $v ); }
    function get_current_user_id(): int { return (int) $GLOBALS['file21_timeline_actor']; }
    final class WP_REST_Response {
        public array $headers = array();
        public function __construct( public array $body, public int $status ) {}
        public function header( $name, $value ): void { $this->headers[$name] = $value; }
    }
}
namespace Sabri\HomeNewsFeed {
    final class ProfileTimeline {
        public const MAX_PER_PAGE = 20;
        public static function query( $user_id, array $args = array() ): array {
            return array(
                'status' => $GLOBALS['file21_timeline_actor'] === -1 ? 'disabled' : 'ok',
                'user_id' => $user_id,
                'items' => $GLOBALS['file21_timeline_actor'] > 0 ? array( array( 'scope' => 'viewer-only' ) ) : array( array( 'scope' => 'world-public' ) ),
            );
        }
    }
}
namespace {
    require dirname( __DIR__ ) . '/includes/class-rest-profile-timeline.php';
    $errors = array();
    $verify = static function ( bool $ok, string $note ) use ( &$errors ) {
        if ( ! $ok ) { $errors[] = $note; }
    };
    $private = \Sabri\HomeNewsFeed\RestProfileTimeline::timeline( array( 'user_id' => 8, 'page' => 1, 'per_page' => 10 ) );
    $verify( $private instanceof WP_REST_Response, 'No WordPress REST response for logged-in viewer' );
    $verify( 'private, no-store, max-age=0' === ( $private->headers['Cache-Control'] ?? '' ),
        'Viewer-scoped private timeline must never enter shared caches' );
    $GLOBALS['file21_timeline_actor'] = 0;
    $guest = \Sabri\HomeNewsFeed\RestProfileTimeline::timeline( array( 'user_id' => 8, 'page' => 1, 'per_page' => 10 ) );
    $verify( $guest instanceof WP_REST_Response && ! isset( $guest->headers['Cache-Control'] ),
        'Anonymous world-public Timeline should retain normal public caching option' );
    if ( $errors ) {
        fwrite( STDERR, "V12.3 Timeline cache FAIL:\n- " . implode( "\n- ", $errors ) . "\n" );
        exit( 1 );
    }
    echo "V12.3 Profile Timeline private viewer no-store and anonymous public cache: PASS\n";
}
