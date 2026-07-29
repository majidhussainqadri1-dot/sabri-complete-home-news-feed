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
 * File 22 health checks consume schema(), which is deliberately role-neutral.
 * Interactive File 22 requests may call schema_for_user() so institutional
 * choices remain hidden from non-Founder and non-Administrator users.
 */
final class UniversalComposerSubjectSchemaAdapter implements Workflow_Adapter, Diagnostic_Adapter {
	private const INSTITUTIONAL_FEED_TYPES = array( 'founder-update', 'platform-news' );
	private const SUPPORTED_FEED_TYPES = array(
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
	public function required_capability(): string { return $this->delegate->required_capability(); }
	public function privacy_classification(): string { return $this->delegate->privacy_classification(); }
	public function is_available(): bool { return $this->delegate->is_available(); }
	public function can_create( int $user_id ): bool { return $this->delegate->can_create( $user_id ); }
	public function start_url( int $user_id ): string { return $this->delegate->start_url( $user_id ); }

	/**
	 * Role-neutral static contract: all configured supported choices.
	 * Authorization is not inferred from this declaration.
	 *
	 * @return array<string,mixed>
	 */
	public function schema(): array {
		return $this->schema_with_choices( $this->feed_type_choices( 0, false ) );
	}

	/**
	 * Subject-aware interactive contract used by corrected File 22 runtimes.
	 *
	 * @return array<string,mixed>
	 */
	public function schema_for_user( int $user_id ): array {
		return $this->schema_with_choices( $this->feed_type_choices( $user_id, true ) );
	}

	public function create_draft( int $user_id, ?string $native_reference, array $payload ) {
		return $this->delegate->create_draft( $user_id, $native_reference, $payload );
	}

	public function validate( int $user_id, array $payload ) {
		return $this->delegate->validate( $user_id, $payload );
	}

	public function preview( int $user_id, array $payload ) {
		return $this->delegate->preview( $user_id, $payload );
	}

	public function submit( int $user_id, string $idempotency_key, array $payload ) {
		return $this->delegate->submit( $user_id, $idempotency_key, $payload );
	}

	public function status( int $user_id, string $native_reference ) {
		return $this->delegate->status( $user_id, $native_reference );
	}

	public function canonical_url( int $user_id, string $native_reference ): string {
		return $this->delegate->canonical_url( $user_id, $native_reference );
	}

	public function health_report(): array {
		$health = $this->delegate->health_report();
		$health['schema_scope'] = 'role_neutral_static_subject_aware_interactive';
		return $health;
	}

	/**
	 * @param array<string,string> $choices Feed-type choices.
	 * @return array<string,mixed>
	 */
	private function schema_with_choices( array $choices ): array {
		$schema = $this->delegate->schema();
		if ( isset( $schema['fields']['feed_type'] ) && is_array( $schema['fields']['feed_type'] ) ) {
			$schema['fields']['feed_type']['choices'] = $choices;
		}
		return $schema;
	}

	/**
	 * @return array<string,string>
	 */
	private function feed_type_choices( int $user_id, bool $subject_aware ): array {
		$settings = Settings::get();
		$allowed = isset( $settings['composer']['allowed_feed_types'] ) && is_array( $settings['composer']['allowed_feed_types'] )
			? array_map( 'sanitize_key', $settings['composer']['allowed_feed_types'] )
			: array();
		$institutional = $subject_aware && $user_id > 0
			&& ( CanonicalIdentityAdapter::is_founder( $user_id ) || CanonicalIdentityAdapter::is_administrator( $user_id ) );
		$choices = array();
		foreach ( self::SUPPORTED_FEED_TYPES as $slug ) {
			if ( ! in_array( $slug, $allowed, true ) ) {
				continue;
			}
			if ( $subject_aware && in_array( $slug, self::INSTITUTIONAL_FEED_TYPES, true ) && ! $institutional ) {
				continue;
			}
			$key = str_replace( '-', '_', $slug );
			$choices[ $key ] = 'feed_type_' . $key;
		}
		return $choices;
	}
}
