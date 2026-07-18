<?php
/**
 * Feed error template.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sabri-hnf-error" role="status">
	<p><?php echo esc_html( $message ); ?></p>
</div>
