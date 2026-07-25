param(
    [string]$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
)

$ErrorActionPreference = 'Stop'
$slug = 'sabri-complete-home-news-feed'
$base = '21-sabri-complete-home-news-feed-1.0.0-PHASE-5-FINAL-CANDIDATE'
$legacyBase = '21-sabri-complete-home-news-feed-1.0.0-PHASE-3-STAGING-CANDIDATE'
$releaseDir = Join-Path $Root 'release'
$stageDir = Join-Path $releaseDir '_stage'
$topDir = Join-Path $stageDir $slug
$zipPath = Join-Path $releaseDir "$base.zip"
$shaPath = Join-Path $releaseDir "$base.sha256"
$manifestPath = Join-Path $releaseDir "$base-MANIFEST.sha256"
$reportPath = Join-Path $releaseDir "$base-TEST-REPORT.md"
$legacyZipPath = Join-Path $releaseDir "$legacyBase.zip"
$legacyShaPath = Join-Path $releaseDir "$legacyBase.sha256"

$requiredRuntimeFiles = @(
    'sabri-complete-home-news-feed.php',
    'includes/class-plugin.php',
    'includes/class-activator.php',
    'includes/class-deactivator.php',
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
    'admin/class-phase5-newsroom-admin.php',
    'public/class-phase5-public-runtime.php',
    'templates/news-breaking-strip.php',
    'templates/news-sources-history.php',
    'templates/news-submission-portal.php',
    'assets/css/phase5-public.css',
    'assets/css/phase5-admin.css',
    'assets/js/phase5-public.js',
    'assets/js/phase5-admin.js',
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
        throw "Required Phase 5 runtime file is missing: $relativePath"
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
        throw "Required Phase 5 runtime file was not staged: $relativePath"
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

foreach ($path in @($zipPath, $shaPath, $manifestPath, $reportPath, $legacyZipPath, $legacyShaPath)) {
    if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path -Force }
}

$manifestLines = @()
Get-ChildItem -LiteralPath $topDir -Recurse -File | Sort-Object FullName | ForEach-Object {
    $relative = $_.FullName.Substring($topDir.Length).TrimStart('\', '/').Replace('\', '/')
    $hash = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
    $manifestLines += "$hash  $slug/$relative"
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
Copy-Item -LiteralPath $zipPath -Destination $legacyZipPath -Force
"$hash  $(Split-Path $legacyZipPath -Leaf)" | Set-Content -LiteralPath $legacyShaPath -Encoding ASCII
$legacyHash = (Get-FileHash -LiteralPath $legacyZipPath -Algorithm SHA256).Hash.ToLowerInvariant()
if ($legacyHash -ne $hash) {
    throw 'Legacy compatibility package digest does not match the canonical Phase 5 package.'
}
$manifestDigest = (Get-FileHash -LiteralPath $manifestPath -Algorithm SHA256).Hash.ToLowerInvariant()
$report = @(
    '# Sabri Complete Home and News Feed Phase 5 Candidate Test Report','',
    '- Accepted plugin version shown in WordPress: 1.0.0',
    '- Accepted schema constant: 1.0.0',
    '- Phase4Contracts checkpoint: 4A',
    '- Target release after separately gated promotion: 1.2.0',
    "- Artifact: $(Split-Path $zipPath -Leaf)",
    "- SHA-256: $hash",
    "- Legacy compatibility alias: $(Split-Path $legacyZipPath -Leaf)",
    "- Runtime manifest SHA-256: $manifestDigest",
    "- Top-level ZIP folder: $slug/",
    "- Runtime files included: $copied",
    "- Required Phase 5 runtime files: $($requiredRuntimeFiles -join ', ')",
    "- Excluded development paths: $($excludedDirs + $excludedFiles -join ', ')",
    '- All Phase 4 and Phase 5 public feature gates remain disabled by default.',
    '- Automatic publication remains disabled.',
    '- Package is an exact-head candidate only; not approved for merge, version promotion, staging activation, or live deployment.'
)
$report | Set-Content -LiteralPath $reportPath -Encoding UTF8

$resolvedStage = (Resolve-Path $stageDir).Path
if (-not $resolvedStage.StartsWith($resolvedRelease, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Refusing to remove a staging path outside the release directory: $resolvedStage"
}
Remove-Item -LiteralPath $stageDir -Recurse -Force
Write-Output "Built $zipPath"
Write-Output "Built legacy compatibility alias $legacyZipPath"
