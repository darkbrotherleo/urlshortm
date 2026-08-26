param(
    [switch] $Fix
)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$php = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
if (-not (Test-Path -LiteralPath $php)) {
    $php = (Get-Command php -ErrorAction SilentlyContinue).Source
}
if (-not $php) {
    throw 'PHP CLI not found. Set $php at the top of scripts/lint.ps1.'
}

$files = Get-ChildItem -Recurse -Filter *.php -Path $root | Where-Object {
    $_.FullName -notlike "*\.mark-repo\*" -and $_.FullName -notlike "*\vendor\*"
}

$failed = 0
foreach ($file in $files) {
    $output = & $php -l $file.FullName 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host $output
        $failed++
    }
}

if ($failed -eq 0) {
    Write-Host "LINT PASS ($($files.Count) files)"
    exit 0
}
Write-Host "LINT FAIL: $failed file(s)"
exit 1
