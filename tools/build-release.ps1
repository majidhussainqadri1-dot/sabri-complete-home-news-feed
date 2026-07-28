param(
    [string]$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
)

$ErrorActionPreference = 'Stop'
$slug = 'sabri-complete-home-news-feed'
$bootstrapPath = Join-Path $Root 'sabri-complete-home-news-feed.php'
$bootstrap = Get-Content -LiteralPath $bootstrapPath -Raw
$match = [regex]::Match($bootstrap, '(?mi)^\s*\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)')
if (-not $match.Success) { throw 'Unable to resolve the File 21 plugin version.' }
$version = $match.Groups[1].Value
$base = "21-sabri-complete-home-news-feed-$version-PUBLIC-VISIBILITY-CANDIDATE"
$releaseDir = Join-Path $Root 'release'
$stageDir = Join-Path $releaseDir '_stage'
$topDir = Join-Path $stageDir $slug
$zipPath = Join-Path $releaseDir "$base.zip"
$shaPath = Join-Path $releaseDir "$base.sha256"
$manifestPath = Join-Path $releaseDir "$base-MANIFEST.sha256"
$reportPath = Join-Path $releaseDir "$base-TEST-REPORT.md"

$requiredRuntimeFiles = @(
    'sabri-complete-home-news-feed.php',
    'readme.txt',
    'CHANGELOG.md',
    'includes/class-plugin.php',
    'includes/class-public-surface-recovery.php',
    'includes/class-corrective-public-mount.php',
    'includes/class-home-composition-registry.php',
    'includes/class-public-query-guard.php',
    'includes/class-integrations.php',
    'includes/class-harmonized-settings.php',
    'public/class-phase5-public-runtime.php',
    'assets/css/home-composition.css',
    'assets/css/corrective-public.css',
    'assets/js/phase5-public.js',
    'FILE-21-HARMONIZATION-COMPLETION-PLAN.md',
    'FILE-21-PUBLIC-VISIBILITY-RECOVERY-1.0.2.md',
    'FILE-21-LIVE-VISUAL-ACCEPTANCE-CHECKLIST.md',
    'uninstall.php'
)
foreach ($relativePath in $requiredRuntimeFiles) {
    if (-not (Test-Path -LiteralPath (Join-Path $Root $relativePath) -PathType Leaf)) {
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
$excludedFiles = @('TASK_LOG.md', '.gitignore')
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
    if (-not (Test-Path -LiteralPath (Join-Path $topDir $relativePath) -PathType Leaf)) {
        throw "Required File 21 runtime file was not staged: $relativePath"
    }
}

foreach ($path in @($zipPath, $shaPath, $manifestPath, $reportPath)) {
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
    "# Sabri Complete Home and News Feed $version Corrective Candidate Test Report", '',
    "- Plugin version: $version",
    '- Schema version: 1.0.0',
    "- Canonical artifact: $(Split-Path $zipPath -Leaf)",
    "- SHA-256: $hash",
    "- Runtime manifest SHA-256: $manifestDigest",
    "- Top-level ZIP folder: $slug/",
    "- Runtime files included: $copied",
    '- One canonical package name is generated; historical version aliases are not emitted.',
    '- Editorial News gates remain explicit and reversible.',
    '- Staging, Safe Boot retry, visual acceptance, rollback and live deployment remain separate gates.'
)
$report | Set-Content -LiteralPath $reportPath -Encoding UTF8

$resolvedStage = (Resolve-Path $stageDir).Path
if (-not $resolvedStage.StartsWith($resolvedRelease, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw "Refusing to remove a staging path outside the release directory: $resolvedStage"
}
Remove-Item -LiteralPath $stageDir -Recurse -Force
Write-Output "Built canonical package $zipPath"
