<?php
/** Runtime regression for File 21 public content integrity. */
declare(strict_types=1);

namespace {
    define('ABSPATH', __DIR__ . '/');
    define('SABRI_HNF_URL', 'https://example.test/wp-content/plugins/sabri-complete-home-news-feed/');

    $GLOBALS['hnf_posts'] = [
        42 => (object) [
  'ID' => 42,
  'post_title' => 'Controlled File 21 post',
  'post_excerpt' => '',
  'post_content' => "This is the controlled canonical body.\n\n## Sluggish Liver\n\n**Cellular Inflammation** and *Sulphur* must be formatted.",
        ],
        43 => (object) [
  'ID' => 43,
  'post_title' => 'Ordinary WordPress content',
  'post_excerpt' => '',
  'post_content' => 'Ordinary WordPress content.',
        ],
        44 => (object) [
  'ID' => 44,
  'post_title' => '# Sabri Homeopathic Empirical Treatment for High Cholesterol',
  'post_excerpt' => '',
  'post_content' => "*Sulphur* and *Chelidonium*: **A Summary of My Discovery and Research**",
        ],
    ];
    $GLOBALS['hnf_meta'] = [42 => ['_sabri_feed_type' => 'standard-post'], 43 => [], 44 => []];
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
    function get_the_terms($post_id, $taxonomy) { return 42 === (int)$post_id && 'sabri_feed_topic' === $taxonomy ? [(object)['term_id' => 101]] : []; }
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
    $assert(count($GLOBALS['hnf_filters']) >= 6, 'Public filters, including title repair, must register.');
    $assert(count($GLOBALS['hnf_actions']) >= 1, 'Public asset action must register.');

    $excerpt = PublicContentIntegrity::canonical_excerpt('Wrong global text.', $GLOBALS['hnf_posts'][42]);
    $assert(str_contains($excerpt, 'controlled canonical body'), 'Excerpt must use the explicitly requested canonical post body.');
    $assert(!str_contains($excerpt, '**') && !str_contains($excerpt, '##') && !str_contains($excerpt, '*Sulphur*'), 'Excerpt must not expose raw Markdown markers.');
    $assert('Ordinary original excerpt' === PublicContentIntegrity::canonical_excerpt('Ordinary original excerpt', $GLOBALS['hnf_posts'][43]), 'Ordinary posts must remain untouched.');

    $default_only = [(object)['term_id' => 9]];
    $assert([] === PublicContentIntegrity::filter_automatic_default_category($default_only, 42, 'category'), 'Sole automatic default category must be hidden when an explicit File 21 topic exists.');
    $assert(1 === count(PublicContentIntegrity::filter_automatic_default_category($default_only, 43, 'category')), 'Ordinary post categories must remain untouched.');

    $source = '<p>## Sluggish Liver</p><figure class="wp-block-embed"><iframe src="https://example.test/embed"></iframe></figure><p>**Cellular Inflammation** and *Sulphur* remain important.</p><pre>**literal** *literal*</pre>';
    $formatted = PublicContentIntegrity::format_single_content($source);
    $assert(str_contains($formatted, '<h3>Sluggish Liver</h3>'), 'Markdown heading must become semantic heading.');
    $assert(str_contains($formatted, '<strong>Cellular Inflammation</strong>'), 'Markdown bold must become strong text.');
    $assert(str_contains($formatted, '<em>Sulphur</em>'), 'Single-asterisk emphasis must become em text.');
    $assert(str_contains($formatted, '<pre>**literal** *literal*</pre>'), 'Code/preformatted text must remain literal.');
    $assert(str_contains($formatted, '<iframe'), 'Existing embed output must be preserved.');

    $wrapped = PublicContentIntegrity::wrap_single_content($formatted);
    $assert(str_contains($wrapped, 'sabri-hnf-single-content'), 'Managed content must receive containment wrapper.');
    $assert($wrapped === PublicContentIntegrity::wrap_single_content($wrapped), 'Containment wrapper must be idempotent.');
    $classes = PublicContentIntegrity::body_classes(['single']);
    $assert(in_array('sabri-hnf-managed-single', $classes, true), 'Managed page must expose managed body class.');
    $assert(in_array('sabri-hnf-content-integrity-single', $classes, true), 'Managed page must expose broader integrity body class.');

    $GLOBALS['hnf_queried_id'] = 44;
    $clean_title = PublicContentIntegrity::format_single_title($GLOBALS['hnf_posts'][44]->post_title, 44);
    $assert('Sabri Homeopathic Empirical Treatment for High Cholesterol' === $clean_title, 'Leading Markdown heading marker must be removed from the queried title.');
    $legacy_content = PublicContentIntegrity::format_single_content('<p>*Sulphur* and *Chelidonium*: **A Summary**</p>');
    $assert(str_contains($legacy_content, '<em>Sulphur</em>') && str_contains($legacy_content, '<em>Chelidonium</em>') && str_contains($legacy_content, '<strong>A Summary</strong>'), 'Legacy public article Markdown must render semantically.');
    $legacy_classes = PublicContentIntegrity::body_classes(['single']);
    $assert(in_array('sabri-hnf-content-integrity-single', $legacy_classes, true), 'Legacy Markdown article must expose the Shell integration class.');
    $assert(!in_array('sabri-hnf-managed-single', $legacy_classes, true), 'Legacy article must not be falsely claimed as File 21-owned.');

    PublicContentIntegrity::enqueue_single_assets();
    $assert(isset($GLOBALS['hnf_styles']['sabri-hnf-public-content-integrity']), 'Integrity stylesheet must enqueue for legacy Markdown articles.');
    $assert('1.0.3.2-public-content-r3' === $GLOBALS['hnf_styles']['sabri-hnf-public-content-integrity'][2], 'Corrective asset version must invalidate stale caches.');
    $assert([] === $GLOBALS['hnf_styles']['sabri-hnf-public-content-integrity'][1], 'Integrity CSS must not depend on an absent feed stylesheet.');

    $GLOBALS['hnf_queried_id'] = 43;
    $assert('Ordinary WordPress content' === PublicContentIntegrity::format_single_title('Ordinary WordPress content', 43), 'Ordinary title must remain untouched.');
    $assert('Ordinary body' === PublicContentIntegrity::format_single_content('Ordinary body'), 'Ordinary body must remain untouched.');

    if ($failures) {
        fwrite(STDERR, "File21 public content integrity: {$passed} PASS, " . count($failures) . " FAIL\n- " . implode("\n- ", $failures) . "\n");
        exit(1);
    }
    echo "File21 public content integrity: {$passed} PASS, 0 FAIL\n";
}
