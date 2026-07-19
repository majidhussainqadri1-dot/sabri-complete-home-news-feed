param(
	[string]$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
)

$ErrorActionPreference = 'Stop'

$slug = 'sabri-complete-home-news-feed'
$base = '21-sabri-complete-home-news-feed-1.0.0-PHASE-3-STAGING-CANDIDATE'
$releaseDir = Join-Path $Root 'release'
$stageDir = Join-Path $releaseDir '_stage'
$topDir = Join-Path $stageDir $slug
$zipPath = Join-Path $releaseDir "$base.zip"
$shaPath = Join-Path $releaseDir "$base.sha256"
$reportPath = Join-Path $releaseDir "$base-TEST-REPORT.md"

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

$excludedDirs = @('.git', '.github', 'tools', 'tests', 'release', 'vendor', 'node_modules')
$excludedFiles = @('TASK_LOG.md', '.gitignore')
$copied = 0

Get-ChildItem -LiteralPath $Root -Force | ForEach-Object {
	if ($excludedDirs -contains $_.Name -or $excludedFiles -contains $_.Name -or $_.Name -like '*.log') {
		return
	}

	$destination = Join-Path $topDir $_.Name
	if ($_.PSIsContainer) {
		Copy-Item -LiteralPath $_.FullName -Destination $destination -Recurse -Force
		Get-ChildItem -LiteralPath $destination -Recurse -File -Filter '*.log' | Remove-Item -Force
		$copied += (Get-ChildItem -LiteralPath $destination -Recurse -File | Measure-Object).Count
	} else {
		Copy-Item -LiteralPath $_.FullName -Destination $destination -Force
		$copied++
	}
}

foreach ($path in @($zipPath, $shaPath, $reportPath)) {
	if (Test-Path -LiteralPath $path) {
		Remove-Item -LiteralPath $path -Force
	}
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
	$archive.CreateEntry("$slug/") | Out-Null
	Get-ChildItem -LiteralPath $topDir -Recurse -Directory | ForEach-Object {
		$relativeDir = $_.FullName.Substring($topDir.Length).TrimStart('\', '/').Replace('\', '/')
		if ($relativeDir) {
			$archive.CreateEntry("$slug/$relativeDir/") | Out-Null
		}
	}
	Get-ChildItem -LiteralPath $topDir -Recurse -File | ForEach-Object {
		$relativeFile = $_.FullName.Substring($topDir.Length).TrimStart('\', '/').Replace('\', '/')
		[System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $_.FullName, "$slug/$relativeFile") | Out-Null
	}
} finally {
	$archive.Dispose()
}

$hash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
"$hash  $(Split-Path $zipPath -Leaf)" | Set-Content -LiteralPath $shaPath -Encoding ASCII

$report = @(
	'# Sabri Complete Home and News Feed Phase 3 Staging Candidate Test Report',
	'',
	'- Accepted plugin version shown in WordPress: 1.0.0',
	'- Target release after staging acceptance: 1.1.0',
	"- Artifact: $(Split-Path $zipPath -Leaf)",
	"- SHA-256: $hash",
	"- Top-level ZIP folder: $slug/",
	"- Runtime files included: $copied",
	"- Excluded development paths: $($excludedDirs + $excludedFiles -join ', '), *.log",
	'- Package status: Hostinger staging candidate only; not approved for main merge or live deployment.',
	'- Compatibility guards: WordPress dynamic option-filter recursion and duplicate plugin-folder activation tests must pass before upload.'
)
$report | Set-Content -LiteralPath $reportPath -Encoding UTF8

$resolvedStage = (Resolve-Path $stageDir).Path
if (-not $resolvedStage.StartsWith($resolvedRelease, [System.StringComparison]::OrdinalIgnoreCase)) {
	throw "Refusing to remove a staging path outside the release directory: $resolvedStage"
}
Remove-Item -LiteralPath $stageDir -Recurse -Force

Write-Output "Built $zipPath"
