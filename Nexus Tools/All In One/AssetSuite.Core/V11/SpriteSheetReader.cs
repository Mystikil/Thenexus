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

namespace AssetSuite.Core.V11;

/// <summary>
/// Reads simplified compressed sprite sheets for modern assets.
/// </summary>
public sealed class SpriteSheetReader
{
    private const uint Magic = 0x48535053;

    /// <summary>
    /// Reads the sheet from the stream and returns the contained sprites.
    /// </summary>
    /// <param name="stream">The sheet stream.</param>
    /// <returns>The decoded sprite collection.</returns>
    public IReadOnlyList<Sprite> Read(Stream stream)
    {
        ArgumentNullException.ThrowIfNull(stream);
        using var reader = new EndianBinaryReader(stream, leaveOpen: true);
        uint magic = reader.ReadUInt32();
        if (magic != Magic)
        {
            throw new InvalidDataException("Unsupported sprite sheet header.");
        }

        int width = reader.ReadInt32();
        int height = reader.ReadInt32();
        int count = reader.ReadInt32();
        if (count < 0)
        {
            throw new InvalidDataException("Sprite count cannot be negative.");
        }

        int payloadLength = reader.ReadInt32();
        byte[] compressed = new byte[payloadLength];
        reader.ReadExactly(compressed);
        byte[] raw = LzmaHelper.Decompress(compressed);
        int spriteSize = width * height * 4;
        if (raw.Length != spriteSize * count)
        {
            throw new InvalidDataException("Sprite payload length mismatch.");
        }

        var sprites = new List<Sprite>(count);
        for (int i = 0; i < count; i++)
        {
            byte[] rgba = new byte[spriteSize];
            Buffer.BlockCopy(raw, i * spriteSize, rgba, 0, spriteSize);
            sprites.Add(new Sprite(i + 1, width, height, rgba));
        }

        return sprites;
    }
}
