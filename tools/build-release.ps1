param([string]$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path)
$ErrorActionPreference = 'Stop'
$slug = 'sabri-complete-home-news-feed'
$base = '21-sabri-complete-home-news-feed-1.0.3-PRODUCTION-REJECTION-CORRECTIVE-CANDIDATE'
$releaseDir = Join-Path $Root 'release'
$stageDir = Join-Path $releaseDir '_stage'
$topDir = Join-Path $stageDir $slug
$zipPath = Join-Path $releaseDir "$base.zip"
$shaPath = Join-Path $releaseDir "$base.sha256"
$manifestPath = Join-Path $releaseDir "$base-MANIFEST.sha256"
$reportPath = Join-Path $releaseDir "$base-TEST-REPORT.md"
$required = @(
 'sabri-complete-home-news-feed.php','includes/class-public-surface-recovery.php','includes/class-corrective-public-mount.php',
 'includes/class-home-composition-registry.php','includes/class-public-query-guard.php','includes/class-integrations.php',
 'includes/class-rest-foundation.php','public/class-news-routing.php','public/class-phase5-public-runtime.php',
 'FILE-21-PRODUCTION-REJECTION-CORRECTIVE-1.0.3.md','readme.txt','CHANGELOG.md'
)
foreach ($relative in $required) { if (-not (Test-Path -LiteralPath (Join-Path $Root $relative) -PathType Leaf)) { throw "Missing required runtime file: $relative" } }
New-Item -ItemType Directory -Force -Path $releaseDir | Out-Null
if (Test-Path $stageDir) { Remove-Item $stageDir -Recurse -Force }
New-Item -ItemType Directory -Force -Path $topDir | Out-Null
$excludedDirs = @('.git','.github','tools','tests','release','vendor','node_modules','.phase5-transport')
$excludedFiles = @('TASK_LOG.md','.gitignore')
$forbiddenExtensions = @('.log','.tmp','.bak','.sql','.sqlite','.env')
Get-ChildItem -LiteralPath $Root -Force | ForEach-Object {
 if ($excludedDirs -contains $_.Name -or $excludedFiles -contains $_.Name) { return }
 $destination = Join-Path $topDir $_.Name
 if ($_.PSIsContainer) { Copy-Item $_.FullName $destination -Recurse -Force } elseif (-not ($forbiddenExtensions -contains $_.Extension.ToLowerInvariant())) { Copy-Item $_.FullName $destination -Force }
}
Get-ChildItem $topDir -Recurse -File | Where-Object { $forbiddenExtensions -contains $_.Extension.ToLowerInvariant() -or $_.Name -match '(^|[.\-_])(secret|credential|private-key)' } | Remove-Item -Force
$manifest = Get-ChildItem $topDir -Recurse -File | Sort-Object FullName | ForEach-Object {
 $relative = $_.FullName.Substring($stageDir.Length).TrimStart('\','/').Replace('\','/')
 $hash = (Get-FileHash $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
 "$hash  $relative"
}
$manifest | Set-Content $manifestPath -Encoding ASCII
foreach ($path in @($zipPath,$shaPath,$reportPath)) { if (Test-Path $path) { Remove-Item $path -Force } }
Compress-Archive -Path $topDir -DestinationPath $zipPath -CompressionLevel Optimal
$hash = (Get-FileHash $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
"$hash  $(Split-Path $zipPath -Leaf)" | Set-Content $shaPath -Encoding ASCII
@('# File 21 1.0.3 Production-Rejection Corrective Candidate','', '- Runtime: 1.0.3','- Schema: 1.0.0',"- Artifact: $(Split-Path $zipPath -Leaf)","- SHA-256: $hash",'- Historical alias packages: none','- Public GET recovery writes: disabled','- Editorial News gates: disabled by default','- Automatic publication/migration: disabled','- Live deployed: 0') | Set-Content $reportPath -Encoding UTF8
Remove-Item $stageDir -Recurse -Force
Write-Output "Built canonical package $zipPath"
