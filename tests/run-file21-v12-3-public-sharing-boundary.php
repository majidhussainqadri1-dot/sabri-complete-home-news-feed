<?php
/** V12.3 Public Share must be world-public only, not viewer-scoped. */
declare(strict_types=1);
namespace {
    define('ABSPATH', __DIR__);
    function get_current_user_id(): int { return 17; }
    function get_post_type($id): string { return in_array((int)$id, array(2,4), true) ? 'sabri_news' : 'post'; }
    function get_permalink($id): string { return 'https://example.test/' . (int)$id; }
    function get_the_title($id): string { return 'Title ' . (int)$id; }
}
namespace Sabri\HomeNewsFeed {
    final class Phase4Contracts { public const POST_TYPE = 'sabri_news'; }
    final class Phase3FeatureSettings {
        public static function enabled($key): bool { return in_array($key, array('share_enabled','reactions_enabled'), true); }
    }
    final class InteractionPermissions {
        public const REST_NONCE_ACTION = 'unit-test';
        public static function can_view_post($id): bool { return true; }
    }
    final class NextGenerationFeed {
        public static function strict_public_item($id): bool { return (int)$id === 3; }
    }
    final class NewsPolicy {
        public static function workflow_state($id): string { return 'published'; }
        public static function can_public_read($id): bool { return (int)$id === 4; }
    }
    final class Assets { public static function enqueue_interactions(): void {} }
    final class EngagementService {
        public static function summary($id, $user = 0): array { return array(); }
    }
    final class FeedRenderer {
        public static function template($name, $data): string {
            return json_encode(array(
                'enabled' => $data['share_enabled'] ?? null,
                'url' => $data['share_url'] ?? null,
                'title' => $data['share_title'] ?? null,
                'reactions' => $data['reactions_enabled'] ?? null,
            ));
        }
    }
}
namespace {
    require dirname(__DIR__) . '/includes/class-social-runtime.php';
    $failures = array();
    foreach (array(1=>false,2=>false,3=>true,4=>true) as $id=>$expected) {
        $json = \Sabri\HomeNewsFeed\SocialRuntime::render_action_bar($id);
        $result = json_decode((string)$json, true);
        if (!is_array($result) || (bool)($result['enabled'] ?? false) !== $expected) {
            $failures[] = "share gate failure for {$id}";
        }
        if (!is_array($result) || empty($result['reactions'])) {
            $failures[] = "scoped card interaction disappeared for {$id}";
        }
        if (!$expected && (!empty($result['url']) || !empty($result['title']))) {
            $failures[] = "restricted Share URL/title leak for {$id}";
        }
        if ($expected && empty($result['url'])) {
            $failures[] = "public share URL missing for {$id}";
        }
    }
    if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
    echo "V12.3 restricted Feed and News public Share boundary: PASS\n";
}
