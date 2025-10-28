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

using AssetSuite.Core.Legacy;
using AssetSuite.Core.Models;
using AssetSuite.Core.V11;

namespace AssetSuite.Core.Pipeline;

/// <summary>
/// Provides conversion routines between V11 and legacy assets.
/// </summary>
public sealed class ClientVersionConverter
{
    private readonly V11ToLegacyMapper _mapper = new();
    private readonly DatLegacyWriter _datWriter = new();
    private readonly SprLegacyWriter _sprWriter = new();

    /// <summary>
    /// Converts modern appearance and sprite data to the simplified legacy binary payloads.
    /// </summary>
    /// <param name="appearances">The modern appearance definitions.</param>
    /// <param name="sprites">The sprite resources referenced by the appearances.</param>
    /// <param name="options">Conversion options that control the mapping behavior.</param>
    /// <returns>The DAT and SPR payloads.</returns>
    public (byte[] Dat, byte[] Spr) ConvertToLegacy(
        IEnumerable<Appearance> appearances,
        IReadOnlyList<Sprite> sprites,
        ClientVersionConverterOptions options)
    {
        ArgumentNullException.ThrowIfNull(appearances);
        ArgumentNullException.ThrowIfNull(sprites);
        var mapperOptions = new V11ToLegacyOptions(
            options.EnableFrameDurations,
            options.EnableFrameGroups,
            options.IdleAnimationAsStatic);
        var mapped = _mapper.Map(appearances, sprites, mapperOptions);
        using var datStream = new MemoryStream();
        using var sprStream = new MemoryStream();
        _datWriter.WriteAll(mapped.Items, datStream);
        _sprWriter.WriteAll(mapped.Sprites, sprStream);
        return (datStream.ToArray(), sprStream.ToArray());
    }
}

/// <summary>
/// Options for the <see cref="ClientVersionConverter"/>.
/// </summary>
/// <param name="EnableFrameDurations">True to preserve frame durations.</param>
/// <param name="EnableFrameGroups">True to keep multiple frame groups.</param>
/// <param name="IdleAnimationAsStatic">True to coerce idle animations into static sprites.</param>
public readonly record struct ClientVersionConverterOptions(bool EnableFrameDurations, bool EnableFrameGroups, bool IdleAnimationAsStatic);
