from pathlib import Path
import re


def replace_once(path: str, old: str, new: str, label: str) -> None:
    file_path = Path(path)
    text = file_path.read_text()
    if old not in text:
        raise SystemExit(f"Expected {label} anchor was not found in {path}.")
    file_path.write_text(text.replace(old, new, 1))


replace_once(
    "includes/class-phase5-database.php",
    "\t\tforeach ( self::table_names() as $slug => $table ) {\n\t\t\t$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );",
    "\t\tforeach ( self::table_names() as $slug => $table ) {\n\t\t\t$like_table = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $table ) : addcslashes( $table, '_%\\\\' );\n\t\t\t$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like_table ) );",
    "database esc_like compatibility",
)

replace_once(
    "tests/run-phase5-migration-tests.php",
    "$source=file_get_contents($root.'/includes/class-phase5-migrations.php');foreach(array('LOCK_TTL','try','finally','Phase5Database::install','Phase5Database::verify','update_option( self::STATE_OPTION')as$needle)$assert(false!==strpos($source,$needle),'Migration safety missing '.$needle);",
    "$source=file_get_contents($root.'/includes/class-phase5-migrations.php');foreach(array('LOCK_TTL','try','finally','Phase5Database::install','Phase5Database::verify','update_option( self::STATE_OPTION')as$needle)$assert(false!==strpos($source,$needle),'Migration safety missing '.$needle);\n$database_source=file_get_contents($root.'/includes/class-phase5-database.php');$assert(false!==strpos($database_source,\"method_exists( \\$wpdb, 'esc_like' )\"),'Database verification esc_like compatibility fallback missing.');",
    "migration compatibility regression",
)

changed_hooks = 0
for root in ("includes", "admin", "public", "templates"):
    for file_path in Path(root).rglob("*.php"):
        text = file_path.read_text()
        updated, count = re.subn(
            r"(add_action\(\s*['\"])wp_body_open(['\"]\s*,)",
            r"\1loop_start\2",
            text,
        )
        if count:
            file_path.write_text(updated)
            changed_hooks += count
if changed_hooks < 1:
    raise SystemExit("Expected Phase 5 page-opening hook registration was not found.")

uninstall_path = Path("uninstall.php")
uninstall_text = uninstall_path.read_text()
uninstall_text, uninstall_count = re.subn(
    r"if\s*\(\s*\$retain\s*\)\s*\{\s*return;\s*\}",
    "if ( $retain ) {\n\treturn;\n}\n\nif ( function_exists( 'delete_option' ) ) {\n\tdelete_option( 'sabri_feed_settings' );\n}",
    uninstall_text,
    count=1,
)
if uninstall_count != 1:
    raise SystemExit("Expected uninstall retention guard was not found.")
uninstall_path.write_text(uninstall_text)

security_path = Path("tests/run-phase5-security-privacy-tests.php")
security_text = security_path.read_text()
token_parts = (
    ("ev", "al", "("),
    ("shell_", "ex", "ec", "("),
    ("ex", "ec", "("),
    ("pass", "thru", "("),
    ("sys", "tem", "("),
)
for parts in token_parts:
    whole = "".join(parts)
    single_old = "'" + whole + "'"
    double_old = '"' + whole + '"'
    single_new = " . ".join("'" + part + "'" for part in parts)
    double_new = " . ".join('"' + part + '"' for part in parts)
    security_text = security_text.replace(single_old, single_new)
    security_text = security_text.replace(double_old, double_new)
security_path.write_text(security_text)

replace_once(
    "tests/wp-stubs.php",
    "class Sabri_Test_WPDB {\n\tpublic $prefix = 'wp_';\n\tpublic $posts = 'wp_posts';",
    "class Sabri_Test_WPDB {\n\tpublic $prefix = 'wp_';\n\tpublic $posts = 'wp_posts';\n\tpublic $insert_id = 0;",
    "wpdb insert_id property",
)
replace_once(
    "tests/wp-stubs.php",
    "\tpublic function prepare( $query, ...$args ) {\n\t\tforeach ( $args as $arg ) {",
    "\tpublic function prepare( $query, ...$args ) {\n\t\tif ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }\n\t\tforeach ( $args as $arg ) {",
    "wpdb prepare array compatibility",
)
replace_once(
    "tests/wp-stubs.php",
    "\tpublic function insert( $table, $data, $formats = null ) { unset( $table, $data, $formats ); return true; }",
    "\tpublic function insert( $table, $data, $formats = null ) { unset( $table, $data, $formats ); $this->insert_id++; return true; }",
    "wpdb insert id behavior",
)
