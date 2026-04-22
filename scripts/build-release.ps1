[CmdletBinding()]
param(
	[string]$OutputDir = "",
	[string]$PackageName = "zignites-sentinel",
	[switch]$ShowExcluded,
	[switch]$SkipZip
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RepoRoot = Split-Path -Parent $ScriptDir

if ([string]::IsNullOrWhiteSpace($OutputDir)) {
	$OutputDir = Join-Path $RepoRoot "build"
}

$DistIgnorePath = Join-Path $RepoRoot ".distignore"

if (-not (Test-Path -LiteralPath $DistIgnorePath)) {
	throw "Missing .distignore at $DistIgnorePath"
}

function Normalize-RelativePath {
	param(
		[Parameter(Mandatory = $true)]
		[string]$Path
	)

	$normalized = $Path -replace "\\", "/"

	while ($normalized.StartsWith("./")) {
		$normalized = $normalized.Substring(2)
	}

	while ($normalized.StartsWith("/")) {
		$normalized = $normalized.Substring(1)
	}

	return $normalized
}

function Get-DistIgnorePatterns {
	param(
		[Parameter(Mandatory = $true)]
		[string]$Path
	)

	$patterns = @()

	foreach ($line in Get-Content -LiteralPath $Path) {
		$trimmed = $line.Trim()

		if ([string]::IsNullOrWhiteSpace($trimmed)) {
			continue
		}

		if ($trimmed.StartsWith("#")) {
			continue
		}

		$patterns += (Normalize-RelativePath -Path $trimmed)
	}

	return $patterns
}

function Test-ExcludedByPattern {
	param(
		[Parameter(Mandatory = $true)]
		[string]$RelativePath,
		[Parameter(Mandatory = $true)]
		[string[]]$Patterns
	)

	foreach ($pattern in $Patterns) {
		if ($pattern.EndsWith("/")) {
			$directoryPattern = $pattern.TrimEnd("/")

			if ($RelativePath -eq $directoryPattern -or $RelativePath.StartsWith($directoryPattern + "/")) {
				return $true
			}

			continue
		}

		if ($RelativePath -eq $pattern -or $RelativePath.StartsWith($pattern + "/")) {
			return $true
		}
	}

	return $false
}

$Patterns = Get-DistIgnorePatterns -Path $DistIgnorePath
$ResolvedOutputDir = [System.IO.Path]::GetFullPath($OutputDir)
$StageRoot = Join-Path $ResolvedOutputDir "stage"
$PackageRoot = Join-Path $StageRoot $PackageName
$ZipPath = Join-Path $ResolvedOutputDir ($PackageName + ".zip")

if (Test-Path -LiteralPath $StageRoot) {
	Remove-Item -LiteralPath $StageRoot -Recurse -Force
}

if (Test-Path -LiteralPath $ZipPath) {
	Remove-Item -LiteralPath $ZipPath -Force
}

New-Item -ItemType Directory -Path $PackageRoot -Force | Out-Null

$IncludedFiles = New-Object System.Collections.Generic.List[string]
$ExcludedFiles = New-Object System.Collections.Generic.List[string]

Get-ChildItem -LiteralPath $RepoRoot -Recurse -Force -File | ForEach-Object {
	$absolutePath = $_.FullName

	if ($absolutePath.StartsWith($ResolvedOutputDir, [System.StringComparison]::OrdinalIgnoreCase)) {
		return
	}

	$relativePath = Normalize-RelativePath -Path $absolutePath.Substring($RepoRoot.Length).TrimStart("\", "/")

	if (Test-ExcludedByPattern -RelativePath $relativePath -Patterns $Patterns) {
		$ExcludedFiles.Add($relativePath)
		return
	}

	$destinationPath = Join-Path $PackageRoot ($relativePath -replace "/", "\")
	$destinationDir = Split-Path -Parent $destinationPath

	if (-not (Test-Path -LiteralPath $destinationDir)) {
		New-Item -ItemType Directory -Path $destinationDir -Force | Out-Null
	}

	Copy-Item -LiteralPath $absolutePath -Destination $destinationPath -Force
	$IncludedFiles.Add($relativePath)
}

if (-not $SkipZip) {
	Compress-Archive -LiteralPath $PackageRoot -DestinationPath $ZipPath -CompressionLevel Optimal
}

Write-Host ("Package root: {0}" -f $PackageRoot)

if (-not $SkipZip) {
	Write-Host ("Zip path: {0}" -f $ZipPath)
}

Write-Host ("Included files: {0}" -f $IncludedFiles.Count)
Write-Host ("Excluded files: {0}" -f $ExcludedFiles.Count)

if ($ShowExcluded -and $ExcludedFiles.Count -gt 0) {
	Write-Host "Excluded paths:"
	$ExcludedFiles | Sort-Object | ForEach-Object {
		Write-Host (" - {0}" -f $_)
	}
}
