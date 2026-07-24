from pathlib import Path


def replace_once(path: str, old: str, new: str, label: str) -> None:
    file_path = Path(path)
    text = file_path.read_text()
    if old not in text:
        raise SystemExit(f"Expected {label} anchor was not found in {path}.")
    file_path.write_text(text.replace(old, new, 1))


replace_once(
    "tests/run-phase4c-public-news-tests.php",
    "$unknown_payload = isset($unknown_rest['payload'])?$unknown_rest['payload']:array();",
    "$unknown_payload = is_object( $unknown_rest ) && method_exists( $unknown_rest, 'get_data' ) ? $unknown_rest->get_data() : ( isset( $unknown_rest['payload'] ) ? $unknown_rest['payload'] : array() );",
    "public REST response",
)
replace_once(
    "tests/run-phase4c-public-news-tests.php",
    "sabri_phase4c_assert( empty($sabri_test_enqueued_styles['sabri-hnf-news']), 'News CSS loaded on a feed with no News card.' );",
    "sabri_phase4c_assert( !in_array('sabri-hnf-news',$sabri_test_enqueued_styles,true), 'News CSS loaded on a feed with no News card.' );",
    "feed-without-News asset",
)
replace_once(
    "tests/run-phase4c-public-news-tests.php",
    "sabri_phase4c_assert( !empty($sabri_test_enqueued_styles['sabri-hnf-news']) && !empty($sabri_test_enqueued_scripts['sabri-hnf-news']), 'News assets did not load when a News card rendered.' );",
    "sabri_phase4c_assert( in_array('sabri-hnf-news',$sabri_test_enqueued_styles,true) && in_array('sabri-hnf-news',$sabri_test_enqueued_scripts,true), 'News assets did not load when a News card rendered.' );",
    "rendered-News asset",
)
replace_once(
    "tests/run-phase4c-security-tests.php",
    "$payload = isset( $unknown['payload'] ) ? $unknown['payload'] : array();\n$assert( 400 === ( isset( $unknown['status'] ) ? $unknown['status'] : 0 ) && 'public_news_filter_invalid' === ( isset( $payload['code'] ) ? $payload['code'] : '' ), 'REST unknown parameters were not rejected.' );",
    "$payload = is_object( $unknown ) && method_exists( $unknown, 'get_data' ) ? $unknown->get_data() : ( isset( $unknown['payload'] ) ? $unknown['payload'] : array() );\n$unknown_status = is_object( $unknown ) && method_exists( $unknown, 'get_status' ) ? $unknown->get_status() : ( isset( $unknown['status'] ) ? $unknown['status'] : 0 );\n$assert( 400 === $unknown_status && 'public_news_filter_invalid' === ( isset( $payload['code'] ) ? $payload['code'] : '' ), 'REST unknown parameters were not rejected.' );",
    "security REST response",
)
replace_once(
    "includes/class-news-public-projector.php",
    "\tprivate static function safe_url( $value ) {\n\t\t$value = is_string( $value ) ? trim( $value ) : '';\n\t\tif ( '' === $value ) { return ''; }\n\t\t$value = function_exists( 'esc_url_raw' ) ? esc_url_raw( $value, array( 'http', 'https' ) ) : filter_var( $value, FILTER_VALIDATE_URL );\n\t\treturn is_string( $value ) ? $value : '';\n\t}",
    "\tprivate static function safe_url( $value ) {\n\t\t$value = is_string( $value ) ? trim( $value ) : '';\n\t\tif ( '' === $value || preg_match( '/[\\x00-\\x1F\\x7F]/', $value ) ) { return ''; }\n\t\t$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $value ) : parse_url( $value );\n\t\tif ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) { return ''; }\n\t\t$scheme = strtolower( (string) $parts['scheme'] );\n\t\tif ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) { return ''; }\n\t\t$clean = function_exists( 'esc_url_raw' ) ? esc_url_raw( $value, array( 'http', 'https' ) ) : filter_var( $value, FILTER_VALIDATE_URL );\n\t\tif ( ! is_string( $clean ) || '' === $clean ) { return ''; }\n\t\t$clean_parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $clean ) : parse_url( $clean );\n\t\treturn is_array( $clean_parts ) && isset( $clean_parts['scheme'], $clean_parts['host'] ) && in_array( strtolower( (string) $clean_parts['scheme'] ), array( 'http', 'https' ), true ) ? $clean : '';\n\t}",
    "public URL sanitizer",
)
