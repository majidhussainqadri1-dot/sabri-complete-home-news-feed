<?php
/** Approved public sources and accountability history. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$sources = isset( $sources ) && is_array( $sources ) ? $sources : array();
$history = isset( $history ) && is_array( $history ) ? $history : array();
?>
<?php if ( $sources ) : ?>
<section class="sabri-news-sources" aria-labelledby="sabri-news-sources-title">
	<h2 id="sabri-news-sources-title"><?php esc_html_e( 'Sources and evidence', 'sabri-complete-home-news-feed' ); ?></h2>
	<ol>
	<?php foreach ( $sources as $source ) : ?>
		<li>
			<?php if ( ! empty( $source['public_url'] ) ) : ?><a rel="external nofollow noopener" href="<?php echo esc_url( $source['public_url'] ); ?>"><?php echo esc_html( $source['title'] ); ?></a><?php else : ?><span><?php echo esc_html( $source['title'] ); ?></span><?php endif; ?>
			<?php if ( ! empty( $source['publisher'] ) ) : ?><span class="sabri-news-source__publisher"> — <?php echo esc_html( $source['publisher'] ); ?></span><?php endif; ?>
			<?php if ( ! empty( $source['evidence_class'] ) ) : ?><span class="sabri-news-label"><?php echo esc_html( ucwords( str_replace( '-', ' ', $source['evidence_class'] ) ) ); ?></span><?php endif; ?>
			<?php if ( ! empty( $source['public_citation'] ) ) : ?><p><?php echo esc_html( $source['public_citation'] ); ?></p><?php endif; ?>
		</li>
	<?php endforeach; ?>
	</ol>
</section>
<?php endif; ?>
<?php if ( $history ) : ?>
<section class="sabri-news-history" aria-labelledby="sabri-news-history-title">
	<h2 id="sabri-news-history-title"><?php esc_html_e( 'Correction and retraction history', 'sabri-complete-home-news-feed' ); ?></h2>
	<ol>
	<?php foreach ( $history as $entry ) : ?>
		<li>
			<strong><?php echo esc_html( ucwords( str_replace( '-', ' ', $entry['class'] ) ) ); ?></strong>
			<?php if ( ! empty( $entry['published_at'] ) ) : ?><time datetime="<?php echo esc_attr( gmdate( 'c', strtotime( $entry['published_at'] . ' UTC' ) ) ); ?>"><?php echo esc_html( get_date_from_gmt( $entry['published_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></time><?php endif; ?>
			<p><?php echo esc_html( $entry['public_note'] ); ?></p>
		</li>
	<?php endforeach; ?>
	</ol>
</section>
<?php endif; ?>
