<?php

declare(strict_types=1);

namespace MockFileSystem\Visitor;

use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystemInterface;

interface VisitorInterface
{
    /**
     * Visits a file.
     *
     * @param FileInterface $file
     *
     * @return VisitorInterface
     */
    public function visit(FileInterface $file): VisitorInterface;

    /**
     * Visits the entire file system.
     *
     * @param FileSystemInterface $fileSystem
     *
     * @return VisitorInterface
     */
    public function visitFileSystem(FileSystemInterface $fileSystem): VisitorInterface;
}
