<?php
/**
 * Phase 3E confidential report queue view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$items = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
?>
<?php if ( ! empty( $_GET['report_updated'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Report updated.', 'sabri-complete-home-news-feed' ); ?></p></div>
<?php endif; ?>
<?php if ( ! empty( $_GET['report_error'] ) ) : ?>
	<div class="notice notice-error"><p><?php esc_html_e( 'The report could not be updated. Review the requested transition and try again.', 'sabri-complete-home-news-feed' ); ?></p></div>
<?php endif; ?>

<p><?php esc_html_e( 'This queue is confidential. Reporter identities, report details, and moderator notes must not be copied into public posts or comments.', 'sabri-complete-home-news-feed' ); ?></p>
<?php if ( ! \Sabri\HomeNewsFeed\Phase3FeatureSettings::enabled( 'reports_enabled' ) ) : ?>
	<div class="notice notice-warning inline"><p><?php esc_html_e( 'New public report submissions are disabled. Existing reports remain available for moderation and accountability.', 'sabri-complete-home-news-feed' ); ?></p></div>
<?php endif; ?>

<form method="get" class="sabri-feed-report-filters">
	<input type="hidden" name="page" value="<?php echo esc_attr( \Sabri\HomeNewsFeed\ReportAdmin::PAGE_SLUG ); ?>" />
	<label>
		<span><?php esc_html_e( 'Status', 'sabri-complete-home-news-feed' ); ?></span>
		<select name="report_status">
			<option value=""><?php esc_html_e( 'All statuses', 'sabri-complete-home-news-feed' ); ?></option>
			<?php foreach ( $state_labels as $state_key => $state_label ) : ?>
				<option value="<?php echo esc_attr( $state_key ); ?>" <?php selected( isset( $filters['status'] ) ? $filters['status'] : '', $state_key ); ?>><?php echo esc_html( $state_label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<label>
		<span><?php esc_html_e( 'Reason', 'sabri-complete-home-news-feed' ); ?></span>
		<select name="report_reason">
			<option value=""><?php esc_html_e( 'All reasons', 'sabri-complete-home-news-feed' ); ?></option>
			<?php foreach ( $reason_labels as $reason_key => $reason_label ) : ?>
				<option value="<?php echo esc_attr( $reason_key ); ?>" <?php selected( isset( $filters['reason'] ) ? $filters['reason'] : '', $reason_key ); ?>><?php echo esc_html( $reason_label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<label>
		<span><?php esc_html_e( 'Content type', 'sabri-complete-home-news-feed' ); ?></span>
		<select name="report_object_type">
			<option value=""><?php esc_html_e( 'Posts and comments', 'sabri-complete-home-news-feed' ); ?></option>
			<option value="post" <?php selected( isset( $filters['object_type'] ) ? $filters['object_type'] : '', 'post' ); ?>><?php esc_html_e( 'Posts', 'sabri-complete-home-news-feed' ); ?></option>
			<option value="comment" <?php selected( isset( $filters['object_type'] ) ? $filters['object_type'] : '', 'comment' ); ?>><?php esc_html_e( 'Comments', 'sabri-complete-home-news-feed' ); ?></option>
		</select>
	</label>
	<button type="submit" class="button"><?php esc_html_e( 'Filter', 'sabri-complete-home-news-feed' ); ?></button>
	<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . \Sabri\HomeNewsFeed\ReportAdmin::PAGE_SLUG ) ); ?>"><?php esc_html_e( 'Reset', 'sabri-complete-home-news-feed' ); ?></a>
</form>

<p><strong><?php echo esc_html( sprintf( __( '%d confidential reports', 'sabri-complete-home-news-feed' ), isset( $data['total'] ) ? (int) $data['total'] : 0 ) ); ?></strong></p>

<?php if ( empty( $items ) ) : ?>
	<div class="notice notice-info inline"><p><?php esc_html_e( 'No reports match the selected filters.', 'sabri-complete-home-news-feed' ); ?></p></div>
<?php else : ?>
	<div class="sabri-feed-report-list">
		<?php foreach ( $items as $report ) : ?>
			<article class="sabri-feed-report-card">
				<header>
					<h2><?php echo esc_html( sprintf( __( 'Report #%d', 'sabri-complete-home-news-feed' ), (int) $report['id'] ) ); ?></h2>
					<span class="sabri-feed-report-status"><?php echo esc_html( isset( $state_labels[ $report['status'] ] ) ? $state_labels[ $report['status'] ] : $report['status'] ); ?></span>
				</header>
				<dl>
					<dt><?php esc_html_e( 'Reported content', 'sabri-complete-home-news-feed' ); ?></dt>
					<dd>
						<?php if ( ! empty( $report['object_url'] ) ) : ?><a href="<?php echo esc_url( $report['object_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $report['object_label'] ); ?></a><?php else : ?><?php echo esc_html( $report['object_label'] ); ?><?php endif; ?>
						<?php if ( ! empty( $report['object_excerpt'] ) ) : ?><blockquote><?php echo esc_html( $report['object_excerpt'] ); ?></blockquote><?php endif; ?>
					</dd>
					<dt><?php esc_html_e( 'Reason', 'sabri-complete-home-news-feed' ); ?></dt>
					<dd><?php echo esc_html( isset( $reason_labels[ $report['reason'] ] ) ? $reason_labels[ $report['reason'] ] : $report['reason'] ); ?></dd>
					<dt><?php esc_html_e( 'Reporter', 'sabri-complete-home-news-feed' ); ?></dt>
					<dd><?php echo esc_html( $report['reporter_name'] . ' — ID ' . (int) $report['reporter_user_id'] ); ?></dd>
					<dt><?php esc_html_e( 'Reporter note', 'sabri-complete-home-news-feed' ); ?></dt>
					<dd><?php echo '' !== $report['reporter_note'] ? nl2br( esc_html( $report['reporter_note'] ) ) : esc_html__( 'No additional details.', 'sabri-complete-home-news-feed' ); ?></dd>
					<dt><?php esc_html_e( 'Created', 'sabri-complete-home-news-feed' ); ?></dt>
					<dd><?php echo esc_html( $report['created_at'] . ' UTC' ); ?></dd>
				</dl>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sabri-feed-report-update">
					<input type="hidden" name="action" value="<?php echo esc_attr( \Sabri\HomeNewsFeed\ReportAdmin::ACTION ); ?>" />
					<input type="hidden" name="report_id" value="<?php echo esc_attr( (string) $report['id'] ); ?>" />
					<?php wp_nonce_field( \Sabri\HomeNewsFeed\ReportAdmin::ACTION ); ?>
					<label>
						<span><?php esc_html_e( 'Status', 'sabri-complete-home-news-feed' ); ?></span>
						<select name="report_status" required>
							<?php foreach ( $state_labels as $state_key => $state_label ) : ?>
								<option value="<?php echo esc_attr( $state_key ); ?>" <?php selected( $report['status'], $state_key ); ?>><?php echo esc_html( $state_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Private moderator note', 'sabri-complete-home-news-feed' ); ?></span>
						<textarea name="moderator_note" rows="4" maxlength="<?php echo esc_attr( (string) \Sabri\HomeNewsFeed\ReportPolicy::MODERATOR_NOTE_MAX ); ?>"><?php echo esc_textarea( $report['moderator_note'] ); ?></textarea>
					</label>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Update Report', 'sabri-complete-home-news-feed' ); ?></button>
				</form>
			</article>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if ( ! empty( $data['max_pages'] ) && (int) $data['max_pages'] > 1 ) : ?>
	<nav class="tablenav-pages" aria-label="<?php esc_attr_e( 'Report queue pages', 'sabri-complete-home-news-feed' ); ?>">
		<?php for ( $page_number = 1; $page_number <= (int) $data['max_pages']; $page_number++ ) : ?>
			<?php
			$page_url = add_query_arg(
				array(
					'page'               => \Sabri\HomeNewsFeed\ReportAdmin::PAGE_SLUG,
					'report_status'      => isset( $filters['status'] ) ? $filters['status'] : '',
					'report_reason'      => isset( $filters['reason'] ) ? $filters['reason'] : '',
					'report_object_type' => isset( $filters['object_type'] ) ? $filters['object_type'] : '',
					'report_page'        => $page_number,
				),
				admin_url( 'admin.php' )
			);
			?>
			<a class="button<?php echo (int) $data['page'] === $page_number ? ' button-primary' : ''; ?>" href="<?php echo esc_url( $page_url ); ?>"<?php echo (int) $data['page'] === $page_number ? ' aria-current="page"' : ''; ?>><?php echo esc_html( (string) $page_number ); ?></a>
		<?php endfor; ?>
	</nav>
<?php endif; ?>
