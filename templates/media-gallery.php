<?php
/**
 * Media gallery template.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sabri-hnf-gallery">
	<?php foreach ( $attachment_ids as $attachment_id ) : ?>
		<?php if ( class_exists( 'Sabri\\HomeNewsFeed\\MediaHandler' ) && ! \Sabri\HomeNewsFeed\MediaHandler::attachment_publicly_visible( $attachment_id ) ) : ?>
			<?php continue; ?>
		<?php endif; ?>
		<figure class="sabri-hnf-gallery__item">
			<?php
			$mime = function_exists( 'get_post_mime_type' ) ? (string) get_post_mime_type( $attachment_id ) : '';
			$url  = function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $attachment_id ) : '';
			if ( 0 === strpos( $mime, 'image/' ) && function_exists( 'wp_get_attachment_image' ) ) {
				echo wp_get_attachment_image( $attachment_id, 'medium_large', false, array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			} elseif ( '' !== $url ) {
				?>
				<a class="sabri-hnf-gallery__file" href="<?php echo esc_url( $url ); ?>">
					<span><?php echo esc_html( 0 === strpos( $mime, 'video/' ) ? __( 'Video', 'sabri-complete-home-news-feed' ) : ( 0 === strpos( $mime, 'audio/' ) ? __( 'Audio', 'sabri-complete-home-news-feed' ) : __( 'Document', 'sabri-complete-home-news-feed' ) ) ); ?></span>
				</a>
				<?php
			}
			?>
		</figure>
	<?php endforeach; ?>
</div>
