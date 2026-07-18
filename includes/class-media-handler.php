<?php
/**
 * Media upload and attachment safety.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uses WordPress media APIs with bounded validation.
 */
final class MediaHandler {
	/**
	 * Extensions never accepted by the public composer.
	 *
	 * @return array<int,string>
	 */
	public static function blocked_extensions() {
		return array( 'php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'js', 'html', 'htm', 'exe', 'sh', 'bash', 'bat', 'cmd', 'ps1', 'svg' );
	}

	/**
	 * MIME map for WordPress upload handling.
	 *
	 * @param array<string,mixed>|null $settings Settings.
	 * @return array<string,string>
	 */
	public static function allowed_mime_map( $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;
		$mimes    = isset( $settings['composer']['allowed_mime_types'] ) && is_array( $settings['composer']['allowed_mime_types'] ) ? $settings['composer']['allowed_mime_types'] : array();
		$map      = self::supported_mime_map();

		return array_filter(
			$map,
			static function ( $mime ) use ( $mimes ) {
				return in_array( $mime, $mimes, true );
			}
		);
	}

	/**
	 * Supported Phase 2 MIME map before administrator selection.
	 *
	 * @return array<string,string>
	 */
	public static function supported_mime_map() {
		return array(
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
			'pdf'          => 'application/pdf',
			'mp4|m4v'      => 'video/mp4',
			'mov|qt'       => 'video/quicktime',
			'mp3'          => 'audio/mpeg',
			'm4a|m4b'      => 'audio/mp4',
			'wav'          => 'audio/wav',
			'ogg|oga'      => 'audio/ogg',
		);
	}

	/**
	 * Validate a single upload array before WordPress handles it.
	 *
	 * @param array<string,mixed>      $file File.
	 * @param array<string,mixed>|null $settings Settings.
	 * @return array<string,mixed>
	 */
	public static function validate_upload( array $file, $settings = null ) {
		$settings = null === $settings ? Settings::get() : $settings;

		if ( empty( $settings['media']['uploads_enabled'] ) ) {
			return self::invalid( 'uploads_disabled', __( 'Media uploads are disabled.', 'sabri-complete-home-news-feed' ) );
		}

		if ( empty( $file['name'] ) || ! empty( $file['error'] ) ) {
			return self::invalid( 'upload_error', __( 'The upload did not complete successfully.', 'sabri-complete-home-news-feed' ) );
		}

		$name = function_exists( 'sanitize_file_name' ) ? sanitize_file_name( $file['name'] ) : basename( preg_replace( '/[^A-Za-z0-9._-]/', '-', (string) $file['name'] ) );
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( in_array( $ext, self::blocked_extensions(), true ) ) {
			return self::invalid( 'blocked_extension', __( 'This file type is not allowed.', 'sabri-complete-home-news-feed' ) );
		}

		$max_bytes = max( 1, (int) $settings['composer']['max_upload_mb'] ) * 1024 * 1024;
		if ( ! empty( $file['size'] ) && (int) $file['size'] > $max_bytes ) {
			return self::invalid( 'file_too_large', __( 'This file exceeds the configured upload limit.', 'sabri-complete-home-news-feed' ) );
		}

		$mime = isset( $file['type'] ) ? strtolower( trim( (string) $file['type'] ) ) : '';
		if ( function_exists( 'wp_check_filetype_and_ext' ) && ! empty( $file['tmp_name'] ) ) {
			$checked = wp_check_filetype_and_ext( $file['tmp_name'], $name, self::allowed_mime_map( $settings ) );
			if ( is_array( $checked ) && ! empty( $checked['type'] ) ) {
				$mime = strtolower( $checked['type'] );
			}
		}

		if ( ! in_array( $mime, array_values( self::allowed_mime_map( $settings ) ), true ) ) {
			return self::invalid( 'mime_not_allowed', __( 'This MIME type is not allowed.', 'sabri-complete-home-news-feed' ) );
		}

		return array(
			'valid' => true,
			'name'  => $name,
			'mime'  => $mime,
		);
	}

	/**
	 * Upload files through WordPress.
	 *
	 * @param array<string,mixed> $files Files.
	 * @param int                 $user_id User ID.
	 * @return array<string,mixed>
	 */
	public static function upload_files( array $files, $user_id = 0, array $context = array() ) {
		$user_id  = $user_id ? (int) $user_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );
		$settings = Settings::get();
		$result   = array(
			'uploaded' => array(),
			'errors'   => array(),
		);

		if ( ! ComposerPermissions::user_can_create( $user_id, $settings ) ) {
			$result['errors'][] = self::invalid( 'upload_denied', __( 'You do not have permission to upload media.', 'sabri-complete-home-news-feed' ) );
			return $result;
		}

		$normalized = self::normalize_files_array( $files );
		$max_items  = min( (int) $settings['media']['max_items'], (int) $settings['composer']['max_image_count'] );
		if ( count( $normalized ) > $max_items ) {
			$result['errors'][] = self::invalid( 'too_many_files', __( 'Too many files were uploaded.', 'sabri-complete-home-news-feed' ) );
			$normalized = array_slice( $normalized, 0, $max_items );
		}

		foreach ( $normalized as $file ) {
			$validation = self::validate_upload( $file, $settings );
			if ( empty( $validation['valid'] ) ) {
				$result['errors'][] = $validation;
				continue;
			}

			if ( ! function_exists( 'wp_handle_upload' ) || ! function_exists( 'wp_insert_attachment' ) ) {
				$result['errors'][] = self::invalid( 'media_api_unavailable', __( 'WordPress media APIs are unavailable.', 'sabri-complete-home-news-feed' ) );
				continue;
			}

			$upload = wp_handle_upload(
				$file,
				array(
					'test_form' => false,
					'mimes'     => self::allowed_mime_map( $settings ),
				)
			);

			if ( ! is_array( $upload ) || ! empty( $upload['error'] ) ) {
				$result['errors'][] = self::invalid( 'upload_failed', isset( $upload['error'] ) ? $upload['error'] : __( 'Upload failed.', 'sabri-complete-home-news-feed' ) );
				continue;
			}

			$attachment_id = wp_insert_attachment(
				array(
					'post_mime_type' => $upload['type'],
					'post_title'     => sanitize_text_field( pathinfo( $validation['name'], PATHINFO_FILENAME ) ),
					'post_excerpt'   => isset( $context['media_caption'] ) ? sanitize_text_field( $context['media_caption'] ) : '',
					'post_status'    => 'inherit',
					'post_author'    => $user_id,
				),
				$upload['file']
			);

			if ( ! empty( $context['media_alt_text'] ) && function_exists( 'update_post_meta' ) ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $context['media_alt_text'] ) );
			}

