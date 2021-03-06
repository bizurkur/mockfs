<?php

declare(strict_types=1);

namespace MockFileSystem\Content;

use MockFileSystem\Content\ContentInterface;

/**
 * Abstract class to define the base methods.
 *
 * Read, write, truncate, and getSize are likely the only methods to always
 * change between different implementations.
 */
abstract class AbstractContent implements ContentInterface
{
    /**
     * @var int
     */
    protected $position = 0;

    /**
     * {@inheritDoc}
     */
    public function open(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    abstract public function read(int $count): string;

    /**
     * {@inheritDoc}
     */
    abstract public function write(string $data): int;

    /**
     * {@inheritDoc}
     */
    abstract public function truncate(int $size): bool;

    /**
     * {@inheritDoc}
     */
    public function seek(int $offset, int $whence = \SEEK_SET): bool
    {
        $position = $this->position;

        if ($whence === \SEEK_SET) {
            $position = $offset;
        } elseif ($whence === \SEEK_CUR) {
            $position += $offset;
        } elseif ($whence === \SEEK_END) {
            $position = $this->getSize() + $offset;
        } else {
            return false;
        }

        if ($position < 0 || $position > $this->getSize()) {
            $this->position = 0;

            return false;
        }

        $this->position = $position;

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function tell(): int
    {
        return $this->position;
    }

    /**
     * {@inheritDoc}
     */
    public function isEof(): bool
    {
        return $this->position >= $this->getSize();
    }

    /**
     * {@inheritDoc}
     */
    public function flush(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    abstract public function getSize(): int;

    /**
     * {@inheritDoc}
     */
    public function unlink(): bool
    {
        return true;
    }
}
