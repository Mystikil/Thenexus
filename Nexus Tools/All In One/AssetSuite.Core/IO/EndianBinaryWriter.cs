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
/// Provides fast little-endian binary writing helpers with defensive bounds checking.
/// </summary>
public sealed class EndianBinaryWriter : IDisposable
{
    private readonly Stream _stream;
    private readonly byte[] _buffer = new byte[8];
    private readonly bool _leaveOpen;

    /// <summary>
    /// Initializes a new instance of the <see cref="EndianBinaryWriter"/> class.
    /// </summary>
    /// <param name="stream">The destination stream.</param>
    /// <param name="leaveOpen">If set to <c>true</c> the stream is left open.</param>
    public EndianBinaryWriter(Stream stream, bool leaveOpen = false)
    {
        _stream = stream ?? throw new ArgumentNullException(nameof(stream));
        _leaveOpen = leaveOpen;
    }

    /// <summary>
    /// Writes a 32-bit little-endian integer.
    /// </summary>
    /// <param name="value">The value to write.</param>
    public void Write(int value)
    {
        BinaryPrimitives.WriteInt32LittleEndian(_buffer, value);
        _stream.Write(_buffer, 0, sizeof(int));
    }

    /// <summary>
    /// Writes a 16-bit little-endian integer.
    /// </summary>
    /// <param name="value">The value to write.</param>
    public void Write(short value)
    {
        BinaryPrimitives.WriteInt16LittleEndian(_buffer, value);
        _stream.Write(_buffer, 0, sizeof(short));
    }

    /// <summary>
    /// Writes a byte value.
    /// </summary>
    /// <param name="value">The value to write.</param>
    public void Write(byte value)
    {
        _stream.WriteByte(value);
    }

    /// <summary>
    /// Writes a 32-bit unsigned integer.
    /// </summary>
    /// <param name="value">The value to write.</param>
    public void Write(uint value)
    {
        BinaryPrimitives.WriteUInt32LittleEndian(_buffer, value);
        _stream.Write(_buffer, 0, sizeof(uint));
    }

    /// <summary>
    /// Writes a length-prefixed UTF-8 string.
    /// </summary>
    /// <param name="value">The string to write.</param>
    public void Write(string value)
    {
        ArgumentNullException.ThrowIfNull(value);
        int byteCount = Encoding.UTF8.GetByteCount(value);
        Write(byteCount);
        Span<byte> tmp = byteCount <= 512 ? stackalloc byte[byteCount] : new byte[byteCount];
        Encoding.UTF8.GetBytes(value, tmp);
        _stream.Write(tmp);
    }

    /// <summary>
    /// Writes raw bytes to the stream.
    /// </summary>
    /// <param name="buffer">The buffer to write.</param>
    public void Write(ReadOnlySpan<byte> buffer)
    {
        _stream.Write(buffer);
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
