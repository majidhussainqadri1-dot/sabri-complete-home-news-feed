<?php
/**
 * Backward-compatible and concurrency-hardened File 22 publication adapter.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preserves the registered class name and requires final submission to update
 * an already-known File 21 draft. This removes the post-create/pre-marker crash
 * window that would otherwise make durable native reconciliation impossible.
 */
final class UniversalComposerPublicationAdapter extends UniversalComposerWorkflowAdapter {
	private const IDEMPOTENCY_PATTERN      = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}:[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
	private const EXECUTION_LOCK_PREFIX    = 'sabri_hnf_file22_exec_';
	private const EXECUTION_LOCK_TTL       = 120;
	private const SUPPORTED_ACTIONS        = array( 'submit', 'publish', 'schedule' );
	private const INSTITUTIONAL_FEED_TYPES = array( 'founder-update', 'platform-news' );
	private const SUPPORTED_FEED_TYPES     = array(
		'standard-post',
		'founder-update',
		'classical-homeopathy',
		'homeopathy-education',
		'materia-medica',
		'repertory',
		'clinical-education',
		'nutrition',
		'public-health-education',
		'platform-news',
		'pathology',
		'anatomy',
		'principles-of-hygiene',
		'islamic-spiritual-healing',
		'homeopathy-philosophy',
		'event',
		'clinic-announcement',
	);

	/**
	 * Final submission is idempotent only after File 22 has obtained an opaque
	 * draft reference from `create_draft()`.
	 *
	 * @param array<string,mixed> $payload Final File 22 payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function submit( int $user_id, string $idempotency_key, array $payload ) {
		if ( ! $this->can_create( $user_id ) ) {
			return $this->error_result( 'permission_denied' );
		}
		if ( 1 !== preg_match( self::IDEMPOTENCY_PATTERN, $idempotency_key ) ) {
			return $this->error_result( 'invalid_reference' );
		}

		$reference = isset( $payload['native_reference'] ) && is_scalar( $payload['native_reference'] ) ? (string) $payload['native_reference'] : '';
		$post_id   = UniversalComposerWorkflowStore::post_id_from_reference( $reference );
		if ( $post_id <= 0 ) {
			return $this->error_result( 'invalid_reference' );
		}
		/* A final replay may no longer be a draft, but it must remain manageable. */
		if ( ! $this->can_manage_reference( $user_id, $post_id ) ) {
			return $this->error_result( 'conflict' );
		}

		$action = $this->requested_action( $payload );
		$input  = $this->normalized_input( $payload, $action, $post_id, $user_id );
		if ( $input instanceof \WP_Error ) {
			return $input;
		}
		$validation = ComposerValidation::validate( $input, $user_id, Settings::get() );
		if ( empty( $validation['valid'] ) ) {
			return $this->error_result( 'validation_failed' );
		}

		$fingerprint = $this->fingerprint( $input );
		$key_hash    = UniversalComposerWorkflowStore::key_hash( $idempotency_key );
		$option_key  = UniversalComposerWorkflowStore::option_key( $user_id, $key_hash );
		if ( '' === $fingerprint ) {
			return $this->error_result( 'temporarily_unavailable' );
		}

		$record          = UniversalComposerWorkflowStore::load_record( $option_key );
		$record_acquired = false;
		if ( is_array( $record ) ) {
			$existing = $this->existing_record_result( $record, $option_key, $user_id, $post_id, $key_hash, $fingerprint );
			if ( $existing instanceof \WP_Error || is_array( $existing ) ) {
				return $existing;
			}
		} else {
			$marked_post_id = (int) UniversalComposerWorkflowStore::find_native_post( $user_id, $key_hash, $fingerprint );
			if ( $marked_post_id > 0 && $marked_post_id !== $post_id ) {
				return $this->error_result( 'conflict' );
			}
			/* A new idempotency key may transition only a mutable draft. */
			if ( $marked_post_id <= 0 && ! $this->can_mutate_draft( $user_id, $post_id ) ) {
				return $this->error_result( 'conflict' );
			}
			if ( ! UniversalComposerWorkflowStore::acquire_record( $option_key, $user_id, $key_hash, $fingerprint ) ) {
				return $this->error_result( 'temporarily_unavailable' );
			}
			$record          = UniversalComposerWorkflowStore::load_record( $option_key );
			$record_acquired = true;
		}

		if ( ! is_array( $record ) ) {
			return $this->error_result( 'temporarily_unavailable' );
		}
		$state = sanitize_key( (string) ( $record['state'] ?? '' ) );
		if ( ! $record_acquired && 'processing' === $state && ! UniversalComposerWorkflowStore::record_is_expired( $record ) ) {
			return $this->error_result( 'temporarily_unavailable' );
		}
		if ( 'completed' === $state ) {
			return $this->error_result( 'temporarily_unavailable' );
		}

		$lock_key = self::EXECUTION_LOCK_PREFIX . $user_id . '_' . $key_hash;
		$token    = $this->acquire_execution_lock( $lock_key );
		if ( '' === $token ) {
			return $this->error_result( 'temporarily_unavailable' );
		}

		try {
			$record = UniversalComposerWorkflowStore::load_record( $option_key );
			if ( ! is_array( $record ) || empty( $record['fingerprint'] ) || ! hash_equals( (string) $record['fingerprint'], $fingerprint ) ) {
				return $this->error_result( 'conflict' );
			}
			$record_post_id = UniversalComposerWorkflowStore::post_id_from_reference( (string) ( $record['native_reference'] ?? '' ) );
			if ( $record_post_id <= 0 ) {
				$record_post_id = (int) UniversalComposerWorkflowStore::find_native_post( $user_id, $key_hash, $fingerprint );
			}
			if ( $record_post_id > 0 && $record_post_id !== $post_id ) {
				return $this->error_result( 'conflict' );
			}

			$current = $this->current_status( $post_id );
			if ( $this->is_final_status( $current ) ) {
				if ( ! UniversalComposerWorkflowStore::complete_record( $option_key, $record, $post_id, $current ) ) {
					return $this->error_result( 'temporarily_unavailable' );
				}
				return $this->result_envelope( $post_id, $current, $user_id );
			}
			if ( 'draft' !== $current || ! $this->can_mutate_draft( $user_id, $post_id ) ) {
				return $this->error_result( 'temporarily_unavailable' );
			}

			/* Recovery identity is durable before the native status mutation. */
			if ( ! UniversalComposerWorkflowStore::attach_native_marker( $post_id, $user_id, $key_hash, $fingerprint ) || ! UniversalComposerWorkflowStore::persist_processing_reference( $option_key, $record, $post_id ) ) {
				return $this->error_result( 'temporarily_unavailable' );
			}
			$record           = UniversalComposerWorkflowStore::load_record( $option_key );
			$input['post_id'] = $post_id;
			if ( ! is_array( $record ) ) {
				return $this->error_result( 'temporarily_unavailable' );
			}

			$result = Composer::create_or_update_from_request( $input, array(), $user_id );
			if ( empty( $result['ok'] ) ) {
				UniversalComposerWorkflowStore::delete_record( $option_key );
				UniversalComposerWorkflowStore::remove_native_marker( $post_id, $key_hash, $fingerprint );
				return $this->native_error( $result );
			}

			$created_id = isset( $result['post_id'] ) ? (int) $result['post_id'] : 0;
			$status     = UniversalComposerWorkflowStore::normalize_status( (string) ( $result['status'] ?? '' ) );
			if ( $created_id !== $post_id || ! $this->is_final_status( $status ) ) {
				return $this->error_result( 'temporarily_unavailable' );
			}
			$record = UniversalComposerWorkflowStore::load_record( $option_key );
			if ( ! is_array( $record ) || ! UniversalComposerWorkflowStore::complete_record( $option_key, $record, $post_id, $status ) ) {
				return $this->error_result( 'temporarily_unavailable' );
			}
			return $this->result_envelope( $post_id, $status, $user_id );
		} finally {
			$this->release_execution_lock( $lock_key, $token );
		}
	}

	/**
	 * @param array<string,mixed> $record Existing option record.
	 * @return true|array<string,mixed>|\WP_Error
	 */
	private function existing_record_result( array $record, string $option_key, int $user_id, int $post_id, string $key_hash, string $fingerprint ) {
		if ( empty( $record['fingerprint'] ) || ! hash_equals( (string) $record['fingerprint'], $fingerprint ) ) {
			return $this->error_result( 'conflict' );
		}
		$record_post_id = UniversalComposerWorkflowStore::post_id_from_reference( (string) ( $record['native_reference'] ?? '' ) );
		if ( $record_post_id <= 0 ) {
			$record_post_id = (int) UniversalComposerWorkflowStore::find_native_post( $user_id, $key_hash, $fingerprint );
		}
		if ( $record_post_id > 0 && $record_post_id !== $post_id ) {
			return $this->error_result( 'conflict' );
		}
		$current = $this->current_status( $post_id );
		if ( $this->is_final_status( $current ) ) {
			if ( ! UniversalComposerWorkflowStore::complete_record( $option_key, $record, $post_id, $current ) ) {
				return $this->error_result( 'temporarily_unavailable' );
			}
			return $this->result_envelope( $post_id, $current, $user_id );
		}
		if ( 'draft' !== $current || ! $this->can_mutate_draft( $user_id, $post_id ) ) {
			return $this->error_result( 'temporarily_unavailable' );
		}
		return true;
	}

	/** Acquire a short atomic execution lease; reclaim only an expired lease. */
	private function acquire_execution_lock( string $lock_key ): string {
		if ( ! function_exists( 'add_option' ) || ! function_exists( 'get_option' ) || ! function_exists( 'delete_option' ) || ! function_exists( 'wp_generate_uuid4' ) ) {
			return '';
		}
		$token = wp_generate_uuid4();
		$value = array( 'token' => $token, 'expires_at' => time() + self::EXECUTION_LOCK_TTL );
		if ( add_option( $lock_key, $value, '', false ) ) {
			return $token;
		}
		$existing = get_option( $lock_key, null );
		if ( ! is_array( $existing ) || (int) ( $existing['expires_at'] ?? 0 ) > time() ) {
			return '';
		}
		delete_option( $lock_key );
		return add_option( $lock_key, $value, '', false ) ? $token : '';
	}

	/** Release only the lease owned by this request. */
	private function release_execution_lock( string $lock_key, string $token ): void {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'delete_option' ) ) {
			return;
		}
		$existing = get_option( $lock_key, null );
		if ( is_array( $existing ) && isset( $existing['token'] ) && hash_equals( (string) $existing['token'], $token ) ) {
			delete_option( $lock_key );
		}
	}

	/** @param array<string,mixed> $payload */
	private function requested_action( array $payload ): string {
		$action = isset( $payload['publication_action'] ) && is_scalar( $payload['publication_action'] ) ? sanitize_key( (string) $payload['publication_action'] ) : 'submit';
		return in_array( $action, self::SUPPORTED_ACTIONS, true ) ? $action : 'invalid';
	}

	/** @param array<string,mixed> $payload @return array<string,mixed>|\WP_Error */
	private function normalized_input( array $payload, string $action, int $post_id, int $user_id ) {
		$feed_key  = isset( $payload['feed_type'] ) && is_scalar( $payload['feed_type'] ) ? sanitize_key( (string) $payload['feed_type'] ) : 'standard_post';
		$feed_type = str_replace( '_', '-', $feed_key );
		if ( ! in_array( $feed_type, self::SUPPORTED_FEED_TYPES, true ) || ! isset( $this->allowed_feed_choices( $user_id )[ $feed_key ] ) ) {
			return $this->error_result( 'validation_failed' );
		}
		$visibility = isset( $payload['visibility'] ) && is_scalar( $payload['visibility'] ) ? sanitize_key( (string) $payload['visibility'] ) : 'public';
		if ( ! isset( $this->allowed_visibility_choices()[ $visibility ] ) || ! in_array( $action, self::SUPPORTED_ACTIONS, true ) ) {
			return $this->error_result( 'validation_failed' );
		}
		return array(
			'post_id' => $post_id,
			'composer_action' => $action,
			'title' => $this->scalar_value( $payload, 'title' ),
			'content' => $this->scalar_value( $payload, 'content' ),
			'feed_type' => $feed_type,
			'topic' => $this->scalar_value( $payload, 'topic' ),
			'visibility' => $visibility,
			'language' => $this->scalar_value( $payload, 'language' ),
			'country_region' => $this->scalar_value( $payload, 'country_region' ),
			'comments_enabled' => $this->boolean_value( $payload, 'comments_enabled' ),
			'medical_disclaimer_confirmed' => $this->boolean_value( $payload, 'medical_disclaimer_confirmed' ),
			'patient_privacy_confirmed' => $this->boolean_value( $payload, 'patient_privacy_confirmed' ),
			'scheduled_date' => $this->scalar_value( $payload, 'scheduled_date' ),
			'attachments' => array(),
			'gallery' => array(),
			'clinical_case' => array(),
			'research' => array(),
		);
	}

	/** @return array<string,string> */
	private function allowed_feed_choices( int $user_id ): array {
		$settings = Settings::get();
		$allowed  = isset( $settings['composer']['allowed_feed_types'] ) && is_array( $settings['composer']['allowed_feed_types'] ) ? array_map( 'sanitize_key', $settings['composer']['allowed_feed_types'] ) : array();
		$choices  = array();
		foreach ( self::SUPPORTED_FEED_TYPES as $slug ) {
			if ( ! in_array( $slug, $allowed, true ) || ( in_array( $slug, self::INSTITUTIONAL_FEED_TYPES, true ) && ! $this->institutional_user( $user_id ) ) ) {
				continue;
			}
			$key = str_replace( '-', '_', $slug );
			$choices[ $key ] = 'feed_type_' . $key;
		}
		return $choices;
	}

	/** @return array<string,string> */
	private function allowed_visibility_choices(): array {
		$choices = array();
		foreach ( (array) FeedContext::allowed_composer_visibility( Settings::get(), true ) as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' !== $slug ) {
				$choices[ $slug ] = 'visibility_' . $slug;
			}
		}
		return $choices;
	}

	private function can_manage_reference( int $user_id, int $post_id ): bool {
		return $post_id > 0 && function_exists( 'get_post_type' ) && 'post' === get_post_type( $post_id ) && ComposerPermissions::user_can_edit_post( $post_id, $user_id );
	}

	private function can_mutate_draft( int $user_id, int $post_id ): bool {
		return $this->can_manage_reference( $user_id, $post_id ) && function_exists( 'get_post_status' ) && 'draft' === get_post_status( $post_id );
	}

	private function institutional_user( int $user_id ): bool {
		return $user_id > 0 && ( CanonicalIdentityAdapter::is_founder( $user_id ) || CanonicalIdentityAdapter::is_administrator( $user_id ) );
	}

	private function current_status( int $post_id ): string {
		return function_exists( 'get_post_status' ) ? UniversalComposerWorkflowStore::normalize_status( (string) get_post_status( $post_id ) ) : '';
	}

	private function is_final_status( string $status ): bool {
		return in_array( $status, array( 'pending_review', 'scheduled', 'published', 'rejected' ), true );
	}

	/** @param array<string,mixed> $input */
	private function fingerprint( array $input ): string {
		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $input ) : json_encode( $input );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
	}

	/** @return array<string,mixed> */
	private function result_envelope( int $post_id, string $status, int $user_id ): array {
		$result = array( 'native_reference' => UniversalComposerWorkflowStore::native_reference( $post_id ), 'status' => $status );
		if ( 'published' === $status && PostMetadata::user_can_view( $post_id, $user_id ) && function_exists( 'get_permalink' ) ) {
			$url = (string) get_permalink( $post_id );
			if ( '' !== $url ) {
				$result['canonical_url'] = $url;
			}
		}
		return $result;
	}

	/** @param array<string,mixed> $result */
	private function native_error( array $result ): \WP_Error {
		$code = isset( $result['code'] ) ? sanitize_key( (string) $result['code'] ) : '';
		$map  = array( 'composer_denied' => 'permission_denied', 'edit_denied' => 'permission_denied', 'publish_denied' => 'permission_denied', 'submit_denied' => 'permission_denied', 'schedule_denied' => 'permission_denied', 'validation_failed' => 'validation_failed', 'rate_limited' => 'rate_limited' );
		return $this->error_result( $map[ $code ] ?? 'temporarily_unavailable' );
	}

	/** @param array<string,mixed> $payload */
	private function scalar_value( array $payload, string $key ): string {
		return isset( $payload[ $key ] ) && is_scalar( $payload[ $key ] ) ? (string) $payload[ $key ] : '';
	}

	/** @param array<string,mixed> $payload */
	private function boolean_value( array $payload, string $key ): bool {
		$value = $payload[ $key ] ?? false;
		return true === $value || 1 === $value || '1' === $value || 'true' === $value || 'yes' === $value || 'on' === $value;
	}

	private function error_result( string $code ): \WP_Error {
		return new \WP_Error( $code, __( 'The native social publication workflow could not complete.', 'sabri-complete-home-news-feed' ) );
	}
}
