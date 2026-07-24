<?php
/**
 * Public retraction accountability projection.
 *
 * @var array<string,mixed> $article
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<main id="main-content" class="sabri-news sabri-news-single sabri-news-retraction">
	<article>
		<header>
			<p class="sabri-news-eyebrow"><?php echo esc_html__( 'Retraction', 'sabri-complete-home-news-feed' ); ?></p>
			<h1><?php echo esc_html( $article['headline'] ); ?></h1>
		</header>
		<div class="sabri-news-notice sabri-news-notice--retraction" role="alert">
			<h2><?php echo esc_html__( 'This article has been retracted', 'sabri-complete-home-news-feed' ); ?></h2>
			<p><?php echo esc_html( $article['retraction_notice'] ); ?></p>
		</div>
	</article>
</main>
