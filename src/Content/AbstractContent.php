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
    protected int $position = 0;

    public function open(): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    /**
     * @param int<1,max> $count
     */
    abstract public function read(int $count): string;

    abstract public function write(string $data): int;

    /**
     * @param int<0,max> $size
     */
    abstract public function truncate(int $size): bool;

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

    public function tell(): int
    {
        return $this->position;
    }

    public function isEof(): bool
    {
        return $this->position >= $this->getSize();
    }

    public function flush(): bool
    {
        return true;
    }

    abstract public function getSize(): int;

    public function unlink(): bool
    {
        return true;
    }
}
