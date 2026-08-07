<?php
/**
 * Phase 3C comment thread.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section
	class="sabri-hnf-comments"
	id="sabri-hnf-comments-<?php echo esc_attr( $post_id ); ?>"
	data-sabri-comments
	data-post-id="<?php echo esc_attr( $post_id ); ?>"
	data-create-url="<?php echo esc_url( $create_url ); ?>"
	data-comment-base="<?php echo esc_url( $comment_base ); ?>"
	data-nonce="<?php echo esc_attr( $nonce ); ?>"
	data-logged-in="<?php echo $logged_in ? '1' : '0'; ?>"
	data-login-url="<?php echo esc_url( $login_url ); ?>"
	aria-labelledby="sabri-hnf-comments-title-<?php echo esc_attr( $post_id ); ?>"
>
	<header class="sabri-hnf-comments__header">
		<div>
			<h2 id="sabri-hnf-comments-title-<?php echo esc_attr( $post_id ); ?>"><?php esc_html_e( 'Comments', 'sabri-complete-home-news-feed' ); ?></h2>
			<span class="sabri-hnf-comments__count"><?php echo esc_html( (string) $data['approved_count'] ); ?></span>
		</div>
		<form class="sabri-hnf-comments__sort" method="get" action="<?php echo esc_url( $sort_action ); ?>">
			<label for="sabri-hnf-comment-sort-<?php echo esc_attr( $post_id ); ?>"><?php esc_html_e( 'Sort comments', 'sabri-complete-home-news-feed' ); ?></label>
			<select id="sabri-hnf-comment-sort-<?php echo esc_attr( $post_id ); ?>" name="sabri_comment_sort">
				<?php foreach ( $sort_modes as $sort_key => $sort_label ) : ?>
					<option value="<?php echo esc_attr( $sort_key ); ?>" <?php selected( $sort, $sort_key ); ?>><?php echo esc_html( $sort_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit"><?php esc_html_e( 'Apply', 'sabri-complete-home-news-feed' ); ?></button>
		</form>
	</header>

	<?php if ( $logged_in && ! empty( $data['comments_open'] ) ) : ?>
		<form class="sabri-hnf-comment-form" data-sabri-comment-form novalidate>
			<input type="hidden" name="parent_id" value="0" data-comment-parent />
			<input type="hidden" name="comment_id" value="0" data-comment-id />
			<p class="sabri-hnf-comment-form__context" data-comment-form-context><?php esc_html_e( 'Write a comment', 'sabri-complete-home-news-feed' ); ?></p>
			<label for="sabri-hnf-comment-content-<?php echo esc_attr( $post_id ); ?>"><?php esc_html_e( 'Comment', 'sabri-complete-home-news-feed' ); ?></label>
			<textarea
				id="sabri-hnf-comment-content-<?php echo esc_attr( $post_id ); ?>"
				name="content"
				rows="4"
				maxlength="<?php echo esc_attr( (string) $data['max_length'] ); ?>"
				required
				data-comment-content
			></textarea>
			<div class="sabri-hnf-comment-form__actions">
				<button type="submit" class="sabri-hnf-comment-submit"><?php esc_html_e( 'Post Comment', 'sabri-complete-home-news-feed' ); ?></button>
				<button type="button" class="sabri-hnf-comment-cancel" data-comment-cancel hidden><?php esc_html_e( 'Cancel', 'sabri-complete-home-news-feed' ); ?></button>
			</div>
			<p class="sabri-hnf-comment-form__help">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: maximum characters, 2: edit window minutes. */
						__( 'Maximum %1$d characters. You may edit your comment for %2$d minutes. Replies retain a short visible parent context; @mentions remain textual unless a canonical account is explicitly linked elsewhere. Never include patient-identifying information.', 'sabri-complete-home-news-feed' ),
						(int) $data['max_length'],
						(int) $data['edit_minutes']
					)
				);
				?>
			</p>
			<p class="sabri-hnf-comment-form__status" data-comment-status aria-live="polite"></p>
		</form>
	<?php elseif ( ! $logged_in ) : ?>
		<p class="sabri-hnf-comments__notice">
			<?php esc_html_e( 'Sign in to comment or reply.', 'sabri-complete-home-news-feed' ); ?>
			<?php if ( '' !== $login_url ) : ?><a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Sign In', 'sabri-complete-home-news-feed' ); ?></a><?php endif; ?>
		</p>
	<?php else : ?>
		<p class="sabri-hnf-comments__notice"><?php esc_html_e( 'Comments are closed for this post.', 'sabri-complete-home-news-feed' ); ?></p>
	<?php endif; ?>

	<div class="sabri-hnf-comment-list" data-comment-list>
		<?php if ( empty( $tree ) ) : ?>
			<p class="sabri-hnf-comments__empty"><?php esc_html_e( 'No approved comments yet.', 'sabri-complete-home-news-feed' ); ?></p>
		<?php else : ?>
			<?php foreach ( $tree as $item ) : ?><?php echo \Sabri\HomeNewsFeed\CommentRuntime::render_item( $item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endforeach; ?>
		<?php endif; ?>
	</div>
</section>
