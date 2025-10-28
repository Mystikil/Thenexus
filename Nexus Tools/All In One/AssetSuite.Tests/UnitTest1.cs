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

using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using AssetSuite.Core.Legacy;
using AssetSuite.Core.Models;
using AssetSuite.Core.Pipeline;
using AssetSuite.Core.V11;
using AssetSuite.Core.Imaging;
using SixLabors.ImageSharp;
using SixLabors.ImageSharp.PixelFormats;
using SixLabors.ImageSharp.Processing;
using System.Text.Json;

namespace AssetSuite.Tests;

/// <summary>
/// Regression tests covering core serialization pipelines.
/// </summary>
public class RegressionTests
{
    /// <summary>
    /// Ensures that writing and reading a legacy sprite archive yields identical pixel data.
    /// </summary>
    [Fact]
    public void Sprites_RoundTrip_IsStable()
    {
        var sprites = FixtureFactory.CreateSampleSprites(8, 32, 32);
        using var stream = new MemoryStream();
        var writer = new SprLegacyWriter();
        writer.WriteAll(sprites, stream);
        stream.Position = 0;
        var reader = new SprLegacyReader();
        var decoded = reader.ReadAll(stream);
        Assert.Equal(sprites.Count, decoded.Count);
        for (int i = 0; i < sprites.Count; i++)
        {
            Assert.Equal(sprites[i].Rgba, decoded[i].Rgba);
        }

        using var rewritten = new MemoryStream();
        writer.WriteAll(decoded, rewritten);
        Assert.Equal(stream.ToArray(), rewritten.ToArray());
    }

    /// <summary>
    /// Verifies that DAT serialization preserves item identity and attributes.
    /// </summary>
    [Fact]
    public void Dat_RoundTrip_PreservesItems()
    {
        var items = FixtureFactory.CreateSampleItems(5);
        using var stream = new MemoryStream();
        var writer = new DatLegacyWriter();
        writer.WriteAll(items, stream);
        stream.Position = 0;
        var reader = new DatLegacyReader();
        var roundtrip = reader.ReadAll(stream);
        Assert.Equal(items.Count, roundtrip.Count);
        for (int i = 0; i < items.Count; i++)
        {
            Assert.Equal(items[i].ClientId, roundtrip[i].ClientId);
            Assert.Equal(items[i].Attributes, roundtrip[i].Attributes);
        }
    }

    /// <summary>
    /// Confirms that appearance metadata can be written and read without loss.
    /// </summary>
    [Fact]
    public void Appearances_RoundTrip_PreservesMetadata()
    {
        var appearances = FixtureFactory.CreateSampleAppearances();
        using var stream = new MemoryStream();
        var writer = new AppearancesWriter();
        writer.Write(appearances, stream);
        stream.Position = 0;
        var reader = new AppearancesReader();
        var roundtrip = reader.Read(stream);
        Assert.Equal(appearances.Count, roundtrip.Count);
        for (int i = 0; i < appearances.Count; i++)
        {
            Assert.Equal(appearances[i].Id, roundtrip[i].Id);
            Assert.Equal(appearances[i].FrameGroups.Count, roundtrip[i].FrameGroups.Count);
        }
    }

    /// <summary>
    /// Slicing a 64x64 tile sheet into 32x32 sprites should produce four tiles.
    /// </summary>
    [Fact]
    public void SpriteSlicer_SlicesExpectedCount()
    {
        using var image = new Image<Rgba32>(64, 64);
        image.ProcessPixelRows(accessor =>
        {
            for (int y = 0; y < accessor.Height; y++)
            {
                var row = accessor.GetRowSpan(y);
                for (int x = 0; x < row.Length; x++)
                {
                    row[x] = new Rgba32(255, 0, 0, 255);
                }
            }
        });
        using var stream = new MemoryStream();
        image.SaveAsPng(stream);
        stream.Position = 0;
        using var bitmap = Bitmap.Load(stream);
        var slices = SpriteSlicer.SliceSheet(bitmap).ToList();
        Assert.Equal(4, slices.Count);
    }
}

/// <summary>
/// Test fixture factory for synthetic data.
/// </summary>
internal static class FixtureFactory
{
    public static List<Sprite> CreateSampleSprites(int count, int width, int height)
    {
        var fixturePath = Path.Combine(AppContext.BaseDirectory, "Fixtures", "sprites.json");
        if (File.Exists(fixturePath))
        {
            using var document = JsonDocument.Parse(File.ReadAllText(fixturePath));
            int w = document.RootElement.GetProperty("width").GetInt32();
            int h = document.RootElement.GetProperty("height").GetInt32();
            var sprites = new List<Sprite>();
            foreach (var element in document.RootElement.GetProperty("sprites").EnumerateArray())
            {
                int id = element.GetProperty("id").GetInt32();
                var rgba = element.GetProperty("rgba").EnumerateArray().Select(v => (byte)v.GetInt32()).ToArray();
                sprites.Add(new Sprite(id, w, h, rgba));
            }

            return sprites;
        }

        var list = new List<Sprite>();
        var random = new Random(42);
        for (int i = 0; i < count; i++)
        {
            byte[] pixels = new byte[width * height * 4];
            random.NextBytes(pixels);
            for (int p = 0; p < pixels.Length; p += 4)
            {
                pixels[p + 3] = 255;
            }

            list.Add(new Sprite(i + 1, width, height, pixels));
        }

        return list;
    }

    public static List<ItemType> CreateSampleItems(int count)
    {
        var fixturePath = Path.Combine(AppContext.BaseDirectory, "Fixtures", "items.json");
        if (File.Exists(fixturePath))
        {
            var items = new List<ItemType>();
            using var document = JsonDocument.Parse(File.ReadAllText(fixturePath));
            foreach (var element in document.RootElement.EnumerateArray())
            {
                var item = new ItemType
                {
                    ClientId = element.GetProperty("clientId").GetInt32(),
                    ServerId = element.GetProperty("serverId").GetInt32(),
                };

                foreach (var attribute in element.GetProperty("attributes").EnumerateObject())
                {
                    item.Attributes[attribute.Name] = attribute.Value.GetString() ?? string.Empty;
                }

                items.Add(item);
            }

            return items;
        }

        var list = new List<ItemType>();
        for (int i = 0; i < count; i++)
        {
            var item = new ItemType { ClientId = 1000 + i, ServerId = 2000 + i };
            item.Attributes[$"Attr{i}"] = i.ToString();
            list.Add(item);
        }

        return list;
    }

    public static List<Appearance> CreateSampleAppearances()
    {
        var fixturePath = Path.Combine(AppContext.BaseDirectory, "Fixtures", "appearances.json");
        if (File.Exists(fixturePath))
        {
            using var stream = File.OpenRead(fixturePath);
            var reader = new AppearancesReader();
            return reader.Read(stream).ToList();
        }

        var sprites = CreateSampleSprites(3, 32, 32);
        return new List<Appearance>
        {
            new Appearance
            {
                Id = 1,
                Type = "Item",
                FrameGroups =
                {
                    new FrameGroup
                    {
                        GroupType = "Idle",
                        Frames =
                        {
                            new Frame { Duration = 100, SpriteIds = { sprites[0].Id, sprites[1].Id } },
                        },
                    },
                },
            },
        };
    }
}
