<#
.SYNOPSIS
Build a WordPress-ready AMS Cache release zip with version management.

.EXAMPLE
powershell -ExecutionPolicy Bypass -File .\bin\build-release.ps1

.EXAMPLE
powershell -ExecutionPolicy Bypass -File .\bin\build-release.ps1 -Version 3.0.0 -KeepStage

.EXAMPLE
powershell -ExecutionPolicy Bypass -File .\bin\build-release.ps1 -Bump patch

.EXAMPLE
powershell -ExecutionPolicy Bypass -File .\bin\build-release.ps1 -Bump minor -SetVersion
#>

[CmdletBinding()]
param(
    [string] $Version = '',
    [ValidateSet('major', 'minor', 'patch')]
    [string] $Bump = '',
    [switch] $SetVersion,
    [string] $OutDir = 'dist',
    [switch] $IncludeTests,
    [switch] $KeepStage,
    [switch] $SkipNpmBuild
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Write-Utf8NoBom {
    param(
        [string] $Path,
        [string] $Value
    )

    $encoding = [System.Text.UTF8Encoding]::new($false)
    [System.IO.File]::WriteAllText([System.IO.Path]::GetFullPath($Path), $Value, $encoding)
}

function Invoke-NativeCommand {
    param(
        [string] $FilePath,
        [string[]] $Arguments
    )

    & $FilePath @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw "$FilePath $($Arguments -join ' ') failed with exit code $LASTEXITCODE."
    }
}

function Bump-Version {
    param(
        [string] $VersionString,
        [string] $Component
    )

    $parts = $VersionString -split '\.'

    if ($parts.Count -lt 3) {
        throw "Version '$VersionString' does not follow MAJOR.MINOR.PATCH format for bumping. Use -Version to specify manually."
    }

    $major = [int]$parts[0]
    $minor = [int]$parts[1]
    $patch = [int]$parts[2]

    switch ($Component) {
        'major' {
            $major++
            $minor = 0
            $patch = 0
        }
        'minor' {
            $minor++
            $patch = 0
        }
        'patch' {
            $patch++
        }
    }

    return "$major.$minor.$patch"
}

$pluginSlug = 'ams-cache'
$root = Resolve-Path (Join-Path $PSScriptRoot '..')
$mainFile = Join-Path $root 'cache-master.php'

if (-not (Test-Path -LiteralPath $mainFile)) {
    throw "Cannot find cache-master.php at $mainFile"
}

$mainFileContent = Get-Content -LiteralPath $mainFile -Raw

if ([string]::IsNullOrWhiteSpace($Version)) {
    if ($Bump) {
        if ($mainFileContent -notmatch '(?m)^\s*\*\s*Version:\s*([^\r\n]+)\s*$') {
            throw 'Cannot read plugin Version from cache-master.php for bump.'
        }

        $currentVersion = $Matches[1].Trim()
        $Version = Bump-Version -VersionString $currentVersion -Component $Bump
        Write-Host "Bumped $Bump : $currentVersion -> $Version"
    }
    else {
        if ($mainFileContent -notmatch '(?m)^\s*\*\s*Version:\s*([^\r\n]+)\s*$') {
            throw 'Cannot read plugin Version from cache-master.php'
        }

        $Version = $Matches[1].Trim()
    }
}

if ($Version -notmatch '^[0-9A-Za-z._-]+$') {
    throw "Invalid version '$Version'. Use numbers, letters, dot, underscore, or dash."
}

