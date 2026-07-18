<?php
/**
 * Feed settings view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\PostTypes;
use Sabri\HomeNewsFeed\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Feed Architecture', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Phase 1 uses the WordPress post type and attaches feed taxonomies and metadata. It does not duplicate ordinary posts or render a complete Home feed.', 'sabri-complete-home-news-feed' ); ?></p>
	<p><strong><?php esc_html_e( 'Future controls:', 'sabri-complete-home-news-feed' ); ?></strong> <?php echo esc_html( $settings['feed']['future_notice'] ); ?></p>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'WordPress Post Usage Policy', 'sabri-complete-home-news-feed' ); ?></h2>
	<table class="sabri-feed-table">
		<tbody>
			<?php foreach ( PostTypes::usage_policy() as $area => $policy ) : ?>
				<tr><th><?php echo esc_html( str_replace( '_', ' ', $area ) ); ?></th><td><?php echo esc_html( $policy ); ?></td></tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Default Feed Types', 'sabri-complete-home-news-feed' ); ?></h2>
	<table class="sabri-feed-table">
		<thead><tr><th><?php esc_html_e( 'Slug', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Label', 'sabri-complete-home-news-feed' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( Taxonomies::feed_type_terms() as $slug => $label ) : ?>
				<tr><td><?php echo esc_html( $slug ); ?></td><td><?php echo esc_html( $label ); ?></td></tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</section>
