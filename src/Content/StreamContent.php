<?php

declare(strict_types=1);

namespace MockFileSystem\Content;

use MockFileSystem\Content\AbstractContent;
use MockFileSystem\Exception\InvalidArgumentException;

/**
 * Content from a file stream.
 */
final class StreamContent extends AbstractContent
{
    /**
     * @var resource
     */
    private $stream;

    /**
     * @param resource|string $stream
     */
    public function __construct($stream)
    {
        $content = null;
        if (is_string($stream)) {
            $content = $stream;
            $stream = fopen('php://temp', 'rb+');
        }

        if (!is_resource($stream)) {
            throw new InvalidArgumentException(
                sprintf('Expected a resource; %s given.', gettype($stream))
            );
        }

        if ($content !== null) {
            fwrite($stream, $content);
            fseek($stream, 0, \SEEK_SET);
        }

        $this->stream = $stream;
    }

    public function close(): bool
    {
        // Do not close the actual stream, as we have no way of getting it back.
        // Simply release any lock instead.
        flock($this->stream, \LOCK_UN | \LOCK_NB);

        return true;
    }

    public function read(int $count): string
    {
        $data = fread($this->stream, $count);
        if ($data === false) {
            return '';
        }

        return $data;
    }

    public function write(string $data): int
    {
        $bytes = fwrite($this->stream, $data);
        if ($bytes === false) {
            return 0;
        }

        return $bytes;
    }

    public function truncate(int $size): bool
    {
        return ftruncate($this->stream, $size);
    }

    public function seek(int $offset, int $whence = \SEEK_SET): bool
    {
        $success = fseek($this->stream, $offset, $whence);

        return $success === 0;
    }

    public function tell(): int
    {
        $position = ftell($this->stream);
        if ($position === false) {
            return 0;
        }

        return $position;
    }

    public function isEof(): bool
    {
        return feof($this->stream);
    }

    public function flush(): bool
    {
        return fflush($this->stream);
    }

    public function getSize(): int
    {
        $stat = fstat($this->stream);
        if ($stat === false) {
            return 0;
        }

        return $stat['size'];
    }
}
