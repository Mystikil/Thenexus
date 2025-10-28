// MIT License
//
// Copyright (c) 2025 DevNexus
//
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
//
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.

using AssetSuite.Core.Imaging;
using AssetSuite.Core.Legacy;
using AssetSuite.Core.Models;
using AssetSuite.Core.Pipeline;
using AssetSuite.Core.V11;
using System.CommandLine;
using System.CommandLine.Invocation;
using System.Globalization;

namespace AssetSuite.CLI;

/// <summary>
/// Entry point for the AssetSuite command line application.
/// </summary>
public static class Program
{
    /// <summary>
    /// Program entry point.
    /// </summary>
    /// <param name="args">Command line arguments.</param>
    /// <returns>The exit code.</returns>
    public static async Task<int> Main(string[] args)
    {
        var root = new RootCommand("DevNexus Asset Suite CLI");
        root.AddCommand(BuildExportSpritesCommand());
        root.AddCommand(BuildSliceSheetCommand());
        root.AddCommand(BuildPackLegacyCommand());
        root.AddCommand(BuildConvertCommand());
        root.AddCommand(BuildValidateCommand());
        return await root.InvokeAsync(args).ConfigureAwait(false);
    }

    private static Command BuildExportSpritesCommand()
    {
        var sprOption = new Option<FileInfo>("--spr") { IsRequired = true, Description = "Path to the legacy SPR file." };
        var outOption = new Option<DirectoryInfo>("--out") { IsRequired = true, Description = "Directory to write PNGs to." };
        var splitOption = new Option<bool>("--split", description: "Organize exports into subfolders.");
        var command = new Command("export-sprites", "Exports a legacy SPR archive as PNGs.")
        {
            sprOption,
            outOption,
            splitOption,
        };

        command.SetHandler((FileInfo sprFile, DirectoryInfo outputDir, bool split) =>
        {
            using var stream = sprFile.OpenRead();
            var reader = new SprLegacyReader();
            var sprites = reader.ReadAll(stream);
            outputDir.Create();
            foreach (var sprite in sprites)
            {
                var destinationDirectory = split ? Directory.CreateDirectory(Path.Combine(outputDir.FullName, sprite.Id.ToString(CultureInfo.InvariantCulture))) : outputDir;
                string filePath = Path.Combine(destinationDirectory.FullName, $"sprite_{sprite.Id:D4}.png");
                using var bitmap = Bitmap.FromPixels(sprite.Width, sprite.Height, sprite.Rgba);
                using var fileStream = File.Create(filePath);
                bitmap.SavePng(fileStream);
            }
        }, sprOption, outOption, splitOption);

        return command;
    }

    private static Command BuildSliceSheetCommand()
    {
        var pngOption = new Option<FileInfo>("--png") { IsRequired = true, Description = "Sheet PNG to slice." };
        var outOption = new Option<DirectoryInfo>("--out") { IsRequired = true, Description = "Destination directory for tiles." };
        var tileOption = new Option<int>("--tile", () => 32, "Tile size in pixels.");
        var command = new Command("slice-sheet", "Slices a sheet PNG into legacy tiles.")
        {
            pngOption,
            outOption,
            tileOption,
        };

        command.SetHandler((FileInfo png, DirectoryInfo output, int tile) =>
        {
            output.Create();
            using var stream = png.OpenRead();
            using var bitmap = Bitmap.Load(stream);
            int index = 1;
            foreach (var sprite in SpriteSlicer.SliceSheet(bitmap, tile, tile))
            {
                string path = Path.Combine(output.FullName, $"tile_{index:D4}.png");
                using var outBitmap = Bitmap.FromPixels(sprite.Width, sprite.Height, sprite.Rgba);
                using var fileStream = File.Create(path);
                outBitmap.SavePng(fileStream);
                index++;
            }
        }, pngOption, outOption, tileOption);

        return command;
    }

    private static Command BuildPackLegacyCommand()
    {
        var pngDir = new Option<DirectoryInfo>("--png-dir") { IsRequired = true, Description = "Directory containing RGBA PNG tiles." };
        var outSpr = new Option<FileInfo>("--out-spr") { IsRequired = true, Description = "Output SPR path." };
        var command = new Command("pack-legacy", "Packs PNG tiles into a legacy SPR archive.")
        {
            pngDir,
            outSpr,
        };

        command.SetHandler((DirectoryInfo source, FileInfo destination) =>
        {
            var sprites = new List<Sprite>();
            int index = 1;
            foreach (var file in source.EnumerateFiles("*.png").OrderBy(f => f.Name, StringComparer.OrdinalIgnoreCase))
            {
                using var stream = file.OpenRead();
                using var bitmap = Bitmap.Load(stream);
                byte[] rgba = bitmap.GetPixelSpan().ToArray();
                sprites.Add(new Sprite(index++, bitmap.Width, bitmap.Height, rgba));
            }

            using var outStream = destination.Open(FileMode.Create, FileAccess.Write);
            var writer = new SprLegacyWriter();
            writer.WriteAll(sprites, outStream);
        }, pngDir, outSpr);

        return command;
    }

