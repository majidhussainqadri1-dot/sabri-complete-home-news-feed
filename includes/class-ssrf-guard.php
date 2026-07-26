<?php
/**
 * Server-side request forgery guard.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Validates outbound metadata-fetch destinations without performing a fetch. */
final class SsrfGuard {
	public static function register() {}

	public static function validate_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url || strlen( $url ) > 2048 || preg_match( '/[\x00-\x20\x7F]/', $url ) ) {
			return array( 'success'=>false, 'code'=>'phase5_payload_invalid' );
		}
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http','https' ), true ) ) {
			return array( 'success'=>false, 'code'=>'phase5_payload_invalid' );
		}
		$host = strtolower( rtrim( (string) $parts['host'], '.' ) );
		if ( in_array( $host, array( 'localhost','localhost.localdomain' ), true ) || str_ends_with( $host, '.local' ) || str_ends_with( $host, '.internal' ) ) {
			return array( 'success'=>false, 'code'=>'phase5_permission_denied' );
		}
		$ips = filter_var( $host, FILTER_VALIDATE_IP ) ? array( $host ) : self::resolve( $host );
		if ( ! $ips ) {
			return array( 'success'=>false, 'code'=>'phase5_query_failed' );
		}
		foreach ( $ips as $ip ) {
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return array( 'success'=>false, 'code'=>'phase5_permission_denied' );
			}
		}
		if ( isset( $parts['port'] ) && ! in_array( (int) $parts['port'], array( 80,443 ), true ) ) {
			return array( 'success'=>false, 'code'=>'phase5_permission_denied' );
		}
		return array( 'success'=>true, 'url'=>function_exists('esc_url_raw')?esc_url_raw($url,array('http','https')):$url, 'host'=>$host, 'ips'=>$ips );
	}

	private static function resolve( $host ) {
		$ips = array();
		if ( function_exists( 'dns_get_record' ) ) {
			$records = @dns_get_record( $host, DNS_A | DNS_AAAA );
			foreach ( is_array( $records ) ? $records : array() as $record ) {
				if ( ! empty( $record['ip'] ) ) $ips[] = $record['ip'];
				if ( ! empty( $record['ipv6'] ) ) $ips[] = $record['ipv6'];
			}
		}
		return array_values( array_unique( $ips ) );
	}
}
