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

/** Sanitizes and validates public composer payloads. */
final class ComposerValidation {
	/** Clinical Case field map. */
	public static function clinical_fields() {
		return array(
			'case_title' => __( 'Case title', 'sabri-complete-home-news-feed' ),
			'patient_age_range' => __( 'Patient age range', 'sabri-complete-home-news-feed' ),
			'patient_gender' => __( 'Patient gender', 'sabri-complete-home-news-feed' ),
			'country' => __( 'Country', 'sabri-complete-home-news-feed' ),
			'chief_complaints' => __( 'Chief complaints', 'sabri-complete-home-news-feed' ),
			'duration' => __( 'Duration', 'sabri-complete-home-news-feed' ),
			'etiology_cause' => __( 'Etiology/cause', 'sabri-complete-home-news-feed' ),
			'mental_symptoms' => __( 'Mental symptoms', 'sabri-complete-home-news-feed' ),
			'physical_generals' => __( 'Physical generals', 'sabri-complete-home-news-feed' ),
			'particular_symptoms' => __( 'Particular symptoms', 'sabri-complete-home-news-feed' ),
			'miasmatic_assessment' => __( 'Miasmatic assessment', 'sabri-complete-home-news-feed' ),
			'repertorial_analysis' => __( 'Repertorial analysis', 'sabri-complete-home-news-feed' ),
			'selected_remedy' => __( 'Selected remedy', 'sabri-complete-home-news-feed' ),
			'potency' => __( 'Potency', 'sabri-complete-home-news-feed' ),
			'repetition' => __( 'Repetition', 'sabri-complete-home-news-feed' ),
			'follow_up' => __( 'Follow-up', 'sabri-complete-home-news-feed' ),
			'outcome' => __( 'Outcome', 'sabri-complete-home-news-feed' ),
			'investigation_notes' => __( 'Investigation notes', 'sabri-complete-home-news-feed' ),
		);
	}

	/** Research field map. */
	public static function research_fields() {
		return array(
			'research_title' => __( 'Research title', 'sabri-complete-home-news-feed' ),
			'abstract' => __( 'Abstract', 'sabri-complete-home-news-feed' ),
			'research_question' => __( 'Research question', 'sabri-complete-home-news-feed' ),
			'background' => __( 'Background', 'sabri-complete-home-news-feed' ),
			'method' => __( 'Method', 'sabri-complete-home-news-feed' ),
			'sample_size' => __( 'Sample size', 'sabri-complete-home-news-feed' ),
			'intervention' => __( 'Intervention', 'sabri-complete-home-news-feed' ),
			'comparison' => __( 'Comparison', 'sabri-complete-home-news-feed' ),
			'outcome' => __( 'Outcome', 'sabri-complete-home-news-feed' ),
			'results' => __( 'Results', 'sabri-complete-home-news-feed' ),
			'limitations' => __( 'Limitations', 'sabri-complete-home-news-feed' ),
			'conclusion' => __( 'Conclusion', 'sabri-complete-home-news-feed' ),
			'references' => __( 'References', 'sabri-complete-home-news-feed' ),
			'doi_source_url' => __( 'DOI/source URL', 'sabri-complete-home-news-feed' ),
			'conflict_disclosure' => __( 'Conflict-of-interest disclosure', 'sabri-complete-home-news-feed' ),
			'funding_disclosure' => __( 'Funding disclosure', 'sabri-complete-home-news-feed' ),
		);
	}

	/** Clinical fields that must never be accepted. */
	public static function forbidden_clinical_fields() {
		return array( 'patient_full_name', 'national_id', 'passport', 'phone_number', 'complete_residential_address', 'raw_confidential_identifiers' );
	}

