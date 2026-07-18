<?php
/**
 * Feed card template.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<article class="sabri-hnf-card" id="sabri-hnf-post-<?php echo esc_attr( $post_id ); ?>">
	<header class="sabri-hnf-card__header">
		<div class="sabri-hnf-card__avatar"><?php echo $author_avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div class="sabri-hnf-card__byline">
			<div class="sabri-hnf-card__author">
				<span><?php echo esc_html( $author_name ); ?></span>
				<?php foreach ( $badges as $badge ) : ?>
					<span class="sabri-hnf-badge"><?php echo esc_html( $badge ); ?></span>
				<?php endforeach; ?>
			</div>
			<div class="sabri-hnf-card__meta">
				<span><?php echo esc_html( $author_label ); ?></span>
				<?php if ( '' !== $feed_type ) : ?>
					<span><?php echo esc_html( $feed_type ); ?></span>
				<?php endif; ?>
				<time datetime="<?php echo esc_attr( $date ); ?>"><?php echo esc_html( trim( $date . ' ' . $time ) ); ?></time>
				<?php if ( $edited ) : ?>
					<span><?php esc_html_e( 'Edited', 'sabri-complete-home-news-feed' ); ?></span>
				<?php endif; ?>
				<?php if ( 'public' !== $visibility ) : ?>
					<span><?php echo esc_html( ucwords( str_replace( '-', ' ', $visibility ) ) ); ?></span>
				<?php endif; ?>
			</div>
		</div>
	</header>
	<?php if ( '' !== $featured ) : ?>
		<figure class="sabri-hnf-card__media"><?php echo $featured; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></figure>
	<?php endif; ?>
	<div class="sabri-hnf-card__body">
		<?php if ( '' !== $title ) : ?>
			<h3><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a></h3>
		<?php endif; ?>
		<?php if ( '' !== $excerpt ) : ?>
			<p><?php echo esc_html( $excerpt ); ?></p>
		<?php endif; ?>
	</div>
	<?php echo $gallery; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php if ( ! empty( $topics ) || ! empty( $categories ) || ! empty( $hashtags ) ) : ?>
		<footer class="sabri-hnf-card__terms">
			<?php foreach ( array_merge( $topics, $categories, $hashtags ) as $term_label ) : ?>
				<span><?php echo esc_html( $term_label ); ?></span>
			<?php endforeach; ?>
		</footer>
	<?php endif; ?>
	<?php if ( '' !== $disclaimer ) : ?>
		<aside class="sabri-hnf-card__disclaimer"><?php echo esc_html( $disclaimer ); ?></aside>
	<?php endif; ?>
	<?php echo \Sabri\HomeNewsFeed\SocialRuntime::render_action_bar( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<a class="sabri-hnf-card__read-more" href="<?php echo esc_url( $permalink ); ?>"><?php esc_html_e( 'Read More', 'sabri-complete-home-news-feed' ); ?></a>
</article>
