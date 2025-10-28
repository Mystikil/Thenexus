# DevNexus Asset Suite

The DevNexus Asset Suite is an all-in-one toolkit for inspecting, editing, and converting Tibia asset containers. It provides a .NET 8 core library with simplified serializers, a cross-platform Avalonia desktop UI, and a command-line interface for batch automation.

## Projects

- **AssetSuite.Core** – parsing, packing, and conversion primitives for legacy (7.x–10.98) and modern (11+) formats.
- **AssetSuite.UI** – Avalonia desktop experience with editors for OTB, appearances, sprite browsing, and conversion pipelines.
- **AssetSuite.CLI** – command-line automation built on System.CommandLine.
- **AssetSuite.Tests** – xUnit regression suite with synthetic fixtures.

## Prerequisites

- .NET SDK 8.0 or later.

## Quickstart

```bash
./build.sh
```

The script restores dependencies, builds all projects, runs the test suite, and publishes the UI and CLI artifacts into the `artifacts/` directory. A PowerShell variant `build.ps1` is available for Windows users.

### Running the UI

```bash
dotnet run --project AssetSuite.UI
```

### Using the CLI

```
dotnet run --project AssetSuite.CLI -- --help
```

Example: export sprites from a legacy archive.

```
dotnet run --project AssetSuite.CLI -- export-sprites --spr tibia.spr --out sprites
```

## Sample Fixtures

Synthetic JSON fixtures are available under `AssetSuite.Tests/Fixtures` and are used by the regression tests to create tiny `.spr`, `.dat`, and `appearances.dat` payloads on the fly without checking binary files into the repository.

## License

Released under the MIT License. See the `LICENSE` file for details.
