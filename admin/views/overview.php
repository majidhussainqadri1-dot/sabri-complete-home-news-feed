<?php
/**
 * Overview view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\Database;
use Sabri\HomeNewsFeed\Integrations;
use Sabri\HomeNewsFeed\SafeMode;
use Sabri\HomeNewsFeed\SystemCheck;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$git          = SystemCheck::git_context();
$tables       = Database::table_status();
$caps         = SystemCheck::capability_status();
$integrations = Integrations::detect();
?>
<div class="sabri-feed-grid">
	<section class="sabri-feed-panel">
		<h2><?php esc_html_e( 'Runtime Status', 'sabri-complete-home-news-feed' ); ?></h2>
		<p><strong><?php esc_html_e( 'Plugin version:', 'sabri-complete-home-news-feed' ); ?></strong> <?php echo esc_html( $identity['version'] ); ?></p>
		<p><strong><?php esc_html_e( 'Schema version:', 'sabri-complete-home-news-feed' ); ?></strong> <?php echo esc_html( $identity['schema_version'] ); ?></p>
		<p><strong><?php esc_html_e( 'Environment:', 'sabri-complete-home-news-feed' ); ?></strong> <?php echo esc_html( $settings['general']['environment'] ); ?></p>
		<p><strong><?php esc_html_e( 'Branch:', 'sabri-complete-home-news-feed' ); ?></strong> <?php echo esc_html( $git['branch'] ); ?></p>
		<p><strong><?php esc_html_e( 'Commit:', 'sabri-complete-home-news-feed' ); ?></strong> <?php echo esc_html( $git['commit'] ); ?></p>
		<p><strong><?php esc_html_e( 'Next implementation phase:', 'sabri-complete-home-news-feed' ); ?></strong> <?php esc_html_e( 'Social interactions runtime.', 'sabri-complete-home-news-feed' ); ?></p>
	</section>

	<section class="sabri-feed-panel">
		<h2><?php esc_html_e( 'Safety', 'sabri-complete-home-news-feed' ); ?></h2>
		<p><strong><?php esc_html_e( 'Safe Mode:', 'sabri-complete-home-news-feed' ); ?></strong> <?php echo SafeMode::query_safe_mode() ? esc_html__( 'Connected', 'sabri-complete-home-news-feed' ) : esc_html__( 'Available but not configured', 'sabri-complete-home-news-feed' ); ?></p>
		<p><strong><?php esc_html_e( 'Emergency Disable:', 'sabri-complete-home-news-feed' ); ?></strong> <?php echo SafeMode::emergency_disabled() ? esc_html__( 'Disabled', 'sabri-complete-home-news-feed' ) : esc_html__( 'Connected', 'sabri-complete-home-news-feed' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sabri_feed_emergency">
			<input type="hidden" name="state" value="<?php echo SafeMode::emergency_disabled() ? 'enable' : 'disable'; ?>">
			<?php wp_nonce_field( 'sabri_feed_emergency' ); ?>
			<button class="button button-secondary" type="submit"><?php echo SafeMode::emergency_disabled() ? esc_html__( 'Re-enable Public Runtime', 'sabri-complete-home-news-feed' ) : esc_html__( 'Emergency Disable Public Runtime', 'sabri-complete-home-news-feed' ); ?></button>
		</form>
	</section>
</div>

<div class="sabri-feed-grid">
	<section class="sabri-feed-panel">
		<h2><?php esc_html_e( 'Unified Shell Integration', 'sabri-complete-home-news-feed' ); ?></h2>
		<p class="sabri-feed-status" data-status="<?php echo esc_attr( $integrations['shell']['status'] ); ?>"><?php echo esc_html( $integrations['shell']['status'] ); ?></p>
		<p><?php esc_html_e( 'This plugin does not replace the Shell header, sidebars, global navigation, or layout resolver.', 'sabri-complete-home-news-feed' ); ?></p>
	</section>

	<section class="sabri-feed-panel">
		<h2><?php esc_html_e( 'Migration And Rollback', 'sabri-complete-home-news-feed' ); ?></h2>
		<p><strong><?php esc_html_e( 'Migration status:', 'sabri-complete-home-news-feed' ); ?></strong> <?php echo esc_html( SystemCheck::migration_status() ); ?></p>
		<p><strong><?php esc_html_e( 'Rollback snapshot:', 'sabri-complete-home-news-feed' ); ?></strong> <?php echo esc_html( SystemCheck::snapshot_status() ); ?></p>
	</section>
</div>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Database Table Status', 'sabri-complete-home-news-feed' ); ?></h2>
	<table class="sabri-feed-table">
		<thead><tr><th><?php esc_html_e( 'Table', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'State', 'sabri-complete-home-news-feed' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $tables as $table => $status ) : ?>
				<tr><td><?php echo esc_html( $table ); ?></td><td class="sabri-feed-status" data-status="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status ); ?></td></tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Capability Status', 'sabri-complete-home-news-feed' ); ?></h2>
	<table class="sabri-feed-table">
		<thead><tr><th><?php esc_html_e( 'Role', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'State', 'sabri-complete-home-news-feed' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $caps as $role => $status ) : ?>
				<tr><td><?php echo esc_html( $role ); ?></td><td class="sabri-feed-status" data-status="<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status ); ?></td></tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</section>
