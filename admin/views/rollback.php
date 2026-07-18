<?php
/**
 * Rollback view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\Rollback;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$preview = Rollback::preview();
$last    = get_option( 'sabri_feed_last_rollback_report', array() );
?>
<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Rollback Foundation', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Rollback restores plugin-owned settings, schema version option, rewrite refresh state, and plugin capability assignments from the activation snapshot. It does not delete content or companion-plugin data.', 'sabri-complete-home-news-feed' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sabri_feed_rollback">
		<?php wp_nonce_field( 'sabri_feed_rollback' ); ?>
		<button class="button" type="submit" name="mode" value="preview"><?php esc_html_e( 'Preview Rollback', 'sabri-complete-home-news-feed' ); ?></button>
		<p><label><input type="checkbox" data-sabri-confirm-toggle="#sabri-rollback-submit"> <?php esc_html_e( 'I understand rollback is limited to plugin-owned state and keeps site content.', 'sabri-complete-home-news-feed' ); ?></label></p>
		<button id="sabri-rollback-submit" class="button button-primary" type="submit" name="mode" value="execute" disabled><?php esc_html_e( 'Run Rollback', 'sabri-complete-home-news-feed' ); ?></button>
	</form>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Rollback Preview', 'sabri-complete-home-news-feed' ); ?></h2>
	<pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $preview, JSON_PRETTY_PRINT ) ); ?></pre>
</section>

<?php if ( $last ) : ?>
	<section class="sabri-feed-panel">
		<h2><?php esc_html_e( 'Last Rollback Report', 'sabri-complete-home-news-feed' ); ?></h2>
		<pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $last, JSON_PRETTY_PRINT ) ); ?></pre>
	</section>
<?php endif; ?>
