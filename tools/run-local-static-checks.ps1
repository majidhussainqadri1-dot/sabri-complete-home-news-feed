param(
	[switch]$SkipPhpLint,
	[switch]$SkipPhpTests
)

$ErrorActionPreference = 'Stop'
$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$failures = New-Object System.Collections.Generic.List[string]
$warnings = New-Object System.Collections.Generic.List[string]

function Add-Failure([string]$Message) {
	$script:failures.Add($Message) | Out-Null
}

function Add-Warning([string]$Message) {
	$script:warnings.Add($Message) | Out-Null
}

$files = Get-ChildItem -LiteralPath $Root -Recurse -File -Force |
	Where-Object {
		$path = $_.FullName
		$path -notmatch '\\\.git\\' -and
		$path -notmatch '\\release\\' -and
		$path -notmatch '\\node_modules\\' -and
		$path -notmatch '\\vendor\\'
	}

$forbiddenRegex = '(?<![A-Za-z0-9_\\>])(?:eval|base64_decode|shell_exec|exec|passthru|system)\s*\('
$remoteRegex = 'https?://(?:cdn\.|fonts\.googleapis\.com|fonts\.gstatic\.com|unpkg\.com|cdn\.jsdelivr\.net|jsdelivr\.net)|fonts\.googleapis\.com|fonts\.gstatic\.com|unpkg\.com|cdn\.jsdelivr\.net'

foreach ($file in $files) {
	$content = Get-Content -LiteralPath $file.FullName -Raw
	if ($file.Extension -eq '.php' -and $content -match $forbiddenRegex) {
		Add-Failure "Forbidden PHP function pattern found in $($file.FullName)"
	}
	if ($content -match $remoteRegex) {
		Add-Failure "Remote CDN/font/runtime URL pattern found in $($file.FullName)"
	}
}

$phpFiles = $files | Where-Object { $_.Extension -eq '.php' }
$php = Get-Command php -ErrorAction SilentlyContinue
if ($php -and -not $SkipPhpLint) {
	foreach ($file in $phpFiles) {
		& $php.Source -l $file.FullName | Out-Null
		if ($LASTEXITCODE -ne 0) {
			Add-Failure "PHP syntax lint failed for $($file.FullName)"
		}
	}
} elseif (-not $SkipPhpLint) {
	Add-Warning 'PHP is unavailable locally; PHP syntax lint was not run.'
}

if ($php -and -not $SkipPhpTests) {
	& $php.Source (Join-Path $Root 'tools/run-tests.php')
	if ($LASTEXITCODE -ne 0) {
		Add-Failure 'PHP behavior tests failed.'
	}
} elseif (-not $SkipPhpTests) {
	Add-Warning 'PHP is unavailable locally; PHP behavior tests were not run.'
}

$node = Get-Command node -ErrorAction SilentlyContinue
if ($node) {
	try {
		$jsFiles = $files | Where-Object { $_.Extension -eq '.js' }
		foreach ($js in $jsFiles) {
			& $node.Source --check $js.FullName | Out-Null
			if ($LASTEXITCODE -ne 0) {
				Add-Failure "JavaScript syntax validation failed for $($js.FullName)"
			}
		}
	} catch {
		Add-Warning "Node is present but could not run locally: $($_.Exception.Message)"
	}
} else {
	Add-Warning 'Node is unavailable locally; JavaScript syntax validation was not run.'
}

$cssFiles = $files | Where-Object { $_.Extension -eq '.css' }
foreach ($cssFile in $cssFiles) {
	$css = Get-Content -LiteralPath $cssFile.FullName -Raw
	if ($css -match '@import|url\(\s*["'']?https?://') {
		Add-Failure "CSS must not import remote resources: $($cssFile.FullName)"
	}
}

$jsonFiles = $files | Where-Object { $_.Extension -eq '.json' }
foreach ($json in $jsonFiles) {
	try {
		Get-Content -LiteralPath $json.FullName -Raw | ConvertFrom-Json | Out-Null
	} catch {
		Add-Failure "JSON validation failed for $($json.FullName)"
	}
}

git -C $Root diff --check
if ($LASTEXITCODE -ne 0) {
	Add-Failure 'git diff --check reported whitespace errors.'
}

if ($warnings.Count -gt 0) {
	Write-Output 'Warnings:'
	$warnings | ForEach-Object { Write-Output "- $_" }
}

if ($failures.Count -gt 0) {
	Write-Output 'Failures:'
	$failures | ForEach-Object { Write-Output "- $_" }
	exit 1
}

Write-Output 'Local static checks completed.'
