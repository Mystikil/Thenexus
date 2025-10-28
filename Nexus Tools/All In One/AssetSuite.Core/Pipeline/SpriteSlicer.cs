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

using System.Security.Cryptography;
using AssetSuite.Core.Imaging;
using AssetSuite.Core.Models;

namespace AssetSuite.Core.Pipeline;

/// <summary>
/// Provides slicing helpers that split sprite sheets into 32x32 legacy tiles.
/// </summary>
public static class SpriteSlicer
{
    /// <summary>
    /// Slices the provided sheet into equally sized tiles.
    /// </summary>
    /// <param name="sheet">The source bitmap.</param>
    /// <param name="tileWidth">The tile width.</param>
    /// <param name="tileHeight">The tile height.</param>
    /// <returns>The resulting sprites.</returns>
    public static IEnumerable<Sprite> SliceSheet(Bitmap sheet, int tileWidth = 32, int tileHeight = 32)
    {
        ArgumentNullException.ThrowIfNull(sheet);
        if (tileWidth <= 0 || tileHeight <= 0)
        {
            throw new ArgumentOutOfRangeException(nameof(tileWidth));
        }

        int columns = sheet.Width / tileWidth;
        int rows = sheet.Height / tileHeight;
        var pixels = sheet.GetPixelSpan().ToArray();
        int sheetWidth = sheet.Width;
        int index = 1;
        for (int row = 0; row < rows; row++)
        {
            for (int column = 0; column < columns; column++)
            {
                byte[] spritePixels = new byte[tileWidth * tileHeight * 4];
                for (int y = 0; y < tileHeight; y++)
                {
                    int sourceY = (row * tileHeight) + y;
                    int sourceX = column * tileWidth;
                    int sourceIndex = ((sourceY * sheetWidth) + sourceX) * 4;
                    int destinationIndex = y * tileWidth * 4;
                    Buffer.BlockCopy(pixels, sourceIndex, spritePixels, destinationIndex, tileWidth * 4);
                }

                yield return new Sprite(index++, tileWidth, tileHeight, spritePixels);
            }
        }
    }

    /// <summary>
    /// Deduplicates sprites by comparing their SHA256 hash.
    /// </summary>
    /// <param name="sprites">The sprites to deduplicate.</param>
    /// <returns>The unique sprite sequence.</returns>
    public static IEnumerable<Sprite> DedupeByHash(IEnumerable<Sprite> sprites)
    {
        ArgumentNullException.ThrowIfNull(sprites);
        using var sha = SHA256.Create();
        var seen = new HashSet<string>(StringComparer.Ordinal);
        foreach (var sprite in sprites)
        {
            string hash = Convert.ToHexString(sha.ComputeHash(sprite.Rgba));
            if (seen.Add(hash))
            {
                yield return sprite;
            }
        }
    }
}
