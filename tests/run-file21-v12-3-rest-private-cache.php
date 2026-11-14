<?php
/** V12.3 NG30 per-viewer REST no-store contract (isolated). */
declare(strict_types=1);
namespace {
    define( 'ABSPATH', __DIR__ . '/' );
    $GLOBALS['ng_actor'] = 4;
    function get_current_user_id(): int { return (int) $GLOBALS['ng_actor']; }
    function absint( $value ): int { return abs( (int) $value ); }
    function sanitize_key( $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?? ''; }
    function sanitize_text_field( $value ): string { return trim( strip_tags( (string) $value ) ); }
    final class WP_REST_Response {
        public array $headers = array();
        public function __construct( public $data, public int $status ) {}
        public function header( $name, $value ): void { $this->headers[ $name ] = $value; }
    }
}
namespace {
    require dirname( __DIR__ ) . '/includes/class-rest-next-generation.php';
    $klass = \Sabri\HomeNewsFeed\RestNextGeneration::class;
    $success = new ReflectionMethod( $klass, 'response' );
    $error = new ReflectionMethod( $klass, 'error' );
    $issues = array();
    $check = static function ( bool $condition, string $why ) use ( &$issues ) {
        if ( ! $condition ) { $issues[] = $why; }
    };
    $res = $success->invoke( null, array( 'private_reading' => array( 1, 2, 3 ) ), 200 );
    $check( $res instanceof WP_REST_Response &&
        'private, no-store, max-age=0' === ( $res->headers['Cache-Control'] ?? '' ),
        'Authenticated NG30 private projections must be no-store' );
    $err = $error->invoke( null, 'denied', 'Private request denied', 403 );
    $check( $err instanceof WP_REST_Response &&
        'private, no-store, max-age=0' === ( $err->headers['Cache-Control'] ?? '' ),
        'Authenticated NG30 error response must be no-store' );
    $GLOBALS['ng_actor'] = 0;
    $public = $success->invoke( null, array( 'manifest' => true ), 200 );
    $check( $public instanceof WP_REST_Response && ! isset( $public->headers['Cache-Control'] ),
        'Anonymous public manifest must not acquire a needless private-cache veto' );
    if ( $issues ) { fwrite( STDERR, "V12.3 NG30 REST private response FAILED:\n- " . implode( "\n- ", $issues ) . "\n" ); exit( 1 ); }
    echo "V12.3 NG30 authenticated REST private no-store and anonymous cache boundary: PASS\n";
}
