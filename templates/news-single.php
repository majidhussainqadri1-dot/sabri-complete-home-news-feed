<?php
/** Public News article body. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$media = ! empty( $article['featured_media'] ) && is_array( $article['featured_media'] ) ? $article['featured_media'] : array();
$taxonomy_groups = array(
	__( 'Sections', 'sabri-complete-home-news-feed' ) => isset( $article['section'] ) ? $article['section'] : array(),
	__( 'Topics', 'sabri-complete-home-news-feed' ) => isset( $article['topics'] ) ? $article['topics'] : array(),
	__( 'Countries', 'sabri-complete-home-news-feed' ) => isset( $article['country'] ) ? $article['country'] : array(),
	__( 'Regions', 'sabri-complete-home-news-feed' ) => isset( $article['region'] ) ? $article['region'] : array(),
	__( 'Article type', 'sabri-complete-home-news-feed' ) => ! empty( $article['article_type_term'] ) ? array( $article['article_type_term'] ) : array(),
);
?>
<main id="main-content" class="sabri-news sabri-news-single">
	<article>
		<header class="sabri-news-single__header">
			<p class="sabri-news-eyebrow"><?php echo esc_html( $article['public_label'] ); ?></p>
			<h1><?php echo esc_html( $article['headline'] ); ?></h1>
			<?php if ( ! empty( $article['subtitle'] ) ) : ?><p class="sabri-news-single__subtitle"><?php echo esc_html( $article['subtitle'] ); ?></p><?php endif; ?>
			<?php if ( ! empty( $article['summary'] ) ) : ?><p class="sabri-news-single__summary"><?php echo esc_html( $article['summary'] ); ?></p><?php endif; ?>
			<div class="sabri-news-byline">
				<?php if ( ! empty( $article['public_author']['name'] ) ) : ?>
					<?php if ( ! empty( $article['public_author']['url'] ) ) : ?><a href="<?php echo esc_url( $article['public_author']['url'] ); ?>"><?php echo esc_html( $article['public_author']['name'] ); ?></a><?php else : ?><span><?php echo esc_html( $article['public_author']['name'] ); ?></span><?php endif; ?>
				<?php endif; ?>
				<?php if ( ! empty( $article['reviewing_editor']['name'] ) ) : ?><span><?php echo esc_html( sprintf( __( 'Reviewed by %s', 'sabri-complete-home-news-feed' ), $article['reviewing_editor']['name'] ) ); ?></span><?php endif; ?>
				<?php if ( ! empty( $article['published_at'] ) ) : ?><span><?php echo esc_html__( 'Published', 'sabri-complete-home-news-feed' ); ?> <time datetime="<?php echo esc_attr( $article['published_at'] ); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format', 'F j, Y' ), strtotime( $article['published_at'] ) ) ); ?></time></span><?php endif; ?>
				<?php if ( ! empty( $article['updated_at'] ) && $article['updated_at'] !== $article['published_at'] ) : ?><span><?php echo esc_html__( 'Updated', 'sabri-complete-home-news-feed' ); ?> <time datetime="<?php echo esc_attr( $article['updated_at'] ); ?>"><?php echo esc_html( date_i18n( get_option( 'date_format', 'F j, Y' ), strtotime( $article['updated_at'] ) ) ); ?></time></span><?php endif; ?>
				<?php if ( ! empty( $article['reading_time'] ) ) : ?><span><?php echo esc_html( sprintf( _n( '%d minute read', '%d minutes read', (int) $article['reading_time'], 'sabri-complete-home-news-feed' ), (int) $article['reading_time'] ) ); ?></span><?php endif; ?>
			</div>
			<div class="sabri-news-share" aria-label="<?php echo esc_attr__( 'Article sharing', 'sabri-complete-home-news-feed' ); ?>">
				<a href="<?php echo esc_url( $article['canonical_url'] ); ?>"><?php echo esc_html__( 'Permanent link', 'sabri-complete-home-news-feed' ); ?></a>
				<button type="button" data-sabri-news-copy-link data-url="<?php echo esc_url( $article['canonical_url'] ); ?>"><?php echo esc_html__( 'Copy link', 'sabri-complete-home-news-feed' ); ?></button>
				<span class="screen-reader-text" data-sabri-news-copy-status aria-live="polite"></span>
			</div>
		</header>
		<?php if ( ! empty( $media['url'] ) ) : ?><figure class="sabri-news-single__media"><img src="<?php echo esc_url( $media['url'] ); ?>" alt="<?php echo esc_attr( isset( $media['alt'] ) ? $media['alt'] : '' ); ?>" decoding="async" /><?php if ( ! empty( $media['caption'] ) || ! empty( $media['credit'] ) ) : ?><figcaption><?php echo esc_html( trim( ( isset( $media['caption'] ) ? $media['caption'] : '' ) . ' ' . ( isset( $media['credit'] ) ? $media['credit'] : '' ) ) ); ?></figcaption><?php endif; ?></figure><?php endif; ?>
		<?php if ( ! empty( $article['correction_state'] ) && 'corrected' === $article['correction_state'] ) { echo \Sabri\HomeNewsFeed\NewsPublicRuntime::template( 'news-correction-notice', array( 'notice' => isset( $article['correction_notice'] ) ? $article['correction_notice'] : '' ) ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="sabri-news-single__content"><?php echo $article['body_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php if ( ! empty( $article['disclaimer'] ) ) : ?><aside class="sabri-news-notice" role="note"><h2><?php echo esc_html__( 'Public information notice', 'sabri-complete-home-news-feed' ); ?></h2><p><?php echo esc_html( $article['disclaimer'] ); ?></p></aside><?php endif; ?>
		<?php if ( ! empty( $article['conflict_disclosure'] ) ) : ?><aside class="sabri-news-notice" role="note"><h2><?php echo esc_html__( 'Conflict disclosure', 'sabri-complete-home-news-feed' ); ?></h2><p><?php echo esc_html( $article['conflict_disclosure'] ); ?></p></aside><?php endif; ?>
		<div class="sabri-news-taxonomy-groups">
			<?php foreach ( $taxonomy_groups as $label => $terms ) : if ( empty( $terms ) ) { continue; } ?>
				<nav class="sabri-news-taxonomies" aria-label="<?php echo esc_attr( $label ); ?>"><strong><?php echo esc_html( $label ); ?>:</strong><?php foreach ( $terms as $term ) : ?><a href="<?php echo esc_url( $term['url'] ); ?>"><?php echo esc_html( $term['name'] ); ?></a><?php endforeach; ?></nav>
			<?php endforeach; ?>
		</div>
		<?php do_action( 'sabri_news_after_article', $article ); ?>
		<?php echo $interactions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</article>
	<?php if ( $related ) : ?><section class="sabri-news-related" aria-labelledby="sabri-news-related-title"><h2 id="sabri-news-related-title"><?php echo esc_html__( 'Related News', 'sabri-complete-home-news-feed' ); ?></h2><div class="sabri-news-grid"><?php foreach ( $related as $item ) { echo \Sabri\HomeNewsFeed\NewsPublicRuntime::render_card( $item ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></section><?php endif; ?>
</main>
