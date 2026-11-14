<?php
/**
 * Exact-source, isolated V12.3 CEN-PUB-001 publishing authority regression.
 * Repository truth only; not evidence of deployed WordPress acceptance.
 */

declare(strict_types=1);

namespace {
    define( 'ABSPATH', __DIR__ . '/' );
    $GLOBALS['v123_subjects'] = array();
    $GLOBALS['v123_current_actor'] = 1;
    $GLOBALS['v123_capabilities'] = array();

    class SMC_Contracts {
        public static function assertions( $user_id ) {
            return $GLOBALS['v123_subjects'][(int) $user_id] ?? array();
        }
    }

    class WP_User {
        public int $ID;
        public array $roles;
        public function __construct( int $id, array $roles = array() ) {
            $this->ID = $id;
            $this->roles = $roles;
        }
    }

    function absint( $value ): int { return abs( (int) $value ); }
    function sanitize_key( $value ): string { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) ?? ''; }
    function get_current_user_id(): int { return (int) $GLOBALS['v123_current_actor']; }
    function get_userdata( $user_id ) { return new WP_User( (int) $user_id ); }
    function user_can( $user_id, $capability ): bool { return ! empty( $GLOBALS['v123_capabilities'][(int) $user_id][(string) $capability] ); }
    function current_user_can( $capability ): bool { return user_can( get_current_user_id(), $capability ); }
    function __( $text, $domain = '' ): string { return $text; }

    function v123_grant( int $id, string $class, string $type, bool $direct, bool $verified, array $types = array() ): array {
        return array(
            'contract_version' => '1.2.0',
            'user_id' => $id,
            'account_class' => $class,
            'membership_type' => $type,
            'approved_membership_types' => $types,
            'approved' => true,
            'eligible' => true,
            'status' => 'approved',
            'suspended' => false,
            'guardian_verified' => true,
            'mfa_required' => false,
            'professional_verified' => $verified,
            'can_practice' => $verified && in_array( 'doctor', $types, true ),
            'can_publish' => true, // File 00: can_open_composer, NOT public publishing.
            'can_direct_publish' => $direct,
            'public_profile_allowed' => true,
            'publishing' => array(
                'can_open_composer' => true,
                'can_direct_publish' => $direct,
                'doctor_verification_claim' => $verified && in_array( 'doctor', $types, true ),
            ),
        );
    }
}

namespace Sabri\HomeNewsFeed {
    final class Settings {
        public static function get(): array { return array( 'capabilities' => array( 'verified_doctor_policy' => 'trusted' ) ); }
    }
    final class SafeMode {
        public static function public_features_disabled(): bool { return false; }
        public static function feature_enabled( $feature ): bool { return true; }
    }
}

namespace {
    require dirname( __DIR__ ) . '/includes/class-canonical-identity-adapter.php';
    require dirname( __DIR__ ) . '/includes/class-composer-permissions.php';
    require dirname( __DIR__ ) . '/includes/class-fifth-fresh-review-hardening.php';

    use Sabri\HomeNewsFeed\CanonicalIdentityAdapter as Identity;
    use Sabri\HomeNewsFeed\ComposerPermissions as Composer;
    use Sabri\HomeNewsFeed\FifthFreshReviewHardening as Hardening;

    $found = array();
    $assert = static function ( bool $ok, string $message ) use ( &$found ): void {
        if ( ! $ok ) { $found[] = $message; fwrite( STDERR, 'FAIL: ' . $message . "\n" ); }
    };

    $GLOBALS['v123_subjects'][1] = v123_grant( 1, 'founder', '', true, false );
    $GLOBALS['v123_subjects'][2] = v123_grant( 2, 'administrator', '', true, false );
    $GLOBALS['v123_subjects'][3] = v123_grant( 3, 'member', 'doctor', true, true, array( 'doctor' ) );
    $GLOBALS['v123_subjects'][4] = v123_grant( 4, 'member', 'member', false, false );
    $GLOBALS['v123_subjects'][5] = v123_grant( 5, 'member', 'publisher', true, false, array( 'publisher' ) );
    $GLOBALS['v123_subjects'][6] = v123_grant( 6, 'member', 'doctor', true, true, array( 'doctor' ) );
    $GLOBALS['v123_subjects'][6]['suspended'] = true;
    $GLOBALS['v123_subjects'][7] = v123_grant( 77, 'founder', '', true, false ); // wrong subject ID.
    $GLOBALS['v123_subjects'][8] = v123_grant( 8, 'member', 'publisher', true, false, array( 'publisher' ) );
    $GLOBALS['v123_subjects'][8]['publishing']['delegation'] = array(
        'subject_id' => 8, 'issuer_class' => 'founder', 'scope' => 'news',
        'expires_at' => '2099-01-01 00:00:00', 'revoked' => false
    ); // Unsupported unversioned delegation MUST NOT grant power.
    $GLOBALS['v123_subjects'][9] = v123_grant( 9, 'member', 'doctor', true, true, array( 'doctor' ) );
    $GLOBALS['v123_subjects'][9]['public_profile_allowed'] = false;
    $GLOBALS['v123_subjects'][10] = v123_grant( 10, 'founder', '', false, false ); // Composer-only Founder.

    foreach ( range( 1, 10 ) as $id ) {
        $GLOBALS['v123_capabilities'][$id] = array(
            'sabri_feed_publish_posts' => true,
            'sabri_feed_create_posts' => true,
            'manage_options' => $id === 2,
        );
    }

    $allowed = array( 1, 3 );
    foreach ( range( 1, 10 ) as $id ) {
        $result = Identity::can_publish_immediately( $id );
        $assert( $result === in_array( $id, $allowed, true ), "CEN-PUB-001 exact subject classifier for case {$id}" );
        $assert( Identity::is_trusted_publisher( $id ) === $result, "Trusted-publisher does not diverge for case {$id}" );
        $assert( Hardening::subject_is_allowed_public_social_publisher( $id ) === $result, "Runtime publish classifier does not diverge for case {$id}" );
    }

    $GLOBALS['v123_current_actor'] = 1;
    $assert( Composer::user_can_publish( 1 ), 'Current Founder may publish with explicit File00 grant and current capability' );
    $assert( Composer::subject_is_institutional_publisher( 1 ), 'Founder remains an institutional publisher' );
    $GLOBALS['v123_current_actor'] = 2;
    $assert( ! Composer::user_can_publish( 2 ), 'Administrator-only direct publication is denied even with manage_options' );
    $assert( ! Composer::subject_is_institutional_publisher( 2 ), 'Administrator is not intrinsically an institutional publisher' );
    $caps = Hardening::guard_social_publication_capabilities(
        array( 'sabri_feed_publish_posts' => true, 'sabri_feed_create_posts' => true ),
        array( 'sabri_feed_publish_posts' ),
        array(),
        new WP_User( 2 )
    );
    $assert( empty( $caps['sabri_feed_publish_posts'] ), 'Stale Administrator publication capability is stripped' );
    $GLOBALS['v123_current_actor'] = 3;
    $assert( Composer::user_can_publish( 3 ), 'Current verified Doctor may publish within direct File00 authority' );
    $GLOBALS['v123_current_actor'] = 4;
    $assert( ! Composer::user_can_publish( 4 ), 'Composer-only ordinary member cannot publish' );

    printf( "V12.3 CEN-PUB-001 source regression: %d failure(s).\n", count( $found ) );
    exit( $found ? 1 : 0 );
}
