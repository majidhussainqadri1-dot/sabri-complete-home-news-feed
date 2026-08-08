#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path):
    return (ROOT / path).read_text(encoding="utf-8")


def write(path, text):
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(text, encoding="utf-8")


def replace_once(path, old, new):
    text = read(path)
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{path}: expected one occurrence, found {count}: {old[:80]!r}")
    write(path, text.replace(old, new, 1))


# Round 2 — privacy lifecycle: NG30 private user state must participate in WordPress export/erase.
privacy = r'''<?php
/**
 * Privacy lifecycle for File 21 next-generation private user state.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Exports and erases private topic/reading/data-saver state owned by File 21. */
final class NextGenerationPrivacy {
	/** Register WordPress privacy callbacks. */
	public static function register() {
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
			add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		}
	}

	/** Register exporter. */
	public static function register_exporter( $exporters ) {
		$exporters = is_array( $exporters ) ? $exporters : array();
		$exporters['sabri-hnf-next-generation-state'] = array(
			'exporter_friendly_name' => __( 'Sabri Home and News Feed private reading preferences', 'sabri-complete-home-news-feed' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/** Register eraser. */
	public static function register_eraser( $erasers ) {
		$erasers = is_array( $erasers ) ? $erasers : array();
		$erasers['sabri-hnf-next-generation-state'] = array(
			'eraser_friendly_name' => __( 'Sabri Home and News Feed private reading preferences', 'sabri-complete-home-news-feed' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/** Export the requesting account's bounded private state without unrelated profile data. */
	public static function export( $email_address, $page = 1 ) {
		unset( $page );
		$user = function_exists( 'get_user_by' ) ? get_user_by( 'email', sanitize_email( $email_address ) ) : false;
		if ( ! $user || empty( $user->ID ) ) {
			return array( 'data' => array(), 'done' => true );
		}
		$state = NextGenerationFeed::user_state( absint( $user->ID ) );
		$data  = array(
			array( 'name' => __( 'Followed topics', 'sabri-complete-home-news-feed' ), 'value' => implode( ', ', (array) $state['topics'] ) ),
			array( 'name' => __( 'Reading progress', 'sabri-complete-home-news-feed' ), 'value' => self::json( $state['progress'] ) ),
			array( 'name' => __( 'Read later post IDs', 'sabri-complete-home-news-feed' ), 'value' => implode( ', ', array_map( 'absint', (array) $state['queue'] ) ) ),
			array( 'name' => __( 'Offline pack post IDs', 'sabri-complete-home-news-feed' ), 'value' => implode( ', ', array_map( 'absint', (array) $state['offline'] ) ) ),
			array( 'name' => __( 'Low-bandwidth preference', 'sabri-complete-home-news-feed' ), 'value' => ! empty( $state['low_bandwidth'] ) ? '1' : '0' ),
			array( 'name' => __( 'Data Saver preference', 'sabri-complete-home-news-feed' ), 'value' => ! empty( $state['data_saver'] ) ? '1' : '0' ),
			array( 'name' => __( 'Last catch-up time (UTC)', 'sabri-complete-home-news-feed' ), 'value' => ! empty( $state['last_catch_up'] ) ? gmdate( 'c', absint( $state['last_catch_up'] ) ) : '' ),
			array( 'name' => __( 'Personal Feed Recipe', 'sabri-complete-home-news-feed' ), 'value' => self::json( $state['recipe'] ) ),
		);
		return array(
			'data' => array(
				array(
					'group_id'    => 'sabri-hnf-next-generation-state',
					'group_label' => __( 'Home and News Feed private reading preferences', 'sabri-complete-home-news-feed' ),
					'item_id'     => 'user-' . absint( $user->ID ),
					'data'        => $data,
				),
			),
			'done' => true,
		);
	}

	/** Erase File 21-owned private preference/progress state for the requesting account. */
	public static function erase( $email_address, $page = 1 ) {
		unset( $page );
		$user = function_exists( 'get_user_by' ) ? get_user_by( 'email', sanitize_email( $email_address ) ) : false;
		if ( ! $user || empty( $user->ID ) ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$had_state = function_exists( 'metadata_exists' ) ? metadata_exists( 'user', absint( $user->ID ), NextGenerationFeed::USER_META ) : true;
		$removed   = function_exists( 'delete_user_meta' ) ? delete_user_meta( absint( $user->ID ), NextGenerationFeed::USER_META ) : false;
		return array(
			'items_removed'  => $had_state ? (bool) $removed : false,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/** Stable JSON for privacy exports. */
	private static function json( $value ) {
		return function_exists( 'wp_json_encode' ) ? (string) wp_json_encode( $value ) : (string) json_encode( $value );
	}
}
'''
write("includes/class-next-generation-privacy.php", privacy)

