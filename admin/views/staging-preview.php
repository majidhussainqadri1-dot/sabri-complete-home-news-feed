<?php
/**
 * Administrator-only staging preview.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\HomeIntegration;
use Sabri\HomeNewsFeed\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

HomeIntegration::reset_runtime_guards();
$feed_preview     = Shortcodes::home_feed( array( 'mode' => 'latest', 'per_page' => 5 ) );
$composer_preview = Shortcodes::composer();
?>
<div class="notice notice-info inline">
	<p><?php esc_html_e( 'Administrator-only staging surface. It renders the real Phase 2 feed and composer without creating WordPress test pages.', 'sabri-complete-home-news-feed' ); ?></p>
</div>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Home Feed Preview', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'This preview reads existing staging posts and does not create placeholder content.', 'sabri-complete-home-news-feed' ); ?></p>
	<?php echo $feed_preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Composer Preview', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Use non-sensitive staging data only. Submitting this form creates a real post on the staging site.', 'sabri-complete-home-news-feed' ); ?></p>
	<?php echo $composer_preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</section>
