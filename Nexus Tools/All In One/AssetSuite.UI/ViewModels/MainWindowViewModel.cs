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

using System.Threading;
using System.Threading.Tasks;
using AssetSuite.UI.Models;
using AssetSuite.UI.Services;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;

namespace AssetSuite.UI.ViewModels;

/// <summary>
/// Root view model that coordinates the individual tool tabs.
/// </summary>
public partial class MainWindowViewModel : ObservableObject
{
    private readonly ISettingsService _settingsService;
    private readonly IFileDialogService _fileDialogService;
    private CancellationTokenSource? _operationToken;

    /// <summary>
    /// Initializes a new instance of the <see cref="MainWindowViewModel"/> class.
    /// </summary>
    /// <param name="fileDialogService">The file dialog service.</param>
    public MainWindowViewModel(IFileDialogService fileDialogService)
        : this(fileDialogService, new SettingsService())
    {
    }

    /// <summary>
    /// Initializes a new instance of the <see cref="MainWindowViewModel"/> class.
    /// </summary>
    /// <param name="fileDialogService">The file dialog service.</param>
    /// <param name="settingsService">The settings persistence service.</param>
    public MainWindowViewModel(IFileDialogService fileDialogService, ISettingsService settingsService)
    {
        _fileDialogService = fileDialogService;
        _settingsService = settingsService;
        Items = new ItemsViewModel(this);
        Appearances = new AppearancesViewModel(this);
        Sprites = new SpritesViewModel(this);
        Converter = new ConvertViewModel(this);
        Batch = new BatchViewModel();
        Settings = _settingsService.Load();
        StatusMessage = "Ready";
    }

    /// <summary>
    /// Gets the settings model.
    /// </summary>
    public AppSettings Settings { get; }

    /// <summary>
    /// Gets the item editor view model.
    /// </summary>
    public ItemsViewModel Items { get; }

    /// <summary>
    /// Gets the appearances view model.
    /// </summary>
    public AppearancesViewModel Appearances { get; }

    /// <summary>
    /// Gets the sprite browser view model.
    /// </summary>
    public SpritesViewModel Sprites { get; }

    /// <summary>
    /// Gets the conversion view model.
    /// </summary>
    public ConvertViewModel Converter { get; }

    /// <summary>
    /// Gets the batch tooling view model.
    /// </summary>
    public BatchViewModel Batch { get; }

    /// <summary>
    /// Gets or sets the owning window reference.
    /// </summary>
    public object? Owner { get; set; }

    /// <summary>
    /// Gets a command that cancels the current operation.
    /// </summary>
    public IRelayCommand CancelCommand => new RelayCommand(() => _operationToken?.Cancel(), () => _operationToken is not null);

    /// <summary>
    /// Gets the file dialog service.
    /// </summary>
    public IFileDialogService FileDialogService => _fileDialogService;

    /// <summary>
    /// Gets the settings service.
    /// </summary>
    public ISettingsService SettingsService => _settingsService;

    [ObservableProperty]
    private string _statusMessage = string.Empty;

    [ObservableProperty]
    private bool _isBusy;

    [ObservableProperty]
    private double _progress;

    /// <summary>
    /// Runs an asynchronous operation while updating the status bar.
    /// </summary>
    /// <param name="description">The operation description.</param>
    /// <param name="operation">The asynchronous delegate.</param>
    /// <returns>A task that completes when the operation finishes.</returns>
    public async Task RunOperationAsync(string description, Func<CancellationToken, Task> operation)
    {
        if (_isBusy)
        {
            return;
        }

        StatusMessage = description;
        IsBusy = true;
        _operationToken = new CancellationTokenSource();

        try
        {
            await operation(_operationToken.Token).ConfigureAwait(false);
            StatusMessage = "Completed";
        }
        catch (OperationCanceledException)
        {
            StatusMessage = "Cancelled";
        }
        catch (Exception ex)
        {
            StatusMessage = ex.Message;
        }
        finally
        {
            _operationToken.Dispose();
            _operationToken = null;
            IsBusy = false;
            Progress = 0;
            _settingsService.Save(Settings);
        }
    }
}
