from pathlib import Path


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
