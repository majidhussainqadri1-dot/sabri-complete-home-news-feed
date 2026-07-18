<?php
/**
 * Isolated WordPress comment API stubs for Phase 3C tests.
 *
 * @package SabriCompleteHomeNewsFeed
 */

if ( ! isset( $sabri_test_comments ) ) {
	$sabri_test_comments = array();
}
if ( ! isset( $sabri_test_comment_meta ) ) {
	$sabri_test_comment_meta = array();
}
if ( ! isset( $sabri_test_next_comment_id ) ) {
	$sabri_test_next_comment_id = 1;
}

/**
 * Reset comment-only test state.
 *
 * @return void
 */
function sabri_test_reset_comments() {
	global $sabri_test_comments, $sabri_test_comment_meta, $sabri_test_next_comment_id, $sabri_test_filter_overrides;
	$sabri_test_comments       = array();
	$sabri_test_comment_meta   = array();
	$sabri_test_next_comment_id = 1;
	unset( $sabri_test_filter_overrides['sabri_feed_comment_now'] );
	unset( $sabri_test_filter_overrides['sabri_feed_comment_max_length'] );
	unset( $sabri_test_filter_overrides['sabri_feed_comment_max_reply_depth'] );
	unset( $sabri_test_filter_overrides['sabri_feed_comment_edit_minutes'] );
	unset( $sabri_test_filter_overrides['sabri_feed_new_comment_policy'] );
	unset( $sabri_test_filter_overrides['sabri_feed_clinical_comment_privacy_scan'] );

	if ( class_exists( 'Sabri\\HomeNewsFeed\\CommentRuntime' ) ) {
		\Sabri\HomeNewsFeed\CommentRuntime::reset_runtime_guards();
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		$email = filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL );
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = false ) {
		unset( $gmt );
		if ( 'timestamp' === $type || 'U' === $type ) {
			return time();
		}
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'comments_open' ) ) {
	function comments_open( $post_id = 0 ) {
		$post = get_post( $post_id );
		return ! $post || ! isset( $post->comment_status ) || 'closed' !== $post->comment_status;
	}
}

if ( ! function_exists( 'wp_insert_comment' ) ) {
	function wp_insert_comment( $commentdata ) {
		global $sabri_test_comments, $sabri_test_next_comment_id;
		if ( ! is_array( $commentdata ) || empty( $commentdata['comment_post_ID'] ) || ! get_post( (int) $commentdata['comment_post_ID'] ) ) {
			return false;
		}

		$id = $sabri_test_next_comment_id++;
		$defaults = array(
			'comment_ID'           => $id,
			'comment_post_ID'      => 0,
			'comment_author'       => '',
			'comment_author_email' => '',
			'comment_author_url'   => '',
			'comment_author_IP'    => '',
			'comment_date'         => gmdate( 'Y-m-d H:i:s' ),
			'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s' ),
			'comment_content'      => '',
			'comment_karma'        => 0,
			'comment_approved'     => '0',
			'comment_agent'        => '',
			'comment_type'         => 'comment',
			'comment_parent'       => 0,
			'user_id'              => 0,
		);
		$row = array_merge( $defaults, $commentdata, array( 'comment_ID' => $id ) );
		$row['comment_ID']       = $id;
		$row['comment_post_ID']  = (int) $row['comment_post_ID'];
		$row['comment_parent']   = (int) $row['comment_parent'];
		$row['user_id']          = (int) $row['user_id'];
		$row['comment_approved'] = (string) $row['comment_approved'];
		$sabri_test_comments[ $id ] = (object) $row;
		return $id;
	}
}

if ( ! function_exists( 'get_comment' ) ) {
	function get_comment( $comment_id ) {
		global $sabri_test_comments;
		$comment_id = (int) $comment_id;
		return isset( $sabri_test_comments[ $comment_id ] ) ? clone $sabri_test_comments[ $comment_id ] : null;
	}
}

if ( ! function_exists( 'get_comments' ) ) {
	function get_comments( $args = array() ) {
		global $sabri_test_comments;
		$args = is_array( $args ) ? $args : array();
		$rows = array_values( $sabri_test_comments );
		$rows = array_values(
			array_filter(
				$rows,
				static function ( $comment ) use ( $args ) {
					if ( isset( $args['post_id'] ) && (int) $comment->comment_post_ID !== (int) $args['post_id'] ) {
						return false;
					}
					if ( isset( $args['type'] ) && (string) $comment->comment_type !== (string) $args['type'] ) {
						return false;
					}
					if ( isset( $args['user_id'] ) && (int) $comment->user_id !== (int) $args['user_id'] ) {
						return false;
					}
					if ( isset( $args['parent'] ) && (int) $comment->comment_parent !== (int) $args['parent'] ) {
						return false;
					}
					$status = isset( $args['status'] ) ? (string) $args['status'] : 'all';
					if ( 'approve' === $status && ! in_array( (string) $comment->comment_approved, array( '1', 'approve', 'approved' ), true ) ) {
						return false;
					}
					if ( 'hold' === $status && ! in_array( (string) $comment->comment_approved, array( '0', 'hold', 'unapproved' ), true ) ) {
						return false;
					}
					return true;
				}
			)
		);

		usort(
			$rows,
			static function ( $a, $b ) use ( $args ) {
				$left  = strtotime( (string) $a->comment_date_gmt . ' GMT' );
				$right = strtotime( (string) $b->comment_date_gmt . ' GMT' );
				$result = $left === $right ? (int) $a->comment_ID <=> (int) $b->comment_ID : $left <=> $right;
				return isset( $args['order'] ) && 'DESC' === strtoupper( (string) $args['order'] ) ? -$result : $result;
			}
		);
		return $rows;
	}
}

if ( ! function_exists( 'wp_update_comment' ) ) {
	function wp_update_comment( $commentarr, $wp_error = false ) {
		global $sabri_test_comments;
		$comment_id = isset( $commentarr['comment_ID'] ) ? (int) $commentarr['comment_ID'] : 0;
		if ( $comment_id <= 0 || empty( $sabri_test_comments[ $comment_id ] ) ) {
			return $wp_error ? new WP_Error( 'comment_not_found', 'Comment not found.' ) : 0;
		}
		foreach ( $commentarr as $key => $value ) {
			if ( 'comment_ID' === $key ) {
				continue;
			}
			$sabri_test_comments[ $comment_id ]->$key = $value;
		}
		return $comment_id;
	}
}

if ( ! function_exists( 'update_comment_meta' ) ) {
	function update_comment_meta( $comment_id, $key, $value ) {
		global $sabri_test_comment_meta;
		$comment_id = (int) $comment_id;
		$sabri_test_comment_meta[ $comment_id ][ (string) $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_comment_meta' ) ) {
	function get_comment_meta( $comment_id, $key = '', $single = false ) {
		global $sabri_test_comment_meta;
		$comment_id = (int) $comment_id;
		if ( '' === $key ) {
			return isset( $sabri_test_comment_meta[ $comment_id ] ) ? $sabri_test_comment_meta[ $comment_id ] : array();
		}
		if ( ! isset( $sabri_test_comment_meta[ $comment_id ][ $key ] ) ) {
			return $single ? '' : array();
		}
		return $single ? $sabri_test_comment_meta[ $comment_id ][ $key ] : array( $sabri_test_comment_meta[ $comment_id ][ $key ] );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		unset( $action );
		return 'rest-nonce';
	}
}

if ( ! function_exists( 'wp_login_url' ) ) {
	function wp_login_url( $redirect = '' ) {
		return 'http://example.test/login?redirect=' . rawurlencode( (string) $redirect );
	}
}
