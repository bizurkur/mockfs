<?php

declare(strict_types=1);

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
final class NullContent extends AbstractContent
{
    // phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

    public function read(int $count): string
    {
        return '';
    }

    // phpcs:enable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

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
        return true;
    }

    public function getSize(): int
    {
        return 0;
    }
}
