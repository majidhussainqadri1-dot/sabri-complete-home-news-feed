<?php
/**
 * Regression guard for the File 21 -> File 22 create gateway.
 *
 * The File 22 registry applies required_capability() before native can_create().
 * The registered File 21 wrapper must therefore use only an authenticated coarse
 * gate and leave final authorization to the native adapter, which revalidates
 * File 00 identity, suspension, native capabilities and current policy.
 */

$root   = dirname( __DIR__ );
$schema = file_get_contents( $root . '/includes/class-universal-composer-subject-schema-adapter.php' );
$native = file_get_contents( $root . '/includes/class-universal-composer-workflow-adapter.php' );
$perms  = file_get_contents( $root . '/includes/class-composer-permissions.php' );

if ( false === $schema || false === $native || false === $perms ) {
	fwrite( STDERR, "Unable to read File 21/File 22 gateway sources.\n" );
	exit( 1 );
}

$assertions = array(
	'wrapper uses authenticated coarse capability gate' => false !== strpos( $schema, "public function required_capability(): string { return 'read'; }" ),
	'wrapper delegates final create authorization'       => false !== strpos( $schema, 'public function can_create( int $user_id ): bool { return $this->delegate->can_create( $user_id ); }' ),
	'native adapter requires policy authorization'       => false !== strpos( $native, 'ComposerPermissions::user_can_create( $user_id, Settings::get() )' ),
	'native policy revalidates File 00 readiness'        => false !== strpos( $perms, 'CanonicalIdentityAdapter::current_action_ready( $user_id )' ),
	'native policy retains plugin/admin capability gate' => false !== strpos( $perms, "array( 'sabri_feed_create_posts', 'manage_options' )" ),
);

$failed = array_keys( array_filter( $assertions, static function ( $passed ) { return ! $passed; } ) );
if ( $failed ) {
	fwrite( STDERR, "Founder create gateway regression failed:\n- " . implode( "\n- ", $failed ) . "\n" );
	exit( 1 );
}

echo "File 21/File 22 Founder create gateway regression: PASS\n";