replace_once(
    "includes/class-plugin.php",
    "NextGenerationIntegrations::class,NextGenerationHardening::class,NextGenerationFeed::class,FeedQuery::class",
    "NextGenerationIntegrations::class,NextGenerationHardening::class,NextGenerationPrivacy::class,NextGenerationFeed::class,FeedQuery::class",
)

# Round 3 — canonical cross-domain visibility for both social posts and Editorial News.
path = "includes/class-next-generation-feed.php"
text = read(path)
visibility_count = text.count("PostMetadata::user_can_view(")
if visibility_count < 8:
    raise SystemExit(f"{path}: expected multiple legacy visibility calls, found {visibility_count}")
text = text.replace("PostMetadata::user_can_view(", "InteractionPermissions::can_view_post(")

# Round 6 — route/context conditional assets, while retaining an explicit extension filter for approved custom mounts.
old = """\tpublic static function enqueue_assets() {\n\t\tif ( ! function_exists( 'wp_enqueue_style' ) || ! function_exists( 'wp_enqueue_script' ) ) {\n"""
new = """\tpublic static function enqueue_assets() {\n\t\tif ( ! self::assets_required_on_current_request() ) {\n\t\t\treturn;\n\t\t}\n\t\tif ( ! function_exists( 'wp_enqueue_style' ) || ! function_exists( 'wp_enqueue_script' ) ) {\n"""
if text.count(old) != 1:
    raise SystemExit("NextGenerationFeed enqueue_assets anchor changed")
text = text.replace(old, new, 1)
marker = "\n\t/** Add explicit data-saving state classes; never infer these preferences. */\n"
helper = r'''
	/** Whether NG30 front-end assets are applicable to this request. */
	public static function assets_required_on_current_request() {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return false;
		}
		if ( function_exists( 'is_front_page' ) && is_front_page() ) {
			return true;
		}
		if ( function_exists( 'is_home' ) && is_home() ) {
			return true;
		}
		if ( function_exists( 'is_singular' ) && is_singular( array( 'post', 'sabri_news' ) ) ) {
			return true;
		}
		if ( function_exists( 'is_post_type_archive' ) && is_post_type_archive( 'sabri_news' ) ) {
			return true;
		}
		global $post;
		if ( is_object( $post ) && isset( $post->post_content ) && function_exists( 'has_shortcode' ) && has_shortcode( (string) $post->post_content, 'sabri_complete_home_feed' ) ) {
			return true;
		}
		$required = false;
		if ( function_exists( 'apply_filters' ) ) {
			$required = (bool) apply_filters( 'sabri_hnf_next_generation_assets_required', false );
		}
		return $required;
	}
'''
if text.count(marker) != 1:
    raise SystemExit("NextGenerationFeed asset helper marker changed")
text = text.replace(marker, "\n" + helper + marker, 1)
write(path, text)

# Round 4 — bounded abuse protection for public and private NG30 read-heavy REST surfaces.
path = "includes/class-next-generation-hardening.php"
text = read(path)
old = """\tpublic static function enqueue_assets() {\n\t\tif ( ! function_exists( 'wp_enqueue_script' ) ) {\n"""
new = """\tpublic static function enqueue_assets() {\n\t\tif ( class_exists( __NAMESPACE__ . '\\\\NextGenerationFeed' ) && ! NextGenerationFeed::assets_required_on_current_request() ) {\n\t\t\treturn;\n\t\t}\n\t\tif ( ! function_exists( 'wp_enqueue_script' ) ) {\n"""
if text.count(old) != 1:
    raise SystemExit("NextGenerationHardening enqueue anchor changed")
