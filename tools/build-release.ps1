param(
    [string]$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
)

$ErrorActionPreference = 'Stop'
$slug = 'sabri-complete-home-news-feed'
$base = '21-sabri-complete-home-news-feed-1.0.2-PUBLIC-VISIBILITY-CANDIDATE'
$harmonizedBase = '21-sabri-complete-home-news-feed-1.0.2-HARMONIZED-CANDIDATE'
$phase3Base = '21-sabri-complete-home-news-feed-1.0.2-PHASE-3-STAGING-CANDIDATE'
$compatibilityBase = '21-sabri-complete-home-news-feed-1.0.2-COMPATIBILITY-CANDIDATE'
$historicalBase = '21-sabri-complete-home-news-feed-1.0.1-HARMONIZED-CANDIDATE'
$releaseDir = Join-Path $Root 'release'
$stageDir = Join-Path $releaseDir '_stage'
$topDir = Join-Path $stageDir $slug

$zipPath = Join-Path $releaseDir "$base.zip"
$shaPath = Join-Path $releaseDir "$base.sha256"
$manifestPath = Join-Path $releaseDir "$base-MANIFEST.sha256"
$reportPath = Join-Path $releaseDir "$base-TEST-REPORT.md"

$aliases = @(
    @{ Base = $harmonizedBase; Report = $true; Manifest = $true },
    @{ Base = $phase3Base; Report = $true; Manifest = $true },
    @{ Base = $compatibilityBase; Report = $true; Manifest = $true },
    @{ Base = $historicalBase; Report = $true; Manifest = $true }
)

$requiredRuntimeFiles = @(
    'sabri-complete-home-news-feed.php',
    'includes/class-plugin.php',
    'includes/class-public-surface-recovery.php',
    'includes/class-corrective-public-settings.php',
    'includes/class-corrective-public-mount.php',
    'includes/class-corrective-activation-wizard.php',
    'includes/class-profile-timeline.php',
    'includes/class-rest-profile-timeline.php',
    'includes/class-privileged-publishing-policy.php',
    'includes/class-legacy-founder-post-migration.php',
    'includes/class-activator.php',
    'includes/class-deactivator.php',
    'includes/class-canonical-identity-adapter.php',
    'includes/class-companion-integration-registry.php',
    'includes/class-companion-home-row-adapters.php',
    'includes/class-search-provider-registry.php',
    'includes/class-viral-ranking-signals.php',
    'includes/class-home-composition-registry.php',
    'includes/class-legacy-interaction-migration-adapter.php',
    'includes/class-legacy-publication-migration.php',
    'includes/class-legacy-publication-rollback.php',
    'includes/class-harmonization-diagnostics.php',
    'includes/class-harmonized-settings.php',
    'includes/class-phase5-contracts.php',
    'includes/class-phase5-feature-settings.php',
    'includes/class-phase5-capabilities.php',
    'includes/class-phase5-database.php',
    'includes/class-phase5-migrations.php',
    'includes/class-phase5-repository.php',
    'includes/class-phase5-audit-integrity.php',
    'includes/class-source-registry.php',
    'includes/class-review-ledger.php',
    'includes/class-upload-security.php',
    'includes/class-privacy-scanner.php',
    'includes/class-phase5-rate-limiter.php',
    'includes/class-preview-token-service.php',
    'includes/class-submission-service.php',
    'includes/class-breaking-news-service.php',
    'includes/class-correction-ledger.php',
    'includes/class-translation-service.php',
    'includes/class-ssrf-guard.php',
    'includes/class-privacy-operations.php',
    'includes/class-news-distribution.php',
    'includes/class-phase5-publication-policy.php',
    'includes/class-phase5-rest.php',
    'includes/class-phase5-performance.php',
    'includes/class-phase5-diagnostics.php',
    'admin/class-corrective-admin.php',
    'admin/class-phase5-newsroom-admin.php',
    'admin/views/corrective-wizard.php',
    'admin/views/migration.php',
    'admin/views/system-check.php',
    'public/class-phase5-public-runtime.php',
    'templates/news-breaking-strip.php',
    'templates/news-sources-history.php',
    'templates/news-submission-portal.php',
    'assets/css/home-composition.css',
    'assets/css/corrective-public.css',
    'assets/css/phase5-public.css',
    'assets/css/phase5-admin.css',
    'assets/js/phase5-public.js',
    'assets/js/phase5-admin.js',
    'FILE-21-HARMONIZATION-COMPLETION-PLAN.md',
    'FILE-21-PUBLIC-VISIBILITY-RECOVERY-1.0.2.md',
    'FILE-21-LIVE-VISUAL-ACCEPTANCE-CHECKLIST.md',
    'PHASE-5-DATABASE-SCHEMA-MANIFEST.md',
    'PHASE-5-MIGRATION-UPGRADE-GUIDE.md',
    'PHASE-5-ROLE-CAPABILITY-MATRIX.md',
    'PHASE-5-EDITORIAL-OPERATOR-RUNBOOK.md',
    'PHASE-5-SECURITY-PRIVACY-RUNBOOK.md',
    'PHASE-5-RELEASE-ROLLBACK-RUNBOOK.md',
    'uninstall.php'
)

