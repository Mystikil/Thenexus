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
/// Reads simplified legacy DAT files that are used by the integration tests.
/// </summary>
public sealed class DatLegacyReader
{
    private const uint Magic = 0x46544144; // DATF

    /// <summary>
    /// Reads all item types from the provided stream.
    /// </summary>
    /// <param name="stream">The DAT stream.</param>
    /// <returns>The parsed <see cref="ItemType"/> entries.</returns>
    public List<ItemType> ReadAll(Stream stream)
    {
        ArgumentNullException.ThrowIfNull(stream);
        using var reader = new EndianBinaryReader(stream, leaveOpen: true);
        uint magic = reader.ReadUInt32();
        if (magic != Magic)
        {
            throw new InvalidDataException("Unsupported DAT header.");
        }

        int count = reader.ReadInt32();
        if (count < 0)
        {
            throw new InvalidDataException("Item count cannot be negative.");
        }

        var items = new List<ItemType>(count);
        for (int i = 0; i < count; i++)
        {
            var item = new ItemType
            {
                ClientId = reader.ReadInt32(),
                ServerId = reader.ReadInt32(),
            };

            int attributeCount = reader.ReadInt32();
            for (int a = 0; a < attributeCount; a++)
            {
                string key = reader.ReadString();
                string value = reader.ReadString();
                item.Attributes[key] = value;
            }

            items.Add(item);
        }

        return items;
    }
}
