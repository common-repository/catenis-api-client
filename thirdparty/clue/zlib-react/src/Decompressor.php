<?php

namespace Catenis\WP\Clue\React\Zlib;

use Catenis\WP\Clue\StreamFilter as Filter;

/**
 * The `Decompressor` class can be used to decompress a stream of data.
 *
 * It implements the [`DuplexStreamInterface`](https://github.com/reactphp/stream#duplexstreaminterface)
 * and accepts compressed data on its writable side and emits decompressed data
 * on its readable side.
 *
 * ```php
 * $encoding = ZLIB_ENCODING_GZIP; // or ZLIB_ENCODING_RAW or ZLIB_ENCODING_DEFLATE
 * $decompressor = new Catenis\WP\Clue\React\Zlib\Decompressor($encoding);
 *
 * $decompressor->on('data', function ($data) {
 *     echo $data; // decompressed data chunk
 * });
 *
 * $decompressor->write($compressed); // write compressed binary data chunk
 * ```
 *
 * This is particularly useful in a piping context:
 *
 * ```php
 * $input->pipe($decompressor)->pipe($filterBadWords)->pipe($output);
 * ```
 *
 * For more details, see ReactPHP's
 * [`DuplexStreamInterface`](https://github.com/reactphp/stream#duplexstreaminterface).
 *
 * >   Internally, it implements the deprecated `ZlibFilterStream` class only for
 *     BC reasons. For best forwards compatibility, you should only rely on it
 *     implementing the `DuplexStreamInterface`.
 */
final class Decompressor extends ZlibFilterStream
{
    /**
     * @param int $encoding ZLIB_ENCODING_GZIP, ZLIB_ENCODING_RAW or ZLIB_ENCODING_DEFLATE
     */
    public function __construct($encoding)
    {
        parent::__construct(
            Filter\fun('zlib.inflate', array('window' => $encoding))
        );
    }
}
