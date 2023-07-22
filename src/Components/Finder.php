<?php

declare(strict_types=1);

namespace MockFileSystem\Components;

use MockFileSystem\Components\ContainerInterface;
use MockFileSystem\Components\FileInterface;
use MockFileSystem\Components\FileSystemInterface;
use MockFileSystem\Components\FinderInterface;

/**
 * Class to find files within the file system.
 */
class Finder implements FinderInterface
{
    private FileSystemInterface $fileSystem;

    public function __construct(FileSystemInterface $fileSystem)
    {
        $this->fileSystem = $fileSystem;
    }

    public function find(string $path): ?FileInterface
    {
        $parts = $this->getParts($path);

        /** @var FileInterface $file */
        $file = $this->fileSystem;

        while ($file) {
            $part = array_shift($parts);
            if ($part === null) {
                return $file;
            }

            if (!$file instanceof ContainerInterface) {
                return null;
            }

            $file = $this->getNextPart($file, $part);
        }

        return null;
    }

    /**
     * Splits a path into pieces.
     *
     * @return string[]
     */
    private function getParts(string $path): array
    {
        $clean = $this->fileSystem->getPath($path);
        $separator = $this->fileSystem->getConfig()->getFileSeparator();

        $parts = explode($separator, $clean);
        if ($parts === false) {
            return [$clean];
        }

        return $parts;
    }

    /**
     * Gets the next piece of the path.
     */
    private function getNextPart(ContainerInterface $file, string $part): ?FileInterface
    {
        if (!$file->hasChild($part)) {
            return null;
        }

        return $file->getChild($part);
    }
}
