<?php
/**
 * First-class File 22 Poll adapter backed by File 21.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UniversalComposerPollAdapter extends UniversalComposerStructuredWorkflowAdapter {
	protected function adapter_key(): string { return 'poll'; }
	protected function feed_type(): string { return 'poll'; }
	protected function display_label(): string { return __( 'Poll', 'sabri-complete-home-news-feed' ); }
	protected function display_description(): string { return __( 'Create a moderated File 21 poll with bounded options and result visibility.', 'sabri-complete-home-news-feed' ); }
	protected function adapter_priority(): int { return 40; }
	protected function adapter_icon(): string { return 'chart-bar'; }

	protected function special_fields(): array {
		$fields = array(
			'poll_question' => array( 'type' => 'text', 'label_code' => 'poll_question', 'required' => true, 'privacy_class' => 'public' ),
		);
		for ( $i = 1; $i <= PollPolicy::MAX_OPTIONS; $i++ ) {
			$fields['poll_option_' . $i] = array(
				'type' => 'text',
				'label_code' => 'poll_option_' . $i,
				'required' => $i <= PollPolicy::MIN_OPTIONS,
				'privacy_class' => 'public',
			);
		}
		$fields['poll_results_policy'] = array(
			'type' => 'select',
			'label_code' => 'poll_results_policy',
			'required' => true,
			'privacy_class' => 'public',
			'choices' => array( 'after_vote' => 'poll_results_after_vote', 'after_close' => 'poll_results_after_close', 'always' => 'poll_results_always' ),
		);
		$fields['poll_closes_at'] = array( 'type' => 'datetime', 'label_code' => 'poll_closes_at', 'required' => false, 'privacy_class' => 'public' );
		$fields['poll_allow_change'] = array( 'type' => 'checkbox', 'label_code' => 'poll_allow_change', 'required' => false, 'privacy_class' => 'public' );
		return $fields;
	}

	protected function inflate_special_fields( array $payload ): array {
		$options = array();
		for ( $i = 1; $i <= PollPolicy::MAX_OPTIONS; $i++ ) {
			$key = 'poll_option_' . $i;
			$value = isset( $payload[ $key ] ) && is_scalar( $payload[ $key ] ) ? trim( (string) $payload[ $key ] ) : '';
			if ( '' !== $value ) {
				$options[] = $value;
			}
			unset( $payload[ $key ] );
		}
		$payload['poll'] = array(
			'question' => isset( $payload['poll_question'] ) && is_scalar( $payload['poll_question'] ) ? (string) $payload['poll_question'] : '',
			'options' => $options,
			'results_policy' => isset( $payload['poll_results_policy'] ) && is_scalar( $payload['poll_results_policy'] ) ? sanitize_key( (string) $payload['poll_results_policy'] ) : 'after_vote',
			'closes_at' => isset( $payload['poll_closes_at'] ) && is_scalar( $payload['poll_closes_at'] ) ? (string) $payload['poll_closes_at'] : '',
			'allow_change' => ! empty( $payload['poll_allow_change'] ),
		);
		unset( $payload['poll_question'], $payload['poll_results_policy'], $payload['poll_closes_at'], $payload['poll_allow_change'] );
		return $payload;
	}
}
