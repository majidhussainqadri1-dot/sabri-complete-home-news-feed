<?php
/**
 * News settings view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'News Foundation', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Phase 2 can surface Platform News through the Home Feed. Complete editorial News workflows remain deferred.', 'sabri-complete-home-news-feed' ); ?></p>
	<p><strong><?php esc_html_e( 'Runtime status:', 'sabri-complete-home-news-feed' ); ?></strong> <?php echo esc_html( $settings['news']['future_notice'] ); ?></p>
	<label>
		<input type="checkbox" disabled>
		<?php esc_html_e( 'Enable complete News runtime after the relevant implementation phase', 'sabri-complete-home-news-feed' ); ?>
	</label>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'News Safety Boundaries', 'sabri-complete-home-news-feed' ); ?></h2>
	<ul>
		<li><?php esc_html_e( 'No fake news records are created during activation or repair.', 'sabri-complete-home-news-feed' ); ?></li>
		<li><?php esc_html_e( 'Breaking News uses a separate capability and remains administrator/editor controlled.', 'sabri-complete-home-news-feed' ); ?></li>
		<li><?php esc_html_e( 'No external feeds, CDNs, remote fonts, or runtime third-party scripts are used.', 'sabri-complete-home-news-feed' ); ?></li>
	</ul>
</section>
