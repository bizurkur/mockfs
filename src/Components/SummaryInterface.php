<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

/**
 * Represents a summary of files.
 */
interface SummaryInterface
{
    /**
     * Gets the size of all the files.
     */
    public function getSize(): int;

    /**
     * Gets the total number of files.
     */
    public function getFileCount(): int;
}
