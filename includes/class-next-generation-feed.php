<?php
/**
 * File 21 next-generation Home and News Feed feature set.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Implements the Founder-approved 30-feature File 21 expansion. */
final class NextGenerationFeed {
	const CONTRACT_VERSION = '1.0.0';
	const USER_META         = '_sabri_hnf_ng_user_v1';

	const META_KIND             = '_sabri_hnf_ng_kind';
	const META_ORIGINAL_ID      = '_sabri_hnf_ng_original_id';
	const META_THREAD_ID        = '_sabri_hnf_ng_thread_id';
	const META_THREAD_ORDER     = '_sabri_hnf_ng_thread_order';
	const META_COAUTHORS        = '_sabri_hnf_ng_coauthors';
	const META_STORY_EXPIRES    = '_sabri_hnf_ng_story_expires';
	const META_DEVELOPING_STORY = '_sabri_hnf_ng_developing_story';
	const META_EXPERT_CONTEXTS  = '_sabri_hnf_ng_expert_contexts';
	const META_EVIDENCE_CARD    = '_sabri_hnf_ng_evidence_card';
	const META_SOURCE_DIVERSITY = '_sabri_hnf_ng_source_diversity';
	const META_QA               = '_sabri_hnf_ng_qna';
	const META_AI_SUMMARY       = '_sabri_hnf_ng_ai_summary';
	const META_TRANSLATIONS     = '_sabri_hnf_ng_translations';

	/** Register runtime hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'init', array( __CLASS__, 'register_meta' ), 22 );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 30 );
		}
		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'the_content', array( __CLASS__, 'append_article_tools' ), 30 );
			add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
			add_filter( 'sabri_hnf_feed_rank_score', array( __CLASS__, 'apply_feed_recipe' ), 30, 4 );
		}
	}

	/** Stable manifest of the complete 30-feature approved expansion. */
	public static function feature_manifest() {
		return array(
			'F21-NG-01' => array( 'slug' => 'repost', 'label' => 'Repost', 'owner' => 'file-21' ),
			'F21-NG-02' => array( 'slug' => 'quote-post', 'label' => 'Quote Post', 'owner' => 'file-21' ),
			'F21-NG-03' => array( 'slug' => 'post-threads', 'label' => 'Post Threads / Series', 'owner' => 'file-21' ),
			'F21-NG-04' => array( 'slug' => 'coauthored-posts', 'label' => 'Collaborative / Co-authored Posts', 'owner' => 'file-21' ),
			'F21-NG-05' => array( 'slug' => 'professional-stories', 'label' => 'Professional Stories / 24-hour Updates', 'owner' => 'file-21' ),
			'F21-NG-06' => array( 'slug' => 'developing-story-timeline', 'label' => 'Developing Story Timeline', 'owner' => 'file-21' ),
			'F21-NG-07' => array( 'slug' => 'expert-context', 'label' => 'Expert Context / Community Notes', 'owner' => 'file-21' ),
			'F21-NG-08' => array( 'slug' => 'evidence-card', 'label' => 'Evidence Card', 'owner' => 'file-21' ),
			'F21-NG-09' => array( 'slug' => 'source-diversity', 'label' => 'Source Diversity View', 'owner' => 'file-21' ),
			'F21-NG-10' => array( 'slug' => 'edit-correction-history', 'label' => 'Full Edit & Correction History', 'owner' => 'file-21' ),
			'F21-NG-11' => array( 'slug' => 'smart-share-warning', 'label' => 'Smart Share Warning', 'owner' => 'file-21' ),
			'F21-NG-12' => array( 'slug' => 'ai-summary', 'label' => 'AI 30-second Summary', 'owner' => 'file-16', 'file21_role' => 'context-renderer' ),
			'F21-NG-13' => array( 'slug' => 'ask-this-article', 'label' => 'Ask This Article', 'owner' => 'file-16', 'file21_role' => 'context-renderer' ),
			'F21-NG-14' => array( 'slug' => 'intelligent-translation', 'label' => 'Intelligent Translation', 'owner' => 'file-16', 'file21_role' => 'relation-renderer' ),
			'F21-NG-15' => array( 'slug' => 'follow-topics', 'label' => 'Follow Topics', 'owner' => 'file-21' ),
			'F21-NG-16' => array( 'slug' => 'my-topics-feed', 'label' => 'My Topics Feed', 'owner' => 'file-21' ),
			'F21-NG-17' => array( 'slug' => 'catch-up', 'label' => 'Catch Up / What You Missed', 'owner' => 'file-21' ),
			'F21-NG-18' => array( 'slug' => 'continue-reading', 'label' => 'Continue Reading', 'owner' => 'file-21' ),
			'F21-NG-19' => array( 'slug' => 'reading-queue', 'label' => 'Reading Queue / Read Later', 'owner' => 'file-21' ),
			'F21-NG-20' => array( 'slug' => 'low-bandwidth', 'label' => 'Low-Bandwidth Feed', 'owner' => 'file-21' ),
			'F21-NG-21' => array( 'slug' => 'offline-feed-pack', 'label' => 'Offline Feed Pack', 'owner' => 'file-21' ),
			'F21-NG-22' => array( 'slug' => 'data-saver', 'label' => 'Data Saver', 'owner' => 'file-21' ),
			'F21-NG-23' => array( 'slug' => 'doctor-answer-badge', 'label' => 'Doctor Answer Badge', 'owner' => 'file-21', 'identity_owner' => 'file-00/file-09' ),
			'F21-NG-24' => array( 'slug' => 'structured-qna', 'label' => 'Structured Q&A beneath Posts', 'owner' => 'file-21' ),
			'F21-NG-25' => array( 'slug' => 'why-trending', 'label' => 'Why Trending?', 'owner' => 'file-26', 'file21_role' => 'renderer' ),
			'F21-NG-26' => array( 'slug' => 'knowledge-graph-cards', 'label' => 'Related Knowledge Graph Cards', 'owner' => 'file-26', 'file21_role' => 'renderer' ),
			'F21-NG-27' => array( 'slug' => 'news-compare', 'label' => 'News Compare Mode', 'owner' => 'file-21' ),
			'F21-NG-28' => array( 'slug' => 'shareable-knowledge-cards', 'label' => 'Shareable Knowledge Cards', 'owner' => 'file-25', 'file21_role' => 'semantic-payload' ),
			'F21-NG-29' => array( 'slug' => 'personal-feed-recipe', 'label' => 'Personal Feed Recipe', 'owner' => 'file-21', 'boundary' => 'local-file21-feed-only' ),
			'F21-NG-30' => array( 'slug' => 'knowledge-digest', 'label' => 'Daily/Weekly Knowledge Digest', 'owner' => 'file-19', 'file21_role' => 'candidate-provider' ),
		);
	}

	/** Register internal metadata without exposing unrestricted generic REST writes. */
	public static function register_meta() {
		if ( ! function_exists( 'register_post_meta' ) ) {
			return;
		}
		$string_keys = array( self::META_KIND, self::META_THREAD_ID, self::META_DEVELOPING_STORY );
		foreach ( $string_keys as $key ) {
			register_post_meta( 'post', $key, array( 'type' => 'string', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'sanitize_text_field', 'auth_callback' => array( __CLASS__, 'meta_auth' ) ) );
		}
		foreach ( array( self::META_ORIGINAL_ID, self::META_THREAD_ORDER, self::META_STORY_EXPIRES ) as $key ) {
			register_post_meta( 'post', $key, array( 'type' => 'integer', 'single' => true, 'show_in_rest' => false, 'sanitize_callback' => 'absint', 'auth_callback' => array( __CLASS__, 'meta_auth' ) ) );
		}
		foreach ( array( self::META_COAUTHORS, self::META_EXPERT_CONTEXTS, self::META_EVIDENCE_CARD, self::META_SOURCE_DIVERSITY, self::META_QA, self::META_AI_SUMMARY, self::META_TRANSLATIONS ) as $key ) {
			register_post_meta( 'post', $key, array( 'type' => 'array', 'single' => true, 'show_in_rest' => false, 'auth_callback' => array( __CLASS__, 'meta_auth' ) ) );
		}
	}

	/** Metadata authorization delegates to canonical File 21 publishing policy. */
	public static function meta_auth( $allowed, $meta_key, $post_id, $user_id ) {
		unset( $allowed, $meta_key );
		return ComposerPermissions::user_can_edit_post( absint( $post_id ), absint( $user_id ) ) || ComposerPermissions::user_can_moderate();
	}

	/** Enqueue the progressive-enhancement UI for the 30-feature layer. */
	public static function enqueue_assets() {
		if ( ! function_exists( 'wp_enqueue_style' ) || ! function_exists( 'wp_enqueue_script' ) ) {
			return;
		}
		wp_enqueue_style( 'sabri-hnf-next-generation', SABRI_HNF_URL . 'assets/css/next-generation.css', array(), SABRI_HNF_PACKAGE_VERSION );
		wp_enqueue_script( 'sabri-hnf-next-generation', SABRI_HNF_URL . 'assets/js/next-generation.js', array(), SABRI_HNF_PACKAGE_VERSION, true );
		$payload = array(
			'endpoint' => function_exists( 'rest_url' ) ? rest_url( RestFoundation::NAMESPACE . '/next-generation/action' ) : '',
			'nonce'    => function_exists( 'wp_create_nonce' ) && self::current_user_id() > 0 ? wp_create_nonce( InteractionPermissions::REST_NONCE_ACTION ) : '',
			'loggedIn' => self::current_user_id() > 0,
			'loginUrl' => function_exists( 'wp_login_url' ) ? wp_login_url( self::current_url() ) : '',
			'i18n'     => array(
				'saved'   => __( 'Saved.', 'sabri-complete-home-news-feed' ),
				'error'   => __( 'The action could not be completed.', 'sabri-complete-home-news-feed' ),
				'login'   => __( 'Please sign in to use this feature.', 'sabri-complete-home-news-feed' ),
			),
		);
		if ( function_exists( 'wp_localize_script' ) ) {
			wp_localize_script( 'sabri-hnf-next-generation', 'SabriHnfNextGeneration', $payload );
		}
	}

