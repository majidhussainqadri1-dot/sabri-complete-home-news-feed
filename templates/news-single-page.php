<?php
/** Full public News single page wrapper. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( function_exists( 'get_header' ) ) {
	get_header();
}
echo \Sabri\HomeNewsFeed\NewsPublicRuntime::render_single(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
if ( function_exists( 'get_footer' ) ) {
	get_footer();
}
