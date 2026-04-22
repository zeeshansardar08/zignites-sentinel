[CmdletBinding()]
param(
	[string]$PackagePath = "",
	[string]$WpRoot = "",
	[string]$BaseUrl = "",
	[string]$LocalUser = "",
	[string]$TempSlug = "zignites-sentinel-release-smoke",
	[switch]$SkipBuild,
	[switch]$SkipSmoke,
	[switch]$KeepInstalled
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RepoRoot = Split-Path -Parent $ScriptDir

function Get-NormalizedPath {
	param(
		[Parameter(Mandatory = $true)]
		[string]$Path
	)

	return [System.IO.Path]::GetFullPath($Path)
}

function Invoke-PhpJson {
	param(
		[Parameter(Mandatory = $true)]
		[string[]]$Arguments
	)

	$output = & php @Arguments
	$json = $output | Out-String

	if ($json -match '(?s)__ZNTS_JSON_START__\s*(\{.*?\})\s*__ZNTS_JSON_END__') {
		$json = $matches[1]
	} else {
		throw ("PHP helper did not return marked JSON. Raw output:`n{0}" -f $json)
	}

	if ([string]::IsNullOrWhiteSpace($json)) {
		throw "PHP helper returned no output."
	}

	return $json | ConvertFrom-Json
}

if ([string]::IsNullOrWhiteSpace($WpRoot)) {
	$wpContentDir = Split-Path -Parent (Split-Path -Parent $RepoRoot)
	$WpRoot = Split-Path -Parent $wpContentDir
}

$WpRoot = Get-NormalizedPath -Path $WpRoot
$PluginsRoot = Join-Path $WpRoot "wp-content\plugins"
$BuildDir = Join-Path $RepoRoot "build"
$VerifyDir = Join-Path $BuildDir "package-verify"
$ExtractDir = Join-Path $VerifyDir "extract"
$StateFile = Join-Path $VerifyDir "activation-state.json"
$TempPluginDir = Join-Path $PluginsRoot $TempSlug
$TempPluginBasename = "$TempSlug/zignites-sentinel.php"
$OriginalPluginBasename = "zignites-sentinel/zignites-sentinel.php"

if ([string]::IsNullOrWhiteSpace($PackagePath)) {
	$PackagePath = Join-Path $BuildDir "zignites-sentinel.zip"
}

$PackagePath = Get-NormalizedPath -Path $PackagePath

if ([string]::IsNullOrWhiteSpace($BaseUrl)) {
	throw "A -BaseUrl value is required for packaged plugin activation verification."
}

if ([string]::IsNullOrWhiteSpace($LocalUser)) {
	throw "A -LocalUser value is required for packaged plugin activation verification."
}

if (-not $SkipBuild) {
	& powershell -ExecutionPolicy Bypass -File (Join-Path $ScriptDir "build-release.ps1")
}

if (-not (Test-Path -LiteralPath $PackagePath)) {
	throw "Package not found at $PackagePath"
}

if (-not (Test-Path -LiteralPath $PluginsRoot)) {
	throw "Plugins directory not found at $PluginsRoot"
}

if (Test-Path -LiteralPath $VerifyDir) {
	Remove-Item -LiteralPath $VerifyDir -Recurse -Force
}

if (Test-Path -LiteralPath $TempPluginDir) {
	Remove-Item -LiteralPath $TempPluginDir -Recurse -Force
}

New-Item -ItemType Directory -Path $ExtractDir -Force | Out-Null
Expand-Archive -LiteralPath $PackagePath -DestinationPath $ExtractDir -Force

$ExtractedPluginDir = Join-Path $ExtractDir "zignites-sentinel"

if (-not (Test-Path -LiteralPath $ExtractedPluginDir)) {
	throw "Expected extracted plugin directory not found at $ExtractedPluginDir"
}

Copy-Item -LiteralPath $ExtractedPluginDir -Destination $TempPluginDir -Recurse -Force

$Activated = $false

try {
	$activation = Invoke-PhpJson -Arguments @(
		(Join-Path $RepoRoot "tests\verify-release-package-activation.php"),
		"--mode=activate",
		"--base-url=$BaseUrl",
		"--local-user=$LocalUser",
		"--wp-root=$WpRoot",
		"--plugin=$TempPluginBasename",
		"--original-plugin=$OriginalPluginBasename",
		"--state-file=$StateFile"
	)

	if (-not $activation.ok) {
		throw "Packaged plugin activation helper reported failure."
	}

	$Activated = $true

	Write-Host ("Temporary plugin basename: {0}" -f $activation.plugin)
	Write-Host ("Original plugin basename: {0}" -f $activation.original_plugin)

	if (-not $SkipSmoke) {
		& php (Join-Path $RepoRoot "tests\smoke-admin-live.php") "--base-url=$BaseUrl" "--local-user=$LocalUser"
	}
}
finally {
	if (Test-Path -LiteralPath $TempPluginDir) {
		try {
			Invoke-PhpJson -Arguments @(
				(Join-Path $RepoRoot "tests\verify-release-package-activation.php"),
				"--mode=restore",
				"--base-url=$BaseUrl",
				"--local-user=$LocalUser",
				"--wp-root=$WpRoot",
				"--plugin=$TempPluginBasename",
				"--original-plugin=$OriginalPluginBasename",
				"--state-file=$StateFile"
			) | Out-Null
		} catch {
			Write-Host ("Restore warning: {0}" -f $_.Exception.Message)
		}
	}

	if (-not $KeepInstalled) {
		if (Test-Path -LiteralPath $TempPluginDir) {
			Remove-Item -LiteralPath $TempPluginDir -Recurse -Force
		}

		if (Test-Path -LiteralPath $VerifyDir) {
			Remove-Item -LiteralPath $VerifyDir -Recurse -Force
		}
	}
}

Write-Host ("Release package verification passed for {0}" -f $PackagePath)
