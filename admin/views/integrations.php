<?php
/**
 * Integrations view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$detected = Integrations::detect();
?>
<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Unified Shell Detection', 'sabri-complete-home-news-feed' ); ?></h2>
	<p class="sabri-feed-status" data-status="<?php echo esc_attr( $detected['shell']['status'] ); ?>"><?php echo esc_html( $detected['shell']['status'] ); ?></p>
	<p><?php esc_html_e( 'The adapter does not fatal when the Shell is absent and does not render duplicate global navigation or replace Shell layout behavior.', 'sabri-complete-home-news-feed' ); ?></p>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Confirmed Existing Shell Hooks', 'sabri-complete-home-news-feed' ); ?></h2>
	<table class="sabri-feed-table">
		<thead><tr><th><?php esc_html_e( 'Hook', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Type', 'sabri-complete-home-news-feed' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( Integrations::confirmed_shell_hooks() as $hook => $type ) : ?>
				<tr><td><?php echo esc_html( $hook ); ?></td><td><?php echo esc_html( $type ); ?></td></tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Plugin-Owned Fallback Hooks', 'sabri-complete-home-news-feed' ); ?></h2>
	<table class="sabri-feed-table">
		<tbody>
			<?php foreach ( Integrations::plugin_owned_hooks() as $hook => $type ) : ?>
				<tr><td><?php echo esc_html( $hook ); ?></td><td><?php echo esc_html( $type ); ?></td></tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Proposed Future Integration Points', 'sabri-complete-home-news-feed' ); ?></h2>
	<table class="sabri-feed-table">
		<tbody>
			<?php foreach ( Integrations::proposed_future_integrations() as $key => $description ) : ?>
				<tr><th><?php echo esc_html( $key ); ?></th><td><?php echo esc_html( $description ); ?></td></tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</section>
