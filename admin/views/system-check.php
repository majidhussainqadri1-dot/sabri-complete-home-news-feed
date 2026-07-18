<?php
/**
 * System check view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\SystemCheck;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$report = SystemCheck::report();
?>
<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'System Check', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'This report is a Phase 2 diagnostic snapshot. Production acceptance still requires GitHub Actions and Hostinger staging checks.', 'sabri-complete-home-news-feed' ); ?></p>
	<table class="sabri-feed-table">
		<thead><tr><th><?php esc_html_e( 'Check', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'State', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Detail', 'sabri-complete-home-news-feed' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $report as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row['label'] ); ?></td>
					<td class="sabri-feed-status" data-status="<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $row['status'] ); ?></td>
					<td><?php echo esc_html( $row['detail'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</section>
