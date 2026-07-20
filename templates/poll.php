<?php
/**
 * Phase 3F accessible poll.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section
	class="sabri-hnf-poll<?php echo ! empty( $data['closed'] ) ? ' is-closed' : ''; ?>"
	id="sabri-hnf-poll-<?php echo esc_attr( $post_id ); ?>"
	data-sabri-poll
	data-post-id="<?php echo esc_attr( $post_id ); ?>"
	data-vote-url="<?php echo esc_url( $vote_url ); ?>"
	data-results-url="<?php echo esc_url( $results_url ); ?>"
	data-nonce="<?php echo esc_attr( $nonce ); ?>"
	data-logged-in="<?php echo $logged_in ? '1' : '0'; ?>"
	data-login-url="<?php echo esc_url( $login_url ); ?>"
	aria-labelledby="sabri-hnf-poll-question-<?php echo esc_attr( $post_id ); ?>"
>
	<header class="sabri-hnf-poll__header">
		<h3 id="sabri-hnf-poll-question-<?php echo esc_attr( $post_id ); ?>"><?php echo esc_html( $data['question'] ); ?></h3>
		<p class="sabri-hnf-poll__meta">
			<?php if ( ! empty( $data['closed'] ) ) : ?>
				<?php esc_html_e( 'Poll closed', 'sabri-complete-home-news-feed' ); ?>
			<?php elseif ( ! empty( $data['closes_at'] ) ) : ?>
				<?php echo esc_html( sprintf( __( 'Closes %s UTC', 'sabri-complete-home-news-feed' ), $data['closes_at'] ) ); ?>
			<?php else : ?>
				<?php esc_html_e( 'Open poll', 'sabri-complete-home-news-feed' ); ?>
			<?php endif; ?>
		</p>
	</header>

	<form class="sabri-hnf-poll__form" data-sabri-poll-form>
		<fieldset<?php echo ! empty( $data['closed'] ) ? ' disabled' : ''; ?>>
			<legend class="screen-reader-text"><?php esc_html_e( 'Choose one poll option', 'sabri-complete-home-news-feed' ); ?></legend>
			<div class="sabri-hnf-poll__options">
				<?php foreach ( $data['options'] as $option ) : ?>
					<label class="sabri-hnf-poll__option<?php echo ! empty( $option['selected'] ) ? ' is-selected' : ''; ?>">
						<span class="sabri-hnf-poll__choice">
							<input
								type="radio"
								name="option_key"
								value="<?php echo esc_attr( $option['key'] ); ?>"
								<?php echo ! empty( $option['selected'] ) ? ' checked' : ''; ?>
								<?php echo empty( $data['can_vote'] ) && empty( $option['selected'] ) ? ' disabled' : ''; ?>
							/>
							<span><?php echo esc_html( $option['label'] ); ?></span>
						</span>
						<?php if ( ! empty( $option['count_visible'] ) ) : ?>
							<span class="sabri-hnf-poll__result">
								<span class="sabri-hnf-poll__bar" aria-hidden="true"><span style="width: <?php echo esc_attr( (string) max( 0, min( 100, (float) $option['percent'] ) ) ); ?>%;"></span></span>
								<span><?php echo esc_html( (string) $option['count'] ); ?> · <?php echo esc_html( (string) $option['percent'] ); ?>%</span>
							</span>
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>

		<div class="sabri-hnf-poll__actions">
			<?php if ( empty( $data['closed'] ) && ( ! empty( $data['can_vote'] ) || ! $logged_in ) ) : ?>
				<button type="submit" class="sabri-hnf-poll__vote"><?php echo ! empty( $data['has_voted'] ) ? esc_html__( 'Update Vote', 'sabri-complete-home-news-feed' ) : esc_html__( 'Vote', 'sabri-complete-home-news-feed' ); ?></button>
			<?php endif; ?>
			<?php if ( ! empty( $data['can_remove'] ) ) : ?>
				<button type="button" class="sabri-hnf-poll__remove" data-poll-remove><?php esc_html_e( 'Remove Vote', 'sabri-complete-home-news-feed' ); ?></button>
			<?php endif; ?>
		</div>
	</form>

	<?php if ( ! $logged_in && empty( $data['closed'] ) ) : ?>
		<p class="sabri-hnf-poll__notice"><?php esc_html_e( 'Sign in to vote.', 'sabri-complete-home-news-feed' ); ?></p>
	<?php elseif ( ! empty( $data['has_voted'] ) && empty( $data['allow_change'] ) ) : ?>
		<p class="sabri-hnf-poll__notice"><?php esc_html_e( 'Your vote is final for this poll.', 'sabri-complete-home-news-feed' ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $data['results_visible'] ) ) : ?>
		<p class="sabri-hnf-poll__total"><?php echo esc_html( sprintf( __( '%d total votes', 'sabri-complete-home-news-feed' ), (int) $data['total_votes'] ) ); ?></p>
	<?php elseif ( 'after_close' === $data['results_policy'] ) : ?>
		<p class="sabri-hnf-poll__notice"><?php esc_html_e( 'Results will be shown after the poll closes.', 'sabri-complete-home-news-feed' ); ?></p>
	<?php else : ?>
		<p class="sabri-hnf-poll__notice"><?php esc_html_e( 'Vote to view aggregate results.', 'sabri-complete-home-news-feed' ); ?></p>
	<?php endif; ?>

	<p class="sabri-hnf-poll__status" data-poll-status aria-live="polite"></p>
</section>
