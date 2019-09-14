<?php declare(strict_types = 1);

namespace MockFileSystem\Content;

use MockFileSystem\Content\AbstractContent;

/**
 * Content adapter to mimic the behavior of /dev/null.
 *
 * - Read actions provide no data.
 * - Write actions get discarded but report as success.
 *
 * @see https://en.wikipedia.org/wiki/Null_device
 */
class NullContent extends AbstractContent
{
    /**
     * {@inheritDoc}
     */
    public function read(int $count): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $data): int
    {
        return mb_strlen($data);
    }

    /**
     * {@inheritDoc}
     */
    public function truncate(int $size): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function isEof(): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): int
    {
        return 0;
    }
}
