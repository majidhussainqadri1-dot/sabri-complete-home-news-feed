<?php
/**
 * Safe-boot compatibility guard.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prevents one plugin module from taking down the complete WordPress site.
 */
final class SafeBoot {
	const OPTION_NAME = 'sabri_hnf_safe_boot_state';

	/** @var bool */
	private static $shutdown_registered = false;

	/** Register the fatal-error shutdown observer once. */
	public static function register_shutdown_guard() {
		if ( self::$shutdown_registered || ! function_exists( 'register_shutdown_function' ) ) {
			return;
		}
		self::$shutdown_registered = true;
		register_shutdown_function( array( __CLASS__, 'handle_shutdown' ) );
	}

	/** Clear an earlier safe-boot stop before an explicit activation attempt. */
	public static function clear() {
		if ( function_exists( 'delete_option' ) ) {
			delete_option( self::OPTION_NAME );
		}
	}

	/** Whether plugin runtime must remain paused. */
	public static function is_blocked() {
		$state = self::state();
		return ! empty( $state['active'] );
	}

	/** Return the sanitized diagnostic state. */
	public static function state() {
		$state = function_exists( 'get_option' ) ? get_option( self::OPTION_NAME, array() ) : array();
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Register one runtime module through a catchable boundary.
	 *
	 * @param string $class Fully qualified class name.
	 * @return bool
	 */
	public static function register_module( $class ) {
		$class = is_string( $class ) ? ltrim( $class, '\\' ) : '';
		if ( '' === $class ) {
			self::record_failure( 'unknown-module', 'InvalidArgumentException', 'A runtime module name was invalid.', '', 0 );
			return false;
		}

		try {
			if ( ! class_exists( $class ) ) {
				throw new \RuntimeException( 'The required runtime module could not be loaded.' );
			}
			if ( ! is_callable( array( $class, 'register' ) ) ) {
				throw new \RuntimeException( 'The runtime module does not expose a register method.' );
			}
			call_user_func( array( $class, 'register' ) );
			return true;
		} catch ( \Throwable $error ) {
			self::record_exception( self::module_name( $class ), $error );
			return false;
		}
	}

	/** Record a caught compatibility exception without exposing server paths. */
	public static function record_exception( $module, \Throwable $error ) {
		self::record_failure(
			$module,
			get_class( $error ),
			$error->getMessage(),
			$error->getFile(),
			$error->getLine()
		);
	}

	/** Observe an uncaught fatal originating inside this plugin. */
	public static function handle_shutdown() {
		$error = error_get_last();
		if ( ! is_array( $error ) || empty( $error['type'] ) ) {
			return;
		}
		$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );
		if ( ! in_array( (int) $error['type'], $fatal_types, true ) ) {
			return;
		}
		$file = isset( $error['file'] ) ? (string) $error['file'] : '';
		if ( '' === $file || 0 !== strpos( self::normalize_path( $file ), self::normalize_path( SABRI_HNF_PATH ) ) ) {
			return;
		}
		self::record_failure(
			'uncaught-fatal',
			'PHPFatalError',
			isset( $error['message'] ) ? (string) $error['message'] : 'An internal plugin fatal error occurred.',
			$file,
			isset( $error['line'] ) ? (int) $error['line'] : 0
		);
	}

	/** Administrator-only notice for a paused runtime. */
	public static function admin_notice() {
		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$state = self::state();
		if ( empty( $state['active'] ) ) {
			return;
		}
		$component = isset( $state['module'] ) ? (string) $state['module'] : 'plugin-runtime';
		$location  = isset( $state['file'] ) && '' !== $state['file'] ? $state['file'] . ( ! empty( $state['line'] ) ? ':' . (int) $state['line'] : '' ) : '';
		$code      = isset( $state['fingerprint'] ) ? substr( (string) $state['fingerprint'], 0, 12 ) : 'unavailable';
		$message   = isset( $state['message'] ) ? (string) $state['message'] : 'An internal compatibility error was detected.';

		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Sabri Home & News Feed Safe Boot is active.', 'sabri-complete-home-news-feed' ) . '</strong> ';
		echo esc_html__( 'The plugin runtime was paused so the rest of WordPress can continue working. No posts, media, or database content were deleted.', 'sabri-complete-home-news-feed' );
		echo '</p><p>' . esc_html( 'Component: ' . $component . ( $location ? ' | Location: ' . $location : '' ) . ' | Diagnostic code: ' . $code ) . '</p>';
		echo '<p>' . esc_html( $message ) . '</p></div>';
	}

	/** Store only a bounded, administrator-safe diagnostic. */
	private static function record_failure( $module, $type, $message, $file, $line ) {
		$module  = sanitize_key( str_replace( '\\', '-', (string) $module ) );
		$type    = preg_replace( '/[^A-Za-z0-9_\\-]/', '', (string) $type );
		$message = self::bounded_text( $message, 320 );
		$file    = self::relative_file( $file );
		$line    = max( 0, (int) $line );
		$seed    = implode( '|', array( $module, $type, $message, $file, $line ) );
		$state   = array(
			'active'      => 1,
			'module'      => $module ? $module : 'plugin-runtime',
			'error_type'  => $type,
			'message'     => $message,
			'file'        => $file,
			'line'        => $line,
			'fingerprint' => hash( 'sha256', $seed ),
			'recorded_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION_NAME, $state, false );
		}
	}

	/** Safe module label. */
	private static function module_name( $class ) {
		$parts = explode( '\\', (string) $class );
		return sanitize_key( end( $parts ) );
	}

	/** Convert an internal absolute file path into a plugin-relative path. */
	private static function relative_file( $file ) {
		$file = self::normalize_path( $file );
		$root = self::normalize_path( SABRI_HNF_PATH );
		if ( '' !== $file && 0 === strpos( $file, $root ) ) {
			return ltrim( substr( $file, strlen( $root ) ), '/' );
		}
		return '';
	}

	/** Normalize filesystem separators. */
	private static function normalize_path( $path ) {
		return rtrim( str_replace( '\\', '/', (string) $path ), '/' ) . '/';
	}

	/** Bounded text without HTML or control characters. */
	private static function bounded_text( $value, $limit ) {
		$value = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $value ) : strip_tags( (string) $value );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( trim( $value ), 0, $limit );
		}
		return substr( trim( $value ), 0, $limit );
	}
}
