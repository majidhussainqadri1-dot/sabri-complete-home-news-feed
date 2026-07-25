<?php
/**
 * Secure editorial upload validation.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Verifies extension, actual MIME, size, dangerous signatures, and privacy constraints. */
final class UploadSecurity {
	const MAX_BYTES = 10485760;

	public static function allowed_extensions() {
		return array( 'jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt', 'csv', 'docx' );
	}

	public static function allowed_mimes() {
		return array(
			'image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'text/plain',
			'text/csv', 'application/csv', 'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/zip',
		);
	}

	public static function validate_file( $path, $original_name, $declared_mime = '', $size = null ) {
		if ( ! is_string( $path ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			return self::error( 'phase5_upload_rejected', 'File is unavailable.' );
		}
		$size = null === $size ? filesize( $path ) : (int) $size;
		if ( $size < 1 || $size > self::MAX_BYTES ) {
			return self::error( 'phase5_upload_rejected', 'File size is not allowed.' );
		}
		$name = basename( (string) $original_name );
		$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, self::allowed_extensions(), true ) || 'svg' === $extension || preg_match( '/\.(?:php\d*|phtml|phar|js|html?|shtml|exe|sh|bat|cmd|ps1|jar)(?:\.|$)/i', $name ) ) {
			return self::error( 'phase5_upload_rejected', 'File extension is not allowed.' );
		}
		$actual = self::actual_mime( $path );
		if ( ! in_array( $actual, self::allowed_mimes(), true ) ) {
			return self::error( 'phase5_upload_rejected', 'Actual MIME type is not allowed.' );
		}
		if ( '' !== $declared_mime && ! self::mime_compatible( $extension, $actual, $declared_mime ) ) {
			return self::error( 'phase5_upload_rejected', 'Declared and actual MIME types conflict.' );
		}
		$signature = file_get_contents( $path, false, null, 0, min( 65536, $size ) );
		if ( ! is_string( $signature ) || self::dangerous_signature( $signature ) ) {
			return self::error( 'phase5_upload_rejected', 'Dangerous or polyglot content was detected.' );
		}
		if ( 'docx' === $extension && ! self::is_docx( $path ) ) {
			return self::error( 'phase5_upload_rejected', 'DOCX structure is invalid.' );
		}
		if ( 'pdf' === $extension && 0 !== strpos( $signature, '%PDF-' ) ) {
			return self::error( 'phase5_upload_rejected', 'PDF signature is invalid.' );
		}
		if ( in_array( $extension, array( 'jpg', 'jpeg', 'png', 'webp' ), true ) && false === @getimagesize( $path ) ) {
			return self::error( 'phase5_upload_rejected', 'Image structure is invalid.' );
		}
		return array(
			'success' => true,
			'data' => array(
				'original_name' => substr( $name, 0, 255 ),
				'extension' => $extension,
				'mime' => $actual,
				'size_bytes' => $size,
				'sha256' => hash_file( 'sha256', $path ),
			)
		);
	}

	public static function safe_filename( $original_name, $sha256 ) {
		$extension = strtolower( pathinfo( basename( (string) $original_name ), PATHINFO_EXTENSION ) );
		$extension = in_array( $extension, self::allowed_extensions(), true ) ? $extension : 'bin';
		return 'sabri-editorial-' . substr( preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $sha256 ) ), 0, 20 ) . '.' . $extension;
	}

	private static function actual_mime( $path ) {
		if ( class_exists( 'finfo' ) ) {
			$finfo = new \finfo( FILEINFO_MIME_TYPE );
			$mime = $finfo->file( $path );
			if ( is_string( $mime ) ) {
				return strtolower( trim( $mime ) );
			}
		}
		return '';
	}

	private static function mime_compatible( $extension, $actual, $declared ) {
		$declared = strtolower( trim( (string) $declared ) );
		$map = array(
			'jpg' => array( 'image/jpeg' ), 'jpeg' => array( 'image/jpeg' ), 'png' => array( 'image/png' ), 'webp' => array( 'image/webp' ),
			'pdf' => array( 'application/pdf' ), 'txt' => array( 'text/plain' ),
			'csv' => array( 'text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel' ),
			'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip' ),
		);
		return isset( $map[ $extension ] ) && in_array( $actual, $map[ $extension ], true ) && in_array( $declared, $map[ $extension ], true );
	}

	private static function dangerous_signature( $content ) {
		$patterns = array(
			'/<\?(?:php|=)/i', '/<script\b/i', '/\bon[a-z0-9_-]+\s*=/i', '/javascript\s*:/i',
			'/\b(?:eval|assert|system|shell_exec|passthru|proc_open|popen)\s*\(/i', '/MZ\x90\x00/s',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return true;
			}
		}
		return false;
	}

	private static function is_docx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return false;
		}
		$valid = false !== $zip->locateName( '[Content_Types].xml' ) && false !== $zip->locateName( 'word/document.xml' );
		for ( $i = 0; $valid && $i < $zip->numFiles; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			if ( preg_match( '#(^|/)(?:\.\.|[^/]+\.(?:php\d*|phtml|phar|js|exe|sh|bat|cmd|ps1))$#i', $name ) ) {
				$valid = false;
			}
		}
		$zip->close();
		return $valid;
	}

	private static function error( $code, $message ) {
		return array( 'success' => false, 'status' => 400, 'code' => $code, 'message' => $message );
	}
}
