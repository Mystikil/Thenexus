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

using System.Collections.Generic;
using System.Collections.ObjectModel;
using System.IO;
using System.Linq;
using System.Threading.Tasks;
using Avalonia.Threading;
using AssetSuite.Core.Legacy;
using AssetSuite.Core.Models;
using AssetSuite.UI.Models;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;

namespace AssetSuite.UI.ViewModels;

/// <summary>
/// View model for sprite browsing and exporting.
/// </summary>
public partial class SpritesViewModel : ObservableObject
{
    private readonly MainWindowViewModel _root;

    /// <summary>
    /// Initializes a new instance of the <see cref="SpritesViewModel"/> class.
    /// </summary>
    /// <param name="root">The root view model.</param>
    public SpritesViewModel(MainWindowViewModel root)
    {
        _root = root;
        Sprites = new ObservableCollection<SpritePreview>();
        LoadSpritesCommand = new AsyncRelayCommand<string?>(LoadSpritesAsync);
        ExportSelectedCommand = new AsyncRelayCommand(ExportSelectedAsync, () => SelectedSprite is not null);
        ZoomLevels = new ObservableCollection<double>(new[] { 0.5, 1.0, 2.0, 4.0 });
        _selectedZoom = 1.0;
    }

    /// <summary>
    /// Gets the sprite collection.
    /// </summary>
    public ObservableCollection<SpritePreview> Sprites { get; }

    /// <summary>
    /// Gets the selected sprite previews.
    /// </summary>
    [ObservableProperty]
    private SpritePreview? _selectedSprite;

    /// <summary>
    /// Gets the available zoom levels.
    /// </summary>
    public ObservableCollection<double> ZoomLevels { get; }

    [ObservableProperty]
    private double _selectedZoom;

    /// <summary>
    /// Gets the command used to load sprites.
    /// </summary>
    public IAsyncRelayCommand<string?> LoadSpritesCommand { get; }

    /// <summary>
    /// Gets the command used to export selected sprites.
    /// </summary>
    public IAsyncRelayCommand ExportSelectedCommand { get; }

    [ObservableProperty]
    private string? _sourcePath;

    partial void OnSelectedSpriteChanged(SpritePreview? value)
    {
        ExportSelectedCommand.NotifyCanExecuteChanged();
    }

    private async Task LoadSpritesAsync(string? path)
    {
        if (string.IsNullOrWhiteSpace(path))
        {
            return;
        }

        await _root.RunOperationAsync("Loading sprites...", async token =>
        {
            SourcePath = path;
            await using var stream = File.OpenRead(path);
            var reader = new SprLegacyReader();
            List<Sprite> sprites = reader.ReadAll(stream);
            await Avalonia.Threading.Dispatcher.UIThread.InvokeAsync(() =>
            {
                Sprites.Clear();
                foreach (var sprite in sprites)
                {
                    Sprites.Add(new SpritePreview(sprite));
                }
                SelectedSprite = Sprites.FirstOrDefault();
            });
        });
    }

    private async Task ExportSelectedAsync()
    {
        var preview = SelectedSprite;
        if (preview is null)
        {
            return;
        }

        string output = Path.Combine(Path.GetTempPath(), "sprites_export");
        Directory.CreateDirectory(output);
        await _root.RunOperationAsync("Exporting sprites...", async token =>
        {
            string path = Path.Combine(output, $"sprite_{preview.Sprite.Id:D4}.bin");
            await File.WriteAllBytesAsync(path, preview.Sprite.Rgba, token);
        });
    }
}
