<?php declare(strict_types = 1);

namespace MockFileSystem\Content;

use MockFileSystem\Content\AbstractContent;

/**
 * Content adapter to mimic the behavior of /dev/random.
 *
 * - Read actions will return infinite pseudo-random bytes.
 * - Write actions get discarded but report as success.
 *
 * @see https://en.wikipedia.org/wiki//dev/random
 */
class RandomContent extends AbstractContent
{
    /**
     * {@inheritDoc}
     */
    public function read(int $count): string
    {
        return random_bytes($count);
    }

    /**
     * {@inheritDoc}
     */
    public function write(string $data): int
    {
        return mb_strlen($data);
    }

    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

    /**
     * {@inheritDoc}
     */
    public function truncate(int $size): bool
    {
        return true;
    }

    // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

    /**
     * {@inheritDoc}
     */
    public function isEof(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): int
    {
        return 0;
    }
}
