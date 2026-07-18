<?php
/**
 * Private Following list.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="sabri-hnf-following" aria-labelledby="sabri-hnf-following-title">
	<h2 id="sabri-hnf-following-title"><?php esc_html_e( 'Following', 'sabri-complete-home-news-feed' ); ?></h2>
	<?php if ( ! $logged_in ) : ?>
		<p><?php esc_html_e( 'Sign in to view the people you follow.', 'sabri-complete-home-news-feed' ); ?></p>
		<?php if ( '' !== $login_url ) : ?>
			<a class="sabri-hnf-following__login" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Sign In', 'sabri-complete-home-news-feed' ); ?></a>
		<?php endif; ?>
	<?php elseif ( empty( $items ) ) : ?>
		<p><?php esc_html_e( 'You are not following any available members yet.', 'sabri-complete-home-news-feed' ); ?></p>
	<?php else : ?>
		<ul class="sabri-hnf-following__list">
			<?php foreach ( $items as $item ) : ?>
				<li class="sabri-hnf-following__item">
					<span class="sabri-hnf-following__avatar"><?php echo $item['avatar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div class="sabri-hnf-following__identity">
						<?php if ( '' !== $item['profile_url'] ) : ?>
							<a href="<?php echo esc_url( $item['profile_url'] ); ?>"><?php echo esc_html( $item['display_name'] ); ?></a>
						<?php else : ?>
							<span><?php echo esc_html( $item['display_name'] ); ?></span>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
