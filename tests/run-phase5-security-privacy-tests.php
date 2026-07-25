<?php
require __DIR__ . '/phase5-stubs.php';
$root = dirname( __DIR__ );
foreach ( array( 'class-phase5-contracts.php','class-privacy-scanner.php','class-upload-security.php','class-ssrf-guard.php','class-preview-token-service.php','class-phase5-rest.php' ) as $file ) require_once $root . '/includes/' . $file;
use Sabri\HomeNewsFeed\PrivacyScanner;
use Sabri\HomeNewsFeed\UploadSecurity;
use Sabri\HomeNewsFeed\SsrfGuard;
$failures=array();$assert=static function($c,$m)use(&$failures){if(!$c)$failures[]=$m;};
foreach(array('http://127.0.0.1/a','http://10.0.0.1/a','http://localhost/a','file:///etc/passwd','http://user:pass@example.com/a')as$url)$assert(empty(SsrfGuard::validate_url($url)['success']),'SSRF guard accepted '.$url);
$assert(PrivacyScanner::scan('CNIC 35202-1234567-1')['blocked'],'CNIC was not blocked.');
$tmp=tempnam(sys_get_temp_dir(),'sabri-p5-');file_put_contents($tmp,"<?php sys" . "tem('id'); ?>");$result=UploadSecurity::validate_file($tmp,'image.jpg','image/jpeg');$assert(empty($result['success']),'PHP polyglot was accepted.');unlink($tmp);
$tmp=tempnam(sys_get_temp_dir(),'sabri-p5-');file_put_contents($tmp,"plain editorial text\n");$result=UploadSecurity::validate_file($tmp,'source.txt','text/plain');$assert(!empty($result['success']),'Safe text file was rejected.');unlink($tmp);
$rest=file_get_contents($root.'/includes/class-phase5-rest.php');foreach(array('X-WP-Nonce','wp_verify_nonce','phase5_payload_invalid','array_diff( array_keys( $params ), $allowed )','private, no-store')as$needle)$assert(false!==strpos($rest,$needle),'REST hardening missing '.$needle);
$rest_required = array( 'submission_file_upload', 'preview_resolve', 'translation_publish', 'allow_json', 'permission_callback', 'can_authenticated_write', 'noindex', 'Referrer-Policy' );
foreach ( $rest_required as $needle ) $assert( false !== strpos( $rest, $needle ), 'Required strict REST/preview boundary is missing ' . $needle );
$preview = file_get_contents( $root . '/includes/class-preview-token-service.php' );
foreach ( array( 'hash_hmac', 'token_hash', 'wp_kses_post' ) as $needle ) $assert( false !== strpos( $preview, $needle ), 'Preview token/projection hardening is missing ' . $needle );
foreach(array('ev' . 'al(','shell_' . 'ex' . 'ec(','pass' . 'thru(','proc_open(')as$forbidden){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/includes'))as$file){if(!$file->isFile()||$file->getExtension()!=='php')continue;$content=file_get_contents($file->getPathname());$assert(false===strpos($content,$forbidden),'Forbidden execution primitive in '.$file->getFilename());}}
if($failures){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}echo "Phase 5 security and privacy tests passed.\n";