text = text.replace(old, new, 1)
old = """\t\t$method = self::method( $request );\n\t\t$route  = self::route( $request );\n\n\t\tif ( 'GET' === $method && preg_match( '#/next-generation/digest/?$#', $route ) ) {\n"""
new = """\t\t$method = self::method( $request );\n\t\t$route  = self::route( $request );\n\n\t\tif ( 'GET' === $method ) {\n\t\t\t$rate_error = self::read_rate_limit_error( $route );\n\t\t\tif ( null !== $rate_error ) {\n\t\t\t\treturn $rate_error;\n\t\t\t}\n\t\t}\n\n\t\tif ( 'GET' === $method && preg_match( '#/next-generation/digest/?$#', $route ) ) {\n"""
if text.count(old) != 1:
    raise SystemExit("NextGenerationHardening pre_dispatch anchor changed")
text = text.replace(old, new, 1)
marker = "\n\t/** Whether this is a File 21 next-generation REST route. */\n"
helper = r'''
	/** Bounded abuse control for read-heavy NG30 REST surfaces. */
	private static function read_rate_limit_error( $route ) {
		if ( ! class_exists( __NAMESPACE__ . '\\Phase5RateLimiter' ) ) {
			return null;
		}
		$rules = array(
			'/next-generation/post/'         => array( 'ng-read-post-context', 30, 60 ),
			'/next-generation/compare'       => array( 'ng-read-compare', 60, 60 ),
			'/next-generation/share-card/'   => array( 'ng-read-share-card', 60, 60 ),
			'/next-generation/stories'       => array( 'ng-read-stories', 120, 60 ),
			'/next-generation/my-topics'     => array( 'ng-read-my-topics', 60, 60 ),
			'/next-generation/catch-up'      => array( 'ng-read-catch-up', 30, 60 ),
			'/next-generation/offline-pack'  => array( 'ng-read-offline-pack', 12, 60 ),
			'/next-generation/digest'        => array( 'ng-read-digest', 12, HOUR_IN_SECONDS ),
		);
		foreach ( $rules as $needle => $rule ) {
			if ( false === strpos( $route, $needle ) ) {
				continue;
			}
			$user_id = function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
			if ( ! Phase5RateLimiter::allow( $rule[0], $rule[1], $rule[2], $user_id ) ) {
				return self::error( 'rate_limited', __( 'Too many requests were attempted. Please wait and try again.', 'sabri-complete-home-news-feed' ), 429 );
			}
			break;
		}
		return null;
	}
'''
if text.count(marker) != 1:
    raise SystemExit("NextGenerationHardening helper marker changed")
text = text.replace(marker, "\n" + helper + marker, 1)
write(path, text)

# Round 5 — File 19 event-intake/dedup contract: deterministic candidate-set identity and trace metadata.
path = "includes/class-next-generation-integrations.php"
text = read(path)
old = r'''	/** File 19-owned digest delivery contract. */
	public static function dispatch_digest_candidates( $user_id, $frequency, array $items ) {
		$user_id   = absint( $user_id );
		$frequency = in_array( $frequency, array( 'daily', 'weekly' ), true ) ? $frequency : 'daily';
		$payload   = array(
			'contract_version' => '1.0.0',
			'owner'            => 'file-21',
			'user_id'          => $user_id,
			'frequency'        => $frequency,
			'items'            => array_slice( $items, 0, 20 ),
			'generated_at_utc' => gmdate( 'c' ),
		);
		if ( function_exists( 'do_action' ) ) {
			do_action( 'sabri_file19_digest_candidates', $payload );
		}
		return $payload;
	}
'''
new = r'''	/** File 19-owned digest delivery contract. */
	public static function dispatch_digest_candidates( $user_id, $frequency, array $items ) {
		$user_id   = absint( $user_id );
		$frequency = in_array( $frequency, array( 'daily', 'weekly' ), true ) ? $frequency : 'daily';
		$items     = array_slice( $items, 0, 20 );
		$window    = 'weekly' === $frequency ? gmdate( 'o-\\WW' ) : gmdate( 'Y-m-d' );
		$item_ids  = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) && ! empty( $item['id'] ) ) {
				$item_ids[] = absint( $item['id'] );
			}
		}
		$fingerprint    = implode( '|', array( 'file-21', $user_id, $frequency, $window, implode( ',', $item_ids ) ) );
		$idempotency_key = 'f21-digest-' . substr( hash( 'sha256', $fingerprint ), 0, 32 );
		$payload         = array(
			'contract_version' => '1.1.0',
			'owner'            => 'file-21',
			'event_type'       => 'File21DigestCandidatesPrepared.v1',
			'event_id'         => $idempotency_key,
			'idempotency_key'  => $idempotency_key,
			'trace_id'         => substr( hash( 'sha256', 'trace|' . $fingerprint ), 0, 32 ),
			'candidate_window' => $window,
			'user_id'          => $user_id,
			'frequency'        => $frequency,
			'items'            => $items,
			'generated_at_utc' => gmdate( 'c' ),
		);
		if ( function_exists( 'do_action' ) ) {
			do_action( 'sabri_file19_digest_candidates', $payload );
		}
		return $payload;
	}
'''
if text.count(old) != 1:
    raise SystemExit("NextGenerationIntegrations digest function changed")
