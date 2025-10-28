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
/// Reads simplified 11+ appearance definitions stored as JSON.
/// </summary>
public sealed class AppearancesReader
{
    /// <summary>
    /// Reads the appearance set from the supplied stream.
    /// </summary>
    /// <param name="stream">The appearance stream.</param>
    /// <returns>The list of appearances.</returns>
    public IReadOnlyList<Appearance> Read(Stream stream)
    {
        ArgumentNullException.ThrowIfNull(stream);
        using var document = JsonDocument.Parse(stream);
        var appearances = new List<Appearance>();
        foreach (var element in document.RootElement.GetProperty("appearances").EnumerateArray())
        {
            var appearance = new Appearance
            {
                Id = element.GetProperty("id").GetInt32(),
                Type = element.GetProperty("type").GetString() ?? string.Empty,
            };

            if (element.TryGetProperty("frameGroups", out var groups))
            {
                foreach (var group in groups.EnumerateArray())
                {
                    var frameGroup = new FrameGroup
                    {
                        GroupType = group.GetProperty("groupType").GetString() ?? string.Empty,
                        DefaultDuration = group.TryGetProperty("defaultDuration", out var duration) ? duration.GetInt32() : 100,
                    };

                    if (group.TryGetProperty("frames", out var frames))
                    {
                        foreach (var frame in frames.EnumerateArray())
                        {
                            var frameModel = new Frame
                            {
                                Duration = frame.TryGetProperty("duration", out var fDuration) ? fDuration.GetInt32() : frameGroup.DefaultDuration,
                            };

                            if (frame.TryGetProperty("spriteIds", out var spriteIds))
                            {
                                foreach (var id in spriteIds.EnumerateArray())
                                {
                                    frameModel.SpriteIds.Add(id.GetInt32());
                                }
                            }

                            frameGroup.Frames.Add(frameModel);
                        }
                    }

                    appearance.FrameGroups.Add(frameGroup);
                }
            }

            appearances.Add(appearance);
        }

        return appearances;
    }
}
