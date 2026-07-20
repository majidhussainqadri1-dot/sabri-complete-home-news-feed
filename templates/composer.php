<?php
/**
 * Composer template.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form class="sabri-hnf-composer" method="post" action="<?php echo esc_url( $action_url ); ?>" enctype="multipart/form-data" data-sabri-composer data-require-medical-disclaimer="<?php echo ! empty( $settings['composer']['require_medical_disclaimer'] ) ? '1' : '0'; ?>" data-require-patient-consent="<?php echo ! empty( $settings['composer']['require_patient_consent'] ) ? '1' : '0'; ?>">
	<input type="hidden" name="action" value="sabri_public_composer" />
	<?php if ( function_exists( 'wp_nonce_field' ) ) : ?>
		<?php wp_nonce_field( 'sabri_public_composer' ); ?>
	<?php endif; ?>
	<div class="sabri-hnf-composer__status" aria-live="polite"></div>
	<label>
		<span><?php esc_html_e( 'Title', 'sabri-complete-home-news-feed' ); ?></span>
		<input type="text" name="title" maxlength="180" />
	</label>
	<label>
		<span><?php esc_html_e( 'Post', 'sabri-complete-home-news-feed' ); ?></span>
		<textarea name="content" rows="8" required></textarea>
	</label>
	<div class="sabri-hnf-composer__grid">
		<label>
			<span><?php esc_html_e( 'Type', 'sabri-complete-home-news-feed' ); ?></span>
			<select name="feed_type" data-sabri-feed-type>
				<?php foreach ( $feed_types as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span><?php esc_html_e( 'Visibility', 'sabri-complete-home-news-feed' ); ?></span>
			<select name="visibility">
				<?php foreach ( $visibility as $slug ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $default_visibility, $slug ); ?>><?php echo esc_html( ucwords( str_replace( '-', ' ', $slug ) ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span><?php esc_html_e( 'Topic', 'sabri-complete-home-news-feed' ); ?></span>
			<input type="text" name="topic" />
		</label>
		<label>
			<span><?php esc_html_e( 'Language', 'sabri-complete-home-news-feed' ); ?></span>
			<input type="text" name="language" maxlength="32" />
		</label>
		<label>
			<span><?php esc_html_e( 'Country/Region', 'sabri-complete-home-news-feed' ); ?></span>
			<input type="text" name="country_region" maxlength="80" />
		</label>
		<?php if ( ! empty( $settings['composer']['scheduling_enabled'] ) && \Sabri\HomeNewsFeed\ComposerPermissions::user_can_publish() ) : ?>
			<label>
				<span><?php esc_html_e( 'Scheduled Date', 'sabri-complete-home-news-feed' ); ?></span>
				<input type="datetime-local" name="scheduled_date" />
			</label>
		<?php endif; ?>
	</div>
	<?php include SABRI_HNF_PATH . 'templates/composer-clinical-case.php'; ?>
	<?php include SABRI_HNF_PATH . 'templates/composer-research.php'; ?>
	<?php if ( ! empty( $polls_enabled ) ) : ?>
		<?php include SABRI_HNF_PATH . 'templates/composer-poll.php'; ?>
	<?php endif; ?>
	<?php if ( ! empty( $settings['composer']['comments_metadata_enabled'] ) ) : ?>
		<label class="sabri-hnf-check">
			<input type="checkbox" name="comments_enabled" value="1" checked />
			<span><?php esc_html_e( 'Comments enabled metadata', 'sabri-complete-home-news-feed' ); ?></span>
		</label>
	<?php endif; ?>
	<label class="sabri-hnf-check" data-sabri-medical-confirmation hidden>
		<input type="checkbox" name="medical_disclaimer_confirmed" value="1" disabled />
		<span><?php esc_html_e( 'Medical disclaimer confirmed', 'sabri-complete-home-news-feed' ); ?></span>
	</label>
	<label class="sabri-hnf-check" data-sabri-patient-confirmation hidden>
		<input type="checkbox" name="patient_privacy_confirmed" value="1" disabled />
		<span><?php esc_html_e( 'Patient consent and anonymization confirmed', 'sabri-complete-home-news-feed' ); ?></span>
	</label>
	<?php if ( ! empty( $settings['media']['uploads_enabled'] ) ) : ?>
		<label>
			<span><?php esc_html_e( 'Media', 'sabri-complete-home-news-feed' ); ?></span>
			<input type="file" name="sabri_media[]" accept="<?php echo esc_attr( implode( ',', (array) $settings['composer']['allowed_mime_types'] ) ); ?>" multiple />
		</label>
		<div class="sabri-hnf-composer__grid">
			<label>
				<span><?php esc_html_e( 'Alt Text', 'sabri-complete-home-news-feed' ); ?></span>
				<input type="text" name="media_alt_text" maxlength="180" />
			</label>
			<label>
				<span><?php esc_html_e( 'Caption', 'sabri-complete-home-news-feed' ); ?></span>
				<input type="text" name="media_caption" maxlength="220" />
			</label>
		</div>
	<?php endif; ?>
	<div class="sabri-hnf-composer__actions">
		<?php if ( ! empty( $settings['composer']['drafts_enabled'] ) ) : ?>
			<button type="submit" name="composer_action" value="draft"><?php esc_html_e( 'Save Draft', 'sabri-complete-home-news-feed' ); ?></button>
		<?php endif; ?>
		<?php if ( ! empty( $settings['composer']['previews_enabled'] ) ) : ?>
			<button type="submit" name="composer_action" value="preview" formnovalidate><?php esc_html_e( 'Preview', 'sabri-complete-home-news-feed' ); ?></button>
		<?php endif; ?>
		<button type="submit" name="composer_action" value="submit"><?php esc_html_e( 'Submit for Review', 'sabri-complete-home-news-feed' ); ?></button>
		<?php if ( \Sabri\HomeNewsFeed\ComposerPermissions::user_can_publish() ) : ?>
			<button type="submit" name="composer_action" value="publish"><?php esc_html_e( 'Publish', 'sabri-complete-home-news-feed' ); ?></button>
			<?php if ( ! empty( $settings['composer']['scheduling_enabled'] ) ) : ?>
				<button type="submit" name="composer_action" value="schedule"><?php esc_html_e( 'Schedule', 'sabri-complete-home-news-feed' ); ?></button>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</form>
