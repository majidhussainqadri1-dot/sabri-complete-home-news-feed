<?php
/**
 * Administrator notice after duplicate File 21 copies are quarantined.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keeps duplicate-folder recovery visible until an administrator acknowledges it. */
final class DuplicateCopyNotice {
	const OPTION_NAME = 'sabri_hnf_duplicate_plugin_resolution';
	const DISMISS_ACTION = 'sabri_hnf_dismiss_duplicate_copy_notice';

	/** Register the notice only in administration. */
	public static function register() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}
		add_action( 'admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'network_admin_notices', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( __CLASS__, 'dismiss' ) );
	}

	/** Render a bounded operator-facing recovery report. */
	public static function render() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) || ! function_exists( 'get_option' ) ) {
			return;
		}
		$report = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $report ) || empty( $report['resolved'] ) || ! empty( $report['acknowledged'] ) ) {
			return;
		}
		$previous = isset( $report['previous_copies'] ) && is_array( $report['previous_copies'] ) ? $report['previous_copies'] : array();
		$previous = array_slice( array_values( array_filter( array_map( array( __CLASS__, 'clean_plugin_basename' ), $previous ) ) ), 0, 10 );
		$current = isset( $report['current_copy'] ) ? self::clean_plugin_basename( $report['current_copy'] ) : '';
		$time = isset( $report['resolved_at_utc'] ) ? sanitize_text_field( $report['resolved_at_utc'] ) : '';

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Duplicate File 21 copies were detected and deactivated.', 'sabri-complete-home-news-feed' ) . '</strong></p>';
		echo '<p>' . esc_html__( 'WordPress stopped the current request before loading File 21 twice. The corrected copy can load on the next request, but obsolete plugin folders must still be removed from staging before production acceptance.', 'sabri-complete-home-news-feed' ) . '</p>';
		if ( ! empty( $previous ) ) {
			echo '<p><strong>' . esc_html__( 'Deactivated copies:', 'sabri-complete-home-news-feed' ) . '</strong> ' . esc_html( implode( ', ', $previous ) ) . '</p>';
		}
		if ( '' !== $current ) {
			echo '<p><strong>' . esc_html__( 'Current copy:', 'sabri-complete-home-news-feed' ) . '</strong> ' . esc_html( $current ) . '</p>';
		}
		if ( '' !== $time ) {
			echo '<p><strong>' . esc_html__( 'Recorded at UTC:', 'sabri-complete-home-news-feed' ) . '</strong> ' . esc_html( $time ) . '</p>';
		}
		if ( class_exists( __NAMESPACE__ . '\\SafeBoot' ) && SafeBoot::is_blocked() ) {
			echo '<p>' . esc_html__( 'Safe Boot is also active. Remove obsolete copies first, then use the separate Retry Safe Boot control.', 'sabri-complete-home-news-feed' ) . '</p>';
		}
		if ( function_exists( 'wp_nonce_url' ) && function_exists( 'admin_url' ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS_ACTION ), self::DISMISS_ACTION );
			echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'I removed the obsolete copies', 'sabri-complete-home-news-feed' ) . '</a></p>';
		}
		echo '</div>';
	}

	/** Acknowledge the report without deleting its audit evidence. */
	public static function dismiss() {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) {
			if ( function_exists( 'wp_die' ) ) {
				wp_die( esc_html__( 'You do not have permission to acknowledge duplicate File 21 copies.', 'sabri-complete-home-news-feed' ) );
			}
			return;
		}
		if ( function_exists( 'check_admin_referer' ) ) {
			check_admin_referer( self::DISMISS_ACTION );
		}
		$report = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();
		$report = is_array( $report ) ? $report : array();
		$report['acknowledged'] = 1;
		$report['acknowledged_at_utc'] = gmdate( 'Y-m-d H:i:s' );
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_NAME, $report, false );
		}
		$url = function_exists( 'admin_url' ) ? admin_url( 'plugins.php?sabri_duplicate_copy_acknowledged=1' ) : '';
		if ( function_exists( 'wp_safe_redirect' ) && '' !== $url ) {
			wp_safe_redirect( $url );
			exit;
		}
	}

	/** Sanitize a relative plugin basename without exposing server paths. */
	private static function clean_plugin_basename( $value ) {
		$value = str_replace( '\\', '/', (string) $value );
		$value = preg_replace( '~[^A-Za-z0-9._/\-]~', '', $value );
		$value = ltrim( is_string( $value ) ? $value : '', '/' );
		return false !== strpos( $value, '..' ) ? '' : $value;
	}
}
