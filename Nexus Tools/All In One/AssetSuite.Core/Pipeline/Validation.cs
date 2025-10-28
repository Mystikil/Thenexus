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

namespace AssetSuite.Core.Pipeline;

/// <summary>
/// Provides lightweight validation helpers for asset bundles.
/// </summary>
public static class Validation
{
    /// <summary>
    /// Validates the correspondence between sprites and item definitions.
    /// </summary>
    /// <param name="items">The item definitions.</param>
    /// <param name="sprites">The sprite collection.</param>
    /// <returns>A list of validation error strings.</returns>
    public static IReadOnlyList<string> ValidateLegacyPair(IEnumerable<ItemType> items, IEnumerable<Sprite> sprites)
    {
        ArgumentNullException.ThrowIfNull(items);
        ArgumentNullException.ThrowIfNull(sprites);
        var errors = new List<string>();
        var spriteList = sprites.ToList();
        var itemList = items.ToList();
        if (spriteList.Count == 0)
        {
            errors.Add("Sprite archive is empty.");
        }

        if (itemList.Count == 0)
        {
            errors.Add("Item list is empty.");
        }

        if (spriteList.Any(s => s.Width <= 0 || s.Height <= 0))
        {
            errors.Add("One or more sprites have invalid dimensions.");
        }

        int highestSpriteId = spriteList.Count > 0 ? spriteList.Max(s => s.Id) : 0;
        foreach (var item in itemList)
        {
            foreach (var attribute in item.Attributes)
            {
                if (attribute.Key.StartsWith("Frame_", StringComparison.OrdinalIgnoreCase) &&
                    !int.TryParse(attribute.Value, out _))
                {
                    errors.Add($"Item {item.ClientId} frame attribute is not numeric.");
                }
            }

            if (item.ClientId <= 0)
            {
                errors.Add($"Item {item.ServerId} has an invalid client identifier.");
            }
        }

        if (highestSpriteId != spriteList.Count)
        {
            errors.Add("Sprite identifiers must be sequential without gaps.");
        }

        return errors;
    }
}
