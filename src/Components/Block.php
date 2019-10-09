<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\RegularFile;
use MockFileSystem\Content\ContentInterface;

/**
 * Class to represent a block device.
 */
final class Block extends RegularFile
{
    /**
     * @param string $name
     * @param int|null $permissions
     * @param ContentInterface|string|null $content
     */
    public function __construct(
        string $name,
        ?int $permissions = null,
        $content = null
    ) {
        parent::__construct($name, $permissions, $content);
        $this->type = self::TYPE_BLOCK;
    }
}
