<?php
/**
 * Roles and capabilities view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$role_map = Capabilities::default_role_map( $settings );
?>
<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Capability Policy', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'The plugin grants only plugin-specific capabilities to existing roles. It does not create broad site roles or alter unrelated WordPress capabilities.', 'sabri-complete-home-news-feed' ); ?></p>
	<p><?php esc_html_e( 'Students and patients receive no general publishing capability by default.', 'sabri-complete-home-news-feed' ); ?></p>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Capabilities', 'sabri-complete-home-news-feed' ); ?></h2>
	<table class="sabri-feed-table">
		<thead><tr><th><?php esc_html_e( 'Capability', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Label', 'sabri-complete-home-news-feed' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( Capabilities::labels() as $capability => $label ) : ?>
				<tr><td><?php echo esc_html( $capability ); ?></td><td><?php echo esc_html( $label ); ?></td></tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Default Role Map', 'sabri-complete-home-news-feed' ); ?></h2>
	<table class="sabri-feed-table">
		<thead><tr><th><?php esc_html_e( 'Role', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Plugin capabilities', 'sabri-complete-home-news-feed' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $role_map as $role => $caps ) : ?>
				<tr>
					<td><?php echo esc_html( $role ); ?></td>
					<td><?php echo $caps ? esc_html( implode( ', ', $caps ) ) : esc_html__( 'None', 'sabri-complete-home-news-feed' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</section>
