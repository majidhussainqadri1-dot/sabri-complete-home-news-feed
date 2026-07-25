<?php
$root=dirname(__DIR__);$failures=array();$assert=static function($c,$m)use(&$failures){if(!$c)$failures[]=$m;};
$groups=array(
'A'=>array('PHASE-5-FINAL-COMPLETION-PLAN.md','PHASE-5-REQUIREMENTS-TRACEABILITY.md'),
'B'=>array('includes/class-phase5-database.php','includes/class-phase5-migrations.php','includes/class-phase5-repository.php'),
'C'=>array('includes/class-source-registry.php','includes/class-review-ledger.php'),
'D'=>array('includes/class-submission-service.php','includes/class-upload-security.php','templates/news-submission-portal.php'),
'E'=>array('includes/class-breaking-news-service.php','includes/class-correction-ledger.php','templates/news-breaking-strip.php'),
'F'=>array('includes/class-news-distribution.php','includes/class-translation-service.php'),
'G'=>array('includes/class-ssrf-guard.php','includes/class-preview-token-service.php','includes/class-privacy-scanner.php','includes/class-privacy-operations.php','includes/class-phase5-rate-limiter.php'),
'H'=>array('includes/class-phase5-performance.php','includes/class-phase5-diagnostics.php','includes/class-phase5-audit-integrity.php'),
'I'=>array('admin/class-phase5-newsroom-admin.php','public/class-phase5-public-runtime.php','assets/css/phase5-public.css','assets/css/phase5-admin.css'),
'J'=>array('tools/build-release.ps1','PHASE-5-RELEASE-ROLLBACK-RUNBOOK.md','.github/workflows/phase5-final-completion-tests.yml','.github/workflows/phase5-two-hour-visible-qa.yml'),
);
foreach($groups as$group=>$files)foreach($files as$file)$assert(is_file($root.'/'.$file),'Workstream '.$group.' missing '.$file);
$plan=file_get_contents($root.'/PHASE-5-FINAL-COMPLETION-PLAN.md');foreach(array('source and evidence registry','review ledgers','submission portal','Breaking News','correction','structured data','privacy export','performance','observability','rollback')as$needle)$assert(false!==stripos($plan,$needle),'Plan concept missing '.$needle);
if($failures){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);}echo "Phase 5 traceability tests passed.\n";
