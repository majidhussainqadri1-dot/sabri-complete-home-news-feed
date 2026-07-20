<?php
/**
 * Phase 3E moderator-only report queue.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the confidential report queue and bounded admin actions.
 */
final class ReportAdmin {
	const PAGE_SLUG = 'sabri-feed-reports';
	const ACTION = 'sabri_feed_update_report';

	/** Register admin hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
			add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_update' ) );
		}
	}

	/** Register submenu under Home & News Feed. */
	public static function menu() {
		if ( ! function_exists( 'add_submenu_page' ) ) {
			return;
		}
		add_submenu_page(
			'sabri-feed-overview',
			__( 'Reports & Moderation', 'sabri-complete-home-news-feed' ),
			__( 'Reports', 'sabri-complete-home-news-feed' ),
			'sabri_feed_manage_reports',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/** Render confidential queue. */
	public static function render() {
		if ( ! InteractionPermissions::can_manage_reports() ) {
			wp_die( esc_html__( 'You do not have permission to access the report queue.', 'sabri-complete-home-news-feed' ) );
		}

		$filters = self::filters_from_request();
		$result  = ReportService::queue( $filters );
		$data    = ! empty( $result['ok'] ) && isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array(
			'items' => array(), 'total' => 0, 'page' => 1, 'per_page' => 25, 'max_pages' => 0, 'filters' => $filters,
		);
		$reason_labels = ReportPolicy::reason_labels();
		$state_labels  = ReportPolicy::state_labels();
		$view_file     = SABRI_HNF_PATH . 'admin/views/reports.php';

		echo '<div class="wrap sabri-feed-admin sabri-feed-report-admin">';
		echo '<h1>' . esc_html__( 'Reports & Moderation', 'sabri-complete-home-news-feed' ) . '</h1>';
		if ( is_readable( $view_file ) ) {
			include $view_file;
		}
		echo '</div>';
	}

	/** Handle a bounded report transition. */
	public static function handle_update() {
		if ( ! InteractionPermissions::can_manage_reports() ) {
			wp_die( esc_html__( 'You do not have permission to moderate reports.', 'sabri-complete-home-news-feed' ) );
		}
		check_admin_referer( self::ACTION );

		$report_id = isset( $_POST['report_id'] ) ? self::positive_id( wp_unslash( $_POST['report_id'] ) ) : 0;
		$status    = isset( $_POST['report_status'] ) ? sanitize_key( wp_unslash( $_POST['report_status'] ) ) : '';
		$note      = isset( $_POST['moderator_note'] ) ? ReportPolicy::moderator_note( wp_unslash( $_POST['moderator_note'] ) ) : '';
		$result    = ReportService::moderate( $report_id, $status, $note );
		$query     = ! empty( $result['ok'] ) ? 'report_updated=1' : 'report_error=' . rawurlencode( isset( $result['code'] ) ? sanitize_key( $result['code'] ) : 'update_failed' );
		self::redirect( $query );
	}

	/** Read safe queue filters. */
	private static function filters_from_request() {
		$status      = isset( $_GET['report_status'] ) ? sanitize_key( wp_unslash( $_GET['report_status'] ) ) : '';
		$reason      = isset( $_GET['report_reason'] ) ? sanitize_key( wp_unslash( $_GET['report_reason'] ) ) : '';
		$object_type = isset( $_GET['report_object_type'] ) ? sanitize_key( wp_unslash( $_GET['report_object_type'] ) ) : '';
		$page        = isset( $_GET['report_page'] ) ? self::positive_id( wp_unslash( $_GET['report_page'] ) ) : 1;
		return array(
			'status'      => ReportPolicy::state_allowed( $status ) ? $status : '',
			'reason'      => ReportPolicy::reason_allowed( $reason ) ? $reason : '',
			'object_type' => ReportPolicy::object_type_allowed( $object_type ) ? $object_type : '',
			'page'        => max( 1, $page ),
			'per_page'    => 25,
		);
	}

	/** Redirect back to queue. */
	private static function redirect( $query = '' ) {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		if ( '' !== $query ) {
			$url = add_query_arg( wp_parse_args( $query ), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/** Strict positive ID. */
	private static function positive_id( $value ) {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/', $value ) ) ) {
			return 0;
		}
		$value = (int) $value;
		return $value > 0 ? $value : 0;
	}
}
