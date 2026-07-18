<?php
/**
 * Pagination template.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( '' !== $pagination || ! empty( $result['has_more'] ) ) : ?>
	<nav class="sabri-hnf-pagination" aria-label="<?php esc_attr_e( 'Home Feed pagination', 'sabri-complete-home-news-feed' ); ?>">
		<?php echo $pagination; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php if ( ! empty( $settings['feed']['load_more_enabled'] ) && ! empty( $result['has_more'] ) ) : ?>
			<button type="button" class="sabri-hnf-load-more" data-sabri-load-more data-next-url="<?php echo esc_url( $next_url ); ?>" data-state-url="<?php echo esc_url( $state_url ); ?>" data-next-page="<?php echo esc_attr( (int) $result['page'] + 1 ); ?>">
				<?php esc_html_e( 'Load More', 'sabri-complete-home-news-feed' ); ?>
			</button>
		<?php endif; ?>
	</nav>
<?php endif; ?>
