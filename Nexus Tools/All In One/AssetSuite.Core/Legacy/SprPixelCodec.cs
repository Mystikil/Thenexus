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

namespace AssetSuite.Core.Legacy;

/// <summary>
/// Implements the classic Tibia sprite RLE codec that alternates transparent runs and colored runs.
/// </summary>
public static class SprPixelCodec
{
    private const int TileSize = 32 * 32 * 4;

    /// <summary>
    /// Decodes a legacy sprite payload into a RGBA buffer.
    /// </summary>
    /// <param name="encoded">The encoded RLE buffer.</param>
    /// <param name="destination">The destination buffer. It must have a length of at least 4096 bytes.</param>
    public static void Decode(ReadOnlySpan<byte> encoded, Span<byte> destination)
    {
        if (destination.Length < TileSize)
        {
            throw new ArgumentException("Destination buffer too small", nameof(destination));
        }

        destination[..TileSize].Clear();
        int writtenPixels = 0;
        int index = 0;
        while (writtenPixels < 32 * 32 && index < encoded.Length)
        {
            byte transparentCount = encoded[index++];
            writtenPixels += transparentCount;

            if (writtenPixels >= 32 * 32)
            {
                break;
            }

            byte coloredCount = encoded[index++];
            for (int i = 0; i < coloredCount; i++)
            {
                if (index + 2 >= encoded.Length)
                {
                    throw new InvalidDataException("Unexpected end of RLE payload.");
                }

                int dstPixel = writtenPixels++;
                int dstIndex = dstPixel * 4;
                destination[dstIndex] = encoded[index++];
                destination[dstIndex + 1] = encoded[index++];
                destination[dstIndex + 2] = encoded[index++];
                destination[dstIndex + 3] = 255;
            }
        }
    }

    /// <summary>
    /// Encodes the provided sprite into the legacy RLE format.
    /// </summary>
    /// <param name="sprite">The sprite to encode.</param>
    /// <returns>The encoded payload.</returns>
    public static byte[] Encode(Sprite sprite)
    {
        ArgumentNullException.ThrowIfNull(sprite);
        var buffer = new List<byte>(512);
        int pixelCount = sprite.Width * sprite.Height;
        ReadOnlySpan<byte> rgba = sprite.Rgba;
        int index = 0;
        while (index < pixelCount)
        {
            int transparent = 0;
            while (index < pixelCount && rgba[(index * 4) + 3] == 0)
            {
                transparent++;
                index++;
            }

            buffer.Add((byte)transparent);

            int colored = 0;
            int colorStart = index;
            while (index < pixelCount && rgba[(index * 4) + 3] > 0 && colored < byte.MaxValue)
            {
                colored++;
                index++;
            }

            buffer.Add((byte)colored);
            for (int i = 0; i < colored; i++)
            {
                int pixelIndex = (colorStart + i) * 4;
                buffer.Add(rgba[pixelIndex]);
                buffer.Add(rgba[pixelIndex + 1]);
                buffer.Add(rgba[pixelIndex + 2]);
            }
        }

        return buffer.ToArray();
    }
}
