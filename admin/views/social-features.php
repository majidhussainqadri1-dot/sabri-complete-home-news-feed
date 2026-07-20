<?php
/**
 * Phase 3 social feature controls.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\Phase3FeatureSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$features = Phase3FeatureSettings::get();
$catalog  = Phase3FeatureSettings::catalog();
$groups   = array();
foreach ( $catalog as $key => $item ) {
	$group = isset( $item['group'] ) ? (string) $item['group'] : __( 'Features', 'sabri-complete-home-news-feed' );
	$groups[ $group ][ $key ] = $item;
}
?>

<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Phase 3 social feature settings saved.', 'sabri-complete-home-news-feed' ); ?></p></div>
<?php endif; ?>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Phase 3 Social Features', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Enable one gated system at a time in WordPress Playground and then Hostinger staging. Complete its acceptance checklist before enabling the next gated system. Emergency Disable remains available on the Overview screen.', 'sabri-complete-home-news-feed' ); ?></p>
	<div class="notice notice-warning inline">
		<p><strong><?php esc_html_e( 'Staging rule:', 'sabri-complete-home-news-feed' ); ?></strong> <?php esc_html_e( 'Do not enable all gated features together. Notification Bridge must remain disabled until a staging-safe callback is connected.', 'sabri-complete-home-news-feed' ); ?></p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sabri_feed_save_phase3_features" />
		<?php wp_nonce_field( 'sabri_feed_save_phase3_features' ); ?>

		<?php foreach ( $groups as $group => $items ) : ?>
			<h3><?php echo esc_html( $group ); ?></h3>
			<table class="form-table" role="presentation">
				<tbody>
					<?php foreach ( $items as $key => $item ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $item['label'] ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="features[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $features[ $key ] ) ); ?> />
									<?php esc_html_e( 'Enabled', 'sabri-complete-home-news-feed' ); ?>
								</label>
								<p class="description"><?php echo esc_html( $item['description'] ); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>

		<?php submit_button( __( 'Save Social Feature Settings', 'sabri-complete-home-news-feed' ) ); ?>
	</form>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Dependency Rules', 'sabri-complete-home-news-feed' ); ?></h2>
	<ul>
		<li><?php esc_html_e( 'Dislike and public reaction counts are automatically disabled when Like reactions are disabled.', 'sabri-complete-home-news-feed' ); ?></li>
		<li><?php esc_html_e( 'Public follower counts and Followers-only visibility are automatically disabled when Follow and Following are disabled.', 'sabri-complete-home-news-feed' ); ?></li>
		<li><?php esc_html_e( 'Share is a non-mutating browser action and does not require login or a database write.', 'sabri-complete-home-news-feed' ); ?></li>
		<li><?php esc_html_e( 'Comments, Reports, Polls, Notifications, and Views remain subject to their individual staging acceptance and privacy checks.', 'sabri-complete-home-news-feed' ); ?></li>
	</ul>
</section>
