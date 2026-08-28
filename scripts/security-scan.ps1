param(
    [switch] $Apply
)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

$dangerPatterns = @(
    'eval\s*\(',
    'system\s*\(',
    'shell_exec\s*\(',
    '(?<!->)exec\s*\(',
    'passthru\s*\(',
    'assert\s*\(',
    'mysql_connect\s*\(',
    '(?<!http_build_)query\s*\(\s*["'']?\$'
)

$files = Get-ChildItem -Recurse -Filter *.php -Path $root | Where-Object {
    $_.FullName -notlike "*\.mark-repo\*" -and $_.FullName -notlike "*\vendor\*" -and $_.FullName -notlike "*\tests\*"
}

$findings = @()
foreach ($file in $files) {
    $content = Get-Content -Raw -Encoding UTF8 -LiteralPath $file.FullName
    foreach ($pattern in $dangerPatterns) {
        $matches = [regex]::Matches($content, $pattern)
        if ($matches.Count -gt 0) {
            $findings += [pscustomobject]@{
                File    = $file.FullName.Substring($root.Length)
                Pattern = $pattern
                Count   = $matches.Count
            }
        }
    }
}

if ($findings.Count -eq 0) {
    Write-Host 'SECURITY SCAN PASS'
    exit 0
}

Write-Host 'SECURITY SCAN FINDINGS (review manually):'
$findings | Format-Table -AutoSize

if ($Apply) {
    Write-Host 'INFO: $Apply không có hiệu lực cho scan; scan chỉ phát hiện. Sửa thủ công.'
}
exit 1
