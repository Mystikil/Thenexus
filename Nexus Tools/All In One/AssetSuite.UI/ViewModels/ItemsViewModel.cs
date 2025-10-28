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
using Avalonia.Threading;
using AssetSuite.Core.Containers;
using AssetSuite.Core.Models;
using AssetSuite.UI.Models;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;

namespace AssetSuite.UI.ViewModels;

/// <summary>
/// View model for the OTB editor tab.
/// </summary>
public partial class ItemsViewModel : ObservableObject
{
    private readonly MainWindowViewModel _root;

    /// <summary>
    /// Initializes a new instance of the <see cref="ItemsViewModel"/> class.
    /// </summary>
    /// <param name="root">The root view model.</param>
    public ItemsViewModel(MainWindowViewModel root)
    {
        _root = root;
        Items = new ObservableCollection<ItemTypeViewModel>();
        LoadOtbCommand = new AsyncRelayCommand<string?>(LoadOtbAsync);
        SaveOtbCommand = new AsyncRelayCommand<string?>(SaveOtbAsync, CanSave);
        Items.CollectionChanged += (_, _) => SaveOtbCommand.NotifyCanExecuteChanged();
    }

    /// <summary>
    /// Gets the item list for binding.
    /// </summary>
    public ObservableCollection<ItemTypeViewModel> Items { get; }

    /// <summary>
    /// Gets or sets the current file path.
    /// </summary>
    [ObservableProperty]
    private string? _currentPath;

    /// <summary>
    /// Gets the command used to load an OTB file.
    /// </summary>
    public IAsyncRelayCommand<string?> LoadOtbCommand { get; }

    /// <summary>
    /// Gets the command used to save the active OTB file.
    /// </summary>
    public IAsyncRelayCommand<string?> SaveOtbCommand { get; }

    private async Task LoadOtbAsync(string? path)
    {
        if (string.IsNullOrWhiteSpace(path))
        {
            return;
        }

            await _root.RunOperationAsync("Loading OTB...", async token =>
            {
            await using var stream = File.OpenRead(path);
            var reader = new OtbReader();
            var result = reader.Read(stream);
            await Avalonia.Threading.Dispatcher.UIThread.InvokeAsync(() =>
            {
                Items.Clear();
                foreach (var item in result.Items)
                {
                    Items.Add(new ItemTypeViewModel(item));
                }

                CurrentPath = path;
                _root.Settings.RecentFiles.Add(path);
                _root.Settings.RecentFiles = _root.Settings.RecentFiles.Distinct().Take(10).ToList();
                SaveOtbCommand.NotifyCanExecuteChanged();
            });
        });
    }

    private bool CanSave(string? path) => Items.Count > 0;

    private async Task SaveOtbAsync(string? path)
    {
        string target = path ?? CurrentPath ?? string.Empty;
        if (string.IsNullOrWhiteSpace(target))
        {
            return;
        }

        await _root.RunOperationAsync("Saving OTB...", async token =>
        {
            foreach (var item in Items)
            {
                item.Commit();
            }

            await using var stream = File.Create(target);
            var writer = new OtbWriter();
            writer.Write(1, Items.Select(i => i.Model), stream);
        });
    }
}
