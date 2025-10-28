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

namespace AssetSuite.Core.IO;

/// <summary>
/// Provides fast little-endian binary reading helpers with defensive bounds checking.
/// </summary>
public sealed class EndianBinaryReader : IDisposable
{
    private readonly Stream _stream;
    private readonly byte[] _buffer = new byte[8];
    private readonly bool _leaveOpen;

    /// <summary>
    /// Initializes a new instance of the <see cref="EndianBinaryReader"/> class.
    /// </summary>
    /// <param name="stream">The input stream.</param>
    /// <param name="leaveOpen">If set to <c>true</c> the stream is not disposed when this reader is disposed.</param>
    public EndianBinaryReader(Stream stream, bool leaveOpen = false)
    {
        _stream = stream ?? throw new ArgumentNullException(nameof(stream));
        _leaveOpen = leaveOpen;
    }

    /// <summary>
    /// Reads a 32-bit little-endian integer.
    /// </summary>
    /// <returns>The value.</returns>
    public int ReadInt32()
    {
        FillBuffer(sizeof(int));
        return BinaryPrimitives.ReadInt32LittleEndian(_buffer);
    }

    /// <summary>
    /// Reads a 16-bit little-endian integer.
    /// </summary>
    /// <returns>The value.</returns>
    public short ReadInt16()
    {
        FillBuffer(sizeof(short));
        return BinaryPrimitives.ReadInt16LittleEndian(_buffer);
    }

    /// <summary>
    /// Reads a byte.
    /// </summary>
    /// <returns>The value.</returns>
    public byte ReadByte()
    {
        int value = _stream.ReadByte();
        if (value < 0)
        {
            throw new EndOfStreamException();
        }

        return (byte)value;
    }

    /// <summary>
    /// Reads a 32-bit little-endian unsigned integer.
    /// </summary>
    /// <returns>The value.</returns>
    public uint ReadUInt32()
    {
        FillBuffer(sizeof(uint));
        return BinaryPrimitives.ReadUInt32LittleEndian(_buffer);
    }

    /// <summary>
    /// Reads a length-prefixed string using UTF-8 encoding.
    /// </summary>
    /// <returns>The value.</returns>
    public string ReadString()
    {
        int length = ReadInt32();
        if (length < 0)
        {
            throw new InvalidDataException("String length cannot be negative.");
        }

        Span<byte> tmp = length <= 512 ? stackalloc byte[length] : new byte[length];
        FillSpan(tmp);
        return Encoding.UTF8.GetString(tmp);
    }

    /// <summary>
    /// Reads the specified number of bytes from the stream.
    /// </summary>
    /// <param name="buffer">The buffer to fill.</param>
    public void ReadExactly(Span<byte> buffer)
    {
        FillSpan(buffer);
    }

    private void FillSpan(Span<byte> span)
    {
        int read = 0;
        while (read < span.Length)
        {
            int result = _stream.Read(span[read..]);
            if (result == 0)
            {
                throw new EndOfStreamException();
            }

            read += result;
        }
    }

    private void FillBuffer(int count)
    {
        FillSpan(_buffer.AsSpan(0, count));
    }

    /// <inheritdoc />
    public void Dispose()
    {
        if (!_leaveOpen)
        {
            _stream.Dispose();
        }
    }
}
