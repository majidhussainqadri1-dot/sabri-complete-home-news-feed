<?php
/**
 * V12.3 exact-source Editorial News publication authority regression.
 * No staging/live claims; isolated contract negative tests only.
 */

declare(strict_types=1);

namespace {
    define( 'ABSPATH', __DIR__ . '/' );
    $GLOBALS['f21v123_actor'] = 1;
    $GLOBALS['f21v123_registered_hooks'] = array();
    function absint( $value ): int { return abs( (int) $value ); }
    function sanitize_key( $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?? ''; }
    function get_current_user_id(): int { return $GLOBALS['f21v123_actor']; }
    function __( $text, $domain = '' ): string { return $text; }
    function add_filter( $name, $callback, $priority = 10, $args = 1 ): bool {
        $GLOBALS['f21v123_registered_hooks'][] = array( $name, $priority, $args );
        return true;
    }
    function apply_filters( $hook, $value ) { return $value; }
}

namespace Sabri\HomeNewsFeed {
    final class Phase4Contracts {
        public static function capabilities(): array {
            return array(
                'read_editorial_news', 'create_editorial_news', 'edit_own_editorial_news',
                'edit_others_editorial_news', 'submit_editorial_news', 'review_editorial_news',
                'fact_check_editorial_news', 'medical_review_editorial_news',
                'publish_editorial_news', 'schedule_editorial_news', 'manage_breaking_news',
                'manage_news_sources', 'manage_news_corrections', 'retract_editorial_news',
                'translate_editorial_news', 'manage_news_taxonomies', 'manage_news_settings'
            );
        }
    }
    final class SafeMode {
        public static function public_features_disabled(): bool { return false; }
    }
    final class CanonicalIdentityAdapter {
        public static function current_action_ready( int $user_id ): bool {
            return in_array( $user_id, array( 1, 3 ), true );
        }
        public static function can_publish_immediately( int $user_id ): bool {
            return in_array( $user_id, array( 1, 3 ), true );
        }
    }
}

namespace {
    require dirname( __DIR__ ) . '/includes/class-news-capabilities.php';
    use Sabri\HomeNewsFeed\NewsCapabilities as N;
    $errors = array();
    $assert = static function ( bool $ok, string $message ) use ( &$errors ): void {
        if ( ! $ok ) { $errors[] = $message; fwrite( STDERR, "FAIL: {$message}\n" ); }
    };

    N::register();
    $assert( in_array( array( 'user_has_cap', 110, 4 ), $GLOBALS['f21v123_registered_hooks'], true ), 'News publication guard is registered after base capability policies' );

    $map = N::default_role_map();
    $assert( N::role_can_publish( 'founder' ), 'Founder role has News publishing capability' );
    $assert( ! N::role_can_publish( 'administrator' ), 'Administrator alone is not a News publisher' );
    $assert( ! N::role_can_publish( 'editor_in_chief' ), 'Editor-in-Chief role alone is not a News publisher' );
    $assert( ! in_array( 'schedule_editorial_news', $map['administrator'] ?? array(), true ), 'Administrator alone does not receive schedule capability' );
    $assert( ! in_array( 'schedule_editorial_news', $map['editor_in_chief'] ?? array(), true ), 'Editor-in-Chief alone does not receive schedule capability' );
    $assert( in_array( 'manage_news_settings', $map['administrator'] ?? array(), true ), 'Administrative non-publication settings capability remains available' );

    $grant = array( 'publish_editorial_news' => true, 'schedule_editorial_news' => true, 'manage_news_settings' => true );
    foreach ( array( 1, 2, 3, 4 ) as $id ) {
        $GLOBALS['f21v123_actor'] = $id;
        $result = N::enforce_v123_direct_publish_boundary(
            $grant,
            array( 'publish_editorial_news' ),
            array( 'publish_editorial_news' ),
            (object) array( 'ID' => $id )
        );
        $expected = in_array( $id, array( 1, 3 ), true );
        $assert( ! empty( $result['publish_editorial_news'] ) === $expected, "Exact actor {$id} News publish contract" );
        $assert( ! empty( $result['schedule_editorial_news'] ) === $expected, "Exact actor {$id} News schedule contract" );
        $assert( ! empty( $result['manage_news_settings'] ), "Exact actor {$id} unrelated settings cap retained" );
    }

    $GLOBALS['f21v123_actor'] = 1;
    $borrow = N::enforce_v123_direct_publish_boundary(
        $grant, array( 'publish_editorial_news' ), array(), (object) array( 'ID' => 3 )
    );
    $assert( empty( $borrow['publish_editorial_news'] ), 'Current Founder cannot borrow different verified Doctor assertion' );
    $unrelated = N::enforce_v123_direct_publish_boundary(
        $grant, array( 'manage_news_settings' ), array(), (object) array( 'ID' => 2 )
    );
    $assert( $unrelated === $grant, 'Unrelated administration query is unaffected' );

    printf( "V12.3 Editorial News authority: %d failure(s).\n", count( $errors ) );
    exit( $errors ? 1 : 0 );
}
