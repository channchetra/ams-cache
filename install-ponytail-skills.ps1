# Ponytail Skills — Install to Global Kun Agent
# Run: pwsh -File install-ponytail-skills.ps1
# Copies the 6 ponytail SKILL.md files from the project's .agents/skills/
# to the global ~/.agents/skills/ directory so Kun picks them up everywhere.

$ErrorActionPreference = "Stop"

$globalRoot = "$env:USERPROFILE\.agents\skills"
$projectRoot = "$PSScriptRoot\.agents\skills"

$skills = @(
    "ponytail",
    "ponytail-review",
    "ponytail-audit",
    "ponytail-debt",
    "ponytail-gain",
    "ponytail-help"
)

Write-Host "Ponytail Skills v4.8.4 — Install to Global Kun Agent" -ForegroundColor Cyan
Write-Host "========================================================`n"

$installed = 0
$skipped = 0

foreach ($skill in $skills) {
    $src = Join-Path $projectRoot $skill "SKILL.md"
    $dstDir = Join-Path $globalRoot $skill
    $dst = Join-Path $dstDir "SKILL.md"

    if (-not (Test-Path $src)) {
        Write-Host "  SKIP  $skill — source missing at $src" -ForegroundColor Yellow
        $skipped++
        continue
    }

    New-Item -ItemType Directory -Path $dstDir -Force | Out-Null
    Copy-Item -Path $src -Destination $dst -Force
    Write-Host "  OK    $skill → $dst" -ForegroundColor Green
    $installed++
}

Write-Host ""
Write-Host "Done: $installed installed, $skipped skipped." -ForegroundColor Cyan
Write-Host "Restart Kun or reload skills to pick up the changes."

# Also write plugin.yaml
$pluginSrc = Join-Path $PSScriptRoot ".agents\plugin.yaml"
$pluginDst = Join-Path "$env:USERPROFILE\.agents" "ponytail-plugin.yaml"
if (Test-Path $pluginSrc) {
    Copy-Item -Path $pluginSrc -Destination $pluginDst -Force
    Write-Host "  OK    plugin.yaml → $pluginDst" -ForegroundColor Green
}
