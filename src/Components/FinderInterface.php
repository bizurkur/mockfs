<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\FileInterface;

/**
 * Represents a service for finding files.
 */
interface FinderInterface
{
    /**
     * Finds a file, if it exists.
     */
    public function find(string $path): ?FileInterface;
}
