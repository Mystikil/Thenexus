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
/// Writes legacy sprite files compatible with the simplified reader implementation.
/// </summary>
public sealed class SprLegacyWriter
{
    private const uint Magic = 0x46525053;

    /// <summary>
    /// Writes all sprites to the provided stream.
    /// </summary>
    /// <param name="sprites">The sprites to serialize.</param>
    /// <param name="destination">The destination stream.</param>
    public void WriteAll(IEnumerable<Sprite> sprites, Stream destination)
    {
        ArgumentNullException.ThrowIfNull(sprites);
        ArgumentNullException.ThrowIfNull(destination);
        using var writer = new EndianBinaryWriter(destination, leaveOpen: true);
        writer.Write(Magic);
        var spriteList = sprites.ToList();
        writer.Write(spriteList.Count);
        foreach (var sprite in spriteList)
        {
            byte[] payload = SprPixelCodec.Encode(sprite);
            writer.Write(sprite.Id);
            writer.Write(sprite.Width);
            writer.Write(sprite.Height);
            writer.Write(payload.Length);
            writer.Write(payload);
        }
    }
}
