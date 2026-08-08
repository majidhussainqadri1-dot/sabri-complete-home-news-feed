<?php
/**
 * Test runner wrapper.
 *
 * Keeps npm-installed third-party dependencies outside the repository while
 * the plugin-owned static safety test traverses the project tree. The folder
 * is restored before this PHP process exits, including failure/exit paths.
 *
 * @package SabriCompleteHomeNewsFeed
 */

$root         = dirname( __DIR__ );
$node_modules = $root . '/node_modules';
$isolated     = '';

/*
 * The lean behavior harness intentionally does not bootstrap WordPress core.
 * File 21 runtime code may rely on WordPress' standard time constants, so the
 * wrapper supplies their canonical values only when the harness lacks them.
 */
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 60 * 60 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS );
}

$restore_node_modules = static function () use ( $node_modules, &$isolated ) {
	if ( '' === $isolated || ! is_dir( $isolated ) || file_exists( $node_modules ) ) {
		return;
	}

	if ( ! rename( $isolated, $node_modules ) ) {
		fwrite( STDERR, "Unable to restore node_modules after the PHP test runner.\n" );
	}
};

if ( is_dir( $node_modules ) ) {
	$isolated = dirname( $root ) . '/.sabri-node-modules-' . getmypid();
	if ( file_exists( $isolated ) ) {
		throw new RuntimeException( 'Temporary node_modules isolation path already exists.' );
	}
	if ( ! rename( $node_modules, $isolated ) ) {
		throw new RuntimeException( 'Unable to isolate node_modules before plugin static-safety tests.' );
	}
	register_shutdown_function( $restore_node_modules );
}

try {
	require $root . '/tests/run-tests.php';
	require $root . '/tests/run-safe-boot-tests.php';
} finally {
	$restore_node_modules();
}
