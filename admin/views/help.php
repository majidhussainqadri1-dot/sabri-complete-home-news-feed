<?php
/**
 * Help view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Phase 2 Boundary', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'This phase provides the Home Feed and public Composer runtime while preserving the Phase 1 safety foundation.', 'sabri-complete-home-news-feed' ); ?></p>
	<ul>
		<li><?php esc_html_e( 'Home Feed uses existing WordPress posts and does not create placeholder production data.', 'sabri-complete-home-news-feed' ); ?></li>
		<li><?php esc_html_e( 'Composer writes enforce server-side capability, visibility, media, and status-transition rules.', 'sabri-complete-home-news-feed' ); ?></li>
		<li><?php esc_html_e( 'Likes, dislikes, comments, replies, saves, follows, reports, and polls remain Phase 3.', 'sabri-complete-home-news-feed' ); ?></li>
		<li><?php esc_html_e( 'Complete editorial News, moderation workflow UI, and analytics dashboards remain future phases.', 'sabri-complete-home-news-feed' ); ?></li>
	</ul>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Safe Operating Notes', 'sabri-complete-home-news-feed' ); ?></h2>
	<ul>
		<li><?php esc_html_e( 'Activation, repair, rollback, and uninstall must preserve WordPress content and companion-plugin data.', 'sabri-complete-home-news-feed' ); ?></li>
		<li><?php esc_html_e( 'Use ?sabri_feed_safe=1 as an administrator for read-only Safe Mode diagnostics.', 'sabri-complete-home-news-feed' ); ?></li>
		<li><?php esc_html_e( 'No production claim is made until CI and Hostinger staging acceptance pass.', 'sabri-complete-home-news-feed' ); ?></li>
	</ul>
</section>
