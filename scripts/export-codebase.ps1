param(
    [string]$OutputFile = "codebase_export.txt",
    [string]$Root = "."
)

$ErrorActionPreference = "Stop"

$resolvedRoot = (Resolve-Path -LiteralPath $Root).Path
$outputPath = Join-Path $resolvedRoot $OutputFile
$scriptPath = $MyInvocation.MyCommand.Path

$excludedDirs = @(
    ".git", ".idea", ".vscode", ".vs",
    "node_modules", "vendor",
    "bin", "obj", "dist", "build", "coverage",
    "tmp", "temp", "cache", ".cache"
)

$excludedFilePatterns = @(
    "*.png", "*.jpg", "*.jpeg", "*.gif", "*.webp", "*.ico", "*.bmp", "*.svg",
    "*.pdf", "*.zip", "*.gz", "*.rar", "*.7z",
    "*.mp3", "*.mp4", "*.avi", "*.mov",
    "*.woff", "*.woff2", "*.ttf", "*.eot", "*.otf",
    "*.dll", "*.exe", "*.class", "*.jar"
)

$pathOnlyExtensions = @(".css", ".html", ".htm")
$pathOnlyDirectories = @("views")

if (Test-Path -LiteralPath $outputPath) {
    Remove-Item -LiteralPath $outputPath -Force
}

$files = Get-ChildItem -Path $resolvedRoot -Recurse -File -Force | Where-Object {
    $fullPath = $_.FullName
    $relativePath = $fullPath.Substring($resolvedRoot.Length).TrimStart('\')

    if ($fullPath -eq $outputPath -or $fullPath -eq $scriptPath) {
        return $false
    }

    foreach ($dir in $excludedDirs) {
        if ($relativePath -match "(^|\\)$([Regex]::Escape($dir))(\\|$)") {
            return $false
        }
    }

    foreach ($pattern in $excludedFilePatterns) {
        if ($_.Name -like $pattern) {
            return $false
        }
    }

    return $true
} | Sort-Object FullName

Set-Content -LiteralPath $outputPath -Value "Codebase export generated on $(Get-Date -Format s)`r`nRoot: $resolvedRoot`r`n" -Encoding utf8

foreach ($file in $files) {
    $relativePath = $file.FullName.Substring($resolvedRoot.Length).TrimStart('\')
    Add-Content -LiteralPath $outputPath -Value "===== FILE: $relativePath =====`r`n" -Encoding utf8

    $extension = [System.IO.Path]::GetExtension($file.Name).ToLowerInvariant()
    $isPathOnlyDirectory = $false
    foreach ($dir in $pathOnlyDirectories) {
        if ($relativePath -match "^$([Regex]::Escape($dir))(\\|$)") {
            $isPathOnlyDirectory = $true
            break
        }
    }

    if ($pathOnlyExtensions -contains $extension -or $isPathOnlyDirectory) {
        Add-Content -LiteralPath $outputPath -Value "[[CONTENT OMITTED: listed for existence only]]" -Encoding utf8
        Add-Content -LiteralPath $outputPath -Value "`r`n`r`n" -Encoding utf8
        continue
    }

    try {
        $content = Get-Content -LiteralPath $file.FullName -Raw -ErrorAction Stop
        Add-Content -LiteralPath $outputPath -Value $content -Encoding utf8
    } catch {
        Add-Content -LiteralPath $outputPath -Value "[[UNREADABLE FILE: $($file.FullName)]]" -Encoding utf8
    }

    Add-Content -LiteralPath $outputPath -Value "`r`n`r`n" -Encoding utf8
}

Write-Host "Export complete: $outputPath"
Write-Host "Files included: $($files.Count)"
