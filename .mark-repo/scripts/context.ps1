param([ValidateSet('start','feature','bug','ui','backend','database','release','handoff')][string] $Mode = 'start')
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$map = @{
    start = @('PROJECT.md','context/INDEX.md','context/CURRENT.md','specs/product.md')
    feature = @('PROJECT.md','context/CURRENT.md','specs/acceptance.md','specs/architecture.md')
    bug = @('context/CURRENT.md','quality/KNOWN_FAILURES.md','templates/bug-fix.md')
    ui = @('context/CURRENT.md','specs/ux.md','playbooks/ui.md','playbooks/frontend.md')
    backend = @('context/CURRENT.md','specs/api.md','playbooks/backend.md','playbooks/security.md')
    database = @('context/CURRENT.md','specs/data-model.md','playbooks/database.md')
    release = @('context/CURRENT.md','quality/CHECKLIST.md','playbooks/deployment.md')
    handoff = @('context/CURRENT.md','templates/handoff.md')
}
foreach ($relative in $map[$Mode]) {$path = Join-Path $root $relative;if (Test-Path -LiteralPath $path) {Write-Output "`n--- $relative ---";Get-Content -Encoding UTF8 -LiteralPath $path -TotalCount 180}}
