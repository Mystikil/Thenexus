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
/// Provides a high level API for reading legacy <c>.spr</c> files.
/// </summary>
public sealed class SprLegacyReader
{
    private const uint Magic = 0x46525053; // SPRF

    /// <summary>
    /// Reads all sprites from the provided legacy stream.
    /// </summary>
    /// <param name="spr">The legacy sprite stream.</param>
    /// <returns>A list of decoded sprites.</returns>
    public List<Sprite> ReadAll(Stream spr)
    {
        ArgumentNullException.ThrowIfNull(spr);
        using var reader = new EndianBinaryReader(spr, leaveOpen: true);
        uint magic = reader.ReadUInt32();
        if (magic != Magic)
        {
            throw new InvalidDataException("Unsupported SPR header.");
        }

        int count = reader.ReadInt32();
        if (count < 0)
        {
            throw new InvalidDataException("Sprite count cannot be negative.");
        }

        var sprites = new List<Sprite>(count);
        byte[] decodeBuffer = new byte[32 * 32 * 4];
        for (int i = 0; i < count; i++)
        {
            int id = reader.ReadInt32();
            int width = reader.ReadInt32();
            int height = reader.ReadInt32();
            int length = reader.ReadInt32();
            if (length < 0)
            {
                throw new InvalidDataException("Sprite payload cannot be negative.");
            }

            byte[] encoded = new byte[length];
            reader.ReadExactly(encoded);
            SprPixelCodec.Decode(encoded, decodeBuffer);
            byte[] rgba = new byte[width * height * 4];
            Buffer.BlockCopy(decodeBuffer, 0, rgba, 0, rgba.Length);
            sprites.Add(new Sprite(id, width, height, rgba));
        }

        return sprites;
    }
}
