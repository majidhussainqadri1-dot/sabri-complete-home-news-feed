<?php
/**
 * Phase 3E confidential report control.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<details class="sabri-hnf-report" data-sabri-report-control>
	<summary class="sabri-hnf-action sabri-hnf-action--report"><?php esc_html_e( 'Report', 'sabri-complete-home-news-feed' ); ?></summary>
	<div class="sabri-hnf-report__panel">
		<?php if ( $logged_in ) : ?>
			<form
				class="sabri-hnf-report__form"
				data-sabri-report-form
				data-report-url="<?php echo esc_url( $report_url ); ?>"
				data-object-type="<?php echo esc_attr( $object_type ); ?>"
				data-object-id="<?php echo esc_attr( $object_id ); ?>"
				novalidate
			>
				<label>
					<span><?php esc_html_e( 'Reason', 'sabri-complete-home-news-feed' ); ?></span>
					<select name="reason" required data-report-reason>
						<option value=""><?php esc_html_e( 'Select a reason', 'sabri-complete-home-news-feed' ); ?></option>
						<?php foreach ( $reasons as $reason_key => $reason_label ) : ?>
							<option value="<?php echo esc_attr( $reason_key ); ?>"><?php echo esc_html( $reason_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Confidential details', 'sabri-complete-home-news-feed' ); ?></span>
					<textarea name="note" rows="3" maxlength="<?php echo esc_attr( (string) $note_max ); ?>" data-report-note></textarea>
				</label>
				<p class="sabri-hnf-report__help"><?php esc_html_e( 'Only authorized moderators can review this report. Do not include patient-identifying information.', 'sabri-complete-home-news-feed' ); ?></p>
				<button type="submit" class="sabri-hnf-report__submit"><?php esc_html_e( 'Submit Report', 'sabri-complete-home-news-feed' ); ?></button>
				<p class="sabri-hnf-report__status" data-report-status aria-live="polite"></p>
			</form>
		<?php else : ?>
			<p class="sabri-hnf-report__login">
				<?php esc_html_e( 'Sign in to submit a confidential report.', 'sabri-complete-home-news-feed' ); ?>
				<?php if ( '' !== $login_url ) : ?>
					<a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Sign In', 'sabri-complete-home-news-feed' ); ?></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>
</details>
