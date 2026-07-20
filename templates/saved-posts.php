<?php
/**
 * Private Saved Posts list.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="sabri-hnf-saved" aria-labelledby="sabri-hnf-saved-title">
	<h2 id="sabri-hnf-saved-title"><?php esc_html_e( 'Saved Posts', 'sabri-complete-home-news-feed' ); ?></h2>
	<?php if ( ! $logged_in ) : ?>
		<p><?php esc_html_e( 'Sign in to view your private saved posts.', 'sabri-complete-home-news-feed' ); ?></p>
		<?php if ( '' !== $login_url ) : ?>
			<a class="sabri-hnf-saved__login" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Sign In', 'sabri-complete-home-news-feed' ); ?></a>
		<?php endif; ?>
	<?php elseif ( empty( $items ) ) : ?>
		<p><?php esc_html_e( 'You have not saved any visible posts yet.', 'sabri-complete-home-news-feed' ); ?></p>
	<?php else : ?>
		<ul class="sabri-hnf-saved__list">
			<?php foreach ( $items as $item ) : ?>
				<li>
					<a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( '' !== $item['title'] ? $item['title'] : __( 'Untitled post', 'sabri-complete-home-news-feed' ) ); ?></a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
