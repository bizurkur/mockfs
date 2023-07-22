<?php

declare(strict_types=1);

namespace MockFileSystem\Components\RegularFile;

use MockFileSystem\Components\RegularFile\AbstractProxyDecorator;

/**
 * Decorator class to allow multiple instances of the same file to be open.
 *
 * It works by tracking and restoring the position in the file for each specific
 * instance created, even though the underlying file is shared.
 *
 * $file = new RegularFile('somefile');
 *
 * $instanceA = new MultiInstanceDecorator($file);
 * $instanceB = new MultiInstanceDecorator($file);
 *
 * $instanceA->seek(50);
 * $instanceA->tell(); // 50
 * $instanceB->tell(); // 0
 *
 * $instanceB->seek(3);
 * $instanceB->tell(); // 3
 * $instanceA->tell(); // 50
 */
final class MultiInstanceDecorator extends AbstractProxyDecorator
{
    private int $position = 0;

    public function read(int $count): string
    {
        $this->base->seek($this->position);
        $data = $this->base->read($count);
        $this->position = $this->base->tell();

        return $data;
    }

    public function write(string $data): int
    {
        $this->base->seek($this->position);
        $bytes = $this->base->write($data);
        $this->position = $this->base->tell();

        return $bytes;
    }

    public function truncate(int $size): bool
    {
        $this->base->seek($this->position);

        return $this->base->truncate($size);
    }

    public function seek(int $offset, int $whence = \SEEK_SET): bool
    {
        if ($whence !== \SEEK_SET) {
            $this->base->seek($this->position);
        }

        $success = $this->base->seek($offset, $whence);
        $this->position = $this->base->tell();

        return $success;
    }

    public function tell(): int
    {
        $this->base->seek($this->position);

        $this->position = $this->base->tell();

        return $this->position;
    }

    public function isEof(): bool
    {
        $this->base->seek($this->position);

        return $this->base->isEof();
    }
}
