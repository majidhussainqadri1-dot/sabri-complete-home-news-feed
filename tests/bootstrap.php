<?php
/**
 * Test bootstrap.
 *
 * @package SabriCompleteHomeNewsFeed
 */

require_once __DIR__ . '/wp-stubs.php';
require_once __DIR__ . '/file00-contract-stubs.php';

// Keep the fixture email index aligned with the fixture user records so
// WordPress-style privacy exporters and erasers can resolve every test user.
if ( isset( $sabri_test_users_by_id ) && is_array( $sabri_test_users_by_id ) ) {
	foreach ( $sabri_test_users_by_id as $sabri_test_user ) {
		if ( ! empty( $sabri_test_user['user_email'] ) && ! empty( $sabri_test_user['ID'] ) ) {
			$sabri_test_users[ $sabri_test_user['user_email'] ] = (int) $sabri_test_user['ID'];
		}
	}
}

require_once dirname( __DIR__ ) . '/sabri-complete-home-news-feed.php';
