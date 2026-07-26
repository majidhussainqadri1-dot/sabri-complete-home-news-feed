<?php
/** Migration and rollback view. @package SabriCompleteHomeNewsFeed */
use Sabri\HomeNewsFeed\LegacyFounderPostMigration;
use Sabri\HomeNewsFeed\LegacyInteractionMigrationAdapter;
use Sabri\HomeNewsFeed\LegacyPublicationMigration;
use Sabri\HomeNewsFeed\LegacyPublicationRollback;
use Sabri\HomeNewsFeed\Migrations;
if ( ! defined( 'ABSPATH' ) ) { exit; }
$preview = Migrations::preview();
$last = get_option( 'sabri_feed_last_migration_report', array() );
$founder_preview = LegacyFounderPostMigration::preview();
$founder_last = get_option( LegacyFounderPostMigration::LAST_REPORT_OPTION, array() );
$file04_preview = LegacyPublicationMigration::preview();
$file04_last = get_option( LegacyPublicationMigration::LAST_REPORT_OPTION, array() );
$interaction_providers = class_exists( LegacyInteractionMigrationAdapter::class ) ? LegacyInteractionMigrationAdapter::providers() : array();
$rollback_preview = LegacyPublicationRollback::preview();
$rollback_last = get_option( LegacyPublicationRollback::LAST_REPORT_OPTION, array() );
?>
<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Migration Foundation', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Every migration is previewed, bounded, selected manually, snapshot-protected and audit-logged. No legacy source is deleted.', 'sabri-complete-home-news-feed' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sabri_feed_migration">
		<?php wp_nonce_field( 'sabri_feed_migration' ); ?>
		<button class="button" type="submit" name="mode" value="preview"><?php esc_html_e( 'Preview Core Migration', 'sabri-complete-home-news-feed' ); ?></button>
		<button class="button button-primary" type="submit" name="mode" value="execute"><?php esc_html_e( 'Run Core Migration', 'sabri-complete-home-news-feed' ); ?></button>
	</form>
	<pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $preview, JSON_PRETTY_PRINT ) ); ?></pre>
	<?php if ( $last ) : ?><h3><?php esc_html_e( 'Last Core Report', 'sabri-complete-home-news-feed' ); ?></h3><pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $last, JSON_PRETTY_PRINT ) ); ?></pre><?php endif; ?>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'Legacy Founder and Administrator Posts', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Only selected pending posts with a safe blank/pending review state are restored. Protected moderation states remain blocked.', 'sabri-complete-home-news-feed' ); ?></p>
	<?php if ( empty( $founder_preview['candidates'] ) ) : ?><p><?php esc_html_e( 'No safe Founder restoration candidates were found.', 'sabri-complete-home-news-feed' ); ?></p><?php else : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sabri_feed_restore_legacy_founder_posts"><?php wp_nonce_field( 'sabri_feed_restore_legacy_founder_posts' ); ?>
		<table class="widefat striped"><thead><tr><td class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'sabri-complete-home-news-feed' ); ?></span></td><th><?php esc_html_e( 'Post', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Author ID', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Review State', 'sabri-complete-home-news-feed' ); ?></th></tr></thead><tbody>
		<?php foreach ( $founder_preview['candidates'] as $candidate ) : ?><tr><th class="check-column"><input type="checkbox" name="post_ids[]" value="<?php echo esc_attr( (int) $candidate['id'] ); ?>"></th><td><strong><?php echo esc_html( $candidate['title'] ? $candidate['title'] : sprintf( __( 'Post #%d', 'sabri-complete-home-news-feed' ), (int) $candidate['id'] ) ); ?></strong></td><td><?php echo esc_html( (string) (int) $candidate['author_id'] ); ?></td><td><?php echo esc_html( $candidate['review_state'] ? $candidate['review_state'] : __( 'blank legacy state', 'sabri-complete-home-news-feed' ) ); ?></td></tr><?php endforeach; ?>
		</tbody></table><p><button class="button button-primary" type="submit"><?php esc_html_e( 'Restore Selected Posts', 'sabri-complete-home-news-feed' ); ?></button></p>
	</form><?php endif; ?>
	<?php if ( $founder_last ) : ?><pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $founder_last, JSON_PRETTY_PRINT ) ); ?></pre><?php endif; ?>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'File 04 Publications — Preview and Selected Migration', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Legacy snp_publication records remain intact. Selected items are copied with dates, author, featured image, topics and approved comments; a canonical mapping and redirect are recorded.', 'sabri-complete-home-news-feed' ); ?></p>
	<?php if ( empty( $file04_preview['candidates'] ) ) : ?><p><?php esc_html_e( 'No unmigrated File 04 publications were found.', 'sabri-complete-home-news-feed' ); ?></p><?php else : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sabri_feed_migrate_legacy_publications"><?php wp_nonce_field( 'sabri_feed_migrate_legacy_publications' ); ?>
		<p><label for="sabri-file04-target"><strong><?php esc_html_e( 'Target', 'sabri-complete-home-news-feed' ); ?></strong></label> <select id="sabri-file04-target" name="target"><option value="auto"><?php esc_html_e( 'Automatic safe target', 'sabri-complete-home-news-feed' ); ?></option><option value="post"><?php esc_html_e( 'Social Post', 'sabri-complete-home-news-feed' ); ?></option><option value="sabri_news"><?php esc_html_e( 'Editorial News Draft', 'sabri-complete-home-news-feed' ); ?></option></select> <label><input type="checkbox" name="copy_comments" value="1" checked> <?php esc_html_e( 'Copy approved comments', 'sabri-complete-home-news-feed' ); ?></label></p>
		<fieldset class="sabri-feed-migration-interactions">
			<legend><strong><?php esc_html_e( 'Legacy interactions', 'sabri-complete-home-news-feed' ); ?></strong></legend>
			<?php if ( $interaction_providers ) : ?>
				<p><label><input type="checkbox" name="migrate_interactions" value="1"> <?php esc_html_e( 'Migrate interaction records or verified aggregate metrics through the selected schema provider', 'sabri-complete-home-news-feed' ); ?></label></p>
				<p><label for="sabri-file04-interaction-provider"><?php esc_html_e( 'Schema provider', 'sabri-complete-home-news-feed' ); ?></label> <select id="sabri-file04-interaction-provider" name="interaction_provider"><option value=""><?php esc_html_e( 'Select a provider', 'sabri-complete-home-news-feed' ); ?></option><?php foreach ( $interaction_providers as $provider_id => $provider ) : ?><option value="<?php echo esc_attr( $provider_id ); ?>"><?php echo esc_html( $provider['label'] ); ?><?php echo ! empty( $provider['source_schema'] ) ? ' — ' . esc_html( $provider['source_schema'] ) : ''; ?></option><?php endforeach; ?></select></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'No verified File 04 interaction-schema provider is connected. Publications and approved comments can still migrate; likes, saves, shares and views will be reported as unavailable rather than guessed.', 'sabri-complete-home-news-feed' ); ?></p>
			<?php endif; ?>
		</fieldset>
		<table class="widefat striped"><thead><tr><td class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'sabri-complete-home-news-feed' ); ?></span></td><th><?php esc_html_e( 'Legacy publication', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Status', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Published', 'sabri-complete-home-news-feed' ); ?></th></tr></thead><tbody>
		<?php foreach ( $file04_preview['candidates'] as $candidate ) : ?><tr><th class="check-column"><input type="checkbox" name="legacy_ids[]" value="<?php echo esc_attr( (int) $candidate['id'] ); ?>"></th><td><strong><?php echo esc_html( $candidate['title'] ? $candidate['title'] : sprintf( __( 'Publication #%d', 'sabri-complete-home-news-feed' ), (int) $candidate['id'] ) ); ?></strong><br><code><?php echo esc_html( $candidate['slug'] ); ?></code></td><td><?php echo esc_html( $candidate['status'] ); ?></td><td><?php echo esc_html( $candidate['published'] ); ?></td></tr><?php endforeach; ?>
		</tbody></table><p><button class="button button-primary" type="submit"><?php esc_html_e( 'Migrate Selected Publications', 'sabri-complete-home-news-feed' ); ?></button></p>
	</form><?php endif; ?>
	<?php if ( $file04_last ) : ?><h3><?php esc_html_e( 'Last File 04 Migration Report', 'sabri-complete-home-news-feed' ); ?></h3><pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $file04_last, JSON_PRETTY_PRINT ) ); ?></pre><?php endif; ?>
