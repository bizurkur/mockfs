<?php

declare(strict_types=1);

namespace MockFileSystem\Content;

use MockFileSystem\Content\AbstractContent;

/**
 * Content adapter to mimic the behavior of /dev/full.
 *
 * - Read actions will return infinite null bytes.
 * - Write actions result in failure due to "No space left on device."
 *
 * @see https://en.wikipedia.org/wiki//dev/full
 */
final class FullContent extends AbstractContent
{
    public function read(int $count): string
    {
        return str_repeat("\0", $count);
    }

    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

    public function write(string $data): int
    {
        return 0;
    }

    public function truncate(int $size): bool
    {
        return false;
    }

    // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

    public function isEof(): bool
    {
        return false;
    }

    public function getSize(): int
    {
        return 0;
    }
}
