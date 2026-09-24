<?php
/** Contract test for first-class File 22 structured File 21 gateway adapters. */

$root = dirname( __DIR__ );
$files = array(
	'base' => $root . '/includes/class-universal-composer-structured-workflow-adapter.php',
	'case' => $root . '/includes/class-universal-composer-clinical-case-adapter.php',
	'research' => $root . '/includes/class-universal-composer-research-adapter.php',
	'poll' => $root . '/includes/class-universal-composer-poll-adapter.php',
	'bridge' => $root . '/includes/class-universal-composer-bridge.php',
	'workflow' => $root . '/includes/class-universal-composer-workflow-adapter.php',
	'plugin' => $root . '/includes/class-plugin.php',
);
$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
	if ( ! $condition ) {
		$failures[] = $message;
	}
};
$sources = array();
foreach ( $files as $key => $path ) {
	$sources[ $key ] = file_get_contents( $path );
	$assert( false !== $sources[ $key ], 'Unreadable contract file: ' . $key );
}
$assert( false !== strpos( (string) $sources['case'], "return 'patient_case';" ), 'Patient Case gateway key missing.' );
$assert( false !== strpos( (string) $sources['case'], "return 'clinical-case';" ), 'Patient Case native feed mapping missing.' );
$assert( false !== strpos( (string) $sources['research'], "return 'research_publication';" ), 'Research gateway key missing.' );
$assert( false !== strpos( (string) $sources['poll'], "return 'poll';" ), 'Poll gateway key missing.' );
$assert( false !== strpos( (string) $sources['base'], '\$payload[\'feed_type\']' ), 'Structured adapters do not force native File 21 feed ownership.' );
$assert( false !== strpos( (string) $sources['bridge'], 'new UniversalComposerClinicalCaseAdapter()' ), 'Patient Case adapter is not registered.' );
$assert( false !== strpos( (string) $sources['bridge'], 'new UniversalComposerResearchAdapter()' ), 'Research adapter is not registered.' );
$assert( false !== strpos( (string) $sources['bridge'], 'new UniversalComposerPollAdapter()' ), 'Poll adapter is not registered.' );
$assert( false !== strpos( (string) $sources['workflow'], "'clinical-case'" ) && false !== strpos( (string) $sources['workflow'], "'research'" ) && false !== strpos( (string) $sources['workflow'], "'poll'" ), 'Native workflow allow-list is incomplete.' );
$assert( false !== strpos( (string) $sources['plugin'], "'package_version'" ) && false !== strpos( (string) $sources['plugin'], "'runtime_version'" ), 'Package/runtime version identity remains ambiguous.' );
if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}
echo "File 21 structured File 22 gateway and release identity contracts passed.\n";
