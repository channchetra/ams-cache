<#
.SYNOPSIS
Build a WordPress-ready AMS Cache release zip.

.EXAMPLE
powershell -ExecutionPolicy Bypass -File .\bin\build-release.ps1

.EXAMPLE
powershell -ExecutionPolicy Bypass -File .\bin\build-release.ps1 -Version 2.2.0 -KeepStage
#>

[CmdletBinding()]
param(
    [string] $Version = '',
    [string] $OutDir = 'dist',
    [switch] $IncludeTests,
    [switch] $KeepStage
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$pluginSlug = 'ams-cache'
$root = Resolve-Path (Join-Path $PSScriptRoot '..')
$mainFile = Join-Path $root 'cache-master.php'

if (-not (Test-Path -LiteralPath $mainFile)) {
    throw "Cannot find cache-master.php at $mainFile"
}

$mainFileContent = Get-Content -LiteralPath $mainFile -Raw

if ([string]::IsNullOrWhiteSpace($Version)) {
    if ($mainFileContent -notmatch '(?m)^\s*\*\s*Version:\s*([^\r\n]+)\s*$') {
        throw 'Cannot read plugin Version from cache-master.php'
    }

    $Version = $Matches[1].Trim()
}

if ($Version -notmatch '^[0-9A-Za-z._-]+$') {
    throw "Invalid version '$Version'. Use numbers, letters, dot, underscore, or dash."
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
        "$OutDir/",
        'bin/',
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
        'package-lock.json',
        'yarn.lock',
        'pnpm-lock.yaml',
        '.phpunit.result.cache',
        'vendor/shieldon/simple-cache/.gitignore',
        'vendor/shieldon/simple-cache/.travis.yml',
        'vendor/shieldon/simple-cache/.scrutinizer.yml',
        'vendor/shieldon/simple-cache/phpunit.xml',
        'vendor/psr/simple-cache/.editorconfig'
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
Set-Content -LiteralPath $notesPath -Value $releaseNotes -Encoding UTF8

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
