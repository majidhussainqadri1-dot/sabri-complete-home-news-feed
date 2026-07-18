<?php
/**
 * Clinical Case composer fields.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<fieldset class="sabri-hnf-composer__fieldset" data-sabri-type-panel="clinical-case">
	<legend><?php esc_html_e( 'Clinical Case', 'sabri-complete-home-news-feed' ); ?></legend>
	<?php foreach ( $clinical_fields as $key => $label ) : ?>
		<label>
			<span><?php echo esc_html( $label ); ?></span>
			<textarea name="clinical_case[<?php echo esc_attr( $key ); ?>]" rows="2"></textarea>
		</label>
	<?php endforeach; ?>
</fieldset>
