<?php
/** Approved public correction notice. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$notice = isset( $notice ) ? (string) $notice : '';
?>
<aside class="sabri-news-notice sabri-news-notice--correction" role="note" aria-labelledby="sabri-news-correction-title">
	<h2 id="sabri-news-correction-title"><?php echo esc_html__( 'Correction notice', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php echo esc_html( '' !== $notice ? $notice : __( 'This article has been corrected. The public version shown here is the approved corrected record.', 'sabri-complete-home-news-feed' ) ); ?></p>
</aside>
