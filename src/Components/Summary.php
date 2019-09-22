<?php declare(strict_types = 1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\SummaryInterface;

/**
 * Represents a summary of files.
 */
final class Summary implements SummaryInterface
{
    /**
     * @var int
     */
    private $size = null;

    /**
     * @var int
     */
    private $fileCount = null;

    /**
     * @param int $size
     * @param int $fileCount
     */
    public function __construct(int $size, int $fileCount)
    {
        $this->size = $size;
        $this->fileCount = $fileCount;
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * {@inheritDoc}
     */
    public function getFileCount(): int
    {
        return $this->fileCount;
    }
}