	/** Validate a composer request. */
	public static function validate( array $input, $user_id = 0, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$user_id = $user_id ? (int) $user_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		$errors = array();
		$data = array();
		$raw_feed_type = self::clean_key( isset( $input['feed_type'] ) ? $input['feed_type'] : 'standard-post' );
		$raw_visibility = self::clean_key( isset( $input['visibility'] ) ? $input['visibility'] : 'public' );
		$allowed_feed_types = isset( $settings['composer']['allowed_feed_types'] ) && is_array( $settings['composer']['allowed_feed_types'] ) ? array_map( 'sanitize_key', $settings['composer']['allowed_feed_types'] ) : FeedContext::phase2_feed_type_slugs();
		$allowed_visibility = FeedContext::allowed_composer_visibility( $settings, true );
		$target_post_id = self::positive_id( isset( $input['post_id'] ) ? $input['post_id'] : 0 );

		$data['action'] = self::clean_key( isset( $input['composer_action'] ) ? $input['composer_action'] : ( isset( $input['action'] ) ? $input['action'] : 'submit' ) );
		$data['title'] = self::clean_text( isset( $input['title'] ) ? $input['title'] : '' );
		$data['content'] = self::clean_content( isset( $input['content'] ) ? $input['content'] : '' );
		$data['feed_type'] = self::normalize_feed_type( $raw_feed_type, $settings );
		$data['topic'] = self::clean_text( isset( $input['topic'] ) ? $input['topic'] : '' );
		$data['visibility'] = FeedContext::normalize_visibility( $raw_visibility, $settings, true );
		$data['language'] = self::clean_text( isset( $input['language'] ) ? $input['language'] : '' );
		$data['country_region'] = self::clean_text( isset( $input['country_region'] ) ? $input['country_region'] : ( isset( $input['country'] ) ? $input['country'] : '' ) );
		$data['comments_enabled'] = ! empty( $settings['composer']['comments_metadata_enabled'] ) && self::bool_value( isset( $input['comments_enabled'] ) ? $input['comments_enabled'] : false ) ? 1 : 0;
		$data['media_alt_text'] = self::clean_text( isset( $input['media_alt_text'] ) ? $input['media_alt_text'] : '' );
		$data['media_caption'] = self::clean_textarea( isset( $input['media_caption'] ) ? $input['media_caption'] : '' );
		$data['medical_disclaimer_confirmed'] = self::bool_value( isset( $input['medical_disclaimer_confirmed'] ) ? $input['medical_disclaimer_confirmed'] : false ) ? 1 : 0;
		$data['patient_privacy_confirmed'] = self::bool_value( isset( $input['patient_privacy_confirmed'] ) ? $input['patient_privacy_confirmed'] : false ) ? 1 : 0;
		$data['privacy_review_required'] = 0;
		$data['scheduled_date'] = self::clean_text( isset( $input['scheduled_date'] ) ? $input['scheduled_date'] : '' );
		$data['clinical_case'] = self::sanitize_structured_fields( isset( $input['clinical_case'] ) && is_array( $input['clinical_case'] ) ? $input['clinical_case'] : array(), self::clinical_fields() );
		$data['research'] = self::sanitize_structured_fields( isset( $input['research'] ) && is_array( $input['research'] ) ? $input['research'] : array(), self::research_fields() );
		$data['attachments'] = self::clean_id_list( isset( $input['attachments'] ) ? $input['attachments'] : array() );
		$data['gallery'] = self::clean_id_list( isset( $input['gallery'] ) ? $input['gallery'] : array() );

		if ( ! in_array( $data['action'], array( 'draft', 'preview', 'submit', 'publish', 'schedule' ), true ) ) {
			$data['action'] = 'submit';
		}
		if ( ! in_array( $raw_feed_type, $allowed_feed_types, true ) ) {
			$errors[] = array( 'code' => 'invalid_feed_type', 'message' => __( 'The selected post type is not available.', 'sabri-complete-home-news-feed' ) );
		}
		if ( ! in_array( $raw_visibility, $allowed_visibility, true ) ) {
			$errors[] = array( 'code' => 'invalid_visibility', 'message' => __( 'The selected visibility is not available.', 'sabri-complete-home-news-feed' ) );
		}
		if ( 'followers' === $raw_visibility && ! Phase3FeatureSettings::enabled( 'followers_visibility_enabled' ) ) {
			$errors[] = array( 'code' => 'followers_visibility_disabled', 'message' => __( 'Followers visibility is currently unavailable.', 'sabri-complete-home-news-feed' ) );
		}
		if ( ! in_array( $data['action'], array( 'preview', 'draft' ), true ) && '' === trim( wp_strip_all_tags( $data['content'] ) ) ) {
			$errors[] = array( 'code' => 'content_required', 'message' => __( 'Post content is required.', 'sabri-complete-home-news-feed' ) );
		}
		if ( ! MediaHandler::validate_attachment_ownership( $data['attachments'], $user_id, $target_post_id ) || ! MediaHandler::validate_attachment_ownership( $data['gallery'], $user_id, $target_post_id ) ) {
			$errors[] = array( 'code' => 'attachment_denied', 'message' => __( 'One or more attachments cannot be used by this account.', 'sabri-complete-home-news-feed' ) );
		}

		$errors = array_merge( $errors, self::bounded_field_errors( $data ) );
		if ( 'clinical-case' === $data['feed_type'] ) {
			foreach ( self::validate_clinical_case( $input, $data, $settings ) as $clinical_error ) {
				if ( ! empty( $clinical_error['warning'] ) && 'privacy_review_required' === $clinical_error['code'] ) {
					$data['privacy_review_required'] = 1;
					continue;
				}
				$errors[] = $clinical_error;
			}
		}
		if ( 'research' === $data['feed_type'] ) {
			$research = self::validate_research( isset( $input['research'] ) && is_array( $input['research'] ) ? $input['research'] : array(), $settings );
			$data['evidence_level'] = $research['evidence_level'];
			$data['research']['doi_source_url'] = $research['doi_source_url'];
			$errors = array_merge( $errors, $research['errors'] );
		}
		if ( ! empty( $settings['composer']['require_medical_disclaimer'] ) && empty( $data['medical_disclaimer_confirmed'] ) && in_array( $data['feed_type'], array( 'clinical-case', 'research', 'public-health' ), true ) ) {
			$errors[] = array( 'code' => 'medical_disclaimer_required', 'message' => __( 'Medical disclaimer confirmation is required.', 'sabri-complete-home-news-feed' ) );
		}

		return array( 'valid' => empty( $errors ), 'errors' => $errors, 'data' => $data );
	}