			if ( function_exists( 'wp_generate_attachment_metadata' ) && function_exists( 'wp_update_attachment_metadata' ) ) {
				$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
				wp_update_attachment_metadata( $attachment_id, $metadata );
			}

			$result['uploaded'][] = (int) $attachment_id;
		}

		return $result;
	}

	/**
	 * Validate attachment ownership.
	 *
	 * @param array<int,int> $attachment_ids Attachment IDs.
	 * @param int            $user_id User ID.
	 * @return bool
	 */
	public static function validate_attachment_ownership( array $attachment_ids, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : ( function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0 );

		foreach ( $attachment_ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			if ( $attachment_id <= 0 ) {
				return false;
			}

			$post = function_exists( 'get_post' ) ? get_post( $attachment_id ) : null;
			if ( ! $post || ( isset( $post->post_type ) && 'attachment' !== $post->post_type ) ) {
				return false;
			}

			if ( isset( $post->post_author ) && (int) $post->post_author === $user_id ) {
				continue;
			}

			if ( ! ComposerPermissions::user_can_moderate() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Normalize PHP files array.
	 *
	 * @param array<string,mixed> $files Files.
	 * @return array<int,array<string,mixed>>
	 */
	private static function normalize_files_array( array $files ) {
		if ( isset( $files['name'] ) && is_array( $files['name'] ) ) {
			$out = array();
			foreach ( $files['name'] as $index => $name ) {
				$out[] = array(
					'name'     => $name,
					'type'     => isset( $files['type'][ $index ] ) ? $files['type'][ $index ] : '',
					'tmp_name' => isset( $files['tmp_name'][ $index ] ) ? $files['tmp_name'][ $index ] : '',
					'error'    => isset( $files['error'][ $index ] ) ? $files['error'][ $index ] : 0,
					'size'     => isset( $files['size'][ $index ] ) ? $files['size'][ $index ] : 0,
				);
			}
			return $out;
		}

		if ( isset( $files['name'] ) ) {
			return array( $files );
		}

		return array();
	}

	/**
	 * Invalid result helper.
	 *
	 * @param string $code Code.
	 * @param string $message Message.
	 * @return array<string,mixed>
	 */
	private static function invalid( $code, $message ) {
		return array(
			'valid'   => false,
			'code'    => $code,
			'message' => $message,
		);
	}
}
