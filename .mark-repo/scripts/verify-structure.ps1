$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$required = @('README.md','START_HERE.md','AI_RULES.md','PROJECT.md','context/INDEX.md','context/CURRENT.md','specs/product.md','specs/acceptance.md','specs/architecture.md','specs/data-model.md','specs/api.md','specs/ux.md','quality/CHECKLIST.md','quality/KNOWN_FAILURES.md','project')
foreach ($relative in $required) {if (-not (Test-Path -LiteralPath (Join-Path $root $relative))) {throw "Missing: $relative"}}
$bytes = (Get-ChildItem -LiteralPath $root -Recurse -File | Measure-Object Length -Sum).Sum
if ($bytes -gt 1048576) {throw 'Mark_Repo metadata exceeds 1 MB; remove generated or duplicated content.'}
Write-Host ("PASS: Mark_Repo structure, {0:N1} KB" -f ($bytes / 1KB))
