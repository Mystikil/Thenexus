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
/// Writes the simplified JSON appearance format.
/// </summary>
public sealed class AppearancesWriter
{
    private static readonly JsonSerializerOptions Options = new()
    {
        PropertyNamingPolicy = JsonNamingPolicy.CamelCase,
        WriteIndented = true,
    };

    /// <summary>
    /// Serializes the provided appearance list to the stream.
    /// </summary>
    /// <param name="appearances">The appearances to write.</param>
    /// <param name="stream">The destination stream.</param>
    public void Write(IEnumerable<Appearance> appearances, Stream stream)
    {
        ArgumentNullException.ThrowIfNull(appearances);
        ArgumentNullException.ThrowIfNull(stream);
        var model = new
        {
            appearances = appearances.Select(a => new
            {
                id = a.Id,
                type = a.Type,
                frameGroups = a.FrameGroups.Select(g => new
                {
                    groupType = g.GroupType,
                    defaultDuration = g.DefaultDuration,
                    frames = g.Frames.Select(f => new
                    {
                        duration = f.Duration,
                        spriteIds = f.SpriteIds,
                    }),
                }),
            }),
        };

        using var writer = new Utf8JsonWriter(stream, new JsonWriterOptions { Indented = Options.WriteIndented });
        JsonSerializer.Serialize(writer, model, Options);
    }
}
