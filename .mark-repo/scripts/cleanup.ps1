param([switch] $Apply)
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$project = [IO.Path]::GetFullPath((Join-Path $root 'project'))
if (-not (Test-Path -LiteralPath $project)) {throw 'project/ missing; cleanup stopped.'}
if (-not $project.StartsWith(($root + [IO.Path]::DirectorySeparatorChar), [StringComparison]::OrdinalIgnoreCase)) {throw 'Unsafe project path.'}
$targets = @('README.md','START_HERE.md','AI_RULES.md','PROJECT.md','.gitignore','context','specs','playbooks','quality','templates','prompts','scripts')
if ($Apply) {Write-Host 'APPLY cleanup; project/ will be preserved.'} else {Write-Host 'dry-run; nothing will be removed.'}
foreach ($relative in $targets) {$target = [IO.Path]::GetFullPath((Join-Path $root $relative));if (-not $target.StartsWith(($root + [IO.Path]::DirectorySeparatorChar), [StringComparison]::OrdinalIgnoreCase)) {throw "Unsafe target: $target"};if (Test-Path -LiteralPath $target) {if ($Apply) {Write-Host 'REMOVE' $target;Remove-Item -LiteralPath $target -Recurse -Force} else {Write-Host 'WOULD REMOVE' $target}}}
if ($Apply) {Write-Host 'Only project/ remains.'}
