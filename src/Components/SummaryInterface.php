<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

/**
 * Represents a summary of files.
 */
interface SummaryInterface
{
    /**
     * Gets the size of all the files.
     *
     * @return int
     */
    public function getSize(): int;

    /**
     * Gets the total number of files.
     *
     * @return int
     */
    public function getFileCount(): int;
}
