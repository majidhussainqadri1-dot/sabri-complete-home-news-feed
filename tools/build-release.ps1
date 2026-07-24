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

$requiredRuntimeFiles = @(
	'sabri-complete-home-news-feed.php',
	'includes/class-poll-composer-integration.php',
	'includes/class-public-query-guard.php',
	'includes/class-followers-query-guard.php',
	'includes/class-rewrite-rules.php',
	'includes/class-phase3-feature-settings.php',
	'admin/views/social-features.php',
	'assets/js/share.js',
	'templates/action-bar.php',
	'includes/class-news-cache.php',
	'includes/class-news-public-projector.php',
	'includes/class-news-query-service.php',
	'includes/class-news-feed-integration.php',
	'includes/class-rest-news.php',
	'public/class-news-routing.php',
	'public/class-news-public-runtime.php',
	'templates/news-archive.php',
	'templates/news-single.php',
	'templates/news-card.php',
	'templates/news-retraction-notice.php',
	'assets/css/news.css',
	'assets/js/news.js'
)
foreach ($relativePath in $requiredRuntimeFiles) {
	$requiredPath = Join-Path $Root $relativePath
	if (-not (Test-Path -LiteralPath $requiredPath -PathType Leaf)) {
		throw "Required runtime repair file is missing: $relativePath"
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

foreach ($relativePath in $requiredRuntimeFiles) {
	$stagedPath = Join-Path $topDir $relativePath
	if (-not (Test-Path -LiteralPath $stagedPath -PathType Leaf)) {
		throw "Required runtime repair file was not staged: $relativePath"
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
	'- Target development line after all staging acceptance: 1.2.0',
	"- Artifact: $(Split-Path $zipPath -Leaf)",
	"- SHA-256: $hash",
	"- Top-level ZIP folder: $slug/",
	"- Runtime files included: $copied",
	"- Required runtime files: $($requiredRuntimeFiles -join ', ')",
	"- Excluded development paths: $($excludedDirs + $excludedFiles -join ', '), *.log",
	'- Package status: WordPress Playground-tested staging candidate only; not approved for version promotion or live deployment.',
	'- Required real integration matrix: WordPress latest/PHP 8.3 and WordPress 6.8/PHP 8.1.',
	'- Phase 4C coverage: public News projections, archive/single/taxonomy routes, bounded search, dedicated News cards, Home Feed integration, read-only REST, cache/privacy boundaries.',
	'- All Phase 4 public feature gates remain disabled by default.'
)
$report | Set-Content -LiteralPath $reportPath -Encoding UTF8

$resolvedStage = (Resolve-Path $stageDir).Path
if (-not $resolvedStage.StartsWith($resolvedRelease, [System.StringComparison]::OrdinalIgnoreCase)) {
	throw "Refusing to remove a staging path outside the release directory: $resolvedStage"
}
Remove-Item -LiteralPath $stageDir -Recurse -Force

Write-Output "Built $zipPath"