write(path, text.replace(old, new, 1))

# Round 10 — make the new privacy runtime file package-mandatory and wire an executable regression gate.
replace_once(
    "tools/build-release.py",
    '    "includes/class-next-generation-hardening.php",\n    "includes/class-rest-next-generation.php",',
    '    "includes/class-next-generation-hardening.php",\n    "includes/class-next-generation-privacy.php",\n    "includes/class-rest-next-generation.php",',
)

replace_once(
    ".github/workflows/build-test-home-news-feed.yml",
    "            tests/run-file21-ten-review-hardening-tests.php\n",
    "            tests/run-file21-ten-review-hardening-tests.php\n            tests/run-file21-latest-plan-fresh-ten-review-tests.php\n",
)
replace_once(
    ".github/workflows/build-test-home-news-feed.yml",
    "            sabri-complete-home-news-feed/includes/class-next-generation-hardening.php \\\n            sabri-complete-home-news-feed/includes/class-rest-next-generation.php \\\n",
    "            sabri-complete-home-news-feed/includes/class-next-generation-hardening.php \\\n            sabri-complete-home-news-feed/includes/class-next-generation-privacy.php \\\n            sabri-complete-home-news-feed/includes/class-rest-next-generation.php \\\n",
)
replace_once(
    ".github/workflows/file21-official-and-browser-gates.yml",
    "          php tests/run-file21-four-plan-audit-1.0.5.php\n",
    "          php tests/run-file21-four-plan-audit-1.0.5.php\n          php tests/run-file21-latest-plan-fresh-ten-review-tests.php\n",
)

