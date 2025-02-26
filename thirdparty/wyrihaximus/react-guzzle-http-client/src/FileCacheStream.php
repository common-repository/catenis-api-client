<?php

/**
 * This file is part of ReactGuzzleRing.
 *
 ** (c) 2015 Cees-Jan Kiewiet
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Catenis\WP\WyriHaximus\React\Guzzle\HttpClient;

use Catenis\WP\GuzzleHttp\Stream\StreamInterface;
use Catenis\WP\GuzzleHttp\Stream\Utils as GuzzleUtils;
use Catenis\WP\React\Filesystem\Filesystem;

/**
 * Class FileCacheStream
 *
 * @package Catenis\WP\WyriHaximus\React\RingPHP\HttpClient
 */
class FileCacheStream
{
    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var \Catenis\WP\React\Filesystem\Node\File
     */
    protected $file;

    /**
     * @var \Catenis\WP\React\Filesystem\Stream
     */
    protected $stream;

    protected $open = false;
    protected $opening = false;

    public function __construct(array $options)
    {
        $this->filesystem = Filesystem::create($options['loop']);
    }

    public function open()
    {
        $this->opening = true;
        $file = $this->filesystem->file($this->getRandomFilename());
        return $file->exists()->then(function () {
            return $this->open();
        }, function () use ($file) {
            return $file->create()->open('cw')->then(function ($stream) {
                $this->open = true;
                $this->opening = false;
                $this->stream = $stream;
            });
        });
    }

    protected function getRandomFilename()
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('wyrihaximus-react-guzzle-ring-', true);
    }

    public function isOpen()
    {
        return $this->open;
    }

    public function isOpening()
    {
        return $this->opening;
    }

    public function write($data)
    {
        $this->stream->write($data);
    }

    public function resume()
    {
        $this->stream->resume();
    }
}
