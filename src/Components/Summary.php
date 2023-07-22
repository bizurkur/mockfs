<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\SummaryInterface;

/**
 * Represents a summary of files.
 */
final class Summary implements SummaryInterface
{
    private int $size;

    private int $fileCount;

    public function __construct(int $size, int $fileCount)
    {
        $this->size = $size;
        $this->fileCount = $fileCount;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getFileCount(): int
    {
        return $this->fileCount;
    }
}
