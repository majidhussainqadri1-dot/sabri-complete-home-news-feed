<?php
/**
 * File 21 canonical Home/News acknowledgement for File 01 legacy cutover.
 *
 * This adapter acknowledges only Home and News content ownership. It does
 * not mutate File 01 options/pages; it issues bounded reversible receipts
 * after File 21's canonical Home/News runtime is available.
 *
 * @package SabriCompleteHomeNewsFeed
 */
namespace Sabri\HomeNewsFeed;
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class File01ReconciliationAdapter {
    const CONTRACT_VERSION = '1.0.0';
    const RECEIPTS_OPTION  = 'sabri_hnf_file01_reconciliation_receipts';

    public static function register() {
        add_filter( 'spf_owner_reconciliation_plan', array( __CLASS__, 'plan' ), 10, 2 );
        add_filter( 'spf_execute_owner_reconciliation', array( __CLASS__, 'execute' ), 10, 3 );
        add_filter( 'spf_rollback_owner_reconciliation', array( __CLASS__, 'rollback' ), 10, 3 );
    }

    public static function plan( $plan, $context ) {
        if ( is_array( $plan ) ) { return $plan; }
        $context = is_array( $context ) ? $context : array();
        $key = sanitize_key( $context['legacy_key'] ?? '' );
        if ( ! in_array( $key, array( 'home', 'news' ), true ) ) { return $plan; }
        if ( 'home' === $key && ! class_exists( HomeIntegration::class ) ) { return $plan; }
        if ( 'news' === $key && ! class_exists( NewsRouting::class ) ) { return $plan; }
        return array(
            'accepted'        => true,
            'owner_module'    => 'file-21',
            'command_version' => self::CONTRACT_VERSION,
            'owner_scope'     => 'canonical_home_news_content_handoff',
            'legacy_key'      => $key,
            'page_id'         => absint( $context['page_id'] ?? 0 ),
            'reversible'      => true,
        );
    }

    public static function execute( $result, $action, $plan_hash ) {
        if ( is_array( $result ) ) { return $result; }
        if ( ! is_array( $action ) || ! is_array( $action['owner_plan'] ?? null ) ) { return $result; }
        $owner_plan = $action['owner_plan'];
        if ( 'file-21' !== sanitize_key( $owner_plan['owner_module'] ?? '' ) ) { return $result; }
        if ( self::CONTRACT_VERSION !== (string) ( $owner_plan['command_version'] ?? '' ) ) { return $result; }
        $key = sanitize_key( $action['legacy_key'] ?? '' );
        if ( ! in_array( $key, array( 'home', 'news' ), true ) ) { return $result; }
        $page_id = absint( $action['page_id'] ?? 0 );
        $plan_hash = strtolower( preg_replace( '/[^a-f0-9]/i', '', (string) $plan_hash ) );
        if ( 64 !== strlen( $plan_hash ) ) { return $result; }
        $receipt_id = 'file21-' . substr( hash( 'sha256', $plan_hash . '|' . $key . '|' . $page_id . '|' . self::CONTRACT_VERSION ), 0, 40 );
        $state = array(
            'receipt_id'      => $receipt_id,
            'legacy_key'      => $key,
            'page_id'         => $page_id,
            'plan_hash'       => $plan_hash,
            'command_version' => self::CONTRACT_VERSION,
            'scope'           => 'canonical_home_news_content_handoff',
        );
        $state_hash = hash( 'sha256', self::json( $state ) );
        $all = get_option( self::RECEIPTS_OPTION, array() );
        $all = is_array( $all ) ? $all : array();
        if ( isset( $all[ $receipt_id ] ) && self::json( $all[ $receipt_id ] ) !== self::json( $state ) ) { return $result; }
        $all[ $receipt_id ] = $state;
        update_option( self::RECEIPTS_OPTION, $all, false );
        $saved = get_option( self::RECEIPTS_OPTION, array() );
        if ( ! is_array( $saved ) || ! isset( $saved[ $receipt_id ] ) || self::json( $saved[ $receipt_id ] ) !== self::json( $state ) ) { return $result; }
        return array(
            'success'          => true,
            'receipt_id'       => $receipt_id,
            'owner_module'     => 'file-21',
            'command_version'  => self::CONTRACT_VERSION,
            'rollback_command' => 'rollback_file01_home_news_ack',
            'state_hash'       => $state_hash,
        );
    }

    public static function rollback( $result, $receipt, $plan_hash ) {
        if ( is_array( $result ) && ! empty( $result['success'] ) ) { return $result; }
        if ( ! is_array( $receipt ) || 'file-21' !== sanitize_key( $receipt['owner_module'] ?? '' ) ) { return $result; }
        if ( 'rollback_file01_home_news_ack' !== sanitize_key( $receipt['rollback_command'] ?? '' ) ) { return $result; }
        $receipt_id = sanitize_key( $receipt['receipt_id'] ?? '' );
        $all = get_option( self::RECEIPTS_OPTION, array() );
        $all = is_array( $all ) ? $all : array();
        if ( ! isset( $all[ $receipt_id ] ) ) { return array( 'success' => true, 'idempotent_replay' => true ); }
        $stored = $all[ $receipt_id ];
        $expected_hash = strtolower( preg_replace( '/[^a-f0-9]/i', '', (string) $plan_hash ) );
        if ( ! is_array( $stored ) || ! hash_equals( (string) $stored['plan_hash'], $expected_hash ) ) { return $result; }
        unset( $all[ $receipt_id ] );
        update_option( self::RECEIPTS_OPTION, $all, false );
        $saved = get_option( self::RECEIPTS_OPTION, array() );
        if ( is_array( $saved ) && isset( $saved[ $receipt_id ] ) ) { return $result; }
        return array( 'success' => true, 'receipt_id' => $receipt_id );
    }

    private static function json( $value ) {
        return function_exists( 'wp_json_encode' ) ? (string) wp_json_encode( $value ) : (string) json_encode( $value );
    }
}
