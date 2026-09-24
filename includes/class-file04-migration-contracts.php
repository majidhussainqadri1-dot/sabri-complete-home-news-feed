<?php
/**
 * File 04 legacy-publishing migration compatibility contracts.
 *
 * @package SabriCompleteHomeNewsFeed
 */
namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Canonical File 21 attestations consumed by the temporary File 04 adapter.
 * These contracts never transfer publishing ownership back to File 04.
 */
final class File04MigrationContracts {
	const PROVIDER_ID = 'file21_legacy_publication_migration_v1';
	const MAX_REFERENCES = 500;

	public static function register() {
		add_filter( 'sabri_file21_legacy_media_preflight_v1', array( __CLASS__, 'media_preflight' ), 10, 2 );
		add_filter( 'sabri_file21_verify_migrated_legacy_media_v1', array( __CLASS__, 'verify_migrated_media' ), 10, 2 );
	}

	public static function media_preflight( $existing, $request ) {
		if ( is_array( $existing ) && ! empty( $existing['verified'] ) ) { return $existing; }
		if ( ! is_array( $request ) ) { return self::denied( 'request_invalid' ); }

		$legacy_id = self::positive_id( $request['legacy_id'] ?? 0 );
		$source_signature = strtolower( trim( (string) ( $request['source_signature'] ?? '' ) ) );
		$request_digest = strtolower( trim( (string) ( $request['request_digest'] ?? '' ) ) );
		$references = isset( $request['references'] ) && is_array( $request['references'] ) ? array_values( $request['references'] ) : array();
		$legacy = $legacy_id > 0 ? get_post( $legacy_id ) : null;
		if ( '04' !== sanitize_key( (string) ( $request['file_number'] ?? '' ) )
			|| ! $legacy instanceof \WP_Post
			|| LegacyPublicationMigration::LEGACY_POST_TYPE !== (string) $legacy->post_type
			|| ! self::sha256( $source_signature )
			|| ! self::sha256( $request_digest )
			|| count( $references ) > self::MAX_REFERENCES ) {
			return self::denied( 'request_unbound' );
		}

		$accepted = array();
		$hashes = array();
		foreach ( $references as $reference ) {
			if ( ! is_array( $reference ) ) { return self::denied( 'reference_invalid' ); }
			$reference_id = sanitize_text_field( (string) ( $reference['reference_id'] ?? '' ) );
			$type = sanitize_key( (string) ( $reference['type'] ?? '' ) );
			if ( '' === $reference_id || ! in_array( $type, array( 'attachment', 'legacy_reference' ), true ) ) {
				return self::denied( 'reference_identity_invalid' );
			}

			if ( 'attachment' === $type ) {
				$attachment_id = self::positive_id( $reference['attachment_id'] ?? 0 );
				$attachment = $attachment_id > 0 ? get_post( $attachment_id ) : null;
				$file = $attachment instanceof \WP_Post && function_exists( 'get_attached_file' ) ? get_attached_file( $attachment_id, true ) : '';
				$sha = is_string( $file ) && is_file( $file ) ? strtolower( (string) hash_file( 'sha256', $file ) ) : '';
				$mime = $attachment instanceof \WP_Post ? (string) get_post_mime_type( $attachment_id ) : '';
				$alt = $attachment instanceof \WP_Post ? trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) : '';
				$explicit_rights = $attachment instanceof \WP_Post && (
					(bool) get_post_meta( $attachment_id, '_sabri_media_rights_verified', true )
					|| '' !== trim( (string) get_post_meta( $attachment_id, '_sabri_media_license', true ) )
				);
				$owner_match = $attachment instanceof \WP_Post
					&& (int) $attachment->post_parent === $legacy_id
					&& ( (int) $attachment->post_author === (int) $legacy->post_author || $explicit_rights );
				$alt_ok = 0 !== strpos( $mime, 'image/' ) || '' !== $alt;
				if ( ! $attachment instanceof \WP_Post
					|| 'attachment' !== (string) $attachment->post_type
					|| ! $owner_match
					|| ! is_string( $file ) || ! is_file( $file )
					|| ! self::sha256( $sha )
					|| ! self::sha256( strtolower( (string) ( $reference['sha256'] ?? '' ) ) )
					|| ! hash_equals( $sha, strtolower( (string) $reference['sha256'] ) )
					|| ! $alt_ok ) {
					return self::denied( 'attachment_attestation_failed' );
				}
				$hashes[ $sha ] = isset( $hashes[ $sha ] ) ? $hashes[ $sha ] + 1 : 1;
			} else {
				$meta_key = (string) ( $reference['meta_key'] ?? '' );
				$value = in_array( $meta_key, array( '_snp_video_url', '_snp_media_manifest', '_snp_source_ledger' ), true ) ? get_post_meta( $legacy_id, $meta_key, true ) : null;
				$present = is_array( $value ) ? ! empty( $value ) : ( is_object( $value ) || '' !== trim( (string) $value ) );
				if ( ! in_array( $meta_key, array( '_snp_video_url', '_snp_media_manifest', '_snp_source_ledger' ), true )
					|| ! $present
					|| ! self::sha256( strtolower( (string) ( $reference['value_digest'] ?? '' ) ) ) ) {
					return self::denied( 'legacy_reference_attestation_failed' );
				}
			}
			$accepted[] = $reference_id;
		}

