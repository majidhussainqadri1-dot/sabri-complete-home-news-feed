<?php
/**
 * System check view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\HarmonizationDiagnostics;
use Sabri\HomeNewsFeed\SystemCheck;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$report = SystemCheck::report();
if ( class_exists( HarmonizationDiagnostics::class ) ) {
	$report = array_merge( $report, HarmonizationDiagnostics::rows() );
}
?>
<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'System Check', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'This report covers the foundation and the File 21 comprehensive harmonization runtime. GitHub exact-head QA and WordPress visual acceptance remain separate required gates.', 'sabri-complete-home-news-feed' ); ?></p>
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