	/** Normalize a feed type. */
	public static function normalize_feed_type( $value, array $settings ) {
		$value = self::clean_key( $value );
		$allowed = isset( $settings['composer']['allowed_feed_types'] ) && is_array( $settings['composer']['allowed_feed_types'] ) ? $settings['composer']['allowed_feed_types'] : FeedContext::phase2_feed_type_slugs();
		return in_array( $value, $allowed, true ) ? $value : 'standard-post';
	}

	/** Validate Clinical Case privacy and consent. */
	private static function validate_clinical_case( array $input, array $data, array $settings ) {
		$errors = array();
		$raw = isset( $input['clinical_case'] ) && is_array( $input['clinical_case'] ) ? $input['clinical_case'] : array();
		foreach ( self::forbidden_clinical_fields() as $field ) {
			if ( ! empty( $raw[ $field ] ) ) {
				$errors[] = self::privacy_error( $field );
				break;
			}
		}
		$scan_fields = array_merge(
			array(
				'title' => isset( $data['title'] ) ? $data['title'] : '',
				'content' => isset( $data['content'] ) ? wp_strip_all_tags( $data['content'] ) : '',
				'media_alt_text' => isset( $data['media_alt_text'] ) ? $data['media_alt_text'] : '',
				'media_caption' => isset( $data['media_caption'] ) ? $data['media_caption'] : '',
			),
			isset( $data['clinical_case'] ) && is_array( $data['clinical_case'] ) ? $data['clinical_case'] : array()
		);
		$scan_fields = array_merge( $scan_fields, self::attachment_privacy_fields( array_merge( $data['attachments'], $data['gallery'] ) ) );
		foreach ( $scan_fields as $field => $value ) {
			if ( self::contains_deterministic_identifier( (string) $value ) ) {
				$errors[] = self::privacy_error( (string) $field );
			}
		}
		if ( empty( $errors ) && self::contains_ambiguous_patient_name_pattern( $scan_fields ) ) {
			$errors[] = array( 'code' => 'privacy_review_required', 'field' => 'clinical_case', 'message' => __( 'Clinical Case content needs privacy review before publication.', 'sabri-complete-home-news-feed' ), 'warning' => true );
		}
		if ( ! empty( $settings['composer']['require_patient_consent'] ) && empty( $data['patient_privacy_confirmed'] ) ) {
			$errors[] = array( 'code' => 'patient_privacy_required', 'message' => __( 'Patient consent and anonymization confirmation is required.', 'sabri-complete-home-news-feed' ) );
		}
		return $errors;
	}