if ($SetVersion) {
    $updatedContent = $mainFileContent -replace '(?m)^(\s*\*\s*Version:\s*)[^\r\n]+(\s*)$', "`${1}$Version`${2}"
    Write-Utf8NoBom -Path $mainFile -Value $updatedContent
    Write-Host "Updated cache-master.php header version to $Version"

    $readmePath = Join-Path $root 'README.txt'

    if (Test-Path -LiteralPath $readmePath) {
        $readmeContent = Get-Content -LiteralPath $readmePath -Raw
        $stableTagPattern = [regex]'(?m)^(\s*Stable tag:\s*)[^\r\n]+(\s*)$'
        $updatedReadme = $stableTagPattern.Replace(
            $readmeContent,
            [System.Text.RegularExpressions.MatchEvaluator] {
                param($match)
                return $match.Groups[1].Value + $Version + $match.Groups[2].Value
            },
            1
        )

        Write-Utf8NoBom -Path $readmePath -Value $updatedReadme
        Write-Host "Updated README.txt Stable tag to $Version"
    }
}

if (-not $SkipNpmBuild -and (Test-Path -LiteralPath (Join-Path $root 'package.json'))) {
    Push-Location -LiteralPath $root

    try {
        if (Test-Path -LiteralPath (Join-Path $root 'package-lock.json')) {
            Invoke-NativeCommand -FilePath 'npm' -Arguments @('ci')
        } else {
            Invoke-NativeCommand -FilePath 'npm' -Arguments @('install')
        }

        Invoke-NativeCommand -FilePath 'npm' -Arguments @('run', 'build')
    } finally {
        Pop-Location
    }
}

$manifestPath = Join-Path $root 'inc/assets/build/.vite/manifest.json'

if (-not (Test-Path -LiteralPath $manifestPath)) {
    throw 'Release missing Vite manifest. Run npm run build or pass -SkipNpmBuild only when built assets already exist.'
}

$manifest = Get-Content -LiteralPath $manifestPath -Raw | ConvertFrom-Json
$entryNames = @('assets/src/admin.jsx', 'assets/src/admin.js', 'admin.js')
$adminEntry = $manifest.PSObject.Properties | Where-Object { $entryNames -contains $_.Name } | Select-Object -First 1

if (-not $adminEntry -or -not $adminEntry.Value.file) {
    throw 'Release manifest does not contain the React admin entry.'
}

if (-not (Test-Path -LiteralPath (Join-Path $root ('inc/assets/build/' + $adminEntry.Value.file)))) {
    throw "Release missing built admin script $($adminEntry.Value.file)."
}

$distDir = Join-Path $root $OutDir
$stageRoot = Join-Path $distDir '_stage'
$stagePlugin = Join-Path $stageRoot $pluginSlug
$zipPath = Join-Path $distDir "$pluginSlug-$Version.zip"
$notesPath = Join-Path $distDir "$pluginSlug-$Version-changelog.md"

