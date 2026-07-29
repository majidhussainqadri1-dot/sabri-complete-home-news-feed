<?php
/**
 * File 22 Universal Post Composer integration bridge.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the native File 21 social-publication adapter only when the
 * versioned File 22 contract is present. File 21 remains fully operational
 * when File 22 is absent, disabled, incompatible, or in Safe Mode.
 */
final class UniversalComposerBridge {
	const ADAPTER_API_VERSION           = '1.0.0';
	const ADAPTER_KEY                   = 'social_publication';
	const MINIMUM_SHELL_VERSION         = '1.0.1';
	const SHELL_CREATE_CONTRACT_VERSION = '1.0.0';

	/** @var bool Prevent duplicate registration during the same request. */
	private static $registered = false;

	/** Attach compatibility, late-registration, and surface-harmonization paths. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'supc_registry_ready', array( __CLASS__, 'maybe_register_adapter' ), 5 );
			add_action( 'init', array( __CLASS__, 'maybe_register_adapter' ), 25 );
			add_action( 'init', array( __CLASS__, 'harmonize_create_surfaces' ), 30 );
		}

		if ( function_exists( 'add_filter' ) ) {
			add_filter( 'sabri_shell_create_url', array( __CLASS__, 'prefer_universal_create_url' ), 100 );
		}
	}

	/** Register the route-only native publication adapter. */
	public static function maybe_register_adapter() {
		if ( self::$registered || ! self::file22_contract_available() ) {
			return;
		}

		try {
			$result = supc_register_adapter( new UniversalComposerPublicationAdapter() );
			if ( true === $result ) {
				self::$registered = true;
				return;
			}

			if ( function_exists( 'do_action' ) ) {
				$code = function_exists( 'is_wp_error' ) && is_wp_error( $result ) ? $result->get_error_code() : 'registration_rejected';
				do_action( 'sabri_hnf_file22_adapter_registration_error', $code );
			}
		} catch ( \Throwable $error ) {
			if ( function_exists( 'do_action' ) ) {
				do_action( 'sabri_hnf_file22_adapter_registration_error', get_class( $error ) );
			}
		}
	}

	/** Prefer File 22's universal Create page after all native fallback filters. */
	public static function prefer_universal_create_url( $url ) {
		if ( ! self::gateway_available() ) {
			return $url;
		}

		$resolver      = array( '\\Sabri\\UniversalComposer\\Core\\Page_Resolver', 'url' );
		$universal_url = is_callable( $resolver ) ? (string) call_user_func( $resolver ) : '';
		return '' !== $universal_url ? $universal_url : $url;
	}

	/**
	 * Remove File 21's Home/News fallback CTA only when File 20 and File 22 can
	 * provide the complete global gateway to the current authorized user and this
	 * exact native adapter is registered and healthy. The native `/create-post/`
	 * route remains available as the adapter destination.
	 */
	public static function harmonize_create_surfaces() {
		if ( ! self::gateway_available() ) {
			return;
		}

		if ( function_exists( 'remove_action' ) ) {
			remove_action( 'sabri_shell_home_before_main', array( PublicComposerSurface::class, 'render_shell_home_button' ), 5 );
			remove_action( 'sabri_shell_news_main', array( PublicComposerSurface::class, 'render_shell_news_button' ), 5 );
			remove_action( 'loop_start', array( PublicComposerSurface::class, 'render_loop_button' ), 0 );
		}

		if ( function_exists( 'remove_filter' ) ) {
			remove_filter( 'the_content', array( PublicComposerSurface::class, 'inject_content_button' ), 9 );
		}
	}

	/** Whether the exact File 22 base and diagnostic contracts are available. */
	public static function file22_contract_available() {
		return defined( 'SUPC_ADAPTER_API_VERSION' )
			&& self::ADAPTER_API_VERSION === (string) SUPC_ADAPTER_API_VERSION
			&& function_exists( 'supc_register_adapter' )
			&& function_exists( 'supc_adapter_matches' )
			&& interface_exists( '\\Sabri\\UniversalComposer\\Contracts\\Adapter' )
			&& interface_exists( '\\Sabri\\UniversalComposer\\Contracts\\Diagnostic_Adapter' );
	}

	/** Whether File 20 exposes the exact non-overridable Create producer contract. */
	private static function shell_contract_available() {
		if ( ! defined( 'SABRI_SHELL_VERSION' ) || version_compare( (string) SABRI_SHELL_VERSION, self::MINIMUM_SHELL_VERSION, '<' ) ) {
			return false;
		}

		if ( ! defined( 'SABRI_SHELL_CREATE_CONTRACT_VERSION' ) || version_compare( (string) SABRI_SHELL_CREATE_CONTRACT_VERSION, self::SHELL_CREATE_CONTRACT_VERSION, '<' ) ) {
			return false;
		}

		return function_exists( 'sabri_shell_create_contract_available' )
			&& function_exists( 'sabri_shell_create_visible_for_current_user' )
			&& (bool) sabri_shell_create_contract_available()
			&& (bool) sabri_shell_create_visible_for_current_user();
	}

	/**
	 * Whether the complete universal Create gateway may replace File 21's
	 * fallback Home/News CTA.
	 */
	public static function gateway_available() {
		if ( ! self::$registered || ! self::file22_contract_available() || ! self::shell_contract_available() ) {
			return false;
		}

		if ( ! supc_adapter_matches( self::ADAPTER_KEY, SABRI_HNF_SLUG ) ) {
			return false;
		}

		$page_resolver = array( '\\Sabri\\UniversalComposer\\Core\\Page_Resolver', 'is_ready' );
		$safe_mode     = array( '\\Sabri\\UniversalComposer\\Core\\Safe_Mode', 'disabled' );
		if ( ! is_callable( $page_resolver ) || ! call_user_func( $page_resolver ) ) {
			return false;
		}

		return ! is_callable( $safe_mode ) || ! call_user_func( $safe_mode );
	}
}
