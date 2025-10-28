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

using AssetSuite.Core.Models;
using CommunityToolkit.Mvvm.ComponentModel;

namespace AssetSuite.UI.Models;

/// <summary>
/// Presentation model for <see cref="ItemType"/> entries.
/// </summary>
public partial class ItemTypeViewModel : ObservableObject
{
    /// <summary>
    /// Initializes a new instance of the <see cref="ItemTypeViewModel"/> class.
    /// </summary>
    /// <param name="model">The underlying model.</param>
    public ItemTypeViewModel(ItemType model)
    {
        Model = model;
        _clientId = model.ClientId;
        _serverId = model.ServerId;
        Attributes = new Dictionary<string, string>(model.Attributes);
    }

    /// <summary>
    /// Gets the wrapped model.
    /// </summary>
    public ItemType Model { get; }

    [ObservableProperty]
    private int _clientId;

    [ObservableProperty]
    private int _serverId;

    /// <summary>
    /// Gets the attribute collection for binding.
    /// </summary>
    public Dictionary<string, string> Attributes { get; }

    /// <summary>
    /// Pushes the changes back to the core model.
    /// </summary>
    public void Commit()
    {
        Model.ClientId = ClientId;
        Model.ServerId = ServerId;
        Model.Attributes.Clear();
        foreach (var entry in Attributes)
        {
            Model.Attributes[entry.Key] = entry.Value;
        }
    }
}
