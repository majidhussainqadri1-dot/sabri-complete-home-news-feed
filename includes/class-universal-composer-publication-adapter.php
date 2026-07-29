<?php
/**
 * Native File 21 social-publication adapter for File 22.
 *
 * @package SabriCompleteHomeNewsFeed
 */

namespace Sabri\HomeNewsFeed;

use Sabri\UniversalComposer\Contracts\Diagnostic_Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes File 21's existing public Composer as a native route. Permanent
 * records, drafts, validation, moderation, media, and publication states remain
 * owned by File 21; File 22 receives no duplicate content copy.
 */
final class UniversalComposerPublicationAdapter implements Diagnostic_Adapter {
	public function api_version(): string {
		return UniversalComposerBridge::ADAPTER_API_VERSION;
	}

	public function key(): string {
		return UniversalComposerBridge::ADAPTER_KEY;
	}

	public function label(): string {
		return __( 'Social Post', 'sabri-complete-home-news-feed' );
	}

	public function description(): string {
		return __( 'Create an authorized Home Feed publication in the native File 21 Composer.', 'sabri-complete-home-news-feed' );
	}

	public function group(): string {
		return 'publishing';
	}

	public function icon(): string {
		return 'admin-post';
	}

	public function priority(): int {
		return 10;
	}

	public function native_module(): string {
		return SABRI_HNF_SLUG;
	}

	public function minimum_native_version(): string {
		return '1.0.3';
	}

	public function required_capability(): string {
		return 'sabri_feed_create_posts';
	}

	public function privacy_classification(): string {
		return 'public';
	}

	public function is_available(): bool {
		if ( ! defined( 'SABRI_HNF_VERSION' ) || version_compare( (string) SABRI_HNF_VERSION, $this->minimum_native_version(), '<' ) ) {
			return false;
		}

		if ( ! class_exists( __NAMESPACE__ . '\\PublicComposerSurface' ) || ! class_exists( __NAMESPACE__ . '\\ComposerPermissions' ) ) {
			return false;
		}

		$settings = Settings::get();
		return ! empty( $settings['composer']['public_composer_enabled'] )
			&& SafeMode::feature_enabled( 'composer' )
			&& '' !== $this->native_url();
	}

	public function can_create( int $user_id ): bool {
		return $user_id > 0
			&& $this->is_available()
			&& ComposerPermissions::user_can_create( $user_id, Settings::get() );
	}

	public function start_url( int $user_id ): string {
		return $this->can_create( $user_id ) ? $this->native_url() : '';
	}

	/**
	 * Privacy-safe System Check data. No user, post, draft, or patient content is
	 * included in this report.
	 *
	 * @return array<string,mixed>
	 */
	public function health_report(): array {
		$settings = Settings::get();
		return array(
			'adapter_key'              => $this->key(),
			'native_module'            => $this->native_module(),
			'actual_native_version'    => defined( 'SABRI_HNF_VERSION' ) ? (string) SABRI_HNF_VERSION : '',
			'minimum_native_version'   => $this->minimum_native_version(),
			'required_capability'      => $this->required_capability(),
			'privacy_classification'   => $this->privacy_classification(),
			'composer_setting_enabled' => ! empty( $settings['composer']['public_composer_enabled'] ),
			'composer_feature_enabled' => SafeMode::feature_enabled( 'composer' ),
			'native_route_available'   => '' !== $this->native_url(),
			'available'                => $this->is_available(),
		);
	}

	/** Return File 21's canonical native route, never a File 22 override. */
	private function native_url(): string {
		return function_exists( 'home_url' ) ? home_url( '/create-post/' ) : '/create-post/';
	}
}
