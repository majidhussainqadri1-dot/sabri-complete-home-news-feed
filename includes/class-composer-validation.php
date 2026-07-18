<?php
/**
 * Public composer validation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitizes and validates public composer payloads.
 */
final class ComposerValidation {
	/**
	 * Clinical Case field map.
	 *
	 * @return array<string,string>
	 */
	public static function clinical_fields() {
		return array(
			'case_title'            => __( 'Case title', 'sabri-complete-home-news-feed' ),
			'patient_age_range'     => __( 'Patient age range', 'sabri-complete-home-news-feed' ),
			'patient_gender'        => __( 'Patient gender', 'sabri-complete-home-news-feed' ),
			'country'               => __( 'Country', 'sabri-complete-home-news-feed' ),
			'chief_complaints'      => __( 'Chief complaints', 'sabri-complete-home-news-feed' ),
			'duration'              => __( 'Duration', 'sabri-complete-home-news-feed' ),
			'etiology_cause'        => __( 'Etiology/cause', 'sabri-complete-home-news-feed' ),
			'mental_symptoms'       => __( 'Mental symptoms', 'sabri-complete-home-news-feed' ),
			'physical_generals'     => __( 'Physical generals', 'sabri-complete-home-news-feed' ),
			'particular_symptoms'   => __( 'Particular symptoms', 'sabri-complete-home-news-feed' ),
			'miasmatic_assessment'  => __( 'Miasmatic assessment', 'sabri-complete-home-news-feed' ),
			'repertorial_analysis'  => __( 'Repertorial analysis', 'sabri-complete-home-news-feed' ),
			'selected_remedy'       => __( 'Selected remedy', 'sabri-complete-home-news-feed' ),
			'potency'               => __( 'Potency', 'sabri-complete-home-news-feed' ),
			'repetition'            => __( 'Repetition', 'sabri-complete-home-news-feed' ),
			'follow_up'             => __( 'Follow-up', 'sabri-complete-home-news-feed' ),
			'outcome'               => __( 'Outcome', 'sabri-complete-home-news-feed' ),
			'investigation_notes'   => __( 'Investigation notes', 'sabri-complete-home-news-feed' ),
		);
	}

	/**
	 * Research field map.
	 *
	 * @return array<string,string>
	 */
	public static function research_fields() {
		return array(
			'research_title'       => __( 'Research title', 'sabri-complete-home-news-feed' ),
			'abstract'             => __( 'Abstract', 'sabri-complete-home-news-feed' ),
			'research_question'    => __( 'Research question', 'sabri-complete-home-news-feed' ),
			'background'           => __( 'Background', 'sabri-complete-home-news-feed' ),
			'method'               => __( 'Method', 'sabri-complete-home-news-feed' ),
			'sample_size'          => __( 'Sample size', 'sabri-complete-home-news-feed' ),
			'intervention'         => __( 'Intervention', 'sabri-complete-home-news-feed' ),
			'comparison'           => __( 'Comparison', 'sabri-complete-home-news-feed' ),
			'outcome'              => __( 'Outcome', 'sabri-complete-home-news-feed' ),
			'results'              => __( 'Results', 'sabri-complete-home-news-feed' ),
			'limitations'          => __( 'Limitations', 'sabri-complete-home-news-feed' ),
			'conclusion'           => __( 'Conclusion', 'sabri-complete-home-news-feed' ),
			'references'           => __( 'References', 'sabri-complete-home-news-feed' ),
			'doi_source_url'       => __( 'DOI/source URL', 'sabri-complete-home-news-feed' ),
			'conflict_disclosure'  => __( 'Conflict-of-interest disclosure', 'sabri-complete-home-news-feed' ),
			'funding_disclosure'   => __( 'Funding disclosure', 'sabri-complete-home-news-feed' ),
		);
	}

	/**
	 * Clinical fields that must never be accepted.
	 *
	 * @return array<int,string>
	 */
	public static function forbidden_clinical_fields() {
		return array(
			'patient_full_name',
			'national_id',
			'passport',
			'phone_number',
			'complete_residential_address',
			'raw_confidential_identifiers',
		);
	}

