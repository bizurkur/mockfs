<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\RegularFile;

/**
 * Class to represent a block device.
 */
class Block extends RegularFile
{
    /**
     * @param string $name
     * @param int|null $permissions
     */
    public function __construct(string $name, ?int $permissions = null)
    {
        parent::__construct($name, $permissions);
        $this->type = self::TYPE_BLOCK;
    }
}
