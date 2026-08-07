<?php
/**
 * Home Feed template.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="sabri-hnf-feed" data-sabri-feed-mode="<?php echo esc_attr( $result['mode'] ); ?>" data-sabri-feed-page="<?php echo esc_attr( $result['page'] ); ?>" aria-labelledby="sabri-hnf-feed-title">
	<header class="sabri-hnf-feed__header">
		<h2 id="sabri-hnf-feed-title"><?php esc_html_e( 'Home Feed', 'sabri-complete-home-news-feed' ); ?></h2>
	</header>
	<?php
	if ( class_exists( '\\Sabri\\HomeNewsFeed\\HomeCompositionRegistry' ) ) {
		echo \Sabri\HomeNewsFeed\HomeCompositionRegistry::render_control_bar( $result['mode'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		echo $filters; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	if ( class_exists( '\\Sabri\\HomeNewsFeed\\FeedUserAgency' ) ) {
		echo \Sabri\HomeNewsFeed\FeedUserAgency::global_controls( $result['mode'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	?>
	<div class="sabri-hnf-feed__status" aria-live="polite"></div>
	<div class="sabri-hnf-feed__list" data-sabri-feed-list>
		<?php echo '' !== $cards ? $cards : $empty_state; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php echo $pagination; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="sabri-hnf-session-boundary" role="note">
		<p><?php esc_html_e( 'You have reached a natural stopping point. You can continue when useful, switch to Latest, or take a break.', 'sabri-complete-home-news-feed' ); ?></p>
	</div>
</section>
