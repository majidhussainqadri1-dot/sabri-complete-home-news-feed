<?php
/**
 * Shared File 21 wrapper for first-class structured File 22 creation cards.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

use Sabri\UniversalComposer\Contracts\Diagnostic_Adapter;
use Sabri\UniversalComposer\Contracts\Lifecycle_Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class UniversalComposerStructuredWorkflowAdapter implements Lifecycle_Adapter, Diagnostic_Adapter {
	private const SCHEMA_VERSION = '1.0.0';

	private UniversalComposerPublicationAdapter $delegate;

	public function __construct() {
		$this->delegate = new UniversalComposerPublicationAdapter();
	}

	abstract protected function adapter_key(): string;
	abstract protected function feed_type(): string;
	abstract protected function display_label(): string;
	abstract protected function display_description(): string;

	/** @return array<string,array<string,mixed>> */
	abstract protected function special_fields(): array;

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	abstract protected function inflate_special_fields( array $payload ): array;

	protected function adapter_priority(): int { return 20; }
	protected function adapter_privacy(): string { return 'public'; }
	protected function adapter_icon(): string { return 'admin-post'; }

	public function api_version(): string { return $this->delegate->api_version(); }
	public function workflow_api_version(): string { return $this->delegate->workflow_api_version(); }
	public function governance_api_version(): string { return $this->delegate->governance_api_version(); }
	public function lifecycle_api_version(): string { return $this->delegate->lifecycle_api_version(); }
	public function schema_version(): string { return self::SCHEMA_VERSION; }
	public function supports_native_drafts(): bool { return true; }
	public function key(): string { return $this->adapter_key(); }
	public function label(): string { return $this->display_label(); }
	public function description(): string { return $this->display_description(); }
	public function group(): string { return 'publishing'; }
	public function icon(): string { return $this->adapter_icon(); }
	public function priority(): int { return $this->adapter_priority(); }
	public function native_module(): string { return $this->delegate->native_module(); }
	public function minimum_native_version(): string { return $this->delegate->minimum_native_version(); }
	public function required_capability(): string { return $this->delegate->required_capability(); }
	public function privacy_classification(): string { return $this->adapter_privacy(); }

	public function is_available(): bool {
		if ( ! $this->delegate->is_available() ) {
			return false;
		}
		$settings = Settings::get();
		$allowed = isset( $settings['composer']['allowed_feed_types'] ) && is_array( $settings['composer']['allowed_feed_types'] )
			? array_map( 'sanitize_key', $settings['composer']['allowed_feed_types'] )
			: array();
		return in_array( $this->feed_type(), $allowed, true );
	}

	public function can_create( int $user_id ): bool {
		return $this->is_available() && $this->delegate->can_create( $user_id );
	}

	public function start_url( int $user_id ): string {
		$url = $this->can_create( $user_id ) ? $this->delegate->start_url( $user_id ) : '';
		return '' !== $url && function_exists( 'add_query_arg' ) ? add_query_arg( array( 'feed_type' => $this->feed_type() ), $url ) : $url;
	}

	/** @return array<string,mixed> */
	public function schema(): array { return $this->build_schema( 0 ); }

	/** @return array<string,mixed> */
	public function schema_for_user( int $user_id ): array { return $this->build_schema( $user_id ); }

	public function create_draft( int $user_id, ?string $native_reference, array $payload ) {
		return $this->delegate->create_draft( $user_id, $native_reference, $this->prepare_payload( $payload ) );
	}
	public function validate( int $user_id, array $payload ) {
		return $this->delegate->validate( $user_id, $this->prepare_payload( $payload ) );
	}
	public function preview( int $user_id, array $payload ) {
		return $this->delegate->preview( $user_id, $this->prepare_payload( $payload ) );
	}
	public function submit( int $user_id, string $idempotency_key, array $payload ) {
		return $this->delegate->submit( $user_id, $idempotency_key, $this->prepare_payload( $payload ) );
	}
	public function status( int $user_id, string $native_reference ) { return $this->delegate->status( $user_id, $native_reference ); }
	public function canonical_url( int $user_id, string $native_reference ): string { return $this->delegate->canonical_url( $user_id, $native_reference ); }
	public function governance_profile(): array { return $this->delegate->governance_profile(); }
	public function lifecycle_capabilities( int $user_id, string $native_reference ) { return $this->delegate->lifecycle_capabilities( $user_id, $native_reference ); }
	public function execute_lifecycle( int $user_id, string $native_reference, string $command, string $idempotency_key, array $payload ) {
		return $this->delegate->execute_lifecycle( $user_id, $native_reference, $command, $idempotency_key, $payload );
	}

	public function health_report(): array {
		$health = $this->delegate->health_report();
		$health['adapter_key'] = $this->key();
		$health['structured_feed_type'] = $this->feed_type();
		$health['privacy_classification'] = $this->privacy_classification();
		$health['available'] = $this->is_available();
		$health['status'] = $health['available'] ? 'pass' : 'warning';
		$health['codes'] = $health['available'] ? array() : array( 'native_unavailable' );
		return $health;
	}

	/** @return array<string,mixed> */
	private function build_schema( int $user_id ): array {
		$fields = array(
			'native_reference' => array( 'type' => 'opaque_reference', 'label_code' => 'native_reference', 'required' => false, 'privacy_class' => 'private' ),
			'title' => array( 'type' => 'text', 'label_code' => 'title', 'required' => false, 'privacy_class' => 'public' ),
			'content' => array( 'type' => 'textarea', 'label_code' => 'content', 'required' => true, 'privacy_class' => $this->adapter_privacy() ),
			'topic' => array( 'type' => 'text', 'label_code' => 'topic', 'required' => false, 'privacy_class' => 'public' ),
			'visibility' => array( 'type' => 'select', 'label_code' => 'visibility', 'required' => true, 'privacy_class' => 'public', 'choices' => $this->visibility_choices() ),
			'language' => array( 'type' => 'text', 'label_code' => 'language', 'required' => false, 'privacy_class' => 'public' ),
			'country_region' => array( 'type' => 'text', 'label_code' => 'country_region', 'required' => false, 'privacy_class' => 'public' ),
			'comments_enabled' => array( 'type' => 'checkbox', 'label_code' => 'comments_enabled', 'required' => false, 'privacy_class' => 'public' ),
			'medical_disclaimer_confirmed' => array( 'type' => 'checkbox', 'label_code' => 'medical_disclaimer_confirmed', 'required' => false, 'privacy_class' => 'private' ),
			'patient_privacy_confirmed' => array( 'type' => 'checkbox', 'label_code' => 'patient_privacy_confirmed', 'required' => false, 'privacy_class' => 'sensitive' ),
			'scheduled_date' => array( 'type' => 'datetime', 'label_code' => 'scheduled_date', 'required' => false, 'privacy_class' => 'private' ),
			'publication_action' => array( 'type' => 'select', 'label_code' => 'publication_action', 'required' => true, 'privacy_class' => 'private', 'choices' => $this->action_choices( $user_id ) ),
		);
		return array( 'version' => self::SCHEMA_VERSION, 'fields' => array_merge( $fields, $this->special_fields() ) );
	}

	/** @return array<string,string> */
	private function visibility_choices(): array {
		$out = array();
		foreach ( (array) FeedContext::allowed_composer_visibility( Settings::get(), true ) as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' !== $slug ) {
				$out[ $slug ] = 'visibility_' . $slug;
			}
		}
		return $out ?: array( 'public' => 'visibility_public' );
	}

	/** @return array<string,string> */
	private function action_choices( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array( 'submit' => 'action_submit', 'publish' => 'action_publish', 'schedule' => 'action_schedule' );
		}
		if ( ComposerPermissions::user_can_publish( $user_id, Settings::get() ) ) {
			return array( 'publish' => 'action_publish', 'schedule' => 'action_schedule' );
		}
		return array( 'submit' => 'action_submit' );
	}

	/** @param array<string,mixed> $payload @return array<string,mixed> */
	private function prepare_payload( array $payload ): array {
		$payload['feed_type'] = str_replace( '-', '_', $this->feed_type() );
		return $this->inflate_special_fields( $payload );
	}
}
