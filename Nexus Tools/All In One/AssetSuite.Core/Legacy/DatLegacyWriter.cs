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

using AssetSuite.Core.IO;
using AssetSuite.Core.Models;

namespace AssetSuite.Core.Legacy;

/// <summary>
/// Serializes the simplified legacy DAT format used in the developer tooling.
/// </summary>
public sealed class DatLegacyWriter
{
    private const uint Magic = 0x46544144;

    /// <summary>
    /// Writes all item types to the destination stream.
    /// </summary>
    /// <param name="items">The items to serialize.</param>
    /// <param name="stream">The destination stream.</param>
    public void WriteAll(IEnumerable<ItemType> items, Stream stream)
    {
        ArgumentNullException.ThrowIfNull(items);
        ArgumentNullException.ThrowIfNull(stream);
        using var writer = new EndianBinaryWriter(stream, leaveOpen: true);
        var list = items.ToList();
        writer.Write(Magic);
        writer.Write(list.Count);
        foreach (var item in list)
        {
            writer.Write(item.ClientId);
            writer.Write(item.ServerId);
            writer.Write(item.Attributes.Count);
            foreach (var kv in item.Attributes)
            {
                writer.Write(kv.Key);
                writer.Write(kv.Value);
            }
        }
    }
}
