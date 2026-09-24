<?php
/**
 * File 21 / File 22 Founder gateway regression.
 *
 * This is intentionally a source-contract test: File 22 may use the subject
 * wrapper's required_capability() only as a coarse authenticated registry gate,
 * while File 21 remains the final authorization owner through the inherited
 * native workflow can_create() policy.
 */

$root = dirname( __DIR__ );
$wrapper_path = $root . '/includes/class-universal-composer-subject-schema-adapter.php';
$workflow_path = $root . '/includes/class-universal-composer-workflow-adapter.php';
$publication_path = $root . '/includes/class-universal-composer-publication-adapter.php';
$permissions_path = $root . '/includes/class-composer-permissions.php';

$failures = array();

$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};

$wrapper = file_get_contents( $wrapper_path );
$workflow = file_get_contents( $workflow_path );
$publication = file_get_contents( $publication_path );
$permissions = file_get_contents( $permissions_path );

$assert( false !== $wrapper, 'Subject schema wrapper must be readable.' );
$assert( false !== $workflow, 'Native workflow adapter must be readable.' );
$assert( false !== $publication, 'Native publication adapter must be readable.' );
$assert( false !== $permissions, 'Composer permissions policy must be readable.' );

if ( false !== $wrapper ) {
	$assert(
		false !== strpos( $wrapper, "public function required_capability(): string { return 'read'; }" ),
		'File 22 wrapper must expose only the coarse authenticated read gate.'
	);
	$assert(
		false !== strpos( $wrapper, 'public function can_create( int $user_id ): bool { return $this->delegate->can_create( $user_id ); }' ),
		'Wrapper can_create() must delegate to the native File 21 authorization boundary.'
	);
}

if ( false !== $publication ) {
	$assert(
		false !== strpos( $publication, 'extends UniversalComposerWorkflowAdapter' ),
		'Publication adapter must inherit the reviewed native workflow authorization boundary.'
	);
}

if ( false !== $workflow ) {
	$assert(
		false !== strpos( $workflow, 'public function can_create( int $user_id ): bool' )
		&& false !== strpos( $workflow, 'ComposerPermissions::user_can_create( $user_id, Settings::get() )' ),
		'Native workflow adapter must use ComposerPermissions::user_can_create().' 
	);
}

if ( false !== $permissions ) {
	$assert(
		false !== strpos( $permissions, 'CanonicalIdentityAdapter::current_action_ready' ),
		'Composer permissions must revalidate current File 00 action readiness.'
	);
	$assert(
		false !== strpos( $permissions, 'sabri_feed_create_posts' ),
		'Native File 21 create capability must remain part of authorization policy.'
	);
	$assert(
		false !== strpos( $permissions, 'manage_options' ),
		'Canonical Administrator/Founder fallback must remain available to native policy.'
	);
}

if ( $failures ) {
	fwrite( STDERR, "File 21/File 22 Founder gateway regression FAILED:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "File 21/File 22 Founder gateway regression: PASS\n";
