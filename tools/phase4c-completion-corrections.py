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
replace_once(
    "includes/class-news-public-projector.php",
    "\tprivate static function body( $post ) {\n\t\t$content = self::content( $post );\n\t\tif ( function_exists( 'apply_filters' ) ) {\n\t\t\t$content = apply_filters( 'the_content', $content );\n\t\t}\n\t\treturn function_exists( 'wp_kses_post' ) ? wp_kses_post( $content ) : strip_tags( $content, '<p><br><strong><em><b><i><ul><ol><li><a><blockquote><code><pre><h2><h3><h4><figure><figcaption><img>' );\n\t}",
    "\tprivate static function body( $post ) {\n\t\t$content = self::content( $post );\n\t\tif ( function_exists( 'apply_filters' ) ) {\n\t\t\t$content = apply_filters( 'the_content', $content );\n\t\t}\n\t\t$content = function_exists( 'wp_kses_post' ) ? wp_kses_post( $content ) : strip_tags( $content, '<p><br><strong><em><b><i><ul><ol><li><a><blockquote><code><pre><h2><h3><h4><figure><figcaption><img>' );\n\t\treturn self::sanitize_content_html( $content );\n\t}\n\n\t/** Remove event handlers and reject unsafe URL schemes after rich-HTML filtering. */\n\tprivate static function sanitize_content_html( $content ) {\n\t\t$content = is_string( $content ) ? $content : '';\n\t\t$content = preg_replace( '/\\s+on[a-z0-9_-]+\\s*=\\s*(?:\"[^\"]*\"|\\'[^\\']*\\'|[^\\s>]+)/iu', '', $content );\n\t\t$content = preg_replace_callback(\n\t\t\t'/\\s+(href|src)\\s*=\\s*(?:\"([^\"]*)\"|\\'([^\\']*)\\'|([^\\s>]+))/iu',\n\t\t\tstatic function ( $match ) {\n\t\t\t\t$attribute = strtolower( (string) $match[1] );\n\t\t\t\t$value = isset( $match[2] ) && '' !== $match[2] ? $match[2] : ( isset( $match[3] ) && '' !== $match[3] ? $match[3] : ( isset( $match[4] ) ? $match[4] : '' ) );\n\t\t\t\t$value = html_entity_decode( trim( (string) $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );\n\t\t\t\t$probe = preg_replace( '/[\\x00-\\x20\\x7F]+/u', '', $value );\n\t\t\t\tif ( '' === $probe || 0 === strpos( $probe, '//' ) ) { return ''; }\n\t\t\t\t$parts = parse_url( $probe );\n\t\t\t\tif ( false === $parts ) { return ''; }\n\t\t\t\tif ( isset( $parts['scheme'] ) ) {\n\t\t\t\t\t$allowed = 'src' === $attribute ? array( 'http', 'https' ) : array( 'http', 'https', 'mailto' );\n\t\t\t\t\tif ( ! in_array( strtolower( (string) $parts['scheme'] ), $allowed, true ) ) { return ''; }\n\t\t\t\t}\n\t\t\t\t$escaped = function_exists( 'esc_attr' ) ? esc_attr( $value ) : htmlspecialchars( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );\n\t\t\t\treturn ' ' . $attribute . '=\"' . $escaped . '\"';\n\t\t\t},\n\t\t\t$content\n\t\t);\n\t\treturn is_string( $content ) ? $content : '';\n\t}",
    "public body protocol sanitizer",
)