	/**
	 * Validate a composer request.
	 *
	 * @param array<string,mixed>      $input Input.
	 * @param int                      $user_id User ID.
	 * @param array<string,mixed>|null $settings Settings.
	 * @return array<string,mixed>
	 */
	public static function validate( array $input, $user_id = 0, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$user_id  = $user_id ? (int) $user_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		$errors   = array();
		$data     = array();

		$data['action']      = self::clean_key( isset( $input['composer_action'] ) ? $input['composer_action'] : ( isset( $input['action'] ) ? $input['action'] : 'submit' ) );
		$data['title']       = self::clean_text( isset( $input['title'] ) ? $input['title'] : '' );
		$data['content']     = self::clean_content( isset( $input['content'] ) ? $input['content'] : '' );
		$data['feed_type']   = self::normalize_feed_type( isset( $input['feed_type'] ) ? $input['feed_type'] : 'standard-post', $settings );
		$data['topic']       = self::clean_text( isset( $input['topic'] ) ? $input['topic'] : '' );
		$data['visibility']  = FeedContext::normalize_visibility( isset( $input['visibility'] ) ? $input['visibility'] : 'public', $settings, true );
		$data['language']    = self::clean_text( isset( $input['language'] ) ? $input['language'] : '' );
		$data['country_region'] = self::clean_text( isset( $input['country_region'] ) ? $input['country_region'] : ( isset( $input['country'] ) ? $input['country'] : '' ) );
		$data['comments_enabled'] = ! empty( $input['comments_enabled'] ) ? 1 : 0;
		$data['media_alt_text'] = self::clean_text( isset( $input['media_alt_text'] ) ? $input['media_alt_text'] : '' );
		$data['media_caption'] = self::clean_textarea( isset( $input['media_caption'] ) ? $input['media_caption'] : '' );
		$data['medical_disclaimer_confirmed'] = ! empty( $input['medical_disclaimer_confirmed'] ) ? 1 : 0;
		$data['patient_privacy_confirmed'] = ! empty( $input['patient_privacy_confirmed'] ) ? 1 : 0;
		$data['scheduled_date'] = self::clean_text( isset( $input['scheduled_date'] ) ? $input['scheduled_date'] : '' );
		$data['clinical_case'] = self::sanitize_structured_fields( isset( $input['clinical_case'] ) && is_array( $input['clinical_case'] ) ? $input['clinical_case'] : array(), self::clinical_fields() );
		$data['research'] = self::sanitize_structured_fields( isset( $input['research'] ) && is_array( $input['research'] ) ? $input['research'] : array(), self::research_fields() );
		$data['attachments'] = self::clean_id_list( isset( $input['attachments'] ) ? $input['attachments'] : array() );
		$data['gallery'] = self::clean_id_list( isset( $input['gallery'] ) ? $input['gallery'] : array() );

		if ( ! in_array( $data['action'], array( 'draft', 'preview', 'submit', 'publish', 'schedule' ), true ) ) {
			$data['action'] = 'submit';
		}

		if ( ! in_array( $data['action'], array( 'preview', 'draft' ), true ) && '' === trim( wp_strip_all_tags( $data['content'] ) ) ) {
			$errors[] = array( 'code' => 'content_required', 'message' => __( 'Post content is required.', 'sabri-complete-home-news-feed' ) );
		}

		if ( 'followers' === self::clean_key( isset( $input['visibility'] ) ? $input['visibility'] : '' ) ) {
			$errors[] = array( 'code' => 'followers_visibility_deferred', 'message' => __( 'Followers visibility is not available until the follow runtime is implemented.', 'sabri-complete-home-news-feed' ) );
		}

		if ( ! MediaHandler::validate_attachment_ownership( $data['attachments'], $user_id ) || ! MediaHandler::validate_attachment_ownership( $data['gallery'], $user_id ) ) {
			$errors[] = array( 'code' => 'attachment_denied', 'message' => __( 'One or more attachments cannot be used by this account.', 'sabri-complete-home-news-feed' ) );
		}

		if ( 'clinical-case' === $data['feed_type'] ) {
			$errors = array_merge( $errors, self::validate_clinical_case( $input, $data, $settings ) );
		}

		if ( 'research' === $data['feed_type'] ) {
			$research = self::validate_research( isset( $input['research'] ) && is_array( $input['research'] ) ? $input['research'] : array(), $settings );
			$data['evidence_level'] = $research['evidence_level'];
			$errors = array_merge( $errors, $research['errors'] );
		}

		if ( ! empty( $settings['composer']['require_medical_disclaimer'] ) && empty( $data['medical_disclaimer_confirmed'] ) && in_array( $data['feed_type'], array( 'clinical-case', 'research', 'public-health' ), true ) ) {
			$errors[] = array( 'code' => 'medical_disclaimer_required', 'message' => __( 'Medical disclaimer confirmation is required.', 'sabri-complete-home-news-feed' ) );
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
			'data'   => $data,
		);
	}

