<?php
/** Accessible Breaking News strip. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$items = isset( $items ) && is_array( $items ) ? $items : array();
if ( ! $items ) { return; }
?>
<section class="sabri-breaking-strip" aria-labelledby="sabri-breaking-title" data-sabri-breaking-strip>
	<div class="sabri-breaking-strip__inner">
		<h2 id="sabri-breaking-title"><?php esc_html_e( 'Breaking News', 'sabri-complete-home-news-feed' ); ?></h2>
		<ul>
		<?php foreach ( $items as $item ) : $article = isset( $item['article'] ) && is_array( $item['article'] ) ? $item['article'] : array(); ?>
			<li>
				<a href="<?php echo esc_url( isset( $article['canonical_url'] ) ? $article['canonical_url'] : '' ); ?>">
					<?php echo esc_html( isset( $article['headline'] ) ? $article['headline'] : '' ); ?>
				</a>
				<?php if ( ! empty( $item['expires_at'] ) ) : ?>
					<time datetime="<?php echo esc_attr( gmdate( 'c', strtotime( $item['expires_at'] . ' UTC' ) ) ); ?>"><?php esc_html_e( 'Temporary alert', 'sabri-complete-home-news-feed' ); ?></time>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
		</ul>
	</div>
</section>
