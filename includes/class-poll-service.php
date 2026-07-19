<?php
/**
 * Phase 3F authenticated poll voting and aggregate results.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Implements bounded vote creation, replacement, removal, and results policy.
 */
final class PollService {
	/** Create or replace the current authenticated user's vote. */
	public static function vote( $post_id, $option_key, $nonce = '', $user_id = 0 ) {
		if ( ! Phase3FeatureSettings::enabled( 'polls_enabled' ) ) {
			return InteractionResult::error( 'polls_disabled', 'Poll voting is currently unavailable.', array(), 503 );
		}

		$authorized = InteractionPermissions::authorize_post_write( $post_id, $nonce, $user_id );
		if ( empty( $authorized['ok'] ) ) {
			return $authorized;
		}

		$post_id = (int) $authorized['data']['post_id'];
		$user_id = (int) $authorized['data']['user_id'];
		if ( ! PollPolicy::is_poll( $post_id ) ) {
			return InteractionResult::error( 'poll_unavailable', 'The requested poll is unavailable.', array(), 404 );
		}

		$definition = PollPolicy::definition( $post_id );
		if ( PollPolicy::is_closed( $definition ) ) {
			return InteractionResult::error( 'poll_closed', 'This poll is closed.', array(), 409 );
		}

		$option_key = PollPolicy::option_key( $option_key );
		if ( '' === PollPolicy::option_label( $definition, $option_key ) ) {
			return InteractionResult::error( 'invalid_poll_option', 'The selected poll option is unavailable.', array(), 400 );
		}

		$limit = InteractionRateLimiter::attempt( 'polls', $user_id, $post_id );
		if ( empty( $limit['ok'] ) ) {
			return $limit;
		}

		$group  = isset( $definition['vote_group_key'] ) ? (string) $definition['vote_group_key'] : PollPolicy::VOTE_GROUP;
		$record = PollVoteRepository::vote_record( $post_id, $user_id, $group );
		if ( $record && 'active' === (string) $record['status'] && $option_key === (string) $record['option_key'] ) {
			return self::success_with_results( 'poll_vote_saved', 'Vote saved.', $post_id, $user_id, 200 );
		}

		if ( $record && 'active' === (string) $record['status'] && empty( $definition['allow_change'] ) ) {
			return InteractionResult::error( 'poll_vote_change_disabled', 'This poll does not allow changing an existing vote.', array(), 409 );
		}

		if ( $record ) {
			$updated = InteractionRepository::update_rows(
				'poll_votes',
				array( 'option_key' => $option_key, 'status' => 'active' ),
				array( 'poll_post_id' => $post_id, 'user_id' => $user_id, 'vote_group_key' => $group )
			);
			if ( empty( $updated['ok'] ) ) {
				return $updated;
			}
		} else {
			$inserted = InteractionRepository::insert_row(
				'poll_votes',
				array(
					'poll_post_id'   => $post_id,
					'option_key'     => $option_key,
					'user_id'        => $user_id,
					'anonymous_hash' => '',
					'vote_group_key' => $group,
					'status'         => 'active',
				)
			);
			if ( empty( $inserted['ok'] ) ) {
				$race_record = PollVoteRepository::vote_record( $post_id, $user_id, $group );
				if ( ! $race_record ) {
					return $inserted;
				}
				if ( 'active' === (string) $race_record['status'] && $option_key !== (string) $race_record['option_key'] && empty( $definition['allow_change'] ) ) {
					return InteractionResult::error( 'poll_vote_change_disabled', 'This poll does not allow changing an existing vote.', array(), 409 );
				}
				$recovered = InteractionRepository::update_rows(
					'poll_votes',
					array( 'option_key' => $option_key, 'status' => 'active' ),
					array( 'poll_post_id' => $post_id, 'user_id' => $user_id, 'vote_group_key' => $group )
				);
				if ( empty( $recovered['ok'] ) ) {
					return $recovered;
				}
			}
		}

		AuditLog::record( 'poll_vote_saved', array( 'option_key' => $option_key ), 'post', $post_id );
		return self::success_with_results( 'poll_vote_saved', 'Vote saved.', $post_id, $user_id, 200 );
	}