foreach ($relativePath in $requiredRuntimeFiles) {
    $requiredPath = Join-Path $Root $relativePath
    if (-not (Test-Path -LiteralPath $requiredPath -PathType Leaf)) {
        throw "Required File 21 runtime file is missing: $relativePath"
    }
}

New-Item -ItemType Directory -Force -Path $releaseDir | Out-Null
$resolvedRelease = (Resolve-Path $releaseDir).Path
if (Test-Path -LiteralPath $stageDir) {
    $resolvedStage = (Resolve-Path $stageDir).Path
    if (-not $resolvedStage.StartsWith($resolvedRelease, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to remove a staging path outside the release directory: $resolvedStage"
    }
    Remove-Item -LiteralPath $stageDir -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $topDir | Out-Null

$excludedDirs = @('.git', '.github', 'tools', 'tests', 'release', 'vendor', 'node_modules', '.phase5-transport')
$excludedFiles = @('TASK_LOG.md', '.gitignore', 'PHASE-5-FINAL-COMPLETION-PLAN.md', 'PHASE-5-REQUIREMENTS-TRACEABILITY.md', 'PHASE-5-DEFECT-REGISTER.md', 'PHASE-5-WORKSTREAM-MAP.md', 'PHASE-5-IMPLEMENTATION-STATUS.md', 'PHASE-5-IMPLEMENTATION-BOUNDARIES.md', 'PHASE-5-PR-DESCRIPTION.md', 'PHASE-5-IMPLEMENTATION-PR.md', 'PHASE-5-ACCEPTANCE-CONTRACT.md')
$forbiddenExtensions = @('.log', '.tmp', '.bak', '.sql', '.sqlite', '.env')
$copied = 0
Get-ChildItem -LiteralPath $Root -Force | ForEach-Object {
    if ($excludedDirs -contains $_.Name -or $excludedFiles -contains $_.Name) { return }
    if (-not $_.PSIsContainer -and $forbiddenExtensions -contains $_.Extension.ToLowerInvariant()) { return }
    $destination = Join-Path $topDir $_.Name
    if ($_.PSIsContainer) {
        Copy-Item -LiteralPath $_.FullName -Destination $destination -Recurse -Force
        Get-ChildItem -LiteralPath $destination -Recurse -File | Where-Object {
            $forbiddenExtensions -contains $_.Extension.ToLowerInvariant() -or $_.Name -match '(^|\.)secret|credential|private-key'
        } | Remove-Item -Force
        $copied += (Get-ChildItem -LiteralPath $destination -Recurse -File | Measure-Object).Count
    } else {
        Copy-Item -LiteralPath $_.FullName -Destination $destination -Force
        $copied++
    }
}

foreach ($relativePath in $requiredRuntimeFiles) {
    $stagedPath = Join-Path $topDir $relativePath
    if (-not (Test-Path -LiteralPath $stagedPath -PathType Leaf)) {
        throw "Required File 21 runtime file was not staged: $relativePath"
    }
}

$forbiddenPackagePaths = @('.git/', '.github/', 'tools/', 'tests/', 'release/', 'node_modules/', 'vendor/', '.phase5-transport/')
Get-ChildItem -LiteralPath $topDir -Recurse -File | ForEach-Object {
    $relative = $_.FullName.Substring($topDir.Length).TrimStart('\', '/').Replace('\', '/')
    foreach ($prefix in $forbiddenPackagePaths) {
        if ($relative.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            throw "Forbidden development path entered package: $relative"
        }
    }
}

$generatedPaths = @($zipPath, $shaPath, $manifestPath, $reportPath)
foreach ($alias in $aliases) {
    $generatedPaths += Join-Path $releaseDir "$($alias.Base).zip"
    $generatedPaths += Join-Path $releaseDir "$($alias.Base).sha256"
    if ($alias.Manifest) { $generatedPaths += Join-Path $releaseDir "$($alias.Base)-MANIFEST.sha256" }
    if ($alias.Report) { $generatedPaths += Join-Path $releaseDir "$($alias.Base)-TEST-REPORT.md" }
}
foreach ($path in $generatedPaths) {
    if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path -Force }
}

$manifestLines = @()
Get-ChildItem -LiteralPath $topDir -Recurse -File | Sort-Object FullName | ForEach-Object {
    $relative = $_.FullName.Substring($topDir.Length).TrimStart('\', '/').Replace('\', '/')
    $fileHash = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
    $manifestLines += "$fileHash  $slug/$relative"
}
$manifestLines | Set-Content -LiteralPath $manifestPath -Encoding ASCII

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $archive.CreateEntry("$slug/") | Out-Null
    Get-ChildItem -LiteralPath $topDir -Recurse -Directory | Sort-Object FullName | ForEach-Object {
        $relativeDir = $_.FullName.Substring($topDir.Length).TrimStart('\', '/').Replace('\', '/')
        if ($relativeDir) { $archive.CreateEntry("$slug/$relativeDir/") | Out-Null }
    }
    Get-ChildItem -LiteralPath $topDir -Recurse -File | Sort-Object FullName | ForEach-Object {
        $relativeFile = $_.FullName.Substring($topDir.Length).TrimStart('\', '/').Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $_.FullName, "$slug/$relativeFile") | Out-Null
    }
} finally {
    $archive.Dispose()
}

$hash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
"$hash  $(Split-Path $zipPath -Leaf)" | Set-Content -LiteralPath $shaPath -Encoding ASCII
$manifestDigest = (Get-FileHash -LiteralPath $manifestPath -Algorithm SHA256).Hash.ToLowerInvariant()
$report = @(
    '# Sabri Complete Home and News Feed 1.0.2 Public Visibility Candidate Test Report','',
    '- Accepted plugin version shown in WordPress: 1.0.2',
    '- Accepted schema constant: 1.0.0',
    '- Phase4Contracts checkpoint: 4A',
    '- Historical Phase 4 target remains separately gated at 1.2.0',
    "- Canonical artifact: $(Split-Path $zipPath -Leaf)",
    "- SHA-256: $hash",
    "- Runtime manifest SHA-256: $manifestDigest",
    "- Top-level ZIP folder: $slug/",
    "- Runtime files included: $copied",
    "- Required File 21 runtime files: $($requiredRuntimeFiles -join ', ')",
    "- Excluded development paths: $($excludedDirs + $excludedFiles -join ', ')",
    '- Compatibility aliases are byte-identical QA names only; the canonical release remains the Public Visibility candidate.',
    '- All Editorial News public feature gates remain disabled by default.',
    '- Automatic publication and automatic legacy migration remain disabled.',
    '- Package is an exact-head candidate only; merge, staging activation, WordPress visual acceptance, and live deployment remain separately gated.'
)
$report | Set-Content -LiteralPath $reportPath -Encoding UTF8

foreach ($alias in $aliases) {
    $aliasZip = Join-Path $releaseDir "$($alias.Base).zip"
    $aliasSha = Join-Path $releaseDir "$($alias.Base).sha256"
    Copy-Item -LiteralPath $zipPath -Destination $aliasZip -Force
    "$hash  $(Split-Path $aliasZip -Leaf)" | Set-Content -LiteralPath $aliasSha -Encoding ASCII
    $aliasHash = (Get-FileHash -LiteralPath $aliasZip -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($aliasHash -ne $hash) {
        throw "Alias package digest does not match canonical package: $($alias.Base)"
    }
    if ($alias.Manifest) {
        Copy-Item -LiteralPath $manifestPath -Destination (Join-Path $releaseDir "$($alias.Base)-MANIFEST.sha256") -Force
    }
    if ($alias.Report) {
        Copy-Item -LiteralPath $reportPath -Destination (Join-Path $releaseDir "$($alias.Base)-TEST-REPORT.md") -Force
    }
}

$resolvedStage = (Resolve-Path $stageDir).Path
if (-not $resolvedStage.StartsWith($resolvedRelease, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Refusing to remove a staging path outside the release directory: $resolvedStage"
}
Remove-Item -LiteralPath $stageDir -Recurse -Force
Write-Output "Built canonical package $zipPath"
foreach ($alias in $aliases) {
    Write-Output "Built byte-identical QA alias $($alias.Base).zip"
}
