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

using System.Runtime.InteropServices;
using SixLabors.ImageSharp;
using SixLabors.ImageSharp.PixelFormats;

namespace AssetSuite.Core.Imaging;

/// <summary>
/// Light-weight wrapper around ImageSharp bitmaps used within the slicing pipeline.
/// </summary>
public sealed class Bitmap : IDisposable
{
    private readonly Image<Rgba32> _image;

    private Bitmap(Image<Rgba32> image)
    {
        _image = image;
    }

    /// <summary>
    /// Gets the bitmap width in pixels.
    /// </summary>
    public int Width => _image.Width;

    /// <summary>
    /// Gets the bitmap height in pixels.
    /// </summary>
    public int Height => _image.Height;

    /// <summary>
    /// Loads a bitmap from a PNG stream.
    /// </summary>
    /// <param name="stream">The source stream.</param>
    /// <returns>The loaded <see cref="Bitmap"/>.</returns>
    public static Bitmap Load(Stream stream)
    {
        ArgumentNullException.ThrowIfNull(stream);
        var image = Image.Load<Rgba32>(stream);
        return new Bitmap(image);
    }

    /// <summary>
    /// Creates a bitmap from the provided pixel span.
    /// </summary>
    /// <param name="width">The width in pixels.</param>
    /// <param name="height">The height in pixels.</param>
    /// <param name="pixels">The RGBA pixels.</param>
    /// <returns>A new <see cref="Bitmap"/> instance.</returns>
    public static Bitmap FromPixels(int width, int height, ReadOnlySpan<byte> pixels)
    {
        var image = new Image<Rgba32>(width, height);
        var buffer = pixels[..(width * height * 4)].ToArray();
        image.ProcessPixelRows(accessor =>
        {
            for (int y = 0; y < accessor.Height; y++)
            {
                var row = accessor.GetRowSpan(y);
                var sourceSlice = buffer.AsSpan(y * width * 4, width * 4);
                MemoryMarshal.Cast<byte, Rgba32>(sourceSlice).CopyTo(row);
            }
        });
        return new Bitmap(image);
    }

    /// <summary>
    /// Gets the raw pixel span for the bitmap in RGBA order.
    /// </summary>
    /// <returns>The pixel span.</returns>
    public Span<byte> GetWritablePixelSpan()
    {
        if (!_image.DangerousTryGetSinglePixelMemory(out var memory))
        {
            throw new InvalidOperationException("Unable to access pixel buffer.");
        }

        return MemoryMarshal.AsBytes(memory.Span);
    }

    /// <summary>
    /// Gets the raw pixel span for the bitmap in RGBA order.
    /// </summary>
    /// <returns>The pixel span.</returns>
    public ReadOnlySpan<byte> GetPixelSpan()
    {
        if (!_image.DangerousTryGetSinglePixelMemory(out var memory))
        {
            throw new InvalidOperationException("Unable to access pixel buffer.");
        }

        return MemoryMarshal.AsBytes(memory.Span);
    }

    /// <summary>
    /// Saves the bitmap to a stream as PNG.
    /// </summary>
    /// <param name="stream">The destination stream.</param>
    public void SavePng(Stream stream)
    {
        ArgumentNullException.ThrowIfNull(stream);
        _image.SaveAsPng(stream);
    }

    /// <inheritdoc />
    public void Dispose()
    {
        _image.Dispose();
    }
}
