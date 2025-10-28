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

namespace AssetSuite.Core.V11;

/// <summary>
/// Converts modern appearance metadata back into the simplified legacy models used by the reader and writer classes.
/// </summary>
public sealed class V11ToLegacyMapper
{
    /// <summary>
    /// Performs the mapping using the provided configuration.
    /// </summary>
    /// <param name="appearances">The modern appearance entries.</param>
    /// <param name="sprites">The sprites that back the appearance references.</param>
    /// <param name="options">The conversion options.</param>
    /// <returns>The mapped legacy item and sprite collections.</returns>
    public (List<ItemType> Items, List<Sprite> Sprites) Map(
        IEnumerable<Appearance> appearances,
        IReadOnlyList<Sprite> sprites,
        V11ToLegacyOptions options)
    {
        ArgumentNullException.ThrowIfNull(appearances);
        ArgumentNullException.ThrowIfNull(sprites);
        var itemList = new List<ItemType>();
        var spriteList = new List<Sprite>();
        int nextSpriteId = 1;

        foreach (var appearance in appearances)
        {
            var item = new ItemType
            {
                ClientId = appearance.Id,
                ServerId = appearance.Id,
            };

            item.Attributes["Type"] = appearance.Type;

            foreach (var group in appearance.FrameGroups)
            {
                IEnumerable<Frame> frames = options.EnableFrameGroups
                    ? group.Frames
                    : group.Frames.Take(1);

                foreach (var frame in frames)
                {
                    int duration = options.EnableFrameDurations ? frame.Duration : group.DefaultDuration;
                    item.Attributes[$"Frame_{item.Attributes.Count}"] = duration.ToString(CultureInfo.InvariantCulture);
                    foreach (int spriteId in frame.SpriteIds)
                    {
                        if (spriteId - 1 < 0 || spriteId - 1 >= sprites.Count)
                        {
                            continue;
                        }

                        var sprite = sprites[spriteId - 1].WithId(nextSpriteId++);
                        spriteList.Add(sprite);
                    }
                }
            }

            if (options.IdleAnimationAsStatic && item.Attributes.Count == 1)
            {
                item.Attributes["Type"] = "Static";
            }

            itemList.Add(item);
        }

        return (itemList, spriteList);
    }
}

/// <summary>
/// Configuration options for the legacy mapper.
/// </summary>
/// <param name="EnableFrameDurations">True to preserve per frame durations.</param>
/// <param name="EnableFrameGroups">True to keep multiple frame groups.</param>
/// <param name="IdleAnimationAsStatic">True to flatten idle groups into static items.</param>
public readonly record struct V11ToLegacyOptions(bool EnableFrameDurations, bool EnableFrameGroups, bool IdleAnimationAsStatic);
