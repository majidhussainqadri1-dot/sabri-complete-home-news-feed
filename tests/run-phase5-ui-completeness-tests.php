<?php
$root=dirname(__DIR__);$failures=array();$assert=static function($c,$m)use(&$failures){if(!$c)$failures[]=$m;};
$admin=file_get_contents($root.'/admin/class-phase5-newsroom-admin.php');foreach(array('Overview','Submissions','Editorial Review','Fact Check Queue','Medical Review Queue','Translation Review','Editorial Calendar','Scheduled News','Breaking News','Sources','Corrections','Retracted News','Taxonomies','Settings','System Check','Audit Log','Release Readiness')as$label)$assert(false!==strpos($admin,$label),'Missing Newsroom screen '.$label);
$breaking=file_get_contents($root.'/templates/news-breaking-strip.php');foreach(array('aria-labelledby','<time','data-sabri-breaking-strip')as$needle)$assert(false!==strpos($breaking,$needle),'Breaking accessibility missing '.$needle);
$submission=file_get_contents($root.'/templates/news-submission-portal.php');foreach(array('<form','wp_nonce_field','<fieldset','required','aria-live')as$needle)$assert(false!==strpos($submission,$needle),'Submission UI missing '.$needle);
$css=file_get_contents($root.'/assets/css/phase5-public.css');foreach(array('prefers-reduced-motion','forced-colors','focus-visible','max-width:600px')as$needle)$assert(false!==strpos($css,$needle),'Public CSS missing '.$needle);
$single=file_get_contents($root.'/templates/news-single.php');$assert(false!==strpos($single,"do_action( 'sabri_news_after_article'"),'Single article supplement hook missing.');
if($failures){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}echo "Phase 5 UI completeness tests passed.\n";