    private static Command BuildConvertCommand()
    {
        var fromOption = new Option<string>("--from", () => "auto", "Source asset type.");
        var toOption = new Option<string>("--to") { IsRequired = true, Description = "Target asset type." };
        var datOption = new Option<FileInfo?>("--dat", () => null, "DAT file path.");
        var sprOption = new Option<FileInfo?>("--spr", () => null, "SPR file path.");
        var appearancesOption = new Option<FileInfo?>("--appearances", () => null, "Modern appearances file.");
        var sheetsOption = new Option<DirectoryInfo?>("--sheets", () => null, "Modern sprite sheet directory.");
        var outOption = new Option<DirectoryInfo>("--out") { IsRequired = true, Description = "Output directory." };
        var command = new Command("convert", "Converts asset bundles between legacy and v11 formats.")
        {
            fromOption,
            toOption,
            datOption,
            sprOption,
            appearancesOption,
            sheetsOption,
            outOption,
        };

        command.SetHandler(async (InvocationContext context) =>
        {
            string from = context.ParseResult.GetValueForOption(fromOption) ?? "auto";
            string to = context.ParseResult.GetValueForOption(toOption) ?? string.Empty;
            var outDir = context.ParseResult.GetValueForOption(outOption) ?? throw new InvalidOperationException();
            outDir.Create();

            if (to.Equals("legacy", StringComparison.OrdinalIgnoreCase))
            {
                var appearancesFile = context.ParseResult.GetValueForOption(appearancesOption);
                var sheetsDir = context.ParseResult.GetValueForOption(sheetsOption);
                if (appearancesFile is null || sheetsDir is null)
                {
                    context.Console.Error.WriteLine("Appearances and sheets are required for legacy conversion.");
                    context.ExitCode = 1;
                    return;
                }

                var reader = new AppearancesReader();
                using var appStream = appearancesFile.OpenRead();
                var appearances = reader.Read(appStream);
                var spriteList = new List<Sprite>();
                foreach (var sheetPath in sheetsDir.EnumerateFiles("*.sheet"))
                {
                    using var sheetStream = sheetPath.OpenRead();
                    var sheetReader = new SpriteSheetReader();
                    spriteList.AddRange(sheetReader.Read(sheetStream));
                }

                var converter = new ClientVersionConverter();
                var result = converter.ConvertToLegacy(appearances, spriteList, new ClientVersionConverterOptions(true, true, false));
                await File.WriteAllBytesAsync(Path.Combine(outDir.FullName, "items.dat"), result.Dat).ConfigureAwait(false);
                await File.WriteAllBytesAsync(Path.Combine(outDir.FullName, "tibia.spr"), result.Spr).ConfigureAwait(false);
            }
            else if (to.Equals("v11", StringComparison.OrdinalIgnoreCase))
            {
                var datFile = context.ParseResult.GetValueForOption(datOption);
                var sprFile = context.ParseResult.GetValueForOption(sprOption);
                if (datFile is null || sprFile is null)
                {
                    context.Console.Error.WriteLine("DAT and SPR files are required for v11 conversion.");
                    context.ExitCode = 1;
                    return;
                }

                var datReader = new DatLegacyReader();
                using var datStream = datFile.OpenRead();
                var items = datReader.ReadAll(datStream);
                using var sprStream = sprFile.OpenRead();
                var sprReader = new SprLegacyReader();
                var sprites = sprReader.ReadAll(sprStream);

                var appearances = items.Select(item => new Appearance
                {
                    Id = item.ClientId,
                    Type = item.Attributes.TryGetValue("Type", out var type) ? type : "Item",
                    FrameGroups =
                    {
                        new FrameGroup
                        {
                            GroupType = "Idle",
                            Frames =
                            {
                                new Frame
                                {
                                    Duration = 100,
                                    SpriteIds = { item.ClientId },
                                },
                            },
                        },
                    },
                }).ToList();

                var appearancesWriter = new AppearancesWriter();
                var sheetWriter = new SpriteSheetWriter();
                using var appOut = File.Create(Path.Combine(outDir.FullName, "appearances.dat"));
                appearancesWriter.Write(appearances, appOut);
                using var sheetOut = File.Create(Path.Combine(outDir.FullName, "sprites.sheet"));
                sheetWriter.Write(sprites, sheetOut);
            }
            else
            {
                context.Console.Error.WriteLine($"Unsupported conversion target '{to}'.");
                context.ExitCode = 1;
            }
        });

        return command;
    }

    private static Command BuildValidateCommand()
    {
        var inputOption = new Option<FileSystemInfo>("--input") { IsRequired = true, Description = "File or directory to validate." };
        var command = new Command("validate", "Runs structural validation on assets.")
        {
            inputOption,
        };

        command.SetHandler((FileSystemInfo input) =>
        {
            if (!input.Exists)
            {
                throw new FileNotFoundException("Input path not found.", input.FullName);
            }

            if (input is FileInfo file)
            {
                if (file.Extension.Equals(".spr", StringComparison.OrdinalIgnoreCase))
                {
                    using var sprStream = file.OpenRead();
                    var reader = new SprLegacyReader();
                    var sprites = reader.ReadAll(sprStream);
                    var errors = Validation.ValidateLegacyPair(Array.Empty<ItemType>(), sprites);
                    if (errors.Count > 0)
                    {
                        foreach (var error in errors)
                        {
                            Console.Error.WriteLine(error);
                        }

                        Environment.ExitCode = 1;
                    }
                }
            }
        }, inputOption);

        return command;
    }
}
