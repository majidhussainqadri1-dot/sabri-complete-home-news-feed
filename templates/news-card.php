<?php
/**
 * Editorial News card partial.
 *
 * @var array<string,mixed> $item
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$image = ! empty( $item['image'] ) && is_array( $item['image'] ) ? $item['image'] : array();
$section = ! empty( $item['section'][0] ) && is_array( $item['section'][0] ) ? $item['section'][0] : array();
?>
<article class="sabri-news-card" data-sabri-global-key="<?php echo esc_attr( $item['global_key'] ); ?>">
	<?php if ( ! empty( $image['url'] ) ) : ?>
		<a class="sabri-news-card__media" href="<?php echo esc_url( $item['canonical_url'] ); ?>" tabindex="-1" aria-hidden="true">
			<img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( isset( $image['alt'] ) ? $image['alt'] : '' ); ?>" loading="lazy" decoding="async" />
		</a>
	<?php endif; ?>
	<div class="sabri-news-card__body">
		<div class="sabri-news-card__meta">
			<span class="sabri-news-card__label"><?php echo esc_html( $item['public_label'] ); ?></span>
			<?php if ( ! empty( $section['name'] ) ) : ?>
				<a href="<?php echo esc_url( $section['url'] ); ?>"><?php echo esc_html( $section['name'] ); ?></a>
			<?php endif; ?>
		</div>
		<h2 class="sabri-news-card__title">
			<a href="<?php echo esc_url( $item['canonical_url'] ); ?>"><?php echo esc_html( $item['headline'] ); ?></a>
		</h2>
		<?php if ( ! empty( $item['summary'] ) ) : ?>
			<p class="sabri-news-card__summary"><?php echo esc_html( $item['summary'] ); ?></p>
		<?php endif; ?>
		<div class="sabri-news-card__footer">
			<?php if ( ! empty( $item['published_at'] ) ) : ?>
				<time datetime="<?php echo esc_attr( $item['published_at'] ); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format', 'F j, Y' ), strtotime( $item['published_at'] ) ) ); ?></time>
			<?php endif; ?>
			<?php if ( ! empty( $item['reading_time'] ) ) : ?>
				<span><?php echo esc_html( sprintf( _n( '%d minute read', '%d minutes read', (int) $item['reading_time'], 'sabri-complete-home-news-feed' ), (int) $item['reading_time'] ) ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</article>
