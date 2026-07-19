<?php
/**
 * Phase 3F Poll composer fields.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<fieldset class="sabri-hnf-composer__structured" data-sabri-type-panel="poll" hidden>
	<legend><?php esc_html_e( 'Poll', 'sabri-complete-home-news-feed' ); ?></legend>
	<label>
		<span><?php esc_html_e( 'Poll Question', 'sabri-complete-home-news-feed' ); ?></span>
		<input type="text" name="poll[question]" maxlength="<?php echo esc_attr( (string) \Sabri\HomeNewsFeed\PollPolicy::QUESTION_MAX ); ?>" required disabled />
	</label>
	<div class="sabri-hnf-composer__grid">
		<?php for ( $poll_option_number = 1; $poll_option_number <= \Sabri\HomeNewsFeed\PollPolicy::MAX_OPTIONS; $poll_option_number++ ) : ?>
			<label>
				<span><?php echo esc_html( sprintf( __( 'Option %d', 'sabri-complete-home-news-feed' ), $poll_option_number ) ); ?></span>
				<input
					type="text"
					name="poll[options][]"
					maxlength="<?php echo esc_attr( (string) \Sabri\HomeNewsFeed\PollPolicy::OPTION_MAX ); ?>"
					<?php echo $poll_option_number <= \Sabri\HomeNewsFeed\PollPolicy::MIN_OPTIONS ? ' required' : ''; ?>
					disabled
				/>
			</label>
		<?php endfor; ?>
	</div>
	<div class="sabri-hnf-composer__grid">
		<label>
			<span><?php esc_html_e( 'Show Results', 'sabri-complete-home-news-feed' ); ?></span>
			<select name="poll[results_policy]" disabled>
				<option value="after_vote"><?php esc_html_e( 'After a member votes', 'sabri-complete-home-news-feed' ); ?></option>
				<option value="after_close"><?php esc_html_e( 'After the poll closes', 'sabri-complete-home-news-feed' ); ?></option>
				<option value="always"><?php esc_html_e( 'Always', 'sabri-complete-home-news-feed' ); ?></option>
			</select>
		</label>
		<label>
			<span><?php esc_html_e( 'Closing Time (UTC)', 'sabri-complete-home-news-feed' ); ?></span>
			<input type="datetime-local" name="poll[closes_at]" disabled />
		</label>
	</div>
	<input type="hidden" name="poll[allow_change]" value="0" disabled />
	<label class="sabri-hnf-check">
		<input type="checkbox" name="poll[allow_change]" value="1" checked disabled />
		<span><?php esc_html_e( 'Allow members to change or remove their vote while the poll is open', 'sabri-complete-home-news-feed' ); ?></span>
	</label>
	<p><?php esc_html_e( 'Use two to eight distinct options. Poll definitions are locked after voting begins.', 'sabri-complete-home-news-feed' ); ?></p>
</fieldset>
