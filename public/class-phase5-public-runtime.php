<?php
/**
 * Phase 5 public presentation hooks.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Renders Breaking News, approved sources/history, and public submission entry. */
final class Phase5PublicRuntime {
	private static $breaking_rendered = false;

	/** Register public hooks. */
	public static function register() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'loop_start', array( __CLASS__, 'breaking_strip' ), 5, 1 );
		add_action( 'sabri_news_after_article', array( __CLASS__, 'article_supplements' ), 10, 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'init', array( __CLASS__, 'shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_submission_post' ) );
	}

	/** Load assets only for an accepted Home/News presentation context. */
	public static function assets() {
		if ( ! self::needed() ) {
			return;
		}
		wp_enqueue_style( 'sabri-hnf-phase5-public', SABRI_HNF_URL . 'assets/css/phase5-public.css', array( 'sabri-hnf-news' ), SABRI_HNF_VERSION );
		wp_enqueue_script( 'sabri-hnf-phase5-public', SABRI_HNF_URL . 'assets/js/phase5-public.js', array(), SABRI_HNF_VERSION, true );
	}

	/** Render one Breaking News strip only in the main Home or News loop. */
	public static function breaking_strip( $query = null ) {
		if ( self::$breaking_rendered || ! self::is_main_home_or_news_context( $query ) ) {
			return;
		}
		if ( ! class_exists( __NAMESPACE__ . '\\Phase5FeatureSettings' ) || ! Phase5FeatureSettings::enabled( 'breaking_news_enabled' ) ) {
			return;
		}
		$items = BreakingNewsService::active_public();
		if ( empty( $items ) ) {
			return;
		}
		$file = SABRI_HNF_PATH . 'templates/news-breaking-strip.php';
		if ( ! is_readable( $file ) ) {
			return;
		}
		self::$breaking_rendered = true;
		include $file;
	}

	/** Render approved sources and correction history for one public article. */
	public static function article_supplements( $article ) {
		if ( ! is_array( $article ) || empty( $article['id'] ) ) {
			return;
		}
		$sources = NewsDistribution::public_sources( (int) $article['id'] );
		$history = NewsDistribution::public_history( (int) $article['id'] );
		$file = SABRI_HNF_PATH . 'templates/news-sources-history.php';
		if ( is_readable( $file ) ) {
			include $file;
		}
	}

	/** Register the controlled public submission shortcode. */
	public static function shortcode() {
		if ( function_exists( 'add_shortcode' ) ) {
			add_shortcode( 'sabri_news_submission_portal', array( __CLASS__, 'render_submission_portal' ) );
		}
	}

	/** Render the submission portal only for an authenticated authorized submitter. */
	public static function render_submission_portal() {
		if ( ! Phase5FeatureSettings::enabled( 'submissions_enabled' ) || ! function_exists( 'is_user_logged_in' ) || ! is_user_logged_in() || ! current_user_can( 'submit_editorial_news' ) ) {
			return '<p>' . esc_html__( 'Submission portal is unavailable.', 'sabri-complete-home-news-feed' ) . '</p>';
		}
		ob_start();
		include SABRI_HNF_PATH . 'templates/news-submission-portal.php';
		return (string) ob_get_clean();
	}

	/** Handle a nonce-protected submission request. */
	public static function handle_submission_post() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) || empty( $_POST['sabri_phase5_nonce'] ) ) {
			return;
		}
		if ( ! function_exists( 'wp_verify_nonce' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sabri_phase5_nonce'] ) ), 'sabri_phase5_submission' ) ) {
			return;
		}
		$sources = isset( $_POST['source_urls'] ) ? preg_split( '/\r?\n/', wp_unslash( $_POST['source_urls'] ) ) : array();
		$input = array(
			'title' => isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : '',
			'summary' => isset( $_POST['summary'] ) ? wp_unslash( $_POST['summary'] ) : '',
			'body' => isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : '',
			'source_urls' => is_array( $sources ) ? $sources : array(),
			'declarations' => array(
				'owns_text' => ! empty( $_POST['owns_text'] ),
				'patient_identifiers_absent' => ! empty( $_POST['patient_identifiers_absent'] ),
				'conflicts_declared' => ! empty( $_POST['conflicts_declared'] ),
				'sponsorship_declared' => ! empty( $_POST['sponsorship_declared'] ),
				'ai_assistance_declared' => ! empty( $_POST['ai_assistance_declared'] ),
			),
		);
		$result = SubmissionService::create( $input );
		if ( function_exists( 'wp_safe_redirect' ) ) {
			$referer = function_exists( 'wp_get_referer' ) ? wp_get_referer() : '';
			wp_safe_redirect( add_query_arg( 'sabri_submission', ! empty( $result['success'] ) ? 'saved' : 'error', $referer ? $referer : home_url( '/' ) ) );
			exit;
		}
	}

	/** Whether the current request may need Phase 5 public assets. */
	private static function needed() {
		$context = class_exists( __NAMESPACE__ . '\\NewsPublicRuntime' ) ? NewsPublicRuntime::context() : array();
		if ( ! empty( $context['route'] ) ) {
			return true;
		}
		if ( function_exists( 'is_front_page' ) && is_front_page() && Phase5FeatureSettings::enabled( 'breaking_news_enabled' ) ) {
			return ! empty( BreakingNewsService::active_public() );
		}
		return false;
	}

	/** Strict loop/context guard for the Breaking News strip. */
	private static function is_main_home_or_news_context( $query ) {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return false;
		}
		if ( is_object( $query ) && method_exists( $query, 'is_main_query' ) && ! $query->is_main_query() ) {
			return false;
		}
		if ( function_exists( 'in_the_loop' ) && ! in_the_loop() ) {
			return false;
		}
		$context = class_exists( __NAMESPACE__ . '\\NewsPublicRuntime' ) ? NewsPublicRuntime::context() : array();
		if ( ! empty( $context['route'] ) ) {
			return true;
		}
		return function_exists( 'is_front_page' ) && is_front_page();
	}

	/** Reset the request guard for isolated integration tests. */
	public static function reset_runtime_guards() {
		self::$breaking_rendered = false;
	}
}
