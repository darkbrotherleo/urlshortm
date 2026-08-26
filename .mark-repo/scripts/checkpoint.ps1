param([Parameter(Mandatory = $true)][string] $Completed,[Parameter(Mandatory = $true)][string] $Next,[string] $InProgress = 'Không',[string] $Tests = 'Chưa chạy',[string] $Plan = 'Không',[string] $Blockers = 'Không',[string] $Risks = 'Không')
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$target = [IO.Path]::GetFullPath((Join-Path $root 'context/CURRENT.md'))
if (-not $target.StartsWith(($root + [IO.Path]::DirectorySeparatorChar), [StringComparison]::OrdinalIgnoreCase)) { throw 'Unsafe checkpoint path.' }
$clean = {param($value) ([string] $value).Replace("`r", ' ').Replace("`n", ' ').Trim()}
$lines = @('# Current checkpoint','',('- Updated: ' + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')),('- Completed: ' + (& $clean $Completed)),('- In progress: ' + (& $clean $InProgress)),('- Exact next action: ' + (& $clean $Next)),('- Active plan: ' + (& $clean $Plan)),('- Blockers: ' + (& $clean $Blockers)),('- Latest verification: ' + (& $clean $Tests)),('- Risks to watch: ' + (& $clean $Risks)))
Set-Content -Encoding UTF8 -LiteralPath $target -Value ($lines -join "`n")
Write-Host "Checkpoint saved: $target"
