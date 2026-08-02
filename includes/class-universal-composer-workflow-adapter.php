<?php
/**
 * Corrected native File 21 social-publication Workflow Adapter for File 22.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

use Sabri\UniversalComposer\Contracts\Diagnostic_Adapter;
use Sabri\UniversalComposer\Contracts\Workflow_Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File 22 orchestrates; File 21 remains the sole writer and permanent owner.
 */
class UniversalComposerWorkflowAdapter implements Workflow_Adapter, Diagnostic_Adapter {
	private const SCHEMA_VERSION               = '1.0.1';
	private const IDEMPOTENCY_PATTERN          = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}:[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
	private const SUPPORTED_PUBLICATION_ACTIONS = array( 'submit', 'publish', 'schedule' );
	private const INSTITUTIONAL_FEED_TYPES      = array( 'founder-update', 'platform-news' );
	private const SUPPORTED_FEED_TYPES          = array(
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

	public function api_version(): string {
		return UniversalComposerBridge::ADAPTER_API_VERSION;
	}

	public function workflow_api_version(): string {
		return UniversalComposerBridge::WORKFLOW_API_VERSION;
	}

	public function schema_version(): string {
		return self::SCHEMA_VERSION;
	}

	public function supports_native_drafts(): bool {
		return true;
	}

	public function key(): string {
		return UniversalComposerBridge::ADAPTER_KEY;
	}

	public function label(): string {
		return __( 'Social Post', 'sabri-complete-home-news-feed' );
	}

	public function description(): string {
		return __( 'Create an authorized Home Feed publication in the native File 21 Composer.', 'sabri-complete-home-news-feed' );
	}

	public function group(): string {
		return 'publishing';
	}

	public function icon(): string {
		return 'admin-post';
	}

	public function priority(): int {
		return 10;
	}

	public function native_module(): string {
		return SABRI_HNF_SLUG;
	}

	public function minimum_native_version(): string {
		return '1.0.3';
	}

	public function required_capability(): string {
		return 'sabri_feed_create_posts';
	}

	public function privacy_classification(): string {
		return 'public';
	}

	public function is_available(): bool {
		if ( ! defined( 'SABRI_HNF_VERSION' ) || version_compare( (string) SABRI_HNF_VERSION, $this->minimum_native_version(), '<' ) ) {
			return false;
		}
		foreach ( array( 'PublicComposerSurface', 'ComposerPermissions', 'ComposerValidation', 'Composer', 'PostMetadata', 'Settings', 'SafeMode', 'FeedContext', 'CanonicalIdentityAdapter', 'UniversalComposerWorkflowStore' ) as $class_name ) {
			if ( ! class_exists( __NAMESPACE__ . '\\' . $class_name ) ) {
				return false;
			}
		}
		$settings = Settings::get();
		return ! empty( $settings['composer']['public_composer_enabled'] )
			&& SafeMode::feature_enabled( 'composer' )
			&& '' !== $this->native_url();
	}

	public function can_create( int $user_id ): bool {
		return $user_id > 0 && $this->is_available() && ComposerPermissions::user_can_create( $user_id, Settings::get() );
	}

	public function start_url( int $user_id ): string {
		return $this->can_create( $user_id ) ? $this->native_url() : '';
	}

	/**
	 * Return a strict, capability-aware, data-free schema.
	 *
	 * @return array<string,mixed>
	 */
	public function schema(): array {
		$user_id = function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
		return array(
			'version' => self::SCHEMA_VERSION,
			'fields'  => array(
				'native_reference' => array( 'type' => 'opaque_reference', 'label_code' => 'native_reference', 'required' => false, 'privacy_class' => 'private' ),
				'title' => array( 'type' => 'text', 'label_code' => 'title', 'required' => false, 'privacy_class' => 'public' ),
				'content' => array( 'type' => 'textarea', 'label_code' => 'content', 'required' => true, 'privacy_class' => 'public' ),
				'feed_type' => array( 'type' => 'select', 'label_code' => 'feed_type', 'required' => true, 'privacy_class' => 'public', 'choices' => $this->feed_type_choices( $user_id ) ),
				'topic' => array( 'type' => 'text', 'label_code' => 'topic', 'required' => false, 'privacy_class' => 'public' ),
				'visibility' => array( 'type' => 'select', 'label_code' => 'visibility', 'required' => true, 'privacy_class' => 'public', 'choices' => $this->visibility_choices() ),
				'language' => array( 'type' => 'text', 'label_code' => 'language', 'required' => false, 'privacy_class' => 'public' ),
				'country_region' => array( 'type' => 'text', 'label_code' => 'country_region', 'required' => false, 'privacy_class' => 'public' ),
				'comments_enabled' => array( 'type' => 'checkbox', 'label_code' => 'comments_enabled', 'required' => false, 'privacy_class' => 'public' ),
				'medical_disclaimer_confirmed' => array( 'type' => 'checkbox', 'label_code' => 'medical_disclaimer_confirmed', 'required' => false, 'privacy_class' => 'private' ),
				'patient_privacy_confirmed' => array( 'type' => 'checkbox', 'label_code' => 'patient_privacy_confirmed', 'required' => false, 'privacy_class' => 'sensitive' ),
				'scheduled_date' => array( 'type' => 'datetime', 'label_code' => 'scheduled_date', 'required' => false, 'privacy_class' => 'private' ),
				'publication_action' => array(
					'type' => 'select',
					'label_code' => 'publication_action',
					'required' => true,
					'privacy_class' => 'private',
					'choices' => array( 'submit' => 'action_submit', 'publish' => 'action_publish', 'schedule' => 'action_schedule' ),
				),
			),
		);
	}

	/**
	 * Create or resume only an actual WordPress draft. Pending moderation records
	 * are immutable through the draft/preview pathway.
	 *
	 * @param string|null          $native_reference Existing draft reference.
	 * @param array<string,mixed> $payload File 22 payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function create_draft( int $user_id, ?string $native_reference, array $payload ) {
		if ( ! $this->can_create( $user_id ) ) {
			return $this->error( 'permission_denied' );
		}
		$post_id = 0;
		if ( null !== $native_reference && '' !== $native_reference ) {
			$post_id = UniversalComposerWorkflowStore::post_id_from_reference( $native_reference );
			if ( $post_id <= 0 ) {
				return $this->error( 'invalid_reference' );
			}
			if ( ! $this->user_can_manage_draft( $user_id, $post_id ) ) {
				return $this->error( 'conflict' );
			}
		}
		$input = $this->normalize_payload( $payload, 'draft', $post_id, $user_id );
		if ( $input instanceof \WP_Error ) {
			return $input;
		}
		$result = Composer::create_or_update_from_request( $input, array(), $user_id );
		if ( empty( $result['ok'] ) ) {
			return $this->result_error( $result );
		}
		$created_id = isset( $result['post_id'] ) ? (int) $result['post_id'] : 0;
		if ( $created_id <= 0 || 'draft' !== (string) ( $result['status'] ?? '' ) ) {
			return $this->error( 'temporarily_unavailable' );
		}
		return array( 'native_reference' => UniversalComposerWorkflowStore::native_reference( $created_id ), 'status' => 'draft' );
	}

	/**
	 * @param array<string,mixed> $payload File 22 payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function validate( int $user_id, array $payload ) {
		if ( ! $this->can_create( $user_id ) ) {
			return $this->error( 'permission_denied' );
		}
		$input = $this->normalize_payload( $payload, $this->publication_action( $payload ), 0, $user_id );
		if ( $input instanceof \WP_Error ) {
			return array( 'valid' => false, 'errors' => array( $input->get_error_code() ), 'warnings' => array() );
		}
		$validation = ComposerValidation::validate( $input, $user_id, Settings::get() );
		return array( 'valid' => ! empty( $validation['valid'] ), 'errors' => $this->validation_codes( $validation['errors'] ?? array() ), 'warnings' => array() );
	}

	/**
	 * Persist draft edits and return a signed URL whose expiry is enforced when
	 * opened. Pending-review and published posts are never demoted to draft.
	 *
	 * @param array<string,mixed> $payload File 22 payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function preview( int $user_id, array $payload ) {
		if ( ! $this->can_create( $user_id ) ) {
			return $this->error( 'permission_denied' );
		}
		$settings = Settings::get();
		if ( empty( $settings['composer']['previews_enabled'] ) ) {
			return $this->error( 'temporarily_unavailable' );
		}
		$reference = isset( $payload['native_reference'] ) && is_scalar( $payload['native_reference'] ) ? (string) $payload['native_reference'] : '';
		$post_id   = UniversalComposerWorkflowStore::post_id_from_reference( $reference );
		if ( $post_id <= 0 ) {
			return $this->error( 'invalid_reference' );
		}
		if ( ! $this->user_can_manage_draft( $user_id, $post_id ) ) {
			return $this->error( 'conflict' );
		}
		$input = $this->normalize_payload( $payload, 'draft', $post_id, $user_id );
		if ( $input instanceof \WP_Error ) {
			return $input;
		}
		$result = Composer::create_or_update_from_request( $input, array(), $user_id );
		if ( empty( $result['ok'] ) || 'draft' !== (string) ( $result['status'] ?? '' ) ) {
			return empty( $result['ok'] ) ? $this->result_error( $result ) : $this->error( 'temporarily_unavailable' );
		}
		$preview = UniversalComposerWorkflowStore::issue_preview_url( $post_id, $user_id );
		if ( empty( $preview['url'] ) || empty( $preview['expires_at'] ) ) {
			return $this->error( 'temporarily_unavailable' );
		}
		return array( 'preview_url' => (string) $preview['url'], 'expires_at' => (int) $preview['expires_at'] );
	}

	/**
	 * Idempotent final submission with native-marker recovery and bounded leases.
	 *
	 * @param array<string,mixed> $payload Final File 22 payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function submit( int $user_id, string $idempotency_key, array $payload ) {
		if ( ! $this->can_create( $user_id ) ) {
			return $this->error( 'permission_denied' );
		}
		if ( 1 !== preg_match( self::IDEMPOTENCY_PATTERN, $idempotency_key ) ) {
			return $this->error( 'invalid_reference' );
		}

		$requested_post_id = 0;
		if ( isset( $payload['native_reference'] ) && is_scalar( $payload['native_reference'] ) && '' !== (string) $payload['native_reference'] ) {
			$requested_post_id = UniversalComposerWorkflowStore::post_id_from_reference( (string) $payload['native_reference'] );
			if ( $requested_post_id <= 0 ) {
				return $this->error( 'invalid_reference' );
			}
			if ( ! $this->user_can_manage_draft( $user_id, $requested_post_id ) ) {
				return $this->error( 'conflict' );
			}
		}

		$action = $this->publication_action( $payload );
		$input  = $this->normalize_payload( $payload, $action, $requested_post_id, $user_id );
		if ( $input instanceof \WP_Error ) {
			return $input;
		}
		$validation = ComposerValidation::validate( $input, $user_id, Settings::get() );
		if ( empty( $validation['valid'] ) ) {
			return $this->error( 'validation_failed' );
		}

		$fingerprint = $this->payload_fingerprint( $input );
		$key_hash    = UniversalComposerWorkflowStore::key_hash( $idempotency_key );
		$option_key  = UniversalComposerWorkflowStore::option_key( $user_id, $key_hash );
		if ( '' === $fingerprint ) {
			return $this->error( 'temporarily_unavailable' );
		}

		$record  = UniversalComposerWorkflowStore::load_record( $option_key );
		$post_id = $requested_post_id;
		if ( is_array( $record ) ) {
			if ( empty( $record['fingerprint'] ) || ! hash_equals( (string) $record['fingerprint'], $fingerprint ) ) {
				return $this->error( 'conflict' );
			}
			$record_post_id = UniversalComposerWorkflowStore::post_id_from_reference( (string) ( $record['native_reference'] ?? '' ) );
			if ( $record_post_id <= 0 ) {
				$record_post_id = (int) UniversalComposerWorkflowStore::find_native_post( $user_id, $key_hash, $fingerprint );
			}
			if ( $post_id > 0 && $record_post_id > 0 && $post_id !== $record_post_id ) {
				return $this->error( 'conflict' );
			}
			$post_id = $record_post_id > 0 ? $record_post_id : $post_id;
			if ( $post_id > 0 ) {
				$current = function_exists( 'get_post_status' ) ? UniversalComposerWorkflowStore::normalize_status( (string) get_post_status( $post_id ) ) : '';
				if ( in_array( $current, array( 'pending_review', 'scheduled', 'published', 'rejected' ), true ) ) {
					UniversalComposerWorkflowStore::complete_record( $option_key, $record, $post_id, $current );
					return $this->status_envelope( $post_id, $current, $user_id );
				}
				if ( 'draft' !== $current || ! $this->user_can_manage_draft( $user_id, $post_id ) ) {
					return $this->error( 'temporarily_unavailable' );
				}
			} elseif ( ! UniversalComposerWorkflowStore::record_is_expired( $record ) ) {
				return $this->error( 'temporarily_unavailable' );
			} else {
				UniversalComposerWorkflowStore::delete_record( $option_key );
				$record = null;
			}
		}

		if ( ! is_array( $record ) ) {
			$marked_post_id = (int) UniversalComposerWorkflowStore::find_native_post( $user_id, $key_hash, $fingerprint );
			if ( $marked_post_id > 0 ) {
				if ( $post_id > 0 && $post_id !== $marked_post_id ) {
					return $this->error( 'conflict' );
				}
				$post_id = $marked_post_id;
				$current = function_exists( 'get_post_status' ) ? UniversalComposerWorkflowStore::normalize_status( (string) get_post_status( $post_id ) ) : '';
				if ( in_array( $current, array( 'pending_review', 'scheduled', 'published', 'rejected' ), true ) ) {
					if ( ! UniversalComposerWorkflowStore::acquire_record( $option_key, $user_id, $key_hash, $fingerprint ) ) {
						return $this->error( 'temporarily_unavailable' );
					}
					$record = UniversalComposerWorkflowStore::load_record( $option_key );
					UniversalComposerWorkflowStore::complete_record( $option_key, is_array( $record ) ? $record : array(), $post_id, $current );
					return $this->status_envelope( $post_id, $current, $user_id );
				}
				if ( 'draft' !== $current || ! $this->user_can_manage_draft( $user_id, $post_id ) ) {
					return $this->error( 'temporarily_unavailable' );
				}
			}
			if ( ! UniversalComposerWorkflowStore::acquire_record( $option_key, $user_id, $key_hash, $fingerprint ) ) {
				$record = UniversalComposerWorkflowStore::load_record( $option_key );
				return is_array( $record ) ? $this->error( 'temporarily_unavailable' ) : $this->error( 'conflict' );
			}
			$record = UniversalComposerWorkflowStore::load_record( $option_key );
		}

		if ( ! is_array( $record ) ) {
			return $this->error( 'temporarily_unavailable' );
		}
		$was_new = $post_id <= 0;
		if ( $post_id > 0 ) {
			if ( ! UniversalComposerWorkflowStore::attach_native_marker( $post_id, $user_id, $key_hash, $fingerprint ) || ! UniversalComposerWorkflowStore::persist_processing_reference( $option_key, $record, $post_id ) ) {
				return $this->error( 'temporarily_unavailable' );
			}
			$record = UniversalComposerWorkflowStore::load_record( $option_key );
			$input['post_id'] = $post_id;
		}

		$result = Composer::create_or_update_from_request( $input, array(), $user_id );
		if ( empty( $result['ok'] ) ) {
			UniversalComposerWorkflowStore::delete_record( $option_key );
			if ( $post_id > 0 ) {
				UniversalComposerWorkflowStore::remove_native_marker( $post_id, $key_hash, $fingerprint );
			}
			return $this->result_error( $result );
		}

		$created_id = isset( $result['post_id'] ) ? (int) $result['post_id'] : 0;
		$status     = UniversalComposerWorkflowStore::normalize_status( (string) ( $result['status'] ?? '' ) );
		if ( $created_id <= 0 || '' === $status ) {
			return $this->error( 'temporarily_unavailable' );
		}
		if ( ! UniversalComposerWorkflowStore::attach_native_marker( $created_id, $user_id, $key_hash, $fingerprint ) ) {
			if ( $was_new && function_exists( 'wp_delete_post' ) ) {
				wp_delete_post( $created_id, true );
			}
			UniversalComposerWorkflowStore::delete_record( $option_key );
			return $this->error( 'temporarily_unavailable' );
		}
		$record = UniversalComposerWorkflowStore::load_record( $option_key );
		if ( ! is_array( $record ) || ! UniversalComposerWorkflowStore::persist_processing_reference( $option_key, $record, $created_id ) ) {
			return $this->error( 'temporarily_unavailable' );
		}
		$record = UniversalComposerWorkflowStore::load_record( $option_key );
		if ( ! is_array( $record ) || ! UniversalComposerWorkflowStore::complete_record( $option_key, $record, $created_id, $status ) ) {
			return $this->error( 'temporarily_unavailable' );
		}
		return $this->status_envelope( $created_id, $status, $user_id );
	}

	/** @return array<string,mixed>|\WP_Error */
	public function status( int $user_id, string $native_reference ) {
		if ( ! $this->can_create( $user_id ) ) {
			return $this->error( 'permission_denied' );
		}
		$post_id = UniversalComposerWorkflowStore::post_id_from_reference( $native_reference );
		if ( $post_id <= 0 ) {
			return $this->error( 'invalid_reference' );
		}
		if ( ! $this->user_can_manage_reference( $user_id, $post_id ) ) {
			return $this->error( 'permission_denied' );
		}
		$status = function_exists( 'get_post_status' ) ? UniversalComposerWorkflowStore::normalize_status( (string) get_post_status( $post_id ) ) : '';
		return '' !== $status ? $this->status_envelope( $post_id, $status, $user_id ) : $this->error( 'not_found' );
	}

	public function canonical_url( int $user_id, string $native_reference ): string {
		$post_id = UniversalComposerWorkflowStore::post_id_from_reference( $native_reference );
		if ( ! $this->can_create( $user_id ) || $post_id <= 0 || ! $this->is_native_post( $post_id ) ) {
			return '';
		}
		if ( 'publish' !== (string) get_post_status( $post_id ) || ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
			return '';
		}
		return function_exists( 'get_permalink' ) ? (string) get_permalink( $post_id ) : '';
	}

	/** @return array<string,mixed> */
	public function health_report(): array {
		$settings  = Settings::get();
		$available = $this->is_available();
		return array(
			'status' => $available ? 'pass' : 'warning',
			'codes' => $available ? array() : array( 'native_unavailable' ),
			'adapter_key' => $this->key(),
			'native_module' => $this->native_module(),
			'actual_native_version' => defined( 'SABRI_HNF_VERSION' ) ? (string) SABRI_HNF_VERSION : '',
			'minimum_native_version' => $this->minimum_native_version(),
			'required_capability' => $this->required_capability(),
			'privacy_classification' => $this->privacy_classification(),
			'workflow_api_version' => $this->workflow_api_version(),
			'schema_version' => $this->schema_version(),
			'supports_native_drafts' => true,
			'preview_expiry_enforced' => true,
			'idempotency_recovery_ready' => function_exists( 'add_option' ) && function_exists( 'update_option' ) && function_exists( 'get_option' ) && function_exists( 'delete_option' ) && function_exists( 'get_post_meta' ) && function_exists( 'update_post_meta' ) && function_exists( 'get_posts' ),
			'idempotency_retention_days' => 30,
			'composer_setting_enabled' => ! empty( $settings['composer']['public_composer_enabled'] ),
			'composer_feature_enabled' => SafeMode::feature_enabled( 'composer' ),
			'native_route_available' => '' !== $this->native_url(),
			'available' => $available,
		);
	}

	/** @return array<string,string> */
	private function feed_type_choices( int $user_id ): array {
		$settings = Settings::get();
		$allowed  = isset( $settings['composer']['allowed_feed_types'] ) && is_array( $settings['composer']['allowed_feed_types'] ) ? array_map( 'sanitize_key', $settings['composer']['allowed_feed_types'] ) : array();
		$choices  = array();
		foreach ( self::SUPPORTED_FEED_TYPES as $slug ) {
			if ( ! in_array( $slug, $allowed, true ) || ( in_array( $slug, self::INSTITUTIONAL_FEED_TYPES, true ) && ! $this->user_can_publish_institutional_type( $user_id ) ) ) {
				continue;
			}
			$key = str_replace( '-', '_', $slug );
			$choices[ $key ] = 'feed_type_' . $key;
		}
		return $choices;
	}

	/** @return array<string,string> */
	private function visibility_choices(): array {
		$choices = array();
		foreach ( (array) FeedContext::allowed_composer_visibility( Settings::get(), true ) as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' !== $slug && 1 === preg_match( '/^[a-z][a-z0-9_]{0,63}$/', $slug ) ) {
				$choices[ $slug ] = 'visibility_' . $slug;
			}
		}
		return $choices;
	}

	/** @param array<string,mixed> $payload @return array<string,mixed>|\WP_Error */
	private function normalize_payload( array $payload, string $action, int $post_id, int $user_id ) {
		$feed_key  = isset( $payload['feed_type'] ) && is_scalar( $payload['feed_type'] ) ? sanitize_key( (string) $payload['feed_type'] ) : 'standard_post';
		$feed_type = str_replace( '_', '-', $feed_key );
		if ( ! in_array( $feed_type, self::SUPPORTED_FEED_TYPES, true ) || ! isset( $this->feed_type_choices( $user_id )[ $feed_key ] ) ) {
			return $this->error( 'validation_failed' );
		}
		$visibility = isset( $payload['visibility'] ) && is_scalar( $payload['visibility'] ) ? sanitize_key( (string) $payload['visibility'] ) : 'public';
		if ( ! isset( $this->visibility_choices()[ $visibility ] ) || ! in_array( $action, array_merge( array( 'draft' ), self::SUPPORTED_PUBLICATION_ACTIONS ), true ) ) {
			return $this->error( 'validation_failed' );
		}
		return array(
			'post_id' => $post_id,
			'composer_action' => $action,
			'title' => $this->scalar( $payload, 'title' ),
			'content' => $this->scalar( $payload, 'content' ),
			'feed_type' => $feed_type,
			'topic' => $this->scalar( $payload, 'topic' ),
			'visibility' => $visibility,
			'language' => $this->scalar( $payload, 'language' ),
			'country_region' => $this->scalar( $payload, 'country_region' ),
			'comments_enabled' => $this->boolean( $payload, 'comments_enabled' ),
			'medical_disclaimer_confirmed' => $this->boolean( $payload, 'medical_disclaimer_confirmed' ),
			'patient_privacy_confirmed' => $this->boolean( $payload, 'patient_privacy_confirmed' ),
			'scheduled_date' => $this->scalar( $payload, 'scheduled_date' ),
			'attachments' => array(),
			'gallery' => array(),
			'clinical_case' => array(),
			'research' => array(),
		);
	}

	/** @param array<string,mixed> $payload */
	private function publication_action( array $payload ): string {
		$action = isset( $payload['publication_action'] ) && is_scalar( $payload['publication_action'] ) ? sanitize_key( (string) $payload['publication_action'] ) : 'submit';
		return in_array( $action, self::SUPPORTED_PUBLICATION_ACTIONS, true ) ? $action : 'invalid';
	}

	/** @param array<string,mixed> $payload */
	private function scalar( array $payload, string $key ): string {
		return isset( $payload[ $key ] ) && is_scalar( $payload[ $key ] ) ? (string) $payload[ $key ] : '';
	}

	/** @param array<string,mixed> $payload */
	private function boolean( array $payload, string $key ): bool {
		$value = $payload[ $key ] ?? false;
		return true === $value || 1 === $value || '1' === $value || 'true' === $value || 'yes' === $value || 'on' === $value;
	}

	/** @param mixed $errors @return array<int,string> */
	private function validation_codes( $errors ): array {
		$codes = array();
		foreach ( is_array( $errors ) ? $errors : array() as $error ) {
			$code = is_array( $error ) && isset( $error['code'] ) ? sanitize_key( (string) $error['code'] ) : '';
			if ( '' !== $code ) {
				$codes[] = $code;
			}
		}
		return array_values( array_unique( $codes ) );
	}

	/** @param array<string,mixed> $result */
	private function result_error( array $result ): \WP_Error {
		$code = isset( $result['code'] ) ? sanitize_key( (string) $result['code'] ) : '';
		$map  = array(
			'composer_denied' => 'permission_denied',
			'edit_denied' => 'permission_denied',
			'publish_denied' => 'permission_denied',
			'submit_denied' => 'permission_denied',
			'schedule_denied' => 'permission_denied',
			'validation_failed' => 'validation_failed',
			'rate_limited' => 'rate_limited',
			'save_failed' => 'temporarily_unavailable',
			'drafts_disabled' => 'temporarily_unavailable',
		);
		return $this->error( $map[ $code ] ?? 'temporarily_unavailable' );
	}

	/** @param array<string,mixed> $input */
	private function payload_fingerprint( array $input ): string {
		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $input ) : json_encode( $input );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
	}

	/** @return array<string,mixed> */
	private function status_envelope( int $post_id, string $status, int $user_id ): array {
		$envelope = array( 'native_reference' => UniversalComposerWorkflowStore::native_reference( $post_id ), 'status' => $status );
		if ( 'published' === $status && PostMetadata::user_can_view( $post_id, $user_id ) && function_exists( 'get_permalink' ) ) {
			$url = (string) get_permalink( $post_id );
			if ( '' !== $url ) {
				$envelope['canonical_url'] = $url;
			}
		}
		return $envelope;
	}

	private function user_can_manage_reference( int $user_id, int $post_id ): bool {
		return $this->is_native_post( $post_id ) && ComposerPermissions::user_can_edit_post( $post_id, $user_id );
	}

	private function user_can_manage_draft( int $user_id, int $post_id ): bool {
		return $this->user_can_manage_reference( $user_id, $post_id ) && function_exists( 'get_post_status' ) && 'draft' === (string) get_post_status( $post_id );
	}

	private function user_can_publish_institutional_type( int $user_id ): bool {
		return $user_id > 0
			&& CanonicalIdentityAdapter::current_action_ready( $user_id )
			&& ( CanonicalIdentityAdapter::is_founder( $user_id ) || CanonicalIdentityAdapter::is_administrator( $user_id ) );
	}

	private function is_native_post( int $post_id ): bool {
		return $post_id > 0 && function_exists( 'get_post_type' ) && 'post' === get_post_type( $post_id );
	}

	private function error( string $code ): \WP_Error {
		return new \WP_Error( $code, __( 'The native social publication workflow could not complete.', 'sabri-complete-home-news-feed' ) );
	}

	private function native_url(): string {
		return function_exists( 'home_url' ) ? home_url( '/create-post/' ) : '/create-post/';
	}
}
