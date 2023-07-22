<?php

declare(strict_types=1);

namespace MockFileSystem\Content;

use MockFileSystem\Content\AbstractContent;

/**
 * Content adapter to mimic the behavior of /dev/zero.
 *
 * - Read actions will return infinite null bytes.
 * - Write actions get discarded but report as success.
 *
 * @see https://en.wikipedia.org/wiki//dev/zero
 */
final class ZeroContent extends AbstractContent
{
    public function read(int $count): string
    {
        return str_repeat("\0", $count);
    }

    public function write(string $data): int
    {
        return strlen($data);
    }

    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

    public function truncate(int $size): bool
    {
        return true;
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
