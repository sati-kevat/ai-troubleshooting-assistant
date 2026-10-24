<#
.SYNOPSIS
    Creates a clean submission ZIP of the project.

.DESCRIPTION
    Copies the project into a temporary staging folder, leaving out
    dependencies (node_modules), build output (dist), local secrets (.env),
    logs and test runtime files. It then checks the staged copy for secret
    files or a database password and stops if any are found. Finally it zips the folder.

    Uses only Windows built-ins (robocopy and .NET ZIP); nothing is downloaded.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\package-submission.ps1

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\package-submission.ps1 -OutputPath "$env:USERPROFILE\Desktop\submission.zip"
#>
param(
    [string]$OutputPath = (Join-Path $PSScriptRoot '..\submission\troubleshooting-assistant.zip')
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$packageName = 'troubleshooting-assistant'
$staging = Join-Path ([System.IO.Path]::GetTempPath()) ("submission-" + [guid]::NewGuid().ToString('N'))
$stagedProject = Join-Path $staging $packageName

# Folders (matched by name anywhere) and files that must never be submitted.
$excludedDirs = @('node_modules', 'dist', '.git', '.mock_state', 'submission', '.vscode', '.idea')
$excludedFiles = @('.env', '.env.local', '*.log', 'Thumbs.db', '.DS_Store', '*.zip')

Write-Host "Staging project from $root"
New-Item -ItemType Directory -Path $stagedProject -Force | Out-Null
& robocopy $root $stagedProject /E /XD $excludedDirs /XF $excludedFiles /NFL /NDL /NJH /NJS /NP | Out-Null
if ($LASTEXITCODE -ge 8) {
    throw "robocopy failed with exit code $LASTEXITCODE"
}

# Safety checks on the staged copy.
$files = Get-ChildItem -Path $stagedProject -Recurse -File -Force
$problems = @()
foreach ($file in $files) {
    $relative = $file.FullName.Substring($stagedProject.Length + 1)
    if ($file.Name -eq '.env' -or $relative -match '(^|\\)(node_modules|dist)\\') {
        $problems += "Excluded file present: $relative"
        continue
    }
    if ($file.Length -lt 2MB) {
        $text = Get-Content -LiteralPath $file.FullName -Raw -ErrorAction SilentlyContinue
        # [ \t] instead of \s so an empty value is not joined with the next line.
        if ($text -match '(?m)^[ \t]*DB_PASS[ \t]*=[ \t]*[^\s#]') {
            $problems += "DB_PASS has a value in: $relative"
        }
    }
}
if ($problems.Count -gt 0) {
    Remove-Item -Recurse -Force $staging
    $problems | ForEach-Object { Write-Host "  $_" -ForegroundColor Red }
    throw 'Packaging stopped: remove the secrets or files listed above and run again.'
}

# Create the ZIP. Entries are added one by one with forward-slash names:
# ZipFile.CreateFromDirectory on Windows PowerShell 5.1 writes backslashes,
# which macOS/Linux unzip tools turn into file names instead of folders.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$outputFull = [System.IO.Path]::GetFullPath($OutputPath)
New-Item -ItemType Directory -Path (Split-Path $outputFull) -Force | Out-Null
if (Test-Path $outputFull) {
    Remove-Item -Force $outputFull
}
$archive = [System.IO.Compression.ZipFile]::Open($outputFull, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in $files) {
        $entryName = $packageName + '/' + $file.FullName.Substring($stagedProject.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $file.FullName, $entryName, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally {
    $archive.Dispose()
}
Remove-Item -Recurse -Force $staging

$zip = [System.IO.Compression.ZipFile]::OpenRead($outputFull)
$entryCount = $zip.Entries.Count
$zip.Dispose()
$sizeKb = [math]::Round((Get-Item $outputFull).Length / 1KB, 1)
Write-Host "Created $outputFull ($entryCount files, $sizeKb KB)" -ForegroundColor Green
Write-Host 'Excluded: node_modules, dist, .env files, logs, test runtime state.'
