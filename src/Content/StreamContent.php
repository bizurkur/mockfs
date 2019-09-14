<?php declare(strict_types = 1);

namespace MockFileSystem\Content;

use MockFileSystem\Content\AbstractContent;
use MockFileSystem\Exception\InvalidArgumentException;

/**
 * Content from a file stream.
 */
class StreamContent extends AbstractContent
{
    /**
     * @var resource
     */
    private $stream = null;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException(
                sprintf('Expected a resource; %s given.', gettype($stream))
            );
        }

        $this->stream = $stream;
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        // Do not close the actual stream, as we have no way of getting it back.
        // Simply release any lock instead.
        flock($this->stream, \LOCK_UN | \LOCK_NB);
    }

    /**
     * {@inheritDoc}
     */
    public function read(int $count): string
    {
        $data = fread($this->stream, $count);
        if ($data === false) {
            return '';
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $data): int
    {
        $bytes = fwrite($this->stream, $data);
        if ($bytes === false) {
            return 0;
        }

        return $bytes;
    }

    /**
     * {@inheritDoc}
     */
    public function truncate(int $size): bool
    {
        return ftruncate($this->stream, $size);
    }

    /**
     * {@inheritDoc}
     */
    public function seek(int $offset, int $whence = \SEEK_SET): bool
    {
        $success = fseek($this->stream, $offset, $whence);

        return $success === 0;
    }

    /**
     * {@inheritDoc}
     */
    public function tell(): int
    {
        $position = ftell($this->stream);
        if ($position === false) {
            return 0;
        }

        return $position;
    }

    /**
     * {@inheritDoc}
     */
    public function isEof(): bool
    {
        return feof($this->stream);
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): int
    {
        $stat = fstat($this->stream);
        if ($stat === false) {
            return 0;
        }

        return $stat['size'];
    }
}