</section>

<section class="sabri-feed-panel">
	<h2><?php esc_html_e( 'File 04 Migration Rollback', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Rollback does not delete either record. It makes the migrated target private, disables the redirect and retains a complete audit trail.', 'sabri-complete-home-news-feed' ); ?></p>
	<?php if ( empty( $rollback_preview['candidates'] ) ) : ?><p><?php esc_html_e( 'No active mappings are available for rollback.', 'sabri-complete-home-news-feed' ); ?></p><?php else : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sabri_feed_rollback_legacy_publications"><?php wp_nonce_field( 'sabri_feed_rollback_legacy_publications' ); ?>
		<table class="widefat striped"><thead><tr><td class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'sabri-complete-home-news-feed' ); ?></span></td><th><?php esc_html_e( 'Legacy ID', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Migrated target', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Target status', 'sabri-complete-home-news-feed' ); ?></th></tr></thead><tbody>
		<?php foreach ( $rollback_preview['candidates'] as $candidate ) : ?><tr><th class="check-column"><input type="checkbox" name="legacy_ids[]" value="<?php echo esc_attr( (int) $candidate['legacy_id'] ); ?>"></th><td>#<?php echo esc_html( (string) (int) $candidate['legacy_id'] ); ?></td><td><strong><?php echo esc_html( $candidate['target_title'] ); ?></strong><br>#<?php echo esc_html( (string) (int) $candidate['target_id'] ); ?></td><td><?php echo esc_html( $candidate['target_status'] ); ?></td></tr><?php endforeach; ?>
		</tbody></table><p><button class="button" type="submit"><?php esc_html_e( 'Roll Back Selected Mappings', 'sabri-complete-home-news-feed' ); ?></button></p>
	</form><?php endif; ?>
	<?php if ( $rollback_last ) : ?><h3><?php esc_html_e( 'Last Rollback Report', 'sabri-complete-home-news-feed' ); ?></h3><pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $rollback_last, JSON_PRETTY_PRINT ) ); ?></pre><?php endif; ?>
</section>
