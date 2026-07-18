<?php
/**
 * Composer settings view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\FeedContext;
use Sabri\HomeNewsFeed\MediaHandler;
use Sabri\HomeNewsFeed\SafeMode;
use Sabri\HomeNewsFeed\Taxonomies;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$composer = $settings['composer'];
?>
<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Public Composer Runtime', 'sabri-complete-home-news-feed' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sabri_feed_save_settings" />
		<input type="hidden" name="tab" value="composer" />
		<?php wp_nonce_field( 'sabri_feed_save_settings' ); ?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Public Composer', 'sabri-complete-home-news-feed' ); ?></th>
					<td><label><input type="checkbox" name="settings[public_composer_enabled]" value="1" <?php checked( ! empty( $composer['public_composer_enabled'] ) ); ?> /> <?php esc_html_e( 'Enabled', 'sabri-complete-home-news-feed' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Runtime Status', 'sabri-complete-home-news-feed' ); ?></th>
					<td><?php echo SafeMode::feature_enabled( 'composer' ) ? esc_html__( 'Connected', 'sabri-complete-home-news-feed' ) : esc_html__( 'Disabled', 'sabri-complete-home-news-feed' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Allowed Feed Types', 'sabri-complete-home-news-feed' ); ?></th>
					<td>
						<?php foreach ( Taxonomies::feed_type_terms() as $slug => $label ) : ?>
							<?php if ( in_array( $slug, FeedContext::phase2_feed_type_slugs(), true ) ) : ?>
								<label><input type="checkbox" name="settings[allowed_feed_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $composer['allowed_feed_types'], true ) ); ?> /> <?php echo esc_html( $label ); ?></label><br />
							<?php endif; ?>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Visibility Modes', 'sabri-complete-home-news-feed' ); ?></th>
					<td>
						<?php foreach ( FeedContext::phase2_visibility_slugs( true ) as $slug ) : ?>
							<label><input type="checkbox" name="settings[allowed_visibility_modes][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $composer['allowed_visibility_modes'], true ) ); ?> /> <?php echo esc_html( ucwords( str_replace( '-', ' ', $slug ) ) ); ?></label><br />
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Publishing Policy', 'sabri-complete-home-news-feed' ); ?></th>
					<td>
						<select name="settings[immediate_publish_policy]">
							<option value="capability" <?php selected( $composer['immediate_publish_policy'], 'capability' ); ?>><?php esc_html_e( 'Capability based', 'sabri-complete-home-news-feed' ); ?></option>
						</select>
						<select name="settings[review_required_policy]">
							<option value="unverified_doctors" <?php selected( $composer['review_required_policy'], 'unverified_doctors' ); ?>><?php esc_html_e( 'Unverified doctors require review', 'sabri-complete-home-news-feed' ); ?></option>
							<option value="all_doctors" <?php selected( $composer['review_required_policy'], 'all_doctors' ); ?>><?php esc_html_e( 'All doctors require review unless explicitly permitted', 'sabri-complete-home-news-feed' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Workflow', 'sabri-complete-home-news-feed' ); ?></th>
					<td>
						<label><input type="checkbox" name="settings[drafts_enabled]" value="1" <?php checked( ! empty( $composer['drafts_enabled'] ) ); ?> /> <?php esc_html_e( 'Drafts', 'sabri-complete-home-news-feed' ); ?></label>
						<label><input type="checkbox" name="settings[previews_enabled]" value="1" <?php checked( ! empty( $composer['previews_enabled'] ) ); ?> /> <?php esc_html_e( 'Previews', 'sabri-complete-home-news-feed' ); ?></label>
						<label><input type="checkbox" name="settings[scheduling_enabled]" value="1" <?php checked( ! empty( $composer['scheduling_enabled'] ) ); ?> /> <?php esc_html_e( 'Scheduling', 'sabri-complete-home-news-feed' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Required Confirmations', 'sabri-complete-home-news-feed' ); ?></th>
					<td>
						<label><input type="checkbox" name="settings[require_patient_consent]" value="1" <?php checked( ! empty( $composer['require_patient_consent'] ) ); ?> /> <?php esc_html_e( 'Patient consent', 'sabri-complete-home-news-feed' ); ?></label>
						<label><input type="checkbox" name="settings[require_medical_disclaimer]" value="1" <?php checked( ! empty( $composer['require_medical_disclaimer'] ) ); ?> /> <?php esc_html_e( 'Medical disclaimer', 'sabri-complete-home-news-feed' ); ?></label>
						<label><input type="checkbox" name="settings[comments_metadata_enabled]" value="1" <?php checked( ! empty( $composer['comments_metadata_enabled'] ) ); ?> /> <?php esc_html_e( 'Comments metadata', 'sabri-complete-home-news-feed' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="sabri-max-upload-mb"><?php esc_html_e( 'Upload Limit', 'sabri-complete-home-news-feed' ); ?></label></th>
					<td><input id="sabri-max-upload-mb" type="number" min="1" max="64" name="settings[max_upload_mb]" value="<?php echo esc_attr( (int) $composer['max_upload_mb'] ); ?>" /> <?php esc_html_e( 'MB', 'sabri-complete-home-news-feed' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="sabri-max-image-count"><?php esc_html_e( 'Maximum Image Count', 'sabri-complete-home-news-feed' ); ?></label></th>
					<td><input id="sabri-max-image-count" type="number" min="1" max="20" name="settings[max_image_count]" value="<?php echo esc_attr( (int) $composer['max_image_count'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Allowed MIME Types', 'sabri-complete-home-news-feed' ); ?></th>
					<td>
						<?php foreach ( array_values( MediaHandler::supported_mime_map() ) as $mime ) : ?>
							<label><input type="checkbox" name="settings[allowed_mime_types][]" value="<?php echo esc_attr( $mime ); ?>" <?php checked( in_array( $mime, $composer['allowed_mime_types'], true ) ); ?> /> <?php echo esc_html( $mime ); ?></label><br />
						<?php endforeach; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php submit_button( __( 'Save Composer Settings', 'sabri-complete-home-news-feed' ) ); ?>
	</form>
</section>
