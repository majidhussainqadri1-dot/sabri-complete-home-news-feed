<?php
/**
 * Repair view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

use Sabri\HomeNewsFeed\Repair;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$preview = Repair::preview();
$last    = get_option( 'sabri_feed_last_repair_report', array() );
?>
<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Repair Foundation', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Repair actions are explicit, nonce-protected, administrator-only, audited, and non-destructive. Orphan social rows are previewed and not deleted automatically.', 'sabri-complete-home-news-feed' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sabri_feed_repair">
		<?php wp_nonce_field( 'sabri_feed_repair' ); ?>
		<p>
			<label for="sabri-repair-action"><?php esc_html_e( 'Repair action', 'sabri-complete-home-news-feed' ); ?></label>
			<select id="sabri-repair-action" name="repair_action">
				<?php foreach ( Repair::actions() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p><label><input type="checkbox" name="confirm_repair" value="1" data-sabri-confirm-toggle="#sabri-repair-submit"> <?php esc_html_e( 'I understand this repair is non-destructive and audited.', 'sabri-complete-home-news-feed' ); ?></label></p>
		<p><button id="sabri-repair-submit" class="button button-primary" type="submit" disabled><?php esc_html_e( 'Run Repair', 'sabri-complete-home-news-feed' ); ?></button></p>
	</form>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Repair Preview', 'sabri-complete-home-news-feed' ); ?></h2>
	<pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $preview, JSON_PRETTY_PRINT ) ); ?></pre>
</section>

<?php if ( $last ) : ?>
	<section class="sabri-feed-panel">
		<h2><?php esc_html_e( 'Last Repair Report', 'sabri-complete-home-news-feed' ); ?></h2>
		<pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $last, JSON_PRETTY_PRINT ) ); ?></pre>
	</section>
<?php endif; ?>
