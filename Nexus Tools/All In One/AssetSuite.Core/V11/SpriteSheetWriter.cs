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
/// Writes sprite sheet payloads that mimic the structure of modern Tibia assets in a simplified form.
/// </summary>
public sealed class SpriteSheetWriter
{
    private const uint Magic = 0x48535053; // SPSH

    /// <summary>
    /// Writes the sprite sheet to the provided stream.
    /// </summary>
    /// <param name="sprites">The sprites to serialize.</param>
    /// <param name="stream">The destination stream.</param>
    public void Write(IEnumerable<Sprite> sprites, Stream stream)
    {
        ArgumentNullException.ThrowIfNull(sprites);
        ArgumentNullException.ThrowIfNull(stream);
        var list = sprites.ToList();
        if (list.Count == 0)
        {
            throw new ArgumentException("At least one sprite is required to produce a sheet.", nameof(sprites));
        }

        int width = list[0].Width;
        int height = list[0].Height;
        foreach (var sprite in list)
        {
            if (sprite.Width != width || sprite.Height != height)
            {
                throw new InvalidOperationException("All sprites must share dimensions for sheet packing.");
            }
        }

        using var writer = new EndianBinaryWriter(stream, leaveOpen: true);
        writer.Write(Magic);
        writer.Write(width);
        writer.Write(height);
        writer.Write(list.Count);

        int spriteSize = width * height * 4;
        byte[] raw = new byte[list.Count * spriteSize];
        for (int i = 0; i < list.Count; i++)
        {
            Buffer.BlockCopy(list[i].Rgba, 0, raw, i * spriteSize, spriteSize);
        }

        byte[] compressed = LzmaHelper.Compress(raw);
        writer.Write(compressed.Length);
        writer.Write(compressed);
    }
}
