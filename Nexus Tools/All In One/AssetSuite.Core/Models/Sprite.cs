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

namespace AssetSuite.Core.Models;

/// <summary>
/// Represents a single sprite within a sprite sheet, expressed as raw RGBA pixels.
/// </summary>
public sealed class Sprite
{
    /// <summary>
    /// Initializes a new instance of the <see cref="Sprite"/> class.
    /// </summary>
    /// <param name="id">The sprite identifier that maps to Tibia resource indices.</param>
    /// <param name="width">The sprite width in pixels.</param>
    /// <param name="height">The sprite height in pixels.</param>
    /// <param name="rgba">The pixel buffer in RGBA byte order.</param>
    /// <exception cref="ArgumentNullException">Thrown when <paramref name="rgba"/> is null.</exception>
    public Sprite(int id, int width, int height, byte[] rgba)
    {
        ArgumentNullException.ThrowIfNull(rgba);
        Id = id;
        Width = width;
        Height = height;
        Rgba = rgba;
    }

    /// <summary>
    /// Gets the sprite identifier.
    /// </summary>
    public int Id { get; }

    /// <summary>
    /// Gets the sprite width in pixels.
    /// </summary>
    public int Width { get; }

    /// <summary>
    /// Gets the sprite height in pixels.
    /// </summary>
    public int Height { get; }

    /// <summary>
    /// Gets the RGBA pixel data in row-major order.
    /// </summary>
    public byte[] Rgba { get; }

    /// <summary>
    /// Creates a defensive copy of the sprite with the provided identifier.
    /// </summary>
    /// <param name="newId">The identifier to assign to the cloned sprite.</param>
    /// <returns>The cloned <see cref="Sprite"/>.</returns>
    public Sprite WithId(int newId)
    {
        return new Sprite(newId, Width, Height, (byte[])Rgba.Clone());
    }
}
