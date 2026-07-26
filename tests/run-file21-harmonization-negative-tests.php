<?php
/** Negative contracts for File 21 harmonization. */
$root = dirname( __DIR__ );
$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) { if ( ! $condition ) { $failures[] = $message; } };

$permissions = file_get_contents( $root . '/includes/class-composer-permissions.php' );
$assert( false !== strpos( $permissions, 'current_actor_matches' ), 'Cross-user capability borrowing guard is missing.' );
$assert( false !== strpos( $permissions, 'is_student_or_patient' ), 'Student/patient publishing denial is missing.' );
$assert( false !== strpos( $permissions, 'CanonicalIdentityAdapter::can_publish_immediately' ), 'Canonical immediate-publishing authority is missing.' );
$assert( false === strpos( $permissions, "|| self::user_has_role_group( $user_id, 'editorial_roles'" ), 'Editorial Newsroom roles still receive implicit social-Composer authority.' );

$identity = file_get_contents( $root . '/includes/class-canonical-identity-adapter.php' );
$assert( false !== strpos( $identity, "'trusted'" ), 'Trusted verified-doctor policy is missing.' );
$assert( false !== strpos( $identity, '_smc_trusted_publisher' ), 'Membership Core trust marker is ignored.' );

$feed = file_get_contents( $root . '/includes/class-feed-query.php' );
foreach ( array( 'MAX_RANK_SCAN', 'PostMetadata::review_state_meta_clause', 'PostMetadata::META_VISIBILITY', 'author__in', 'total_is_complete', '$query_count' ) as $needle ) {
	$assert( false !== strpos( $feed, $needle ), 'Bounded/authorized Feed query contract missing: ' . $needle );
}
$assert( false === strpos( $feed, 'posts_per_page' . "' => -1" ), 'Feed ranking query is unbounded.' );

$search = file_get_contents( $root . '/includes/class-search-provider-registry.php' );
$assert( false !== strpos( $search, 'MAX_QUERY_LENGTH' ), 'Search query length bound is missing.' );
$assert( false !== strpos( $search, 'MAX_RESULTS_PER_PROVIDER' ), 'Search result bound is missing.' );
$assert( false !== strpos( $search, 'PostMetadata::user_can_view' ), 'Search object-level authorization is missing.' );
$assert( false === strpos( $search, "array( 'search' => $query" ), 'Search still uses the rejected News query parameter.' );

$migration = file_get_contents( $root . '/includes/class-legacy-publication-migration.php' );
foreach ( array( 'actor_can_migrate', 'Snapshot::capture_before_mutation', 'already_migrated', 'interaction_report', 'interaction_provider_requested', 'source_deleted', 'destructive', 'automatic' ) as $needle ) {
	$assert( false !== strpos( $migration, $needle ), 'Migration negative safeguard missing: ' . $needle );
}
$assert( false === strpos( $migration, 'posts_per_page' . "' => -1" ), 'Migration contains an unbounded query.' );
$assert( false === stripos( $migration, 'TRUNCATE ' ), 'Migration contains TRUNCATE.' );
$assert( false === stripos( $migration, 'DROP TABLE' ), 'Migration contains DROP TABLE.' );
$assert( false === stripos( $migration, 'DELETE FROM' ), 'Migration contains destructive SQL deletion.' );
$assert( false === stripos( $migration, 'wp_delete_post' ), 'Migration deletes a legacy or migrated post.' );

$interaction = file_get_contents( $root . '/includes/class-legacy-interaction-migration-adapter.php' );
foreach ( array( 'MAX_PROVIDER_RECORDS', 'actor_can_migrate', 'target_provenance_failed', 'normalize_report', "'source_deleted' => false", "'automatic' => false" ) as $needle ) {
	$assert( false !== strpos( $interaction, $needle ), 'Interaction provider negative safeguard missing: ' . $needle );
}
$assert( false === stripos( $interaction, 'DELETE FROM' ), 'Interaction adapter contains destructive SQL.' );
$assert( false === stripos( $interaction, 'wp_delete_post' ), 'Interaction adapter deletes source content.' );

$rollback = file_get_contents( $root . '/includes/class-legacy-publication-rollback.php' );
foreach ( array( 'target_belongs_to_legacy', "'post_status' => 'private'", "'status'] = 'rolled_back'", "'destructive' => false", "'automatic' => false" ) as $needle ) {
	$assert( false !== strpos( $rollback, $needle ), 'Rollback safeguard missing: ' . $needle );
}
$assert( false === stripos( $rollback, 'DELETE FROM' ), 'Rollback contains destructive SQL.' );
$assert( false === stripos( $rollback, 'wp_delete_post' ), 'Rollback deletes source or target content.' );

$settings = file_get_contents( $root . '/includes/class-settings.php' );
$assert( false !== strpos( $settings, 'preserving unknown future keys' ), 'Settings no longer state future-key preservation.' );
$assert( false !== strpos( $settings, 'sanitize_function_map' ), 'Integration function-map sanitizer is missing.' );
$assert( false !== strpos( $settings, '(?:\\\\[A-Za-z_]' ), 'Namespaced callback validation is missing or malformed.' );

$diagnostics = file_get_contents( $root . '/includes/class-harmonization-diagnostics.php' );
$assert( false !== strpos( $diagnostics, "'live_release_ready' => false" ), 'Diagnostics may falsely claim live release readiness.' );
$assert( false !== strpos( $diagnostics, "'automatic_merge' => false" ), 'Diagnostics do not block automatic merge.' );
$assert( false !== strpos( $diagnostics, "'automatic_deployment' => false" ), 'Diagnostics do not block automatic deployment.' );

$viral = file_get_contents( $root . '/includes/class-viral-ranking-signals.php' );
$assert( false !== strpos( $viral, 'log( 1 +' ), 'Viral ranking lacks logarithmic anti-abuse scaling.' );
$assert( false !== strpos( $viral, 'max( -1000, min( 10000' ), 'Viral score is not bounded.' );

if ( $failures ) { fwrite( STDERR, implode( "\n", $failures ) . "\n" ); exit( 1 ); }
echo "File 21 harmonization negative tests passed.\n";
