<?php
/**
 * First-class File 22 Research Publication adapter backed by File 21.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UniversalComposerResearchAdapter extends UniversalComposerStructuredWorkflowAdapter {
	protected function adapter_key(): string { return 'research_publication'; }
	protected function feed_type(): string { return 'research'; }
	protected function display_label(): string { return __( 'Research Publication', 'sabri-complete-home-news-feed' ); }
	protected function display_description(): string { return __( 'Create a structured research publication with evidence and source fields in File 21.', 'sabri-complete-home-news-feed' ); }
	protected function adapter_priority(): int { return 30; }
	protected function adapter_icon(): string { return 'media-document'; }

	protected function special_fields(): array {
		$choices = array();
		foreach ( Taxonomies::evidence_level_terms() as $slug => $label ) {
			unset( $label );
			$key = str_replace( '-', '_', sanitize_key( (string) $slug ) );
			$choices[ $key ] = 'evidence_level_' . $key;
		}
		if ( array() === $choices ) {
			$choices['unverified_claim'] = 'evidence_level_unverified_claim';
		}
		$fields = array(
			'research_evidence_level' => array( 'type' => 'select', 'label_code' => 'research_evidence_level', 'required' => true, 'privacy_class' => 'public', 'choices' => $choices ),
		);
		foreach ( ComposerValidation::research_fields() as $key => $label ) {
			unset( $label );
			$fields['research_' . $key] = array(
				'type' => 'textarea',
				'label_code' => 'research_' . sanitize_key( (string) $key ),
				'required' => false,
				'privacy_class' => 'public',
			);
		}
		return $fields;
	}

	protected function inflate_special_fields( array $payload ): array {
		$research = array();
		$evidence = isset( $payload['research_evidence_level'] ) && is_scalar( $payload['research_evidence_level'] ) ? sanitize_key( (string) $payload['research_evidence_level'] ) : 'unverified_claim';
		$research['evidence_level'] = str_replace( '_', '-', $evidence );
		unset( $payload['research_evidence_level'] );
		foreach ( ComposerValidation::research_fields() as $key => $label ) {
			unset( $label );
			$field = 'research_' . $key;
			$research[ $key ] = isset( $payload[ $field ] ) && is_scalar( $payload[ $field ] ) ? (string) $payload[ $field ] : '';
			unset( $payload[ $field ] );
		}
		$payload['research'] = $research;
		return $payload;
	}
}