	/** Validate Research fields. */
	private static function validate_research( array $input, array $settings ) {
		unset( $settings );
		$evidence = self::clean_key( isset( $input['evidence_level'] ) ? $input['evidence_level'] : 'unverified-claim' );
		$errors = array();
		if ( ! in_array( $evidence, array_keys( Taxonomies::evidence_level_terms() ), true ) ) {
			$errors[] = array( 'code' => 'invalid_evidence_level', 'message' => __( 'Research evidence level must use a controlled value.', 'sabri-complete-home-news-feed' ) );
			$evidence = 'unverified-claim';
		}
		$source = self::validate_doi_source( isset( $input['doi_source_url'] ) ? $input['doi_source_url'] : '' );
		if ( ! empty( $source['error'] ) ) {
			$errors[] = array( 'code' => 'invalid_doi_source_url', 'field' => 'doi_source_url', 'message' => __( 'Research DOI/source must be a valid DOI or safe HTTPS URL.', 'sabri-complete-home-news-feed' ) );
		}
		return array( 'evidence_level' => $evidence, 'doi_source_url' => $source['value'], 'errors' => $errors );
	}

	/** Validate DOI or source URL. */
	private static function validate_doi_source( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return array( 'value' => '', 'error' => false );
		}
		if ( preg_match( '#^10\.\d{4,9}/\S+$#i', $value ) ) {
			return array( 'value' => $value, 'error' => false );
		}
		$url = function_exists( 'esc_url_raw' ) ? esc_url_raw( $value ) : filter_var( $value, FILTER_VALIDATE_URL );
		if ( ! is_string( $url ) || '' === $url ) {
			return array( 'value' => '', 'error' => true );
		}
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $url ) : parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return array( 'value' => '', 'error' => true );
		}
		$host = strtolower( $parts['host'] );
		if ( in_array( $host, array( 'doi.org', 'dx.doi.org' ), true ) ) {
			$path = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';
			if ( ! preg_match( '#^10\.\d{4,9}/\S+$#i', $path ) ) {
				return array( 'value' => '', 'error' => true );
			}
		}
		return array( 'value' => $url, 'error' => false );
	}

	/** Build a privacy validation error. */
	private static function privacy_error( $field ) {
		return array( 'code' => 'forbidden_patient_identifier', 'field' => sanitize_key( $field ), 'message' => __( 'Clinical Case content cannot include direct patient identifiers.', 'sabri-complete-home-news-feed' ) );
	}

	/** Detect deterministic sensitive identifiers. */
	private static function contains_deterministic_identifier( $value ) {
		$value = (string) $value;
		if ( '' === trim( $value ) ) {
			return false;
		}
		$patterns = array(
			'/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i',
			'/\b(?:\+92|0092|0)?3[0-9]{2}[\s\-]?[0-9]{7}\b/',
			'/\b[0-9]{5}[\s\-]?[0-9]{7}[\s\-]?[0-9]\b/',
			'/\b(?:CNIC|Passport|National\s*ID|Phone|Mobile|Address|MRN|Medical\s*Record|Registration\s*Number|Patient\s*Registration)\s*[:#：-]\s*\S+/iu',
			'/\b(?:house|flat|street|road|sector|block|town|city|district)\b.+\b(?:Pakistan|Karachi|Lahore|Islamabad|Rawalpindi|Peshawar|Quetta|Multan|Faisalabad)\b/i',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $value ) ) {
				return true;
			}
		}
		return false;
	}

	/** Flag ambiguous name-like labels for review. */
	private static function contains_ambiguous_patient_name_pattern( array $fields ) {
		foreach ( $fields as $value ) {
			if ( preg_match( '/(?:\b(?:Patient\s*Name|Full\s*Name)\b|(?:نام|اسم))\s*[:#：-]\s*\S+/iu', (string) $value ) ) {
				return true;
			}
		}
		return false;
	}

	/** Sanitize structured fields by allow-list. */
	private static function sanitize_structured_fields( array $input, array $fields ) {
		$out = array();
		foreach ( $fields as $key => $label ) {
			unset( $label );
			$out[ $key ] = isset( $input[ $key ] ) ? self::clean_textarea( $input[ $key ] ) : '';
		}
		return $out;
	}

	/** Clean an ID list. */
	private static function clean_id_list( $value ) {
		$items = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
		$out = array();
		foreach ( (array) $items as $item ) {
			$item = function_exists( 'absint' ) ? absint( $item ) : abs( (int) $item );
			if ( $item > 0 ) {
				$out[] = $item;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** Read media text for privacy scanning. */
	private static function attachment_privacy_fields( array $attachment_ids ) {
		$fields = array();
		foreach ( array_unique( array_map( 'absint', $attachment_ids ) ) as $attachment_id ) {
			$caption = function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_excerpt', $attachment_id ) : '';
			$alt = function_exists( 'get_post_meta' ) ? (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) : '';
			if ( '' !== trim( $caption ) ) {
				$fields[ 'attachment_caption_' . $attachment_id ] = $caption;
			}
			if ( '' !== trim( $alt ) ) {
				$fields[ 'attachment_alt_' . $attachment_id ] = $alt;
			}
		}
		return $fields;
	}

	/** Reject oversized values. */
	private static function bounded_field_errors( array $data ) {
		$limits = array( 'title' => 500, 'content' => 20000, 'topic' => 500, 'language' => 100, 'country_region' => 500, 'media_alt_text' => 500, 'media_caption' => 5000 );
		foreach ( $limits as $field => $limit ) {
			if ( isset( $data[ $field ] ) && self::text_length( (string) $data[ $field ] ) > $limit ) {
				return array( array( 'code' => 'field_too_long', 'field' => $field, 'message' => __( 'One or more fields exceed the allowed length.', 'sabri-complete-home-news-feed' ) ) );
			}
		}
		foreach ( array( 'clinical_case', 'research' ) as $group ) {
			if ( empty( $data[ $group ] ) || ! is_array( $data[ $group ] ) ) {
				continue;
			}
			foreach ( $data[ $group ] as $field => $value ) {
				if ( self::text_length( (string) $value ) > 5000 ) {
					return array( array( 'code' => 'field_too_long', 'field' => sanitize_key( $field ), 'message' => __( 'One or more fields exceed the allowed length.', 'sabri-complete-home-news-feed' ) ) );
				}
			}
		}
		return array();
	}

	/** Parse a form or REST boolean. */
	private static function bool_value( $value ) {
		if ( true === $value || 1 === $value || 1.0 === $value ) {
			return true;
		}
		return is_scalar( $value ) && in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'on', 'yes' ), true );
	}

	/** Positive integer ID. */
	private static function positive_id( $value ) {
		return is_scalar( $value ) && preg_match( '/^[1-9][0-9]*$/', trim( (string) $value ) ) ? (int) $value : 0;
	}

	/** Multibyte-safe length. */
	private static function text_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}

	/** Clean HTML content. */
	private static function clean_content( $value ) {
		$value = (string) $value;
		return function_exists( 'wp_kses_post' ) ? wp_kses_post( $value ) : strip_tags( $value, '<p><br><strong><em><b><i><ul><ol><li><a><blockquote><code><pre>' );
	}

	/** Clean text. */
	private static function clean_text( $value ) {
		return function_exists( 'sanitize_text_field' ) ? sanitize_text_field( $value ) : trim( strip_tags( (string) $value ) );
	}

	/** Clean textarea. */
	private static function clean_textarea( $value ) {
		return function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $value ) : trim( strip_tags( (string) $value ) );
	}

	/** Clean key. */
	private static function clean_key( $value ) {
		return function_exists( 'sanitize_key' ) ? sanitize_key( $value ) : strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $value ) );
	}
}
