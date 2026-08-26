param([Parameter(Mandatory = $true)][string] $Name,[Parameter(Mandatory = $true)][string] $Stack)
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$project = [IO.Path]::GetFullPath((Join-Path $root 'project'))
if (-not $project.StartsWith(($root + [IO.Path]::DirectorySeparatorChar), [StringComparison]::OrdinalIgnoreCase)) { throw 'Unsafe project path.' }
New-Item -ItemType Directory -Force -Path $project | Out-Null
$profile = Join-Path $root 'PROJECT.md'
$content = Get-Content -Raw -Encoding UTF8 -LiteralPath $profile
$content = $content -replace '(?m)^- Name:.*$', ('- Name: ' + $Name)
$content = $content -replace '(?m)^- Main stack:.*$', ('- Main stack: ' + $Stack)
Set-Content -LiteralPath $profile -Value $content -Encoding UTF8
Write-Host "Initialized $Name"
Write-Host "Product code: $project"
