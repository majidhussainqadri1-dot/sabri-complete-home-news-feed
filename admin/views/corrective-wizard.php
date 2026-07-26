<?php
/** File 21 comprehensive Activation Wizard view. @package SabriCompleteHomeNewsFeed */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$display_value = static function ( $value ) {
	if ( is_bool( $value ) ) { return $value ? 'Yes' : 'No'; }
	if ( is_array( $value ) || is_object( $value ) ) { return wp_json_encode( $value, JSON_UNESCAPED_SLASHES ); }
	return (string) $value;
};
?>
<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Comprehensive activation steps', 'sabri-complete-home-news-feed' ); ?>">
	<?php foreach ( $steps as $slug => $label ) : ?><a class="nav-tab" href="#sabri-corrective-<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?>
</nav>

<section id="sabri-corrective-environment" class="sabri-feed-panel">
	<h2><?php esc_html_e( '1. Environment and Dependencies', 'sabri-complete-home-news-feed' ); ?></h2>
	<table class="widefat striped"><tbody><?php foreach ( $preview['environment'] as $label => $value ) : ?><tr><th><?php echo esc_html( ucwords( str_replace( '_', ' ', $label ) ) ); ?></th><td><?php echo esc_html( $display_value( $value ) ); ?></td></tr><?php endforeach; ?></tbody></table>
</section>

<section id="sabri-corrective-identity-authority" class="sabri-feed-panel">
	<h2><?php esc_html_e( '2. Identity and Publishing Authority', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Founder, Administrator and institutionally trusted verified-doctor authority is resolved across Membership Core, Profiles and Doctor Verification contracts. Editorial Newsroom roles do not automatically receive social Composer authority.', 'sabri-complete-home-news-feed' ); ?></p>
	<table class="widefat striped"><tbody><?php foreach ( $preview['identity_authority'] as $label => $value ) : ?><tr><th><?php echo esc_html( ucwords( str_replace( '_', ' ', $label ) ) ); ?></th><td><?php echo esc_html( $display_value( $value ) ); ?></td></tr><?php endforeach; ?></tbody></table>
</section>

<section id="sabri-corrective-existing-content" class="sabri-feed-panel">
	<h2><?php esc_html_e( '3. Existing and Legacy Content', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'This wizard never bulk-publishes or bulk-migrates content. Founder restoration and File 04 migration are performed only from the Migration screen after individual selection.', 'sabri-complete-home-news-feed' ); ?></p>
	<table class="widefat striped"><tbody><?php foreach ( $preview['existing_content'] as $label => $value ) : ?><tr><th><?php echo esc_html( ucwords( str_replace( '_', ' ', $label ) ) ); ?></th><td><?php echo esc_html( $display_value( $value ) ); ?></td></tr><?php endforeach; ?></tbody></table>
	<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sabri-feed-migration' ) ); ?>"><?php esc_html_e( 'Open Selected Migration and Rollback', 'sabri-complete-home-news-feed' ); ?></a></p>
</section>

<section id="sabri-corrective-public-components" class="sabri-feed-panel">
	<h2><?php esc_html_e( '4. Public Components', 'sabri-complete-home-news-feed' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sabri_hnf_corrective_save_components"><?php wp_nonce_field( 'sabri_hnf_corrective_save_components' ); ?>
		<fieldset><legend class="screen-reader-text"><?php esc_html_e( 'Public components', 'sabri-complete-home-news-feed' ); ?></legend>
		<?php foreach ( $component_labels as $key => $label ) : ?><p><label><input type="checkbox" name="components[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $preview['components'][ $key ] ) ); ?>> <?php echo esc_html( $label ); ?></label></p><?php endforeach; ?>
		</fieldset>
		<?php if ( empty( $preview['can_activate_home_surface'] ) ) : ?><div class="notice notice-error inline"><p><?php echo esc_html( sprintf( __( 'Home auto-mount is blocked because an existing Feed shortcode was detected: %s.', 'sabri-complete-home-news-feed' ), $preview['duplicate_protection']['existing_feed_shortcode'] ) ); ?></p></div><?php endif; ?>
		<?php submit_button( __( 'Save and Apply Selected Components', 'sabri-complete-home-news-feed' ) ); ?>
	</form>
	<h3><?php esc_html_e( 'Home Controls', 'sabri-complete-home-news-feed' ); ?></h3><pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $preview['home_controls'], JSON_PRETTY_PRINT ) ); ?></pre>
	<h3><?php esc_html_e( 'Home Rows', 'sabri-complete-home-news-feed' ); ?></h3><pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $preview['home_rows'], JSON_PRETTY_PRINT ) ); ?></pre>
</section>

