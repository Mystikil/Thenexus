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

using System.Linq;
using Avalonia.Controls;
using Avalonia.Platform.Storage;

namespace AssetSuite.UI.Services;

/// <summary>
/// Avalonia storage provider backed implementation of <see cref="IFileDialogService"/>.
/// </summary>
public sealed class FileDialogService : IFileDialogService
{
    /// <inheritdoc />
    public async Task<string?> OpenFileAsync(Window owner, string title, params FilePickerFileType[] filters)
    {
        ArgumentNullException.ThrowIfNull(owner);
        var options = new FilePickerOpenOptions
        {
            Title = title,
            AllowMultiple = false,
            FileTypeFilter = filters?.Length > 0 ? filters : null,
        };

        var result = await owner.StorageProvider.OpenFilePickerAsync(options).ConfigureAwait(false);
        return result.FirstOrDefault()?.Path.LocalPath;
    }

    /// <inheritdoc />
    public async Task<string?> OpenFolderAsync(Window owner, string title)
    {
        ArgumentNullException.ThrowIfNull(owner);
        var options = new FolderPickerOpenOptions
        {
            Title = title,
            AllowMultiple = false,
        };

        var result = await owner.StorageProvider.OpenFolderPickerAsync(options).ConfigureAwait(false);
        return result.FirstOrDefault()?.Path.LocalPath;
    }
}