test = r'''<?php
/** Executable source gate for the 2026-08-08 fresh ten-round latest-plan review. */
$root = getenv( 'FILE21_ROOT' ) ?: dirname( __DIR__ );
$files = array(
	'plugin'       => file_get_contents( $root . '/includes/class-plugin.php' ),
	'feed'         => file_get_contents( $root . '/includes/class-next-generation-feed.php' ),
	'hardening'    => file_get_contents( $root . '/includes/class-next-generation-hardening.php' ),
	'privacy'      => file_get_contents( $root . '/includes/class-next-generation-privacy.php' ),
	'integrations' => file_get_contents( $root . '/includes/class-next-generation-integrations.php' ),
	'identity'     => file_get_contents( $root . '/includes/class-canonical-identity-adapter.php' ),
	'builder'      => file_get_contents( $root . '/tools/build-release.py' ),
);
$failures = array();
$check = static function ( $ok, $message ) use ( &$failures ) {
	if ( ! $ok ) { $failures[] = $message; }
};

// Round 1: current File 00 action assurance remains the only privileged identity gate.
$check( false !== strpos( $files['identity'], 'SMC_Contracts::assertions' ), 'Round 1: canonical File 00 assertions missing.' );
$check( false !== strpos( $files['identity'], "['two_factor_ready']" ) && false !== strpos( $files['identity'], "['session_two_factor']" ), 'Round 1: current 2FA/session assurance gate missing.' );

// Round 2: private NG30 state participates in privacy export and erasure.
$check( false !== strpos( $files['plugin'], 'NextGenerationPrivacy::class' ), 'Round 2: privacy module not registered.' );
$check( false !== strpos( $files['privacy'], 'wp_privacy_personal_data_exporters' ) && false !== strpos( $files['privacy'], 'wp_privacy_personal_data_erasers' ), 'Round 2: WordPress privacy hooks missing.' );
$check( false !== strpos( $files['privacy'], 'NextGenerationFeed::USER_META' ) && false !== strpos( $files['privacy'], 'delete_user_meta' ), 'Round 2: NG30 private user state erasure missing.' );

// Round 3: article/News visibility uses the canonical cross-domain gate.
$check( false === strpos( $files['feed'], 'PostMetadata::user_can_view(' ), 'Round 3: legacy social-only visibility call remains in NG30 runtime.' );
$check( substr_count( $files['feed'], 'InteractionPermissions::can_view_post(' ) >= 8, 'Round 3: canonical cross-domain visibility not applied comprehensively.' );

// Round 4: read-heavy public/private REST surfaces have bounded rate gates.
foreach ( array( 'ng-read-post-context', 'ng-read-compare', 'ng-read-share-card', 'ng-read-stories', 'ng-read-offline-pack', 'ng-read-digest' ) as $bucket ) {
	$check( false !== strpos( $files['hardening'], $bucket ), 'Round 4: missing rate-limit bucket ' . $bucket );
}

// Round 5: File 19 receives deterministic event/dedup metadata while retaining delivery ownership.
foreach ( array( 'File21DigestCandidatesPrepared.v1', 'idempotency_key', 'candidate_window', 'trace_id', 'sabri_file19_digest_candidates' ) as $needle ) {
	$check( false !== strpos( $files['integrations'], $needle ), 'Round 5: digest contract missing ' . $needle );
}

// Round 6: File 21 corrective assets are route/context conditional.
$check( false !== strpos( $files['feed'], 'assets_required_on_current_request' ) && false !== strpos( $files['feed'], 'sabri_hnf_next_generation_assets_required' ), 'Round 6: conditional asset policy missing.' );
$check( false !== strpos( $files['hardening'], 'NextGenerationFeed::assets_required_on_current_request()' ), 'Round 6: hardening assets bypass conditional policy.' );

// Round 7: File 25 remains the visual renderer; File 21 remains semantic payload owner.
$check( false !== strpos( $files['integrations'], 'sabri_file25_shareable_knowledge_card' ), 'Round 7: File 25 visual handoff missing.' );
$check( false !== strpos( $files['feed'], "'file25_rendered'" ), 'Round 7: File 25 render projection not exposed.' );

// Round 8: File 26 remains global discovery owner and File 21 only consumes versioned adapter hooks.
$check( false !== strpos( $files['integrations'], 'sabri_file26_why_trending' ) && false !== strpos( $files['integrations'], 'sabri_file26_related_knowledge' ), 'Round 8: File 26 discovery adapters missing.' );

// Round 9: state-changing NG30 action path still requires current identity assurance and nonce enforcement.
$check( false !== strpos( $files['feed'], 'CanonicalIdentityAdapter::current_action_ready' ), 'Round 9: current identity assurance missing from user action.' );
$check( false !== strpos( $files['hardening'], 'InteractionPermissions::nonce_valid' ), 'Round 9: nonce enforcement missing from protected NG30 path.' );

// Round 10: new runtime file is deterministic-package mandatory.
$check( false !== strpos( $files['builder'], 'includes/class-next-generation-privacy.php' ), 'Round 10: new privacy runtime file is not package-mandatory.' );

if ( $failures ) {
	foreach ( $failures as $failure ) { fwrite( STDERR, "FAIL: {$failure}\n" ); }
	exit( 1 );
}
echo "File 21 fresh ten-round latest-plan source gate: PASS\n";
'''
write("tests/run-file21-latest-plan-fresh-ten-review-tests.php", test)

# Self-remove the one-shot apply workflow after it executes; retain this patcher for audit reproducibility.
workflow = ROOT / ".github/workflows/file21-apply-latest-plan-corrections.yml"
if workflow.exists():
    workflow.unlink()

print(f"Applied latest-plan fresh ten-round corrections; replaced {visibility_count} NG30 visibility call(s).")
