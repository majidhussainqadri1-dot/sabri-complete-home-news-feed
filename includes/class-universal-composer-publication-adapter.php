<?php
/**
 * Native File 21 social-publication adapter for File 22.
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
 * Exposes File 21's public Composer as both a route and a guarded native
 * workflow. Permanent records, drafts, validation, moderation, media, and
 * publication states remain owned by File 21; File 22 receives no duplicate
 * content copy.
 */
final class UniversalComposerPublicationAdapter implements Workflow_Adapter, Diagnostic_Adapter {
	private const SCHEMA_VERSION              = '1.0.0';
	private const PREVIEW_TTL                 = 600;
	private const IDEMPOTENCY_PREFIX          = 'sabri_hnf_file22_idem_';
	private const IDEMPOTENCY_PATTERN         = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}:[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
	private const NATIVE_REFERENCE_PATTERN    = '/^post-([1-9][0-9]*)$/';
	private const SUPPORTED_PUBLICATION_ACTIONS = array( 'submit', 'publish', 'schedule' );
	private const SUPPORTED_FEED_TYPES        = array(
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

		foreach ( array( 'PublicComposerSurface', 'ComposerPermissions', 'ComposerValidation', 'Composer', 'PostMetadata' ) as $class_name ) {
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
		return $user_id > 0
			&& $this->is_available()
			&& ComposerPermissions::user_can_create( $user_id, Settings::get() );
	}

	public function start_url( int $user_id ): string {
		return $this->can_create( $user_id ) ? $this->native_url() : '';
	}

	/**
	 * File 22 strict, data-free schema. Structured Clinical Case, Research, Poll,
	 * upload, Video, and PDF workflows remain on their native owner routes.
	 *
	 * @return array<string,mixed>
	 */
	public function schema(): array {
		return array(
			'version' => self::SCHEMA_VERSION,
			'fields'  => array(
				'native_reference' => array(
					'type'          => 'opaque_reference',
					'label_code'    => 'native_reference',
					'required'      => false,
					'privacy_class' => 'private',
				),
				'title' => array(
					'type'          => 'text',
					'label_code'    => 'title',
					'required'      => false,
					'privacy_class' => 'public',
				),
				'content' => array(
					'type'          => 'textarea',
					'label_code'    => 'content',
					'required'      => true,
					'privacy_class' => 'public',
				),
				'feed_type' => array(
					'type'          => 'select',
					'label_code'    => 'feed_type',
					'required'      => true,
					'privacy_class' => 'public',
					'choices'       => $this->feed_type_choices(),
				),
				'topic' => array(
					'type'          => 'text',
					'label_code'    => 'topic',
					'required'      => false,
					'privacy_class' => 'public',
				),
				'visibility' => array(
					'type'          => 'select',
					'label_code'    => 'visibility',
					'required'      => true,
					'privacy_class' => 'public',
					'choices'       => $this->visibility_choices(),
				),
				'language' => array(
					'type'          => 'text',
					'label_code'    => 'language',
					'required'      => false,
					'privacy_class' => 'public',
				),
				'country_region' => array(
					'type'          => 'text',
					'label_code'    => 'country_region',
					'required'      => false,
					'privacy_class' => 'public',
				),
				'comments_enabled' => array(
					'type'          => 'checkbox',
					'label_code'    => 'comments_enabled',
					'required'      => false,
					'privacy_class' => 'public',
				),
				'medical_disclaimer_confirmed' => array(
					'type'          => 'checkbox',
					'label_code'    => 'medical_disclaimer_confirmed',
					'required'      => false,
					'privacy_class' => 'private',
				),
				'patient_privacy_confirmed' => array(
					'type'          => 'checkbox',
					'label_code'    => 'patient_privacy_confirmed',
					'required'      => false,
					'privacy_class' => 'sensitive',
				),
				'scheduled_date' => array(
					'type'          => 'datetime',
					'label_code'    => 'scheduled_date',
					'required'      => false,
					'privacy_class' => 'private',
				),
				'publication_action' => array(
					'type'          => 'select',
					'label_code'    => 'publication_action',
					'required'      => true,
					'privacy_class' => 'private',
					'choices'       => array(
						'submit'   => 'action_submit',
						'publish'  => 'action_publish',
						'schedule' => 'action_schedule',
					),
				),
			),
		);
	}

	/**
	 * @param string|null          $native_reference Existing File 21 draft.
	 * @param array<string,mixed> $payload          File 22 payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function create_draft( int $user_id, ?string $native_reference, array $payload ) {
		if ( ! $this->can_create( $user_id ) ) {
			return $this->error( 'permission_denied' );
		}

		$post_id = 0;
		if ( null !== $native_reference && '' !== $native_reference ) {
			$post_id = $this->post_id_from_reference( $native_reference );
			if ( $post_id <= 0 ) {
				return $this->error( 'invalid_reference' );
			}
			if ( ! $this->user_can_manage_reference( $user_id, $post_id ) ) {
				return $this->error( 'permission_denied' );
			}
		}

		$input = $this->normalize_payload( $payload, 'draft', $post_id );
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

		return array(
			'native_reference' => $this->native_reference( $created_id ),
			'status'           => 'draft',
		);
	}

	/**
	 * @param array<string,mixed> $payload File 22 payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function validate( int $user_id, array $payload ) {
		if ( ! $this->can_create( $user_id ) ) {
			return $this->error( 'permission_denied' );
		}

		$input = $this->normalize_payload( $payload, $this->publication_action( $payload ) );
		if ( $input instanceof \WP_Error ) {
			return array( 'valid' => false, 'errors' => array( $input->get_error_code() ), 'warnings' => array() );
		}

		$validation = ComposerValidation::validate( $input, $user_id, Settings::get() );
		return array(
			'valid'    => ! empty( $validation['valid'] ),
			'errors'   => $this->validation_codes( $validation['errors'] ?? array() ),
			'warnings' => array(),
		);
	}

	/**
	 * @param array<string,mixed> $payload File 22 payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function preview( int $user_id, array $payload ) {
		if ( ! $this->can_create( $user_id ) ) {
			return $this->error( 'permission_denied' );
		}

		$reference = isset( $payload['native_reference'] ) && is_scalar( $payload['native_reference'] ) ? (string) $payload['native_reference'] : '';
		$post_id   = $this->post_id_from_reference( $reference );
		if ( $post_id <= 0 ) {
			return $this->error( 'invalid_reference' );
		}
		if ( ! $this->user_can_manage_reference( $user_id, $post_id ) ) {
			return $this->error( 'permission_denied' );
		}

		$input = $this->normalize_payload( $payload, 'draft', $post_id );
		if ( $input instanceof \WP_Error ) {
			return $input;
		}
		$result = Composer::create_or_update_from_request( $input, array(), $user_id );
		if ( empty( $result['ok'] ) ) {
			return $this->result_error( $result );
		}

		$url = function_exists( 'get_preview_post_link' ) ? (string) get_preview_post_link( $post_id ) : '';
		if ( '' === $url ) {
			return $this->error( 'temporarily_unavailable' );
		}

		return array(
			'preview_url' => $url,
			'expires_at'  => time() + self::PREVIEW_TTL,
		);
	}

	/**
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

		$post_id = 0;
		if ( isset( $payload['native_reference'] ) && is_scalar( $payload['native_reference'] ) && '' !== (string) $payload['native_reference'] ) {
			$post_id = $this->post_id_from_reference( (string) $payload['native_reference'] );
			if ( $post_id <= 0 ) {
				return $this->error( 'invalid_reference' );
			}
			if ( ! $this->user_can_manage_reference( $user_id, $post_id ) ) {
				return $this->error( 'permission_denied' );
			}
		}

		$action = $this->publication_action( $payload );
		$input  = $this->normalize_payload( $payload, $action, $post_id );
		if ( $input instanceof \WP_Error ) {
			return $input;
		}

		$fingerprint = $this->payload_fingerprint( $input );
		if ( '' === $fingerprint ) {
			return $this->error( 'temporarily_unavailable' );
		}

		$option_key = self::IDEMPOTENCY_PREFIX . $user_id . '_' . hash( 'sha256', $idempotency_key );
		$existing   = function_exists( 'get_option' ) ? get_option( $option_key, null ) : null;
		$reconciled = $this->reconcile_idempotency_record( $existing, $fingerprint, $user_id );
		if ( null !== $reconciled ) {
			return $reconciled;
		}

		if ( ! function_exists( 'add_option' ) ) {
			return $this->error( 'temporarily_unavailable' );
		}
		$processing = array(
			'state'       => 'processing',
			'fingerprint' => $fingerprint,
			'created_at'  => time(),
		);
		if ( ! add_option( $option_key, $processing, '', false ) ) {
			$existing = function_exists( 'get_option' ) ? get_option( $option_key, null ) : null;
			$reconciled = $this->reconcile_idempotency_record( $existing, $fingerprint, $user_id );
			return null !== $reconciled ? $reconciled : $this->error( 'conflict' );
		}

		$result = Composer::create_or_update_from_request( $input, array(), $user_id );
		if ( empty( $result['ok'] ) ) {
			if ( function_exists( 'delete_option' ) ) {
				delete_option( $option_key );
			}
			return $this->result_error( $result );
		}

		$created_id = isset( $result['post_id'] ) ? (int) $result['post_id'] : 0;
		$status     = $this->normalize_status( (string) ( $result['status'] ?? '' ) );
		if ( $created_id <= 0 || '' === $status ) {
			return $this->error( 'temporarily_unavailable' );
		}

		$completed = array(
			'state'            => 'completed',
			'fingerprint'      => $fingerprint,
			'native_reference' => $this->native_reference( $created_id ),
			'status'           => $status,
			'completed_at'     => time(),
		);
		if ( function_exists( 'update_option' ) ) {
			update_option( $option_key, $completed, false );
		}
		$persisted = function_exists( 'get_option' ) ? get_option( $option_key, null ) : null;
		if ( ! is_array( $persisted ) || $completed !== $persisted ) {
			return $this->error( 'temporarily_unavailable' );
		}

		return $this->status_envelope( $created_id, $status );
	}

	/**
	 * @return array<string,mixed>|\WP_Error
	 */
	public function status( int $user_id, string $native_reference ) {
		if ( ! $this->can_create( $user_id ) ) {
			return $this->error( 'permission_denied' );
		}
		$post_id = $this->post_id_from_reference( $native_reference );
		if ( $post_id <= 0 ) {
			return $this->error( 'invalid_reference' );
		}
		if ( ! $this->user_can_manage_reference( $user_id, $post_id ) ) {
			return $this->error( 'permission_denied' );
		}

		$status = function_exists( 'get_post_status' ) ? $this->normalize_status( (string) get_post_status( $post_id ) ) : '';
		return '' !== $status ? $this->status_envelope( $post_id, $status ) : $this->error( 'not_found' );
	}

	/** Resolve a canonical URL only after native ownership/visibility checks. */
	public function canonical_url( int $user_id, string $native_reference ): string {
		$post_id = $this->post_id_from_reference( $native_reference );
		if ( $user_id <= 0 || $post_id <= 0 || ! $this->is_native_post( $post_id ) ) {
			return '';
		}
		if ( 'publish' !== (string) get_post_status( $post_id ) || ! PostMetadata::user_can_view( $post_id, $user_id ) ) {
			return '';
		}
		return function_exists( 'get_permalink' ) ? (string) get_permalink( $post_id ) : '';
	}

	/**
	 * Privacy-safe System Check data. No user, post, draft, patient content,
	 * native reference, idempotency key, or payload is included.
	 *
	 * @return array<string,mixed>
	 */
	public function health_report(): array {
		$settings  = Settings::get();
		$available = $this->is_available();
		return array(
			'status'                     => $available ? 'pass' : 'warning',
			'codes'                      => $available ? array() : array( 'native_unavailable' ),
			'adapter_key'                => $this->key(),
			'native_module'              => $this->native_module(),
			'actual_native_version'      => defined( 'SABRI_HNF_VERSION' ) ? (string) SABRI_HNF_VERSION : '',
			'minimum_native_version'     => $this->minimum_native_version(),
			'required_capability'        => $this->required_capability(),
			'privacy_classification'     => $this->privacy_classification(),
			'workflow_api_version'       => $this->workflow_api_version(),
			'schema_version'             => $this->schema_version(),
			'supports_native_drafts'     => $this->supports_native_drafts(),
			'idempotency_storage_ready'  => function_exists( 'add_option' ) && function_exists( 'update_option' ) && function_exists( 'get_option' ),
			'composer_setting_enabled'   => ! empty( $settings['composer']['public_composer_enabled'] ),
			'composer_feature_enabled'   => SafeMode::feature_enabled( 'composer' ),
			'native_route_available'     => '' !== $this->native_url(),
			'available'                  => $available,
		);
	}

	/** @return array<string,string> */
	private function feed_type_choices(): array {
		$choices = array();
		foreach ( self::SUPPORTED_FEED_TYPES as $slug ) {
			$key = str_replace( '-', '_', $slug );
			$choices[ $key ] = 'feed_type_' . $key;
		}
		return $choices;
	}

	/** @return array<string,string> */
	private function visibility_choices(): array {
		$settings = Settings::get();
		$allowed  = FeedContext::allowed_composer_visibility( $settings, true );
		$choices  = array();
		foreach ( (array) $allowed as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' !== $slug && 1 === preg_match( '/^[a-z][a-z0-9_]{0,63}$/', $slug ) ) {
				$choices[ $slug ] = 'visibility_' . $slug;
			}
		}
		return $choices;
	}

	/**
	 * @param array<string,mixed> $payload File 22 payload.
	 * @return array<string,mixed>|\WP_Error
	 */
	private function normalize_payload( array $payload, string $action, int $post_id = 0 ) {
		$feed_key  = isset( $payload['feed_type'] ) && is_scalar( $payload['feed_type'] ) ? sanitize_key( (string) $payload['feed_type'] ) : 'standard_post';
		$feed_type = str_replace( '_', '-', $feed_key );
		if ( ! in_array( $feed_type, self::SUPPORTED_FEED_TYPES, true ) ) {
			return $this->error( 'validation_failed' );
		}

		$visibility = isset( $payload['visibility'] ) && is_scalar( $payload['visibility'] ) ? sanitize_key( (string) $payload['visibility'] ) : 'public';
		if ( ! isset( $this->visibility_choices()[ $visibility ] ) ) {
			return $this->error( 'validation_failed' );
		}
		if ( ! in_array( $action, array_merge( array( 'draft' ), self::SUPPORTED_PUBLICATION_ACTIONS ), true ) ) {
			return $this->error( 'validation_failed' );
		}

		$input = array(
			'post_id'                       => $post_id,
			'composer_action'               => $action,
			'title'                         => $this->scalar( $payload, 'title' ),
			'content'                       => $this->scalar( $payload, 'content' ),
			'feed_type'                     => $feed_type,
			'topic'                         => $this->scalar( $payload, 'topic' ),
			'visibility'                    => $visibility,
			'language'                      => $this->scalar( $payload, 'language' ),
			'country_region'                => $this->scalar( $payload, 'country_region' ),
			'comments_enabled'              => $this->boolean( $payload, 'comments_enabled' ),
			'medical_disclaimer_confirmed' => $this->boolean( $payload, 'medical_disclaimer_confirmed' ),
			'patient_privacy_confirmed'     => $this->boolean( $payload, 'patient_privacy_confirmed' ),
			'scheduled_date'                => $this->scalar( $payload, 'scheduled_date' ),
			'attachments'                   => array(),
			'gallery'                       => array(),
			'clinical_case'                 => array(),
			'research'                      => array(),
		);

		return $input;
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
			'composer_denied'   => 'permission_denied',
			'edit_denied'       => 'permission_denied',
			'publish_denied'    => 'permission_denied',
			'submit_denied'     => 'permission_denied',
			'schedule_denied'   => 'permission_denied',
			'validation_failed' => 'validation_failed',
			'rate_limited'      => 'rate_limited',
			'save_failed'       => 'temporarily_unavailable',
			'drafts_disabled'   => 'temporarily_unavailable',
		);
		return $this->error( $map[ $code ] ?? 'temporarily_unavailable' );
	}

	/** @param mixed $record @return array<string,mixed>|\WP_Error|null */
	private function reconcile_idempotency_record( $record, string $fingerprint, int $user_id ) {
		if ( ! is_array( $record ) ) {
			return null;
		}
		if ( ! isset( $record['fingerprint'] ) || ! hash_equals( (string) $record['fingerprint'], $fingerprint ) ) {
			return $this->error( 'conflict' );
		}
		if ( 'completed' !== (string) ( $record['state'] ?? '' ) ) {
			return $this->error( 'temporarily_unavailable' );
		}

		$post_id = $this->post_id_from_reference( (string) ( $record['native_reference'] ?? '' ) );
		$status  = sanitize_key( (string) ( $record['status'] ?? '' ) );
		if ( $post_id <= 0 || ! $this->user_can_manage_reference( $user_id, $post_id ) || ! in_array( $status, array( 'draft', 'pending_review', 'scheduled', 'published', 'rejected', 'failed' ), true ) ) {
			return $this->error( 'temporarily_unavailable' );
		}
		return $this->status_envelope( $post_id, $status );
	}

	/** @param array<string,mixed> $input */
	private function payload_fingerprint( array $input ): string {
		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $input ) : json_encode( $input );
		return is_string( $encoded ) ? hash( 'sha256', $encoded ) : '';
	}

	/** @return array<string,mixed> */
	private function status_envelope( int $post_id, string $status ): array {
		$envelope = array(
			'native_reference' => $this->native_reference( $post_id ),
			'status'           => $status,
		);
		if ( 'published' === $status && function_exists( 'get_permalink' ) ) {
			$url = (string) get_permalink( $post_id );
			if ( '' !== $url ) {
				$envelope['canonical_url'] = $url;
			}
		}
		return $envelope;
	}

	private function normalize_status( string $status ): string {
		$map = array(
			'draft'   => 'draft',
			'pending' => 'pending_review',
			'future'  => 'scheduled',
			'publish' => 'published',
			'trash'   => 'rejected',
		);
		return $map[ sanitize_key( $status ) ] ?? '';
	}

	private function user_can_manage_reference( int $user_id, int $post_id ): bool {
		return $this->is_native_post( $post_id ) && ComposerPermissions::user_can_edit_post( $post_id, $user_id );
	}

	private function is_native_post( int $post_id ): bool {
		return $post_id > 0 && function_exists( 'get_post_type' ) && 'post' === get_post_type( $post_id );
	}

	private function post_id_from_reference( string $reference ): int {
		if ( 1 !== preg_match( self::NATIVE_REFERENCE_PATTERN, trim( $reference ), $matches ) ) {
			return 0;
		}
		return isset( $matches[1] ) ? (int) $matches[1] : 0;
	}

	private function native_reference( int $post_id ): string {
		return 'post-' . $post_id;
	}

	private function error( string $code ): \WP_Error {
		return new \WP_Error( $code, __( 'The native social publication workflow could not complete.', 'sabri-complete-home-news-feed' ) );
	}

	/** Return File 21's canonical native route, never a File 22 override. */
	private function native_url(): string {
		return function_exists( 'home_url' ) ? home_url( '/create-post/' ) : '/create-post/';
	}
}
