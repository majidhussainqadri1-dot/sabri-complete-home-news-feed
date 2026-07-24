<?php
/**
 * Public News article body.
 *
 * @var array<string,mixed> $article
 * @var array<int,array<string,mixed>> $related
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$media = ! empty( $article['featured_media'] ) && is_array( $article['featured_media'] ) ? $article['featured_media'] : array();
?>
<main id="main-content" class="sabri-news sabri-news-single">
	<article>
		<header class="sabri-news-single__header">
			<p class="sabri-news-eyebrow"><?php echo esc_html( $article['public_label'] ); ?></p>
			<h1><?php echo esc_html( $article['headline'] ); ?></h1>
			<?php if ( ! empty( $article['subtitle'] ) ) : ?>
				<p class="sabri-news-single__subtitle"><?php echo esc_html( $article['subtitle'] ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $article['summary'] ) ) : ?>
				<p class="sabri-news-single__summary"><?php echo esc_html( $article['summary'] ); ?></p>
			<?php endif; ?>
			<div class="sabri-news-byline">
				<?php if ( ! empty( $article['public_author']['name'] ) ) : ?>
					<span><?php echo esc_html( $article['public_author']['name'] ); ?></span>
				<?php endif; ?>
				<?php if ( ! empty( $article['published_at'] ) ) : ?>
					<time datetime="<?php echo esc_attr( $article['published_at'] ); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format', 'F j, Y' ), strtotime( $article['published_at'] ) ) ); ?></time>
				<?php endif; ?>
				<?php if ( ! empty( $article['reading_time'] ) ) : ?>
					<span><?php echo esc_html( sprintf( _n( '%d minute read', '%d minutes read', (int) $article['reading_time'], 'sabri-complete-home-news-feed' ), (int) $article['reading_time'] ) ); ?></span>
				<?php endif; ?>
			</div>
		</header>

		<?php if ( ! empty( $media['url'] ) ) : ?>
			<figure class="sabri-news-single__media">
				<img src="<?php echo esc_url( $media['url'] ); ?>" alt="<?php echo esc_attr( isset( $media['alt'] ) ? $media['alt'] : '' ); ?>" decoding="async" />
				<?php if ( ! empty( $media['caption'] ) || ! empty( $media['credit'] ) ) : ?>
					<figcaption>
						<?php echo esc_html( trim( ( isset( $media['caption'] ) ? $media['caption'] : '' ) . ' ' . ( isset( $media['credit'] ) ? $media['credit'] : '' ) ) ); ?>
					</figcaption>
				<?php endif; ?>
			</figure>
		<?php endif; ?>

		<?php if ( ! empty( $article['correction_state'] ) && 'none' !== $article['correction_state'] ) : ?>
			<div class="sabri-news-notice sabri-news-notice--correction" role="note">
				<strong><?php echo esc_html__( 'Correction status:', 'sabri-complete-home-news-feed' ); ?></strong>
				<?php echo esc_html( ucwords( str_replace( '-', ' ', $article['correction_state'] ) ) ); ?>
			</div>
		<?php endif; ?>

		<div class="sabri-news-single__content">
			<?php echo $article['body_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by NewsPublicProjector. ?>
		</div>

		<?php if ( ! empty( $article['disclaimer'] ) ) : ?>
			<aside class="sabri-news-notice" role="note">
				<h2><?php echo esc_html__( 'Public information notice', 'sabri-complete-home-news-feed' ); ?></h2>
				<p><?php echo esc_html( $article['disclaimer'] ); ?></p>
			</aside>
		<?php endif; ?>

		<?php if ( ! empty( $article['conflict_disclosure'] ) ) : ?>
			<aside class="sabri-news-notice" role="note">
				<h2><?php echo esc_html__( 'Conflict disclosure', 'sabri-complete-home-news-feed' ); ?></h2>
				<p><?php echo esc_html( $article['conflict_disclosure'] ); ?></p>
			</aside>
		<?php endif; ?>

		<?php if ( ! empty( $article['section'] ) || ! empty( $article['topics'] ) ) : ?>
			<nav class="sabri-news-taxonomies" aria-label="<?php echo esc_attr__( 'Article topics', 'sabri-complete-home-news-feed' ); ?>">
				<?php foreach ( array_merge( $article['section'], $article['topics'] ) as $term ) : ?>
					<a href="<?php echo esc_url( $term['url'] ); ?>"><?php echo esc_html( $term['name'] ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</article>

	<?php if ( $related ) : ?>
		<section class="sabri-news-related" aria-labelledby="sabri-news-related-title">
			<h2 id="sabri-news-related-title"><?php echo esc_html__( 'Related News', 'sabri-complete-home-news-feed' ); ?></h2>
			<div class="sabri-news-grid">
				<?php foreach ( $related as $item ) : ?>
					<?php echo \Sabri\HomeNewsFeed\NewsPublicRuntime::render_card( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
</main>
