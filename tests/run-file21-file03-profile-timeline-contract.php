<?php
$root = dirname(__DIR__);
$src = file_get_contents($root . '/includes/class-profile-timeline.php');
$needles = array(
    "sabri_file21_profile_timeline_provider_health_v1",
    "sabri_file21_profile_timeline_items_v1",
    "file03_provider_health",
    "file03_provider_items",
    "'contract_version' => '1.0.0'",
    "PostMetadata::user_can_view",
    "PostMetadata::visibility",
    "'author_user_id' => $user_id",
    "'canonical_id' => 'file21-post-'",
);
foreach ($needles as $needle) {
    if (strpos($src, $needle) === false) {
        fwrite(STDERR, "Missing File 03 timeline contract token: {$needle}\n");
        exit(1);
    }
}
if (strpos($src, "'visibility' => 'public'") !== false && strpos($src, "file03_provider_items") !== false) {
    // This guard prevents accidentally flattening viewer-authorized non-public content into public.
    $section = substr($src, strpos($src, 'public static function file03_provider_items'), 5000);
    if (strpos($section, "'visibility' => 'public'") !== false) {
        fwrite(STDERR, "File 03 adapter must preserve File 21 visibility instead of forcing public.\n");
        exit(1);
    }
}
echo "File 21 -> File 03 timeline owner adapter contract: PASS\n";
