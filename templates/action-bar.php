<?php
/**
 * Phase 3 action bar.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section
	class="sabri-hnf-actions"
	data-sabri-interactions
	data-post-id="<?php echo esc_attr( $post_id ); ?>"
	data-engagement-url="<?php echo esc_url( $engagement_url ); ?>"
	data-reaction-url="<?php echo esc_url( $reaction_url ); ?>"
	data-save-url="<?php echo esc_url( $save_url ); ?>"
	data-follow-url="<?php echo esc_url( $follow_url ); ?>"
	data-share-url="<?php echo esc_url( $share_url ); ?>"
	data-share-title="<?php echo esc_attr( $share_title ); ?>"
	data-nonce="<?php echo esc_attr( $nonce ); ?>"
	data-login-url="<?php echo esc_url( $login_url ); ?>"
	data-logged-in="<?php echo $logged_in ? '1' : '0'; ?>"
	aria-label="<?php esc_attr_e( 'Post actions', 'sabri-complete-home-news-feed' ); ?>"
>
	<div class="sabri-hnf-actions__buttons">
		<?php if ( $reactions_enabled ) : ?>
			<button type="button" class="sabri-hnf-action sabri-hnf-action--like<?php echo 'like' === $summary['current_reaction'] ? ' is-active' : ''; ?>" data-sabri-action="reaction" data-reaction-type="like" aria-pressed="<?php echo 'like' === $summary['current_reaction'] ? 'true' : 'false'; ?>">
				<span><?php esc_html_e( 'Like', 'sabri-complete-home-news-feed' ); ?></span>
				<?php if ( $show_public_counts ) : ?><span class="sabri-hnf-action__count" data-count="like"><?php echo esc_html( (string) $summary['like_count'] ); ?></span><?php endif; ?>
			</button>
			<?php if ( $dislikes_enabled ) : ?>
				<button type="button" class="sabri-hnf-action sabri-hnf-action--dislike<?php echo 'dislike' === $summary['current_reaction'] ? ' is-active' : ''; ?>" data-sabri-action="reaction" data-reaction-type="dislike" aria-pressed="<?php echo 'dislike' === $summary['current_reaction'] ? 'true' : 'false'; ?>">
					<span><?php esc_html_e( 'Dislike', 'sabri-complete-home-news-feed' ); ?></span>
					<?php if ( $show_public_counts ) : ?><span class="sabri-hnf-action__count" data-count="dislike"><?php echo esc_html( (string) $summary['dislike_count'] ); ?></span><?php endif; ?>
				</button>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $comments_enabled ) : ?>
			<a class="sabri-hnf-action sabri-hnf-action--comment" href="<?php echo esc_url( $comments_url ); ?>">
				<span><?php esc_html_e( 'Comment', 'sabri-complete-home-news-feed' ); ?></span>
				<span class="sabri-hnf-action__count" data-count="comment"><?php echo esc_html( (string) $comment_count ); ?></span>
			</a>
		<?php endif; ?>

		<?php if ( $saves_enabled ) : ?>
			<button type="button" class="sabri-hnf-action sabri-hnf-action--save<?php echo ! empty( $summary['saved'] ) ? ' is-active' : ''; ?>" data-sabri-action="save" aria-pressed="<?php echo ! empty( $summary['saved'] ) ? 'true' : 'false'; ?>">
				<span data-save-label><?php echo ! empty( $summary['saved'] ) ? esc_html__( 'Saved', 'sabri-complete-home-news-feed' ) : esc_html__( 'Save', 'sabri-complete-home-news-feed' ); ?></span>
			</button>
		<?php endif; ?>

		<?php if ( $share_enabled && '' !== $share_url ) : ?>
			<button type="button" class="sabri-hnf-action sabri-hnf-action--share" data-sabri-action="share">
				<span><?php esc_html_e( 'Share', 'sabri-complete-home-news-feed' ); ?></span>
			</button>
		<?php endif; ?>

		<?php if ( $views_enabled ) : ?>
			<span class="sabri-hnf-action sabri-hnf-action--views" aria-label="<?php echo esc_attr( sprintf( __( '%d views', 'sabri-complete-home-news-feed' ), (int) $summary['view_count'] ) ); ?>">
				<span><?php esc_html_e( 'Views', 'sabri-complete-home-news-feed' ); ?></span>
				<span class="sabri-hnf-action__count" data-count="views"><?php echo esc_html( (string) $summary['view_count'] ); ?></span>
			</span>
		<?php endif; ?>

		<?php if ( $follows_enabled && '' !== $profile_url ) : ?>
			<a class="sabri-hnf-action sabri-hnf-action--profile" href="<?php echo esc_url( $profile_url ); ?>"><span><?php esc_html_e( 'View Profile', 'sabri-complete-home-news-feed' ); ?></span></a>
		<?php endif; ?>

		<?php if ( $can_follow ) : ?>
			<button type="button" class="sabri-hnf-action sabri-hnf-action--follow<?php echo ! empty( $follow_summary['following'] ) ? ' is-active' : ''; ?>" data-sabri-action="follow" aria-pressed="<?php echo ! empty( $follow_summary['following'] ) ? 'true' : 'false'; ?>">
				<span data-follow-label><?php echo ! empty( $follow_summary['following'] ) ? esc_html__( 'Following', 'sabri-complete-home-news-feed' ) : esc_html__( 'Follow', 'sabri-complete-home-news-feed' ); ?></span>
				<?php if ( ! empty( $follow_summary['count_visible'] ) ) : ?><span class="sabri-hnf-action__count" data-count="followers"><?php echo esc_html( (string) $follow_summary['follower_count'] ); ?></span><?php endif; ?>
			</button>
		<?php endif; ?>

		<?php if ( $reports_enabled && '' !== $report_control ) : ?>
			<?php echo $report_control; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</div>
	<p class="sabri-hnf-actions__status" data-sabri-action-status aria-live="polite"></p>
</section>
