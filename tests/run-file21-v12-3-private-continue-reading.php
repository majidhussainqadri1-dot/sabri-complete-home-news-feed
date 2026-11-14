<?php
/**
 * V12.3 F21-NG-18 private Continue Reading boundary regression.
 * Isolated mocks prove request-subject binding and matching click-time ACL;
 * this is NOT a substitute for independent real WordPress/SQL acceptance.
 */
declare(strict_types=1);
namespace {
    define( 'ABSPATH', __DIR__ . '/' );
    define( 'WEEK_IN_SECONDS', 604800 );
    $GLOBALS['f21_progress_actor'] = 2;
    $GLOBALS['f21_progress_assurance'] = array( 2 => true, 3 => true );
    $GLOBALS['f21_progress_readable'] = array( 2 => array( 11 => true, 12 => true, 13 => false ) );
    $GLOBALS['f21_progress_state'] = array(
        2 => array(
            'progress' => array(
                11 => array( 'percent' => 20, 'updated' => 100 ),
                12 => array( 'percent' => 55, 'updated' => 300 ), // valid scoped member post.
                13 => array( 'percent' => 90, 'updated' => 200 ), // revoked since saving.
            ),
        ),
        3 => array( 'progress' => array( 99 => array( 'percent' => 40, 'updated' => 400 ) ) ),
    );
    function absint( $value ): int { return abs( (int) $value ); }
    function get_current_user_id(): int { return (int) $GLOBALS['f21_progress_actor']; }
    function get_user_meta( $user_id, $key, $single = true ) {
        return $GLOBALS['f21_progress_state'][(int) $user_id] ?? array();
    }
    function get_the_title( $post_id ): string { return 'Article ' . (int) $post_id; }
    function get_permalink( $post_id ): string { return 'https://example.test/articles/' . (int) $post_id; }
}
namespace Sabri\HomeNewsFeed {
    final class CanonicalIdentityAdapter {
        public static function current_action_ready( $user_id ): bool {
            return ! empty( $GLOBALS['f21_progress_assurance'][(int) $user_id] )
                && (int) $GLOBALS['f21_progress_actor'] === (int) $user_id;
        }
    }
    final class InteractionPermissions {
        public static function authenticated_user_id( $user_id = 0 ): int {
            $actor = (int) $GLOBALS['f21_progress_actor'];
            return $actor > 0 && (int) $user_id === $actor ? $actor : 0;
        }
        public static function can_view_post( $post_id, $user_id = 0 ): bool {
            return ! empty( $GLOBALS['f21_progress_readable'][(int) $user_id][(int) $post_id] );
        }
    }
}
namespace {
    require dirname( __DIR__ ) . '/includes/class-next-generation-feed.php';
    use Sabri\HomeNewsFeed\NextGenerationFeed as NG;
    $issues = array();
    $assert = static function ( bool $condition, string $why ) use ( &$issues ): void {
        if ( ! $condition ) { $issues[] = $why; }
    };
    $items = NG::continue_reading_items( 2 );
    $ids = array_map( static fn ( array $item ) => (int) $item['id'], $items );
    $assert( $ids === array( 12, 11 ), 'Scoped reviewed post must be resumable; withdrawn post excluded; newest first' );
    $assert( isset( $items[0]['percent'] ) && 55 === $items[0]['percent'], 'Reading progress remains private and correct' );
    $assert( NG::continue_reading_items( 3 ) === array(), 'Cannot request another member private reading history' );
    $GLOBALS['f21_progress_assurance'][2] = false;
    $assert( NG::continue_reading_items( 2 ) === array(), 'Suspended/assurance-unready actor cannot read private progress' );
    $GLOBALS['f21_progress_assurance'][2] = true;
    $GLOBALS['f21_progress_actor'] = 0;
    $assert( NG::continue_reading_items( 2 ) === array(), 'Anonymous actor cannot read private progress' );
    if ( $issues ) {
        fwrite( STDERR, "V12.3 F21-NG-18 private Continue Reading FAIL:\n- " . implode( "\n- ", $issues ) . "\n" );
        exit( 1 );
    }
    echo "V12.3 F21-NG-18 scoped reading, revoked ACL, subject binding and assurance: PASS\n";
}