<section id="sabri-corrective-duplicate-protection" class="sabri-feed-panel">
	<h2><?php esc_html_e( '5. Duplicate Protection and Companion Contracts', 'sabri-complete-home-news-feed' ); ?></h2>
	<table class="widefat striped"><tbody>
	<tr><th><?php esc_html_e( 'Existing Feed shortcode', 'sabri-complete-home-news-feed' ); ?></th><td><?php echo esc_html( $preview['duplicate_protection']['existing_feed_shortcode'] ? $preview['duplicate_protection']['existing_feed_shortcode'] : __( 'None detected', 'sabri-complete-home-news-feed' ) ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Feed conflict', 'sabri-complete-home-news-feed' ); ?></th><td><?php echo ! empty( $preview['duplicate_protection']['feed_conflict'] ) ? esc_html__( 'Blocked until controlled replacement', 'sabri-complete-home-news-feed' ) : esc_html__( 'Clear', 'sabri-complete-home-news-feed' ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Duplicate navigation destinations', 'sabri-complete-home-news-feed' ); ?></th><td><?php echo esc_html( empty( $preview['duplicate_protection']['navigation_duplicate_keys'] ) ? __( 'None detected', 'sabri-complete-home-news-feed' ) : implode( ', ', $preview['duplicate_protection']['navigation_duplicate_keys'] ) ); ?></td></tr>
	<tr><th><?php esc_html_e( 'File 21 inserts global navigation', 'sabri-complete-home-news-feed' ); ?></th><td><?php esc_html_e( 'No — File 20 remains authoritative', 'sabri-complete-home-news-feed' ); ?></td></tr>
	</tbody></table>
	<h3><?php esc_html_e( 'Companion Integration Registry', 'sabri-complete-home-news-feed' ); ?></h3>
	<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Service', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Status', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Evidence', 'sabri-complete-home-news-feed' ); ?></th></tr></thead><tbody>
	<?php foreach ( $preview['companion_integrations'] as $service ) : ?><tr><td><?php echo esc_html( $service['label'] ); ?></td><td><?php echo esc_html( $service['status'] ); ?></td><td><?php echo esc_html( empty( $service['evidence'] ) ? __( 'None', 'sabri-complete-home-news-feed' ) : implode( ', ', $service['evidence'] ) ); ?></td></tr><?php endforeach; ?>
	</tbody></table>
	<h3><?php esc_html_e( 'Global Search Providers', 'sabri-complete-home-news-feed' ); ?></h3><pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $preview['search_providers'], JSON_PRETTY_PRINT ) ); ?></pre>
</section>

<section id="sabri-corrective-news-gates" class="sabri-feed-panel">
	<h2><?php esc_html_e( '6. Gate-by-Gate Public News Activation', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Editorial News is the parent public gate. Dependent distribution remains disabled when it is disabled.', 'sabri-complete-home-news-feed' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="sabri_hnf_corrective_save_news_gates"><?php wp_nonce_field( 'sabri_hnf_corrective_save_news_gates' ); ?>
		<?php foreach ( array( 'phase4' => __( 'Phase 4 Gates', 'sabri-complete-home-news-feed' ), 'phase5' => __( 'Phase 5 Gates', 'sabri-complete-home-news-feed' ) ) as $phase => $heading ) : ?>
		<h3><?php echo esc_html( $heading ); ?></h3><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Enable', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Feature', 'sabri-complete-home-news-feed' ); ?></th><th><?php esc_html_e( 'Observable URL', 'sabri-complete-home-news-feed' ); ?></th></tr></thead><tbody>
		<?php foreach ( $gate_definitions[ $phase ] as $key => $definition ) : ?><tr><td><input type="checkbox" name="<?php echo esc_attr( $phase ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $preview[ $phase . '_gates' ][ $key ] ) ); ?>></td><td><?php echo esc_html( $definition['label'] ); ?></td><td><code><?php echo esc_html( $definition['url'] ); ?></code></td></tr><?php endforeach; ?></tbody></table>
		<?php endforeach; ?><?php submit_button( __( 'Save News Gates', 'sabri-complete-home-news-feed' ) ); ?>
	</form>
</section>

<section id="sabri-corrective-preview-activate" class="sabri-feed-panel">
	<h2><?php esc_html_e( '7. Preview and Acceptance', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php esc_html_e( 'Coding and CI do not replace WordPress visual acceptance. Test each URL logged out, as Founder, as trusted verified doctor, as unverified doctor and on required responsive widths.', 'sabri-complete-home-news-feed' ); ?></p>
	<ul><?php foreach ( $preview['public_urls'] as $label => $url ) : ?><li><strong><?php echo esc_html( ucwords( $label ) ); ?>:</strong> <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a></li><?php endforeach; ?></ul>
	<div class="notice notice-info inline"><p><?php esc_html_e( 'Automatic bulk publication: No. Automatic File 04 migration: No. Destructive source deletion: No.', 'sabri-complete-home-news-feed' ); ?></p></div>
	<pre class="sabri-feed-code"><?php echo esc_html( wp_json_encode( $preview, JSON_PRETTY_PRINT ) ); ?></pre>
</section>