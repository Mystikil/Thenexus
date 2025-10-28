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

using Avalonia;
using Avalonia.Media.Imaging;
using Avalonia.Platform;
using AssetSuite.Core.Models;

namespace AssetSuite.UI.Models;

/// <summary>
/// Presentation model for sprite previews in the UI.
/// </summary>
public sealed class SpritePreview
{
    /// <summary>
    /// Initializes a new instance of the <see cref="SpritePreview"/> class.
    /// </summary>
    /// <param name="sprite">The underlying sprite.</param>
    public SpritePreview(Sprite sprite)
    {
        Sprite = sprite;
        Bitmap = CreateBitmap(sprite);
    }

    /// <summary>
    /// Gets the underlying sprite.
    /// </summary>
    public Sprite Sprite { get; }

    /// <summary>
    /// Gets the bitmap for rendering.
    /// </summary>
    public Bitmap Bitmap { get; }

    private static Bitmap CreateBitmap(Sprite sprite)
    {
        var writeable = new WriteableBitmap(new PixelSize(sprite.Width, sprite.Height), new Vector(96, 96), PixelFormat.Bgra8888, AlphaFormat.Premul);
        using var frame = writeable.Lock();
        for (int y = 0; y < sprite.Height; y++)
        {
            for (int x = 0; x < sprite.Width; x++)
            {
                int index = ((y * sprite.Width) + x) * 4;
                byte r = sprite.Rgba[index];
                byte g = sprite.Rgba[index + 1];
                byte b = sprite.Rgba[index + 2];
                byte a = sprite.Rgba[index + 3];
                int dest = index;
                frame.Address[dest] = b;
                frame.Address[dest + 1] = g;
                frame.Address[dest + 2] = r;
                frame.Address[dest + 3] = a;
            }
        }

        return writeable;
    }
}
