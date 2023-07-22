<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\RegularFile;

/**
 * Class to represent a block device.
 */
final class Block extends RegularFile
{
    public function getType(): int
    {
        return self::TYPE_BLOCK;
    }
}
