<?php
/**
 * File 21 corrective activation wizard view.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Corrective activation steps', 'sabri-complete-home-news-feed' ); ?>">
	<?php foreach ( $steps as $slug => $label ) : ?>
		<a class="nav-tab" href="#sabri-corrective-<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></a>
	<?php endforeach; ?>
</nav>

<section id="sabri-corrective-environment" class="sabri-feed-panel">
	<h2><?php esc_html_e( '1. Environment and Dependencies', 'sabri-complete-home-news-feed' ); ?></h2>
	<table class="widefat striped"><tbody>
		<?php foreach ( $preview['environment'] as $label => $value ) : ?>
			<tr><th><?php echo esc_html( ucwords( str_replace( '_', ' ', $label ) ) ); ?></th><td><?php echo esc_html( is_bool( $value ) ? ( $value ? 'Connected' : 'Missing / optional' ) : (string) $value ); ?></td></tr>
		<?php endforeach; ?>
	</tbody></table>
</section>

<section id="sabri-corrective-existing-content" class="sabri-feed-panel">
	<h2><?php esc_html_e( '2. Existing Content', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'This wizard never bulk-publishes content. Legacy Founder posts are restored only from the separate Migration screen after individual selection.', 'sabri-complete-home-news-feed' ); ?></p>
	<table class="widefat striped"><tbody>
		<?php foreach ( $preview['existing_content'] as $label => $value ) : ?>
			<tr><th><?php echo esc_html( ucwords( str_replace( '_', ' ', $label ) ) ); ?></th><td><?php echo esc_html( (string) $value ); ?></td></tr>
		<?php endforeach; ?>
	</tbody></table>
</section>

<section id="sabri-corrective-public-components" class="sabri-feed-panel">
	<h2><?php esc_html_e( '3. Public Components', 'sabri-complete-home-news-feed' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sabri_hnf_corrective_save_components">
		<?php wp_nonce_field( 'sabri_hnf_corrective_save_components' ); ?>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Corrective public components', 'sabri-complete-home-news-feed' ); ?></legend>
			<?php foreach ( $component_labels as $key => $label ) : ?>
				<p><label><input type="checkbox" name="components[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $preview['components'][ $key ] ) ); ?>> <?php echo esc_html( $label ); ?></label></p>
			<?php endforeach; ?>
		</fieldset>
		<?php if ( empty( $preview['can_activate_home_surface'] ) ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( sprintf( __( 'Home auto-mount is blocked because an existing Feed shortcode was detected: %s.', 'sabri-complete-home-news-feed' ), $preview['duplicate_protection']['existing_feed_shortcode'] ) ); ?></p></div>
		<?php endif; ?>
		<?php submit_button( __( 'Save and Apply Selected Components', 'sabri-complete-home-news-feed' ) ); ?>
	</form>
</section>

<section id="sabri-corrective-duplicate-protection" class="sabri-feed-panel">
	<h2><?php esc_html_e( '4. Duplicate Protection', 'sabri-complete-home-news-feed' ); ?></h2>
	<table class="widefat striped"><tbody>
		<tr><th><?php esc_html_e( 'Existing Feed shortcode', 'sabri-complete-home-news-feed' ); ?></th><td><?php echo esc_html( $preview['duplicate_protection']['existing_feed_shortcode'] ? $preview['duplicate_protection']['existing_feed_shortcode'] : __( 'None detected', 'sabri-complete-home-news-feed' ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Feed conflict', 'sabri-complete-home-news-feed' ); ?></th><td><?php echo ! empty( $preview['duplicate_protection']['feed_conflict'] ) ? esc_html__( 'Blocked', 'sabri-complete-home-news-feed' ) : esc_html__( 'Clear', 'sabri-complete-home-news-feed' ); ?></td></tr>
		<tr><th><?php esc_html_e( 'Duplicate navigation destinations', 'sabri-complete-home-news-feed' ); ?></th><td><?php echo esc_html( empty( $preview['duplicate_protection']['navigation_duplicate_keys'] ) ? __( 'None detected', 'sabri-complete-home-news-feed' ) : implode( ', ', $preview['duplicate_protection']['navigation_duplicate_keys'] ) ); ?></td></tr>
		<tr><th><?php esc_html_e( 'File 21 inserts navigation', 'sabri-complete-home-news-feed' ); ?></th><td><?php esc_html_e( 'No', 'sabri-complete-home-news-feed' ); ?></td></tr>
	</tbody></table>
</section>

<section id="sabri-corrective-news-gates" class="sabri-feed-panel">
	<h2><?php esc_html_e( '5. Gate-by-Gate Public News Activation', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Editorial News is the parent gate. Breaking News, corrections, RSS, schema, sitemap, and public distribution remain disabled when Editorial News is disabled.', 'sabri-complete-home-news-feed' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sabri_hnf_corrective_save_news_gates">
		<?php wp_nonce_field( 'sabri_hnf_corrective_save_news_gates' ); ?>
		<h3><?php esc_html_e( 'Phase 4 Gates', 'sabri-complete-home-news-feed' ); ?></h3>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Enable', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Feature', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Observable URL', 'sabri-complete-home-news-feed' ); ?></th></tr></thead><tbody>
			<?php foreach ( $gate_definitions['phase4'] as $key => $definition ) : ?>
				<tr><td><input type="checkbox" name="phase4[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $preview['phase4_gates'][ $key ] ) ); ?>></td><td><?php echo esc_html( $definition['label'] ); ?></td><td><code><?php echo esc_html( $definition['url'] ); ?></code></td></tr>
			<?php endforeach; ?>
		</tbody></table>
		<h3><?php esc_html_e( 'Phase 5 Gates', 'sabri-complete-home-news-feed' ); ?></h3>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Enable', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Feature', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Observable URL', 'sabri-complete-home-news-feed' ); ?></th></tr></thead><tbody>
			<?php foreach ( $gate_definitions['phase5'] as $key => $definition ) : ?>
				<tr><td><input type="checkbox" name="phase5[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $preview['phase5_gates'][ $key ] ) ); ?>></td><td><?php echo esc_html( $definition['label'] ); ?></td><td><code><?php echo esc_html( $definition['url'] ); ?></code></td></tr>
			<?php endforeach; ?>
		</tbody></table>
		<?php submit_button( __( 'Save News Gates', 'sabri-complete-home-news-feed' ) ); ?>
	</form>
</section>

<section id="sabri-corrective-preview-activate" class="sabri-feed-panel">
	<h2><?php esc_html_e( '6. Preview and Acceptance', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Use the URLs below in a logged-out window and as Founder. A release is not accepted until the screenshot checklist records commit, package checksum, environment, user role, URL, and gate state.', 'sabri-complete-home-news-feed' ); ?></p>
	<ul>
		<?php foreach ( $preview['public_urls'] as $label => $url ) : ?>
			<li><strong><?php echo esc_html( ucwords( $label ) ); ?>:</strong> <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a></li>
		<?php endforeach; ?>
	</ul>
	<pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $preview, JSON_PRETTY_PRINT ) ); ?></pre>
</section>
