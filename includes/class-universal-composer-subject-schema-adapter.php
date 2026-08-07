<?php
/**
 * Role-neutral static and subject-aware interactive schema wrapper.
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
 * File 22 health checks consume schema(), which never invokes current-user
 * identity logic. Interactive File 22 requests call schema_for_user().
 */
final class UniversalComposerSubjectSchemaAdapter implements Workflow_Adapter, Diagnostic_Adapter {
	private const INSTITUTIONAL_FEED_TYPES = array( 'founder-update', 'platform-news' );
	private const SUPPORTED_FEED_TYPES = array(
		'standard-post', 'founder-update', 'classical-homeopathy', 'homeopathy-education',
		'materia-medica', 'repertory', 'clinical-education', 'nutrition',
		'public-health-education', 'platform-news', 'pathology', 'anatomy',
		'principles-of-hygiene', 'islamic-spiritual-healing', 'homeopathy-philosophy',
		'event', 'clinic-announcement',
	);

	private UniversalComposerPublicationAdapter $delegate;

	public function __construct() {
		$this->delegate = new UniversalComposerPublicationAdapter();
	}

	public function api_version(): string { return $this->delegate->api_version(); }
	public function workflow_api_version(): string { return $this->delegate->workflow_api_version(); }
	public function schema_version(): string { return $this->delegate->schema_version(); }
	public function supports_native_drafts(): bool { return $this->delegate->supports_native_drafts(); }
	public function key(): string { return $this->delegate->key(); }
	public function label(): string { return $this->delegate->label(); }
	public function description(): string { return $this->delegate->description(); }
	public function group(): string { return $this->delegate->group(); }
	public function icon(): string { return $this->delegate->icon(); }
	public function priority(): int { return $this->delegate->priority(); }
	public function native_module(): string { return $this->delegate->native_module(); }
	public function minimum_native_version(): string { return $this->delegate->minimum_native_version(); }

	/**
	 * File 22 performs a generic capability prefilter before invoking the native
	 * adapter. That prefilter must not reject a canonical Founder or Administrator
	 * merely because a plugin-specific role capability was not re-applied during
	 * an in-place upgrade. `read` is only the authenticated coarse gate; the
	 * authoritative File 00 identity, suspension, native capability and policy
	 * checks remain enforced by can_create() and every native write command.
	 */
	public function required_capability(): string { return 'read'; }

	public function privacy_classification(): string { return $this->delegate->privacy_classification(); }
	public function is_available(): bool { return $this->delegate->is_available(); }
	public function can_create( int $user_id ): bool { return $this->delegate->can_create( $user_id ); }
	public function start_url( int $user_id ): string { return $this->delegate->start_url( $user_id ); }

	/** @return array<string,mixed> */
	public function schema(): array {
		return $this->build_schema( $this->feed_type_choices( 0, false ) );
	}

	/** @return array<string,mixed> */
	public function schema_for_user( int $user_id ): array {
		return $this->build_schema( $this->feed_type_choices( $user_id, true ) );
	}

	public function create_draft( int $user_id, ?string $native_reference, array $payload ) { return $this->delegate->create_draft( $user_id, $native_reference, $payload ); }
	public function validate( int $user_id, array $payload ) { return $this->delegate->validate( $user_id, $payload ); }
	public function preview( int $user_id, array $payload ) { return $this->delegate->preview( $user_id, $payload ); }
	public function submit( int $user_id, string $idempotency_key, array $payload ) { return $this->delegate->submit( $user_id, $idempotency_key, $payload ); }
	public function status( int $user_id, string $native_reference ) { return $this->delegate->status( $user_id, $native_reference ); }
	public function canonical_url( int $user_id, string $native_reference ): string { return $this->delegate->canonical_url( $user_id, $native_reference ); }

	public function health_report(): array {
		$health = $this->delegate->health_report();
		$health['schema_scope'] = 'role_neutral_static_subject_aware_interactive';
		return $health;
	}

	/**
	 * Build the complete declaration directly. Static health therefore cannot
	 * execute current-user identity checks through the delegated adapter.
	 *
	 * @param array<string,string> $feed_type_choices Feed-type choices.
	 * @return array<string,mixed>
	 */
	private function build_schema( array $feed_type_choices ): array {
		return array(
			'version' => $this->schema_version(),
			'fields'  => array(
				'native_reference' => array( 'type' => 'opaque_reference', 'label_code' => 'native_reference', 'required' => false, 'privacy_class' => 'private' ),
				'title' => array( 'type' => 'text', 'label_code' => 'title', 'required' => false, 'privacy_class' => 'public' ),
				'content' => array( 'type' => 'textarea', 'label_code' => 'content', 'required' => true, 'privacy_class' => 'public' ),
				'feed_type' => array( 'type' => 'select', 'label_code' => 'feed_type', 'required' => true, 'privacy_class' => 'public', 'choices' => $feed_type_choices ),
				'topic' => array( 'type' => 'text', 'label_code' => 'topic', 'required' => false, 'privacy_class' => 'public' ),
				'visibility' => array( 'type' => 'select', 'label_code' => 'visibility', 'required' => true, 'privacy_class' => 'public', 'choices' => array( 'public' => 'visibility_public', 'private' => 'visibility_private' ) ),
				'language' => array( 'type' => 'text', 'label_code' => 'language', 'required' => false, 'privacy_class' => 'public' ),
				'country_region' => array( 'type' => 'text', 'label_code' => 'country_region', 'required' => false, 'privacy_class' => 'public' ),
				'comments_enabled' => array( 'type' => 'checkbox', 'label_code' => 'comments_enabled', 'required' => false, 'privacy_class' => 'public' ),
				'medical_disclaimer_confirmed' => array( 'type' => 'checkbox', 'label_code' => 'medical_disclaimer_confirmed', 'required' => false, 'privacy_class' => 'private' ),
				'patient_privacy_confirmed' => array( 'type' => 'checkbox', 'label_code' => 'patient_privacy_confirmed', 'required' => false, 'privacy_class' => 'sensitive' ),
				'scheduled_date' => array( 'type' => 'datetime', 'label_code' => 'scheduled_date', 'required' => false, 'privacy_class' => 'private' ),
				'publication_action' => array(
					'type' => 'select', 'label_code' => 'publication_action', 'required' => true, 'privacy_class' => 'private',
					'choices' => array( 'submit' => 'action_submit', 'publish' => 'action_publish', 'schedule' => 'action_schedule' ),
				),
			),
		);
	}

	/** @return array<string,string> */
	private function feed_type_choices( int $user_id, bool $subject_aware ): array {
		$settings = Settings::get();
		$allowed = isset( $settings['composer']['allowed_feed_types'] ) && is_array( $settings['composer']['allowed_feed_types'] )
			? array_map( 'sanitize_key', $settings['composer']['allowed_feed_types'] )
			: array();
		$institutional = $subject_aware && $user_id > 0
			&& CanonicalIdentityAdapter::current_action_ready( $user_id )
			&& ( CanonicalIdentityAdapter::is_founder( $user_id ) || CanonicalIdentityAdapter::is_administrator( $user_id ) );
		$choices = array();
		foreach ( self::SUPPORTED_FEED_TYPES as $slug ) {
			if ( ! in_array( $slug, $allowed, true ) ) { continue; }
			if ( $subject_aware && in_array( $slug, self::INSTITUTIONAL_FEED_TYPES, true ) && ! $institutional ) { continue; }
			$key = str_replace( '-', '_', $slug );
			$choices[ $key ] = 'feed_type_' . $key;
		}
		return $choices;
	}
}
