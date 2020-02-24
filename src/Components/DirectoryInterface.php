<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\FileInterface;

/**
 * Represents a single directory.
 */
interface DirectoryInterface extends ContainerInterface, FileInterface, \IteratorAggregate
{
    /**
     * Gets the total number of files (children) in this directory only.
     *
     * Does not include files in subdirectories.
     *
     * @return int
     */
    public function getFileCount(): int;

    /**
     * Gets the directory children as an iterator.
     *
     * @return \Iterator
     */
    public function getIterator(): \Iterator;

    /**
     * Checks if this directory is a dot file.
     *
     * @return bool True if directory name is "." or ".."
     */
    public function isDot(): bool;
}
