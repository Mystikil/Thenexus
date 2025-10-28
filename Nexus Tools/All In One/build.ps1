#!/usr/bin/env pwsh
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ScriptDir

$Artifacts = Join-Path $ScriptDir 'artifacts'
if (Test-Path $Artifacts) { Remove-Item $Artifacts -Recurse -Force }
New-Item -ItemType Directory -Path $Artifacts | Out-Null

dotnet restore DevNexus.AssetSuite.sln

dotnet build DevNexus.AssetSuite.sln -c Release --no-restore

dotnet test DevNexus.AssetSuite.sln -c Release --no-build --logger "trx;LogFileName=$Artifacts/tests.trx"

dotnet publish AssetSuite.UI/AssetSuite.UI.csproj -c Release -o (Join-Path $Artifacts 'ui')

dotnet publish AssetSuite.CLI/AssetSuite.CLI.csproj -c Release -o (Join-Path $Artifacts 'cli')
