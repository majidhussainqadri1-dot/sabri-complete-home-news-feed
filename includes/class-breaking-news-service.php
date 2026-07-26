<?php
/**
 * Breaking News lifecycle.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Schedules, activates, expires, and cancels bounded Breaking News records. */
final class BreakingNewsService {
	const CRON_HOOK = 'sabri_hnf_phase5_breaking_tick';
	const ACTIVE_LIMIT = 3;

	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( self::CRON_HOOK, array( __CLASS__, 'tick' ) );
		}
	}

	public static function schedule( $article_id, $starts_at, $expires_at, $priority = 1 ) {
		$article_id = Phase5Contracts::positive_int( $article_id );
		$start = self::utc( $starts_at ); $end = self::utc( $expires_at ); $priority = max( 1, min( 10, (int) $priority ) );
		if ( $article_id < 1 || ! self::can_manage() || ! $start || ! $end || strtotime( $end . ' UTC' ) <= strtotime( $start . ' UTC' ) || strtotime( $end . ' UTC' ) <= time() ) {
			return self::error( 'phase5_payload_invalid', 400 );
		}
		if ( function_exists( 'get_post_type' ) && Phase4Contracts::POST_TYPE !== get_post_type( $article_id ) ) { return self::error( 'phase5_not_found', 404 ); }
		if ( ! NewsPolicy::can_public_read( $article_id, 'single' ) ) { return self::error( 'phase5_release_blocked', 409 ); }
		$existing = Phase5Repository::query( 'breaking', array( 'article_id' => $article_id ), 20, 0, 'id', 'DESC' );
		foreach ( $existing as $row ) {
			if ( in_array( $row['state'], array( 'scheduled', 'active' ), true ) ) { return self::error( 'phase5_conflict', 409 ); }
		}
		$state = strtotime( $start . ' UTC' ) <= time() ? 'active' : 'scheduled';
		if ( 'active' === $state && self::active_count() >= self::ACTIVE_LIMIT ) { return self::error( 'phase5_conflict', 409 ); }
		$now = gmdate( 'Y-m-d H:i:s' );
		$id = Phase5Repository::insert( 'breaking', array( 'article_id'=>$article_id,'state'=>$state,'priority'=>$priority,'starts_at'=>$start,'expires_at'=>$end,'created_by'=>self::actor(),'cancelled_by'=>0,'created_at'=>$now,'updated_at'=>$now ) );
		if ( $id < 1 ) { return self::error( 'phase5_query_failed', 500 ); }
		self::schedule_tick(); Phase5AuditIntegrity::record( 'breaking-' . $state, 'breaking', $id, array( 'state'=>$state ) );
		return array( 'success'=>true,'status'=>201,'data'=>Phase5Repository::find( 'breaking', $id ) );
	}

	public static function cancel( $id ) {
		$row = Phase5Repository::find( 'breaking', $id );
		if ( ! $row || ! self::can_manage() ) { return self::error( 'phase5_not_found', 404 ); }
		if ( ! in_array( $row['state'], array( 'scheduled','active' ), true ) ) { return self::error( 'phase5_conflict', 409 ); }
		if ( ! Phase5Repository::update( 'breaking', $id, array( 'state'=>'cancelled','cancelled_by'=>self::actor(),'updated_at'=>gmdate( 'Y-m-d H:i:s' ) ) ) ) { return self::error( 'phase5_query_failed', 500 ); }
		Phase5AuditIntegrity::record( 'breaking-cancelled', 'breaking', $id, array( 'previous_state'=>$row['state'],'state'=>'cancelled' ) );
		return array( 'success'=>true,'status'=>200 );
	}

	public static function tick() {
		$now = gmdate( 'Y-m-d H:i:s' );
		$scheduled = Phase5Repository::query( 'breaking', array( 'state'=>'scheduled' ), 100, 0, 'starts_at', 'ASC' );
		foreach ( $scheduled as $row ) {
			if ( strtotime( $row['expires_at'] . ' UTC' ) <= time() ) {
				Phase5Repository::update( 'breaking', $row['id'], array( 'state'=>'expired','updated_at'=>$now ) );
				continue;
			}
			if ( strtotime( $row['starts_at'] . ' UTC' ) <= time() && NewsPolicy::can_public_read( $row['article_id'], 'single' ) && self::active_count() < self::ACTIVE_LIMIT ) {
				Phase5Repository::update( 'breaking', $row['id'], array( 'state'=>'active','updated_at'=>$now ) );
			}
		}
		$active = Phase5Repository::query( 'breaking', array( 'state'=>'active' ), 100, 0, 'expires_at', 'ASC' );
		foreach ( $active as $row ) {
			if ( strtotime( $row['expires_at'] . ' UTC' ) <= time() || ! NewsPolicy::can_public_read( $row['article_id'], 'single' ) ) {
				Phase5Repository::update( 'breaking', $row['id'], array( 'state'=>'expired','updated_at'=>$now ) );
			}
		}
		self::schedule_tick();
	}

	public static function active_public() {
		if ( ! Phase5FeatureSettings::enabled( 'breaking_news_enabled' ) || ! NewsPolicy::public_reads_allowed() ) { return array(); }
		self::tick();
		$rows = Phase5Repository::query( 'breaking', array( 'state'=>'active' ), self::ACTIVE_LIMIT, 0, 'priority', 'DESC' );
		$out = array();
		foreach ( $rows as $row ) {
			if ( ! NewsPolicy::can_public_read( $row['article_id'], 'single' ) ) { continue; }
			$article = NewsQueryService::single( (int) $row['article_id'] );
			if ( ! empty( $article['success'] ) ) { $out[] = array( 'id'=>(int)$row['id'],'article'=>$article['data'],'priority'=>(int)$row['priority'],'expires_at'=>$row['expires_at'] ); }
		}
		return $out;
	}

	public static function active_count() { return Phase5Repository::count( 'breaking', array( 'state'=>'active' ) ); }

	private static function schedule_tick() {
		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( self::CRON_HOOK ) ) { wp_schedule_single_event( time() + 60, self::CRON_HOOK ); }
	}
	private static function can_manage() { return Phase5FeatureSettings::enabled( 'breaking_news_enabled' ) && function_exists( 'current_user_can' ) && current_user_can( 'manage_breaking_news' ); }
	private static function actor() { return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0; }
	private static function utc( $value ) { if ( ! is_string( $value ) ) return ''; $dt = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value, new \DateTimeZone( 'UTC' ) ); return $dt && $dt->format( 'Y-m-d H:i:s' ) === $value ? $value : ''; }
	private static function error( $code, $status ) { return array( 'success'=>false,'status'=>$status,'code'=>$code ); }
}
