#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
cd "$SCRIPT_DIR"

ARTIFACTS_DIR="$SCRIPT_DIR/artifacts"
rm -rf "$ARTIFACTS_DIR"
mkdir -p "$ARTIFACTS_DIR"

dotnet restore DevNexus.AssetSuite.sln

dotnet build DevNexus.AssetSuite.sln -c Release --no-restore

dotnet test DevNexus.AssetSuite.sln -c Release --no-build --logger "trx;LogFileName=$ARTIFACTS_DIR/tests.trx"

dotnet publish AssetSuite.UI/AssetSuite.UI.csproj -c Release -o "$ARTIFACTS_DIR/ui"

dotnet publish AssetSuite.CLI/AssetSuite.CLI.csproj -c Release -o "$ARTIFACTS_DIR/cli"
