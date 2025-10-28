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

using Avalonia.Controls;

namespace AssetSuite.UI.Services;

/// <summary>
/// Provides file dialog helpers used by the UI commands.
/// </summary>
public interface IFileDialogService
{
    /// <summary>
    /// Opens a file picker dialog.
    /// </summary>
    /// <param name="owner">The owning window.</param>
    /// <param name="title">Dialog title.</param>
    /// <param name="filters">Optional filters.</param>
    /// <returns>The selected path or null.</returns>
    Task<string?> OpenFileAsync(Window owner, string title, params FilePickerFileType[] filters);

    /// <summary>
    /// Presents a folder picker dialog.
    /// </summary>
    /// <param name="owner">The owning window.</param>
    /// <param name="title">The dialog title.</param>
    /// <returns>The selected folder path or null.</returns>
    Task<string?> OpenFolderAsync(Window owner, string title);
}