function Convert-ToRelativePath {
    param(
        [string] $BasePath,
        [string] $FullPath
    )

    $baseUri = [System.Uri]::new((Resolve-Path -LiteralPath $BasePath).Path.TrimEnd('\') + '\')
    $fileUri = [System.Uri]::new((Resolve-Path -LiteralPath $FullPath).Path)

    return [System.Uri]::UnescapeDataString($baseUri.MakeRelativeUri($fileUri).ToString()).Replace('\', '/')
}

function Test-ReleaseExclude {
    param([string] $RelativePath)

    $path = $RelativePath.Replace('\', '/')

    $excludedPrefixes = @(
        '.git/',
        '.github/',
        '.vscode/',
        '.agents/',
        '.code-review-graph/',
        ".opencode/",
        '.understand-anything/',
        "$OutDir/",
        'assets/',
        'bin/',
        'graphify-out/',
        'node_modules/',
        'vendor/bin/',
        'vendor/composer/tmp-',
        'vendor/shieldon/simple-cache/.github/',
        'vendor/shieldon/simple-cache/tests/',
        'vendor/psr/simple-cache/.github/',
        'vendor/psr/simple-cache/tests/'
    )

    if (-not $IncludeTests) {
        $excludedPrefixes += 'tests/'
    }

    foreach ($prefix in $excludedPrefixes) {
        if ($path.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            return $true
        }
    }

    $excludedNames = @(
        '.gitignore',
        '.gitattributes',
        '.travis.yml',
        '.scrutinizer.yml',
        'install-tests.sh',
        'phpunit.xml',
        'phpcs.xml',
        'sample.txt',
        'composer.lock',
        'yarn.lock',
        'pnpm-lock.yaml',
        'npm-debug.log',
        '.phpunit.result.cache',
        'vendor/shieldon/simple-cache/.gitignore',
        'vendor/shieldon/simple-cache/.travis.yml',
        'vendor/shieldon/simple-cache/.scrutinizer.yml',
        'vendor/shieldon/simple-cache/phpunit.xml',
        'vendor/psr/simple-cache/.editorconfig',
        'graphify-out',
        'AGENTS.md',
        'SESSION_MEMORY.md',
        'DESIGN.md',
        'skills-lock.json',
        'vite.config.js',
        'economy.ams.com.kh-20260521T162807.json'
    )

    if ($excludedNames -contains $path) {
        return $true
    }

    if ($path -match '\.(zip|tar|gz|tgz|7z)$') {
        return $true
    }

    return $false
}

function Get-LatestChangelogSection {
    param(
        [string] $RepoRoot,
        [string] $ReleaseVersion
    )

    $changelogPath = Join-Path $RepoRoot 'CHANGELOG.md'

    if (Test-Path -LiteralPath $changelogPath) {
        $content = Get-Content -LiteralPath $changelogPath -Raw
        $pattern = "(?ms)^##\s+\[$([regex]::Escape($ReleaseVersion))\].*?(?=^##\s+\[|\z)"
        $match = [regex]::Match($content, $pattern)

        if ($match.Success) {
            return $match.Value.Trim()
        }
    }

    return "## [$ReleaseVersion]`n`nSee README.txt changelog."
}

if (Test-Path -LiteralPath $stageRoot) {
    Remove-Item -LiteralPath $stageRoot -Recurse -Force
}

New-Item -ItemType Directory -Force -Path $stagePlugin | Out-Null
New-Item -ItemType Directory -Force -Path $distDir | Out-Null

$files = Get-ChildItem -LiteralPath $root -Recurse -File -Force
$copied = 0

foreach ($file in $files) {
    $relative = Convert-ToRelativePath -BasePath $root -FullPath $file.FullName

    if (Test-ReleaseExclude -RelativePath $relative) {
        continue
    }

    $target = Join-Path $stagePlugin $relative
    $targetDir = Split-Path -Parent $target

    if (-not (Test-Path -LiteralPath $targetDir)) {
        New-Item -ItemType Directory -Force -Path $targetDir | Out-Null
    }

    Copy-Item -LiteralPath $file.FullName -Destination $target -Force
    $copied++
}

if (-not (Test-Path -LiteralPath (Join-Path $stagePlugin 'vendor/autoload.php'))) {
    throw 'Release missing vendor/autoload.php. Runtime vendor files must be present before packaging.'
}

if (Test-Path -LiteralPath $zipPath) {
    Remove-Item -LiteralPath $zipPath -Force
}

$releaseNotes = Get-LatestChangelogSection -RepoRoot $root -ReleaseVersion $Version
Write-Utf8NoBom -Path $notesPath -Value $releaseNotes

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$archive = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    $stageFiles = Get-ChildItem -LiteralPath $stagePlugin -Recurse -File -Force

    foreach ($file in $stageFiles) {
        $entryName = Convert-ToRelativePath -BasePath $stageRoot -FullPath $file.FullName
        $entryName = $entryName.Replace('\', '/')

        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive,
            $file.FullName,
            $entryName,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
} finally {
    $archive.Dispose()
}

if (-not $KeepStage) {
    Remove-Item -LiteralPath $stageRoot -Recurse -Force
}

$zipInfo = Get-Item -LiteralPath $zipPath
$sizeMb = [math]::Round($zipInfo.Length / 1MB, 2)

Write-Host "Release zip: $zipPath"
Write-Host "Changelog:   $notesPath"
Write-Host "Version:     $Version"
Write-Host "Files:       $copied"
Write-Host "Size:        $sizeMb MB"
