<?php
/**
 * Feed settings view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\FeedContext;
use Sabri\HomeNewsFeed\PostTypes;
use Sabri\HomeNewsFeed\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$feed = $settings['feed'];
?>
<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Home Feed Runtime', 'sabri-complete-home-news-feed' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sabri_feed_save_settings" />
		<input type="hidden" name="tab" value="feed" />
		<?php wp_nonce_field( 'sabri_feed_save_settings' ); ?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Home Feed', 'sabri-complete-home-news-feed' ); ?></th>
					<td><label><input type="checkbox" name="settings[enabled]" value="1" <?php checked( ! empty( $feed['enabled'] ) ); ?> /> <?php esc_html_e( 'Enabled', 'sabri-complete-home-news-feed' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="sabri-feed-default-mode"><?php esc_html_e( 'Default Mode', 'sabri-complete-home-news-feed' ); ?></label></th>
					<td>
						<select id="sabri-feed-default-mode" name="settings[default_mode]">
							<?php foreach ( FeedContext::modes() as $mode => $label ) : ?>
								<option value="<?php echo esc_attr( $mode ); ?>" <?php selected( $feed['default_mode'], $mode ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sabri-feed-posts-per-page"><?php esc_html_e( 'Posts Per Page', 'sabri-complete-home-news-feed' ); ?></label></th>
					<td><input id="sabri-feed-posts-per-page" type="number" min="1" max="50" name="settings[posts_per_page]" value="<?php echo esc_attr( (int) $feed['posts_per_page'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Pagination', 'sabri-complete-home-news-feed' ); ?></th>
					<td>
						<select name="settings[pagination]">
							<option value="numbers" <?php selected( $feed['pagination'], 'numbers' ); ?>><?php esc_html_e( 'Numbers', 'sabri-complete-home-news-feed' ); ?></option>
							<option value="previous_next" <?php selected( $feed['pagination'], 'previous_next' ); ?>><?php esc_html_e( 'Previous/Next', 'sabri-complete-home-news-feed' ); ?></option>
						</select>
						<label><input type="checkbox" name="settings[load_more_enabled]" value="1" <?php checked( ! empty( $feed['load_more_enabled'] ) ); ?> /> <?php esc_html_e( 'Load More', 'sabri-complete-home-news-feed' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Ranking Weights', 'sabri-complete-home-news-feed' ); ?></th>
					<td>
						<label><?php esc_html_e( 'Founder', 'sabri-complete-home-news-feed' ); ?> <input type="number" min="0" max="100" name="settings[founder_priority]" value="<?php echo esc_attr( (int) $feed['founder_priority'] ); ?>" /></label>
						<label><?php esc_html_e( 'Verified Author', 'sabri-complete-home-news-feed' ); ?> <input type="number" min="0" max="100" name="settings[verified_author_priority]" value="<?php echo esc_attr( (int) $feed['verified_author_priority'] ); ?>" /></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enabled Filters', 'sabri-complete-home-news-feed' ); ?></th>
					<td>
						<?php foreach ( FeedContext::modes() as $mode => $label ) : ?>
							<label><input type="checkbox" name="settings[enabled_filters][]" value="<?php echo esc_attr( $mode ); ?>" <?php checked( in_array( $mode, $feed['enabled_filters'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label><br />
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Card Details', 'sabri-complete-home-news-feed' ); ?></th>
					<td>
						<label><input type="checkbox" name="settings[show_author_details]" value="1" <?php checked( ! empty( $feed['show_author_details'] ) ); ?> /> <?php esc_html_e( 'Author details', 'sabri-complete-home-news-feed' ); ?></label>
						<label><input type="checkbox" name="settings[show_post_type]" value="1" <?php checked( ! empty( $feed['show_post_type'] ) ); ?> /> <?php esc_html_e( 'Post type', 'sabri-complete-home-news-feed' ); ?></label>
						<label><input type="checkbox" name="settings[show_media]" value="1" <?php checked( ! empty( $feed['show_media'] ) ); ?> /> <?php esc_html_e( 'Media', 'sabri-complete-home-news-feed' ); ?></label>
						<label><input type="checkbox" name="settings[show_disclaimer]" value="1" <?php checked( ! empty( $feed['show_disclaimer'] ) ); ?> /> <?php esc_html_e( 'Medical disclaimer', 'sabri-complete-home-news-feed' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sabri-feed-cache-duration"><?php esc_html_e( 'Cache Duration', 'sabri-complete-home-news-feed' ); ?></label></th>
					<td><input id="sabri-feed-cache-duration" type="number" min="0" max="86400" name="settings[cache_duration]" value="<?php echo esc_attr( (int) $feed['cache_duration'] ); ?>" /> <?php esc_html_e( 'seconds', 'sabri-complete-home-news-feed' ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php submit_button( __( 'Save Feed Settings', 'sabri-complete-home-news-feed' ) ); ?>
	</form>
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
