<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\RegularFile;
use MockFileSystem\Content\ContentInterface;

/**
 * Class to represent a block device.
 */
class Block extends RegularFile
{
    /**
     * @param string $name
     * @param int|null $permissions
     * @param ContentInterface|null $content
     */
    public function __construct(
        string $name,
        ?int $permissions = null,
        ?ContentInterface $content = null
    ) {
        parent::__construct($name, $permissions, $content);
        $this->type = self::TYPE_BLOCK;
    }
}
