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
	<?php echo $filters; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<div class="sabri-hnf-feed__status" aria-live="polite"></div>
	<div class="sabri-hnf-feed__list" data-sabri-feed-list>
		<?php echo '' !== $cards ? $cards : $empty_state; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php echo $pagination; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</section>