	/**
	 * Normalize a feed type.
	 *
	 * @param mixed               $value Value.
	 * @param array<string,mixed> $settings Settings.
	 * @return string
	 */
	public static function normalize_feed_type( $value, array $settings ) {
		$value   = self::clean_key( $value );
		$allowed = isset( $settings['composer']['allowed_feed_types'] ) && is_array( $settings['composer']['allowed_feed_types'] ) ? $settings['composer']['allowed_feed_types'] : FeedContext::phase2_feed_type_slugs();

		return in_array( $value, $allowed, true ) ? $value : 'standard-post';
	}

	/**
	 * Validate upload-like file metadata.
	 *
	 * @param array<string,mixed> $input Input.
	 * @param array<string,mixed> $data Sanitized data.
	 * @param array<string,mixed> $settings Settings.
	 * @return array<int,array<string,string>>
	 */
	private static function validate_clinical_case( array $input, array $data, array $settings ) {
		$errors = array();
		$raw    = isset( $input['clinical_case'] ) && is_array( $input['clinical_case'] ) ? $input['clinical_case'] : array();

		foreach ( self::forbidden_clinical_fields() as $field ) {
			if ( ! empty( $raw[ $field ] ) ) {
				$errors[] = array( 'code' => 'forbidden_patient_identifier', 'message' => __( 'Clinical Case content cannot include direct patient identifiers.', 'sabri-complete-home-news-feed' ) );
				break;
			}
		}

		if ( ! empty( $settings['composer']['require_patient_consent'] ) && empty( $data['patient_privacy_confirmed'] ) ) {
			$errors[] = array( 'code' => 'patient_privacy_required', 'message' => __( 'Patient consent and anonymization confirmation is required.', 'sabri-complete-home-news-feed' ) );
		}

		return $errors;
	}

	/**
	 * Validate Research fields.
	 *
	 * @param array<string,mixed> $input Input.
	 * @param array<string,mixed> $settings Settings.
	 * @return array<string,mixed>
	 */
	private static function validate_research( array $input, array $settings ) {
		unset( $settings );

		$evidence = self::clean_key( isset( $input['evidence_level'] ) ? $input['evidence_level'] : 'unverified-claim' );
		$errors   = array();
		if ( ! in_array( $evidence, array_keys( Taxonomies::evidence_level_terms() ), true ) ) {
			$errors[] = array( 'code' => 'invalid_evidence_level', 'message' => __( 'Research evidence level must use a controlled value.', 'sabri-complete-home-news-feed' ) );
			$evidence = 'unverified-claim';
		}

		return array(
			'evidence_level' => $evidence,
			'errors'         => $errors,
		);
	}

	/**
	 * Sanitize structured fields by allow-list.
	 *
	 * @param array<string,mixed> $input Input.
	 * @param array<string,string> $fields Allowed fields.
	 * @return array<string,string>
	 */
	private static function sanitize_structured_fields( array $input, array $fields ) {
		$out = array();
		foreach ( $fields as $key => $label ) {
			unset( $label );
			$out[ $key ] = isset( $input[ $key ] ) ? self::clean_textarea( $input[ $key ] ) : '';
		}

		return $out;
	}

	/**
	 * Clean an ID list.
	 *
	 * @param mixed $value Value.
	 * @return array<int,int>
	 */
	private static function clean_id_list( $value ) {
		$items = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
		$out   = array();

		foreach ( (array) $items as $item ) {
			$item = function_exists( 'absint' ) ? absint( $item ) : abs( (int) $item );
			if ( $item > 0 ) {
				$out[] = $item;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Clean HTML content.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_content( $value ) {
		$value = (string) $value;
		if ( function_exists( 'wp_kses_post' ) ) {
			return wp_kses_post( $value );
		}

		return strip_tags( $value, '<p><br><strong><em><b><i><ul><ol><li><a><blockquote><code><pre>' );
	}

	/**
	 * Clean text.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_text( $value ) {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( $value );
		}

		return trim( strip_tags( (string) $value ) );
	}

	/**
	 * Clean textarea.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_textarea( $value ) {
		if ( function_exists( 'sanitize_textarea_field' ) ) {
			return sanitize_textarea_field( $value );
		}

		return trim( strip_tags( (string) $value ) );
	}

	/**
	 * Clean key.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function clean_key( $value ) {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}

		return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}
}
