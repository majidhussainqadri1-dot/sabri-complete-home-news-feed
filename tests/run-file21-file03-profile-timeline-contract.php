<?php
$src = file_get_contents(dirname(__DIR__) . '/includes/class-profile-timeline.php');
$needles = array(
    'sabri_file21_profile_timeline_provider_health_v1',
    'sabri_file21_profile_timeline_items_v1',
    'file03_provider_health',
    'file03_provider_items',
    'FILE03_PROVIDER_CONTRACT',
    'PostMetadata::user_can_view',
    'PostMetadata::visibility',
    "'author_user_id' => $user_id",
    "'canonical_id' => 'file21-post:'",
);
foreach ($needles as $needle) {
    if (strpos($src, $needle) === false) {
        fwrite(STDERR, "Missing File 03 timeline contract token: {$needle}\n");
        exit(1);
    }
}
$section = substr($src, strpos($src, 'public static function file03_provider_items'), 9000);
if (strpos($section, "'visibility' => 'public'") !== false) {
    fwrite(STDERR, "File 03 adapter must preserve File 21 visibility instead of forcing public.\n");
    exit(1);
}
if (strpos($section, "$query_args['author'] = $user_id") === false || strpos($section, "$query_args['post_status'] = array( 'publish' )") === false) {
    fwrite(STDERR, "Owner/security query bounds are not reasserted after extension filters.\n");
    exit(1);
}
echo "File 21 -> File 03 timeline owner adapter contract: PASS\n";
