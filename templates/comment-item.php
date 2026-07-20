<?php
/**
 * Phase 3C/3E comment item.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$report_control = '';
if ( 'approved' === $item['status'] && empty( $item['deleted'] ) ) {
	$report_control = \Sabri\HomeNewsFeed\ReportRuntime::render_control( 'comment', (int) $item['id'], isset( $item['user_id'] ) ? (int) $item['user_id'] : 0 );
}
?>
<article
	class="sabri-hnf-comment<?php echo ! empty( $item['deleted'] ) ? ' is-deleted' : ''; ?><?php echo 'pending' === $item['status'] ? ' is-pending' : ''; ?>"
	id="sabri-hnf-comment-<?php echo esc_attr( $item['id'] ); ?>"
	data-comment-item
	data-comment-id="<?php echo esc_attr( $item['id'] ); ?>"
	data-comment-content="<?php echo esc_attr( $item['content'] ); ?>"
	style="--sabri-comment-depth: <?php echo esc_attr( (string) min( 6, max( 0, (int) $item['depth'] ) ) ); ?>;"
>
	<header class="sabri-hnf-comment__header">
		<div class="sabri-hnf-comment__avatar"><?php echo $item['avatar']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div class="sabri-hnf-comment__byline">
			<strong><?php echo esc_html( $item['author_name'] ); ?></strong>
			<div class="sabri-hnf-comment__meta">
				<time datetime="<?php echo esc_attr( $item['date_gmt'] ); ?>"><?php echo esc_html( $item['date_gmt'] ); ?> UTC</time>
				<?php if ( 'pending' === $item['status'] ) : ?><span><?php esc_html_e( 'Pending review', 'sabri-complete-home-news-feed' ); ?></span><?php endif; ?>
				<?php if ( ! empty( $item['edited'] ) ) : ?><span><?php esc_html_e( 'Edited', 'sabri-complete-home-news-feed' ); ?></span><?php endif; ?>
			</div>
		</div>
	</header>

	<div class="sabri-hnf-comment__content">
		<?php echo wpautop( esc_html( $item['content'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>

	<?php if ( ! empty( $item['can_reply'] ) || ! empty( $item['can_edit'] ) || ! empty( $item['can_delete'] ) || '' !== $report_control ) : ?>
		<div class="sabri-hnf-comment__actions">
			<?php if ( ! empty( $item['can_reply'] ) ) : ?><button type="button" data-comment-reply data-comment-id="<?php echo esc_attr( $item['id'] ); ?>" data-author-name="<?php echo esc_attr( $item['author_name'] ); ?>"><?php esc_html_e( 'Reply', 'sabri-complete-home-news-feed' ); ?></button><?php endif; ?>
			<?php if ( ! empty( $item['can_edit'] ) ) : ?><button type="button" data-comment-edit data-comment-id="<?php echo esc_attr( $item['id'] ); ?>"><?php esc_html_e( 'Edit', 'sabri-complete-home-news-feed' ); ?></button><?php endif; ?>
			<?php if ( ! empty( $item['can_delete'] ) ) : ?><button type="button" data-comment-delete data-comment-id="<?php echo esc_attr( $item['id'] ); ?>"><?php esc_html_e( 'Delete', 'sabri-complete-home-news-feed' ); ?></button><?php endif; ?>
			<?php if ( '' !== $report_control ) : ?><?php echo $report_control; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $item['children'] ) ) : ?>
		<div class="sabri-hnf-comment__children">
			<?php foreach ( $item['children'] as $child ) : ?>
				<?php echo \Sabri\HomeNewsFeed\CommentRuntime::render_item( $child ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</article>
