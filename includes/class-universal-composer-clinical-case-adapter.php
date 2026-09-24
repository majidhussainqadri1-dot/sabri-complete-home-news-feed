<?php
/**
 * First-class File 22 Patient Case adapter backed by File 21.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UniversalComposerClinicalCaseAdapter extends UniversalComposerStructuredWorkflowAdapter {
	protected function adapter_key(): string { return 'patient_case'; }
	protected function feed_type(): string { return 'clinical-case'; }
	protected function display_label(): string { return __( 'Patient Case', 'sabri-complete-home-news-feed' ); }
	protected function display_description(): string { return __( 'Create a consent-gated, de-identified clinical/patient case in File 21.', 'sabri-complete-home-news-feed' ); }
	protected function adapter_priority(): int { return 20; }
	protected function adapter_privacy(): string { return 'sensitive'; }
	protected function adapter_icon(): string { return 'clipboard'; }

	protected function special_fields(): array {
		$fields = array();
		foreach ( ComposerValidation::clinical_fields() as $key => $label ) {
			unset( $label );
			$fields['clinical_' . $key] = array(
				'type' => 'textarea',
				'label_code' => 'clinical_' . sanitize_key( (string) $key ),
				'required' => false,
				'privacy_class' => 'sensitive',
			);
		}
		return $fields;
	}

	protected function inflate_special_fields( array $payload ): array {
		$clinical = array();
		foreach ( ComposerValidation::clinical_fields() as $key => $label ) {
			unset( $label );
			$field = 'clinical_' . $key;
			$clinical[ $key ] = isset( $payload[ $field ] ) && is_scalar( $payload[ $field ] ) ? (string) $payload[ $field ] : '';
			unset( $payload[ $field ] );
		}
		$payload['clinical_case'] = $clinical;
		return $payload;
	}
}
