<?php
/**
 * Migration view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\LegacyFounderPostMigration;
use Sabri\HomeNewsFeed\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$preview        = Migrations::preview();
$last           = get_option( 'sabri_feed_last_migration_report', array() );
$legacy_preview = LegacyFounderPostMigration::preview();
$legacy_last    = get_option( LegacyFounderPostMigration::LAST_REPORT_OPTION, array() );
?>
<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Migration Foundation', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Migrations are idempotent, previewable, schema-versioned, and snapshot-protected. Activation already captures a snapshot before mutation.', 'sabri-complete-home-news-feed' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sabri_feed_migration">
		<?php wp_nonce_field( 'sabri_feed_migration' ); ?>
		<button class="button" type="submit" name="mode" value="preview"><?php esc_html_e( 'Preview Migration', 'sabri-complete-home-news-feed' ); ?></button>
		<button class="button button-primary" type="submit" name="mode" value="execute"><?php esc_html_e( 'Run Migration', 'sabri-complete-home-news-feed' ); ?></button>
	</form>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Current Preview', 'sabri-complete-home-news-feed' ); ?></h2>
	<pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $preview, JSON_PRETTY_PRINT ) ); ?></pre>
</section>

<?php if ( $last ) : ?>
	<section class="sabri-feed-panel">
		<h2><?php esc_html_e( 'Last Migration Report', 'sabri-complete-home-news-feed' ); ?></h2>
		<pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $last, JSON_PRETTY_PRINT ) ); ?></pre>
	</section>
<?php endif; ?>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Legacy Founder and Administrator Posts', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Only explicitly selected pending posts belonging to a configured Founder or Administrator can be restored. Nothing is published automatically, protected moderation states are excluded, and a pre-mutation snapshot is captured.', 'sabri-complete-home-news-feed' ); ?></p>
	<?php if ( empty( $legacy_preview['candidates'] ) ) : ?>
		<p><?php esc_html_e( 'No safe restoration candidates were found.', 'sabri-complete-home-news-feed' ); ?></p>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="sabri_feed_restore_legacy_founder_posts">
			<?php wp_nonce_field( 'sabri_feed_restore_legacy_founder_posts' ); ?>
			<table class="widefat striped">
				<thead><tr>
					<td class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'sabri-complete-home-news-feed' ); ?></span></td>
					<th><?php esc_html_e( 'Post', 'sabri-complete-home-news-feed' ); ?></th>
					<th><?php esc_html_e( 'Author ID', 'sabri-complete-home-news-feed' ); ?></th>
					<th><?php esc_html_e( 'Review State', 'sabri-complete-home-news-feed' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $legacy_preview['candidates'] as $candidate ) : ?>
					<tr>
						<th class="check-column"><input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( (int) $candidate['id'] ); ?>"></th>
						<td><strong><?php echo esc_html( $candidate['title'] ? $candidate['title'] : sprintf( __( 'Post #%d', 'sabri-complete-home-news-feed' ), (int) $candidate['id'] ) ); ?></strong><br><code>#<?php echo esc_html( (string) (int) $candidate['id'] ); ?></code></td>
						<td><?php echo esc_html( (string) (int) $candidate['author_id'] ); ?></td>
						<td><?php echo esc_html( $candidate['review_state'] ? $candidate['review_state'] : __( 'blank legacy state', 'sabri-complete-home-news-feed' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button class="button button-primary" type="submit"><?php esc_html_e( 'Restore Selected Posts', 'sabri-complete-home-news-feed' ); ?></button></p>
		</form>
	<?php endif; ?>
</section>

<?php if ( $legacy_last ) : ?>
	<section class="sabri-feed-panel">
		<h2><?php esc_html_e( 'Last Legacy Restoration Report', 'sabri-complete-home-news-feed' ); ?></h2>
		<pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $legacy_last, JSON_PRETTY_PRINT ) ); ?></pre>
	</section>
<?php endif; ?>
