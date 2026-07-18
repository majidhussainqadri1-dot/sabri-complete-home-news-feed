<?php
/**
 * Migration view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$preview = Migrations::preview();
$last    = get_option( 'sabri_feed_last_migration_report', array() );
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
