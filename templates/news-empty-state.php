<?php
/** Public News empty state. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="sabri-news-empty" role="status">
	<h2><?php echo esc_html__( 'No public News found', 'sabri-complete-home-news-feed' ); ?></h2>
	<p><?php echo esc_html__( 'Try clearing a filter or return later for newly published Editorial News.', 'sabri-complete-home-news-feed' ); ?></p>
</div>
