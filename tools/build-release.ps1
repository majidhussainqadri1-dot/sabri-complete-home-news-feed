param([string]$Root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path)
$ErrorActionPreference = 'Stop'
$python = if ($env:PYTHON) { $env:PYTHON } else { 'python3' }
& $python (Join-Path $PSScriptRoot 'build-release.py') --root $Root
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
