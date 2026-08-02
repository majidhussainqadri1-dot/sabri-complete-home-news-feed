from pathlib import Path


def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text(encoding="utf-8")
    if new in text:
        return
    if old not in text:
        raise SystemExit(f"Expected fragment not found in {path}: {old[:120]!r}")
    path.write_text(text.replace(old, new, 1), encoding="utf-8")


stubs = Path("tests/wp-stubs.php")
old_current = "function current_user_can( $capability ) { global $sabri_test_current_caps, $sabri_test_current_user_id, $sabri_test_user_roles, $sabri_test_roles; if ( (int) $sabri_test_current_user_id <= 0 ) { return false; } if ( ! empty( $sabri_test_current_caps[ $capability ] ) ) { return true; } foreach ( isset( $sabri_test_user_roles[ $sabri_test_current_user_id ] ) ? $sabri_test_user_roles[ $sabri_test_current_user_id ] : array() as $role_slug ) { if ( ! empty( $sabri_test_roles[ $role_slug ]->capabilities[ $capability ] ) ) { return true; } } return false; }"
new_current = """function current_user_can( $capability ) {
	global $sabri_test_current_caps, $sabri_test_current_user_id, $sabri_test_user_roles, $sabri_test_roles;
	if ( (int) $sabri_test_current_user_id <= 0 ) {
		return false;
	}
	if ( ! empty( $sabri_test_current_caps[ $capability ] ) ) {
		return true;
	}
	$roles = isset( $sabri_test_user_roles[ $sabri_test_current_user_id ] ) ? $sabri_test_user_roles[ $sabri_test_current_user_id ] : array();
	$default_map = class_exists( '\\Sabri\\HomeNewsFeed\\Capabilities' )
		? \\Sabri\\HomeNewsFeed\\Capabilities::default_role_map()
		: array();
	foreach ( $roles as $role_slug ) {
		if ( ! empty( $sabri_test_roles[ $role_slug ]->capabilities[ $capability ] ) ) {
			return true;
		}
		if ( ! empty( $default_map[ $role_slug ] ) && in_array( $capability, $default_map[ $role_slug ], true ) ) {
			return true;
		}
	}
	return false;
}"""
replace_once(stubs, old_current, new_current)

text = stubs.read_text(encoding="utf-8")
if "function user_can( $user_id, $capability )" not in text:
    user_can = """
function user_can( $user_id, $capability ) {
	global $sabri_test_current_caps, $sabri_test_current_user_id, $sabri_test_user_roles, $sabri_test_roles;
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return false;
	}
	if ( $user_id === (int) $sabri_test_current_user_id && ! empty( $sabri_test_current_caps[ $capability ] ) ) {
		return true;
	}
	$roles = isset( $sabri_test_user_roles[ $user_id ] ) ? $sabri_test_user_roles[ $user_id ] : array();
	$default_map = class_exists( '\\Sabri\\HomeNewsFeed\\Capabilities' )
		? \\Sabri\\HomeNewsFeed\\Capabilities::default_role_map()
		: array();
	foreach ( $roles as $role_slug ) {
		if ( ! empty( $sabri_test_roles[ $role_slug ]->capabilities[ $capability ] ) ) {
			return true;
		}
		if ( ! empty( $default_map[ $role_slug ] ) && in_array( $capability, $default_map[ $role_slug ], true ) ) {
			return true;
		}
	}
	return false;
}
"""
    marker = new_current + "\n"
    if marker not in text:
        raise SystemExit("Expanded current_user_can marker not found")
    stubs.write_text(text.replace(marker, marker + user_can, 1), encoding="utf-8")

run_tests = Path("tests/run-tests.php")
old_publish = """	$settings['capabilities']['verified_doctor_policy'] = 'publish';
	$sabri_test_current_user_id = 3;
	$verified_publish = ComposerPermissions::resolve_status_for_action( 'publish', 3, $settings );
	sabri_assert( ! empty( $verified_publish['allowed'] ) && 'publish' === $verified_publish['status'], 'Verified doctor publish policy must be configurable.' );
"""
new_publish = """	$settings['capabilities']['verified_doctor_policy'] = 'publish';
	$sabri_test_current_user_id = 3;
	$GLOBALS['sabri_test_membership_assertions'][3] = array(
		'account_class' => 'member',
		'membership_type' => 'doctor',
		'status' => 'active',
		'approved' => true,
		'eligible' => true,
		'guardian_verified' => true,
		'identity_evidence_current' => true,
		'two_factor_ready' => true,
		'session_two_factor' => true,
		'sensitive_action_ready' => true,
		'professional_verified' => true,
		'can_publish' => true,
		'can_practice' => true,
		'public_profile_allowed' => true,
		'suspended' => false,
	);
	$sabri_test_current_caps['sabri_feed_publish_posts'] = true;
	$verified_publish = ComposerPermissions::resolve_status_for_action( 'publish', 3, $settings );
	sabri_assert( ! empty( $verified_publish['allowed'] ) && 'publish' === $verified_publish['status'], 'Verified doctor publish policy must be configurable when File 00 also authorizes publication.' );
	unset( $GLOBALS['sabri_test_membership_assertions'][3], $sabri_test_current_caps['sabri_feed_publish_posts'] );
"""
replace_once(run_tests, old_publish, new_publish)

adapter_test = Path("tests/run-file21-file22-workflow-adapter-tests.php")
old_adapter = "final class CanonicalIdentityAdapter { public static function is_founder( int $user_id ): bool { return 1 === $user_id; } public static function is_administrator( int $user_id ): bool { return 99 === $user_id; } }"
new_adapter = "final class CanonicalIdentityAdapter { public static function current_action_ready( int $user_id = 0 ): bool { return in_array( $user_id, array( 1, 2, 99 ), true ); } public static function is_founder( int $user_id ): bool { return 1 === $user_id; } public static function is_administrator( int $user_id ): bool { return 99 === $user_id; } }"
replace_once(adapter_test, old_adapter, new_adapter)

print("File 21 legacy test fixtures aligned with the current File 00 authority contract.")
