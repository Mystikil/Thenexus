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

using System;
using System.Collections.Generic;
using System.Collections.ObjectModel;
using System.IO;
using System.Linq;
using System.Threading.Tasks;
using AssetSuite.Core.Legacy;
using AssetSuite.Core.Models;
using AssetSuite.Core.Pipeline;
using AssetSuite.Core.V11;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;

namespace AssetSuite.UI.ViewModels;

/// <summary>
/// View model for converting between client versions.
/// </summary>
public partial class ConvertViewModel : ObservableObject
{
    private readonly MainWindowViewModel _root;

    /// <summary>
    /// Initializes a new instance of the <see cref="ConvertViewModel"/> class.
    /// </summary>
    /// <param name="root">The root view model.</param>
    public ConvertViewModel(MainWindowViewModel root)
    {
        _root = root;
        TargetVersions = new ObservableCollection<string>(new[] { "10.98", "12.00" });
        _selectedTargetVersion = TargetVersions[0];
        ConvertCommand = new AsyncRelayCommand(ExecuteConversionAsync);
    }

    /// <summary>
    /// Gets the available target versions.
    /// </summary>
    public ObservableCollection<string> TargetVersions { get; }

    [ObservableProperty]
    private string _selectedTargetVersion;

    [ObservableProperty]
    private string? _legacyDatPath;

    [ObservableProperty]
    private string? _legacySprPath;

    [ObservableProperty]
    private string? _appearancesPath;

    [ObservableProperty]
    private string? _sheetsFolder;

    [ObservableProperty]
    private bool _enableFrameDurations = true;

    [ObservableProperty]
    private bool _enableFrameGroups = true;

    [ObservableProperty]
    private bool _idleAsStatic;

    /// <summary>
    /// Gets the command that performs the conversion.
    /// </summary>
    public IAsyncRelayCommand ConvertCommand { get; }

    private async Task ExecuteConversionAsync()
    {
        await _root.RunOperationAsync("Converting assets...", async token =>
        {
            if (SelectedTargetVersion == "10.98")
            {
                if (AppearancesPath is null || SheetsFolder is null)
                {
                    throw new InvalidOperationException("Appearances and sprite sheets are required.");
                }

                var reader = new AppearancesReader();
                await using var appStream = File.OpenRead(AppearancesPath);
                var appearances = reader.Read(appStream);
                var sprites = new List<Sprite>();
                foreach (var sheet in Directory.EnumerateFiles(SheetsFolder, "*.sheet"))
                {
                    await using var sheetStream = File.OpenRead(sheet);
                    var sheetReader = new SpriteSheetReader();
                    sprites.AddRange(sheetReader.Read(sheetStream));
                }

                var converter = new ClientVersionConverter();
                var options = new ClientVersionConverterOptions(EnableFrameDurations, EnableFrameGroups, IdleAsStatic);
                var result = converter.ConvertToLegacy(appearances, sprites, options);
                string output = Path.Combine(Path.GetTempPath(), "assets_legacy");
                Directory.CreateDirectory(output);
                await File.WriteAllBytesAsync(Path.Combine(output, "items.dat"), result.Dat, token);
                await File.WriteAllBytesAsync(Path.Combine(output, "tibia.spr"), result.Spr, token);
            }
            else
            {
                if (LegacyDatPath is null || LegacySprPath is null)
                {
                    throw new InvalidOperationException("Legacy DAT and SPR are required.");
                }

                var datReader = new DatLegacyReader();
                await using var datStream = File.OpenRead(LegacyDatPath);
                var items = datReader.ReadAll(datStream);
                await using var sprStream = File.OpenRead(LegacySprPath);
                var sprReader = new SprLegacyReader();
                var sprites = sprReader.ReadAll(sprStream);
                var appearances = items.Select(item => new Appearance
                {
                    Id = item.ClientId,
                    Type = item.Attributes.TryGetValue("Type", out var type) ? type : "Item",
                }).ToList();

                var writer = new AppearancesWriter();
                string output = Path.Combine(Path.GetTempPath(), "assets_v11");
                Directory.CreateDirectory(output);
                await using var appOut = File.Create(Path.Combine(output, "appearances.dat"));
                writer.Write(appearances, appOut);
                await using var sheetOut = File.Create(Path.Combine(output, "sprites.sheet"));
                var sheetWriter = new SpriteSheetWriter();
                sheetWriter.Write(sprites, sheetOut);
            }
        });
    }
}