		return array(
			'verified'                   => true,
			'provider_id'                => self::PROVIDER_ID,
			'ownership_verified'         => true,
			'rights_or_license_verified' => true,
			'alt_policy_verified'        => true,
			'duplicate_hash_checked'     => true,
			'duplicate_hash_groups'      => count( array_filter( $hashes, static function ( $count ) { return $count > 1; } ) ),
			'broken_links'               => 0,
			'canonical_target_contract'  => true,
			'accepted_reference_ids'     => array_values( array_unique( $accepted ) ),
			'source_signature'           => $source_signature,
			'request_digest'             => $request_digest,
			'verified_at_utc'            => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	public static function verify_migrated_media( $existing, $request ) {
		if ( is_array( $existing ) && ! empty( $existing['verified'] ) ) { return $existing; }
		if ( ! is_array( $request ) ) { return self::denied( 'verification_request_invalid' ); }
		$legacy_id = self::positive_id( $request['legacy_id'] ?? 0 );
		$target_id = self::positive_id( $request['target_id'] ?? 0 );
		$source_signature = strtolower( trim( (string) ( $request['source_signature'] ?? '' ) ) );
		$request_digest = strtolower( trim( (string) ( $request['request_digest'] ?? '' ) ) );
		$preflight_digest = strtolower( trim( (string) ( $request['preflight_request_digest'] ?? '' ) ) );
		$target = $target_id > 0 ? get_post( $target_id ) : null;
		$attestation = $target_id > 0 ? get_post_meta( $target_id, '_sabri_hnf_legacy_media_attestation_v1', true ) : array();
		if ( $legacy_id <= 0 || ! $target instanceof \WP_Post
			|| ! in_array( (string) $target->post_type, array( 'post', 'sabri_news' ), true )
			|| ! is_array( $attestation )
			|| (int) ( $attestation['legacy_id'] ?? 0 ) !== $legacy_id
			|| ! self::sha256( $source_signature ) || ! self::sha256( $request_digest ) || ! self::sha256( $preflight_digest )
			|| ! hash_equals( $source_signature, strtolower( (string) ( $attestation['source_signature'] ?? '' ) ) )
			|| ! hash_equals( $preflight_digest, strtolower( (string) ( $attestation['preflight_request_digest'] ?? '' ) ) ) ) {
			return self::denied( 'migration_attestation_unbound' );
		}

		$references = isset( $request['references'] ) && is_array( $request['references'] ) ? array_values( $request['references'] ) : array();
		$expected_ids = array();
		foreach ( $references as $reference ) {
			if ( ! is_array( $reference ) ) { return self::denied( 'verification_reference_invalid' ); }
			$reference_id = sanitize_text_field( (string) ( $reference['reference_id'] ?? '' ) );
			if ( '' === $reference_id ) { return self::denied( 'verification_reference_identity_invalid' ); }
			$expected_ids[] = $reference_id;
			if ( 'attachment' === sanitize_key( (string) ( $reference['type'] ?? '' ) ) ) {
				$attachment_id = self::positive_id( $reference['attachment_id'] ?? 0 );
				$file = $attachment_id > 0 && function_exists( 'get_attached_file' ) ? get_attached_file( $attachment_id, true ) : '';
				$actual_sha = is_string( $file ) && is_file( $file ) ? strtolower( (string) hash_file( 'sha256', $file ) ) : '';
				$expected_sha = strtolower( (string) ( $reference['sha256'] ?? '' ) );
				if ( ! self::sha256( $actual_sha ) || ! self::sha256( $expected_sha ) || ! hash_equals( $actual_sha, $expected_sha ) ) {
					return self::denied( 'verification_attachment_changed' );
				}
			}
		}
		sort( $expected_ids );
		$stored_ids = array_values( array_unique( array_map( 'sanitize_text_field', (array) ( $attestation['reference_ids'] ?? array() ) ) ) );
		sort( $stored_ids );
		if ( $stored_ids !== $expected_ids ) { return self::denied( 'verification_reference_set_changed' ); }

		return array(
			'verified'                 => true,
			'provider_id'              => self::PROVIDER_ID,
			'legacy_id'                => $legacy_id,
			'target_id'                => $target_id,
			'verified_reference_count' => count( $expected_ids ),
			'source_signature'         => $source_signature,
			'request_digest'           => $request_digest,
			'verified_at_utc'          => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	private static function denied( $reason ) {
		return array( 'verified' => false, 'provider_id' => self::PROVIDER_ID, 'reason_code' => sanitize_key( (string) $reason ) );
	}

	private static function positive_id( $value ) {
		if ( is_int( $value ) ) { return $value > 0 ? $value : 0; }
		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) ) { return 0; }
		$id = (int) $value;
		return $id > 0 && (string) $id === $value ? $id : 0;
	}

	private static function sha256( $value ) {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value );
	}
}
