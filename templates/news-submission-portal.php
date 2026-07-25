<?php
/** Progressive-enhancement doctor/contributor submission portal. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<section class="sabri-submission-portal" aria-labelledby="sabri-submission-title">
	<h1 id="sabri-submission-title"><?php esc_html_e( 'Submit Editorial News', 'sabri-complete-home-news-feed' ); ?></h1>
	<p><?php esc_html_e( 'Submission does not guarantee publication. Text, media rights, sources, conflicts, sponsorship, AI assistance, and patient privacy must be declared.', 'sabri-complete-home-news-feed' ); ?></p>
	<form method="post" enctype="multipart/form-data" data-sabri-submission-form>
		<?php wp_nonce_field( 'sabri_phase5_submission', 'sabri_phase5_nonce' ); ?>
		<p><label for="sabri-submission-title-field"><?php esc_html_e( 'Headline', 'sabri-complete-home-news-feed' ); ?></label><input id="sabri-submission-title-field" name="title" type="text" maxlength="300" required /></p>
		<p><label for="sabri-submission-summary"><?php esc_html_e( 'Summary', 'sabri-complete-home-news-feed' ); ?></label><textarea id="sabri-submission-summary" name="summary" maxlength="2000"></textarea></p>
		<p><label for="sabri-submission-body"><?php esc_html_e( 'Article or report', 'sabri-complete-home-news-feed' ); ?></label><textarea id="sabri-submission-body" name="body" required></textarea></p>
		<p><label for="sabri-submission-sources"><?php esc_html_e( 'Source URLs, one per line', 'sabri-complete-home-news-feed' ); ?></label><textarea id="sabri-submission-sources" name="source_urls" required></textarea></p>
		<fieldset><legend><?php esc_html_e( 'Required declarations', 'sabri-complete-home-news-feed' ); ?></legend>
			<label><input type="checkbox" name="owns_text" value="1" required /> <?php esc_html_e( 'I own or may submit this text.', 'sabri-complete-home-news-feed' ); ?></label>
			<label><input type="checkbox" name="patient_identifiers_absent" value="1" required /> <?php esc_html_e( 'No unauthorized patient identifiers are included.', 'sabri-complete-home-news-feed' ); ?></label>
			<label><input type="checkbox" name="conflicts_declared" value="1" /> <?php esc_html_e( 'Relevant conflicts have been declared.', 'sabri-complete-home-news-feed' ); ?></label>
			<label><input type="checkbox" name="sponsorship_declared" value="1" /> <?php esc_html_e( 'Sponsorship or payment has been declared.', 'sabri-complete-home-news-feed' ); ?></label>
			<label><input type="checkbox" name="ai_assistance_declared" value="1" /> <?php esc_html_e( 'Material AI assistance has been declared.', 'sabri-complete-home-news-feed' ); ?></label>
		</fieldset>
		<button type="submit"><?php esc_html_e( 'Save submission draft', 'sabri-complete-home-news-feed' ); ?></button>
	</form>
	<div class="sabri-submission-status" aria-live="polite"></div>
</section>