	/** Remove the current authenticated user's active vote while the poll is open. */
	public static function remove_vote( $post_id, $nonce = '', $user_id = 0 ) {
		if ( ! Phase3FeatureSettings::enabled( 'polls_enabled' ) ) {
			return InteractionResult::error( 'polls_disabled', 'Poll voting is currently unavailable.', array(), 503 );
		}

		$authorized = InteractionPermissions::authorize_post_write( $post_id, $nonce, $user_id );
		if ( empty( $authorized['ok'] ) ) {
			return $authorized;
		}

		$post_id = (int) $authorized['data']['post_id'];
		$user_id = (int) $authorized['data']['user_id'];
		if ( ! PollPolicy::is_poll( $post_id ) ) {
			return InteractionResult::error( 'poll_unavailable', 'The requested poll is unavailable.', array(), 404 );
		}

		$definition = PollPolicy::definition( $post_id );
		if ( PollPolicy::is_closed( $definition ) ) {
			return InteractionResult::error( 'poll_closed', 'This poll is closed.', array(), 409 );
		}

		$limit = InteractionRateLimiter::attempt( 'polls', $user_id, $post_id );
		if ( empty( $limit['ok'] ) ) {
			return $limit;
		}

		$group  = isset( $definition['vote_group_key'] ) ? (string) $definition['vote_group_key'] : PollPolicy::VOTE_GROUP;
		$record = PollVoteRepository::vote_record( $post_id, $user_id, $group );
		if ( ! $record || 'active' !== (string) $record['status'] ) {
			return self::success_with_results( 'poll_vote_removed', 'Vote removed.', $post_id, $user_id, 200 );
		}
		if ( empty( $definition['allow_change'] ) ) {
			return InteractionResult::error( 'poll_vote_change_disabled', 'This poll does not allow removing an existing vote.', array(), 409 );
		}

		$updated = InteractionRepository::update_rows(
			'poll_votes',
			array( 'status' => 'removed' ),
			array( 'poll_post_id' => $post_id, 'user_id' => $user_id, 'vote_group_key' => $group )
		);
		if ( empty( $updated['ok'] ) ) {
			return $updated;
		}

		AuditLog::record( 'poll_vote_removed', array(), 'post', $post_id );
		return self::success_with_results( 'poll_vote_removed', 'Vote removed.', $post_id, $user_id, 200 );
	}

	/** Return visibility-safe poll state and aggregate-only results. */
	public static function results( $post_id, $user_id = 0 ) {
		if ( ! Phase3FeatureSettings::enabled( 'polls_enabled' ) ) {
			return InteractionResult::error( 'polls_disabled', 'Polls are currently unavailable.', array(), 503 );
		}

		$post_id = self::positive_id( $post_id );
		$current = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		$user_id = $user_id && (int) $user_id !== $current ? 0 : $current;

		if ( $post_id <= 0 || ! PostMetadata::user_can_view( $post_id, $user_id ) || ! PollPolicy::is_poll( $post_id ) ) {
			return InteractionResult::error( 'poll_unavailable', 'The requested poll is unavailable.', array(), 404 );
		}

		return InteractionResult::success( 'poll_results_loaded', self::results_data( $post_id, $user_id ), 'Poll loaded.', 200 );
	}

	/** Build safe result data without voter identities. */
	public static function results_data( $post_id, $user_id = 0 ) {
		$definition  = PollPolicy::definition( $post_id );
		$group       = isset( $definition['vote_group_key'] ) ? (string) $definition['vote_group_key'] : PollPolicy::VOTE_GROUP;
		$record      = $user_id > 0 ? PollVoteRepository::vote_record( $post_id, $user_id, $group ) : null;
		$current_key = $record && 'active' === (string) $record['status'] ? PollPolicy::option_key( $record['option_key'] ) : '';
		$closed      = PollPolicy::is_closed( $definition );
		$visible     = PollPolicy::results_visible( $definition, '' !== $current_key );
		$counts      = $visible ? PollVoteRepository::aggregate_counts( $post_id, $group ) : array();
		$total       = $visible ? array_sum( $counts ) : 0;
		$options     = array();

		foreach ( $definition['options'] as $option ) {
			$key     = (string) $option['key'];
			$count   = $visible && isset( $counts[ $key ] ) ? max( 0, (int) $counts[ $key ] ) : 0;
			$percent = $visible && $total > 0 ? round( ( $count / $total ) * 100, 1 ) : 0;
			$options[] = array(
				'key'           => $key,
				'label'         => (string) $option['label'],
				'selected'      => $key === $current_key,
				'count_visible' => $visible,
				'count'         => $visible ? $count : null,
				'percent'       => $visible ? $percent : null,
			);
		}

		$has_vote    = '' !== $current_key;
		$allow_change = ! empty( $definition['allow_change'] );
		return array(
			'post_id'         => (int) $post_id,
			'question'        => (string) $definition['question'],
			'options'         => $options,
			'closed'          => $closed,
			'closes_at'       => (string) $definition['closes_at'],
			'results_policy'  => (string) $definition['results_policy'],
			'results_visible' => $visible,
			'total_votes'     => $visible ? $total : null,
			'current_option'  => $current_key,
			'has_voted'       => $has_vote,
			'allow_change'    => $allow_change,
			'can_vote'        => $user_id > 0 && ! $closed && ( ! $has_vote || $allow_change ),
			'can_remove'      => $user_id > 0 && ! $closed && $has_vote && $allow_change,
		);
	}

	/** Return a successful mutation with refreshed results. */
	private static function success_with_results( $code, $message, $post_id, $user_id, $status ) {
		return InteractionResult::success( $code, self::results_data( $post_id, $user_id ), $message, $status );
	}

	/** Strict positive ID. */
	private static function positive_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/', (string) $value ) ? (int) $value : 0;
	}
}
