<?php
/**
 * Phase 3G notification bridge.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emits privacy-minimized social events to an optional Notifications system.
 *
 * This class owns no notification inbox or delivery table. It exposes one
 * plugin-owned action and one explicitly configured callback contract while
 * keeping the originating social write independent from delivery success.
 */
final class NotificationBridge {
	const EVENT_HOOK    = 'sabri_feed_notification_event';
	const FAILURE_HOOK  = 'sabri_feed_notification_bridge_failed';
	const DEDUPE_PREFIX = 'sabri_hnf_notify_';
	const DEDUPE_TTL    = 300;

	/** Register approved-comment notification hooks. */
	public static function register() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'comment_post', array( __CLASS__, 'handle_comment_created' ), 10, 3 );
			add_action( 'transition_comment_status', array( __CLASS__, 'handle_comment_status_transition' ), 10, 3 );
		}
	}

	/** Allowed bridge events and their required object types. */
	public static function allowed_events() {
		return array(
			'post_reaction' => 'post',
			'post_comment'  => 'comment',
			'comment_reply' => 'comment',
			'user_follow'   => 'user',
			'poll_vote'     => 'post',
		);
	}

	/** Dispatch one normalized event without exposing private content. */
	public static function dispatch( $event, $actor_user_id, $recipient_user_id, $object_type, $object_id, array $context = array() ) {
		if ( ! Phase3FeatureSettings::enabled( 'notification_bridge_enabled' ) ) {
			return InteractionResult::success( 'notification_bridge_disabled', array( 'dispatched' => false ), 'Notification bridge disabled.', 200 );
		}

		$event             = self::clean_key( $event );
		$object_type       = self::clean_key( $object_type );
		$actor_user_id     = self::positive_id( $actor_user_id );
		$recipient_user_id = self::positive_id( $recipient_user_id );
		$object_id         = self::positive_id( $object_id );
		$events            = self::allowed_events();

		if ( ! isset( $events[ $event ] ) || $events[ $event ] !== $object_type || $actor_user_id <= 0 || $recipient_user_id <= 0 || $object_id <= 0 ) {
			return InteractionResult::error( 'invalid_notification_event', 'The notification event is unavailable.', array(), 400 );
		}
		if ( $actor_user_id === $recipient_user_id ) {
			return InteractionResult::success( 'self_notification_suppressed', array( 'dispatched' => false ), 'Self notification suppressed.', 200 );
		}
		if ( ! self::user_exists( $actor_user_id ) || ! self::user_exists( $recipient_user_id ) ) {
			return InteractionResult::error( 'notification_user_unavailable', 'The notification recipient is unavailable.', array(), 404 );
		}

		$context = self::sanitize_context( $context );
		$key     = self::dedupe_key( $event, $actor_user_id, $recipient_user_id, $object_type, $object_id, $context );
		if ( function_exists( 'get_transient' ) && get_transient( $key ) ) {
			return InteractionResult::success( 'notification_duplicate_suppressed', array( 'dispatched' => false, 'duplicate' => true ), 'Duplicate notification suppressed.', 200 );
		}

		$payload = array(
			'event'             => $event,
			'actor_user_id'     => $actor_user_id,
			'recipient_user_id' => $recipient_user_id,
			'object_type'       => $object_type,
			'object_id'         => $object_id,
			'post_id'           => isset( $context['post_id'] ) ? (int) $context['post_id'] : ( 'post' === $object_type ? $object_id : 0 ),
			'url'               => self::object_url( $object_type, $object_id, $context ),
			'created_at'        => gmdate( 'Y-m-d H:i:s' ),
		);

		$callback_called = false;
		$callback        = self::configured_callback();
		try {
			if ( function_exists( 'do_action' ) ) {
				do_action( self::EVENT_HOOK, $payload );
			}
			if ( $callback && is_callable( $callback ) ) {
				$callback_called = true;
				call_user_func( $callback, $payload );
			}
		} catch ( \Throwable $error ) {
			if ( function_exists( 'do_action' ) ) {
				do_action(
					self::FAILURE_HOOK,
					array(
						'event'        => $event,
						'object_type'  => $object_type,
						'object_id'    => $object_id,
						'failure_code' => 'callback_exception',
					)
				);
			}
			return InteractionResult::error( 'notification_bridge_failed', 'The notification could not be dispatched.', array(), 503 );
		}

		if ( function_exists( 'set_transient' ) ) {
			set_transient( $key, 1, self::DEDUPE_TTL );
		}

		return InteractionResult::success(
			'notification_dispatched',
			array(
				'dispatched'      => true,
				'callback_called' => $callback_called,
			),
			'Notification event dispatched.',
			200
		);
	}

	/** Dispatch a post-author event. */
	public static function post_event( $event, $actor_user_id, $post_id, array $context = array() ) {
		$post_id            = self::positive_id( $post_id );
		$recipient          = $post_id > 0 && function_exists( 'get_post_field' ) ? self::positive_id( get_post_field( 'post_author', $post_id ) ) : 0;
		$context['post_id'] = $post_id;
		return self::dispatch( $event, $actor_user_id, $recipient, 'post', $post_id, $context );
	}

	/** Dispatch a follow event to the followed account. */
	public static function follow_event( $actor_user_id, $target_user_id ) {
		return self::dispatch( 'user_follow', $actor_user_id, $target_user_id, 'user', $target_user_id );
	}

	/** Dispatch an approved plugin comment or reply. */
	public static function comment_event( $comment ) {
		$comment = self::comment_object( $comment );
		if ( ! $comment || CommentPolicy::COMMENT_TYPE !== (string) $comment->comment_type || self::comment_removed( $comment->comment_ID ) ) {
			return InteractionResult::error( 'notification_comment_unavailable', 'The comment notification is unavailable.', array(), 404 );
		}

		$actor_user_id = self::positive_id( $comment->user_id );
		$post_id       = self::positive_id( $comment->comment_post_ID );
		$parent_id     = self::positive_id( $comment->comment_parent );
		$recipient     = 0;
		$event         = 'post_comment';

		if ( $parent_id > 0 ) {
			$parent    = self::comment_object( $parent_id );
			$recipient = $parent ? self::positive_id( $parent->user_id ) : 0;
			$event     = 'comment_reply';
		} elseif ( $post_id > 0 && function_exists( 'get_post_field' ) ) {
			$recipient = self::positive_id( get_post_field( 'post_author', $post_id ) );
		}

		return self::dispatch(
			$event,
			$actor_user_id,
			$recipient,
			'comment',
			self::positive_id( $comment->comment_ID ),
			array( 'post_id' => $post_id )
		);
	}

	/** Dispatch an immediately approved newly inserted plugin comment. */
	public static function handle_comment_created( $comment_id, $approved, $commentdata = array() ) {
		unset( $commentdata );
		$approved = self::clean_key( $approved );
		if ( ! in_array( $approved, array( 'approved', 'approve', '1' ), true ) ) {
			return;
		}
		self::comment_event( $comment_id );
	}

	/** Send a pending comment notification only when it becomes approved. */
	public static function handle_comment_status_transition( $new_status, $old_status, $comment ) {
		$new_status = self::clean_key( $new_status );
		$old_status = self::clean_key( $old_status );
		if ( ! in_array( $new_status, array( 'approved', 'approve', '1' ), true ) || $new_status === $old_status ) {
			return;
		}
		self::comment_event( $comment );
	}

	/** Restrict context to non-content keys. */
	private static function sanitize_context( array $context ) {
		$out = array();
		if ( isset( $context['post_id'] ) ) {
			$out['post_id'] = self::positive_id( $context['post_id'] );
		}
		if ( isset( $context['state_key'] ) ) {
			$out['state_key'] = substr( self::clean_key( $context['state_key'] ), 0, 64 );
		}
		return $out;
	}

	/** Resolve the explicitly configured one-argument callback. */
	private static function configured_callback() {
		$settings = Settings::get();
		$value    = isset( $settings['integrations']['functions']['notifications'] ) ? trim( (string) $settings['integrations']['functions']['notifications'] ) : '';
		if ( '' === $value || ! preg_match( '/^[A-Za-z_\\\\][A-Za-z0-9_\\\\:]*$/', $value ) ) {
			return '';
		}
		return function_exists( 'apply_filters' ) ? apply_filters( 'sabri_feed_notification_callback', $value ) : $value;
	}

	/** Build a privacy-safe destination URL. */
	private static function object_url( $object_type, $object_id, array $context ) {
		if ( 'user' === $object_type ) {
			return ProfileLinkResolver::url( $object_id );
		}
		$post_id = isset( $context['post_id'] ) ? self::positive_id( $context['post_id'] ) : ( 'post' === $object_type ? self::positive_id( $object_id ) : 0 );
		$url     = $post_id > 0 && function_exists( 'get_permalink' ) ? (string) get_permalink( $post_id ) : '';
		if ( 'comment' === $object_type && $url ) {
			$url .= '#comment-' . self::positive_id( $object_id );
		}
		return $url;
	}

	/** Dedupe without storing raw identities in transient names. */
	private static function dedupe_key( $event, $actor, $recipient, $object_type, $object_id, array $context ) {
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'nonce' ) : SABRI_HNF_SLUG;
		$raw  = implode( '|', array( $event, $actor, $recipient, $object_type, $object_id, isset( $context['state_key'] ) ? $context['state_key'] : '' ) );
		return self::DEDUPE_PREFIX . hash_hmac( 'sha256', $raw, (string) $salt );
	}

	/** Existing user test. */
	private static function user_exists( $user_id ) {
		return $user_id > 0 && function_exists( 'get_userdata' ) && (bool) get_userdata( $user_id );
	}

	/** Existing comment object. */
	private static function comment_object( $comment ) {
		if ( is_object( $comment ) ) {
			return $comment;
		}
		return function_exists( 'get_comment' ) ? get_comment( self::positive_id( $comment ) ) : false;
	}

	/** Whether plugin comment is soft-deleted. */
	private static function comment_removed( $comment_id ) {
		return function_exists( 'get_comment_meta' ) && (bool) get_comment_meta( self::positive_id( $comment_id ), CommentPolicy::META_DELETED, true );
	}

	/** Strict positive integer. */
	private static function positive_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/', (string) $value ) ? (int) $value : 0;
	}

	/** Safe key. */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}
}
