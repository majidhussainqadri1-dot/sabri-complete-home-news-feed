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
	<h2><?php esc_html_e( 'Phase 1 Boundary', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'This phase builds the production-grade foundation and data architecture for later Home Feed, Composer, Social Interactions, News, Moderation, and Analytics work.', 'sabri-complete-home-news-feed' ); ?></p>
	<ul>
		<li><?php esc_html_e( 'Home Feed runtime is not complete in Phase 1.', 'sabri-complete-home-news-feed' ); ?></li>
		<li><?php esc_html_e( 'Composer runtime is not complete in Phase 1.', 'sabri-complete-home-news-feed' ); ?></li>
		<li><?php esc_html_e( 'Social interactions endpoints are not complete in Phase 1.', 'sabri-complete-home-news-feed' ); ?></li>
		<li><?php esc_html_e( 'News publishing workflows, moderation workflows, and analytics dashboards are future phases.', 'sabri-complete-home-news-feed' ); ?></li>
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
