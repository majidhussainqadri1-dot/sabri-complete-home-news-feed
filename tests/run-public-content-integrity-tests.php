<?php
/** Runtime regression for File 21 public content integrity. */
declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__ . '/');
    define('SABRI_HNF_URL', 'https://example.test/wp-content/plugins/sabri-complete-home-news-feed/');

    $GLOBALS['hnf_posts'] = [
        42 => (object) [
            'ID' => 42,
            'post_excerpt' => '',
            'post_content' => "This is the controlled canonical body for File 22 and File 21.\n\n## Sluggish Liver\n\n**Cellular Inflammation** must be formatted.",
        ],
        43 => (object) [
            'ID' => 43,
            'post_excerpt' => '',
            'post_content' => 'Ordinary WordPress content.',
        ],
    ];
    $GLOBALS['hnf_meta'] = [42 => ['_sabri_feed_type' => 'standard-post'], 43 => []];
    $GLOBALS['hnf_default_category'] = 9;
    $GLOBALS['hnf_queried_id'] = 42;
    $GLOBALS['hnf_filters'] = [];
    $GLOBALS['hnf_actions'] = [];
    $GLOBALS['hnf_styles'] = [];

    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['hnf_filters'][] = [$hook, $callback, $priority, $accepted_args]; }
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { $GLOBALS['hnf_actions'][] = [$hook, $callback, $priority, $accepted_args]; }
    function get_post_meta($post_id, $key, $single = true) { return $GLOBALS['hnf_meta'][(int)$post_id][$key] ?? ''; }
    function get_post($post_id) { return $GLOBALS['hnf_posts'][(int)$post_id] ?? null; }
    function get_option($key, $default = false) { return 'default_category' === $key ? $GLOBALS['hnf_default_category'] : $default; }
    function get_the_terms($post_id, $taxonomy) {
        if (42 === (int)$post_id && 'sabri_feed_topic' === $taxonomy) {
            return [(object)['term_id' => 101, 'name' => 'Platform Testing']];
        }
        return [];
    }
    function strip_shortcodes($text) { return preg_replace('/\[[^\]]+\]/', '', (string)$text); }
    function wp_strip_all_tags($text, $remove_breaks = false) { $value = strip_tags((string)$text); return $remove_breaks ? preg_replace('/[\r\n\t ]+/', ' ', $value) : $value; }
    function wp_trim_words($text, $num_words = 55, $more = null) { $words = preg_split('/\s+/', trim((string)$text)); return count($words) > $num_words ? implode(' ', array_slice($words, 0, $num_words)) . ($more ?? '…') : implode(' ', $words); }
    function wp_kses_post($html) { throw new \RuntimeException('Whole rendered content must not be re-sanitized.'); }
    function in_the_loop() { return true; }
    function is_main_query() { return true; }
    function get_the_ID() { return (int)$GLOBALS['hnf_queried_id']; }
    function get_queried_object_id() { return (int)$GLOBALS['hnf_queried_id']; }
    function wp_enqueue_style($handle, $src = '', $deps = [], $version = false) { $GLOBALS['hnf_styles'][$handle] = [$src, $deps, $version]; }
}

namespace Sabri\HomeNewsFeed {
    final class PostMetadata { public const META_TYPE = '_sabri_feed_type'; }
    final class HomeIntegration { public static function is_single_post_request() { return true; } }
    final class Assets { public static function enqueue_interactions() {} }

    require dirname(__DIR__) . '/includes/class-public-content-integrity.php';

    $failures = [];
    $passed = 0;
    $assert = static function ($condition, $message) use (&$failures, &$passed) {
        if ($condition) { ++$passed; return; }
        $failures[] = $message;
    };

    PublicContentIntegrity::register();
    $assert(count($GLOBALS['hnf_filters']) >= 5, 'Public filters must register.');
    $assert(count($GLOBALS['hnf_actions']) >= 1, 'Public asset action must register.');

    $wrong_global = 'Global Cloud Clinic Network unrelated text.';
    $excerpt = PublicContentIntegrity::canonical_excerpt($wrong_global, $GLOBALS['hnf_posts'][42]);
    $assert(str_contains($excerpt, 'controlled canonical body'), 'Excerpt must use the explicitly requested canonical post body.');
    $assert(!str_contains($excerpt, 'Global Cloud Clinic'), 'Excerpt must reject unrelated global-loop content.');
    $assert(!str_contains($excerpt, '**') && !str_contains($excerpt, '##'), 'Excerpt must not expose raw Markdown markers.');

    $ordinary = PublicContentIntegrity::canonical_excerpt('Ordinary original excerpt', $GLOBALS['hnf_posts'][43]);
    $assert('Ordinary original excerpt' === $ordinary, 'Ordinary WordPress posts must remain untouched.');

    $default_only = [(object)['term_id' => 9, 'name' => 'Successful case with homeopathic medicine']];
    $filtered = PublicContentIntegrity::filter_automatic_default_category($default_only, 42, 'category');
    $assert([] === $filtered, 'Sole automatic default category must not be projected when an explicit File 21 topic exists.');

    $mixed_terms = [
        (object)['term_id' => 9, 'name' => 'Successful case with homeopathic medicine'],
        (object)['term_id' => 10, 'name' => 'Explicit Category'],
    ];
    $mixed = PublicContentIntegrity::filter_automatic_default_category($mixed_terms, 42, 'category');
    $assert(2 === count($mixed), 'Mixed or explicit Category sets must remain unchanged.');
    $unchanged_terms = PublicContentIntegrity::filter_automatic_default_category($default_only, 43, 'category');
    $assert(1 === count($unchanged_terms), 'Ordinary post categories must remain untouched.');

    $source = '<p>## Sluggish Liver</p><figure class="wp-block-embed"><iframe src="https://example.test/embed"></iframe></figure><p>**Cellular Inflammation** remains important.</p>';
    $formatted = PublicContentIntegrity::format_single_content($source);
    $assert(str_contains($formatted, '<h3>Sluggish Liver</h3>'), 'Markdown heading must become semantic heading.');
    $assert(str_contains($formatted, '<strong>Cellular Inflammation</strong>'), 'Markdown bold must become strong text.');
    $assert(!str_contains($formatted, '**'), 'Raw Markdown bold markers must not remain.');
    $assert(str_contains($formatted, '<figure class="wp-block-embed">') && str_contains($formatted, '<iframe'), 'Existing block and embed output must be preserved.');

    $wrapped = PublicContentIntegrity::wrap_single_content($formatted);
    $assert(str_contains($wrapped, 'sabri-hnf-single-content'), 'Managed single content must receive containment wrapper.');
    $wrapped_twice = PublicContentIntegrity::wrap_single_content($wrapped);
    $assert($wrapped === $wrapped_twice, 'Containment wrapper must be idempotent.');

    $classes = PublicContentIntegrity::body_classes(['single']);
    $assert(in_array('sabri-hnf-managed-single', $classes, true), 'Managed single page must expose integration body class.');
    PublicContentIntegrity::enqueue_single_assets();
    $assert(isset($GLOBALS['hnf_styles']['sabri-hnf-public-content-integrity']), 'Integrity stylesheet must enqueue.');
    $assert('1.0.3.2' === $GLOBALS['hnf_styles']['sabri-hnf-public-content-integrity'][2], 'Integrity stylesheet must use corrective cache-busting version.');

    if ($failures) {
        fwrite(STDERR, "File21 public content integrity: {$passed} PASS, " . count($failures) . " FAIL\n- " . implode("\n- ", $failures) . "\n");
        exit(1);
    }
    echo "File21 public content integrity: {$passed} PASS, 0 FAIL\n";
}