	/** Add explicit data-saving state classes; never infer these preferences. */
	public static function body_classes( $classes ) {
		$classes = is_array( $classes ) ? $classes : array();
		$prefs   = self::user_state();
		if ( ! empty( $prefs['low_bandwidth'] ) ) {
			$classes[] = 'sabri-hnf-low-bandwidth';
		}
		if ( ! empty( $prefs['data_saver'] ) ) {
			$classes[] = 'sabri-hnf-data-saver';
		}
		return array_values( array_unique( $classes ) );
	}

	/** Render supplemental Home tools without changing the frozen 14-control bar. */
	public static function render_feed_tools() {
		$user_id = self::current_user_id();
		$stories = self::active_stories( 8 );
		$html    = '<section class="sabri-hnf-ng-home" aria-label="' . esc_attr__( 'Knowledge Feed tools', 'sabri-complete-home-news-feed' ) . '">';
		if ( $stories ) {
			$html .= '<div class="sabri-hnf-ng-stories"><h3>' . esc_html__( 'Professional Stories', 'sabri-complete-home-news-feed' ) . '</h3><div class="sabri-hnf-ng-stories__list">';
			foreach ( $stories as $story ) {
				$html .= '<a href="' . esc_url( $story['url'] ) . '"><span>' . esc_html( $story['title'] ) . '</span><small>' . esc_html( $story['remaining'] ) . '</small></a>';
			}
			$html .= '</div></div>';
		}
		$html .= '<div class="sabri-hnf-ng-home__controls">';
		if ( $user_id > 0 ) {
			$state = self::user_state( $user_id );
			$html .= self::toggle_button( 'set-low-bandwidth', ! empty( $state['low_bandwidth'] ), __( 'Low-bandwidth Feed', 'sabri-complete-home-news-feed' ) );
			$html .= self::toggle_button( 'set-data-saver', ! empty( $state['data_saver'] ), __( 'Data Saver', 'sabri-complete-home-news-feed' ) );
			$html .= '<button type="button" class="sabri-hnf-ng-button" data-sabri-ng-action="mark-caught-up">' . esc_html__( 'Mark caught up', 'sabri-complete-home-news-feed' ) . '</button>';
			$html .= '<details><summary>' . esc_html__( 'Personal Feed Recipe', 'sabri-complete-home-news-feed' ) . '</summary>' . self::render_recipe_form( $state['recipe'] ) . '</details>';
			$html .= '<details><summary>' . esc_html__( 'My Topics', 'sabri-complete-home-news-feed' ) . '</summary>' . self::render_my_topics_preview( $user_id ) . '</details>';
			$html .= '<details><summary>' . esc_html__( 'Catch Up', 'sabri-complete-home-news-feed' ) . '</summary>' . self::render_post_link_list( self::catch_up_posts( $user_id, 6 ), __( 'You are caught up.', 'sabri-complete-home-news-feed' ) ) . '</details>';
			$html .= '<details><summary>' . esc_html__( 'Continue Reading', 'sabri-complete-home-news-feed' ) . '</summary>' . self::render_continue_reading( $user_id ) . '</details>';
			$html .= '<details><summary>' . esc_html__( 'Read Later', 'sabri-complete-home-news-feed' ) . '</summary>' . self::render_post_link_list( self::reading_queue_posts( $user_id, 10 ), __( 'Your reading queue is empty.', 'sabri-complete-home-news-feed' ) ) . '</details>';
			$html .= '<details><summary>' . esc_html__( 'Offline Feed Pack', 'sabri-complete-home-news-feed' ) . '</summary><p>' . esc_html__( 'Your selected articles can be exported as a portable offline reading pack.', 'sabri-complete-home-news-feed' ) . '</p><a class="sabri-hnf-ng-button" href="' . esc_url( self::rest_url( '/next-generation/offline-pack' ) ) . '">' . esc_html__( 'Open offline pack', 'sabri-complete-home-news-feed' ) . '</a></details>';
		} else {
			$html .= '<p>' . esc_html__( 'Sign in to follow topics, catch up, continue reading, build an offline pack, and control your Feed recipe.', 'sabri-complete-home-news-feed' ) . '</p>';
		}
		return $html . '</div><div class="sabri-hnf-ng-status" data-sabri-ng-status aria-live="polite"></div></section>';
	}

	/** Render File 21-owned and cross-owner context on a Feed card. */
	public static function render_card_extensions( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 || ! PostMetadata::user_can_view( $post_id ) ) {
			return '';
		}
		$kind     = self::post_kind( $post_id );
		$original = self::original_post( $post_id );
		$evidence = self::evidence_card( $post_id );
		$sources  = self::source_diversity( $post_id );
		$warning  = self::share_warning( $post_id );
		$html     = '<div class="sabri-hnf-ng-card" data-sabri-ng-card="' . esc_attr( $post_id ) . '">';

