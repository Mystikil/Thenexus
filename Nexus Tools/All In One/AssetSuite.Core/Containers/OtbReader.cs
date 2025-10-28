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

namespace AssetSuite.Core.Containers;

/// <summary>
/// Parses the simplified OTB container format used in the integration tests.
/// </summary>
public sealed class OtbReader
{
    private const uint Magic = 0x424F544F; // OTBO

    /// <summary>
    /// Reads the container from the stream.
    /// </summary>
    /// <param name="stream">The OTB stream.</param>
    /// <returns>The parsed items and version information.</returns>
    public (int Version, List<ItemType> Items) Read(Stream stream)
    {
        ArgumentNullException.ThrowIfNull(stream);
        using var reader = new EndianBinaryReader(stream, leaveOpen: true);
        uint magic = reader.ReadUInt32();
        if (magic != Magic)
        {
            throw new InvalidDataException("Unsupported OTB header.");
        }

        int version = reader.ReadInt32();
        int count = reader.ReadInt32();
        var items = new List<ItemType>(count);
        for (int i = 0; i < count; i++)
        {
            var item = new ItemType
            {
                ServerId = reader.ReadInt32(),
                ClientId = reader.ReadInt32(),
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

        return (version, items);
    }
}
