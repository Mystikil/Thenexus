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

using System.Collections.ObjectModel;
using System.IO;
using System.Linq;
using System.Threading.Tasks;
using AssetSuite.Core.Models;
using AssetSuite.Core.V11;
using Avalonia.Threading;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;

namespace AssetSuite.UI.ViewModels;

/// <summary>
/// View model for the appearances tab.
/// </summary>
public partial class AppearancesViewModel : ObservableObject
{
    private readonly MainWindowViewModel _root;

    /// <summary>
    /// Initializes a new instance of the <see cref="AppearancesViewModel"/> class.
    /// </summary>
    /// <param name="root">The root view model.</param>
    public AppearancesViewModel(MainWindowViewModel root)
    {
        _root = root;
        Appearances = new ObservableCollection<Appearance>();
        LoadAppearancesCommand = new AsyncRelayCommand<string?>(LoadAppearancesAsync);
        ExportPngCommand = new AsyncRelayCommand(ExportSpritesAsync, () => SelectedAppearance is not null);
    }

    /// <summary>
    /// Gets the appearance collection.
    /// </summary>
    public ObservableCollection<Appearance> Appearances { get; }

    /// <summary>
    /// Gets or sets the selected appearance.
    /// </summary>
    [ObservableProperty]
    private Appearance? _selectedAppearance;

    [ObservableProperty]
    private string? _sourcePath;

    /// <summary>
    /// Gets the command used to load appearances.
    /// </summary>
    public IAsyncRelayCommand<string?> LoadAppearancesCommand { get; }

    /// <summary>
    /// Gets the command used to export sprites for the selected appearance.
    /// </summary>
    public IAsyncRelayCommand ExportPngCommand { get; }

    private async Task LoadAppearancesAsync(string? path)
    {
        if (string.IsNullOrWhiteSpace(path))
        {
            return;
        }

            await _root.RunOperationAsync("Loading appearances...", async token =>
            {
            SourcePath = path;
            await using var stream = File.OpenRead(path);
            var reader = new AppearancesReader();
            var appearances = reader.Read(stream);
            await Avalonia.Threading.Dispatcher.UIThread.InvokeAsync(() =>
            {
                Appearances.Clear();
                foreach (var appearance in appearances)
                {
                    Appearances.Add(appearance);
                }

                SelectedAppearance = Appearances.FirstOrDefault();
            });
        });
    }

    private async Task ExportSpritesAsync()
    {
        var appearance = SelectedAppearance;
        if (appearance is null)
        {
            return;
        }

        string directory = Path.Combine(Path.GetTempPath(), $"appearance_{appearance.Id}");
        Directory.CreateDirectory(directory);
        await _root.RunOperationAsync("Exporting sprites...", async token =>
        {
            int frameIndex = 0;
            foreach (var group in appearance.FrameGroups)
            {
                foreach (var frame in group.Frames)
                {
                    string infoPath = Path.Combine(directory, $"frame_{frameIndex++}.txt");
                    await File.WriteAllTextAsync(infoPath, string.Join(",", frame.SpriteIds), token);
                }
            }
        });
    }

    partial void OnSelectedAppearanceChanged(Appearance? value)
    {
        ExportPngCommand.NotifyCanExecuteChanged();
    }
}
