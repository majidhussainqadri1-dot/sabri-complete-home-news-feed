<?php
/**
 * Composer settings view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\SafeMode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Composer Foundation', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Phase 1 creates capability, MIME, privacy, and feature-gate architecture for a future composer. Public composer runtime is intentionally disabled.', 'sabri-complete-home-news-feed' ); ?></p>
	<p><strong><?php esc_html_e( 'Runtime status:', 'sabri-complete-home-news-feed' ); ?></strong> <?php echo esc_html( $settings['composer']['future_notice'] ); ?></p>
	<p><strong><?php esc_html_e( 'Central feature gate:', 'sabri-complete-home-news-feed' ); ?></strong> <?php echo SafeMode::feature_enabled( 'composer' ) ? esc_html__( 'Connected', 'sabri-complete-home-news-feed' ) : esc_html__( 'Disabled', 'sabri-complete-home-news-feed' ); ?></p>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Configured MIME Allow List', 'sabri-complete-home-news-feed' ); ?></h2>
	<ul>
		<?php foreach ( $settings['composer']['allowed_mime_types'] as $mime ) : ?>
			<li><?php echo esc_html( $mime ); ?></li>
		<?php endforeach; ?>
	</ul>
</section>