		if ( $original ) {
			$label = 'quote' === $kind ? __( 'Quoted post', 'sabri-complete-home-news-feed' ) : __( 'Reposted from', 'sabri-complete-home-news-feed' );
			$html .= '<aside class="sabri-hnf-ng-original"><strong>' . esc_html( $label ) . ':</strong> <a href="' . esc_url( $original['url'] ) . '">' . esc_html( $original['title'] ) . '</a></aside>';
		}
		$thread = self::thread_projection( $post_id );
		if ( $thread['total'] > 1 ) {
			$html .= '<nav class="sabri-hnf-ng-thread" aria-label="' . esc_attr__( 'Thread navigation', 'sabri-complete-home-news-feed' ) . '"><span>' . esc_html( sprintf( __( 'Thread %1$d of %2$d', 'sabri-complete-home-news-feed' ), $thread['position'], $thread['total'] ) ) . '</span>';
			if ( $thread['previous'] ) {
				$html .= '<a href="' . esc_url( $thread['previous']['url'] ) . '">' . esc_html__( 'Previous', 'sabri-complete-home-news-feed' ) . '</a>';
			}
			if ( $thread['next'] ) {
				$html .= '<a href="' . esc_url( $thread['next']['url'] ) . '">' . esc_html__( 'Next', 'sabri-complete-home-news-feed' ) . '</a>';
			}
			$html .= '</nav>';
		}
		$coauthors = self::coauthors( $post_id );
		if ( $coauthors ) {
			$html .= '<p class="sabri-hnf-ng-coauthors"><strong>' . esc_html__( 'Co-authors:', 'sabri-complete-home-news-feed' ) . '</strong> ' . esc_html( implode( ', ', wp_list_pluck( $coauthors, 'name' ) ) ) . '</p>';
		}
		if ( ! empty( $evidence['level'] ) || ! empty( $evidence['review_date'] ) || ! empty( $evidence['uncertainty'] ) ) {
			$html .= '<aside class="sabri-hnf-ng-evidence"><strong>' . esc_html__( 'Evidence', 'sabri-complete-home-news-feed' ) . '</strong>';
			if ( $evidence['level'] ) {
				$html .= '<span>' . esc_html( $evidence['level'] ) . '</span>';
			}
			if ( $evidence['review_date'] ) {
				$html .= '<span>' . esc_html( sprintf( __( 'Reviewed %s', 'sabri-complete-home-news-feed' ), $evidence['review_date'] ) ) . '</span>';
			}
			if ( $evidence['uncertainty'] ) {
				$html .= '<span>' . esc_html( $evidence['uncertainty'] ) . '</span>';
			}
			$html .= '</aside>';
		}
		if ( $sources['count'] > 0 ) {
			$html .= '<p class="sabri-hnf-ng-sources">' . esc_html( sprintf( _n( '%d independent source domain', '%d independent source domains', $sources['count'], 'sabri-complete-home-news-feed' ), $sources['count'] ) ) . '</p>';
		}
		if ( $warning['warn'] ) {
			$html .= '<p class="sabri-hnf-ng-warning" role="note"><strong>' . esc_html__( 'Before sharing:', 'sabri-complete-home-news-feed' ) . '</strong> ' . esc_html( $warning['message'] ) . '</p>';
		}
		$html .= self::render_card_actions( $post_id );
		return $html . '</div>';
	}

	/** Add article-level summary, translation, reading, Q&A and provenance tools. */
	public static function append_article_tools( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return $content;
		}
		if ( function_exists( 'is_singular' ) && ! is_singular( array( 'post', 'sabri_news' ) ) ) {
			return $content;
		}
		if ( function_exists( 'in_the_loop' ) && ! in_the_loop() ) {
			return $content;
		}
		$post_id = function_exists( 'get_the_ID' ) ? absint( get_the_ID() ) : 0;
		if ( $post_id < 1 ) {
			return $content;
		}
		return $content . self::render_article_tools( $post_id );
	}

	/** Render the next-generation article panel. */
	public static function render_article_tools( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return '';
		}
		$summary      = NextGenerationIntegrations::ai_summary( $post_id );
		$ask          = NextGenerationIntegrations::ask_article( $post_id );
		$translations = NextGenerationIntegrations::translation_options( $post_id );
		$contexts     = self::expert_contexts( $post_id, true );
		$qna          = self::qna( $post_id );
		$history      = self::edit_history( $post_id );
		$timeline     = self::developing_story_timeline( $post_id );
		$related      = NextGenerationIntegrations::related_knowledge( $post_id, 6 );
		$why          = NextGenerationIntegrations::why_trending( $post_id );
		$html         = '<section class="sabri-hnf-ng-article" data-sabri-ng-article="' . esc_attr( $post_id ) . '">';
		$html        .= '<h2>' . esc_html__( 'Article tools and context', 'sabri-complete-home-news-feed' ) . '</h2>';

		if ( ! empty( $summary['available'] ) ) {
			$html .= '<aside class="sabri-hnf-ng-summary"><h3>' . esc_html__( '30-second summary', 'sabri-complete-home-news-feed' ) . '</h3><p><small>' . esc_html( $summary['label'] ) . '</small></p><p>' . esc_html( $summary['text'] ) . '</p></aside>';
		}
		if ( ! empty( $ask['available'] ) ) {
			$html .= '<p><a class="sabri-hnf-ng-button" href="' . esc_url( $ask['url'] ) . '">' . esc_html__( 'Ask this article', 'sabri-complete-home-news-feed' ) . '</a></p>';
		}
		if ( $translations ) {
			$html .= '<nav class="sabri-hnf-ng-translations" aria-label="' . esc_attr__( 'Article translations', 'sabri-complete-home-news-feed' ) . '"><strong>' . esc_html__( 'Translations:', 'sabri-complete-home-news-feed' ) . '</strong>';
			foreach ( $translations as $translation ) {
				$html .= '<a href="' . esc_url( $translation['url'] ) . '">' . esc_html( $translation['label'] ) . ' <small>(' . esc_html( $translation['method'] ) . ')</small></a>';
			}
			$html .= '</nav>';
		}
		if ( self::current_user_id() > 0 ) {
			$html .= '<div class="sabri-hnf-ng-reading-actions">';
			$html .= '<button type="button" class="sabri-hnf-ng-button" data-sabri-ng-action="queue-toggle" data-post-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Read later', 'sabri-complete-home-news-feed' ) . '</button>';
			$html .= '<button type="button" class="sabri-hnf-ng-button" data-sabri-ng-action="offline-toggle" data-post-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Add to offline pack', 'sabri-complete-home-news-feed' ) . '</button>';
			$html .= '<label>' . esc_html__( 'Reading progress', 'sabri-complete-home-news-feed' ) . ' <input type="range" min="0" max="100" step="5" value="' . esc_attr( self::reading_progress_percent( $post_id ) ) . '" data-sabri-ng-progress data-post-id="' . esc_attr( $post_id ) . '"></label>';
			$html .= '</div>';
		}
		if ( $timeline ) {
			$html .= '<section><h3>' . esc_html__( 'Developing story timeline', 'sabri-complete-home-news-feed' ) . '</h3>' . self::render_post_link_list( $timeline, '' ) . '</section>';
		}
		if ( $contexts ) {
			$html .= '<section class="sabri-hnf-ng-context"><h3>' . esc_html__( 'Expert context', 'sabri-complete-home-news-feed' ) . '</h3>';
			foreach ( $contexts as $context ) {
				$html .= '<article><p>' . esc_html( $context['text'] ) . '</p><small>' . esc_html( $context['author_name'] ) . ( $context['verified_doctor'] ? ' - ' . esc_html__( 'Verified doctor', 'sabri-complete-home-news-feed' ) : '' ) . '</small></article>';
			}
			$html .= '</section>';
		}
		$html .= self::render_qna( $post_id, $qna );
		if ( $history ) {
			$html .= '<details class="sabri-hnf-ng-history"><summary>' . esc_html__( 'Edit and correction history', 'sabri-complete-home-news-feed' ) . '</summary><ul>';
			foreach ( $history as $entry ) {
				$html .= '<li>' . esc_html( $entry['date'] . ' - ' . $entry['label'] ) . '</li>';
			}
			$html .= '</ul></details>';
		}
		if ( ! empty( $why['available'] ) ) {
			$html .= '<details><summary>' . esc_html__( 'Why is this trending?', 'sabri-complete-home-news-feed' ) . '</summary><p>' . esc_html( $why['reason'] ) . '</p></details>';
		}
		if ( $related ) {
			$html .= '<section><h3>' . esc_html__( 'Related knowledge', 'sabri-complete-home-news-feed' ) . '</h3><ul>';
			foreach ( $related as $item ) {
				$html .= '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a> <small>' . esc_html( $item['type'] ) . '</small></li>';
			}
			$html .= '</ul></section>';
		}
		$html .= '<p><a href="' . esc_url( self::rest_url( '/next-generation/share-card/' . $post_id ) ) . '">' . esc_html__( 'Shareable knowledge card', 'sabri-complete-home-news-feed' ) . '</a></p>';
		return $html . '<div class="sabri-hnf-ng-status" data-sabri-ng-status aria-live="polite"></div></section>';
	}

	/** Create a non-duplicating Repost or Quote Post under existing publication policy. */
	public static function create_repost( $original_id, $quote = '' ) {
		$original_id = absint( $original_id );
		$user_id     = self::current_user_id();
		$quote       = self::clean_textarea( $quote );
		if ( $user_id < 1 || ! ComposerPermissions::user_can_create( $user_id ) ) {
			return self::error( 'publishing_not_allowed', __( 'Your account cannot create public social posts.', 'sabri-complete-home-news-feed' ), 403 );
		}
		if ( $original_id < 1 || ! PostMetadata::user_can_view( $original_id, $user_id ) ) {
			return self::error( 'original_unavailable', __( 'The original post is unavailable.', 'sabri-complete-home-news-feed' ), 404 );
		}
		if ( ! function_exists( 'wp_insert_post' ) ) {
			return self::error( 'publishing_unavailable', __( 'Publishing is temporarily unavailable.', 'sabri-complete-home-news-feed' ), 503 );
		}
		$status = ComposerPermissions::user_can_publish( $user_id ) ? 'publish' : ( ComposerPermissions::user_can_submit_for_review( $user_id ) ? 'pending' : 'draft' );
		$title  = function_exists( 'get_the_title' ) ? (string) get_the_title( $original_id ) : __( 'Post', 'sabri-complete-home-news-feed' );
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => $status,
				'post_author'  => $user_id,
				'post_title'   => ( '' === $quote ? __( 'Repost: ', 'sabri-complete-home-news-feed' ) : __( 'Quote: ', 'sabri-complete-home-news-feed' ) ) . $title,
				'post_content' => $quote,
			),
			true
		);
		if ( function_exists( 'is_wp_error' ) && is_wp_error( $post_id ) ) {
			return self::error( 'repost_failed', __( 'The repost could not be created.', 'sabri-complete-home-news-feed' ), 500 );
		}
		$post_id = absint( $post_id );
		if ( function_exists( 'update_post_meta' ) ) {
			update_post_meta( $post_id, self::META_KIND, '' === $quote ? 'repost' : 'quote' );
			update_post_meta( $post_id, self::META_ORIGINAL_ID, $original_id );
		}
		PostMetadata::save_for_post(
			$post_id,
			array(
				'feed_type'        => 'standard-post',
				'visibility'       => 'public',
				'review_state'     => 'publish' === $status ? 'approved' : 'pending',
				'comments_enabled' => true,
			)
		);
		AuditLog::record( '' === $quote ? 'post_reposted' : 'post_quoted', array( 'post_id' => $post_id, 'original_id' => $original_id ), 'post', $post_id );
		return self::success( array( 'post_id' => $post_id, 'status' => $status, 'kind' => '' === $quote ? 'repost' : 'quote' ) );
	}

	/** Update post-level next-generation metadata with canonical edit authorization. */
	public static function editor_update( $post_id, array $input ) {
		$post_id = absint( $post_id );
		$user_id = self::current_user_id();
		if ( $post_id < 1 || $user_id < 1 || ! ComposerPermissions::user_can_edit_post( $post_id, $user_id ) ) {
			return self::error( 'edit_not_allowed', __( 'You cannot edit these post extensions.', 'sabri-complete-home-news-feed' ), 403 );
		}
		if ( ! function_exists( 'update_post_meta' ) ) {
			return self::error( 'metadata_unavailable', __( 'Post metadata is temporarily unavailable.', 'sabri-complete-home-news-feed' ), 503 );
		}
		$changed = array();
		if ( array_key_exists( 'thread_id', $input ) ) {
			$value = self::clean_key( $input['thread_id'] );
			update_post_meta( $post_id, self::META_THREAD_ID, $value );
			update_post_meta( $post_id, self::META_THREAD_ORDER, max( 1, absint( isset( $input['thread_order'] ) ? $input['thread_order'] : 1 ) ) );
			$changed[] = 'thread';
		}
		if ( array_key_exists( 'coauthors', $input ) ) {
			$ids = self::normalize_user_ids( $input['coauthors'], 12, $user_id );
			update_post_meta( $post_id, self::META_COAUTHORS, $ids );
			$changed[] = 'coauthors';
		}
		if ( array_key_exists( 'story', $input ) ) {
			$enabled = self::truthy( $input['story'] );
			update_post_meta( $post_id, self::META_STORY_EXPIRES, $enabled ? time() + DAY_IN_SECONDS : 0 );
			$changed[] = 'story';
		}
		if ( array_key_exists( 'developing_story', $input ) ) {
			update_post_meta( $post_id, self::META_DEVELOPING_STORY, self::clean_key( $input['developing_story'] ) );
			$changed[] = 'developing_story';
		}
		if ( array_key_exists( 'evidence', $input ) ) {
			update_post_meta( $post_id, self::META_EVIDENCE_CARD, self::sanitize_evidence( $input['evidence'] ) );
			$changed[] = 'evidence';
		}
		if ( array_key_exists( 'sources', $input ) ) {
			update_post_meta( $post_id, self::META_SOURCE_DIVERSITY, self::sanitize_sources( $input['sources'] ) );
			$changed[] = 'sources';
		}
		if ( array_key_exists( 'translations', $input ) ) {
			update_post_meta( $post_id, self::META_TRANSLATIONS, self::sanitize_translations( $input['translations'] ) );
			$changed[] = 'translations';
		}
		AuditLog::record( 'next_generation_post_metadata_updated', array( 'post_id' => $post_id, 'fields' => $changed ), 'post', $post_id );
		return self::success( array( 'post_id' => $post_id, 'changed' => $changed ) );
	}

	/** Submit verified expert context; non-moderator expert notes remain pending. */
	public static function add_expert_context( $post_id, $text ) {
		$post_id = absint( $post_id );
		$user_id = self::current_user_id();
		$text    = self::clean_textarea( $text );
		if ( $post_id < 1 || $user_id < 1 || '' === $text || ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
			return self::error( 'context_invalid', __( 'The expert context is invalid.', 'sabri-complete-home-news-feed' ), 400 );
		}
		if ( ! CanonicalIdentityAdapter::is_verified_doctor( $user_id ) && ! ComposerPermissions::user_can_moderate() ) {
			return self::error( 'verified_expert_required', __( 'Verified professional status is required to add expert context.', 'sabri-complete-home-news-feed' ), 403 );
		}
		$items   = self::expert_contexts( $post_id, false );
		$items[] = array(
			'id'              => wp_generate_uuid4(),
			'author_id'       => $user_id,
			'text'            => $text,
			'status'          => ComposerPermissions::user_can_moderate() ? 'approved' : 'pending',
			'created_at_utc'  => gmdate( 'c' ),
			'verified_doctor' => CanonicalIdentityAdapter::is_verified_doctor( $user_id ) ? 1 : 0,
		);
		$items = array_slice( $items, -100 );
		update_post_meta( $post_id, self::META_EXPERT_CONTEXTS, $items );
		AuditLog::record( 'expert_context_submitted', array( 'post_id' => $post_id, 'status' => end( $items )['status'] ), 'post', $post_id );
		return self::success( array( 'status' => end( $items )['status'] ) );
	}

	/** Add a structured Q&A question or answer. */
	public static function qna_action( $post_id, $kind, $text, $question_id = '' ) {
		$post_id = absint( $post_id );
		$user_id = self::current_user_id();
		$kind    = self::clean_key( $kind );
		$text    = self::clean_textarea( $text );
		if ( $post_id < 1 || $user_id < 1 || '' === $text || ! in_array( $kind, array( 'question', 'answer' ), true ) || ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
			return self::error( 'qna_invalid', __( 'The Q&A entry is invalid.', 'sabri-complete-home-news-feed' ), 400 );
		}
		if ( ! CanonicalIdentityAdapter::current_action_ready( $user_id ) ) {
			return self::error( 'identity_assurance_required', __( 'Current account assurance is required.', 'sabri-complete-home-news-feed' ), 403 );
		}
		$qna = self::qna( $post_id, false );
		if ( 'question' === $kind ) {
			$qna[] = array(
				'id'             => wp_generate_uuid4(),
				'author_id'      => $user_id,
				'text'           => $text,
				'created_at_utc' => gmdate( 'c' ),
				'answers'        => array(),
			);
		} else {
			$found = false;
			foreach ( $qna as &$question ) {
				if ( isset( $question['id'] ) && hash_equals( (string) $question['id'], (string) $question_id ) ) {
					$question['answers']   = isset( $question['answers'] ) && is_array( $question['answers'] ) ? $question['answers'] : array();
					$question['answers'][] = array(
						'id'              => wp_generate_uuid4(),
						'author_id'       => $user_id,
						'text'            => $text,
						'created_at_utc'  => gmdate( 'c' ),
						'verified_doctor' => CanonicalIdentityAdapter::is_verified_doctor( $user_id ) ? 1 : 0,
					);
					$question['answers'] = array_slice( $question['answers'], -50 );
					$found = true;
					break;
				}
			}
			unset( $question );
			if ( ! $found ) {
				return self::error( 'question_not_found', __( 'The question could not be found.', 'sabri-complete-home-news-feed' ), 404 );
			}
		}
		$qna = array_slice( $qna, -100 );
		update_post_meta( $post_id, self::META_QA, $qna );
		AuditLog::record( 'structured_qna_updated', array( 'post_id' => $post_id, 'kind' => $kind ), 'post', $post_id );
		return self::success( array( 'kind' => $kind ) );
	}

	/** Update one private user feature. */
	public static function user_action( $action, array $input = array() ) {
		$user_id = self::current_user_id();
		$action  = self::clean_key( $action );
		if ( $user_id < 1 || ! CanonicalIdentityAdapter::current_action_ready( $user_id ) ) {
			return self::error( 'authentication_required', __( 'Authentication is required.', 'sabri-complete-home-news-feed' ), 401 );
		}
		$state = self::user_state( $user_id );
		switch ( $action ) {
			case 'follow-topic':
			case 'unfollow-topic':
				$topic = self::clean_key( isset( $input['topic'] ) ? $input['topic'] : '' );
				if ( '' === $topic ) {
					return self::error( 'topic_invalid', __( 'The topic is invalid.', 'sabri-complete-home-news-feed' ), 400 );
				}
				if ( 'follow-topic' === $action ) {
					$state['topics'][] = $topic;
					$state['topics']   = array_slice( array_values( array_unique( $state['topics'] ) ), -100 );
				} else {
					$state['topics'] = array_values( array_diff( $state['topics'], array( $topic ) ) );
				}
				break;
			case 'progress':
				$post_id = absint( isset( $input['post_id'] ) ? $input['post_id'] : 0 );
				$percent = min( 100, max( 0, absint( isset( $input['percent'] ) ? $input['percent'] : 0 ) ) );
				if ( $post_id < 1 || ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
					return self::error( 'post_unavailable', __( 'The article is unavailable.', 'sabri-complete-home-news-feed' ), 404 );
				}
				if ( $percent >= 100 ) {
					unset( $state['progress'][ (string) $post_id ] );
				} else {
					$state['progress'][ (string) $post_id ] = array( 'percent' => $percent, 'updated' => time() );
				}
				$state['progress'] = self::bounded_assoc( $state['progress'], 100 );
				break;
			case 'queue-toggle':
			case 'offline-toggle':
				$post_id = absint( isset( $input['post_id'] ) ? $input['post_id'] : 0 );
				if ( $post_id < 1 || ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
					return self::error( 'post_unavailable', __( 'The article is unavailable.', 'sabri-complete-home-news-feed' ), 404 );
				}
				$key = 'queue-toggle' === $action ? 'queue' : 'offline';
				$state[ $key ] = in_array( $post_id, $state[ $key ], true ) ? array_values( array_diff( $state[ $key ], array( $post_id ) ) ) : array_merge( $state[ $key ], array( $post_id ) );
				$state[ $key ] = array_slice( array_values( array_unique( array_map( 'absint', $state[ $key ] ) ) ), -100 );
				break;
			case 'set-low-bandwidth':
				$state['low_bandwidth'] = self::truthy( isset( $input['enabled'] ) ? $input['enabled'] : ! $state['low_bandwidth'] ) ? 1 : 0;
				break;
			case 'set-data-saver':
				$state['data_saver'] = self::truthy( isset( $input['enabled'] ) ? $input['enabled'] : ! $state['data_saver'] ) ? 1 : 0;
				break;
			case 'mark-caught-up':
				$state['last_catch_up'] = time();
				break;
			case 'recipe':
				$state['recipe'] = self::sanitize_recipe( isset( $input['recipe'] ) ? $input['recipe'] : array() );
				break;
			default:
				return self::error( 'action_invalid', __( 'The requested action is invalid.', 'sabri-complete-home-news-feed' ), 400 );
		}
		if ( ! self::save_user_state( $user_id, $state ) ) {
			return self::error( 'preference_save_failed', __( 'The preference could not be saved.', 'sabri-complete-home-news-feed' ), 500 );
		}
		FeedQuery::invalidate_cache();
		AuditLog::record( 'next_generation_user_preference_updated', array( 'action' => $action ), 'user', $user_id );
		return self::success( array( 'action' => $action, 'state' => self::public_user_state( $state ) ) );
	}

	/** Private current-user state, normalized and bounded. */
	public static function user_state( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : self::current_user_id();
		$stored  = $user_id > 0 && function_exists( 'get_user_meta' ) ? get_user_meta( $user_id, self::USER_META, true ) : array();
		$stored  = is_array( $stored ) ? $stored : array();
		$state   = array_merge(
			array(
				'topics'          => array(),
				'progress'        => array(),
				'queue'           => array(),
				'offline'         => array(),
				'low_bandwidth'   => 0,
				'data_saver'      => 0,
				'last_catch_up'   => time() - WEEK_IN_SECONDS,
				'recipe'          => self::default_recipe(),
			),
			$stored
		);
		$state['topics']        = array_slice( array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_key' ), (array) $state['topics'] ) ) ) ), -100 );
		$state['queue']         = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $state['queue'] ) ) ) ), -100 );
		$state['offline']       = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $state['offline'] ) ) ) ), -100 );
		$state['progress']      = self::sanitize_progress( $state['progress'] );
		$state['low_bandwidth'] = ! empty( $state['low_bandwidth'] ) ? 1 : 0;
		$state['data_saver']    = ! empty( $state['data_saver'] ) ? 1 : 0;
		$state['last_catch_up'] = max( 0, absint( $state['last_catch_up'] ) );
		$state['recipe']        = self::sanitize_recipe( $state['recipe'] );
		return $state;
	}

	/** Apply only explicit local File 21 Feed recipe preferences. */
	public static function apply_feed_recipe( $score, $post_id, $mode, $settings ) {
		unset( $settings );
		if ( 'for-you' !== sanitize_key( $mode ) || self::current_user_id() < 1 ) {
			return $score;
		}
		$recipe = self::user_state()['recipe'];
		$score  = (int) $score;
		if ( ! empty( $recipe['latest'] ) ) {
			$age_hours = max( 0, ( time() - (int) get_post_time( 'U', true, $post_id ) ) / HOUR_IN_SECONDS );
			$score += max( 0, (int) round( $recipe['latest'] * max( 0, 6 - min( 6, $age_hours / 4 ) ) ) );
		}
		$author_id = (int) get_post_field( 'post_author', $post_id );
		if ( ! empty( $recipe['doctors'] ) && $author_id > 0 && CanonicalIdentityAdapter::is_verified_doctor( $author_id ) ) {
			$score += 3 * (int) $recipe['doctors'];
		}
		if ( ! empty( $recipe['research'] ) && 'research' === PostMetadata::feed_type( $post_id ) ) {
			$score += 3 * (int) $recipe['research'];
		}
		return $score;
	}

	/** Active 24-hour professional stories. */
	public static function active_stories( $limit = 8 ) {
		$limit = min( 20, max( 1, absint( $limit ) ) );
		if ( ! class_exists( 'WP_Query' ) ) {
			return array();
		}
		$query = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array( array( 'key' => self::META_STORY_EXPIRES, 'value' => time(), 'compare' => '>', 'type' => 'NUMERIC' ) ),
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( (array) $query->posts as $post ) {
			$post_id = absint( $post->ID );
			if ( ! PostMetadata::user_can_view( $post_id ) ) {
				continue;
			}
			$expires = absint( get_post_meta( $post_id, self::META_STORY_EXPIRES, true ) );
			$out[]   = array(
				'id'        => $post_id,
				'title'     => get_the_title( $post_id ),
				'url'       => get_permalink( $post_id ),
				'expires'   => $expires,
				'remaining' => human_time_diff( time(), $expires ),
			);
		}
		return $out;
	}

	/** My Topics Feed candidates. */
	public static function my_topics_posts( $user_id = 0, $limit = 10 ) {
		$user_id = $user_id ? absint( $user_id ) : self::current_user_id();
		$topics  = self::user_state( $user_id )['topics'];
		if ( $user_id < 1 || ! $topics || ! class_exists( 'WP_Query' ) ) {
			return array();
		}
		$query = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => min( 20, max( 1, absint( $limit ) ) ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'tax_query'      => array( array( 'taxonomy' => 'sabri_feed_topic', 'field' => 'slug', 'terms' => $topics, 'operator' => 'IN' ) ),
				'no_found_rows'  => true,
			)
		);
		return self::public_post_links( (array) $query->posts, $user_id );
	}

	/** Bounded catch-up based on the user's explicit last catch-up marker. */
	public static function catch_up_posts( $user_id = 0, $limit = 10 ) {
		$user_id = $user_id ? absint( $user_id ) : self::current_user_id();
		$state   = self::user_state( $user_id );
		if ( $user_id < 1 || ! class_exists( 'WP_Query' ) ) {
			return array();
		}
		$query = new \WP_Query(
			array(
				'post_type'      => array( 'post', 'sabri_news' ),
				'post_status'    => 'publish',
				'posts_per_page' => min( 20, max( 1, absint( $limit ) ) ),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'date_query'     => array( array( 'after' => gmdate( 'Y-m-d H:i:s', $state['last_catch_up'] ), 'inclusive' => false, 'column' => 'post_date_gmt' ) ),
				'no_found_rows'  => true,
			)
		);
		return self::public_post_links( (array) $query->posts, $user_id );
	}

	/** Continue Reading projection. */
	public static function continue_reading_items( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : self::current_user_id();
		$state   = self::user_state( $user_id );
		$items   = array();
		foreach ( $state['progress'] as $post_id => $progress ) {
			$post_id = absint( $post_id );
			if ( $post_id < 1 || ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
				continue;
			}
			$items[] = array(
				'id'      => $post_id,
				'title'   => get_the_title( $post_id ),
				'url'     => get_permalink( $post_id ),
				'percent' => absint( $progress['percent'] ),
				'updated' => absint( $progress['updated'] ),
			);
		}
		usort( $items, static function ( $a, $b ) { return $b['updated'] <=> $a['updated']; } );
		return array_slice( $items, 0, 20 );
	}

	/** Read Later projection. */
	public static function reading_queue_posts( $user_id = 0, $limit = 20 ) {
		$user_id = $user_id ? absint( $user_id ) : self::current_user_id();
		$ids     = array_reverse( self::user_state( $user_id )['queue'] );
		$posts   = array();
		foreach ( array_slice( $ids, 0, max( 1, absint( $limit ) ) ) as $post_id ) {
			if ( PostMetadata::user_can_view( $post_id, $user_id ) ) {
				$posts[] = (object) array( 'ID' => $post_id );
			}
		}
		return self::public_post_links( $posts, $user_id );
	}

	/** Offline pack containing accessible article text and provenance. */
	public static function offline_pack( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : self::current_user_id();
		$items   = array();
		foreach ( self::user_state( $user_id )['offline'] as $post_id ) {
			if ( ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
				continue;
			}
			$items[] = array(
				'id'       => $post_id,
				'title'    => get_the_title( $post_id ),
				'url'      => get_permalink( $post_id ),
				'content'  => wp_kses_post( get_post_field( 'post_content', $post_id ) ),
				'modified' => get_post_modified_time( 'c', true, $post_id ),
				'evidence' => self::evidence_card( $post_id ),
				'warning'  => self::share_warning( $post_id ),
			);
		}
		return array( 'contract_version' => self::CONTRACT_VERSION, 'generated_at_utc' => gmdate( 'c' ), 'items' => $items );
	}

	/** Daily/weekly digest candidates; File 19 remains the delivery owner. */
	public static function digest_candidates( $user_id, $frequency = 'daily' ) {
		$user_id   = absint( $user_id );
		$frequency = in_array( $frequency, array( 'daily', 'weekly' ), true ) ? $frequency : 'daily';
		$since     = time() - ( 'weekly' === $frequency ? WEEK_IN_SECONDS : DAY_IN_SECONDS );
		if ( ! class_exists( 'WP_Query' ) ) {
			return array();
		}
		$query = new \WP_Query(
			array(
				'post_type'      => array( 'post', 'sabri_news' ),
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'date_query'     => array( array( 'after' => gmdate( 'Y-m-d H:i:s', $since ), 'inclusive' => true, 'column' => 'post_date_gmt' ) ),
				'no_found_rows'  => true,
			)
		);
		$items = self::public_post_links( (array) $query->posts, $user_id );
		return NextGenerationIntegrations::dispatch_digest_candidates( $user_id, $frequency, $items );
	}

	/** News/post compare payload for 2-4 publicly visible items. */
	public static function compare_posts( array $ids ) {
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, 4 );
		$out = array();
		foreach ( $ids as $post_id ) {
			if ( ! PostMetadata::user_can_view( $post_id ) ) {
				continue;
			}
			$out[] = array(
				'id'       => $post_id,
				'title'    => get_the_title( $post_id ),
				'url'      => get_permalink( $post_id ),
				'date'     => get_the_date( DATE_ATOM, $post_id ),
				'author'   => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ),
				'evidence' => self::evidence_card( $post_id ),
				'sources'  => self::source_diversity( $post_id ),
				'history'  => self::edit_history( $post_id ),
				'warning'  => self::share_warning( $post_id ),
			);
		}
		return $out;
	}

	/** Safe semantic payload for File 25 visual card rendering or ordinary sharing. */
	public static function share_card_payload( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 || ! PostMetadata::user_can_view( $post_id ) ) {
			return array();
		}
		$content = wp_strip_all_tags( get_post_field( 'post_excerpt', $post_id ) );
		if ( '' === trim( $content ) ) {
			$content = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 36, '...' );
		}
		$payload = array(
			'contract_version' => self::CONTRACT_VERSION,
			'post_id'          => $post_id,
			'title'            => get_the_title( $post_id ),
			'excerpt'          => $content,
			'url'              => get_permalink( $post_id ),
			'source_label'     => get_bloginfo( 'name' ),
			'evidence'         => self::evidence_card( $post_id ),
			'warning'          => self::share_warning( $post_id ),
		);
		$payload['file25_rendered'] = NextGenerationIntegrations::share_card( $payload );
		return $payload;
	}

	/** Post kind. */
	public static function post_kind( $post_id ) {
		$kind = function_exists( 'get_post_meta' ) ? sanitize_key( get_post_meta( absint( $post_id ), self::META_KIND, true ) ) : '';
		return in_array( $kind, array( 'repost', 'quote' ), true ) ? $kind : 'original';
	}

	/** Original source projection for Repost/Quote. */
	public static function original_post( $post_id ) {
		$original_id = function_exists( 'get_post_meta' ) ? absint( get_post_meta( absint( $post_id ), self::META_ORIGINAL_ID, true ) ) : 0;
		if ( $original_id < 1 || ! PostMetadata::user_can_view( $original_id ) ) {
			return array();
		}
		return array( 'id' => $original_id, 'title' => get_the_title( $original_id ), 'url' => get_permalink( $original_id ) );
	}

	/** Thread/series navigation. */
	public static function thread_projection( $post_id ) {
		$post_id   = absint( $post_id );
		$thread_id = self::clean_key( get_post_meta( $post_id, self::META_THREAD_ID, true ) );
		$empty     = array( 'thread_id' => '', 'position' => 1, 'total' => 1, 'previous' => array(), 'next' => array() );
		if ( '' === $thread_id || ! class_exists( 'WP_Query' ) ) {
			return $empty;
		}
		$query = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'meta_key'       => self::META_THREAD_ORDER,
				'orderby'        => array( 'meta_value_num' => 'ASC', 'date' => 'ASC' ),
				'meta_query'     => array( array( 'key' => self::META_THREAD_ID, 'value' => $thread_id, 'compare' => '=' ) ),
				'no_found_rows'  => true,
			)
		);
		$items = array();
		foreach ( (array) $query->posts as $post ) {
			if ( PostMetadata::user_can_view( $post->ID ) ) {
				$items[] = array( 'id' => (int) $post->ID, 'title' => get_the_title( $post->ID ), 'url' => get_permalink( $post->ID ) );
			}
		}
		$position = 0;
		foreach ( $items as $index => $item ) {
			if ( $item['id'] === $post_id ) {
				$position = $index;
				break;
			}
		}
		return array(
			'thread_id' => $thread_id,
			'position'  => $position + 1,
			'total'     => max( 1, count( $items ) ),
			'previous'  => $position > 0 ? $items[ $position - 1 ] : array(),
			'next'      => isset( $items[ $position + 1 ] ) ? $items[ $position + 1 ] : array(),
		);
	}

	/** Co-author public projections. */
	public static function coauthors( $post_id ) {
		$ids = get_post_meta( absint( $post_id ), self::META_COAUTHORS, true );
		$ids = is_array( $ids ) ? array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, 12 ) : array();
		$out = array();
		foreach ( $ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}
			$out[] = array(
				'id'              => $user_id,
				'name'            => sanitize_text_field( $user->display_name ),
				'verified_doctor' => CanonicalIdentityAdapter::is_verified_doctor( $user_id ),
			);
		}
		return $out;
	}

	/** Developing story timeline from an explicit File 21 group identifier. */
	public static function developing_story_timeline( $post_id ) {
		$group = self::clean_key( get_post_meta( absint( $post_id ), self::META_DEVELOPING_STORY, true ) );
		if ( '' === $group || ! class_exists( 'WP_Query' ) ) {
			return array();
		}
		$query = new \WP_Query(
			array(
				'post_type'      => array( 'post', 'sabri_news' ),
				'post_status'    => 'publish',
				'posts_per_page' => 30,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'meta_query'     => array( array( 'key' => self::META_DEVELOPING_STORY, 'value' => $group, 'compare' => '=' ) ),
				'no_found_rows'  => true,
			)
		);
		return self::public_post_links( (array) $query->posts, self::current_user_id() );
	}

	/** Approved or all expert context. */
	public static function expert_contexts( $post_id, $approved_only = true ) {
		$items = get_post_meta( absint( $post_id ), self::META_EXPERT_CONTEXTS, true );
		$items = is_array( $items ) ? $items : array();
		$out   = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['text'] ) ) {
				continue;
			}
			$status = self::clean_key( isset( $item['status'] ) ? $item['status'] : 'pending' );
			if ( $approved_only && 'approved' !== $status ) {
				continue;
			}
			$author_id = absint( isset( $item['author_id'] ) ? $item['author_id'] : 0 );
			$out[] = array(
				'id'              => self::clean_text( isset( $item['id'] ) ? $item['id'] : '' ),
				'author_id'       => $author_id,
				'author_name'     => $author_id > 0 ? sanitize_text_field( get_the_author_meta( 'display_name', $author_id ) ) : __( 'Expert', 'sabri-complete-home-news-feed' ),
				'text'            => self::clean_textarea( $item['text'] ),
				'status'          => $status,
				'created_at_utc'  => self::clean_text( isset( $item['created_at_utc'] ) ? $item['created_at_utc'] : '' ),
				'verified_doctor' => $author_id > 0 && CanonicalIdentityAdapter::is_verified_doctor( $author_id ),
			);
		}
		return array_slice( $out, -100 );
	}

	/** Evidence card. */
	public static function evidence_card( $post_id ) {
		$value = get_post_meta( absint( $post_id ), self::META_EVIDENCE_CARD, true );
		$value = is_array( $value ) ? $value : array();
		if ( empty( $value['level'] ) ) {
			$value['level'] = get_post_meta( absint( $post_id ), PostMetadata::META_EVIDENCE_LEVEL, true );
		}
		return self::sanitize_evidence( $value );
	}

	/** Source diversity projection. */
	public static function source_diversity( $post_id ) {
		$sources = get_post_meta( absint( $post_id ), self::META_SOURCE_DIVERSITY, true );
		$sources = self::sanitize_sources( $sources );
		$domains = array();
		foreach ( $sources as $source ) {
			$host = wp_parse_url( $source['url'], PHP_URL_HOST );
			if ( $host ) {
				$domains[] = strtolower( preg_replace( '/^www\./', '', $host ) );
			}
		}
		return array( 'count' => count( array_unique( $domains ) ), 'domains' => array_values( array_unique( $domains ) ), 'sources' => $sources );
	}

	/** Full public-safe edit/correction history. */
	public static function edit_history( $post_id ) {
		$post_id = absint( $post_id );
		$out     = array();
		if ( function_exists( 'wp_get_post_revisions' ) ) {
			$revisions = wp_get_post_revisions( $post_id, array( 'posts_per_page' => 20, 'order' => 'DESC' ) );
			foreach ( (array) $revisions as $revision ) {
				$out[] = array( 'date' => get_post_modified_time( 'c', true, $revision ), 'label' => __( 'Edited revision', 'sabri-complete-home-news-feed' ) );
			}
		}
		if ( function_exists( 'get_post_type' ) && 'sabri_news' === get_post_type( $post_id ) && class_exists( __NAMESPACE__ . '\\CorrectionLedger' ) ) {
			foreach ( CorrectionLedger::public_history( $post_id ) as $entry ) {
				$out[] = array( 'date' => self::clean_text( $entry['published_at'] ), 'label' => ucfirst( self::clean_key( $entry['class'] ) ) . ': ' . self::clean_textarea( $entry['public_note'] ) );
			}
		}
		usort( $out, static function ( $a, $b ) { return strcmp( $b['date'], $a['date'] ); } );
		return array_slice( $out, 0, 30 );
	}

	/** Share warning for materially changed, corrected, retracted, stale, or non-public content. */
	public static function share_warning( $post_id ) {
		$post_id  = absint( $post_id );
		$messages = array();
		$status   = function_exists( 'get_post_status' ) ? get_post_status( $post_id ) : '';
		if ( 'publish' !== $status ) {
			$messages[] = __( 'This item is not in ordinary published state.', 'sabri-complete-home-news-feed' );
		}
		$history = self::edit_history( $post_id );
		if ( $history ) {
			$messages[] = __( 'This item has an edit or correction history; review the latest version before sharing.', 'sabri-complete-home-news-feed' );
		}
		$retraction = get_post_meta( $post_id, '_sabri_news_retraction_notice', true );
		$correction = get_post_meta( $post_id, '_sabri_news_correction_notice', true );
		if ( $retraction ) {
			$messages[] = __( 'This item has been retracted.', 'sabri-complete-home-news-feed' );
		} elseif ( $correction ) {
			$messages[] = __( 'This item has a published correction.', 'sabri-complete-home-news-feed' );
		}
		$published = function_exists( 'get_post_time' ) ? (int) get_post_time( 'U', true, $post_id ) : 0;
		if ( $published > 0 && time() - $published > 365 * DAY_IN_SECONDS ) {
			$messages[] = __( 'This content is more than one year old; check whether it is still current.', 'sabri-complete-home-news-feed' );
		}
		return array( 'warn' => ! empty( $messages ), 'message' => implode( ' ', array_unique( $messages ) ) );
	}

	/** Q&A projection with verified-doctor answer badges. */
	public static function qna( $post_id, $decorate = true ) {
		$qna = get_post_meta( absint( $post_id ), self::META_QA, true );
		$qna = is_array( $qna ) ? array_slice( $qna, -100 ) : array();
		if ( ! $decorate ) {
			return $qna;
		}
		$out = array();
		foreach ( $qna as $question ) {
			if ( ! is_array( $question ) || empty( $question['id'] ) || empty( $question['text'] ) ) {
				continue;
			}
			$item = array(
				'id'          => self::clean_text( $question['id'] ),
				'text'        => self::clean_textarea( $question['text'] ),
				'author_id'   => absint( isset( $question['author_id'] ) ? $question['author_id'] : 0 ),
				'author_name' => '',
				'answers'     => array(),
			);
			$item['author_name'] = $item['author_id'] > 0 ? sanitize_text_field( get_the_author_meta( 'display_name', $item['author_id'] ) ) : __( 'Member', 'sabri-complete-home-news-feed' );
			foreach ( isset( $question['answers'] ) && is_array( $question['answers'] ) ? $question['answers'] : array() as $answer ) {
				$author_id = absint( isset( $answer['author_id'] ) ? $answer['author_id'] : 0 );
				$item['answers'][] = array(
					'id'              => self::clean_text( isset( $answer['id'] ) ? $answer['id'] : '' ),
					'text'            => self::clean_textarea( isset( $answer['text'] ) ? $answer['text'] : '' ),
					'author_id'       => $author_id,
					'author_name'     => $author_id > 0 ? sanitize_text_field( get_the_author_meta( 'display_name', $author_id ) ) : __( 'Member', 'sabri-complete-home-news-feed' ),
					'verified_doctor' => $author_id > 0 && CanonicalIdentityAdapter::is_verified_doctor( $author_id ),
				);
			}
			$out[] = $item;
		}
		return $out;
	}

	/** Current user's saved reading percentage for one post. */
	public static function reading_progress_percent( $post_id, $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : self::current_user_id();
		$state   = self::user_state( $user_id );
		$key     = (string) absint( $post_id );
		return isset( $state['progress'][ $key ]['percent'] ) ? absint( $state['progress'][ $key ]['percent'] ) : 0;
	}

	/** Public-safe user state for REST/UI. */
	public static function public_user_state( array $state = array() ) {
		$state = $state ? $state : self::user_state();
		return array(
			'topics'        => $state['topics'],
			'queue_count'   => count( $state['queue'] ),
			'offline_count' => count( $state['offline'] ),
			'low_bandwidth' => (bool) $state['low_bandwidth'],
			'data_saver'    => (bool) $state['data_saver'],
			'last_catch_up' => $state['last_catch_up'],
			'recipe'        => $state['recipe'],
		);
	}

	/** Render compact card actions for approved new social features. */
	private static function render_card_actions( $post_id ) {
		$post_id = absint( $post_id );
		$html    = '<div class="sabri-hnf-ng-actions">';
		if ( self::current_user_id() > 0 ) {
			if ( ComposerPermissions::user_can_create() ) {
				$html .= '<button type="button" class="sabri-hnf-ng-button" data-sabri-ng-action="repost" data-post-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Repost', 'sabri-complete-home-news-feed' ) . '</button>';
				$html .= '<button type="button" class="sabri-hnf-ng-button" data-sabri-ng-action="quote" data-post-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Quote', 'sabri-complete-home-news-feed' ) . '</button>';
			}
			$html .= '<button type="button" class="sabri-hnf-ng-button" data-sabri-ng-action="queue-toggle" data-post-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Read later', 'sabri-complete-home-news-feed' ) . '</button>';
			$html .= '<button type="button" class="sabri-hnf-ng-button" data-sabri-ng-action="offline-toggle" data-post-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Offline', 'sabri-complete-home-news-feed' ) . '</button>';
		}
		$html .= '<a class="sabri-hnf-ng-button" href="' . esc_url( self::rest_url( '/next-generation/share-card/' . $post_id ) ) . '">' . esc_html__( 'Knowledge card', 'sabri-complete-home-news-feed' ) . '</a>';
		return $html . '</div>';
	}

	/** Render structured Q&A. */
	private static function render_qna( $post_id, array $qna ) {
		$html = '<section class="sabri-hnf-ng-qna"><h3>' . esc_html__( 'Questions & answers', 'sabri-complete-home-news-feed' ) . '</h3>';
		foreach ( $qna as $question ) {
			$html .= '<article class="sabri-hnf-ng-question"><p><strong>' . esc_html( $question['author_name'] ) . ':</strong> ' . esc_html( $question['text'] ) . '</p>';
			foreach ( $question['answers'] as $answer ) {
				$html .= '<div class="sabri-hnf-ng-answer"><p>' . esc_html( $answer['text'] ) . '</p><small>' . esc_html( $answer['author_name'] );
				if ( $answer['verified_doctor'] ) {
					$html .= ' - <span class="sabri-hnf-ng-doctor-badge">' . esc_html__( 'Verified Doctor Response', 'sabri-complete-home-news-feed' ) . '</span>';
				}
				$html .= '</small></div>';
			}
			if ( self::current_user_id() > 0 ) {
				$html .= '<button type="button" class="sabri-hnf-ng-button" data-sabri-ng-action="qna-answer" data-post-id="' . esc_attr( $post_id ) . '" data-question-id="' . esc_attr( $question['id'] ) . '">' . esc_html__( 'Answer', 'sabri-complete-home-news-feed' ) . '</button>';
			}
			$html .= '</article>';
		}
		if ( self::current_user_id() > 0 ) {
			$html .= '<button type="button" class="sabri-hnf-ng-button" data-sabri-ng-action="qna-question" data-post-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Ask a question', 'sabri-complete-home-news-feed' ) . '</button>';
			if ( CanonicalIdentityAdapter::is_verified_doctor( self::current_user_id() ) ) {
				$html .= '<button type="button" class="sabri-hnf-ng-button" data-sabri-ng-action="expert-context" data-post-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Add expert context', 'sabri-complete-home-news-feed' ) . '</button>';
			}
		}
		return $html . '</section>';
	}

	/** My Topics preview. */
	private static function render_my_topics_preview( $user_id ) {
		$state = self::user_state( $user_id );
		$html  = '<p><strong>' . esc_html__( 'Followed topics:', 'sabri-complete-home-news-feed' ) . '</strong> ' . esc_html( $state['topics'] ? implode( ', ', $state['topics'] ) : __( 'None yet', 'sabri-complete-home-news-feed' ) ) . '</p>';
		return $html . self::render_post_link_list( self::my_topics_posts( $user_id, 6 ), __( 'Follow a topic to build this Feed.', 'sabri-complete-home-news-feed' ) );
	}

	/** Continue Reading list. */
	private static function render_continue_reading( $user_id ) {
		$items = self::continue_reading_items( $user_id );
		if ( ! $items ) {
			return '<p>' . esc_html__( 'No unfinished reading yet.', 'sabri-complete-home-news-feed' ) . '</p>';
		}
		$html = '<ul>';
		foreach ( $items as $item ) {
			$html .= '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a> - ' . esc_html( $item['percent'] . '%' ) . '</li>';
		}
		return $html . '</ul>';
	}

	/** Render a simple public-safe post link list. */
	private static function render_post_link_list( array $items, $empty_message ) {
		if ( ! $items ) {
			return '' !== $empty_message ? '<p>' . esc_html( $empty_message ) . '</p>' : '';
		}
		$html = '<ul>';
		foreach ( $items as $item ) {
			$html .= '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['title'] ) . '</a>';
			if ( ! empty( $item['date'] ) ) {
				$html .= ' <small>' . esc_html( $item['date'] ) . '</small>';
			}
			$html .= '</li>';
		}
		return $html . '</ul>';
	}

	/** Local File 21 Feed recipe form. */
	private static function render_recipe_form( array $recipe ) {
		$html = '<form class="sabri-hnf-ng-recipe" data-sabri-ng-recipe><p>' . esc_html__( 'These explicit preferences only adjust the local File 21 For You blend. They never create donor, payment, or Founder advantage.', 'sabri-complete-home-news-feed' ) . '</p>';
		foreach ( array( 'latest' => __( 'More Latest', 'sabri-complete-home-news-feed' ), 'doctors' => __( 'More verified doctors', 'sabri-complete-home-news-feed' ), 'research' => __( 'More research', 'sabri-complete-home-news-feed' ) ) as $key => $label ) {
			$html .= '<label>' . esc_html( $label ) . ' <select name="' . esc_attr( $key ) . '">';
			for ( $value = 0; $value <= 3; $value++ ) {
				$html .= '<option value="' . esc_attr( $value ) . '"' . selected( (int) $recipe[ $key ], $value, false ) . '>' . esc_html( (string) $value ) . '</option>';
			}
			$html .= '</select></label>';
		}
		$html .= '<label><input type="checkbox" name="less_personalized" value="1"' . checked( ! empty( $recipe['less_personalized'] ), true, false ) . '> ' . esc_html__( 'Less personalized', 'sabri-complete-home-news-feed' ) . '</label><button type="submit" class="sabri-hnf-ng-button">' . esc_html__( 'Save recipe', 'sabri-complete-home-news-feed' ) . '</button></form>';
		return $html;
	}

	/** Toggle button helper. */
	private static function toggle_button( $action, $enabled, $label ) {
		return '<button type="button" class="sabri-hnf-ng-button' . ( $enabled ? ' is-active' : '' ) . '" data-sabri-ng-action="' . esc_attr( $action ) . '" data-enabled="' . ( $enabled ? '1' : '0' ) . '" aria-pressed="' . ( $enabled ? 'true' : 'false' ) . '">' . esc_html( $label ) . '</button>';
	}

	/** Save normalized private user state. */
	private static function save_user_state( $user_id, array $state ) {
		if ( ! function_exists( 'update_user_meta' ) ) {
			return false;
		}
		$state   = self::user_state_from_array( $state );
		$updated = update_user_meta( absint( $user_id ), self::USER_META, $state );
		if ( false !== $updated ) {
			return true;
		}
		$stored = get_user_meta( absint( $user_id ), self::USER_META, true );
		return is_array( $stored ) && self::user_state_from_array( $stored ) === $state;
	}

	/** Normalize supplied state without re-reading storage. */
	private static function user_state_from_array( array $state ) {
		$defaults = array(
			'topics'        => array(), 'progress' => array(), 'queue' => array(), 'offline' => array(),
			'low_bandwidth' => 0, 'data_saver' => 0, 'last_catch_up' => time() - WEEK_IN_SECONDS, 'recipe' => self::default_recipe(),
		);
		$state = array_merge( $defaults, $state );
		$state['topics']        = array_slice( array_values( array_unique( array_filter( array_map( array( __CLASS__, 'clean_key' ), (array) $state['topics'] ) ) ) ), -100 );
		$state['queue']         = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $state['queue'] ) ) ) ), -100 );
		$state['offline']       = array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $state['offline'] ) ) ) ), -100 );
		$state['progress']      = self::sanitize_progress( $state['progress'] );
		$state['low_bandwidth'] = ! empty( $state['low_bandwidth'] ) ? 1 : 0;
		$state['data_saver']    = ! empty( $state['data_saver'] ) ? 1 : 0;
		$state['last_catch_up'] = max( 0, absint( $state['last_catch_up'] ) );
		$state['recipe']        = self::sanitize_recipe( $state['recipe'] );
		return $state;
	}

	/** Default explicit Feed recipe. */
	private static function default_recipe() {
		return array( 'latest' => 0, 'doctors' => 0, 'research' => 0, 'less_personalized' => 0 );
	}

	/** Normalize recipe weights to small bounded explicit values. */
	private static function sanitize_recipe( $recipe ) {
		$recipe = is_array( $recipe ) ? $recipe : array();
		$out    = self::default_recipe();
		foreach ( array( 'latest', 'doctors', 'research' ) as $key ) {
			$out[ $key ] = min( 3, max( 0, absint( isset( $recipe[ $key ] ) ? $recipe[ $key ] : 0 ) ) );
		}
		$out['less_personalized'] = self::truthy( isset( $recipe['less_personalized'] ) ? $recipe['less_personalized'] : 0 ) ? 1 : 0;
		return $out;
	}

	/** Normalize progress map. */
	private static function sanitize_progress( $progress ) {
		$progress = is_array( $progress ) ? $progress : array();
		$out      = array();
		foreach ( $progress as $post_id => $item ) {
			$post_id = absint( $post_id );
			if ( $post_id < 1 || ! is_array( $item ) ) {
				continue;
			}
			$out[ (string) $post_id ] = array( 'percent' => min( 99, max( 0, absint( isset( $item['percent'] ) ? $item['percent'] : 0 ) ) ), 'updated' => absint( isset( $item['updated'] ) ? $item['updated'] : time() ) );
		}
		return self::bounded_assoc( $out, 100 );
	}

	/** Keep the newest associative entries. */
	private static function bounded_assoc( array $items, $limit ) {
		if ( count( $items ) <= $limit ) {
			return $items;
		}
		uasort( $items, static function ( $a, $b ) { return (int) $b['updated'] <=> (int) $a['updated']; } );
		return array_slice( $items, 0, $limit, true );
	}

	/** Sanitize evidence card. */
	private static function sanitize_evidence( $value ) {
		$value = is_array( $value ) ? $value : array();
		return array(
			'level'                => self::clean_text( isset( $value['level'] ) ? $value['level'] : '' ),
			'review_date'          => self::clean_text( isset( $value['review_date'] ) ? $value['review_date'] : '' ),
			'author_qualification' => self::clean_text( isset( $value['author_qualification'] ) ? $value['author_qualification'] : '' ),
			'uncertainty'          => self::clean_textarea( isset( $value['uncertainty'] ) ? $value['uncertainty'] : '' ),
			'sources'              => self::sanitize_sources( isset( $value['sources'] ) ? $value['sources'] : array() ),
		);
	}

	/** Sanitize source list. */
	private static function sanitize_sources( $sources ) {
		$sources = is_array( $sources ) ? $sources : array();
		$out     = array();
		foreach ( $sources as $source ) {
			if ( is_string( $source ) ) {
				$source = array( 'url' => $source, 'label' => '' );
			}
			if ( ! is_array( $source ) ) {
				continue;
			}
			$url = esc_url_raw( isset( $source['url'] ) ? $source['url'] : '' );
			if ( '' === $url || ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
				continue;
			}
			$out[] = array( 'url' => $url, 'label' => self::clean_text( isset( $source['label'] ) ? $source['label'] : '' ) );
			if ( count( $out ) >= 30 ) {
				break;
			}
		}
		return $out;
	}

	/** Sanitize translation relation list. */
	private static function sanitize_translations( $items ) {
		$items = is_array( $items ) ? $items : array();
		$out   = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$lang = self::clean_key( isset( $item['language'] ) ? $item['language'] : '' );
			$url  = esc_url_raw( isset( $item['url'] ) ? $item['url'] : '' );
			if ( '' === $lang || '' === $url ) {
				continue;
			}
			$out[] = array(
				'language' => $lang,
				'url'      => $url,
				'method'   => in_array( isset( $item['method'] ) ? $item['method'] : '', array( 'human', 'machine' ), true ) ? $item['method'] : 'machine',
				'label'    => self::clean_text( isset( $item['label'] ) ? $item['label'] : strtoupper( $lang ) ),
			);
			if ( count( $out ) >= 12 ) {
				break;
			}
		}
		return $out;
	}

	/** Normalize user IDs. */
	private static function normalize_user_ids( $values, $limit, $exclude = 0 ) {
		$values = is_array( $values ) ? $values : array();
		$ids    = array_values( array_unique( array_filter( array_map( 'absint', $values ) ) ) );
		if ( $exclude > 0 ) {
			$ids = array_values( array_diff( $ids, array( absint( $exclude ) ) ) );
		}
		$out = array();
		foreach ( $ids as $user_id ) {
			if ( function_exists( 'get_userdata' ) && ! get_userdata( $user_id ) ) {
				continue;
			}
			$out[] = $user_id;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/** Convert query posts into public-safe links. */
	private static function public_post_links( array $posts, $user_id ) {
		$out = array();
		foreach ( $posts as $post ) {
			$post_id = is_object( $post ) && isset( $post->ID ) ? absint( $post->ID ) : absint( $post );
			if ( $post_id < 1 || ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
				continue;
			}
			$out[] = array( 'id' => $post_id, 'title' => get_the_title( $post_id ), 'url' => get_permalink( $post_id ), 'date' => get_the_date( '', $post_id ) );
		}
		return $out;
	}

	/** Current URL for safe post-login return. */
	private static function current_url() {
		if ( function_exists( 'home_url' ) ) {
			$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return home_url( '/' . ltrim( $path, '/' ) );
		}
		return '';
	}

	/** REST URL helper. */
	private static function rest_url( $path ) {
		return function_exists( 'rest_url' ) ? rest_url( RestFoundation::NAMESPACE . $path ) : '';
	}

	/** Current user ID. */
	private static function current_user_id() {
		return function_exists( 'get_current_user_id' ) ? absint( get_current_user_id() ) : 0;
	}

	/** Truthy scalar. */
	private static function truthy( $value ) {
		return in_array( strtolower( (string) $value ), array( '1', 'true', 'yes', 'on' ), true ) || true === $value || 1 === $value;
	}

	/** Clean key. */
	public static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) );
	}

	/** Clean short text. */
	private static function clean_text( $value ) {
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( (string) $value ) );
	}

	/** Clean textarea text. */
	private static function clean_textarea( $value ) {
		return function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $value ) : trim( strip_tags( (string) $value ) );
	}

	/** Standard success. */
	private static function success( array $data ) {
		return array( 'success' => true, 'status' => 200, 'data' => $data );
	}

	/** Standard error. */
	private static function error( $code, $message, $status ) {
		return array( 'success' => false, 'status' => absint( $status ), 'code' => self::clean_key( $code ), 'message' => self::clean_text( $message ) );
	}
}
