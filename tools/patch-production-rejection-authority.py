from pathlib import Path
import re

path = Path('tests/run-file21-production-rejection-tests.php')
text = path.read_text(encoding='utf-8')

old_loop = re.compile(
    r"foreach \( array\( 'Role precedence is intentional'.*?\) as \$needle \) \{\n"
    r"\t\$assert\( false !== strpos\( \$permissions, \$needle \), 'Authority-precedence contract missing: ' \. \$needle \);\n"
    r"\}\n",
    re.S,
)
new_loop = """foreach ( array(
	'CanonicalIdentityAdapter::current_action_ready',
	'CanonicalIdentityAdapter::can_create_social_content',
	'CanonicalIdentityAdapter::can_publish_immediately',
	"current_user_can_any( array( 'sabri_feed_create_posts', 'manage_options' ) )",
	"current_user_can_any( array( 'sabri_feed_publish_posts', 'manage_options' ) )",
	'self::current_actor_matches',
) as $needle ) {
	$assert( false !== strpos( $permissions, $needle ), 'Subject-bound File 00 authority contract missing: ' . $needle );
}
"""
text, count = old_loop.subn(new_loop, text, count=1)
if count != 1 and 'Subject-bound File 00 authority contract missing' not in text:
    raise SystemExit('Stale authority marker loop was not found.')

authority_block = re.compile(
    r"\$authority_test = \$root \. '/tests/run-composer-authority-precedence-tests\.php';\n"
    r"\$assert\( is_file\( \$authority_test \), 'Authority-precedence behavior test is missing\.' \);\n"
    r"if \( is_file\( \$authority_test \) \) \{\n"
    r"\t\$authority_runner = static function \( \$test_file \) \{ require \$test_file; \};\n"
    r"\t\$authority_runner\( \$authority_test \);\n"
    r"\}\n",
    re.S,
)
new_authority_block = """$authority_test = $root . '/tests/run-composer-authority-precedence-tests.php';
$assert( is_file( $authority_test ), 'Authority-precedence behavior test is missing.' );
// This behavior suite owns global test symbols and is intentionally executed
// as a separate process by the exact-head workflow. Requiring it here would
// create a false collision rather than test production behavior.
"""
text, count = authority_block.subn(new_authority_block, text, count=1)
if count != 1 and 'intentionally executed' not in text:
    raise SystemExit('Nested authority test runner block was not found.')

path.write_text(text, encoding='utf-8')
print('Production-rejection assertions updated for subject-bound File 00 authority.')
