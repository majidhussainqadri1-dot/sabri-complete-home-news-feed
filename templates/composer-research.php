<?php
/**
 * Research composer fields.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<fieldset class="sabri-hnf-composer__fieldset" data-sabri-type-panel="research">
	<legend><?php esc_html_e( 'Research', 'sabri-complete-home-news-feed' ); ?></legend>
	<label>
		<span><?php esc_html_e( 'Evidence Level', 'sabri-complete-home-news-feed' ); ?></span>
		<select name="research[evidence_level]">
			<?php foreach ( $evidence_terms as $slug => $label ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<?php foreach ( $research_fields as $key => $label ) : ?>
		<label>
			<span><?php echo esc_html( $label ); ?></span>
			<textarea name="research[<?php echo esc_attr( $key ); ?>]" rows="2"></textarea>
		</label>
	<?php endforeach; ?>
</fieldset>
